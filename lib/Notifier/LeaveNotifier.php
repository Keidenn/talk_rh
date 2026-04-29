<?php
declare(strict_types=1);

namespace OCA\TalkRh\Notifier;

use OCP\IURLGenerator;
use OCP\IL10N;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class LeaveNotifier implements INotifier {
    public function __construct(
        private IURLGenerator $urlGenerator,
        private IL10N $l10n,
    ) {}

    public function getID(): string {
        return 'talk_rh-notifier';
    }

    public function getName(): string {
        return 'Demande de congés';
    }

    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== 'talk_rh') {
            // Not for us
            return $notification;
        }

        $subject = $notification->getSubject();
        $params = $notification->getSubjectParameters();

        if ($subject === 'leave_created') {
            $uid = (string)($params['uid'] ?? '');
            $start = (string)($params['start'] ?? '');
            $end = (string)($params['end'] ?? '');
            $label = $this->l10n->t('Nouvelle demande de congés');
            $text = $uid !== ''
                ? $this->l10n->t('%1$s a créé une demande de congés du %2$s au %3$s.', [$uid, $start, $end])
                : $this->l10n->t('Nouvelle demande de congés du %1$s au %2$s.', [$start, $end]);

            $notification->setParsedSubject($label)
                ->setParsedMessage($text)
                ->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('talk_rh', 'app.svg')));

            // If not already set, point to the app index
            if ($notification->getLink() === '') {
                $notification->setLink($this->urlGenerator->linkToRouteAbsolute('talk_rh.page.index'));
            }
        } elseif ($subject === 'leave_status_changed') {
            $status = (string)($params['status'] ?? '');
            $start = (string)($params['start'] ?? '');
            $end = (string)($params['end'] ?? '');
            $comment = (string)($params['comment'] ?? '');
            $statusFr = $status === 'approved' ? $this->l10n->t('approuvée') : ($status === 'rejected' ? $this->l10n->t('refusée') : $this->l10n->t('mise à jour'));
            $label = $this->l10n->t('Statut de votre demande');
            $base = $this->l10n->t('Votre demande de congés (%1$s → %2$s) a été %3$s.', [$start, $end, $statusFr]);
            $text = $comment !== '' ? $base . ' ' . $this->l10n->t('Commentaire: ') . $comment : $base;

            $notification->setParsedSubject($label)
                ->setParsedMessage($text)
                ->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('talk_rh', 'app.svg')));

            if ($notification->getLink() === '') {
                $notification->setLink($this->urlGenerator->linkToRouteAbsolute('talk_rh.page.employeeView'));
            }
        } elseif ($subject === 'leave_deleted_by_employee') {
            $uid = (string)($params['uid'] ?? '');
            $start = (string)($params['start'] ?? '');
            $end = (string)($params['end'] ?? '');
            $label = $this->l10n->t('Congé annulé');
            $text = $uid !== ''
                ? $this->l10n->t('%1$s a annulé son congé validé du %2$s au %3$s.', [$uid, $start, $end])
                : $this->l10n->t('Un congé validé du %1$s au %2$s a été annulé par l\'employé.', [$start, $end]);

            $notification->setParsedSubject($label)
                ->setParsedMessage($text)
                ->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('talk_rh', 'app.svg')));

            if ($notification->getLink() === '') {
                $notification->setLink($this->urlGenerator->linkToRouteAbsolute('talk_rh.page.index'));
            }
        }

        return $notification;
    }
}
