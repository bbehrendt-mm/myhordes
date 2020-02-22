<?php


namespace App\Structures;

class TownDefenseSummary
{
    public $base_defense = 10;
    public $house_defense = 0;
    public $guardian_defense = 0;
    public $building_defense = 0;
    public $item_defense = 0;

    public function sum(): int {
        return $this->base_defense + $this->house_defense + $this->guardian_defense + $this->building_defense + $this->item_defense;
    }
}