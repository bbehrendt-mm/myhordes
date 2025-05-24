<?php

namespace App\Enum\Configuration;

trait PropertyComparisonTrait
{
    /**
     * @template T of int|string|array|bool
     * @param T|null $a
     * @param T|null $b
     * @return int
    */
    protected static function defaultCompare(mixed $a, mixed $b): int {
        return match(true) {
            is_int($a) && is_int($b) => $a <=> $b,
            is_bool($a) && is_bool($b) => ($a ? 1 : 0) <=> ($b ? 1 : 0),
            is_array($a) && is_array($b) => count($a) <=> count($b),
            is_null($a) && !is_null($b) => -1,
            !is_null($a) && is_null($b) =>  1,
            default => 0
        };
    }

    /**
     * @template T of int|string|array|bool
     * @param Configuration $property
     * @param T $a
     * @param T $b
     * @return int
     */
    public static function sort( Configuration $property, mixed $a, mixed $b ): int
    {
        return self::defaultCompare( $a, $b );
    }

    /**
     * @template T of int|string|array|bool
     * @param T|null $a
     * @param T|null $b
     * @return int
     */
    public function compare(mixed $a, mixed $b): int {
        return self::defaultCompare( $a, $b );
    }
}