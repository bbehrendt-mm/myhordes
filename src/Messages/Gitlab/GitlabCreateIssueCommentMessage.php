<?php

namespace App\Messages\Gitlab;

use App\Entity\NotificationSubscription;
use App\Entity\User;
use App\Messages\AsyncMessageInterface;
use App\Messages\AsyncMessageLowInterface;
use Symfony\Component\Uid\Uuid;

readonly class GitlabCreateIssueCommentMessage implements AsyncMessageLowInterface
{
    public function __construct(
        public ?int $owner,
        public int $issue_id,
        public string $description,
        public bool $confidential = true,
    ) { }
}