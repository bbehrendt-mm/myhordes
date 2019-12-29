<?php


namespace App\Structures;


use App\Entity\Item;

class ItemRequest
{
    private $itemPrototypeName;
    private $count;
    private $broken;
    private $poison;

    public function __construct(string $name, int $count = 1, ?bool $broken = false, ?bool $poison = false)
    {
        $this->itemPrototypeName = $name;
        $this->count = $count;
        $this->broken = $broken;
        $this->poison = $poison;
    }

    public function getItemPrototypeName(): string {
        return $this->itemPrototypeName;
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