<?php


namespace App\Structures;

use Adbar\Dot;
use App\Entity\ItemPrototype;
use App\Enum\Configuration\Configuration;
use App\Interfaces\RandomEntry;
use App\Interfaces\RandomGroup;
use ArrayHelpers\Arr;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class RandomGroupObjectEntry implements RandomEntry
{

    public function __construct(private ItemPrototype $i, private int $c)
    {}

    public function getPrototype(): ?ItemPrototype {
        return $this->i;
    }

    public function getChance(): ?int
    {
        return $this->c;
    }
}
