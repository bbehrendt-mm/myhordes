<?php

namespace App\Event\Game\Town\Addon\Dump;

use App\Entity\Item;
use App\Entity\ItemPrototype;

class DumpInsertionCheckData
{
    use DumpTrait;
    public ?ItemPrototype $consumable;
    public int $quantity = 0;
    public bool $to_home = false;
	public array $dumpableItems;

	/**
	 * @param ItemPrototype $item
	 * @return DumpInsertionCheckData
	 * @noinspection PhpDocSignatureInspection
	 */
	public function setup( ?ItemPrototype $item = null, int $quantity = 0, bool $to_home = false ): void {
		$this->consumable = $item;
		$this->quantity = $quantity;
        $this->to_home = $to_home;
		$this->dumpableItems = [];
	}

}
