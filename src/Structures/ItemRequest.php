<?php


namespace App\Structures;


use App\Entity\Item;

class ItemRequest
{
    private string $name;
    private int $count;
    private ?bool $broken;
    private ?bool $poison;
    private bool $is_property;
    private bool $all;
    private bool $incomplete;

    public function __construct(string $name, int $count = 1, ?bool $broken = false, ?bool $poison = null, bool $is_prop = false)
    {
        $this->name = $name;
        $this->count = $count;
        $this->broken = $broken;
        $this->poison = $poison;
        $this->is_property = $is_prop;
        $this->all = false;
        $this->incomplete = false;
    }

    public function fetchAll(bool $b): self {
        $this->all = $b;
        return $this;
    }

    public function fetchIncomplete(bool $b): self {
        $this->incomplete = $b;
        return $this;
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

    public function addCount(int $c): self {
        $this->count += $c;
        return $this;
    }

    public function getAll(): bool {
        return $this->all;
    }

    public function getIncomplete(): bool {
        return $this->incomplete;
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