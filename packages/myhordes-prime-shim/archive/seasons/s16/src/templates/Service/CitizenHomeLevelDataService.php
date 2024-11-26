<?php

namespace MyHordes\Prime\Service;

use MyHordes\Plugins\Interfaces\FixtureProcessorInterface;

class CitizenHomeLevelDataService implements FixtureProcessorInterface {

    public function process(array &$data): void
    {
        $data = array_replace_recursive($data, [
            0 => [
                'label' => 'Feldbett',
                'icon' => 'home_lv0',
                'def' =>  0,
                'ap' => 0,
                'ap_urbanism' => 0,
                'resources' => [],
                'resources_urbanism' => [],
                'building' => null,
                'upgrades' => false,
                'theft' => false
            ],
            1 => [
                'label' => 'Zelt',
                'icon' => 'home_lv1',
                'def' =>  1,
                'ap' => 2,
                'ap_urbanism' => 2,
                'resources' => [],
                'resources_urbanism' => [],
                'building' => null,
                'upgrades' => true,
                'theft' => false
            ],
            2 => [
                'label' => 'Baracke',
                'icon' => 'home_lv2',
                'def' =>  4,
                'ap' => 4,
                'ap_urbanism' => 4,
                'resources' => ['wood_bad_#00' => 1],
                'resources_urbanism' => [],
                'building' => null,
                'upgrades' => true,
                'theft' => false
            ],
            3 => [
                'label' => 'Hütte',
                'icon' => 'home_lv3',
                'def' =>  9,
                'ap' => 5,
                'ap_urbanism' => 4,
                'resources' => ['wood2_#00' => 1],
                'resources_urbanism' => ['wood2_#00' => 1],
                'building' => null,
                'upgrades' => true,
                'theft' => false
            ],
            4 => [
                'label' => 'Haus',
                'icon' => 'home_lv4',
                'def' => 16,
                'ap' => 6,
                'ap_urbanism' => 5,
                'resources' => ['metal_#00' => 1],
                'resources_urbanism' => ['metal_#00' => 1],
                'building' => null,
                'upgrades' => true,
                'theft' => false
            ],
            5 => [
                'label' => 'Umzäuntes Haus',
                'icon' => 'home_lv5',
                'def' => 25,
                'ap' => 6,
                'ap_urbanism' => 6,
                'resources' => ['wood2_#00' => 2, 'metal_#00' => 2, 'wood_beam_#00' => 1, 'lock_#00' => 1],
                'resources_urbanism' => ['wood2_#00' => 2, 'metal_#00' => 2, 'wood_beam_#00' => 1],
                'building' => null,
                'upgrades' => true,
                'theft' => true
            ],
            6 => [
                'label' => 'Befestigte Unterkunft',
                'icon' => 'home_lv6',
                'def' => 36,
                'ap' => 7,
                'ap_urbanism' => 6,
                'resources' => ['plate_raw_#00' => 1, 'wood2_#00' => 2, 'metal_#00' => 3, 'home_box_#00'=> 1],
                'resources_urbanism' => ['plate_raw_#00' => 1, 'wood2_#00' => 2, 'metal_#00' => 3],
                'building' => null,
                'upgrades' => true,
                'theft' => true
            ],
            7 => [
                'label' => 'Bunker',
                'icon' => 'home_lv7',
                'def' => 49,
                'ap' => 7,
                'ap_urbanism' => 7,
                'resources' => ['door_#00' => 1, 'meca_parts_#00' => 1, 'concrete_wall_#00' => 2, 'metal_beam_#00' => 1, 'metal_#00' => 6, 'music_#00' => 1],
                'resources_urbanism' => ['door_#00' => 1, 'meca_parts_#00' => 1, 'concrete_wall_#00' => 2, 'metal_beam_#00' => 1, 'metal_#00' => 4],
                'building' => null,
                'upgrades' => true,
                'theft' => true
            ],
            8 => [
                'label' => 'Schloss',
                'icon' => 'home_lv8',
                'def' => 64,
                'ap' => 8,
                'ap_urbanism' => 8,
                'resources' => ['metal_beam_#00' => 2, 'wood_beam_#00' => 3, 'meca_parts_#00' => 2, 'concrete_wall_#00' => 2, 'plate_raw_#00' => 3, 'wood2_#00' => 5, 'metal_#00' => 8, 'car_door_#00' => 1],
                'resources_urbanism' => ['metal_beam_#00' => 2, 'wood_beam_#00' => 3, 'meca_parts_#00' => 1, 'concrete_wall_#00' => 2, 'plate_raw_#00' => 2, 'wood2_#00' => 5, 'metal_#00' => 8],
                'building' => null,
                'upgrades' => true,
                'theft' => true
            ],
        ]);
    }
}