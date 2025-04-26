<?php

namespace App\Enum;

use App\Translation\T;

enum OfficialGroupSemantic: int {

    case None   = 0;
    case Support  = 1;
    case Moderation = 2;
    case Animaction  = 3;
    case Oracle  = 4;

    public function label(): string {
        return match($this) {
            self::Support => T::__('Support', 'global'),
            self::Moderation => T::__('Moderation', 'global'),
            self::Animaction => T::__('Animation', 'global'),
            self::Oracle => T::__('Orakel', 'global'),
            default => T::__('Sonstiges', 'global'),
        };
    }

}