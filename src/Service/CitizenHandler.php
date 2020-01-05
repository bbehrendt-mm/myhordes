<?php


namespace App\Service;


use App\Entity\Citizen;
use App\Entity\CitizenHome;
use App\Entity\CitizenProfession;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\Town;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\WellCounter;
use App\Structures\ItemRequest;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

class CitizenHandler
{
    private $entity_manager;
    private $status_factory;

    public function __construct( EntityManagerInterface $em, StatusFactory $sf)
    {
        $this->entity_manager = $em;
        $this->status_factory = $sf;
    }

    public function isWounded(Citizen $citizen) {
        $wounds = ['wound1','wound2','wound3','wound4','wound5','wound6'];
        foreach ($citizen->getStatus() as $status)
            if (in_array($status->getName(), $wounds)) return true;
        return false;
    }

    public function getMaxAP(Citizen $citizen) {
        return $this->isWounded($citizen) ? 5 : 6;
    }

    public function setAP(Citizen &$citizen, bool $relative, int $num) {
        $citizen->setAp( $relative ? ($citizen->getAp() + $num) : max(0,$num) );
        if ($citizen->getAp() == 0) $citizen->addStatus( $this->status_factory->createStatus( 'tired' ) );
        else $citizen->removeStatus( $this->status_factory->createStatus( 'tired' ) );
    }
}