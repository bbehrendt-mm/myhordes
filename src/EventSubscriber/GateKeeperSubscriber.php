<?php


namespace App\EventSubscriber;


use App\Controller\BeyondInterfaceController;
use App\Controller\GameAliveInterfaceController;
use App\Controller\GameInterfaceController;
use App\Controller\GameProfessionInterfaceController;
use App\Controller\GhostInterfaceController;
use App\Controller\TownController;
use App\Controller\TownInterfaceController;
use App\Entity\Citizen;
use App\Entity\User;
use App\Exception\DynamicAjaxResetException;
use App\Service\Locksmith;
use Doctrine\ORM\EntityManagerInterface;
use Proxies\__CG__\App\Entity\CitizenProfession;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class GateKeeperSubscriber implements EventSubscriberInterface
{
    private $token;
    private $em;
    private $locksmith;

    /** @var LockInterface|null  */
    private $current_lock = null;

    public function __construct(TokenStorageInterface $tokenStorage, EntityManagerInterface $em, Locksmith $locksmith)
    {
        $this->token = $tokenStorage;
        $this->em = $em;
        $this->locksmith = $locksmith;
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
            if (!$user || !$citizen = $this->em->getRepository(Citizen::class)->findActiveByUser($user))
                throw new DynamicAjaxResetException($event->getRequest());

            /** @var $citizen Citizen */
            $this->current_lock = $this->locksmith->waitForLock( 'game-' . $citizen->getTown()->getId() );

            if ($controller instanceof GameAliveInterfaceController) {
                // This is a game action controller; it is not available to players who are dead
                if (!$citizen->getAlive())
                    throw new DynamicAjaxResetException($event->getRequest());
            }

            if ($controller instanceof GameProfessionInterfaceController) {
                // This is a game profession controller; it is not available to players who have not chosen a profession
                // yet.
                if ($citizen->getProfession()->getName() === CitizenProfession::DEFAULT)
                    throw new DynamicAjaxResetException($event->getRequest());
            }

            if ($controller instanceof TownInterfaceController) {
                // This is a town controller; it is not available to players in the world beyond
                if ($citizen->getZone())
                    throw new DynamicAjaxResetException($event->getRequest());
            }

            if ($controller instanceof BeyondInterfaceController) {
                // This is a beyond controller; it is not available to players inside a town
                if (!$citizen->getZone())
                    throw new DynamicAjaxResetException($event->getRequest());
            }
        }
    }

    public function releaseTheDoor(ResponseEvent $event) {
        if ($this->current_lock) $this->current_lock->release();
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'holdTheDoor',
            KernelEvents::RESPONSE   => 'releaseTheDoor',
        ];
    }
}