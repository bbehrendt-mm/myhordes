<?php

namespace MyHordes\Fixtures\Service;

use MyHordes\Plugins\Interfaces\FixtureProcessorInterface;

class CitizenHomeUpgradeDataService implements FixtureProcessorInterface {

    public function process(array &$data, ?string $tag = null): void
    {
        $data = array_replace_recursive($data, [
            [ 'name' => 'curtain', 'label' => 'Großer Vorhang', 'desc' => 'Mit dieser alten, schmutzigen Jutesackleinwand kannst du deine Habseligkeiten vor den neugierigen Blicken deiner Nachbarn schützen.', 'levels' => [
                1 => [ 4, [] ]
            ] ],
            [ 'name' => 'lab', 'label' => 'Hobbylabor', 'desc' => 'Ein in dein Wohnzimmer geschaufeltes Loch dient dir als Versuchsküche für deine pharmazeutischen Experimente.', 'levels' => [
                1 => [ 6, ['machine_1_#00' => 1] ], 2 => [ 4, ['electro_#00' => 1] ], 3 => [ 4, ['tube_#00' => 1] ], 4 => [ 6, ['engine_#00' => 1] ]
            ] ],
            [ 'name' => 'kitchen', 'label' => 'Küche', 'desc' => 'In dieser notdürftig zusammengeschraubten Küche können schmackhafte und \'gesunde\' Speisen zubereitet werden.', 'levels' => [
                1 => [ 6, [] ], 2 => [ 3, ['small_knife_#00' => 1]], 3 => [ 4, ['machine_2_#00' => 1] ], 4 => [ 4, ['machine_3_#00' => 1]]
            ] ],
            [ 'name' => 'alarm', 'label' => 'Primitives Alarmsystem', 'desc' => 'Eisenteile, die an einem Faden hängen - so einfach und so effektiv kann ein Alarmsystem sein. Wenn jemand versuchen sollte, bei dir einzubrechen, wird er zwangsläufig die halbe Stadt aufwecken...', 'levels' => [
                1 => [ 4, ['metal_#00' => 1] ]
            ] ],
            [ 'name' => 'rest', 'label' => 'Ruheecke', 'desc' => 'Was hier als \'Ruhe-Ecke\' bezeichnet wird, ist in Wahrheit nichts anderes als ein mit Kartons gefülltes Loch im Boden... der ideale Ort, wenn deine Kräfte schwinden und du dich für ein Nickerchen zurückziehen willst.', 'levels' => [
                1 => [ 6, [] ], 2 => [ 3, ['wood2_#00' => 1] ], 3 => [ 4, ['bed_#00' => 1] ]
            ] ],
            [ 'name' => 'lock', 'label' => 'Türschloss', 'desc' => 'Dieses rudimentäre Schließsystem schützt dein Haus vor Diebstahl.', 'levels' => [
                1 => [ 6, ['chain_#00' => 1] ]
            ] ],
            [ 'name' => 'fence', 'label' => 'Zaun (Haus)', 'desc' => 'Wenn dich deine Wände nicht mehr ausreichend schützen, solltest du den Bau eines Zauns erwägen.', 'levels' => [
                1 => [ 3, ['chain_#00' => 1, 'metal_beam_#00' => 1] ]
            ] ],
            [ 'name' => 'chest', 'label' => 'Stauraum', 'desc' => 'Deine persönliche Truhe vergrößert sich. ', 'levels' => [
                1 => [ 2, [] ], 2 => [ 2, [] ], 3 => [ 2, [] ], 4 => [ 3, [] ], 5 => [ 4, [] ], 6 => [ 6, [] ], 7 => [ 6, [] ], 8 => [ 6, [] ], 9 => [ 6, [] ], 10 => [ 6, [] ], 11 => [ 6, [] ], 12 => [ 6, [] ], 13 => [ 6, [] ]
            ] ],
            [ 'name' => 'defense', 'label' => 'Verstärkungen', 'desc' => 'Dein Haus wird mit allen zur Verfügung stehenden Mitteln technisch verstärkt und auf Vordermann gebraucht. Diese Maßnahmen verlängern dein Leben... zumindest ein wenig.', 'levels' => [
                1 => [ 3, [] ], 2 => [ 3, ['fence_#00' => 1] ], 3 => [ 3, ['fence_#00' => 1] ], 4 => [ 3, ['fence_#00' => 1] ], 5 => [ 6, ['fence_#00' => 1] ], 6 => [ 6, ['fence_#00' => 1] ], 7 => [ 6, ['fence_#00' => 1, 'metal_#00' => 1] ], 8 => [ 6, ['fence_#00' => 1, 'metal_#00' => 1] ], 9 => [ 6, ['fence_#00' => 1, 'metal_#00' => 1] ], 10 => [ 6, ['fence_#00' => 1, 'metal_#00' => 1] ]
            ] ],
        ]);
    }
}