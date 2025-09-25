<?php
declare(strict_types=1);

namespace OCA\TalkRh\Controller;

use OCA\TalkRh\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class PageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private IUserSession $userSession,
        private IGroupManager $groupManager,
        private IConfig $config,
        private IURLGenerator $urlGenerator,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new TemplateResponse($this->appName, 'not-logged-in');
        }

        $adminGroup = Application::getAdminGroupId($this->config);
        $isServerAdmin = $this->groupManager->isAdmin($user->getUID());
        $isAppAdmin = $this->groupManager->isInGroup($user->getUID(), $adminGroup);

        if ($isServerAdmin || $isAppAdmin) {
            return new TemplateResponse($this->appName, 'admin', ['isAdmin' => true]);
        }
        return new TemplateResponse($this->appName, 'employee', ['isAdmin' => false]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function employeeView(): TemplateResponse {
        $user = $this->userSession->getUser();
        $isAdmin = false;
        if ($user !== null) {
            $adminGroup = Application::getAdminGroupId($this->config);
            $isServerAdmin = $this->groupManager->isAdmin($user->getUID());
            $isAppAdmin = $this->groupManager->isInGroup($user->getUID(), $adminGroup);
            $isAdmin = $isServerAdmin || $isAppAdmin;
        }
        return new TemplateResponse($this->appName, 'employee', ['isAdmin' => $isAdmin]);
    }
}
