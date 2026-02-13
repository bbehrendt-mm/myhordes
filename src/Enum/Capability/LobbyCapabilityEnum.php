<?php

namespace App\Enum\Capability;

enum LobbyCapabilityEnum implements CapabilityEnumInterface
{
    use CapabilityEnumTrait;

    case Town;

    public function validAttributes(): array
    {
        return match ($this) {
            self::Town => ['create', 'mayor'],
        };
    }
}
