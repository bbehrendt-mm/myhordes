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
    const CONF_INSTANT_PICTOS = 'instant_pictos';

    const CONF_ESTIM_INITIAL_SHIFT  = 'estimation.shift';
    const CONF_ESTIM_SPREAD         = 'estimation.spread';
    const CONF_ESTIM_VARIANCE       = 'estimation.variance';
    const CONF_ESTIM_OFFSET_MIN     = 'estimation.offset.min';
    const CONF_ESTIM_OFFSET_MAX     = 'estimation.offset.max';

    const CONF_SCAVENGING_PLAN_LIMIT_B = 'zone_items.plan_limits.bag';

    const CONF_BANK_ABUSE_LIMIT       = 'bank_abuse.limit';
    const CONF_BANK_ABUSE_LIMIT_CHAOS = 'bank_abuse.chaos_limit';
    const CONF_BANK_ABUSE_BASE        = 'bank_abuse.base_range_min';
    const CONF_BANK_ABUSE_LOCK        = 'bank_abuse.lock_range_min';


    const CONF_GUIDE_ENABLED    = 'spiritual_guide.enabled';
    const CONF_GUIDE_SP_LIMIT   = 'spiritual_guide.sp_limit';
    const CONF_GUIDE_CTC_LIMIT  = 'spiritual_guide.citizen';

    const CONF_OVERRIDE_ITEM_GROUP  = 'overrides.item_groups';
    const CONF_OVERRIDE_NAMED_DROPS = 'overrides.named_drops';

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

        if (in_array( 'with-toxin', $this->get( self::CONF_OVERRIDE_NAMED_DROPS ) ))
            $base[] = DropMod::Infective;

        if ($this->get( TownSetting::OptFeatureGhoulMode ) === 'childtown') $remove[] = DropMod::Ghouls;
        if (!$this->get( TownSetting::OptFeatureNightmode )) $remove[] = DropMod::NightMode;
        if (!$this->get( TownSetting::OptFeatureCamping )) $remove[] = DropMod::Camp;

        return array_filter( $base, fn(DropMod $d) => !in_array( $d, $remove) );
    }
}