<?php

namespace MyHordes\Fixtures\DTO\Actions\Atoms\Requirement;

use App\Service\Actions\Game\AtomProcessors\Require\ProcessSourceRequirement;
use MyHordes\Fixtures\DTO\Actions\RequirementsAtom;

/**
 * @method self essential(?bool $v)
 * @property ?bool $essential
 */
class SourceRequirement extends RequirementsAtom {
    public function getClass(): string
    {
        return ProcessSourceRequirement::class;
    }
}