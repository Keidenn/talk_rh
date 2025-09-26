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
use OCP\IUserManager;
use OCP\Accounts\IAccountManager;
use OCP\Http\Client\IClientService;
use OCP\IRequest;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Psr\Log\LoggerInterface;

class LeaveService {
    public function __construct(
        private IDBConnection $db,
        private IRequest $request,
        private IUserSession $userSession,
        private IGroupManager $groupManager,
        private IConfig $config,
        private IURLGenerator $urlGenerator,
        private NotificationManager $notificationManager,
        private LoggerInterface $logger,
        private IUserManager $userManager,
        private IAccountManager $accountManager,
        private IClientService $clientService,
    ) {}

    public function createLeave(string $uid, string $startDate, string $endDate, string $type, string $reason, string $dayParts = ''): array {
        $qb = $this->db->getQueryBuilder();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $qb->insert('talk_rh_leaves')
            ->values([
                'uid' => $qb->createNamedParameter($uid),
                'start_date' => $qb->createNamedParameter($startDate),
                'end_date' => $qb->createNamedParameter($endDate),
                'type' => $qb->createNamedParameter($type),
                'reason' => $qb->createNamedParameter($reason),
                'day_parts' => $qb->createNamedParameter($dayParts),
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
            // Talk: notify managers (if enabled)
            if ($this->isTalkEnabled()) {
                try {
                    $employeeUid = (string)$row['uid'];
                    $managers = $this->getManagerUidsFor($employeeUid);
                    $msg = $this->formatTalkMsgNewLeave($row);
                    foreach ($managers as $mUid) {
                        if ($mUid !== '' && $mUid !== $employeeUid) {
                            $this->sendTalkMessage($employeeUid, $mUid, $msg);
                        }
                    }
                    // Broadcast to selected multi-user channel if configured
                    $channelToken = $this->getSelectedTalkChannelToken();
                    if ($channelToken !== '') {
                        $this->sendTalkToRoomToken($channelToken, $msg);
                    }
                } catch (\Throwable $e) {
                    $this->logger->debug('Talk send (create) failed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
                }
            }
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

    /**
     * Return all leaves belonging to the provided user IDs.
     * Note: simple PHP-side filter to keep DB logic minimal. Optimize later if needed.
     *
     * @param string[] $uids
     * @return array<int, array<string, mixed>>
     */
    public function getLeavesForUids(array $uids): array {
        if (empty($uids)) {
            return [];
        }
        $all = $this->getAllLeaves();
        $allowed = array_flip($uids);
        return array_values(array_filter($all, function($row) use ($allowed) {
            return isset($row['uid']) && isset($allowed[(string)$row['uid']]);
        }));
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
            // Talk: notify employee about the decision (if enabled)
            if ($this->isTalkEnabled()) {
                try {
                    $employeeUid = (string)$updated['uid'];
                    $actor = $this->userSession->getUser();
                    $fromUid = $actor ? $actor->getUID() : '';
                    $msg = $this->formatTalkMsgStatus($updated);
                    if ($fromUid !== '' && $employeeUid !== '') {
                        $this->sendTalkMessage($fromUid, $employeeUid, $msg);
                    }
                    // Broadcast to selected multi-user channel if configured
                    $channelToken = $this->getSelectedTalkChannelToken();
                    if ($channelToken !== '') {
                        $this->sendTalkToRoomToken($channelToken, $msg);
                    }
                } catch (\Throwable $e) {
                    $this->logger->debug('Talk send (status) failed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
                }
            }
        }
        return true;
    }

    private function isTalkEnabled(): bool {
        return $this->config->getAppValue(Application::APP_ID, 'talk_enabled', '0') === '1';
    }

    private function formatTalkMsgNewLeave(array $leave): string {
        $start = (string)($leave['start_date'] ?? '');
        $end = (string)($leave['end_date'] ?? '');
        $dayParts = trim((string)($leave['day_parts'] ?? ''));
        $who = (string)($leave['uid'] ?? '');
        $whoDisplay = $this->getDisplayNameSafe($who);
        $daysMd = $this->formatDayPartsMarkdown($dayParts);
        $reason = trim((string)($leave['reason'] ?? ''));
        $md = "__                                           __\n\n";
        $md .= "# Nouvelle demande de congés\n\n";
        $md .= "### Employé\n" . ($whoDisplay !== '' ? $whoDisplay . " (" . $who . ")" : $who) . "\n\n";
        $startLong = $this->formatDateLongFr($start);
        $endLong = $this->formatDateLongFr($end);
        $md .= "### Période\n$startLong → $endLong\n";
        if ($daysMd !== '') {
            $md .= "\n### Jours\n" . $daysMd . "\n";
        }
        if ($reason !== '') {
            $md .= "\n### Motif\n" . $reason . "\n";
        }
        return $md;
    }

    private function formatTalkMsgStatus(array $leave): string {
        $start = (string)($leave['start_date'] ?? '');
        $end = (string)($leave['end_date'] ?? '');
        $dayParts = trim((string)($leave['day_parts'] ?? ''));
        $daysMd = $this->formatDayPartsMarkdown($dayParts);
        $status = (string)($leave['status'] ?? '');
        $statusFr = $status === 'approved' ? 'approuvée' : ($status === 'rejected' ? 'refusée' : 'mise à jour');
        $comment = trim((string)($leave['admin_comment'] ?? ''));
        $md = "__                                           __\n\n";
        $md .= "# Statut de votre demande de congés\n\n";
        $md .= "### Période\n$start → $end\n";
        $who = (string)($leave['uid'] ?? '');
        $whoDisplay = $this->getDisplayNameSafe($who);
        $md .= "### Employé\n" . ($whoDisplay !== '' ? $whoDisplay . " (" . $who . ")" : $who) . "\n\n";
        if ($daysMd !== '') {
            $md .= "\n### Jours\n" . $daysMd . "\n";
        }
        $md .= "\n### Statut\n" . ucfirst($statusFr) . ".\n";
        if ($comment !== '') {
            $md .= "\n### Commentaire\n" . $comment . "\n";
        }
        return $md;
    }

    private function getDisplayNameSafe(string $uid): string {
        try {
            $u = $this->userManager->get($uid);
            return $u ? (string)$u->getDisplayName() : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function formatDayPartsMarkdown(string $dayParts): string {
        $dayParts = trim($dayParts);
        if ($dayParts === '') return '';
        $map = [
            'full' => 'Journée complète',
            'am' => 'Matin',
            'pm' => 'Après-midi',
        ];
        $lines = [];
        $decoded = null;
        if ($dayParts !== '' && ($dayParts[0] === '{' || $dayParts[0] === '[')) {
            try {
                $decoded = json_decode($dayParts, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $decoded = null;
            }
        }
        if (is_array($decoded)) {
            // Object of date => part or array of items
            if (array_keys($decoded) !== range(0, count($decoded) - 1)) {
                foreach ($decoded as $date => $part) {
                    $label = isset($map[$part]) ? $map[$part] : (string)$part;
                    $dateLong = $this->formatDateLongFr((string)$date);
                    $lines[] = "- $dateLong: $label";
                }
            } else {
                foreach ($decoded as $item) {
                    if (is_array($item)) {
                        $date = (string)($item['date'] ?? '');
                        $part = (string)($item['part'] ?? '');
                        $label = isset($map[$part]) ? $map[$part] : $part;
                        if ($date !== '' && $label !== '') {
                            $dateLong = $this->formatDateLongFr($date);
                            $lines[] = "- $dateLong: $label";
                        }
                    } elseif (is_string($item)) {
                        $lines[] = "- " . $this->formatDateLongFr($item);
                    }
                }
            }
        } else {
            // Fallback: display raw content
            $lines[] = '- ' . $this->formatDateLongFr($dayParts);
        }
        return implode("\n", $lines);
    }

    private function formatDateLongFr(string $dateStr): string {
        $dateStr = trim($dateStr);
        if ($dateStr === '') return '';
        try {
            // Try parsing plain Y-m-d first
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
            if ($dt === false) {
                // Fallback: let DateTime parse other formats
                $dt = new \DateTimeImmutable($dateStr);
            }
            if (class_exists('\\IntlDateFormatter')) {
                $fmt = new \IntlDateFormatter(
                    'fr_FR',
                    \IntlDateFormatter::FULL,
                    \IntlDateFormatter::NONE,
                    $dt->getTimezone()->getName(),
                    \IntlDateFormatter::GREGORIAN,
                    'EEEE d MMMM yyyy'
                );
                $out = $fmt->format($dt);
                return is_string($out) ? ucfirst($out) : $dateStr;
            }
            // Fallback manual formatting in French
            $jours = [1=>'lundi',2=>'mardi',3=>'mercredi',4=>'jeudi',5=>'vendredi',6=>'samedi',7=>'dimanche'];
            $mois = [1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',7=>'juillet',8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre'];
            $j = (int)$dt->format('N');
            $d = (int)$dt->format('j');
            $m = (int)$dt->format('n');
            $y = (int)$dt->format('Y');
            return ucfirst($jours[$j]) . ' ' . $d . ' ' . $mois[$m] . ' ' . $y;
        } catch (\Throwable $e) {
            return $dateStr;
        }
    }

    /**
     * Attempt to send a Talk message by using Talk's internal services if available.
     * Gracefully no-op if Talk is not installed or API differs.
     */
    private function sendTalkMessage(string $fromUid, string $toUid, string $message): void {
        try {
            $token = $this->getOrCreateDirectRoomToken($toUid);
            if ($token === '') {
                $this->logger->debug('Talk: no direct room token for toUid=' . $toUid, ['app' => Application::APP_ID]);
                return;
            }
            // Minimal payload for broad compatibility — use Chat API v1
            $this->ocsPostV1('/chat/' . rawurlencode($token) . '?format=json', [
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            $this->logger->debug('Talk send exception: ' . $e->getMessage(), ['app' => Application::APP_ID]);
        }
    }

    private function sendTalkToRoomToken(string $token, string $message): void {
        try {
            if (trim($token) === '') return;
            $this->ocsPostV1('/chat/' . rawurlencode($token) . '?format=json', [
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            $this->logger->debug('Talk room send exception: ' . $e->getMessage(), ['app' => Application::APP_ID]);
        }
    }

    private function getOrCreateDirectRoomToken(string $otherUid): string {
        // First create/ensure direct conversation
        try {
            $resp = $this->ocsPost('/room?format=json', [
                // According to API v4, POST /room creates conversations; for one-to-one it returns 200 if exists
                // Try different invite formats for compatibility
                'roomType' => '1',
                'invite' => $otherUid,
                'source' => 'users',
            ]);
            $token = $this->extractTokenFromRoomResponse($resp);
            if ($token !== '') return $token;
        } catch (\Throwable $e) {
            // ignore and try alternative or fallback
        }
        try {
            $resp = $this->ocsPost('/room', [
                'roomType' => '1',
                'invite[]' => $otherUid,
                'source' => 'users',
            ]);
            $token = $this->extractTokenFromRoomResponse($resp);
            if ($token !== '') return $token;
        } catch (\Throwable $e) {
        }
        // Fallback: list rooms and find one-to-one with otherUid in name/display
        try {
            $rooms = $this->ocsGet('/room?format=json');
            $token = $this->findDirectRoomTokenInList($rooms, $otherUid);
            return $token;
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function extractTokenFromRoomResponse(array $resp): string {
        // Expect OCS envelope: ['ocs' => ['data' => room]]
        $data = $resp['ocs']['data'] ?? null;
        if (is_array($data)) {
            if (isset($data['token']) && is_string($data['token'])) return $data['token'];
            if (isset($data['conversation']) && is_array($data['conversation']) && isset($data['conversation']['token'])) {
                return (string)$data['conversation']['token'];
            }
            // Some responses may return array of rooms
            if (isset($data[0]['token'])) return (string)$data[0]['token'];
            // Search recursively
            $stack = [$data];
            while ($stack) {
                $cur = array_pop($stack);
                if (!is_array($cur)) continue;
                if (isset($cur['token']) && is_string($cur['token'])) return $cur['token'];
                foreach ($cur as $v) if (is_array($v)) $stack[] = $v;
            }
        }
        return '';
    }

    private function findDirectRoomTokenInList(array $resp, string $otherUid): string {
        $list = $resp['ocs']['data'] ?? [];
        if (!is_array($list)) return '';
        foreach ($list as $room) {
            if (!is_array($room)) continue;
            // Heuristic: one-to-one rooms usually have type 1 and name/displayName referencing other participant
            $type = isset($room['type']) ? (int)$room['type'] : null;
            $name = (string)($room['name'] ?? '');
            $display = (string)($room['displayName'] ?? '');
            if ($type === 1 && ($name === $otherUid || $display === $otherUid)) {
                return (string)($room['token'] ?? '');
            }
        }
        return '';
    }

    private function ocsBase(): string {
        $base = rtrim($this->urlGenerator->getAbsoluteURL('/'), '/');
        if (str_ends_with($base, '/index.php')) {
            $base = substr($base, 0, -10);
        }
        return $base . '/ocs/v2.php/apps/spreed/api/v4';
    }

    private function ocsBaseV1(): string {
        $base = rtrim($this->urlGenerator->getAbsoluteURL('/'), '/');
        if (str_ends_with($base, '/index.php')) {
            $base = substr($base, 0, -10);
        }
        return $base . '/ocs/v2.php/apps/spreed/api/v1';
    }

    /**
     * Return list of multi-user Talk channels available to the current user.
     * Each item: ['token' => string, 'name' => string]
     */
    public function getTalkChannels(): array {
        try {
            $rooms = $this->ocsGet('/room?format=json');
            $list = $rooms['ocs']['data'] ?? [];
            $out = [];
            if (is_array($list)) {
                foreach ($list as $room) {
                    if (!is_array($room)) continue;
                    $type = isset($room['type']) ? (int)$room['type'] : null;
                    if ($type === 1) continue; // skip one-to-one
                    $token = (string)($room['token'] ?? '');
                    if ($token === '') continue;
                    $label = (string)($room['displayName'] ?? ($room['name'] ?? $token));
                    $out[] = ['token' => $token, 'name' => $label];
                }
            }
            // simple sort by name
            usort($out, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getSelectedTalkChannelToken(): string {
        return (string)$this->config->getAppValue(Application::APP_ID, 'talk_channel_token', '');
    }

    public function saveSelectedTalkChannelToken(string $token): void {
        $token = trim($token);
        $this->config->setAppValue(Application::APP_ID, 'talk_channel_token', $token);
    }

    private function ocsGet(string $path): array {
        $client = $this->clientService->newClient();
        $headers = $this->buildOcsHeaders();
        $url = $this->ocsBase() . $path;
        $res = $client->get($url, [ 'headers' => $headers, 'http_errors' => false ]);
        $status = $res->getStatusCode();
        $body = (string)$res->getBody();
        $this->logger->debug('Talk OCS GET ' . $url . ' status=' . $status, ['app' => Application::APP_ID]);
        if ($status >= 400) {
            $this->logger->debug('Talk OCS GET response body: ' . substr($body, 0, 1000), ['app' => Application::APP_ID]);
        }
        return $this->decodeJson($body);
    }

    private function ocsPost(string $path, array $data): array {
        $client = $this->clientService->newClient();
        $headers = $this->buildOcsHeaders();
        $url = $this->ocsBase() . $path;
        // form-encode body for OCS
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $res = $client->post($url, [ 'headers' => $headers, 'body' => http_build_query($data), 'http_errors' => false ]);
        $status = $res->getStatusCode();
        $body = (string)$res->getBody();
        $this->logger->debug('Talk OCS POST ' . $url . ' status=' . $status, ['app' => Application::APP_ID, 'payload' => array_keys($data)]);
        if ($status >= 400) {
            $this->logger->debug('Talk OCS POST response body: ' . substr($body, 0, 1000), ['app' => Application::APP_ID]);
        }
        return $this->decodeJson($body);
    }

    private function ocsPostV1(string $path, array $data): array {
        $client = $this->clientService->newClient();
        $headers = $this->buildOcsHeaders();
        $url = $this->ocsBaseV1() . $path;
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $res = $client->post($url, [ 'headers' => $headers, 'body' => http_build_query($data), 'http_errors' => false ]);
        $status = $res->getStatusCode();
        $body = (string)$res->getBody();
        $this->logger->debug('Talk OCS POST v1 ' . $url . ' status=' . $status, ['app' => Application::APP_ID, 'payload' => array_keys($data)]);
        if ($status >= 400) {
            $this->logger->debug('Talk OCS POST v1 response body: ' . substr($body, 0, 1000), ['app' => Application::APP_ID]);
        }
        return $this->decodeJson($body);
    }

    private function buildOcsHeaders(): array {
        $cookie = $this->request->getHeader('Cookie') ?? '';
        $rt = method_exists($this->request, 'getRequestToken') ? (string)$this->request->getRequestToken() : '';
        $h = [
            'OCS-APIRequest' => 'true',
            'Accept' => 'application/json',
        ];
        if ($cookie !== '') {
            $h['Cookie'] = $cookie;
        }
        if ($rt !== '') {
            $h['requesttoken'] = $rt;
        }
        return $h;
    }

    private function decodeJson(string $body): array {
        try {
            $arr = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return is_array($arr) ? $arr : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Admin diagnostic: try to send a Talk DM and return rich details.
     * Note: OCS will act as the CURRENT session user, not strictly as $fromUid.
     */
    public function testTalkDM(string $fromUid, string $toUid, string $message): array {
        $actor = $this->userSession->getUser();
        $currentUid = $actor ? $actor->getUID() : '';
        $actorName = $actor ? $actor->getDisplayName() : '';
        $diag = [
            'currentUid' => $currentUid,
            'requestedFromUid' => $fromUid,
            'toUid' => $toUid,
            'token' => '',
            'createRoom' => null,
            'send1' => null,
            'send2' => null,
        ];
        try {
            // Ensure/create one-to-one room
            $create = $this->ocsPostDiag('/room?format=json', [
                'roomType' => '1',
                'invite' => $toUid,
                'source' => 'users',
            ]);
            $diag['createRoom'] = $create;
            $token = $this->extractTokenFromRoomResponse($create['json'] ?? []);
            if ($token === '') {
                // Fallback listing
                $list = $this->ocsGetDiag('/room?format=json');
                $diag['rooms'] = [ 'status' => $list['status'] ?? null, 'count' => isset($list['json']['ocs']['data']) && is_array($list['json']['ocs']['data']) ? count($list['json']['ocs']['data']) : null ];
                $token = $this->findDirectRoomTokenInList($list['json'] ?? [], $toUid);
            }
            $diag['token'] = $token;
            if ($token === '') {
                return $diag;
            }
            // Fetch participants to confirm membership
            $parts = $this->ocsGetDiag('/room/' . rawurlencode($token) . '/participants?format=json');
            $isMember = false;
            try {
                $isMember = $this->participantsContainUid($parts['json'] ?? [], $currentUid);
            } catch (\Throwable $e) { /* ignore */ }
            $diag['participants'] = [
                'status' => $parts['status'] ?? null,
                'isMember' => $isMember,
                'bodyPreview' => isset($parts['body']) ? substr($parts['body'], 0, 300) : null,
            ];
            // Try sending with extra fields (API v1)
            $send1 = $this->ocsPostDiagV1('/chat/' . rawurlencode($token) . '?format=json', [
                'message' => $message,
                'actorDisplayName' => $actorName,
                'silent' => '0',
            ]);
            $diag['send1'] = [ 'status' => $send1['status'] ?? null, 'bodyPreview' => isset($send1['body']) ? substr($send1['body'], 0, 300) : null ];
            if (($send1['status'] ?? 0) >= 400) {
                // Retry minimal payload
                $send2 = $this->ocsPostDiagV1('/chat/' . rawurlencode($token) . '?format=json', [
                    'message' => $message,
                ]);
                $diag['send2'] = [ 'status' => $send2['status'] ?? null, 'bodyPreview' => isset($send2['body']) ? substr($send2['body'], 0, 300) : null ];
            }
        } catch (\Throwable $e) {
            $diag['exception'] = $e->getMessage();
        }
        return $diag;
    }

    private function participantsContainUid(array $resp, string $uid): bool {
        if ($uid === '') return false;
        $data = $resp['ocs']['data'] ?? null;
        if (!is_array($data)) return false;
        $stack = [$data];
        while ($stack) {
            $cur = array_pop($stack);
            if (!is_array($cur)) continue;
            // common keys that could carry user id
            foreach (['id','uid','actorId','userId'] as $k) {
                if (isset($cur[$k]) && is_string($cur[$k]) && $cur[$k] === $uid) return true;
            }
            foreach ($cur as $v) if (is_array($v)) $stack[] = $v;
        }
        return false;
    }

    private function ocsGetDiag(string $path): array {
        $client = $this->clientService->newClient();
        $headers = $this->buildOcsHeaders();
        $url = $this->ocsBase() . $path;
        $res = $client->get($url, [ 'headers' => $headers, 'http_errors' => false ]);
        $status = $res->getStatusCode();
        $body = (string)$res->getBody();
        return [ 'status' => $status, 'body' => $body, 'json' => $this->decodeJson($body) ];
    }

    private function ocsPostDiag(string $path, array $data): array {
        $client = $this->clientService->newClient();
        $headers = $this->buildOcsHeaders();
        $url = $this->ocsBase() . $path;
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $res = $client->post($url, [ 'headers' => $headers, 'body' => http_build_query($data), 'http_errors' => false ]);
        $status = $res->getStatusCode();
        $body = (string)$res->getBody();
        return [ 'status' => $status, 'body' => $body, 'json' => $this->decodeJson($body) ];
    }

    private function ocsPostJsonDiag(string $path, array $data): array {
        $client = $this->clientService->newClient();
        $headers = $this->buildOcsHeaders();
        $url = $this->ocsBase() . $path;
        $headers['Content-Type'] = 'application/json';
        $res = $client->post($url, [ 'headers' => $headers, 'body' => json_encode($data), 'http_errors' => false ]);
        $status = $res->getStatusCode();
        $body = (string)$res->getBody();
        return [ 'status' => $status, 'body' => $body, 'json' => $this->decodeJson($body) ];
    }

    private function ocsPostDiagV1(string $path, array $data): array {
        $client = $this->clientService->newClient();
        $headers = $this->buildOcsHeaders();
        $url = $this->ocsBaseV1() . $path;
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $res = $client->post($url, [ 'headers' => $headers, 'body' => http_build_query($data), 'http_errors' => false ]);
        $status = $res->getStatusCode();
        $body = (string)$res->getBody();
        return [ 'status' => $status, 'body' => $body, 'json' => $this->decodeJson($body) ];
    }

    /**
     * Return manager UIDs for an employee using Accounts properties first, then config fallback.
     */
    private function getManagerUidsFor(string $employeeUid): array {
        $uids = [];
        try {
            $employee = $this->userManager->get($employeeUid);
            if ($employee) {
                $account = $this->accountManager->getAccount($employee);
                if ($account && method_exists($account, 'getProperty')) {
                    if (defined('OCP\\Accounts\\IAccountManager::PROPERTY_MANAGER')) {
                        $p = $account->getProperty(IAccountManager::PROPERTY_MANAGER);
                        if ($p && method_exists($p, 'getValue')) {
                            $uid = $this->resolveValueToUid((string)$p->getValue());
                            if ($uid !== '') $uids[] = $uid;
                        }
                    }
                    if (empty($uids) && defined('OCP\\Accounts\\IAccountManager::PROPERTY_SUPERVISOR')) {
                        $p = $account->getProperty(IAccountManager::PROPERTY_SUPERVISOR);
                        if ($p && method_exists($p, 'getValue')) {
                            $uid = $this->resolveValueToUid((string)$p->getValue());
                            if ($uid !== '') $uids[] = $uid;
                        }
                    }
                }
            }
            if (empty($uids)) {
                // Config fallback (supports array or scalar)
                $raw = $this->config->getUserValue($employeeUid, 'settings', 'manager', '');
                if ($raw !== '') {
                    $decoded = null;
                    try {
                        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Throwable $e) {}
                    if (is_array($decoded)) {
                        foreach ($decoded as $val) {
                            if (!is_string($val)) continue;
                            $uid = $this->resolveValueToUid($val);
                            if ($uid !== '') $uids[] = $uid;
                        }
                    } else {
                        $uid = $this->resolveValueToUid((string)$raw);
                        if ($uid !== '') $uids[] = $uid;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return array_values(array_unique($uids));
    }

    /**
     * Try to resolve UID from UID/email/displayName/cloudId values
     */
    private function resolveValueToUid(string $value): string {
        $value = trim($value);
        if ($value === '') return '';
        // direct UID
        try {
            $u = $this->userManager->get($value);
            if ($u !== null) return $u->getUID();
        } catch (\Throwable $e) {}
        // email
        try {
            if (method_exists($this->userManager, 'getByEmail')) {
                $byEmail = $this->userManager->getByEmail($value);
                if (is_array($byEmail) && count($byEmail) > 0 && $byEmail[0] !== null) {
                    return $byEmail[0]->getUID();
                }
            }
        } catch (\Throwable $e) {}
        // display name exact
        try {
            if (method_exists($this->userManager, 'searchDisplayName')) {
                $found = $this->userManager->searchDisplayName($value, 10, 0);
                foreach ($found as $cand) {
                    if (strcasecmp($cand->getDisplayName(), $value) === 0) {
                        return $cand->getUID();
                    }
                }
            } else {
                $all = $this->userManager->search('');
                foreach ($all as $cand) {
                    if (strcasecmp($cand->getDisplayName(), $value) === 0) {
                        return $cand->getUID();
                    }
                }
            }
        } catch (\Throwable $e) {}
        // cloud id
        if (strpos($value, '@') !== false) {
            $uid = substr($value, 0, strpos($value, '@'));
            try {
                $u = $this->userManager->get($uid);
                if ($u !== null) return $u->getUID();
            } catch (\Throwable $e) {}
        }
        return '';
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
