<?php


namespace App\EventSubscriber;


use App\Controller\BeyondInterfaceController;
use App\Controller\GameInterfaceController;
use App\Controller\GameProfessionInterfaceController;
use App\Controller\GhostInterfaceController;
use App\Controller\TownController;
use App\Controller\TownInterfaceController;
use App\Entity\Citizen;
use App\Entity\User;
use App\Exception\DynamicAjaxResetException;
use Doctrine\ORM\EntityManagerInterface;
use Proxies\__CG__\App\Entity\CitizenProfession;
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
            if (!$user || !$citizen = $this->em->getRepository(Citizen::class)->findActiveByUser($user))
                throw new DynamicAjaxResetException($event->getRequest());

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