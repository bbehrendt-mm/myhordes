<?php

namespace App\Messages\Gitlab;

use App\Entity\NotificationSubscription;
use App\Entity\User;
use App\Messages\AsyncMessageInterface;
use App\Messages\AsyncMessageLowInterface;
use Symfony\Component\Uid\Uuid;

readonly class GitlabCreateIssueMessage implements AsyncMessageLowInterface
{
    public function __construct(
        public int $owner,
        public string $title,
        public string $description,
        public string $issue_type = 'issue',
        public bool $confidential = true,
        public array $trusted_info = [],
        public array $passed_info = [],
        public array $attachments = [],
    ) { }
}