<?php

namespace App\Enum\Capability;

trait CapabilityEnumTrait
{
    abstract public function validAttributes(): array;

    public function attributeIsValid(string $attribute): bool {
        return in_array($attribute, $this->validAttributes());
    }

    public static function allValidAttributes(): array {
        return array_unique(array_merge(...array_map(fn($c) => $c->validAttributes(), self::cases())));
    }

    public static function supportsAttribute(string $attribute): bool {
        return in_array( $attribute, self::allValidAttributes() );
    }
}
