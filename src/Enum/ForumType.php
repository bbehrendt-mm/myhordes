<?php

namespace App\Enum;

enum ForumType: int {
    case Default = 0;
    case Elevated = 1;
    case Mods = 2;
    case Admins = 3;
    case Custom = 4;
    case Animac = 5;
    case Dev = 6;

    public function isInternal(): bool {
        return match($this) {
            self::Default, self::Elevated, self::Mods, self::Admins, self::Animac, self::Dev => true,
            default => false
        };
    }
}
