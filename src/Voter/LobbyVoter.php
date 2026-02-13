<?php

namespace App\Voter;

use App\Entity\Town;
use App\Entity\User;
use App\Enum\Capability\CapabilityEnumInterface;
use App\Enum\Capability\LobbyCapabilityEnum;
use App\Enum\Configuration\MyHordesSetting;
use App\Service\ConfMaster;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @implements CapabilityEnumInterface<LobbyCapabilityEnum>
 */
class LobbyVoter extends CapabilityVoter
{
    use ElevatedUserCheckTrait;

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) { }

    public static function getCapabilityEnum(): string
    {
        return LobbyCapabilityEnum::class;
    }

    protected function canDo(User $user, TokenInterface $token, CapabilityEnumInterface|LobbyCapabilityEnum $capability, string $attribute): bool
    {
        if (self::userIsElevated( $token, $this->accessDecisionManager )) return true;

        return match (true) {
            $attribute === 'create' && $capability === LobbyCapabilityEnum::Town =>
                $user->getAllSoulPoints() >= 25,

            $attribute === 'mayor' && $capability === LobbyCapabilityEnum::Town =>
                $user->getAllSoulPoints() >= 250,

            default => false,
        };
    }
}
