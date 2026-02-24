<?php

namespace App\Messages\WebPush;

use App\Entity\Media;
use App\Entity\NotificationSubscription;
use App\Enum\NotificationSubscriptionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use WebPush\Message;
use WebPush\Notification;
use WebPush\WebPush;

#[AsMessageHandler]
readonly class WebPushMessageHandler
{
    public function __construct(
        private KernelInterface $kernel,
        private WebPush $sender,
        private EntityManagerInterface $em,
        private Packages $asset,
        private string $uri,
    ) {}

    private function buildPayload( WebPushMessage $message, bool $html_supported = false ): Message {

        $payload = Message::create("MyHordes: {$message->title}")
            ->withBody( $html_supported ? $message->body : html_entity_decode( strip_tags( $message->body ), ENT_QUOTES ) )
            ->withTimestamp( $message->timestamp->getTimestamp() * 1000 )
            ->withBadge( $this->uri . $this->asset->getUrl('build/favicon/android-chrome-72x72.png') );

        if ($message->avatar) {
            $avatar = $this->em->getRepository(Media::class)->find( $message->avatar )?->getLargestConversionByTag('default', 200);
            if ($avatar) $payload->withIcon( $avatar->url );
        }

        return $payload;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(WebPushMessage $message): void
    {
        // Do not attempt sending notifications in local environment
        if ($this->kernel->getEnvironment() === 'local') return;

        // Get the subscription
        $subscription = $this->em->getRepository(NotificationSubscription::class)->find( $message->subscription );

        // Only process WebPush subscriptions
        if ($subscription?->getType() !== NotificationSubscriptionType::WebPush) return;

        // We do not process expired subscriptions
        if ($subscription->isExpired()) return;

        // Check if the receiver is Firefox - it can render HTML in message bodies, for all other services, the HTML
        // needs to be escaped.
        //$domain = parse_url(
        //    Arr::get($subscription->getSubscription(), 'endpoint', 'https://domain.com/' ),
        //    PHP_URL_HOST
        //);
        //$html_supported = str_ends_with( $domain, 'mozilla.com' );

        // Push notification to subscriber service
        $response = $this->sender->send( Notification::create()
            ->withTTL(2419200)
            ->withPayload(
                $this->buildPayload( $message, false )->toString()
            ),
            $subscription
        );

        // If the subscription is expired, blacklist it
        if ($response?->isSubscriptionExpired()) {
            $this->em->persist( $subscription->setExpired( true ) );
            $this->em->flush();
        }
    }
}
