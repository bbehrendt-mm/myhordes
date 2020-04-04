<?php


namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LanguageSubscriber implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event) {
        // Set locale
        $langs = $event->getRequest()->getLanguages();
        if ($langs)
            $event->getRequest()->setLocale( $langs[0] );
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}