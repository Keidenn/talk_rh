<?php
declare(strict_types=1);

namespace OCA\TalkRh\Controller;

use OCA\TalkRh\AppInfo\Application;
use OCA\TalkRh\Service\LeaveService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class ApiController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private LeaveService $leaveService,
        private IUserSession $userSession,
        private IGroupManager $groupManager,
        private IConfig $config,
    ) {
        parent::__construct($appName, $request);
    }

    private function ensureLoggedIn(): void {
        if ($this->userSession->getUser() === null) {
            throw new \Exception('Not logged in');
        }
    }

    private function ensureAppAdmin(): void {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new \Exception('Not logged in');
        }
        $adminGroup = Application::getAdminGroupId($this->config);
        $isServerAdmin = $this->groupManager->isAdmin($user->getUID());
        $isAppAdmin = $this->groupManager->isInGroup($user->getUID(), $adminGroup);
        if (!$isServerAdmin && !$isAppAdmin) {
            throw new \Exception('Admin privileges required');
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getMyLeaves(): JSONResponse {
        $this->ensureLoggedIn();
        $user = $this->userSession->getUser();
        $data = $this->leaveService->getLeavesForUser($user->getUID());
        return new JSONResponse(['leaves' => $data]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function createLeave(string $startDate, string $endDate, string $type = 'paid', string $reason = '', string $dayParts = ''): JSONResponse {
        $this->ensureLoggedIn();
        $user = $this->userSession->getUser();
        $created = $this->leaveService->createLeave($user->getUID(), $startDate, $endDate, $type, $reason, $dayParts);
        return new JSONResponse(['leave' => $created]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function deleteLeave(int $id): JSONResponse {
        $this->ensureLoggedIn();
        $user = $this->userSession->getUser();
        $ok = $this->leaveService->deleteLeave($user->getUID(), $id);
        return new JSONResponse(['success' => $ok]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getAllLeaves(): JSONResponse {
        $this->ensureAppAdmin();
        $data = $this->leaveService->getAllLeaves();
        return new JSONResponse(['leaves' => $data]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function setLeaveStatus(int $id, string $status, string $adminComment = ''): JSONResponse {
        $this->ensureAppAdmin();
        $ok = $this->leaveService->setLeaveStatus($id, $status, $adminComment);
        return new JSONResponse(['success' => $ok]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveAdminGroup(string $groupId): JSONResponse {
        $this->ensureAppAdmin();
        $this->config->setAppValue(Application::APP_ID, 'admin_group', $groupId);
        return new JSONResponse(['saved' => true, 'groupId' => $groupId]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getAdminGroup(): JSONResponse {
        $this->ensureAppAdmin();
        $gid = Application::getAdminGroupId($this->config);
        return new JSONResponse(['groupId' => $gid]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function listGroups(): JSONResponse {
        $this->ensureAppAdmin();
        // Search all groups (empty search)
        $groups = $this->groupManager->search('');
        $res = [];
        foreach ($groups as $g) {
            $res[] = [
                'id' => $g->getGID(),
                'displayName' => method_exists($g, 'getDisplayName') ? $g->getDisplayName() : $g->getGID(),
            ];
        }
        return new JSONResponse(['groups' => $res]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getAdminGroupMembers(): JSONResponse {
        $this->ensureAppAdmin();
        $gid = (string)$this->request->getParam('groupId') ?: Application::getAdminGroupId($this->config);
        $group = $this->groupManager->get($gid);
        if ($group === null) {
            return new JSONResponse(['groupId' => $gid, 'members' => []]);
        }
        $members = [];
        foreach ($group->getUsers() as $user) {
            $members[] = [
                'uid' => $user->getUID(),
                'displayName' => $user->getDisplayName(),
            ];
        }
        return new JSONResponse(['groupId' => $gid, 'members' => $members]);
    }
}
