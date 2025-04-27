<?php

namespace App\Messages\Gitlab;

use App\Entity\User;
use App\Messages\AsyncMessageLowInterface;
use Symfony\Component\Uid\Uuid;

readonly class SupportChannelPostMessage implements AsyncMessageLowInterface
{
    public function __construct(
        public ?int $user,
        public ?int $issue_id,
        public string $title,
        public ?string $body,
        public string $template,
        public array $images = [],
        public array $attachments = [],
    ) { }
}