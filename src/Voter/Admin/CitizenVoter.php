<?php

namespace App\Voter\Admin;

use App\Entity\Citizen;
use App\Entity\User;
use App\Enum\Configuration\MyHordesSetting;
use App\Service\ConfMaster;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CitizenVoter extends Voter
{

    public function __construct(
        private readonly ConfMaster $confMaster,
        private readonly KernelInterface $kernel,
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {

    }

    public function supportsAttribute(string $attribute): bool
    {
        return in_array($attribute, [
            'edit', 'administrate','sudo'
        ], true);
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Citizen::class, true);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return
            $this->supportsAttribute( $attribute ) &&
            is_a($subject, Citizen::class, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var User $user */
        $user = $token->getUser();
        if (!$user instanceof User) return false;

        $lax = $this->confMaster->getGlobalConf()->get( MyHordesSetting::StagingSettingsEnabled ) ||
            $this->kernel->getEnvironment() === 'dev' || $this->kernel->getEnvironment() === 'local';

        return match ($attribute) {
            'edit' => $this->canEdit( $subject, $user, $token, $lax ),
            'administrate' => $this->canAdministrate( $subject, $user, $token, $lax ),
            'sudo' => $this->canDoEverything( $token ),
            default => false,
        };
    }

    private function canEdit(Citizen $citizen, User $user, TokenInterface $token, bool $lax): bool {
        return match(true) {
            $this->accessDecisionManager->decide($token, ['ROLE_SUB_ADMIN']),
            => true,
            $this->accessDecisionManager->decide($token, ['ROLE_CHEATER']),
            => $lax && $citizen->getTown()->userInTown( $user ),

            default => false,
        };
    }

    private function canAdministrate(Citizen $citizen, User $user, TokenInterface $token, bool $lax): bool {
        return match(true) {
            $this->accessDecisionManager->decide($token, ['ROLE_SUB_ADMIN']),
                => true,
            $this->accessDecisionManager->decide($token, ['ROLE_CHEATER']),
                => $lax && $citizen->getUser()->getId() === $user->getId(),

            default => false,
        };
    }

    private function canDoEverything(TokenInterface $token,): bool {
        return match(true) {
            $this->accessDecisionManager->decide($token, ['ROLE_SUB_ADMIN']),
                => true,
            default => false,
        };
    }
}