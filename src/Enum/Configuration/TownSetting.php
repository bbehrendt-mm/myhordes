<?php

namespace App\Enum\Configuration;

enum TownSetting: string implements Configuration
{


    //<editor-fold desc="Core Settings">
    case AllowLocalConfiguration = 'allow_local_conf';
    case CreateQAPost = 'qa_post';
    //</editor-fold>

    //<editor-fold desc="Town Start Meta Settings">
    case Section_TownStartMeta = '--section--/TownStartMeta';
    case CancelTownAfterDaysWithoutFilling = 'open_town_limit';
    case DoNotCancelAfterCitizensReached = 'open_town_grace';
    case SpawnStrangerAfterUnfilledDays = 'stranger_day_limit';
    case SpawnStrangerAfterCitizenCount = 'stranger_citizen_limit';
    case LockDoorUntilTownIsFull = 'lock_door_until_full';
    case PopulationMin = 'population.min';
    case PopulationMax = 'population.max';
    case SkillMode = 'skill_mode';

    case DisabledJobs = 'disabled_jobs';
    case DisabledRoles = 'disabled_roles';
    case DisabledBuildings = 'disabled_buildings';
    //</editor-fold>

    //<editor-fold desc="Town Well Settings">
    case Section_Well = '--section--/Well';
    case DefaultWellFillMin = 'well.min';
    case DefaultWellFillMax = 'well.max';
    //</editor-fold>

    //<editor-fold desc="Town Map Settings">
    case Section_Map = '--section--/Map';

    //<editor-fold desc="Town Map Beyond Settings">
    case Section_Map_Beyond = '--section--/Map/Beyond';
    case MapSizeMin = 'map.min';
    case MapSizeMax = 'map.max';
    case MapSafeMargin = 'map.margin';

    case MapUseCustomMargin = 'margin_custom.enabled';
    case MapCustomMarginNorth = 'margin_custom.north';
    case MapCustomMarginSouth = 'margin_custom.south';
    case MapCustomMarginWest = 'margin_custom.west';
    case MapCustomMarginEast = 'margin_custom.east';

    case MapZoneDropCountInitializer = 'zone_items.min';
    case MapZoneDropCountThreshold = 'zone_items.max';
    case MapZoneDropCountRefresh = 'zone_items.refresh_max';

    case MapRuinCount = 'ruins';
    case MapExplorableRuinCount = 'explorable_ruins';

    case MapRuinItemsMin = 'ruin_items.min';
    case MapRuinItemsMax = 'ruin_items.max';

    case MapParamsFreeSpawnCount = 'map_params.free_spawn_zones.count';
    case MapParamsFreeSpawnDist  = 'map_params.free_spawn_zones.min_dist';
    case MapParamsBuriedProb      = 'map_params.buried_ruins.probability';
    case MapParamsBuriedDigsMin  = 'map_params.buried_ruins.digs.min';
    case MapParamsBuriedDigsMax  = 'map_params.buried_ruins.digs.max';
    case MapParamsDigChancesBase     = 'map_params.dig_chances.base';
    case MapParamsDigChancesDepleted = 'map_params.dig_chances.depleted';
    //</editor-fold>

    //</editor-fold>

    //<editor-fold desc="Town E-Ruin Settings">
    case Section_Explorable = '--section--/Explorable';

    case ERuinItemFillrate = 'explorable_ruin_params.item_fillrate';
    case ERuinMaxDistanceFromTown = 'explorable_ruin_params.max_distance';

    //<editor-fold desc="Town E-Ruin Room Settings">
    case Section_Explorable_Rooms = '--section--/Explorable/Rooms';
    case ERuinRoomLockDistance = 'explorable_ruin_params.room_config.lock';
    case ERuinRoomDistance = 'explorable_ruin_params.room_config.distance';
    case ERuinRoomSpacing = 'explorable_ruin_params.room_config.spacing';
    case ERuinRoomCountTotal = 'explorable_ruin_params.room_config.total';
    case ERuinRoomCountMinPerFloor = 'explorable_ruin_params.room_config.min';
    //</editor-fold>

