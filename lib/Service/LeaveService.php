<?php
declare(strict_types=1);

namespace OCA\TalkRh\Service;

use OCA\TalkRh\AppInfo\Application;
use OCP\IDBConnection;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\Notification\IManager as NotificationManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Psr\Log\LoggerInterface;

class LeaveService {
    public function __construct(
        private IDBConnection $db,
        private IUserSession $userSession,
        private IGroupManager $groupManager,
        private IConfig $config,
        private IURLGenerator $urlGenerator,
        private NotificationManager $notificationManager,
        private LoggerInterface $logger,
    ) {}

    public function createLeave(string $uid, string $startDate, string $endDate, string $type, string $reason): array {
        $qb = $this->db->getQueryBuilder();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $qb->insert('talk_rh_leaves')
            ->values([
                'uid' => $qb->createNamedParameter($uid),
                'start_date' => $qb->createNamedParameter($startDate),
                'end_date' => $qb->createNamedParameter($endDate),
                'type' => $qb->createNamedParameter($type),
                'reason' => $qb->createNamedParameter($reason),
                'status' => $qb->createNamedParameter('pending'),
                'admin_comment' => $qb->createNamedParameter(''),
                'created_at' => $qb->createNamedParameter($now),
                'updated_at' => $qb->createNamedParameter($now),
            ])
            ->executeStatement();
        // Fetch inserted row by last insert id to ensure correctness (Nextcloud DB requires *PREFIX*)
        $insertedId = (int)$this->db->lastInsertId('*PREFIX*talk_rh_leaves');
        $row = $this->getLeaveById($insertedId);
        if ($row) {
            $this->logger->debug('notifyAdminsNewLeave: created leave id=' . ($row['id'] ?? 'n/a') . ' for uid=' . ($row['uid'] ?? 'n/a'), ['app' => Application::APP_ID]);
            $this->notifyAdminsNewLeave($row);
            $this->logger->debug('notifyAdminsNewLeave: notified admins for leave id=' . ($row['id'] ?? 'n/a') . ' for uid=' . ($row['uid'] ?? 'n/a'), ['app' => Application::APP_ID]);
        }
        return $row ?: [];
    }

    public function getLeavesForUser(string $uid): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('talk_rh_leaves')
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->orderBy('created_at', 'DESC');
        $cursor = $qb->executeQuery();
        $rows = $cursor->fetchAll();
        $cursor->closeCursor();
        return $rows;
    }

    public function getApprovedLeavesForUser(string $uid): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('talk_rh_leaves')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('uid', $qb->createNamedParameter($uid)),
                    $qb->expr()->eq('status', $qb->createNamedParameter('approved'))
                )
            )
            ->orderBy('start_date', 'ASC');
        $cursor = $qb->executeQuery();
        $rows = $cursor->fetchAll();
        $cursor->closeCursor();
        return $rows;
    }

    public function deleteLeave(string $uid, int $id): bool {
        $leave = $this->getLeaveById($id);
        if (!$leave || $leave['uid'] !== $uid) {
            return false;
        }
        if ($leave['status'] !== 'pending') {
            return false; // cannot delete once approved/rejected
        }
        $qb = $this->db->getQueryBuilder();
        $qb->delete('talk_rh_leaves')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
        return true;
    }

    public function getAllLeaves(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('talk_rh_leaves')->orderBy('created_at', 'DESC');
        $cursor = $qb->executeQuery();
        $rows = $cursor->fetchAll();
        $cursor->closeCursor();
        return $rows;
    }

    public function setLeaveStatus(int $id, string $status, string $adminComment = ''): bool {
        if (!in_array($status, ['approved', 'rejected', 'pending'], true)) {
            return false;
        }
        // Fetch current leave to enforce business rule: only pending can be changed
        $current = $this->getLeaveById($id);
        if ($current === null) {
            return false;
        }
        if ($current['status'] !== 'pending') {
            // Do not allow changing status once approved or rejected
            return false;
        }
        $qb = $this->db->getQueryBuilder();
        $qb->update('talk_rh_leaves')
            ->set('status', $qb->createNamedParameter($status))
            ->set('admin_comment', $qb->createNamedParameter($adminComment))
            ->set('updated_at', $qb->createNamedParameter((new \DateTimeImmutable())->format('Y-m-d H:i:s')))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
        // Notify the requester about the decision
        $updated = $this->getLeaveById($id);
        if ($updated) {
            $this->logger->debug('notifyUserStatusChange: leave id=' . ($updated['id'] ?? 'n/a') . ' to status=' . ($updated['status'] ?? 'n/a') . ' for uid=' . ($updated['uid'] ?? 'n/a'), ['app' => Application::APP_ID]);
            $this->notifyUserStatusChange($updated);
        }
        return true;
    }

    private function notifyUserStatusChange(array $leave): void {
        try {
            if (!isset($leave['uid'])) return;
            $status = (string)($leave['status'] ?? '');
            $this->logger->debug('Sending status notification to ' . (string)$leave['uid'], ['app' => Application::APP_ID]);
            $n = $this->notificationManager->createNotification();
            $n->setApp(Application::APP_ID)
                ->setUser((string)$leave['uid'])
                ->setDateTime(new \DateTime())
                ->setSubject('leave_status_changed', [
                    'status' => $status,
                    'start' => $leave['start_date'] ?? '',
                    'end' => $leave['end_date'] ?? '',
                    'comment' => $leave['admin_comment'] ?? '',
                ])
                ->setObject('leave', (string)($leave['id'] ?? ''))
                ->setLink($this->urlGenerator->linkToRoute('talk_rh.page.employeeView'));
            $this->notificationManager->notify($n);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function getLeaveById(int $id): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('talk_rh_leaves')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->setMaxResults(1);
        $cursor = $qb->executeQuery();
        $row = $cursor->fetch();
        $cursor->closeCursor();
        return $row ?: null;
    }

    private function notifyAdminsNewLeave(array $leave): void {
        try {
            $adminUids = [];
            // App admin group
            $adminGroupId = Application::getAdminGroupId($this->config);
            $group = $this->groupManager->get($adminGroupId);
            if ($group) {
                foreach ($group->getUsers() as $user) {
                    $adminUids[$user->getUID()] = true;
                }
            }
            // Server admins (group 'admin')
            $serverAdminGroup = $this->groupManager->get('admin');
            if ($serverAdminGroup) {
                foreach ($serverAdminGroup->getUsers() as $user) {
                    $adminUids[$user->getUID()] = true;
                }
            }
            $link = $this->urlGenerator->linkToRoute('talk_rh.page.index');
            foreach (array_keys($adminUids) as $uid) {
                // Skip notifying the requester if they are also admin
                if ($uid === ($leave['uid'] ?? '')) {
                    continue;
                }
                $this->logger->debug('Sending admin notification to ' . $uid, ['app' => Application::APP_ID]);
                $n = $this->notificationManager->createNotification();
                $n->setApp(Application::APP_ID)
                    ->setUser($uid)
                    ->setDateTime(new \DateTime())
                    ->setSubject('leave_created', [
                        'uid' => $leave['uid'] ?? '',
                        'start' => $leave['start_date'] ?? '',
                        'end' => $leave['end_date'] ?? '',
                    ])
                    ->setObject('leave', (string)($leave['id'] ?? ''))
                    ->setLink($link);
                $this->notificationManager->notify($n);
            }
        } catch (\Throwable $e) {
            // Silently ignore notification errors
        }
    }
}
