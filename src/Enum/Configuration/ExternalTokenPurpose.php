<?php

namespace App\Enum\Configuration;

enum ExternalTokenPurpose: string
{
    case ErrorReporting = 'error-reporting';
    case ModerationReporting = 'mod-reporting';
    case EventReporting = 'event-reporting';

    public function isUnique(): bool {
        return false;
    }

    public function isValidForType( ExternalTokenType $type ): bool {
        return $type === ExternalTokenType::DiscordWebhook;
    }

}
