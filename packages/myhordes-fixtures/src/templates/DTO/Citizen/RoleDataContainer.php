<?php

namespace MyHordes\Fixtures\DTO\Citizen;

use MyHordes\Fixtures\DTO\Container;
use MyHordes\Fixtures\DTO\ElementInterface;

/**
 * @method RoleDataElement[] all()
 * @method RoleDataElement add()
 * @method RoleDataElement clone(string $id)
 * @method RoleDataElement modify(string $id, bool $required = true)
 */
class RoleDataContainer extends Container
{
    protected function getElementClass(): string
    {
        return RoleDataElement::class;
    }

    protected function store(ElementInterface|RoleDataElement $child, mixed $context = null): string
    {
        return parent::store( $child, $context ?? $child->name );
    }
}