<?php


namespace App\Structures;


use App\Entity\Item;

class BankItem
{
    private $item;
    private $count;

    public function __construct(Item $item, int $count = 1)
    {
        $this->item = $item;
        $this->count = $count;
    }

    public function getItem(): Item {
        return $this->item;
    }

    public function getCount(): int {
        return $this->count;
    }
}