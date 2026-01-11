<?php

namespace MyHordes\Fixtures\Data;

use App\Entity\ActionCounter;
use App\Entity\Requirement;
use App\Enum\ActionCounterType;
use App\Enum\ActionHandler\PointType;
use App\Enum\Configuration\CitizenProperties;
use App\Enum\Configuration\TownSetting;
use App\Service\Actions\Game\AtomProcessors\Require\Custom\GuardTowerUseIsNotMaxed;
use App\Service\Actions\Game\AtomProcessors\Require\Custom\RoleVote;
use App\Structures\TownConf;
use ArrayHelpers\Arr;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\BuildingRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\ConfigRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\CounterRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\CustomClassRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\EscortRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\FeatureRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\HomeRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\InventorySpaceRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\ItemRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\LocationRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\PointRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\ProfessionRoleRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\SourceRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\StatusRequirement;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\TimeRequirement;
use MyHordes\Fixtures\DTO\Actions\RequirementsDataContainer;

class ActionCatapultProvider
{
    private static function write(array &$data, string $effect, array $entries): void {
        foreach ($entries as $item) {
            $prev = Arr::get($data, $item);
            if ($prev !== null) throw new \Exception("Cannot set effect $effect: Item $item already has an effect ($prev) set");

            Arr::set($data, $item, $effect);
        }
    }

