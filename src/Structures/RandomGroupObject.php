<?php


namespace App\Structures;

use Adbar\Dot;
use App\Enum\Configuration\Configuration;
use App\Interfaces\RandomGroup;
use ArrayHelpers\Arr;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class RandomGroupObject implements RandomGroup
{

    public function __construct(private ArrayCollection $entries = new ArrayCollection())
    {}

    public function getEntries(): Collection
    {
        return $this->entries;
    }
}
