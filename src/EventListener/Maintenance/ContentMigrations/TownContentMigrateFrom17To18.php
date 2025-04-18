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

#[AsEventListener(event: TownContentMigrationEvent::class, method: 'handle', priority: 800 - 17)]
class TownContentMigrateFrom17To18 extends TownContentMigrateBuildingTreeListener
{
    use PrimeInfo;

    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [

        ]);
    }

    protected function getMigrationName(): string {
        return "MyHordes 4.0 Migrations";
    }

    protected function applies( TownContentMigrationEvent $event ): bool {
        return self::primePackageVersionIdentifierSatisfies( $event->town->getPrime(), '^3.0.0', package: 'myhordes/prime-csc' );
    }


    protected function execute( TownContentMigrationEvent $event ): void {
        if ($event->town->getType()->getName() === TownClass::HARD) {
            $event->debug( "Pre-existing HC town requires <bg=green>remote construction tree settings</> to be set." );

            $conf = $event->town->getConf();
            Arr::set( $conf, TownSetting::TownInitialBuildingsUnlocked->value . '.replace', [] );
            Arr::set( $conf, TownSetting::OptModifierOverrideBuildingRarity->value . '.replace', [] );
            Arr::set( $conf, TownSetting::OptFeatureBlueprintMode->value, 'unlock' );
            Arr::set( $conf, TownSetting::OptModifierBuildingDifficulty->value, 0 );
            $event->town->setConf( $conf );
        }

        $event->town->setPrime( self::buildPrimePackageVersionIdentifier(version: '4.0.0.0') );
    }
}