    //<editor-fold desc="Town E-Ruin Space Settings">
    case Section_Explorable_Space = '--section--/Explorable/Space';
    case ERuinSpaceFloors = 'explorable_ruin_params.space.floors';
    case ERuinSpaceMaxSizeX = 'explorable_ruin_params.space.x';
    case ERuinSpaceMaxSizeY = 'explorable_ruin_params.space.y';
    case ERuinSpaceOffsetX = 'explorable_ruin_params.space.ox';
    //</editor-fold>

    //<editor-fold desc="Town E-Ruin Blueprint Settings">
    case Section_Explorable_BP = '--section--/Explorable/BP';
    case ERuinBPUnusual = 'explorable_ruin_params.plan_limits.unusual';
    case ERuinBPRare = 'explorable_ruin_params.plan_limits.rare';
    case ERuinBPEpic = 'explorable_ruin_params.plan_limits.epic';
    //</editor-fold>

    //<editor-fold desc="Town E-Ruin Zombie Settings">
    case Section_Explorable_Zombies = '--section--/Explorable/Zombies';
    case ERuinZombiesInitial = 'explorable_ruin_params.zombies.initial';
    case ERuinZombiesDaily = 'explorable_ruin_params.plan_limits.daily';
    //</editor-fold>

    //</editor-fold>

    //<editor-fold desc="Town Timing Settings">
    case Section_Timing = '--section--/Timing';

    //<editor-fold desc="Town Timing for Digging Settings">
    case Section_Timing_Digging = '--section--/Explorable/Digging';
    case TimingDiggingDefault = 'times.digging.normal';
    case TimingDiggingCollector = 'times.digging.collec';
    //</editor-fold>

    //<editor-fold desc="Town Timing for Exploration Settings">
    case Section_Timing_Exploration = '--section--/Explorable/Exploration';
    case TimingExplorationDefault = 'times.exploration.normal';
    case TimingExplorationCollector = 'times.exploration.collec';
    //</editor-fold>

    //</editor-fold>

    //<editor-fold desc="Town Reward Settings">
    case Section_Rewards = '--section--/Rewards';

    //<editor-fold desc="Town Timing for Digging Settings">
    case Section_Rewards_Pictos = '--section--/Rewards/Pictos';

    case PictoClassicCullMode = 'features.picto_classic_cull_mode';
    //</editor-fold>

    //</editor-fold>

    //<editor-fold desc="Town Initializer Settings">
    case Section_Initial = '--section--/Initial';

    case TownInitialBuildingsConstructed = 'initial_buildings';
    case TownInitialBuildingsUnlocked    = 'unlocked_buildings';
    case TownInitialDistributesItems     = 'distribute_items';
    case TownInitialDistributionDistance = 'distribution_distance';
    case TownInitialChestItems   = 'initial_chest';

    //</editor-fold>

    //<editor-fold desc="Town Features and Modifiers">
    case Section_Opts = '--section--/Opts';

    //<editor-fold desc="Town Features">
    case Section_Opts_Features = '--section--/Opts/Features';

    case OptFeatureCamping         = 'features.camping';
    case OptFeatureNightmode       = 'features.nightmode';
    case OptFeatureShamanMode     = 'features.shaman';
    case OptFeatureWordsOfHeros  = 'features.words_of_heros';
    case OptFeatureEscort          = 'features.escort.enabled';
    case OptFeatureEscortSize     = 'features.escort.max';
    case OptFeatureXml             = 'features.xml_feed';
    case OptFeatureCitizenAlias   = 'features.citizen_alias';
    case OptFeatureGhoulMode      = 'features.ghoul_mode';
    case OptFeatureGhoulsHungry   = 'features.hungry_ghouls';
    case OptFeatureAllPoison      = 'features.all_poison';
    case OptFeatureShun            = 'features.shun';
    case OptFeatureNightwatch      = 'features.nightwatch.enabled';
    case OptFeatureNightwatchInstant = 'features.nightwatch.instant';
    case OptFeatureAttacks         = 'features.attacks';
    case OptFeatureGiveAllPictos = 'features.give_all_pictos';
    case OptFeaturePictos          = 'features.enable_pictos';
    case OptFeatureGiveSoulpoints = 'features.give_soulpoints';
    case OptFeatureLastDeath      = 'features.last_death';
    case OptFeatureLastDeathDay  = 'features.last_death_day';
    case OptFeatureSurvivalPicto  = 'features.survival_picto';
    case OptFeatureNoSpRequired  = 'features.free_for_all';
    case OptFeatureNoTeams  = 'features.free_from_teams';
    case OptFeatureGuideEnabled    = 'spiritual_guide.enabled';
    case OptFeatureGuideSpLimit   = 'spiritual_guide.sp_limit';
    case OptFeatureGuideCtcLimit  = 'spiritual_guide.citizen';
    case OptFeatureBlueprintMode  = 'features.blueprint_mode';
    //</editor-fold>

