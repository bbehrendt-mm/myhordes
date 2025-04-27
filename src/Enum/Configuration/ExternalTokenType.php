<?php

namespace App\Enum\Configuration;

enum ExternalTokenType: string
{
    case GitlabApiToken = 'gitlab-api';

    public function canImport(): bool {
        return true;
    }

    public function canRenew(): bool {
        return true;
    }

}