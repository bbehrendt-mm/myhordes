<?php

namespace App\Voter\Admin;

use App\Entity\Town;
use App\Entity\TownAdminUser;
use App\Entity\User;
use App\Enum\Configuration\MyHordesSetting;
use App\Service\ConfMaster;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class TownVoter extends Voter
{

    public function __construct(
        private readonly ConfMaster $confMaster,
        private readonly KernelInterface $kernel,
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly TagAwareCacheInterface $gameCachePool,
        private readonly EntityManagerInterface $em,
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

    protected function attributesByTownSettings(User $user, Town $town): array {
        $key = "town_admin_list_{$user->getId()}_{$town->getId()}";

        return $this->gameCachePool->get($key, function (ItemInterface $item) use ($town, $user) {
            $item->expiresAfter(86400)
                ->tag([
                    'town_al',
                    "town_al_user_{$user->getId()}", "town_al_town_{$town->getId()}",
                    "town_al_{$user->getId()}_{$town->getId()}"
                ]);

            $adminSetting = $this->em->getRepository(TownAdminUser::class)->findOneBy( ['town' => $town, 'user' => $user ]);
            if (!$adminSetting) return [];
            return $adminSetting->isFullAccess() ? ['spy', 'edit', 'cheat'] : ['spy'];
        });
    }

    protected function allowedByTownSettings(User $user, Town $town, string $attribute): bool {
        return in_array($attribute, $this->attributesByTownSettings( $user, $town ));
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return
            $this->supportsAttribute( $attribute ) &&
            is_a($subject, Town::class, true) &&
            ( in_array($attribute, ['list','create']) || !is_string( $subject ) );
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var User $user */
        $user = $token->getUser();
        if (!$user instanceof User) return false;

        $lax = $this->confMaster->getGlobalConf()->get( MyHordesSetting::StagingSettingsEnabled ) ||
            $this->kernel->getEnvironment() === 'dev' || $this->kernel->getEnvironment() === 'local';

        return match ($attribute) {
            'create' => $this->canCreate( $token, $lax ),
            'list' => $this->canList( $token, $lax ) || $this->em->getRepository(TownAdminUser::class)->count( ['user' => $user ] ) > 0,
            'spy' => $this->canSpy( $token, $lax ),
            'cheat' => $this->canCheat( $subject, $token, $user, $lax ),
            'edit' => $this->canEdit( $subject, $token, $user, $lax ),
            'administrate' => $this->canAdministrate( $token ),
            'sudo' => $this->canDoEverything( $token ),
            default => false,
        } || (is_a($subject, Town::class) && $this->allowedByTownSettings( $user, $subject, $attribute ));
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