    //<editor-fold desc="Town Modifiers">
    case Section_Opts_Modifiers = '--section--/Opts/Modifiers';

    case OptModifierComplaintsShun     = 'modifiers.complaints.shun';
    case OptModifierComplaintsKill     = 'modifiers.complaints.kill';
    case OptModifierPoisonStack         = 'modifiers.poison.stack_poisoned_items';
    case OptModifierPoisonTrans         = 'modifiers.poison.transgress';
    case OptModifierWtThreshold         = 'modifiers.watchtower_estimation_threshold';
    case OptModifierWtOffset            = 'modifiers.watchtower_estimation_offset';
    case OptModifierAllowRedigs         = 'modifiers.allow_redig';
    case OptModifierFloorAsmbly         = 'modifiers.assemble_items_from_floor';
    case OptModifierPreAssembly         = 'modifiers.preview_item_assemblage';
    case OptModifierInfectDeath         = 'modifiers.infection_death_chance';
    case OptModifierWoundTerrorPenalty = 'modifiers.wound_terror_penalty';
    case OptModifierAttackProtect       = 'modifiers.citizen_attack.protection';
    case OptModifierAttackAp            = 'modifiers.citizen_attack.ap';
    case OptModifierAttackChance        = 'modifiers.citizen_attack.injury';
    case OptModifierCarryExtraBag      = 'modifiers.carry_extra_bag';
    case OptModifierBonesInTown        = 'modifiers.meaty_bones_within_town';
    case OptModifierBuildingDamage      = 'modifiers.building_attack_damage';
    case OptModifierDoDestroy           = 'modifiers.destroy_defense_objects_attack';
    case OptModifierDoDestroyRatio     = 'modifiers.destroy_defense_objects_attack_ratio';
    case OptModifierDoDestroyMax       = 'modifiers.destroy_defense_objects_attack_max';
    case OptModifierCampingBonus        = 'modifiers.camping.default_bonus';
    case OptModifierCampingChanceMap   = 'modifiers.camping.map';
    case OptModifierRedSoulFactor      = 'modifiers.red_soul_max_factor';
    case OptModifierSandballNastyness   = 'modifiers.sandball_nastyness';
    case OptModifierWindDistance        = 'modifiers.wind_distance';
    case OptModifierStrictPictos        = 'modifiers.strict_picto_distribution';
    case OptModifierRespawnFactor       = 'modifiers.massive_respawn_factor';
    case OptModifierRespawnThreshold    = 'modifiers.massive_respawn_threshold';
    case OptModifierAutoghoulFrom       = 'modifiers.ghoul_infection_begin';
    case OptModifierAutoghoulAdvance       = 'modifiers.ghoul_infection_advance';
    case OptModifierAutoghoulMax       = 'modifiers.ghoul_infection_max';
    case OptModifierDaytimeRange        = 'modifiers.daytime.range';
    case OptModifierDaytimeInvert       = 'modifiers.daytime.invert';
    case OptModifierHideHomeUpgrade    = 'modifiers.hide_home_upgrade';
    case OptModifierRecyclingAp         = 'modifiers.home_recycling.ap';
    case OptModifierRecyclingReturn     = 'modifiers.home_recycling.return';
    case OptModifierGenerosityGhoul     = 'modifiers.generosity.from_ghoul';
    case OptModifierGenerosityLast     = 'modifiers.generosity.from_last_death_factor';
    case OptModifierGuardtowerMax     = 'modifiers.guard_tower.max_def';
    case OptModifierGuardtowerUnit    = 'modifiers.guard_tower.per_use';
    case OptModifierStrangeSoil        = 'modifiers.strange_soil';
    case OptModifierSoulGenerationCoef = 'modifiers.soul_generation_coef';
    case OptModifierInstantPictos = 'instant_pictos';

