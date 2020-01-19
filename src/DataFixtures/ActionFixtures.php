<?php

namespace App\DataFixtures;

use App\Entity\AffectAP;
use App\Entity\AffectItemConsume;
use App\Entity\AffectItemSpawn;
use App\Entity\AffectOriginalItem;
use App\Entity\AffectResultGroup;
use App\Entity\AffectResultGroupEntry;
use App\Entity\AffectStatus;
use App\Entity\AffectZombies;
use App\Entity\CitizenStatus;
use App\Entity\ItemAction;
use App\Entity\ItemGroup;
use App\Entity\ItemGroupEntry;
use App\Entity\ItemProperty;
use App\Entity\ItemPrototype;
use App\Entity\RequireItem;
use App\Entity\RequireLocation;
use App\Entity\Requirement;
use App\Entity\RequireStatus;
use App\Entity\RequireZombiePresence;
use App\Entity\Result;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class ActionFixtures extends Fixture implements DependentFixtureInterface
{
    public static $item_actions = [
        'meta_requirements' => [
            'drink_ap_1'  => [ 'type' => Requirement::CrossOnFail, 'collection' => [ 'status' => 'has_not_drunken' ]],
            'drink_ap_2'  => [ 'type' => Requirement::HideOnFail,  'collection' => [ 'status' => 'not_dehydrated' ]],
            'drink_no_ap' => [ 'type' => Requirement::HideOnFail,  'collection' => [ 'status' => 'dehydrated' ]],

            'eat_ap'      => [ 'type' => Requirement::CrossOnFail, 'collection' => [ 'status' => 'has_not_eaten' ]],

            'drug_1'  => [ 'type' => Requirement::HideOnFail, 'collection' => [ 'status' => 'not_drugged' ]],
            'drug_2'  => [ 'type' => Requirement::HideOnFail, 'collection' => [ 'status' => 'drugged' ]],

            'not_tired' => [ 'type' => Requirement::CrossOnFail, 'collection' => [ 'status' => 'not_tired' ]],

            'have_can_opener' => [ 'type' => Requirement::MessageOnFail, 'collection' => [ 'item' => 'have_can_opener' ],  'text' => 'Du brauchst ein Werkzeug, um diesen Gegenstand zu öffnen...' ],
            'have_box_opener' => [ 'type' => Requirement::MessageOnFail, 'collection' => [ 'item' => 'have_box_opener' ],  'text' => 'Du brauchst ein Werkzeug, um diesen Gegenstand zu öffnen...' ],
            'have_water'      => [ 'type' => Requirement::MessageOnFail, 'collection' => [ 'item' => 'have_water' ],       'text' => 'Hierfür brauchst du eine Ration Wasser.' ],
            'have_battery'    => [ 'type' => Requirement::MessageOnFail, 'collection' => [ 'item' => 'have_battery' ],     'text' => 'Hierfür brauchst du eine Batterie.' ],

            'must_be_outside' => [ 'type' => Requirement::HideOnFail,  'collection' => [ 'location' => 'must_be_outside' ]],
            'must_be_inside' =>  [ 'type' => Requirement::HideOnFail,  'collection' => [ 'location' => 'must_be_inside' ]],

            'must_have_zombies' => [ 'type' => Requirement::MessageOnFail, 'collection' => [ 'zombies' => 'must_have_zombies' ], 'text' => 'Zum Glück sind hier keine Zombies...'],
        ],

        'requirements' => [
            'status' => [
                'has_not_drunken' => [ 'enabled' => false, 'status' => 'hasdrunk' ],
                'has_not_eaten'   => [ 'enabled' => false, 'status' => 'haseaten' ],

                'not_dehydrated'  => [ 'enabled' => false, 'status' => 'thirst2' ],
                'dehydrated'      => [ 'enabled' => true,  'status' => 'thirst2' ],

                'not_drugged'  => [ 'enabled' => false, 'status' => 'drugged' ],
                'drugged'      => [ 'enabled' => true,  'status' => 'drugged' ],

                'not_tired'    => [ 'enabled' => false, 'status' => 'tired' ],

            ],
            'item' => [
                'have_can_opener' => [ 'item' => null, 'prop' => 'can_opener' ],
                'have_box_opener' => [ 'item' => null, 'prop' => 'box_opener' ],
                'have_water'   => [ 'item' => 'water_#00', 'prop' => null ],
                'have_battery' => [ 'item' => 'pile_#00',  'prop' => null ],
            ],
            'location' => [
                'must_be_outside' => [ RequireLocation::LocationOutside ],
                'must_be_inside'  => [ RequireLocation::LocationInTown ],
            ],
            'zombies' => [
                'must_have_zombies' => [ 'min' => 1, 'block' => false ]
            ]
        ],

        'meta_results' => [
            'do_nothing' => [],

            'consume_item'   => [ 'item' => 'consume' ],
            'consume_water'  => [ 'consume' => 'water'   ],
            'consume_battery'=> [ 'consume' => 'battery' ],
            'break_item'     => [ 'item' => 'consume' ],

            'drink_ap_1'  => [ 'status' => 'add_has_drunk', 'ap' => 'to_max_plus_0' ],
            'drink_ap_2'  => [ 'status' => 'remove_thirst' ],
            'drink_no_ap' => [ 'status' => 'replace_dehydration' ],

            'eat_ap6'     => [ 'status' => 'add_has_eaten', 'ap' => 'to_max_plus_0' ],
            'eat_ap7'     => [ 'status' => 'add_has_eaten', 'ap' => 'to_max_plus_1' ],

            'drug_any_1'   => [ 'status' => 'remove_clean' ],
            'drug_any_2'   => [ 'status' => 'add_is_drugged' ],
            'drug_addict'  => [ 'status' => 'add_addicted' ],
            'terrorize'    => [ 'status' => 'add_terror' ],

            'disinfect'    => [ 'status' => 'remove_infection' ],

            'just_ap6'     => [ 'ap' => 'to_max_plus_0' ],
            'just_ap7'     => [ 'ap' => 'to_max_plus_1' ],
            'just_ap8'     => [ 'ap' => 'to_max_plus_2' ],

            'produce_watercan3' => [ 'item' => [ 'consume' => false, 'morph' => 'water_can_3_#00' ] ],
            'produce_watercan2' => [ 'item' => [ 'consume' => false, 'morph' => 'water_can_2_#00' ] ],
            'produce_watercan1' => [ 'item' => [ 'consume' => false, 'morph' => 'water_can_1_#00' ] ],
            'produce_watercan0' => [ 'item' => [ 'consume' => false, 'morph' => 'water_can_empty_#00' ] ],

            'kill_1_zombie' => [ 'zombies' => 'kill_1z' ],
            'kill_2_zombie' => [ 'zombies' => 'kill_2z' ],
        ],

        'results' => [
            'ap' => [
                'to_max_plus_0' => [ 'max' => true, 'num' => 0 ],
                'to_max_plus_1' => [ 'max' => true, 'num' => 1 ],
                'to_max_plus_2' => [ 'max' => true, 'num' => 2 ],
                'to_max_plus_3' => [ 'max' => true, 'num' => 3 ],
            ],
            'status' => [
                'replace_dehydration' => [ 'from' => 'thirst2', 'to' => 'thirst1' ],
                'add_has_drunk' => [ 'from' => null, 'to' => 'hasdrunk' ],
                'remove_thirst' => [ 'from' => 'thirst1', 'to' => null ],

                'remove_clean'    => [ 'from' => 'clean', 'to' => null ],
                'remove_infection'=> [ 'from' => 'infection', 'to' => null ],

                'add_has_eaten'  => [ 'from' => null, 'to' => 'haseaten' ],
                'add_is_drugged' => [ 'from' => null, 'to' => 'drugged' ],
                'add_addicted'   => [ 'from' => null, 'to' => 'addict' ],
                'add_terror'     => [ 'from' => null, 'to' => 'terror' ],

            ],
            'item' => [
                'consume' => [ 'consume' => true,  'morph' => null, 'break' => null, 'poison' => null ],
                'break'   => [ 'consume' => false, 'morph' => null, 'break' => true, 'poison' => null ],
            ],

            'spawn' => [
                'xmas'   => [ ['omg_this_will_kill_you_#00', 8], ['pocket_belt_#00', 8], 'rp_scroll_#00', 'rp_manual_#00', 'rp_sheets_#00', 'rp_letter_#00', 'rp_scroll_#00', 'rp_book_#00', 'rp_book_#01', 'rp_book2_#00' ],
                'matbox' => [ 'wood2_#00', 'metal_#00' ],
                'empty_battery' => [ 'pile_broken_#00' ],
            ],

            'consume' => [
                'water'   => [ 'water_#00' ],
                'battery' => [ 'pile_#00' ],
            ],

            'group' => [
                'g_break_20' => [[['do_nothing'], 80], [['break_item'], 20]],
                'g_break_25' => [[['do_nothing'], 75], [['break_item'], 25]],
                'g_break_30' => [[['do_nothing'], 70], [['break_item'], 30]],
                'g_break_33' => [[['do_nothing'], 67], [['break_item'], 33]],
                'g_break_40' => [[['do_nothing'], 60], [['break_item'], 40]],
                'g_break_50' => [[['do_nothing'], 50], [['break_item'], 50]],
                'g_break_60' => [[['do_nothing'], 40], [['break_item'], 60]],
                'g_break_66' => [[['do_nothing'], 44], [['break_item'], 66]],
                'g_break_80' => [[['do_nothing'], 20], [['break_item'], 80]],

                'g_kill_1z_10' => [[['do_nothing'], 90], [['break_item'], 10]],
                'g_kill_1z_20' => [[['do_nothing'], 80], [['break_item'], 20]],
                'g_kill_1z_33' => [[['do_nothing'], 67], [['break_item'], 33]],
                'g_kill_1z_50' => [[['do_nothing'], 50], [['break_item'], 50]],
                'g_kill_1z_85' => [[['do_nothing'], 15], [['break_item'], 85]],

            ],

            'zombies' => [
                'kill_maybe_1z' => [ 'min' => 0, 'max' => 1 ],
                'kill_1z' => [ 'num' => 1 ],
                'kill_2z' => [ 'num' => 2 ],
                'kill_3z' => [ 'num' => 3 ],
            ]
        ],

        'actions' => [
            'water_6ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_ap_1', 'drink_ap_2' ], 'result' => [ 'drink_ap_1', 'drink_ap_2', 'consume_item' ] ],
            'water_0ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_no_ap' ], 'result' => [ 'drink_no_ap', 'consume_item' ] ],

            'watercan3_6ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_ap_1', 'drink_ap_2' ], 'result' => [ 'drink_ap_1', 'drink_ap_2', 'produce_watercan2' ] ],
            'watercan3_0ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_no_ap' ], 'result' => [ 'drink_no_ap', 'produce_watercan2' ] ],
            'watercan2_6ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_ap_1', 'drink_ap_2' ], 'result' => [ 'drink_ap_1', 'drink_ap_2', 'produce_watercan1' ] ],
            'watercan2_0ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_no_ap' ], 'result' => [ 'drink_no_ap', 'produce_watercan1' ] ],
            'watercan1_6ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_ap_1', 'drink_ap_2' ], 'result' => [ 'drink_ap_1', 'drink_ap_2', 'produce_watercan0' ] ],
            'watercan1_0ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_no_ap' ], 'result' => [ 'drink_no_ap', 'produce_watercan0' ] ],

            'can'       => [ 'label' => 'Öffnen',  'meta' => [ 'have_can_opener' ], 'result' => [ [ 'item' => [ 'consume' => false, 'morph' => 'can_open_#00' ] ] ] ],

            'eat_6ap'   => [ 'label' => 'Essen',   'meta' => [ 'eat_ap' ], 'result' => [ 'eat_ap6', 'consume_item' ] ],
            'eat_7ap'   => [ 'label' => 'Essen',   'meta' => [ 'eat_ap' ], 'result' => [ 'eat_ap7', 'consume_item' ] ],

            'drug_par_1'   => [ 'label' => 'Einnehmen', 'meta' => [ 'drug_1' ], 'result' => [ 'drug_any_1', 'drug_any_2', 'disinfect', 'consume_item' ] ],
            'drug_par_2'   => [ 'label' => 'Einnehmen', 'meta' => [ 'drug_2' ], 'result' => [ 'drug_addict', 'disinfect', 'consume_item' ] ],
            'drug_6ap_1'   => [ 'label' => 'Einnehmen', 'meta' => [ 'drug_1' ], 'result' => [ 'drug_any_1', 'drug_any_2', 'just_ap6', 'consume_item' ] ],
            'drug_6ap_2'   => [ 'label' => 'Einnehmen', 'meta' => [ 'drug_2' ], 'result' => [ 'drug_addict', 'just_ap6', 'consume_item' ] ],
            'drug_7ap_1'   => [ 'label' => 'Einnehmen', 'meta' => [ 'drug_1' ], 'result' => [ 'drug_any_1', 'drug_any_2', 'just_ap7', 'consume_item' ] ],
            'drug_7ap_2'   => [ 'label' => 'Einnehmen', 'meta' => [ 'drug_2' ], 'result' => [ 'drug_addict', 'just_ap7', 'consume_item' ] ],
            'drug_8ap_1'   => [ 'label' => 'Einnehmen', 'meta' => [ 'drug_1' ], 'result' => [ 'drug_any_1', 'drug_any_2', 'just_ap8', 'consume_item' ] ],
            'drug_8ap_2'   => [ 'label' => 'Einnehmen', 'meta' => [ 'drug_2' ], 'result' => [ 'drug_addict', 'just_ap8', 'consume_item' ] ],

            'drug_rand_1'  => [ 'label' => 'Einnehmen', 'meta' => [ 'drug_1' ], 'result' => [ 'consume_item', ['group' => [
                [ ['drug_any_1', 'drug_any_2', 'just_ap6'], 5 ],
                [ ['drug_any_1', 'drug_any_2', 'terrorize'], 2 ],
                [ ['drug_any_1', 'drug_any_2', 'drug_addict', 'just_ap7'], 2 ],
                [ ['do_nothing'], 1 ],
            ]] ] ] ,
            'drug_rand_2'  => [ 'label' => 'Einnehmen', 'meta' => [ 'drug_2' ], 'result' => [ 'consume_item', ['group' => [
                [ ['drug_addict', 'just_ap6'], 5 ],
                [ ['drug_addict', 'terrorize'], 2 ],
                [ ['drug_addict', 'just_ap7'], 2 ],
                [ ['do_nothing'], 1 ],
            ]] ] ] ,

            'open_doggybag' => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ 'consume_item', [ 'spawn' => [ 'food_pims_#00', 'food_tarte_#00', 'food_chick_#00', 'food_biscuit_#00', 'food_bar3_#00', 'food_bar1_#00', 'food_sandw_#00', 'food_bar2_#00' ] ] ], 'message' => 'Du hast dein {item} ausgepackt und {items_spawn} erhalten!' ],
            'open_lunchbag' => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ 'consume_item', [ 'spawn' => [ 'food_candies_#00', 'food_noodles_hot_#00', 'vegetable_tasty_#00', 'meat_#00' ] ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],
            'open_c_chest'  => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ 'consume_item', [ 'spawn' => [ 'pile_#00', 'radio_off_#00', 'pharma_#00', 'lights_#00' ] ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],
            'open_h_chest'  => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ 'consume_item', [ 'spawn' => [ 'watergun_empty_#00', 'pilegun_empty_#00', 'flash_#00', 'repair_one_#00', 'smoke_bomb_#00' ] ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],
            'open_postbox'  => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ 'consume_item', [ 'spawn' => [ 'money_#00', 'rp_book_#00', 'rp_book_#01', 'rp_sheets_#00' ] ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],

            'open_gamebox'  => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ 'consume_item', [ 'spawn' => [ 'dice_#00', 'cards_#00' ] ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],
            'open_abox'     => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ 'consume_item', [ 'spawn' => [ 'bplan_r_#00' ] ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],
            'open_cbox'     => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ 'consume_item', [ 'spawn' => [ 'bplan_c_#00', 'bplan_u_#00', 'bplan_r_#00', 'bplan_e_#00' ] ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],

            'open_matbox3'   => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ [ 'item' => [ 'consume' => false, 'morph' => 'rsc_pack_2_#00' ],  'spawn' => 'matbox' ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],
            'open_matbox2'   => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ [ 'item' => [ 'consume' => false, 'morph' => 'rsc_pack_1_#00' ],  'spawn' => 'matbox' ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],
            'open_matbox1'   => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ 'consume_item', [ 'spawn' => 'matbox' ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],

            'open_xmasbox3'  => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ [ 'item' => [ 'consume' => false, 'morph' => 'chest_christmas_2_#00' ],  'spawn' => 'xmas' ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],
            'open_xmasbox2'  => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ [ 'item' => [ 'consume' => false, 'morph' => 'chest_christmas_1_#00' ],  'spawn' => 'xmas' ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],
            'open_xmasbox1'  => [ 'label' => 'Öffnen', 'meta' => [], 'result' => [ 'consume_item', [ 'spawn' => 'xmas' ] ] ],

            'open_metalbox'  => [ 'label' => 'Öffnen', 'meta' => [ 'have_can_opener' ], 'result' => [ 'consume_item', [ 'spawn' => [ 'drug_#00', 'bandage_#00', 'pile_#00', 'pilegun_empty_#00', 'vodka_de_#00', 'pharma_#00', 'explo_#00', 'lights_#00', 'drug_hero_#00', 'rhum_#00' ] ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],
            'open_metalbox2' => [ 'label' => 'Öffnen', 'meta' => [ 'have_can_opener' ], 'result' => [ 'consume_item', [ 'spawn' => [ 'watergun_opt_part_#00', 'pilegun_upkit_#00', 'pocket_belt_#00', 'cutcut_#00', 'chainsaw_part_#00', 'mixergun_part_#00', 'big_pgun_part_#00', 'lawn_part_#00' ] ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],
            'open_catbox'    => [ 'label' => 'Öffnen', 'meta' => [ 'have_can_opener' ], 'result' => [ 'consume_item', [ 'spawn' => [ 'poison_part_#00', 'pet_cat_#00', 'angryc_#00' ] ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],

            'open_toolbox'    => [ 'label' => 'Öffnen', 'meta' => [ 'have_box_opener' ], 'result' => [ 'consume_item', [ 'spawn' => [ 'pile_#00', 'meca_parts_#00', 'rustine_#00', 'tube_#00', 'pharma_#00', 'explo_#00', 'lights_#00' ] ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],
            'open_foodbox'    => [ 'label' => 'Öffnen', 'meta' => [ 'have_box_opener' ], 'result' => [ 'consume_item', [ 'spawn' => [ 'food_bag_#00', 'can_#00', 'meat_#00', 'hmeat_#00', 'vegetable_#00' ] ] ], 'message' => 'Du hast die {item} geöffnet und darin {items_spawn} gefunden!' ],

            'load_pilegun'   => [ 'label' => 'Laden', 'meta' => [ 'have_battery' ], 'result' => [ 'consume_battery', [ 'item' => [ 'consume' => false, 'morph' => 'pilegun_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} eingelegt und {item_to} erhalten!' ],
            'load_pilegun2'  => [ 'label' => 'Laden', 'meta' => [ 'have_battery' ], 'result' => [ 'consume_battery', [ 'item' => [ 'consume' => false, 'morph' => 'pilegun_up_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} eingelegt und {item_to} erhalten!' ],
            'load_pilegun3'  => [ 'label' => 'Laden', 'meta' => [ 'have_battery' ], 'result' => [ 'consume_battery', [ 'item' => [ 'consume' => false, 'morph' => 'big_pgun_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} eingelegt und {item_to} erhalten!' ],
            'load_mixergun'  => [ 'label' => 'Laden', 'meta' => [ 'have_battery' ], 'result' => [ 'consume_battery', [ 'item' => [ 'consume' => false, 'morph' => 'mixergun_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} eingelegt und {item_to} erhalten!' ],
            'load_chainsaw'  => [ 'label' => 'Laden', 'meta' => [ 'have_battery' ], 'result' => [ 'consume_battery', [ 'item' => [ 'consume' => false, 'morph' => 'chainsaw_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} eingelegt und {item_to} erhalten!' ],
            'load_taser'     => [ 'label' => 'Laden', 'meta' => [ 'have_battery' ], 'result' => [ 'consume_battery', [ 'item' => [ 'consume' => false, 'morph' => 'taser_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} eingelegt und {item_to} erhalten!' ],
            'load_lpointer'  => [ 'label' => 'Laden', 'meta' => [ 'have_battery' ], 'result' => [ 'consume_battery', [ 'item' => [ 'consume' => false, 'morph' => 'lpoint4_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} eingelegt und {item_to} erhalten!' ],

            'load_lamp'      => [ 'label' => 'Laden', 'meta' => [ 'have_battery' ], 'result' => [ 'consume_battery', [ 'item' => [ 'consume' => false, 'morph' => 'lamp_on_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} eingelegt und {item_to} erhalten!' ],
            'load_dildo'     => [ 'label' => 'Laden', 'meta' => [ 'have_battery' ], 'result' => [ 'consume_battery', [ 'item' => [ 'consume' => false, 'morph' => 'vibr_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} eingelegt und {item_to} erhalten!' ],
            'load_rmk2'      => [ 'label' => 'Laden', 'meta' => [ 'have_battery' ], 'result' => [ 'consume_battery', [ 'item' => [ 'consume' => false, 'morph' => 'radius_mk2_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} eingelegt und {item_to} erhalten!' ],
            'load_maglite'   => [ 'label' => 'Laden', 'meta' => [ 'have_battery' ], 'result' => [ 'consume_battery', [ 'item' => [ 'consume' => false, 'morph' => 'maglite_2_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} eingelegt und {item_to} erhalten!' ],

            'fill_asplash'   => [ 'label' => 'Befüllen', 'meta' => [ 'have_water' ], 'result' => [ 'consume_water', [ 'item' => [ 'consume' => false, 'morph' => 'watergun_opt_5_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} gefüllt und {item_to} erhalten!' ],
            'fill_splash'    => [ 'label' => 'Befüllen', 'meta' => [ 'have_water' ], 'result' => [ 'consume_water', [ 'item' => [ 'consume' => false, 'morph' => 'watergun_3_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} gefüllt und {item_to} erhalten!' ],
            'fill_jsplash'   => [ 'label' => 'Befüllen', 'meta' => [ 'have_water' ], 'result' => [ 'consume_water', [ 'item' => [ 'consume' => false, 'morph' => 'jerrygun_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} gefüllt und {item_to} erhalten!' ],
            'fill_ksplash'   => [ 'label' => 'Befüllen', 'meta' => [ 'have_water' ], 'result' => [ 'consume_water', [ 'item' => [ 'consume' => false, 'morph' => 'kalach_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} gefüllt und {item_to} erhalten!' ],
            'fill_grenade'   => [ 'label' => 'Befüllen', 'meta' => [ 'have_water' ], 'result' => [ 'consume_water', [ 'item' => [ 'consume' => false, 'morph' => 'grenade_#00' ] ] ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} gefüllt und {item_to} erhalten!' ],

            'fill_watercan0' => [ 'label' => 'Befüllen', 'meta' => [ 'have_water' ], 'result' => [ 'consume_water', 'produce_watercan1' ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} gefüllt und {item_to} erhalten!' ],
            'fill_watercan1' => [ 'label' => 'Befüllen', 'meta' => [ 'have_water' ], 'result' => [ 'consume_water', 'produce_watercan2' ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} gefüllt und {item_to} erhalten!' ],
            'fill_watercan2' => [ 'label' => 'Befüllen', 'meta' => [ 'have_water' ], 'result' => [ 'consume_water', 'produce_watercan3' ], 'message' => 'Du hast eine {items_consume} in dein/e/n {item_from} gefüllt und {item_to} erhalten!' ],

            'fire_pilegun'   => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'pilegun_empty_#00',    'consume' => false], 'zombies' => 'kill_maybe_1z' ] ] ],
            'fire_pilegun2'  => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'group' => [ [['do_nothing'],  8], [[ ['spawn' => 'empty_battery', 'item' => ['morph' => 'pilegun_up_empty_#00', 'consume' => false]] ], 2] ], 'zombies' => 'kill_1z' ] ] ],
            'fire_pilegun3'  => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'group' => [ [['do_nothing'],  5], [[ ['spawn' => 'empty_battery', 'item' => ['morph' => 'big_pgun_empty_#00',   'consume' => false]] ], 5] ], 'zombies' => 'kill_2z' ] ] ],
            'fire_mixergun'  => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'group' => [ [['do_nothing'],  6], [[ [                            'item' => ['morph' => 'mixergun_empty_#00',   'consume' => false]] ], 4] ], 'zombies' => 'kill_1z' ] ] ],
            'fire_chainsaw'  => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'group' => [ [['do_nothing'],  7], [[ [                            'item' => ['morph' => 'chainsaw_empty_#00',   'consume' => false]] ], 3] ], 'zombies' => 'kill_3z' ] ] ],
            'fire_taser'     => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'group' => [ [['do_nothing'],  2], [[ [                            'item' => ['morph' => 'taser_empty_#00',      'consume' => false]] ], 8] ], 'zombies' => 'kill_maybe_1z' ] ] ],
            'fire_lpointer4' => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'lpoint3_#00', 'consume' => false], 'zombies' => 'kill_2z' ] ] ],
            'fire_lpointer3' => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'lpoint2_#00', 'consume' => false], 'zombies' => 'kill_2z' ] ] ],
            'fire_lpointer2' => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'lpoint1_#00', 'consume' => false], 'zombies' => 'kill_2z' ] ] ],
            'fire_lpointer1' => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'lpoint_#00',  'consume' => false], 'zombies' => 'kill_2z' ] ] ],

            'fire_asplash5'   => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'watergun_opt_4_#00',     'consume' => false], 'zombies' => 'kill_1z' ] ] ],
            'fire_asplash4'   => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'watergun_opt_3_#00',     'consume' => false], 'zombies' => 'kill_1z' ] ] ],
            'fire_asplash3'   => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'watergun_opt_2_#00',     'consume' => false], 'zombies' => 'kill_1z' ] ] ],
            'fire_asplash2'   => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'watergun_opt_1_#00',     'consume' => false], 'zombies' => 'kill_1z' ] ] ],
            'fire_asplash1'   => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'watergun_opt_empty_#00', 'consume' => false], 'zombies' => 'kill_1z' ] ] ],
            'fire_splash3'    => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'watergun_2_#00',         'consume' => false], 'zombies' => 'kill_1z' ] ] ],
            'fire_splash2'    => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'watergun_1_#00',         'consume' => false], 'zombies' => 'kill_1z' ] ] ],
            'fire_splash1'    => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'item' => ['morph' => 'watergun_empty_#00',     'consume' => false], 'zombies' => 'kill_1z' ] ] ],

            'throw_animal'     => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ 'consume_item', 'kill_1_zombie' ] ],
            'throw_animal_cat' => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'group' => [ [['do_nothing'],  7], [['consume_item'], 3] ], 'zombies' => 'kill_1z' ] ] ],
            'throw_animal_dog' => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ [ 'group' => [ [['do_nothing'], 95], [['consume_item'], 5] ], 'zombies' => 'kill_1z' ] ] ],

            'throw_b_machine_1'     => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_40'], 'kill_1_zombie' ] ],
            'throw_b_bone'          => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_80'], 'kill_1_zombie' ] ],
            'throw_b_can_opener'    => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_30'], ['group' => 'g_kill_1z_33'] ] ],
            'throw_b_chair basic'   => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_30'], ['group' => 'g_kill_1z_85'] ] ],
            'throw_b_torch'         => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['item' => ['morph' => 'torch_off_#00', 'consume' => false]], 'kill_1_zombie' ] ],
            'throw_b_chain'         => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_25'], ['group' => 'g_kill_1z_50'] ] ],
            'throw_b_staff'         => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_50'], ['group' => 'g_kill_1z_33'] ] ],
            'throw_b_knife'         => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_33'], 'kill_1_zombie' ] ],
            'throw_b_machine_2'     => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_40'], 'kill_1_zombie' ] ],
            'throw_b_small_knife'   => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_50'], ['group' => 'g_kill_1z_33'] ] ],
            'throw_b_cutcut'        => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_25'], 'kill_2_zombie' ] ],
            'throw_b_machine_3'     => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_40'], 'kill_1_zombie' ] ],
            'throw_b_pc'            => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_50'], 'kill_1_zombie' ] ],
            'throw_b_lawn'          => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_20'], 'kill_2_zombie' ] ],
            'throw_b_screw'         => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_66'], ['group' => 'g_kill_1z_33'] ] ],
            'throw_b_swiss_knife'   => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_33'], ['group' => 'g_kill_1z_33'] ] ],
            'throw_b_cutter'        => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_80'], ['group' => 'g_kill_1z_20'] ] ],
            'throw_b_concrete_wall' => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_20'], 'kill_1_zombie' ] ],
            'throw_b_torch_off'     => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_50'], ['group' => 'g_kill_1z_10'] ] ],
            'throw_b_wrench'        => [ 'label' => 'Waffe einsetzen', 'meta' => [ 'must_be_outside', 'must_have_zombies', 'not_tired' ], 'result' => [ ['group' => 'g_break_33'], ['group' => 'g_kill_1z_50'] ] ],
        ],
        'items' => [
            'water_#00'           => [ 'water_6ap', 'water_0ap' ],
            'water_cup_#00'       => [ 'water_6ap', 'water_0ap' ],

            'water_can_3_#00'     => [ 'watercan3_6ap', 'watercan3_0ap' ],
            'water_can_2_#00'     => [ 'watercan2_6ap', 'watercan2_0ap', 'fill_watercan2' ],
            'water_can_1_#00'     => [ 'watercan1_6ap', 'watercan1_0ap', 'fill_watercan1' ],
            'water_can_empty_#00' => [ 'fill_watercan0' ],

            'can_#00'             => [ 'can' ],

            'can_open_#00'        => [ 'eat_6ap'],
            'fruit_#00'           => [ 'eat_6ap'],
            'bretz_#00'           => [ 'eat_6ap'],
            'undef_#00'           => [ 'eat_6ap'],
            'dish_#00'            => [ 'eat_6ap'],
            'vegetable_#00'       => [ 'eat_6ap'],
            'food_bar1_#00'       => [ 'eat_6ap'],
            'food_bar2_#00'       => [ 'eat_6ap'],
            'food_bar3_#00'       => [ 'eat_6ap'],
            'food_biscuit_#00'    => [ 'eat_6ap'],
            'food_chick_#00'      => [ 'eat_6ap'],
            'food_pims_#00'       => [ 'eat_6ap'],
            'food_tarte_#00'      => [ 'eat_6ap'],
            'food_sandw_#00'      => [ 'eat_6ap'],
            'food_noodles_#00'    => [ 'eat_6ap'],

            'food_noodles_hot_#00'=> [ 'eat_7ap'],
            'meat_#00'            => [ 'eat_7ap'],
            'vegetable_tasty_#00' => [ 'eat_7ap'],
            'dish_tasty_#00'      => [ 'eat_7ap'],
            'food_candies_#00'    => [ 'eat_7ap'],
            'chama_tasty_#00'     => [ 'eat_7ap'],
            'woodsteak_#00'       => [ 'eat_7ap'],
            'egg_#00'             => [ 'eat_7ap'],
            'apple_#00'           => [ 'eat_7ap'],

            'disinfect_#00'       => [ 'drug_par_1', 'drug_par_2' ],
            'drug_#00'            => [ 'drug_6ap_1', 'drug_6ap_2' ],
            'drug_hero_#00'       => [ 'drug_8ap_1', 'drug_8ap_2' ],
            'drug_random_#00'     => [ 'drug_rand_1', 'drug_rand_2' ],

            'food_bag_#00'        => [ 'open_doggybag' ],
            'food_armag_#00'      => [ 'open_lunchbag' ],
            'chest_citizen_#00'   => [ 'open_c_chest' ],
            'chest_hero_#00'      => [ 'open_h_chest' ],
            'postal_box_#00'      => [ 'open_postbox' ],

            'game_box_#00'        => [ 'open_gamebox' ],
            'bplan_box_#00'       => [ 'open_abox' ],
            'bplan_drop_#00'      => [ 'open_cbox' ],

            'rsc_pack_3_#00'         => [ 'open_matbox3' ],
            'rsc_pack_2_#00'         => [ 'open_matbox2' ],
            'rsc_pack_1_#00'         => [ 'open_matbox1' ],
            'chest_christmas_3_#00'  => [ 'open_xmasbox3' ],
            'chest_christmas_2_#00'  => [ 'open_xmasbox2' ],
            'chest_christmas_1_#00'  => [ 'open_xmasbox1' ],

            'chest_#00'           => [ 'open_metalbox' ],
            'chest_xl_#00'        => [ 'open_metalbox2' ],
            'catbox_#00'          => [ 'open_catbox' ],
            'chest_tools_#00'     => [ 'open_toolbox' ],
            'chest_food_#00'      => [ 'open_foodbox' ],

            'pilegun_empty_#00'      => [ 'load_pilegun'  ],
            'pilegun_up_empty_#00'   => [ 'load_pilegun2' ],
            'big_pgun_empty_#00'     => [ 'load_pilegun3' ],
            'mixergun_empty_#00'     => [ 'load_mixergun' ],
            'chainsaw_empty_#00'     => [ 'load_chainsaw' ],
            'taser_empty_#00'        => [ 'load_taser' ],
            'lpoint_#00'             => [ 'load_lpointer' ],

            'lamp_#00'             => [ 'load_lamp' ],
            'vibr_empty_#00'       => [ 'load_dildo' ],
            'radius_mk2_part_#00'  => [ 'load_rmk2' ],
            'maglite_off_#00'      => [ 'load_maglite' ],

            'watergun_opt_empty_#00' => [ 'fill_asplash' ],
            'watergun_empty_#00'     => [ 'fill_splash' ],
            'jerrygun_off_#00'       => [ 'fill_jsplash'],
            'kalach_#01'             => [ 'fill_ksplash'],
            'grenade_empty_#00'      => [ 'fill_grenade'],

            'pilegun_#00'      => [ 'fire_pilegun'  ],
            'pilegun_up_#00'   => [ 'fire_pilegun2' ],
            'big_pgun_#00'     => [ 'fire_pilegun3' ],
            'mixergun_#00'     => [ 'fire_mixergun' ],
            'chainsaw_#00'     => [ 'fire_chainsaw' ],
            'taser_#00'        => [ 'fire_taser' ],
            'lpoint4_#00'       => [ 'fire_lpointer4' ],
            'lpoint3_#00'       => [ 'fire_lpointer3' ],
            'lpoint2_#00'       => [ 'fire_lpointer2' ],
            'lpoint1_#00'       => [ 'fire_lpointer1' ],

            'watergun_opt_5_#00' => [ 'fire_asplash5' ],
            'watergun_opt_4_#00' => [ 'fire_asplash4' ],
            'watergun_opt_3_#00' => [ 'fire_asplash3' ],
            'watergun_opt_2_#00' => [ 'fire_asplash2' ],
            'watergun_opt_1_#00' => [ 'fire_asplash1' ],
            'watergun_3_#00'     => [ 'fire_splash3' ],
            'watergun_2_#00'     => [ 'fire_splash2' ],
            'watergun_1_#00'     => [ 'fire_splash1' ],

            'pet_chick_#00' => [ 'throw_animal'     ],
            'pet_rat_#00'   => [ 'throw_animal'     ],
            'pet_pig_#00'   => [ 'throw_animal'     ],
            'pet_snake_#00' => [ 'throw_animal'     ],
            'pet_cat_#00'   => [ 'throw_animal_cat' ],
            'tekel_#00'     => [ 'throw_animal_dog' ],
            'pet_dog_#00'   => [ 'throw_animal_dog' ],

            'machine_1_#00'     => ['throw_b_machine_1'    ],
            'bone_#00'          => ['throw_b_bone'         ],
            'can_opener_#00'    => ['throw_b_can_opener'   ],
            'chair_basic_#00'   => ['throw_b_chair basic'  ],
            'torch_#00'         => ['throw_b_torch'        ],
            'chain_#00'         => ['throw_b_chain'        ],
            'staff_#00'         => ['throw_b_staff'        ],
            'knife_#00'         => ['throw_b_knife'        ],
            'machine_2_#00'     => ['throw_b_machine_2'    ],
            'small_knife_#00'   => ['throw_b_small_knife'  ],
            'cutcut_#00'        => ['throw_b_cutcut'       ],
            'machine_3_#00'     => ['throw_b_machine_3'    ],
            'pc_#00'            => ['throw_b_pc'           ],
            'lawn_#00'          => ['throw_b_lawn'         ],
            'screw_#00'         => ['throw_b_screw'        ],
            'swiss_knife_#00'   => ['throw_b_swiss_knife'  ],
            'cutter_#00'        => ['throw_b_cutter'       ],
            'concrete_wall_#00' => ['throw_b_concrete_wall'],
            'torch_off_#00'     => ['throw_b_torch_off'    ],
            'wrench_#00'        => ['throw_b_wrench'       ],
        ]

    ];

    private $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $sub_cache
     * @param array|null $data
     * @return Requirement
     * @throws Exception
     */
    private function process_meta_requirement(        
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array &$sub_cache, ?array &$data = null): Requirement
    {
        if (!isset($cache[$id])) {
            if ($data === null && !isset(static::$item_actions['meta_requirements'][$id])) throw new Exception('Requirement definition not found: ' . $id);

            $data = $data ?: static::$item_actions['meta_requirements'][$id];
            $requirement = $manager->getRepository(Requirement::class)->findOneByName( $id );
            if ($requirement) $out->writeln( "\t\t<comment>Update</comment> meta condition <info>$id</info>" );
            else {
                $requirement = new Requirement();
                $out->writeln( "\t\t<comment>Create</comment> meta condition <info>$id</info>" );
            }

            $requirement
                ->setName( $id )
                ->setFailureMode( $data['type'] )
                ->setFailureText( isset($data['text']) ? $data['text'] : null );

            foreach ($data['collection'] as $sub_id => $sub_req) {
                if (is_array($sub_req)) {
                    $sub_data = $sub_req;
                    $sub_req = "{$id}_i_{$sub_id}";
                }

                else {
                    if (!isset( static::$item_actions['requirements'][$sub_id] ))
                        throw new Exception('Requirement type definition not found: ' . $sub_id);
                    if (!isset( static::$item_actions['requirements'][$sub_id][$sub_req] ))
                        throw new Exception('Requirement entry definition not found: ' . $sub_id . '/' . $sub_req);

                    $sub_data = static::$item_actions['requirements'][$sub_id][$sub_req];
                }

                if (!isset($sub_cache[$sub_id])) $sub_cache[$sub_id] = [];
                                
                switch ($sub_id) {
                    case 'status':
                        $requirement->setStatusRequirement( $this->process_status_requirement( $manager, $out, $sub_cache[$sub_id], $sub_req, $sub_data ) );
                        break;
                    case 'item':
                        $requirement->setItem( $this->process_item_requirement($manager, $out, $sub_cache[$sub_id], $sub_req, $sub_data ) );
                        break;
                    case 'location':
                        $requirement->setLocation( $this->process_location_requirement($manager, $out, $sub_cache[$sub_id], $sub_req, $sub_data ) );
                        break;
                    case 'zombies':
                        $requirement->setZombies( $this->process_zombie_requirement($manager, $out, $sub_cache[$sub_id], $sub_req, $sub_data ) );
                        break;
                    default:
                        throw new Exception('No handler for requirement type ' . $sub_id);
                }
            }

            $manager->persist( $cache[$id] = $requirement );
        } else $out->writeln( "\t\t<comment>Skip</comment> meta condition <info>$id</info>" );
        
        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return RequireStatus
     * @throws Exception
     */
    private function process_status_requirement(
        ObjectManager $manager, ConsoleOutputInterface $out, 
        array &$cache, string $id, array $data): RequireStatus
    {
        if (!isset($cache[$id])) {
            $requirement = $manager->getRepository(RequireStatus::class)->findOneByName( $id );
            if ($requirement) $out->writeln( "\t\t\t<comment>Update</comment> condition <info>status/{$id}</info>" );
            else {
                $requirement = new RequireStatus();
                $out->writeln( "\t\t\t<comment>Create</comment> condition <info>status/{$id}</info>" );
            }
            $status = $manager->getRepository(CitizenStatus::class)->findOneByName( $data['status'] );
            if (!$status)
                throw new Exception('Status condition not found: ' . $data['status']);

            $requirement->setName( $id )->setEnabled( $data['enabled'] )->setStatus( $status );
            $manager->persist( $cache[$id] = $requirement );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> condition <info>status/{$id}</info>" );
        
        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return RequireItem
     * @throws Exception
     */
    private function process_item_requirement(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): RequireItem
    {
        if (!isset($cache[$id])) {
            $requirement = $manager->getRepository(RequireItem::class)->findOneByName( $id );
            if ($requirement) $out->writeln( "\t\t\t<comment>Update</comment> condition <info>item/{$id}</info>" );
            else {
                $requirement = new RequireItem();
                $out->writeln( "\t\t\t<comment>Create</comment> condition <info>item/{$id}</info>" );
            }
            $prototype = empty($data['item']) ? null : $manager->getRepository(ItemPrototype::class)->findOneByName( $data['item'] );
            if (!empty($data['item']) && ! $prototype)
                throw new Exception('Item prototype not found: ' . $data['item']);

            $property  = empty($data['prop']) ? null : $manager->getRepository(ItemProperty::class )->findOneByName( $data['prop'] );
            if (!empty($data['prop']) && ! $property)
                throw new Exception('Item property not found: ' . $data['prop']);

            if (!$prototype && !$property)
                throw new Exception('Item condition must have a prototype or property attached. not found: ' . $data['status']);

            $requirement->setName( $id )->setPrototype( $prototype )->setProperty( $property );
            $manager->persist( $cache[$id] = $requirement );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> condition <info>item/{$id}</info>" );

        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return RequireLocation
     * @throws Exception
     */
    private function process_location_requirement(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): RequireLocation
    {
        if (!isset($cache[$id])) {
            $requirement = $manager->getRepository(RequireLocation::class)->findOneByName( $id );
            if ($requirement) $out->writeln( "\t\t\t<comment>Update</comment> condition <info>location/{$id}</info>" );
            else {
                $requirement = new RequireLocation();
                $out->writeln( "\t\t\t<comment>Create</comment> condition <info>location/{$id}</info>" );
            }

            $requirement->setName( $id )->setLocation( $data[0] );
            $manager->persist( $cache[$id] = $requirement );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> condition <info>location/{$id}</info>" );

        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return RequireZombiePresence
     * @throws Exception
     */
    private function process_zombie_requirement(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): RequireZombiePresence
    {
        if (!isset($cache[$id])) {
            $requirement = $manager->getRepository(RequireZombiePresence::class)->findOneByName( $id );
            if ($requirement) $out->writeln( "\t\t\t<comment>Update</comment> condition <info>zombies/{$id}</info>" );
            else {
                $requirement = new RequireZombiePresence();
                $out->writeln( "\t\t\t<comment>Create</comment> condition <info>zombies/{$id}</info>" );
            }

            $requirement->setName( $id )->setNumber( $data['min'] )->setMustBlock( $data['block'] );
            $manager->persist( $cache[$id] = $requirement );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> condition <info>zombies/{$id}</info>" );

        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $sub_cache
     * @param array|null $data
     * @return Result
     * @throws Exception
     */
    private function process_meta_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array &$sub_cache, ?array &$data = null): Result
    {
        if (!isset($cache[$id])) {
            if ($data === null && !isset(static::$item_actions['meta_results'][$id])) throw new Exception('Result definition not found: ' . $id);
            $data = $data ?: static::$item_actions['meta_results'][$id];

            $result = $manager->getRepository(Result::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t<comment>Update</comment> meta effect <info>$id</info>" );
            else {
                $result = new Result();
                $out->writeln( "\t\t<comment>Create</comment> meta effect <info>$id</info>" );
            }

            $result->setName( $id );

            $collection = isset($data['collection']) ? $data['collection'] : $data;
            foreach ($collection as $sub_id => $sub_res) {
                if (is_array($sub_res)) {
                    $sub_data = $sub_res;
                    $sub_res = "{$id}_i_{$sub_id}";
                } else {
                    if (!isset( static::$item_actions['results'][$sub_id] ))
                        throw new Exception('Result type definition not found: ' . $sub_id);
                    if (!isset( static::$item_actions['results'][$sub_id][$sub_res] ))
                        throw new Exception('Result entry definition not found: ' . $sub_id . '/' . $sub_res);

                    $sub_data = static::$item_actions['results'][$sub_id][$sub_res];
                }

                if (!isset($sub_cache[$sub_id])) $sub_cache[$sub_id] = [];

                switch ($sub_id) {
                    case 'status':
                        $result->setStatus( $this->process_status_effect($manager,$out,$sub_cache[$sub_id], $sub_res, $sub_data) );
                        break;
                    case 'ap':
                        $result->setAp( $this->process_ap_effect($manager,$out, $sub_cache[$sub_id], $sub_res, $sub_data) );
                        break;
                    case 'item':
                        $result->setItem( $this->process_item_effect($manager, $out, $sub_cache[$sub_id], $sub_res, $sub_data) );
                        break;
                    case 'spawn':
                        $result->setSpawn( $this->process_spawn_effect($manager, $out, $sub_cache[$sub_id], $sub_res, $sub_data) );
                        break;
                    case 'consume':
                        $result->setConsume( $this->process_consume_effect($manager, $out, $sub_cache[$sub_id], $sub_res, $sub_data) );
                        break;
                    case 'zombies':
                        $result->setZombies( $this->process_zombie_effect($manager, $out, $sub_cache[$sub_id], $sub_res, $sub_data) );
                        break;
                    case 'group':
                        $result->setResultGroup( $this->process_group_effect($manager, $out, $sub_cache[$sub_id], $cache, $sub_cache, $sub_res, $sub_data) );
                        break;
                    default:
                        throw new Exception('No handler for effect type ' . $sub_id);
                }
            }

            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t<comment>Skip</comment> meta effect <info>$id</info>" );

        return $cache[$id];
    }
    
    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return AffectStatus
     * @throws Exception
     */
    private function process_status_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): AffectStatus
    {
        if (!isset($cache[$id])) {
            $result = $manager->getRepository(AffectStatus::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t\t<comment>Update</comment> effect <info>status/{$id}</info>" );
            else {
                $result = new AffectStatus();
                $out->writeln( "\t\t\t<comment>Create</comment> effect <info>status/{$id}</info>" );
            }
            $status_from = empty($data['from']) ? null : $manager->getRepository(CitizenStatus::class)->findOneByName( $data['from'] );
            if (!$status_from && !empty($data['from'])) throw new Exception('Status effect not found: ' . $data['from']);
            $status_to = empty($data['to']) ? null : $manager->getRepository(CitizenStatus::class)->findOneByName( $data['to'] );
            if (!$status_to && !empty($data['to'])) throw new Exception('Status effect not found: ' . $data['to']);

            if (!$status_from && !$status_to) throw new Exception('Status effects must have at least one attached status.');

            $result->setName( $id )->setInitial( $status_from )->setResult( $status_to );
            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> effect <info>status/{$id}</info>" );
        
        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return AffectAP
     */
    private function process_ap_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): AffectAP
    {
        if (!isset($cache[$id])) {
            $result = $manager->getRepository(AffectAP::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t\t<comment>Update</comment> effect <info>ap/{$id}</info>" );
            else {
                $result = new AffectAP();
                $out->writeln( "\t\t\t<comment>Create</comment> effect <info>ap/{$id}</info>" );
            }

            $result->setName( $id )->setMax( $data['max'] )->setAp( $data['num'] );
            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> effect <info>ap/{$id}</info>" );
        
        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return AffectOriginalItem
     * @throws Exception
     */
    private function process_item_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): AffectOriginalItem 
    {
        if (!isset($cache[$id])) {
            $result = $manager->getRepository(AffectOriginalItem::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t\t<comment>Update</comment> effect <info>item/{$id}</info>" );
            else {
                $result = new AffectOriginalItem();
                $out->writeln( "\t\t\t<comment>Create</comment> effect <info>item/{$id}</info>" );
            }
            $morph_to = empty($data['morph']) ? null : $manager->getRepository(ItemPrototype::class)->findOneByName( $data['morph'] );
            if (!$morph_to && !empty($data['morph'])) throw new Exception('Item prototype not found: ' . $data['morph']);

            if ($morph_to && $data['consume']) throw new Exception('Item effects cannot morph and consume at the same time!');

            $result->setName( $id )->setConsume( $data['consume'] )->setMorph( $morph_to )
                ->setBreak( isset($data['break']) ? $data['break'] : null )
                ->setPoison( isset($data['poison']) ? $data['poison'] : null );
            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> effect <info>item/{$id}</info>" );
        
        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return AffectItemSpawn
     * @throws Exception
     */
    private function process_spawn_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): AffectItemSpawn
    {
        if (!isset($cache[$id])) {
            $result = $manager->getRepository(AffectItemSpawn::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t\t<comment>Update</comment> effect <info>spawn/{$id}</info>" );
            else {
                $result = (new AffectItemSpawn())->setName( $id );
                $out->writeln( "\t\t\t<comment>Create</comment> effect <info>spawn/{$id}</info>" );
            }

            if (count($data) === 1) {
                $name = is_array($data[0]) ? $data[0][0] : $data[0];
                $prototype = $manager->getRepository(ItemPrototype::class)->findOneByName( $name );
                if (!$prototype) throw new Exception('Item prototype not found: ' . $name);
                $result->setPrototype( $prototype );
            } else {
                $g_name = "efg_{$id}";
                $group = $manager->getRepository( ItemGroup::class )->findOneByName( $g_name );
                if ($group) $group->getEntries()->clear();
                else $group = (new ItemGroup())->setName( $g_name );

                foreach ($data as $entry) {
                    list($p,$c) = is_array($entry) ? $entry : [$entry,1];
                    $prototype = $manager->getRepository(ItemPrototype::class)->findOneByName( $p );
                    if (!$prototype) throw new Exception('Item prototype not found: ' . $p);
                    $group->addEntry( (new ItemGroupEntry())->setChance($c)->setPrototype( $prototype ) );
                }

                $result->setItemGroup( $group );
                $manager->persist( $group );
            }

            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> effect <info>spawn/{$id}</info>" );

        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return AffectItemConsume
     * @throws Exception
     */
    private function process_consume_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): AffectItemConsume
    {
        if (!isset($cache[$id])) {
            $result = $manager->getRepository(AffectItemConsume::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t\t<comment>Update</comment> effect <info>consume/{$id}</info>" );
            else {
                $result = (new AffectItemConsume())->setName( $id );
                $out->writeln( "\t\t\t<comment>Create</comment> effect <info>consume/{$id}</info>" );
            }

            list($name,$count) = count($data) > 1 ? $data : [$data[0],1];
            $prototype = $manager->getRepository(ItemPrototype::class)->findOneByName( $name );
            if (!$prototype) throw new Exception('Item prototype not found: ' . $name);
            $result->setPrototype( $prototype )->setCount( $count );

            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> effect <info>consume/{$id}</info>" );

        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return AffectZombies
     */
    private function process_zombie_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): AffectZombies
    {
        if (!isset($cache[$id])) {
            $result = $manager->getRepository(AffectZombies::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t\t<comment>Update</comment> effect <info>zombie/{$id}</info>" );
            else {
                $result = new AffectZombies();
                $out->writeln( "\t\t\t<comment>Create</comment> effect <info>zombie/{$id}</info>" );
            }

            $result->setName( $id )->setMax( isset($data['max']) ? $data['max'] : $data['num'] )->setMin( isset($data['min']) ? $data['min'] : $data['num'] );
            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> effect <info>zombie/{$id}</info>" );

        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param array $meta_cache
     * @param array $sub_cache
     * @param string $id
     * @param array $data
     * @return AffectResultGroup
     * @throws Exception
     */
    private function process_group_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, array &$meta_cache, array &$sub_cache, string $id, array $data): AffectResultGroup
    {
        if (!isset($cache[$id])) {
            $result = $manager->getRepository(AffectResultGroup::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t\t<comment>Update</comment> effect <info>group/{$id}</info>" );
            else {
                $result = (new AffectResultGroup())->setName( $id );
                $out->writeln( "\t\t\t<comment>Create</comment> effect <info>group/{$id}</info>" );
            }

            foreach ( $result->getEntries() as $entry ) $manager->remove( $entry ); $result->getEntries()->clear();
            foreach ( $data as $k => $entry ) {

                $entry_obj = new AffectResultGroupEntry();
                $entry_obj->setCount( $entry[1] );

                if (!is_array($entry[0])) $entry[0] = [$entry[0]];
                foreach ( $entry[0] as $n => $nested_action )
                    if (is_array( $nested_action ))
                        $entry_obj->addResult( $this->process_meta_effect( $manager, $out, $meta_cache, "{$id}_{$k}_{$n}", $sub_cache, $nested_action ) );
                    else $entry_obj->addResult( $this->process_meta_effect( $manager, $out, $meta_cache, $nested_action, $sub_cache ) );

                $result->addEntry( $entry_obj );
                $manager->persist( $entry_obj );
            }

            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> effect <info>group/{$id}</info>" );

        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @throws Exception
     */
    public function insert_item_actions(ObjectManager $manager, ConsoleOutputInterface $out) {

        $out->writeln( '<comment>Compiling item action fixtures.</comment>' );

        $set_meta_requirements = [];
        $set_sub_requirements = [];

        $set_meta_results = [];
        $set_sub_results = [];

        $set_actions = [];

        foreach (static::$item_actions['items'] as $item_name => $actions) {

            $item = $manager->getRepository(ItemPrototype::class)->findOneByName( $item_name );
            if (!$item) throw new Exception('Item prototype not found: ' . $item_name);
            $out->writeln( "Compiling action set for item <info>{$item->getLabel()}</info>..." );

            foreach ($actions as $action) {

                if (!isset($set_actions[$action])) {
                    if (!isset(static::$item_actions['actions'][$action])) throw new Exception('Action definition not found: ' . $action);

                    $data = static::$item_actions['actions'][$action];
                    $new_action = $manager->getRepository(ItemAction::class)->findOneByName( $action );
                    if ($new_action) $out->writeln( "\t<comment>Update</comment> action <info>$action</info> ('<info>{$data['label']}</info>')" );
                    else {
                        $new_action = new ItemAction();
                        $out->writeln( "\t<comment>Create</comment> action <info>$action</info> ('<info>{$data['label']}</info>')" );
                    }

                    $new_action->setName( $action )->setLabel( $data['label'] )->clearRequirements();
                    if (!empty($data['message'])) $new_action->setMessage( $data['message'] );
                    else $new_action->setMessage(null);

                    foreach ( $data['meta'] as $num => $requirement ) {
                        if (is_array($requirement))
                            $new_action->addRequirement( $this->process_meta_requirement( $manager, $out, $set_meta_requirements, "{$action}_{$num}", $set_sub_requirements, $requirement ) );
                        else $new_action->addRequirement( $this->process_meta_requirement( $manager, $out, $set_meta_requirements, $requirement, $set_sub_requirements ) );
                    }

                    foreach ( $data['result'] as $num => $result ) {
                        if (is_array($result))
                            $new_action->addResult( $this->process_meta_effect($manager,$out, $set_meta_results, "{$action}_{$num}", $set_sub_results, $result) );
                        else $new_action->addResult( $this->process_meta_effect($manager,$out, $set_meta_results, $result, $set_sub_results) );
                    }

                    $manager->persist( $set_actions[$action] = $new_action );
                } else $out->writeln( "\t<comment>Skip</comment> action <info>$action</info> ('<info>{$set_actions[$action]->getLabel()}</info>')" );

                $item->addAction( $set_actions[$action] );
            }
            $manager->persist( $item );
        }
        $manager->flush();
    }

    public function load(ObjectManager $manager) {

        $output = new ConsoleOutput();
        $output->writeln( '<info>Installing fixtures: Actions</info>' );
        $output->writeln("");

        try {
            $this->insert_item_actions( $manager, $output );
        } catch (Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }

        $output->writeln("");
    }

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [ ItemFixtures::class ];
    }
}
