<?php

namespace MyHordes\Fixtures\Service;

use MyHordes\Fixtures\Interfaces\FixtureProcessorInterface;

class CitizenRoleDataService implements FixtureProcessorInterface {

    public function process(array &$data): void
    {
        $data = array_merge_recursive($data, [
            ['label' => 'Schamane'                    , 'vote' => true,  'icon' => 'shaman', 'name'=>'shaman', 'hidden' => false, 'secret' => false, 'help_section' => 'shaman' ],
            ['label' => 'Reiseleiter in der Außenwelt', 'vote' => true,  'icon' => 'guide',  'name'=>'guide' , 'hidden' => false, 'secret' => false, 'help_section' => 'guide_to_the_world_beyond' ],
            ['label' => 'Ghul',                         'vote' => false, 'icon' => 'ghoul',  'name'=>'ghoul' , 'hidden' => false, 'secret' => true, 'message' => 'Du hast dich in einen Ghul verwandelt! Verrate es niemandem und schau in der Hilfe nach, um mehr zu erfahren.', 'help_section' => 'ghouls' ],
            ['label' => 'Katapult-Bediener',            'vote' => false, 'icon' => 'cata',   'name'=>'cata'  , 'hidden' => true,  'secret' => false ],
        ]);
    }
}