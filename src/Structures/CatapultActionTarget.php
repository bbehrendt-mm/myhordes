<?php


namespace App\Structures;

use App\Entity\Zone;

readonly class CatapultActionTarget
{
    public function __construct(
        private Zone $zone
    ) {}

    public function zone(): Zone {
        return $this->zone;
    }
}
