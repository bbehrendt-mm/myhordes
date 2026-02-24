<?php

namespace App\Structures;

use App\Entity\BuildingPrototype;

class TownWaterConsumptionSummary
{
    public int $total_required = 0;
    public int $total_consumed = 0;

    /**
     * @var array<string, WaterConsumptionSummaryItem>
     */
    private array $buildings = [];

    /**
     * Add a building and its consumption (order preserved)
     */
    public function addBuilding(BuildingPrototype $building, int $water_consumed, bool $active) {
        $this->buildings[$building->getName()] = new WaterConsumptionSummaryItem(
            $building->getName(),
            $building->getIcon(),
            $building->getLabel(),
            $water_consumed,
            $active,
        );
    }

    public function getBuilding(string $name): ?WaterConsumptionSummaryItem {
        if (isset($this->buildings[$name])) return $this->buildings[$name]; 
        return null;
    }

    public function toArray(): array {
        return [
            'total_required' => $this->total_required,
            'total_consumed' => $this->total_consumed,
            'buildings' => array_map(fn($i) => $i->toArray(), $this->buildings),
        ];
    }
}

class WaterConsumptionSummaryItem {
    public function __construct(
        public string $name,
        public string $icon,
        public string $label,
        public int $water_consumed,
        public bool $active,
    ) {}

    public function toArray(): array {
        return [
            'name' => $this->name,
            'icon' => $this->icon,
            'label' => $this->label,
            'water_consumed' => $this->water_consumed,
            'active' => $this->active,
        ];
    }
}