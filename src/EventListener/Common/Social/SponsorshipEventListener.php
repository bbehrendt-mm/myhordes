<?php

namespace App\EventListener\Common\Social;

use App\Enum\NotificationSubscriptionType;
use App\Enum\UserSetting;
use App\Event\Common\Social\SponsorshipEvent;
use App\EventListener\ContainerTypeTrait;
use App\Messages\WebPush\WebPushMessage;
use App\Service\CrowService;
use App\Service\Media\MediaService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends an in-game crow (Global PM) notification to the sponsor whenever
 * a new player registers using their referral link.
 *
 * The notification is opt-in via UserSetting::NotifyMeOnSponsorRegistration
 * (default: true).  A web-push notification is also dispatched when the
 * sponsor has registered a push subscription.
 */
#[AsEventListener(event: SponsorshipEvent::class, method: 'notifySponsor', priority: 0)]
final class SponsorshipEventListener implements ServiceSubscriberInterface
{
    use ContainerTypeTrait;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public static function getSubscribedServices(): array
    {
        return [
            MessageBusInterface::class,
            EntityManagerInterface::class,
            TranslatorInterface::class,
            CrowService::class,
            MediaService::class,
        ];
    }

    public function notifySponsor(SponsorshipEvent $event): void
    {
        $sponsor  = $event->sponsor;
        $newcomer = $event->newcomer;

        // Respect the sponsor's notification preference.
        if (!$sponsor->getSetting(UserSetting::NotifyMeOnSponsorRegistration)) {
            return;
        }

        $em   = $this->getService(EntityManagerInterface::class);
        $crow = $this->getService(CrowService::class);

        // Persist the in-game crow (Global PM) notification.
        $em->persist($crow->createPM_sponsorshipNotification($sponsor, $newcomer));

        try {
            $em->flush();
        } catch (\Throwable) {
            // Non-critical: don't let a notification failure break registration.
        }

        // Optional web-push notification.
        $lang = $sponsor->getLanguage() ?? 'en';
        $translator = $this->getService(TranslatorInterface::class);

        foreach ($sponsor->getNotificationSubscriptionsFor(NotificationSubscriptionType::WebPush) as $subscription) {
            $this->getService(MessageBusInterface::class)->dispatch(
                new WebPushMessage(
                    $subscription,
                    title: $translator->trans('Du hast einen neuen geworbenen Spieler!', [], 'global', $lang),
                    body:  $translator->trans(
                        '{player} hat sich über deinen Einladungslink registriert.',
                        ['player' => $newcomer],
                        'game',
                        $lang
                    ),
                    avatar: $this->getService(MediaService::class)
                        ->getSingleMediaForObject($newcomer, 'avatar')?->getId()
                )
            );
        }
    }
}
