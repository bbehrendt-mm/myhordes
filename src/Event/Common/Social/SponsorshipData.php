<?php

namespace App\Event\Common\Social;

use App\Entity\User;

/**
 * Data payload for a new sponsorship registration.
 *
 * @property-read User $sponsor  The existing player whose refer-link was used.
 * @property-read User $newcomer The newly registered player.
 */
readonly class SponsorshipData
{
    public User $sponsor;
    public User $newcomer;

    /**
     * @param User $sponsor  The existing player whose refer-link was used.
     * @param User $newcomer The newly registered player.
     */
    public function setup(User $sponsor, User $newcomer): void
    {
        $this->sponsor  = $sponsor;
        $this->newcomer = $newcomer;
    }
}
