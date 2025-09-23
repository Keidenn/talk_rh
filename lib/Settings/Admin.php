<?php
declare(strict_types=1);

namespace OCA\TalkRh\Settings;

use OCA\TalkRh\AppInfo\Application;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCP\AppFramework\Http\TemplateResponse;

class Admin implements ISettings {
    public function __construct(
        private IL10N $l,
        private IConfig $config,
        private IGroupManager $groupManager,
    ) {}

    public function getForm(): TemplateResponse {
        // Fetch groups for select
        $groups = [];
        foreach ($this->groupManager->search('') as $group) {
            $groups[] = [
                'id' => $group->getGID(),
                'displayname' => $group->getDisplayName(),
            ];
        }
        $selected = Application::getAdminGroupId($this->config);
        return new TemplateResponse(Application::APP_ID, 'settings-admin', [
            'groups' => $groups,
            'selected' => $selected,
        ], '');
    }

    public function getSection(): string {
        return 'additional';
    }

    public function getPriority(): int {
        return 50;
    }
}
