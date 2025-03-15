<?php

namespace App\Service\Actions\Game;

use App\Entity\BuildingPrototype;
use App\Entity\Town;
use App\Enum\Configuration\TownSetting;
use App\Service\EventProxyService;
use App\Service\GameProfilerService;
use App\Service\TownHandler;
use App\Structures\TownConf;
use Doctrine\ORM\EntityManagerInterface;

readonly class InitializeTownBuildingsAction
{
    public function __construct(
        private EntityManagerInterface $em,
        private TownHandler            $townHandler,
        private GameProfilerService    $gps,
        private EventProxyService      $eventProxy,
    ) { }

    public function __invoke(
        Town $town,
        TownConf $conf,
        bool $trigger_gps = true,
    ): void
    {
        foreach ($this->em->getRepository(BuildingPrototype::class)->findProspectivePrototypes($town, $conf, 0) as $prototype)
            if (!in_array($prototype->getName(), $conf->get(TownSetting::DisabledBuildings))) {
                $this->townHandler->addBuilding($town, $prototype);
                if ($trigger_gps) $this->gps->recordBuildingDiscovered( $prototype, $town, null, 'always' );
            }

        $buildings_to_unlock = array_unique( array_merge( $conf->get(TownSetting::TownInitialBuildingsUnlocked), $conf->get(TownSetting::TownInitialBuildingsConstructed) ) );
        $failed_unlocks = $last_failed_unlocks = 0;
        do {
            $last_failed_unlocks = $failed_unlocks;
            $failed_unlocks = 0;
            foreach ($buildings_to_unlock as $str_prototype)
                if (!in_array($str_prototype, $conf->get(TownSetting::DisabledBuildings))) {
                    $prototype = $this->em->getRepository(BuildingPrototype::class)->findOneBy(['name' => $str_prototype]);
                    if ($prototype) {
                        if ($this->townHandler->addBuilding($town, $prototype)) {
                            if ($trigger_gps) $this->gps->recordBuildingDiscovered($prototype, $town, null, 'config');
                        }
                        else $failed_unlocks++;
                    }
                }
        } while ($failed_unlocks > 0 && $failed_unlocks !== $last_failed_unlocks);

        foreach ($conf->get(TownSetting::TownInitialBuildingsConstructed) as $str_prototype) {
            if (in_array($str_prototype, $conf->get(TownSetting::DisabledBuildings)))
                continue;

            /** @var BuildingPrototype $proto */
            $proto = $this->em->getRepository(BuildingPrototype::class)->findOneBy( ['name' => $str_prototype] );
            $b = $this->townHandler->addBuilding( $town, $proto );
            if ($b) $this->eventProxy->buildingConstruction( $b, 'config' );
        }
    }
}