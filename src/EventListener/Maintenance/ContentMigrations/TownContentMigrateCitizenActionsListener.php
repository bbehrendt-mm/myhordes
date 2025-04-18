<?php


namespace App\EventListener\Maintenance\ContentMigrations;

use App\Entity\Building;
use App\Entity\CitizenProfession;
use App\Entity\CitizenRole;
use App\Entity\SpecialActionPrototype;
use App\Event\Game\Town\Maintenance\TownContentMigrationEvent;
use App\EventListener\ContainerTypeTrait;
use App\Service\EventProxyService;
use App\Service\RandomGenerator;
use App\Service\TownHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: TownContentMigrationEvent::class, method: 'handle', priority: 400)]
class TownContentMigrateCitizenActionsListener extends TownContentMigrationListener
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            EntityManagerInterface::class,
            RandomGenerator::class,
            EventProxyService::class
        ]);
    }


    protected function getMigrationName(): string {
        return "Migrate citizen action set";
    }

    protected function applies( TownContentMigrationEvent $event ): bool {
        return true;
    }

    protected function execute( TownContentMigrationEvent $event ): void {
        $em = $this->getService(EntityManagerInterface::class);

        /** @var SpecialActionPrototype[] $actions */
        $actions = array_filter(array_map(
            fn(CitizenRole $role) => $em->getRepository(SpecialActionPrototype::class)->findOneBy(['name' => "special_vote_{$role->getName()}"]),
            $em->getRepository(CitizenRole::class)->findVotable()
        ), fn(?SpecialActionPrototype $a) => $a !== null);

        foreach ($event->town->getCitizens() as $citizen)
            if ($citizen->getAlive() && $citizen->getProfession()->getName() !== CitizenProfession::DEFAULT) {

                foreach ($actions as $action)
                    if (!$citizen->getSpecialActions()->contains($action)) {
                        $event->debug( "Citizen <fg=green>{$citizen->getId()}</> unlocked action <fg=yellow>{$action->getName()}</>." );
                        $citizen->addSpecialAction($action);
                        $em->persist($citizen);
                    }

            }

    }


}