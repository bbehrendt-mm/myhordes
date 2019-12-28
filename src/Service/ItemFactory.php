<?php


namespace App\Service;


use App\Entity\Item;
use App\Entity\ItemPrototype;
use Doctrine\ORM\EntityManagerInterface;

class ItemFactory
{
    private $entity_manager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entity_manager = $em;
    }

    public function createItem( string $name, bool $broken = false, bool $poison = false ) {

        $prototype = $this->entity_manager->getRepository( ItemPrototype::class )->findOneByName( $name );
        if (!$prototype) return null;

        $item = new Item();
        $item
            ->setPrototype( $prototype )
            ->setBroken( $broken )
            ->setPoison( $poison );
        return $item;

    }

}