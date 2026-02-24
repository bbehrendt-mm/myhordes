<?php


namespace App\Structures;

class HomeDefenseSummary
{
    public int $house_defense = 0;
    public int $job_defense = 0;
    public int $job_guard_defense = 0;
    public int $upgrades_defense {
        get { return $this->upgrades_defense_base + $this->upgrades_defense_dump; }
    }
    public int $item_defense = 0;

    public int $upgrades_defense_base = 0;
    public int $upgrades_defense_dump = 0;

    public function sum(): int {
        return $this->house_defense + $this->job_defense + $this->job_guard_defense + $this->upgrades_defense + $this->item_defense;
    }
}
