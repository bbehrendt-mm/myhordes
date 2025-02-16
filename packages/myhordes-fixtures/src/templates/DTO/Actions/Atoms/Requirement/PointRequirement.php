<?php

namespace MyHordes\Fixtures\DTO\Actions\Atoms\Requirement;

use App\Enum\ActionHandler\PointType;
use App\Service\Actions\Game\AtomProcessors\Require\ProcessPointRequirement;
use MyHordes\Fixtures\DTO\Actions\RequirementsAtom;

/**
 * @method self require(PointType $v)
 * @property PointType $require
 * @method self min(?int $v)
 * @property ?int $min
 * @method self max(?int $v)
 * @property ?int $max
 * @method self fromLimit(?bool $v = true)
 * @property ?bool $fromLimit
 */
class PointRequirement extends RequirementsAtom {

    public static array $enumCasts = [
        'require' => PointType::class,
    ];

    public function getClass(): string
    {
        return ProcessPointRequirement::class;
    }

    public function __call(string $name, array $arguments): self
    {
        if (($name === 'fromLimit') && count($arguments) === 0) return parent::__call( $name, [true] );
        else return parent::__call( $name, $arguments );
    }

    protected function default(string $name): mixed
    {
        return match ($name) {
            'require' => PointType::AP,
            default => parent::default($name)
        };
    }
}