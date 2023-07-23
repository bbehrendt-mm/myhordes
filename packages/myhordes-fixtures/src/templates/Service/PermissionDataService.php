<?php

namespace MyHordes\Fixtures\Service;

use App\Entity\UserGroup;
use MyHordes\Plugins\Interfaces\FixtureProcessorInterface;

class PermissionDataService implements FixtureProcessorInterface {

    public function process(array &$data): void
    {
        $data = array_merge_recursive($data, [
            ['name'=>'[users]',      'type'=> UserGroup::GroupTypeDefaultUserGroup],
            ['name'=>'[elevated]',   'type'=> UserGroup::GroupTypeDefaultElevatedGroup],
            ['name'=>'[oracles]',    'type'=> UserGroup::GroupTypeDefaultOracleGroup],
            ['name'=>'[mods]',       'type'=> UserGroup::GroupTypeDefaultModeratorGroup],
            ['name'=>'[admins]',     'type'=> UserGroup::GroupTypeDefaultAdminGroup],
            ['name'=>'[animaction]', 'type'=> UserGroup::GroupTypeDefaultAnimactorGroup],
            ['name'=>'[dev]',        'type'=> UserGroup::GroupTypeDefaultDevGroup],
        ]);
    }
}