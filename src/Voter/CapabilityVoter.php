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
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @template T of CapabilityEnumInterface
 */
abstract class CapabilityVoter extends Voter
{
    /**
     * @return class-string<CapabilityEnumInterface>
     */
    abstract public static function getCapabilityEnum(): string;

    public function supportsAttribute(string $attribute): bool
    {
        return static::getCapabilityEnum()::supportsAttribute( $attribute );
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, static::getCapabilityEnum(), true);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return
            $this->supportsAttribute( $attribute ) &&
            is_a($subject, static::getCapabilityEnum()) &&
            $subject->attributeIsValid( $attribute );
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var User $user */
        $user = $token->getUser();
        if (!$user instanceof User) return false;

        return $this->canDo( $user, $token, $subject, $attribute );
    }

    /**
     * Determines if a user has the capability to perform an action based on the given token and attribute.
     *
     * @param User $user The user instance being checked.
     * @param TokenInterface $token The security token representing the user's authentication state.
     * @param T $capability The capability being verified.
     * @param string $attribute The specific attribute being checked for the action.
     *
     * @return bool Whether the user possesses the required capability.
     */
    abstract protected function canDo(User $user, TokenInterface $token, CapabilityEnumInterface $capability, string $attribute): bool;
}
