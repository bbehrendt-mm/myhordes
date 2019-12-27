<?php


namespace App\EventSubscriber;


use App\Controller\GameInterfaceController;
use App\Controller\GhostInterfaceController;
use App\Entity\Citizen;
use App\Entity\User;
use App\Exception\DynamicAjaxResetException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class GateKeeperSubscriber implements EventSubscriberInterface
{
    private $token;
    private $em;

    public function __construct(TokenStorageInterface $tokenStorage, EntityManagerInterface $em)
    {
        $this->token = $tokenStorage;
        $this->em = $em;
    }

    public function holdTheDoor(ControllerEvent $event) {
        $controller = $event->getController();
        if (is_array($controller)) $controller = $controller[0];

        $token = $this->token->getToken();
        /** @var User $user */
        $user = ($token && $token->isAuthenticated()) ? $token->getUser() : null;
        if ($user !== null && !($user instanceof User)) $user = null;

        if ($controller instanceof GhostInterfaceController) {
            // This is a ghost controller; it is not available to players in a game
            if (!$user || $this->em->getRepository(Citizen::class)->findActiveByUser($user))
                throw new DynamicAjaxResetException($event->getRequest());
        }

        if ($controller instanceof GameInterfaceController) {
            // This is a game controller; it is not available to players outside of a game
            if (!$user || !$this->em->getRepository(Citizen::class)->findActiveByUser($user))
                throw new DynamicAjaxResetException($event->getRequest());
        }
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'holdTheDoor',
        ];
    }
}