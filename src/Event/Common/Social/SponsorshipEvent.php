<?php

namespace App\Event\Common\Social;

use App\Event\Event;

/**
 * Fired after a new player successfully registers via a sponsor link.
 *
 * @property-read SponsorshipData $data
 * @mixin SponsorshipData
 */
class SponsorshipEvent extends Event
{
    protected static function configuration(): string
    {
        return SponsorshipData::class;
    }
}
