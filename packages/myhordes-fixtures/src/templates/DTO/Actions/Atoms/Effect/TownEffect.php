<?php

namespace MyHordes\Fixtures\DTO\Actions\Atoms\Effect;

use App\Service\Actions\Game\AtomProcessors\Effect\ProcessTownEffect;
use MyHordes\Fixtures\DTO\Actions\EffectAtom;

/**
 * @property-read ?int wellMin
 * @property-read ?int wellMax
 * @property-read ?int unlockBlueprintType
 * @property-read ?string unlockBlueprintNamedList
 * @property-read ?array unlockBlueprintList
 * @method self soulDefense(?int $v)
 * @property ?int soulDefense
 * @method
 */
class TownEffect extends EffectAtom {
    public function getClass(): string
    {
        return ProcessTownEffect::class;
    }

    public function well(int $min, ?int $max = null): self {
        $this->wellMin = $min;
        $this->wellMax = $max ?? $min;
        return $this;
    }

    public function unlockBlueprint(int|array|string $type): self {
        $this->unlockBlueprintType      = is_int( $type )    ? $type : null;
        $this->unlockBlueprintNamedList = is_string( $type ) ? $type : null;
        $this->unlockBlueprintList      = is_array( $type )  ? $type : null;
        return $this;
    }

    public function hasWellEffect(): bool {
        return $this->wellMin !== 0 || $this->wellMax !== 0;
    }

    public function unlocksBlueprint(): bool {
        return $this->unlockBlueprintType !== null || $this->unlockBlueprintList !== null || $this->unlockBlueprintNamedList !== null;
    }

    protected function default(string $name): mixed {
        return match($name) {
            'wellMin', 'wellMax', 'soulDefense' => 0,
            default => null
        };
    }

}
