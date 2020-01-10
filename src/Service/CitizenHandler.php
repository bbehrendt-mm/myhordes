<?php


namespace App\Service;


use App\Entity\Citizen;
use App\Entity\CitizenHome;
use App\Entity\CitizenProfession;
use App\Entity\CitizenStatus;
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
    private $random_generator;

    public function __construct( EntityManagerInterface $em, StatusFactory $sf, RandomGenerator $g)
    {
        $this->entity_manager = $em;
        $this->status_factory = $sf;
        $this->random_generator = $g;
    }

    public function isWounded(Citizen $citizen) {
        $wounds = ['wound1','wound2','wound3','wound4','wound5','wound6'];
        foreach ($citizen->getStatus() as $status)
            if (in_array($status->getName(), $wounds)) return true;
        return false;
    }

    public function inflictWound( Citizen &$citizen ) {
        $s = [];
        foreach ($citizen->getStatus() as $status)
            $s[] = $status->getName();
        $wounds = array_filter(['wound1','wound2','wound3','wound4','wound5','wound6'], function(string $w) use (&$s) {
            return !in_array($w,$s);
        });
        if (empty($wounds)) return;
        $citizen->addStatus( $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName( $this->random_generator->pick( $wounds ) ) );
    }

    public function isTired(Citizen $citizen) {
        foreach ($citizen->getStatus() as $status)
            if ($status->getName() === 'tired') return true;
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

    public function getCP(Citizen &$citizen): int {
        return $citizen->getProfession()->getName() == 'guardian' ? 4 : 2;
    }
}