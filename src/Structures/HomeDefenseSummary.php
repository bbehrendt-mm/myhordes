<?php


namespace App\Structures;

class HomeDefenseSummary
{
    public $house_defense = 0;
    public $upgrades_defense = 0;
    public $item_defense = 0;

    public function sum(): int {
        return $this->house_defense + $this->upgrades_defense + $this->item_defense;
    }
}