    case OptModifierEstimInitialShift  = 'estimation.shift';
    case OptModifierEstimSpread         = 'estimation.spread';
    case OptModifierEstimVariance       = 'estimation.variance';
    case OptModifierEstimOffsetMin     = 'estimation.offset.min';
    case OptModifierEstimOffsetMax     = 'estimation.offset.max';

    case OptModifierScavengingPlanLimitB = 'zone_items.plan_limits.bag';

    case OptModifierBankAbuseLimit       = 'bank_abuse.limit';
    case OptModifierBankAbuseLimitChaos = 'bank_abuse.chaos_limit';
    case OptModifierBankAbuseBase        = 'bank_abuse.base_range_min';
    case OptModifierBankAbuseLock        = 'bank_abuse.lock_range_min';

    case OptModifierOverrideItemGroup  = 'overrides.item_groups';
    case OptModifierOverrideNamedDrops = 'overrides.named_drops';
    case OptModifierOverrideBuildingRarity = 'overrides.building_rarity';
    case OptModifierBuildingDifficulty = 'modifiers.building_difficulty';

    //</editor-fold>

    //</editor-fold>

    public function abstract(): bool
    {
        return match ($this) {
            self::Section_TownStartMeta,
            self::Section_Well,
            self::Section_Map,
            self::Section_Map_Beyond,
            self::Section_Explorable,
            self::Section_Explorable_Rooms,
            self::Section_Explorable_Space,
            self::Section_Explorable_BP,
            self::Section_Explorable_Zombies,
            self::Section_Timing,
            self::Section_Timing_Digging,
            self::Section_Timing_Exploration,
            self::Section_Rewards,
            self::Section_Rewards_Pictos,
            self::Section_Initial,
            self::Section_Opts,
            self::Section_Opts_Features,
            self::Section_Opts_Modifiers
                => true,

            default => false
        };
    }

