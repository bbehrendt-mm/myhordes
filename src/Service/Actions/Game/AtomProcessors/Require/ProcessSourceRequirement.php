<?php

namespace App\Service\Actions\Game\AtomProcessors\Require;

use App\Structures\ActionHandler\Evaluation;
use MyHordes\Fixtures\DTO\Actions\Atoms\Requirement\SourceRequirement;
use MyHordes\Fixtures\DTO\Actions\RequirementsAtom;

class ProcessSourceRequirement extends AtomRequirementProcessor
{
    public function __invoke(Evaluation $cache, RequirementsAtom|SourceRequirement $data): bool
    {
        if ($data->essential !== null && $cache->item->getEssential() !== $data->essential) return false;

        return true;
    }
}