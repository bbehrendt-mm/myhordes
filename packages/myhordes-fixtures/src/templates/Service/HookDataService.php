<?php

namespace MyHordes\Fixtures\Service;

use App\Hooks\ClassHooks;
use App\Hooks\HomeHooks;
use App\Hooks\ItemTargetRendererHooks;
use App\Hooks\NightwatchHooks;
use App\Hooks\SoulHooks;
use App\Hooks\TownHooks;
use App\Hooks\WorkshopHooks;
use MyHordes\Plugins\Interfaces\FixtureProcessorInterface;

class HookDataService implements FixtureProcessorInterface {

    public function process(array &$data, ?string $tag = null): void
    {
        $data = array_replace_recursive($data, [
            'additionalCitizenRowClassCore' => [
                'hookname' => 'additionalCitizenRowClass',
                'classname' => ClassHooks::class,
                'active' => true,
                'position' => 0
            ],
            'nightwatchHeader' => [
                'hookname' => 'nightwatchHeader',
                'classname' => NightwatchHooks::class,
                'active' => true,
                'position' => 1
            ],
            'homeForeignDeadActions' => [
                'hookname' => 'homeForeignDeadActions',
                'classname' => TownHooks::class,
                'active' => true,
                'position' => 1
            ],
            'homeForeignDeadActionsJs' => [
                'hookname' => 'homeForeignDeadActionsJs',
                'classname' => TownHooks::class,
                'active' => true,
                'position' => 1
            ],
            'homeForeignDisposalText' => [
                'hookname' => 'homeForeignDisposalText',
                'classname' => TownHooks::class,
                'active' => true,
                'position' => 1
            ],
            'WorkshopTechText' => [
                'hookname' => 'WorkShopTech',
                'classname' => WorkshopHooks::class,
                'active' => true,
                'position' => 1
            ],
            'HomeDecoPre' => [
                'hookname' => 'HomeDecoValues',
                'classname' => HomeHooks::class,
                'active' => true,
                'position' => 1
            ],
            'gazetteFilterBuildingOptions' => [
                'hookname' => 'gazetteFilterBuildingOptions',
                'classname' => TownHooks::class,
                'active' => true,
                'position' => 1
            ],
            'itemTargetRendererDogPopup' => [
                'hookname' => 'itemTargetRenderer:tamer_dog_popup',
                'function' => 'hookTamerDogPopup',
                'classname' => ItemTargetRendererHooks::class,
                'active' => true,
                'position' => 1
            ],
            'EarnXHP' => [
                'hookname' => 'EarnXHP',
                'classname' => SoulHooks::class,
                'function' => 'earnXHP',
                'active' => true,
                'position' => 1
            ],
        ]);
    }
}
