<?php

namespace App\Enum;

enum ItemDropType: int {
    case None = -1;
    case ZoneEmptyDig = 0;
    case ZoneDig = 1;
    case ZoneEventDig = 2;

    case RuinDig = 3;

    case ERuinDig = 4;

    case TownZoneDig = 5;

    case TrashEmptyDig = 6;
    case TrashDig = 7;
}