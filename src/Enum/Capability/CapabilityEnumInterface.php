<?php

namespace App\Enum\Capability;

interface CapabilityEnumInterface
{
    public function attributeIsValid(string $attribute): bool;

    public function validAttributes(): array;

    public static function allValidAttributes(): array;

    public static function supportsAttribute(string $attribute): bool;
}
