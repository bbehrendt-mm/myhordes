<?php

namespace MyHordes\Fixtures\DTO\Actions\Atoms\Requirement;

use App\Enum\ActionCounterType;
use App\Service\Actions\Game\AtomProcessors\Require\ProcessCounterRequirement;
use MyHordes\Fixtures\DTO\Actions\RequirementsAtom;

/**
 * @method self min(?int $v)
 * @property ?int $minDay
 * @method self max(?int $v)
 * @property ?int $maxDay
 * @method self counter(?ActionCounterType $v)
 * @property ?ActionCounterType $counter
 */
class CounterRequirement extends RequirementsAtom {

    public static array $enumCasts = [
        'counter' => ActionCounterType::class
    ];

    public function getClass(): string
    {
        return ProcessCounterRequirement::class;
    }

    protected function default(string $name): mixed
    {
        return match ($name) {
            'counter' => 0,
            default => parent::default($name)
        };
    }

}