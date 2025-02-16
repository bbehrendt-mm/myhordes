<?php

namespace App\Traits\Entity;

use App\Entity\ActionCounter;
use App\Enum\ActionCounterType;
use Doctrine\Common\Collections\Collection;

trait ActionCounters
{
    abstract public function getActionCounters(): Collection;
    abstract public function addActionCounter(ActionCounter $a): self;

    public function getSpecificActionCounterValue( ActionCounterType $type, ?int $ref = null ): int {
        foreach ($this->getActionCounters() as $c)
            if ($c->getType() === $type && ($ref === null || $c->getReferenceID() === $ref)) return $c->getCount();
        return 0;
    }
    public function getSpecificActionCounter( ActionCounterType $type, ?int $ref = null ): ActionCounter {
        foreach ($this->getActionCounters() as $c)
            if ($c->getType() === $type && ($ref === null || $c->getReferenceID() === $ref)) return $c;
        $a = (new ActionCounter())->setType($type);
        if ($ref !== null) $a->setReferenceID( $ref );

        $this->addActionCounter($a);
        return $a;
    }
}