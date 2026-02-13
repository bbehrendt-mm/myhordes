<?php


namespace App\EventListener\Maintenance\ContentMigrations;

use App\Entity\TownClass;
use App\Enum\Configuration\CitizenProperties;
use App\Enum\Configuration\TownSetting;
use App\Event\Game\Town\Maintenance\TownContentMigrationEvent;
use App\Service\EventProxyService;
use App\Service\User\UserUnlockableService;
use App\Service\UserHandler;
use App\Traits\System\PrimeInfo;
use ArrayHelpers\Arr;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: TownContentMigrationEvent::class, method: 'handle', priority: 800 - 19)]
class TownContentMigrateFrom18To19 extends TownContentMigrateBuildingTreeListener
{
    use PrimeInfo;

    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [

        ]);
    }

    protected function getMigrationName(): string {
        return "MyHordes 5.0 Migrations";
    }

    protected function applies( TownContentMigrationEvent $event ): bool {
        return self::primePackageVersionIdentifierSatisfies( $event->town->getPrime(), '^4.0.0' );
    }


    protected function execute( TownContentMigrationEvent $event ): void {
        $event->town->setPrime( self::buildPrimePackageVersionIdentifier(version: '5.0.0.0') );
    }
}
