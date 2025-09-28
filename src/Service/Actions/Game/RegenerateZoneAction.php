<?php

namespace App\Service\Actions\Game;



use App\Entity\Citizen;
use App\Entity\CitizenProfession;
use App\Entity\Zone;
use App\Enum\Configuration\TownSetting;
use App\Service\ConfMaster;
use App\Service\RandomGenerator;
use Psr\Log\LoggerInterface;

readonly class RegenerateZoneAction
{
    public function __construct(
        private ConfMaster $confMaster,
        private RandomGenerator $random,
    ) { }

    public function __invoke(
        Zone $zone,
        float $chance,
        bool $force = false,
        bool $ruin_regeneration = true,
        ?LoggerInterface $log = null
    ): bool
    {
        $conf = $this->confMaster->getTownConfiguration( $zone->getTown() );
        $regenerate_by = $conf->get(TownSetting::MapZoneDropCountRefresh);

        $dropChanceFactor = $zone->getDigs() >= $conf->get(TownSetting::MapZoneDropCountThreshold)
            ? 0.33
            : 1;

        $regenerate_base = 0;
        $regenerate_ruin = 0;

        if ($force || $this->random->chance($chance * $dropChanceFactor)) {
            $regenerate_base = $zone->getDigs() + mt_rand($regenerate_by, 2 * $regenerate_by - 1);
            $zone->setDigs($zone->getDigs() + $regenerate_base);
            $log?->debug("Zone <info>{$zone->getX()}/{$zone->getY()}</info>: Recovering by <info>{$regenerate_base}</info> to <info>{$zone->getDigs()}</info>.");
        }

        if (!$zone->getPrototype() || !$ruin_regeneration) return $regenerate_base > 0;

        $ruinChanceFactor = $zone->getRuinDigs() >= $conf->get(TownSetting::MapZoneDropCountThreshold)
            ? 0.33
            : 1;

        if ($force || $this->random->chance($chance * $ruinChanceFactor)) {
            $regenerate_ruin = mt_rand($regenerate_by, 2 * $regenerate_by - 1);
            $zone->setRuinDigs($zone->getRuinDigs() + $regenerate_ruin);
            $log->debug("Zone <info>{$zone->getX()}/{$zone->getY()}</info>: Recovering ruin by <info>{$regenerate_ruin}</info> to <info>{$zone->getRuinDigs()}</info>.");
        }

        return $regenerate_base > 0 || $regenerate_ruin > 0;
    }
}
