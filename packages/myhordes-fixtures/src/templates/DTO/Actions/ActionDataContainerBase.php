<?php

namespace MyHordes\Fixtures\DTO\Actions;

use BackedEnum;
use MyHordes\Fixtures\DTO\Container;
use MyHordes\Fixtures\DTO\ElementInterface;

/**
 * @method ActionDataElementBase[] all()
 * @method ActionDataElementBase add()
 * @method ActionDataElementBase clone(string $id)
 * @method ActionDataElementBase modify(string $id, bool $required = true)
 */
abstract class ActionDataContainerBase extends Container
{
    abstract protected function getElementClass(): string;

    protected function store(ElementInterface|ActionDataElementBase $child, mixed $context = null): string
    {
        if ($context === null && $this->has( $child->identifier )) throw new \Exception("Duplicate identifier '{$child->identifier}'");
        elseif ($context !== null && $context !== $child->identifier) throw new \Exception("Forbidden attempt to change identifier ('$context' > '{$child->identifier}'");
        parent::store( $child, $context ?? $child->identifier );
        return $context ?? $child->identifier;
    }

    protected function findAtom(string $class): array {
        $r = [];
        foreach ( $this->all() as $de )
            foreach ($de->atomList as $atom)
                if (is_a( $atom, $class ))
                    $r[] = $atom;
        return $r;
    }

    /**
     * Retrieves a unique array of properties injected into the data structure
     * that belong to the specified class type.
     *
     * @template T of BackedEnum
     * @psalm-param class-string<T> $propClass The class name to filter the injected properties by. Defaults to BackedEnum::class.
     *
     * @return T[] An array of unique properties matching the specified class type.
     */
    public function injectedProperties(string $propClass = BackedEnum::class): array {
        $result = [];
        foreach ( $this->all() as $de )
            foreach ($de->atomList as $atom)
                $result = [...$result, ...$atom->injectedProperties( $propClass )];
        return array_map(fn(mixed $v) => $propClass::from($v), array_unique( array_map(fn(mixed $v) => $v->value, $result )));
    }
}