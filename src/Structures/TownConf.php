<?php


namespace App\Structures;

use App\Enum\Configuration\Configuration;
use App\Enum\Configuration\TownSetting;
use App\Enum\DropMod;
use DateTime;

/**
 * @method get(string|TownSetting $key, $default = null): mixed
 */
class TownConf extends Conf
{
    public function __construct(array $data)
    {
        $first = false;
        foreach ( $data as $conf_block )
            if ($conf_block === null) continue;
            elseif (!$first) {
                parent::__construct( $conf_block );
                $first = true;
            }
            else $this->import( $conf_block );
    }

    public function isNightMode(?DateTime $dateTime = null, bool $ignoreNightModeConfig = false): bool {
        return ($ignoreNightModeConfig || $this->get(TownSetting::OptFeatureNightmode)) && $this->isNightTime($dateTime);
    }

    public function isNightTime(?DateTime $dateTime = null): bool {
        $h = (int)($dateTime ?? new DateTime())->format('H');
        $range = $this->get(TownSetting::OptModifierDaytimeRange);
        return $this->get(TownSetting::OptModifierDaytimeInvert) !==
            ($h < $range[0] || $h > $range[1]);
    }

    public function dropMods(): array {
        $base = DropMod::defaultMods();
        $remove = [];

        if (in_array( 'with-toxin', $this->get( TownSetting::OptModifierOverrideNamedDrops ) ))
            $base[] = DropMod::Infective;

        if ($this->get( TownSetting::OptFeatureGhoulMode ) === 'childtown') $remove[] = DropMod::Ghouls;
        if (!$this->get( TownSetting::OptFeatureNightmode )) $remove[] = DropMod::NightMode;
        if (!$this->get( TownSetting::OptFeatureCamping )) $remove[] = DropMod::Camp;

        return array_filter( $base, fn(DropMod $d) => !in_array( $d, $remove) );
    }
}