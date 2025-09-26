<?php
declare(strict_types=1);

namespace OCA\TalkRh\AppInfo;

use OCA\TalkRh\Controller\ApiController;
use OCA\TalkRh\Controller\IcsController;
use OCA\TalkRh\Controller\PageController;
use OCA\TalkRh\Service\LeaveService;
use OCA\TalkRh\Notifier\LeaveNotifier;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\IDBConnection;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Notification\IManager as NotificationManager;
use Psr\Log\LoggerInterface;
use OCP\Util;
use OCP\IUserManager;
use OCP\Accounts\IAccountManager;
use OCP\Http\Client\IClientService;

class Application extends App {
    public const APP_ID = 'talk_rh';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
        $container = $this->getContainer();

        // Services
        $container->registerService(LeaveService::class, function(IAppContainer $c) {
            return new LeaveService(
                $c->query(IDBConnection::class),
                $c->query(IRequest::class),
                $c->query(IUserSession::class),
                $c->query(IGroupManager::class),
                $c->query(IConfig::class),
                $c->query(IURLGenerator::class),
                $c->query(NotificationManager::class),
                $c->getServer()->get(LoggerInterface::class),
                $c->query(IUserManager::class),
                $c->query(IAccountManager::class),
                $c->query(IClientService::class),
            );
        });

        // Controllers
        $container->registerService(PageController::class, function(IAppContainer $c) {
            return new PageController(
                self::APP_ID,
                $c->query(IRequest::class),
                $c->query(IUserSession::class),
                $c->query(IGroupManager::class),
                $c->query(IConfig::class),
                $c->query(IURLGenerator::class)
            );
        });

        $container->registerService(ApiController::class, function(IAppContainer $c) {
            return new ApiController(
                self::APP_ID,
                $c->query(IRequest::class),
                $c->query(LeaveService::class),
                $c->query(IUserSession::class),
                $c->query(IGroupManager::class),
                $c->query(IConfig::class),
                $c->query(IUserManager::class),
                $c->query(IAccountManager::class),
                $c->getServer()->get(LoggerInterface::class)
            );
        });

        $container->registerService(IcsController::class, function(IAppContainer $c) {
            return new IcsController(
                self::APP_ID,
                $c->query(IRequest::class),
                $c->query(IUserSession::class),
                $c->query(IConfig::class),
                $c->query(IURLGenerator::class),
                $c->query(LeaveService::class),
            );
        });

        // Register LeaveNotifier as a service for DI
        $container->registerService(LeaveNotifier::class, function(IAppContainer $c) {
            return new LeaveNotifier(
                $c->query(IURLGenerator::class)
            );
        });

        // Add quick link in the app menu to the employee view
        try {
            $urlGen = $container->query(IURLGenerator::class);
            \OC::$server->getNavigationManager()->add(function() use ($urlGen) {
                return [
                    'id' => 'talk_rh_employee',
                    'order' => 56,
                    'name' => 'Congés',
                    'href' => $urlGen->linkToRoute('talk_rh.page.employeeView'),
                    'icon' => $urlGen->imagePath(self::APP_ID, 'app.svg'),
                ];
            });
        } catch (\Throwable $e) {
            // ignore if navigation not available
        }

        // Register notifications notifier (new API)
        try {
            /** @var NotificationManager $notif */
            $notif = $container->query(NotificationManager::class);
            if (method_exists($notif, 'registerNotifierService')) {
                $notif->registerNotifierService(LeaveNotifier::class);
            }
        } catch (\Throwable $e) {
            // ignore if notifications not available
        }
    }

    public static function getAdminGroupId(IConfig $config): string {
        return $config->getAppValue(self::APP_ID, 'admin_group', 'admin');
    }
}