    public function parent(): ?TownSetting {
        return match ($this) {
            self::CancelTownAfterDaysWithoutFilling,
            self::DoNotCancelAfterCitizensReached,
            self::SpawnStrangerAfterUnfilledDays,
            self::SpawnStrangerAfterCitizenCount,
            self::LockDoorUntilTownIsFull,
            self::PopulationMin,
            self::PopulationMax,
            self::SkillMode,
            self::DisabledJobs,
            self::DisabledRoles,
            self::DisabledBuildings => self::Section_TownStartMeta,

            self::DefaultWellFillMin,
            self::DefaultWellFillMax => self::Section_Well,

            self::Section_Map_Beyond => self::Section_Map,
            self::MapSizeMin,
            self::MapSizeMax,
            self::MapSafeMargin,
            self::MapUseCustomMargin,
            self::MapCustomMarginNorth,
            self::MapCustomMarginSouth,
            self::MapCustomMarginWest,
            self::MapCustomMarginEast,
            self::MapZoneDropCountInitializer,
            self::MapZoneDropCountThreshold,
            self::MapZoneDropCountRefresh,
            self::MapRuinCount,
            self::MapExplorableRuinCount,
            self::MapRuinItemsMin,
            self::MapRuinItemsMax,
            self::MapParamsFreeSpawnCount,
            self::MapParamsFreeSpawnDist,
            self::MapParamsBuriedProb,
            self::MapParamsBuriedDigsMin,
            self::MapParamsBuriedDigsMax,
            self::MapParamsDigChancesBase,
            self::MapParamsDigChancesDepleted => self::Section_Map_Beyond,

            self::ERuinItemFillrate,
            self::ERuinMaxDistanceFromTown,
            self::Section_Explorable_Rooms,
            self::Section_Explorable_Space,
            self::Section_Explorable_BP,
            self::Section_Explorable_Zombies  => self::Section_Explorable,

            self::ERuinRoomLockDistance,
            self::ERuinRoomDistance,
            self::ERuinRoomSpacing,
            self::ERuinRoomCountTotal,
            self::ERuinRoomCountMinPerFloor => self::Section_Explorable_Rooms,

            self::ERuinSpaceFloors,
            self::ERuinSpaceMaxSizeX,
            self::ERuinSpaceMaxSizeY,
            self::ERuinSpaceOffsetX => self::Section_Explorable_Space,

            self::ERuinBPUnusual,
            self::ERuinBPRare,
            self::ERuinBPEpic => self::Section_Explorable_BP,

            self::ERuinZombiesInitial,
            self::ERuinZombiesDaily => self::Section_Explorable_Zombies,

            self::Section_Timing_Digging,
            self::Section_Timing_Exploration => self::Section_Timing,

            self::TimingDiggingDefault,
            self::TimingDiggingCollector => self::Section_Timing_Digging,

            self::TimingExplorationDefault,
            self::TimingExplorationCollector => self::Section_Timing_Exploration,

            self::Section_Rewards_Pictos => self::Section_Rewards,
            self::PictoClassicCullMode => self::Section_Rewards_Pictos,

            self::TownInitialBuildingsConstructed,
            self::TownInitialBuildingsUnlocked,
            self::TownInitialDistributesItems,
            self::TownInitialDistributionDistance,
            self::TownInitialChestItems => self::Section_Initial,

            self::Section_Opts_Features,
            self::Section_Opts_Modifiers => self::Section_Opts,

            self::OptFeatureCamping,
            self::OptFeatureNightmode,
            self::OptFeatureShamanMode,
            self::OptFeatureWordsOfHeros,
            self::OptFeatureEscort,
            self::OptFeatureEscortSize,
            self::OptFeatureXml,
            self::OptFeatureCitizenAlias,
            self::OptFeatureGhoulMode,
            self::OptFeatureGhoulsHungry,
            self::OptFeatureAllPoison,
            self::OptFeatureShun,
            self::OptFeatureNightwatch,
            self::OptFeatureNightwatchInstant,
            self::OptFeatureAttacks,
            self::OptFeatureGiveAllPictos,
            self::OptFeaturePictos,
            self::OptFeatureGiveSoulpoints,
            self::OptFeatureLastDeath,
            self::OptFeatureLastDeathDay,
            self::OptFeatureSurvivalPicto,
            self::OptFeatureNoSpRequired,
            self::OptFeatureNoTeams,
            self::OptFeatureGuideEnabled,
            self::OptFeatureGuideSpLimit,
            self::OptFeatureGuideCtcLimit,
            self::OptFeatureBlueprintMode => self::Section_Opts_Features,

            self::OptModifierComplaintsShun,
            self::OptModifierComplaintsKill,
            self::OptModifierPoisonStack,
            self::OptModifierPoisonTrans,
            self::OptModifierWtThreshold,
            self::OptModifierWtOffset,
            self::OptModifierAllowRedigs,
            self::OptModifierFloorAsmbly,
            self::OptModifierPreAssembly,
            self::OptModifierInfectDeath,
            self::OptModifierWoundTerrorPenalty,
            self::OptModifierAttackProtect,
            self::OptModifierAttackAp,
            self::OptModifierAttackChance,
            self::OptModifierCarryExtraBag,
            self::OptModifierBonesInTown,
            self::OptModifierBuildingDamage,
            self::OptModifierDoDestroy,
            self::OptModifierDoDestroyRatio,
            self::OptModifierDoDestroyMax,
            self::OptModifierCampingBonus,
            self::OptModifierCampingChanceMap,
            self::OptModifierRedSoulFactor,
            self::OptModifierSandballNastyness,
            self::OptModifierWindDistance,
            self::OptModifierStrictPictos,
            self::OptModifierRespawnFactor,
            self::OptModifierRespawnThreshold,
            self::OptModifierAutoghoulFrom,
            self::OptModifierAutoghoulAdvance,
            self::OptModifierAutoghoulMax,
            self::OptModifierDaytimeRange,
            self::OptModifierDaytimeInvert,
            self::OptModifierHideHomeUpgrade,
            self::OptModifierRecyclingAp,
            self::OptModifierRecyclingReturn,
            self::OptModifierGenerosityGhoul,
            self::OptModifierGenerosityLast,
            self::OptModifierGuardtowerMax,
            self::OptModifierGuardtowerUnit,
            self::OptModifierStrangeSoil,
            self::OptModifierSoulGenerationCoef,
            self::OptModifierInstantPictos,
            self::OptModifierEstimInitialShift,
            self::OptModifierEstimSpread,
            self::OptModifierEstimVariance,
            self::OptModifierEstimOffsetMin,
            self::OptModifierEstimOffsetMax,
            self::OptModifierScavengingPlanLimitB,
            self::OptModifierBankAbuseLimit,
            self::OptModifierBankAbuseLimitChaos,
            self::OptModifierBankAbuseBase,
            self::OptModifierBankAbuseLock,
            self::OptModifierOverrideItemGroup,
            self::OptModifierOverrideNamedDrops,
            self::OptModifierOverrideBuildingRarity,
            self::OptModifierBuildingDifficulty
                => self::Section_Opts_Modifiers,

            default => null
        };
    }

