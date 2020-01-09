<?php

namespace App\DataFixtures;

use App\Entity\AffectAP;
use App\Entity\AffectOriginalItem;
use App\Entity\AffectStatus;
use App\Entity\CitizenStatus;
use App\Entity\ItemAction;
use App\Entity\ItemProperty;
use App\Entity\ItemPrototype;
use App\Entity\RequireItem;
use App\Entity\Requirement;
use App\Entity\RequireStatus;
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

            'have_can_opener' => [ 'type' => Requirement::MessageOnFail, 'collection' => [ 'item' => 'have_can_opener' ],  'text' => 'Du brauchst ein Werkzeug, um diesen Gegenstand zu öffnen...' ]
        ],

        'requirements' => [
            'status' => [
                'has_not_drunken' => [ 'enabled' => false, 'status' => 'hasdrunk' ],
                'has_not_eaten'   => [ 'enabled' => false, 'status' => 'haseaten' ],

                'not_dehydrated'  => [ 'enabled' => false, 'status' => 'thirst2' ],
                'dehydrated'      => [ 'enabled' => true,  'status' => 'thirst2' ],

                'not_drugged'  => [ 'enabled' => false, 'status' => 'drugged' ],
                'drugged'      => [ 'enabled' => true,  'status' => 'drugged' ],

            ],
            'item' => [
                'have_can_opener' => [ 'item' => null, 'prop' => 'can_opener' ],
            ]
        ],

        'meta_results' => [
            'consume_item'=> [ 'collection' => [ 'item' => 'consume' ]],

            'drink_ap_1'  => [ 'collection' => [ 'status' => 'add_has_drunk', 'ap' => 'to_max_plus_0' ]],
            'drink_ap_2'  => [ 'collection' => [ 'status' => 'remove_thirst' ]],
            'drink_no_ap' => [ 'collection' => [ 'status' => 'replace_dehydration' ]],

            'eat_ap6'     => [ 'collection' => [ 'status' => 'add_has_eaten', 'ap' => 'to_max_plus_0' ]],
            'eat_ap7'     => [ 'collection' => [ 'status' => 'add_has_eaten', 'ap' => 'to_max_plus_1' ]],

            'drug_any_1'   => [ 'collection' => [ 'status' => 'remove_clean' ]],
            'drug_any_2'   => [ 'collection' => [ 'status' => 'add_is_drugged' ]],
            'drug_addict'  => [ 'collection' => [ 'status' => 'add_addicted' ]],

            'disinfect'    => [ 'collection' => [ 'status' => 'remove_infection' ]],

            'just_ap6'     => [ 'collection' => [ 'ap' => 'to_max_plus_0' ]],
            'just_ap7'     => [ 'collection' => [ 'ap' => 'to_max_plus_1' ]],
            'just_ap8'     => [ 'collection' => [ 'ap' => 'to_max_plus_2' ]],

            'produce_open_can' =>  [ 'collection' => [ 'item' => 'produce_open_can' ]],
            'produce_watercan2' => [ 'collection' => [ 'item' => 'produce_watercan2' ]],
            'produce_watercan1' => [ 'collection' => [ 'item' => 'produce_watercan1' ]],
            'produce_watercan0' => [ 'collection' => [ 'item' => 'produce_watercan0' ]],
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

            ],
            'item' => [
                'consume' => [ 'consume' => true, 'morph' => null ],

                'produce_open_can' =>  [ 'consume' => false, 'morph' => 'can_open_#00' ],
                'produce_watercan2' => [ 'consume' => false, 'morph' => 'water_can_2_#00' ],
                'produce_watercan1' => [ 'consume' => false, 'morph' => 'water_can_1_#00' ],
                'produce_watercan0' => [ 'consume' => false, 'morph' => 'water_can_empty_#00' ],
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

            'can'       => [ 'label' => 'Öffnen',  'meta' => [ 'have_can_opener' ], 'result' => [ 'produce_open_can' ] ],

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

        ],
        'items' => [
            'water_#00'           => [ 'water_6ap', 'water_0ap' ],
            'water_cup_#00'       => [ 'water_6ap', 'water_0ap' ],
            'water_can_3_#00'     => [ 'watercan3_6ap', 'watercan3_0ap' ],
            'water_can_2_#00'     => [ 'watercan2_6ap', 'watercan2_0ap' ],
            'water_can_1_#00'     => [ 'watercan1_6ap', 'watercan1_0ap' ],
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
     * @return Requirement
     * @throws Exception
     */
    private function process_meta_requirement(        
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array &$sub_cache): Requirement
    {
        if (!isset($cache[$id])) {
            if (!isset(static::$item_actions['meta_requirements'][$id])) throw new Exception('Requirement definition not found: ' . $id);

            $data = static::$item_actions['meta_requirements'][$id];
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
                if (!isset( static::$item_actions['requirements'][$sub_id] ))
                    throw new Exception('Requirement type definition not found: ' . $sub_id);
                if (!isset( static::$item_actions['requirements'][$sub_id][$sub_req] ))
                    throw new Exception('Requirement entry definition not found: ' . $sub_id . '/' . $sub_req);

                $sub_data = static::$item_actions['requirements'][$sub_id][$sub_req];
                if (!isset($sub_cache[$sub_id])) $sub_cache[$sub_id] = [];
                                
                switch ($sub_id) {
                    case 'status':
                        $requirement->setStatusRequirement( $this->process_status_requirement( $manager, $out, $sub_cache[$sub_id], $sub_req, $sub_data ) );
                        break;
                    case 'item':
                        $requirement->setItem( $this->process_item_requirement($manager, $out, $sub_cache[$sub_id], $sub_req, $sub_data ) );
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
     * @param array $sub_cache
     * @return Result
     * @throws Exception
     */
    private function process_meta_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array &$sub_cache): Result
    {
        if (!isset($cache[$id])) {
            if (!isset(static::$item_actions['meta_results'][$id])) throw new Exception('Result definition not found: ' . $id);

            $data = static::$item_actions['meta_results'][$id];
            $result = $manager->getRepository(Result::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t<comment>Update</comment> meta effect <info>$id</info>" );
            else {
                $result = new Result();
                $out->writeln( "\t\t<comment>Create</comment> meta effect <info>$id</info>" );
            }

            $result->setName( $id );

            foreach ($data['collection'] as $sub_id => $sub_res) {
                if (!isset( static::$item_actions['results'][$sub_id] ))
                    throw new Exception('Result type definition not found: ' . $sub_id);
                if (!isset( static::$item_actions['results'][$sub_id][$sub_res] ))
                    throw new Exception('Result entry definition not found: ' . $sub_id . '/' . $sub_res);

                $sub_data = static::$item_actions['results'][$sub_id][$sub_res];
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
        } else $out->writeln( "\t\t\t<comment>Skip</comment> condition <info>status/{$id}</info>" );
        
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
        } else $out->writeln( "\t\t\t<comment>Skip</comment> condition <info>ap/{$id}</info>" );
        
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

            $result->setName( $id )->setConsume( $data['consume'] )->setMorph( $morph_to );
            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> condition <info>item/{$id}</info>" );
        
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

                    foreach ( $data['meta'] as $requirement )
                        $new_action->addRequirement( $this->process_meta_requirement( $manager, $out, $set_meta_requirements, $requirement, $set_sub_requirements ) );

                    foreach ( $data['result'] as $result )
                        $new_action->addResult( $this->process_meta_effect($manager,$out, $set_meta_results, $result, $set_sub_results) );

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
