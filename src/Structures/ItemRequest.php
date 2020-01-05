<?php


namespace App\Structures;


use App\Entity\Item;

class ItemRequest
{
    private $name;
    private $count;
    private $broken;
    private $poison;
    private $is_property;

    public function __construct(string $name, int $count = 1, ?bool $broken = false, ?bool $poison = false, bool $is_prop = false)
    {
        $this->name = $name;
        $this->count = $count;
        $this->broken = $broken;
        $this->poison = $poison;
        $this->is_property = $is_prop;
    }

    public function isProperty(): bool {
        return $this->is_property;
    }

    public function getItemPrototypeName(): ?string {
        return $this->is_property ? null : $this->name;
    }

    public function getItemPropertyName(): ?string {
        return $this->is_property ? $this->name : null;
    }

    public function getCount(): int {
        return $this->count;
    }

    public function getBroken(): bool {
        return $this->broken;
    }

    public function getPoison(): bool {
        return $this->poison;
    }

    public function filterBroken(): bool {
        return $this->broken !== null;
    }

    public function filterPoison(): bool {
        return $this->poison !== null;
    }
}