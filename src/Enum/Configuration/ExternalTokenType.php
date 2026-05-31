<?php

namespace App\Enum\Configuration;

enum ExternalTokenType: string
{
    case GitlabApiToken = 'gitlab-api';
    case DiscordWebhook = 'discord-webhook';

    public function canImport(): bool {
        return true;
    }

    public function canExpire(): bool {
        return $this === self::GitlabApiToken;
    }

    public function canRenew(): bool {
        return $this === self::GitlabApiToken;
    }

    public function isUnique(): bool {
        return $this === self::GitlabApiToken;
    }

}
