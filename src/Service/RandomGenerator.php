<?php


namespace App\Service;


use App\Entity\AffectResultGroup;
use App\Entity\AffectResultGroupEntry;
use App\Entity\Citizen;
use App\Entity\CitizenHome;
use App\Entity\CitizenProfession;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\ItemGroup;
use App\Entity\ItemGroupEntry;
use App\Entity\ItemPrototype;
use App\Entity\Result;
use App\Entity\Town;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\WellCounter;
use App\Structures\ItemRequest;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

class RandomGenerator
{

    function chance(float $c): bool {
        if ($c >= 1.0)     return true;
        elseif ($c <= 0.0) return false;
        return mt_rand(0,100) <= (100*$c);
    }

    /**
     * @param array $a
     * @param int $num
     * @param bool $force_array
     * @return mixed|array|null
     */
    function pick( array $a, int $num = 1, bool $force_array = false ) {
        if     ($num <=  0) return $force_array ? [] : null;
        elseif ($num === 1) return $force_array ? [$a[ array_rand($a, 1) ]] : $a[ array_rand($a, 1) ];
        else return array_map( function($k) use (&$a) { return $a[$k]; }, array_rand( $a, min($num,count($a)) ) );
    }

    function pickItemPrototypeFromGroup(ItemGroup $g): ?ItemPrototype {
        if (!$g->getEntries()->count()) return null;
        $sum = 0;
        foreach ( $g->getEntries() as $entry )
            $sum += abs($entry->getChance());
        if ($sum === 0) {
            /** @var ItemGroupEntry $pe */
            $pe = $this->pick( $g->getEntries()->getValues() );
            return $pe->getPrototype();
        }
        $random = mt_rand(0,$sum-1);
        $sum = 0;
        foreach ( $g->getEntries() as $entry ) {
            $sum += abs($entry->getChance());
            if ($sum > $random) return $entry->getPrototype();
        }
        return $g->getEntries()->last()->getPrototype();
    }

    /**
     * @param AffectResultGroup $g
     * @return Result[]
     */
    function pickResultsFromGroup(AffectResultGroup $g): ?array {
        if (!$g->getEntries()->count()) return null;
        $sum = 0;
        foreach ( $g->getEntries() as $entry )
            $sum += abs($entry->getCount());
        if ($sum === 0) {
            /** @var AffectResultGroupEntry $pe */
            $pe = $this->pick( $g->getEntries()->getValues() );
            return $pe->getResults()->getValues();
        }
        $random = mt_rand(0,$sum-1);
        $sum = 0;
        foreach ( $g->getEntries() as $entry ) {
            $sum += abs($entry->getCount());
            if ($sum > $random) return $entry->getResults()->getValues();
        }
        return $g->getEntries()->last()->getResults()->getValues();
    }

}