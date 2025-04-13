<?php

namespace MyHordes\Fixtures\Service;

use MyHordes\Fixtures\DTO\Citizen\RoleDataContainer;
use MyHordes\Plugins\Interfaces\FixtureProcessorInterface;

class CitizenRoleDataService implements FixtureProcessorInterface {

    public function process(array &$data, ?string $tag = null): void
    {
        $container = new RoleDataContainer($data);

        $container->add()->label('Schamane')
            ->name('shaman')->vote(true)->help('shaman')
            ->commit();
        $container->add()->label('Reiseleiter in der Außenwelt')
            ->name('guide')->vote(true)->help('guide_to_the_world_beyond')
            ->commit();
        $container->add()->label('Katapult-Bediener')
            ->name('cata')->vote(true)->notShunned(true)
            ->commit();

        $container->add()->label('Ghul')
            ->name('ghoul')->hidden(true)->help('ghouls')->message('Du hast dich in einen Ghul verwandelt! Verrate es niemandem und schau in der Hilfe nach, um mehr zu erfahren.')
            ->commit();

        $data = $container->toArray();
    }
}