<?php

namespace App\Messages\Media;

use App\Entity\NotificationSubscription;
use App\Entity\User;
use App\Messages\AsyncMessageInterface;
use App\Messages\AsyncMessageLowInterface;
use Symfony\Component\Uid\Uuid;

readonly class CreateMediaVariantMessage implements AsyncMessageLowInterface
{
    public function __construct(
        public string $uuid,
        public string $variant,
        public bool $force = false
    ) { }
}
