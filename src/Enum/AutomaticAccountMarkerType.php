<?php

namespace App\Enum;

use App\Entity\Item;
use App\Entity\ItemPrototype;
use App\Enum\Configuration\TownSetting;
use App\Structures\TownConf;

enum AutomaticAccountMarkerType: string {
    case SuspiciousDeath = 'suspicious-death';
    case Mayor = 'mayor';

    public function delayInDays(): int {
        return match($this) {
            self::SuspiciousDeath => 14,
            self::Mayor => 10,
        };
    }

    public function limit(): int {
        return match($this) {
            self::SuspiciousDeath => 3,
            self::Mayor => 1,
        };
    }

}