    public function children(): array
    {
        return array_filter(self::cases(), fn(self $setting) => $setting->parent() === $this);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function key(): string {
        return $this->value;
    }

    public function default(): null|bool|int|float|string|array
    {
        /** @noinspection PhpDuplicateMatchArmBodyInspection */
        return match ($this) {
            self::AllowLocalConfiguration               => false,
            self::CreateQAPost                          => true,
            self::CancelTownAfterDaysWithoutFilling     => -1,
            self::DoNotCancelAfterCitizensReached       => 40,
            self::SpawnStrangerAfterUnfilledDays        => -1,
            self::SpawnStrangerAfterCitizenCount        =>  0,
            self::LockDoorUntilTownIsFull               =>  false,
            self::PopulationMin                         =>  0,
            self::PopulationMax                         =>  0,
            self::SkillMode                             =>  false,
            self::DisabledJobs                          =>  ['shaman'],
            self::DisabledRoles                         =>  [],
            self::DisabledBuildings                     =>  [],

            self::DefaultWellFillMin => 0,
            self::DefaultWellFillMax => 0,

            self::MapSizeMin            => 0,
            self::MapSizeMax            => 0,
            self::MapSafeMargin         => 0.25,
            self::MapUseCustomMargin    => false,
            self::MapCustomMarginNorth  => 0,
            self::MapCustomMarginSouth  => 0,
            self::MapCustomMarginWest   => 0,
            self::MapCustomMarginEast   => 0,

            self::MapZoneDropCountInitializer => 5,
            self::MapZoneDropCountThreshold   => 10,
            self::MapZoneDropCountRefresh     => 5,
            self::MapRuinCount               => 0,
            self::MapExplorableRuinCount     => 0,
            self::MapRuinItemsMin            => 8,
            self::MapRuinItemsMax            => 8,

            self::MapParamsFreeSpawnCount       => 3,
            self::MapParamsFreeSpawnDist        => 0,
            self::MapParamsBuriedProb           => 0.5,
            self::MapParamsBuriedDigsMin        => 1,
            self::MapParamsBuriedDigsMax        => 19,
            self::MapParamsDigChancesBase       => 0.60,
            self::MapParamsDigChancesDepleted   => 0.35,

            self::ERuinItemFillrate => 7,
            self::ERuinMaxDistanceFromTown => 10,

            self::ERuinRoomLockDistance     => 10,
            self::ERuinRoomDistance         => 5,
            self::ERuinRoomSpacing          => 4,
            self::ERuinRoomCountTotal       => 15,
            self::ERuinRoomCountMinPerFloor => 10,

            self::ERuinSpaceFloors    => 2,
            self::ERuinSpaceMaxSizeX  => 13,
            self::ERuinSpaceMaxSizeY  => 13,
            self::ERuinSpaceOffsetX   => 7,

            self::ERuinBPUnusual,
            self::ERuinBPRare,
            self::ERuinBPEpic => -1,

            self::ERuinZombiesInitial => 10,
            self::ERuinZombiesDaily   => 5,

            self::TimingDiggingDefault => '+2hour',
            self::TimingDiggingCollector => '+1hour30min',

            self::TimingExplorationDefault => '+5min',
            self::TimingExplorationCollector => '+7min30sec',

            self::PictoClassicCullMode => true,

            self::TownInitialBuildingsConstructed => [],
            self::TownInitialBuildingsUnlocked => [],
            self::TownInitialDistributesItems => [],
            self::TownInitialDistributionDistance => [],
            self::TownInitialChestItems => [],

            self::OptFeatureCamping => true,
            self::OptFeatureNightmode => true,
            self::OptFeatureShamanMode => 'normal',
            self::OptFeatureWordsOfHeros => true,
            self::OptFeatureEscort => true,
            self::OptFeatureEscortSize => 4,
            self::OptFeatureXml => true,
            self::OptFeatureCitizenAlias => false,
            self::OptFeatureGhoulMode => 'normal',
            self::OptFeatureGhoulsHungry => false,
            self::OptFeatureAllPoison => false,
            self::OptFeatureShun => true,
            self::OptFeatureNightwatch => true,
            self::OptFeatureNightwatchInstant => false,
            self::OptFeatureAttacks => 'normal',
            self::OptFeatureGiveAllPictos => true,
            self::OptFeaturePictos => true,
            self::OptFeatureGiveSoulpoints => true,
            self::OptFeatureLastDeath => ['r_surlst_#00'],
            self::OptFeatureLastDeathDay => 5,
            self::OptFeatureSurvivalPicto => null,
            self::OptFeatureNoSpRequired => false,
            self::OptFeatureNoTeams => false,
            self::OptFeatureGuideEnabled => false,
            self::OptFeatureGuideSpLimit => 100,
            self::OptFeatureGuideCtcLimit => 0.5,
            self::OptFeatureBlueprintMode => 'unlock',

            self::OptModifierComplaintsShun => 8,
            self::OptModifierComplaintsKill => 6,
            self::OptModifierPoisonStack => false,
            self::OptModifierPoisonTrans => false,
            self::OptModifierWtThreshold => 33,
            self::OptModifierWtOffset => 0,
            self::OptModifierAllowRedigs => false,
            self::OptModifierFloorAsmbly => false,
            self::OptModifierPreAssembly => false,
            self::OptModifierInfectDeath => 0.5,
            self::OptModifierWoundTerrorPenalty => 0.05,
            self::OptModifierAttackProtect => false,
            self::OptModifierAttackAp => 5,
            self::OptModifierAttackChance => 0.5,
            self::OptModifierCarryExtraBag => false,
            self::OptModifierBonesInTown => false,
            self::OptModifierBuildingDamage => false,
            self::OptModifierDoDestroy => false,
            self::OptModifierDoDestroyRatio => 50,
            self::OptModifierDoDestroyMax => 20,
            self::OptModifierCampingBonus => 0,
            self::OptModifierCampingChanceMap => [],
            self::OptModifierRedSoulFactor => 1.2,
            self::OptModifierSandballNastyness => 0,
            self::OptModifierWindDistance => 2,
            self::OptModifierStrictPictos => false,
            self::OptModifierRespawnFactor => 0.5,
            self::OptModifierRespawnThreshold => 50,
            self::OptModifierAutoghoulFrom => 5,
            self::OptModifierAutoghoulAdvance => 0.1,
            self::OptModifierAutoghoulMax => 0.9,
            self::OptModifierDaytimeRange => [7,18],
            self::OptModifierDaytimeInvert => false,
            self::OptModifierHideHomeUpgrade => false,
            self::OptModifierRecyclingAp => 15,
            self::OptModifierRecyclingReturn => 5,
            self::OptModifierGenerosityGhoul => 1,
            self::OptModifierGenerosityLast => 1,
            self::OptModifierGuardtowerMax => 150,
            self::OptModifierGuardtowerUnit => 10,
            self::OptModifierStrangeSoil => false,
            self::OptModifierSoulGenerationCoef => 1.0,
            self::OptModifierInstantPictos => [],
            self::OptModifierEstimInitialShift => 0,
            self::OptModifierEstimSpread => 10,
            self::OptModifierEstimVariance => 48,
            self::OptModifierEstimOffsetMin => 15,
            self::OptModifierEstimOffsetMax => 36,
            self::OptModifierScavengingPlanLimitB => -1,
            self::OptModifierBankAbuseLimit => 5,
            self::OptModifierBankAbuseLimitChaos => 10,
            self::OptModifierBankAbuseBase => 5,
            self::OptModifierBankAbuseLock => 15,
            self::OptModifierOverrideItemGroup => [],
            self::OptModifierOverrideNamedDrops => [],
            self::OptModifierOverrideBuildingRarity => [],
            self::OptModifierBuildingDifficulty => 0,

            default => null,
        };
    }

    public function fallback(): array
    {
        return [];
    }

    /**
     * @return TownSetting[]
     */
    public static function validCases(): array
    {
        return array_filter(self::cases(), fn(TownSetting $s) => !$s->abstract());
    }

    public function merge(mixed $old, mixed $new): mixed
    {
        return $new;
    }

    public function translationKey(): string
    {
        return "cfg_ts_" . str_replace(".", "_", $this->value);
    }
}