    public static function create(): array {
        $data = [];

        self::write( $data, 'cata_rsc_fine', [
            'april_drug_#00',
            'bandage_#00',
            'beta_drug_#00',
            'beta_drug_bad_#00',
            'big_pgun_#00',
            'big_pgun_empty_#00',
            'big_pgun_part_#00',
            'bike_#00',
            'bquies_#00',
            'bretz_#00',
            'cadaver_remains_#00',
            'can_#00',
            'can_open_#00',
            'cards_#00',
            'carpet_#00',
            'car_door_#00',
            'car_door_part_#00',
            'chainsaw_empty_#00',
            'chainsaw_part_#00',
            'chama_#00',
            'chama_tasty_#00',
            'christmas_suit_full_#00',
            'christmas_suit_1_#00',
            'christmas_suit_2_#00',
            'christmas_suit_3_#00',
            'chudol_#00',
            'cigs_#00',
            'coffee_machine_part_#00',
            'courroie_#00',
            'cyanure_#00',
            'deto_#00',
            'dice_#00',
            'digger_#00',
            'diode_#00',
            'disinfect_#00',
            'door_carpet_#00',
            'drug_#00',
            'drug_hero_#00',
            'drug_random_#00',
            'drug_water_#00',
            'engine_part_#00',
            'electro_#00',
            'explo_#00',
            'flesh_part_#00',
            'food_bag_#00',
            'food_bar1_#00',
            'food_bar2_#00',
            'food_bar3_#00',
            'food_biscuit_#00',
            'food_candies_#00',
            'food_noodles_#00',
            'food_pims_#00',
            'fruit_part_#00',
            'fungus_#00',
            'game_box_#00',
            'gun_#00',
            'infect_poison_part_#00',
            'jerrycan_#00',
            'jerrygun_#00',
            'jerrygun_part_#00',
            'jerrygun_off_#00',
            'kalach_#00',
            'lawn_part_#00',
            'lights_#00',
            'lilboo_#00',
            'lock_#00',
            'lpoint_#00',
            'lpoint1_#00',
            'lpoint2_#00',
            'lpoint3_#00',
            'lpoint4_#00',
            'lsd_#00',
            'machine_gun_#00',
            'magneticKey_#00',
            'bumpKey_#00',
            'classicKey_#00',
            'keymol_#00',
            'meca_parts_#00',
            'medic_#00',
            'metal_#00',
            'metal_bad_#00',
            'mixergun_empty_#00',
            'mixergun_part_#00',
            'moldy_food_#00',
            'moldy_food_part_#00',
            'noodle_prints_#00',
            'noodle_prints_#01',
            'noodle_prints_#02',
            'oilcan_#00',
            'out_def_#00',
            'pharma_#00',
            'pharma_part_#00',
            'pilegun_#00',
            'pilegun_empty_#00',
            'pilegun_upkit_#00',
            'pilegun_up_#00',
            'pilegun_up_empty_#00',
            'pile_#00',
            'pile_broken_#00',
            'potion_#00',
            'prints_#00',
            'prints_#01',
            'prints_#02',
            'radius_mk2_#00',
            'radius_mk2_part_#00',
            'repair_kit_#00',
            'repair_kit_part_#00',
            'repair_one_#00',
            'rustine_#00',
            'ryebag_#00',
            'saw_tool_#00',
            'saw_tool_part_#00',
            'saw_tool_temp_#00',
            'sheet_#00',
            'smelly_meat_#00',
            'smoke_bomb_#00',
            'soccer_#00',
            'sport_elec_#00',
            'sport_elec_empty_#00',
            'staff_#01',
            'staff2_#00',
            'tagger_#00',
            'maglite_1_#00',
            'maglite_2_#00',
            'maglite_off_#00',
            'tube_#00',
            'vagoul_#00',
            'vibr_#00',
            'vibr_empty_#00',
            'watergun_1_#00',
            'watergun_2_#00',
            'watergun_3_#00',
            'watergun_empty_#00',
            'watergun_opt_1_#00',
            'watergun_opt_2_#00',
            'watergun_opt_3_#00',
            'watergun_opt_4_#00',
            'watergun_opt_5_#00',
            'watergun_opt_empty_#00',
            'watergun_opt_part_#00',
            'water_#00',
            'water_can_1_#00',
            'water_can_2_#00',
            'water_can_3_#00',
            'water_can_empty_#00',
            'water_cleaner_#00',
            'wire_#00',
            'wood2_#00',
            'wood_bad_#00',
            'xanax_#00',
        ] );
        self::write( $data, 'cata_rsc_break', ['staff_#00']);
        self::write( $data, 'cata_rsc_destroy', ['egg_#00']);
        self::write( $data, 'cata_rsc_scrap', [
            'coffee_machine_#00',
            'mecanism_#00',
            'radio_off_#00',
            'scope_#00',
        ]);
        self::write( $data, 'cata_rsc_remains', [
            'chest_food_#00',
            'coffee_#00',
            'fest_#00',
            'guiness_#00',
            'hmbrew_#00',
            'lamp_#00',
            'lamp_on_#00',
            'lens_#00',
            'omg_this_will_kill_you_#00',
            'poison_#00',
            'poison_part_#00',
            'quantum_#00',
            'radio_on_#00',
            'rhum_#00',
            'vodka_#00',
            'vodka_de_#00',
        ]);
        self::write( $data, 'cata_rsc_moldy', [
            'apple_#00',
            'apple_blue_#00',
            'bone_meat_#00',
            'cadaver_#00',
            'dish_#00',
            'dish_tasty_#00',
            'food_chick_#00',
            'food_noodles_hot_#00',
            'food_sandw_#00',
            'food_tarte_#00',
            'fruit_#00',
            'hmeat_#00',
            'meat_#00',
            'moldy_food_spicy_#00',
            'undef_#00',
            'vegetable_#00',
            'vegetable_tasty_#00',
            'woodsteak_#00',
        ]);

        self::write( $data, 'cata_wpn_scrap_1_rid', [
            'catbox_#00',
            'chest_#00',
            'electro_box_#00',
            'iphone_#00',
            'safe_#00',
        ]);
        self::write( $data, 'cata_wpn_remains_1_rid', [
            'dfhifi_#01',
            'hifiev_#00',
            'metal_beam_#00',
            'rlaunc_#00',
            'taser_#00',
            'torch_#00',
            'wood_beam_#00',
        ]);
        self::write( $data, 'cata_wpn_destroy_1_rid', [
            'chair_#00',
            'chair_basic_#00',
            'concrete_#00',
            'dfhifi_#00',
            'home_def_#00',
            'music_#00',
            'paques_#00',
            'pet_chick_#00',
            'pet_rat_#00',
            'sand_ball_#00',
        ]);
        self::write( $data, 'cata_wpn_break_1_rid', [
            'bone_#00',
            'can_opener_#00',
            'cutter_#00',
            'hurling_stick_#00',
            'knife_#00',
            'screw_#00',
            'small_knife_#00',
            'swiss_knife_#00',
            'torch_off_#00',
            'wrench_#00',
        ]);

        self::write( $data, 'cata_wpn_remains_1_low', ['home_box_xl_#00']);
        self::write( $data, 'cata_wpn_destroy_1_low', [
            'mixergun_#00',
            'pet_pig_#00',
            'pet_snake2_#00',
            'pet_snake_#00',
            'renne_#00',
            'wood_log_#00',
        ]);
        self::write( $data, 'cata_wpn_break_1_low', ['cutcut_#00']);

        self::write( $data, 'cata_wpn_destroy_1_high', [
            'angryc_#00',
            'pet_cat_#00',
            'pet_dog_#00',
            'tekel_#00',
        ]);

        self::write( $data, 'cata_wpn_destroy_1_imp', ['trapma_#00']);
        self::write( $data, 'cata_wpn_break_1_imp', ['lawn_#00']);

        self::write( $data, 'cata_wpn_scrap_c_low', [
            'cinema_#00',
            'engine_#00',
        ]);
        self::write( $data, 'cata_wpn_remains_c_low', [
            'bed_#00',
            'bureau_#00',
            'door_#00',
            'plate_#00',
            'table_#00',
            'trestle_#00',
            'wood_plate_#00',
        ]);
        self::write( $data, 'cata_wpn_destroy_c_low', [
            'grenade_#00',
            'pumpkin_tasty_#00',
            'wood_xmas_#00',
        ]);
        self::write( $data, 'cata_wpn_break_c_low', [
            'machine_2_#00',
            'machine_3_#00',
            'pc_#00',
        ]);

        self::write( $data, 'cata_wpn_destroy_c_high', ['distri_#00']);
        self::write( $data, 'cata_wpn_break_c_high', ['machine_1_#00']);

        self::write( $data, 'cata_wpn_scrap_c_imp', ['chainsaw_#00']);

        self::write( $data, 'cata_wpn_destroy_c_ctrl', [
            'flash_#00',
            'flesh_#00',
        ]);

        self::write( $data, 'cata_wpn_destroy_s_low', ['plate_raw_#00']);

        self::write( $data, 'cata_wpn_destroy_s_high', [
            'bgrenade_#00',
            'boomfruit_#00',
        ]);

        self::write( $data, 'cata_wpn_remains_s_imp', ['claymo_#00']);
        self::write( $data, 'cata_wpn_destroy_s_imp', ['concrete_wall_#00']);

        foreach ( $data as $key => $value )
            if ($value === null) throw new \Exception("No effect set for item $key");

        return $data;
    }
}
