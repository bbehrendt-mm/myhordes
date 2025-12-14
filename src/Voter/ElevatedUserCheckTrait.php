<?php

namespace App\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

trait ElevatedUserCheckTrait
{
    protected static function userIsElevated(TokenInterface $token, AccessDecisionManagerInterface $accessDecisionManager): bool {
        return
            $accessDecisionManager->decide($token, ['ROLE_CHEATER']) ||
            $accessDecisionManager->decide($token, ['ROLE_ELEVATED']);
    }
}
