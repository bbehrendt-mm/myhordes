<?php


namespace App\Structures;

use DateTime;

class TownConf extends Conf
{
    const CONF_ALLOW_LOCAL           = 'allow_local_conf';

    const CONF_CLOSE_TOWN_AFTER      = 'open_town_limit';
    const CONF_CLOSE_TOWN_GRACE      = 'open_town_grace';
    const CONF_STRANGER_TOWN_AFTER   = 'stranger_day_limit';
    const CONF_STRANGER_TOWN_MIN     = 'stranger_citizen_limit';
    const CONF_LOCK_UNTIL_FULL       = 'lock_door_until_full';

    const CONF_WELL_MIN              = 'well.min';
    const CONF_WELL_MAX              = 'well.max';
    const CONF_MAP_MIN               = 'map.min';
    const CONF_MAP_MAX               = 'map.max';
    const CONF_MAP_MARGIN            = 'map.margin';
    const CONF_POPULATION_MIN        = 'population.min';
    const CONF_POPULATION_MAX        = 'population.max';

    const CONF_ZONE_ITEMS_MIN        = 'zone_items.min';
    const CONF_ZONE_ITEMS_MAX        = 'zone_items.max';
    const CONF_ZONE_ITEMS_RE_MIN     = 'zone_items.refresh_min';
    const CONF_ZONE_ITEMS_RE_MAX     = 'zone_items.refresh_max';
    const CONF_ZONE_ITEMS_TOTAL_MAX  = 'zone_items.fill_max';
    const CONF_ZONE_ITEMS_THROTTLE_AT= 'zone_items.throttle_at';

    const CONF_NUM_RUINS             = 'ruins';
    const CONF_NUM_EXPLORABLE_RUINS  = 'explorable_ruins';
    const CONF_BUILDINGS_CONSTRUCTED = 'initial_buildings';
    const CONF_BUILDINGS_UNLOCKED    = 'unlocked_buildings';
    const CONF_DISTRIBUTED_ITEMS     = 'distribute_items';
    const CONF_DEFAULT_CHEST_ITEMS   = 'initial_chest';
    const CONF_DISTRIBUTION_DISTANCE = 'distribution_distance';

    const CONF_INSTANT_PICTOS = 'instant_pictos';

    const CONF_EXPLORABLES_COMPLEXITY   = 'explorable_ruin_params.complexity';
    const CONF_EXPLORABLES_CONVOLUTION  = 'explorable_ruin_params.convolution';
    const CONF_EXPLORABLES_CRUELTY      = 'explorable_ruin_params.cruelty';
    const CONF_EXPLORABLES_ROOMS        = 'explorable_ruin_params.rooms';
    const CONF_EXPLORABLES_ROOM_DIST    = 'explorable_ruin_params.room_spacing';
    const CONF_EXPLORABLES_LOCKDIST     = 'explorable_ruin_params.lock_distance';
    const CONF_EXPLORABLES_ITEM_RATE    = 'explorable_ruin_params.item_fillrate';
    const CONF_EXPLORABLES_MAX_DISTANCE = 'explorable_ruin_params.max_distance';
    const CONF_EXPLORABLES_ZOMBIES_INI  = 'explorable_ruin_params.zombies.initial';
    const CONF_EXPLORABLES_ZOMBIES_DAY  = 'explorable_ruin_params.zombies.daily';

    const CONF_TIMES_DIG_NORMAL     = 'times.digging.normal';
    const CONF_TIMES_DIG_COLLEC     = 'times.digging.collec';
    const CONF_TIMES_EXPLORE_NORMAL = 'times.exploration.normal';
    const CONF_TIMES_EXPLORE_COLLEC = 'times.exploration.collec';

    const CONF_BANK_ABUSE_LIMIT       = 'bank_abuse.limit';
    const CONF_BANK_ABUSE_LIMIT_CHAOS = 'bank_abuse.chaos_limit';
    const CONF_BANK_ABUSE_BASE        = 'bank_abuse.base_range_min';
    const CONF_BANK_ABUSE_LOCK        = 'bank_abuse.lock_range_min';

    const CONF_MODIFIER_COMPLAINTS_SHUN     = 'modifiers.complaints.shun';
    const CONF_MODIFIER_COMPLAINTS_KILL     = 'modifiers.complaints.kill';

