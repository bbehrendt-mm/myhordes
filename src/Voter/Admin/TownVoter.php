<?php

namespace App\Voter\Admin;

use App\Entity\Town;
use App\Entity\User;
use App\Enum\Configuration\MyHordesSetting;
use App\Service\ConfMaster;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TownVoter extends Voter
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
            'list', 'create',
            'spy','edit','cheat','administrate','sudo'
        ], true);
    }

    public function supportsType(string $subjectType): bool
    {
        return
            $subjectType === 'string' ||
            is_a($subjectType, Town::class, true);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return
            $this->supportsAttribute( $attribute ) &&
            is_a($subject, Town::class, true) &&
            ( in_array($attribute, ['list','create']) || !is_string( $subject ) );
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var User $user */
        $user = $token->getUser();
        if (!$user instanceof User) return false;

        $lax = $this->confMaster->getGlobalConf()->get( MyHordesSetting::StagingSettingsEnabled ) ||
            $this->kernel->getEnvironment() === 'dev' || $this->kernel->getEnvironment() === 'local';

        return match ($attribute) {
            'create' => $this->canCreate( $token, $lax ),
            'list' => $this->canList( $token, $lax ),
            'spy' => $this->canSpy( $token, $lax ),
            'cheat' => $this->canCheat( $subject, $token, $user, $lax ),
            'edit' => $this->canEdit( $subject, $token, $user, $lax ),
            'administrate' => $this->canAdministrate( $token ),
            'sudo' => $this->canDoEverything( $token ),
            default => false,
        };
    }

    private function canCreate(TokenInterface $token, bool $lax): bool {
        return match(true) {
            $this->accessDecisionManager->decide($token, ['ROLE_CROW']),
            $this->accessDecisionManager->decide($token, ['ROLE_SUB_ADMIN']),
            => true,
            $this->accessDecisionManager->decide($token, ['ROLE_CHEATER']),
            => $lax,
            default => false,
        };
    }

    private function canList(TokenInterface $token, bool $lax): bool {
        return match(true) {
            $this->accessDecisionManager->decide($token, ['ROLE_CROW']),
            $this->accessDecisionManager->decide($token, ['ROLE_SUB_ADMIN']),
                => true,
            $this->accessDecisionManager->decide($token, ['ROLE_CHEATER']),
                => $lax,
            default => false,
        };
    }

    private function canSpy(TokenInterface $token, bool $lax): bool {
        return match(true) {
            $this->accessDecisionManager->decide($token, ['ROLE_CROW']),
            $this->accessDecisionManager->decide($token, ['ROLE_SUB_ADMIN']),
                => true,
            $this->accessDecisionManager->decide($token, ['ROLE_CHEATER']),
                => $lax,
            default => false,
        };
    }

    private function canEdit(Town $town, TokenInterface $token, User $user, bool $lax): bool {
        return match(true) {
            $this->accessDecisionManager->decide($token, ['ROLE_CROW']),
            $this->accessDecisionManager->decide($token, ['ROLE_SUB_ADMIN']),
                => true,
            $this->accessDecisionManager->decide($token, ['ROLE_CHEATER']),
                => $lax && $town->userInTown( $user ),
            default => false,
        };
    }

    private function canCheat(Town $town, TokenInterface $token, User $user, bool $lax): bool {
        return match(true) {
            $this->accessDecisionManager->decide($token, ['ROLE_SUB_ADMIN']),
            => true,
            $this->accessDecisionManager->decide($token, ['ROLE_CROW'])
            => $lax,
            $this->accessDecisionManager->decide($token, ['ROLE_CHEATER']),
            => $lax && $town->userInTown( $user ),
            default => false,
        };
    }

    private function canAdministrate(TokenInterface $token,): bool {
        return match(true) {
            $this->accessDecisionManager->decide($token, ['ROLE_SUB_ADMIN']),
                => true,
            default => false,
        };
    }

    private function canDoEverything(TokenInterface $token,): bool {
        return match(true) {
            $this->accessDecisionManager->decide($token, ['ROLE_ADMIN']),
            => true,
            default => false,
        };
    }
}