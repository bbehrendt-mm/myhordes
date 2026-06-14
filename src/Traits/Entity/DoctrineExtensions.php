<?php

namespace App\Traits\Entity;

trait DoctrineExtensions
{
    private static ?array $trait_cache = null;
    private static function doctrineTraits(): array {
        if (static::$trait_cache !== null) return static::$trait_cache;

        $discovered = [];

        $handled = [];
        $pending = [static::class, ...class_parents( static::class )];

        while ($class = array_pop($pending)) {
            if (in_array($class, $handled)) continue;

            $traits = class_uses($class);
            $discovered = array_merge($discovered, $traits);
            $pending = array_merge($pending, $traits);

            $handled[] = $class;
        }

        return static::$trait_cache = array_values( array_filter( array_unique($discovered), fn(string $trait) =>
            in_array(DoctrineExtension::class, class_uses($trait))
        ) );
    }

    /**
     * @param class-string $trait
     * @return bool
     */
    public static function usesDoctrineTrait(string $trait): bool {
        return in_array($trait, static::doctrineTraits());
    }
}
