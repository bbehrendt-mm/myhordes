<?php


namespace App\Service;


use App\Entity\CitizenStatus;
use Doctrine\ORM\EntityManagerInterface;

class StatusFactory
{
    private $entity_manager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entity_manager = $em;
    }

    public function createStatus( string $name ) {
        return $this->entity_manager->getRepository( CitizenStatus::class )->findOneByName( $name );
    }

}