    const CONF_MODIFIER_POISON_STACK         = 'modifiers.poison.stack_poisoned_items';
    const CONF_MODIFIER_POISON_TRANS         = 'modifiers.poison.transgress';
    const CONF_MODIFIER_WT_THRESHOLD         = 'modifiers.watchtower_estimation_threshold';
    const CONF_MODIFIER_WT_OFFSET            = 'modifiers.watchtower_estimation_offset';
    const CONF_MODIFIER_ALLOW_REDIGS         = 'modifiers.allow_redig';
    const CONF_MODIFIER_FLOOR_ASMBLY         = 'modifiers.assemble_items_from_floor';
    const CONF_MODIFIER_PRE_ASSEMBLY         = 'modifiers.preview_item_assemblage';
    const CONF_MODIFIER_INFECT_DEATH         = 'modifiers.infection_death_chance';
    const CONF_MODIFIER_WOUND_TERROR_PENALTY = 'modifiers.wound_terror_penalty';
    const CONF_MODIFIER_ATTACK_PROTECT       = 'modifiers.citizen_attack.protection';
    const CONF_MODIFIER_ATTACK_AP            = 'modifiers.citizen_attack.ap';
    const CONF_MODIFIER_ATTACK_CHANCE        = 'modifiers.citizen_attack.injury';
    const CONF_MODIFIER_CARRY_EXTRA_BAG      = 'modifiers.carry_extra_bag';
    const CONF_MODIFIER_BONES_IN_TOWN        = 'modifiers.meaty_bones_within_town';
    const CONF_MODIFIER_BUILDING_DAMAGE      = 'modifiers.building_attack_damage';
    const CONF_MODIFIER_DO_DESTROY           = 'modifiers.destroy_defense_objects_attack';
    const CONF_MODIFIER_CAMPING_BONUS        = 'modifiers.camping.default_bonus';
    const CONF_MODIFIER_CAMPING_CHANCE_MAP   = 'modifiers.camping.map';
    const CONF_MODIFIER_RED_SOUL_FACTOR      = 'modifiers.red_soul_max_factor';
    const CONF_MODIFIER_SANDBALL_NASTYNESS   = 'modifiers.sandball_nastyness';
    const CONF_MODIFIER_WIND_DISTANCE        = 'modifiers.wind_distance';
    const CONF_MODIFIER_STRICT_PICTOS        = 'modifiers.strict_picto_distribution';
    const CONF_MODIFIER_RESPAWN_FACTOR       = 'modifiers.massive_respawn_factor';
    const CONF_MODIFIER_AUTOGHOUL_FROM       = 'modifiers.ghoul_infection_begin';
    const CONF_MODIFIER_DAYTIME_RANGE        = 'modifiers.daytime.range';
    const CONF_MODIFIER_DAYTIME_INVERT       = 'modifiers.daytime.invert';
    const CONF_MODIFIER_HIDE_HOME_UPGRADE    = 'modifiers.hide_home_upgrade';

    const CONF_FEATURE_CAMPING         = 'features.camping';
    const CONF_FEATURE_NIGHTMODE       = 'features.nightmode';
    const CONF_FEATURE_SHAMAN_MODE     = 'features.shaman';
    const CONF_FEATURE_WORDS_OF_HEROS  = 'features.words_of_heros';
    const CONF_FEATURE_ESCORT          = 'features.escort.enabled';
    const CONF_FEATURE_ESCORT_SIZE     = 'features.escort.max';
    const CONF_FEATURE_XML             = 'features.xml_feed';
    const CONF_FEATURE_CITIZEN_ALIAS   = 'features.citizen_alias';
    const CONF_FEATURE_GHOUL_MODE      = 'features.ghoul_mode';
    const CONF_FEATURE_GHOULS_HUNGRY   = 'features.hungry_ghouls';
    const CONF_FEATURE_ALL_POISON      = 'features.all_poison';
    const CONF_FEATURE_SHUN            = 'features.shun';
    const CONF_FEATURE_NIGHTWATCH      = 'features.nightwatch.enabled';
    const CONF_FEATURE_NIGHTWATCH_INSTANT = 'features.nightwatch.instant';
    const CONF_FEATURE_ATTACKS         = 'features.attacks';
    const CONF_FEATURE_GIVE_ALL_PICTOS = 'features.give_all_pictos';
    const CONF_FEATURE_GIVE_SOULPOINTS = 'features.give_soulpoints';
    const CONF_FEATURE_LAST_DEATH      = 'features.last_death';
    const CONF_FEATURE_SURVIVAL_PICTO  = 'features.survival_picto';

    const CONF_GUIDE_ENABLED    = 'spiritual_guide.enabled';
    const CONF_GUIDE_SP_LIMIT   = 'spiritual_guide.sp_limit';
    const CONF_GUIDE_CTC_LIMIT  = 'spiritual_guide.citizen';

    const CONF_DISABLED_JOBS = 'disabled_jobs';
    const CONF_DISABLED_ROLES = 'disabled_roles';
    const CONF_DISABLED_BUILDINGS = 'disabled_buildings';

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

    public function isNightMode(?DateTime $dateTime = null): bool {
        return $this->get(TownConf::CONF_FEATURE_NIGHTMODE, true) && $this->isNightTime($dateTime);
    }

    public function isNightTime(?DateTime $dateTime = null): bool {
        $h = (int)($dateTime ?? new DateTime())->format('H');
        $range = $this->get(TownConf::CONF_MODIFIER_DAYTIME_RANGE, [7,18]);
        return $this->get(TownConf::CONF_MODIFIER_DAYTIME_INVERT, false) !==
            ($h < $range[0] || $h > $range[1]);
    }
}