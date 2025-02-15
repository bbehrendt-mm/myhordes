<?php

namespace App\Enum\Game;

enum CitizenPersistentCache: string
{
    case Ghoul_Aggression = 'gh_ag';
    case Profession = 'pro';
    case ForceBaseHXP = 'base_hxp';
    case GraceSkillPoints = 'grace_pack_pts';

    public function isAccumulative(): bool {
        return match ($this) {
            CitizenPersistentCache::Ghoul_Aggression,
            CitizenPersistentCache::GraceSkillPoints => true,
            default => false
        };
    }

}
