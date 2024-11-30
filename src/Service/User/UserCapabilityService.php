<?php

namespace App\Service\User;

use App\Entity\OfficialGroup;
use App\Entity\User;
use App\EventListener\ContainerTypeTrait;
use App\Service\PermissionHandler;
use App\Service\UserHandler;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserCapabilityService implements ServiceSubscriberInterface
{
    use ContainerTypeTrait;
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public static function getSubscribedServices(): array
    {
        return [
            EntityManagerInterface::class,
            PermissionHandler::class,
            RoleHierarchyInterface::class,
            UserHandler::class,
            UrlGeneratorInterface::class,
            TranslatorInterface::class
        ];
    }

    /**
     * @return OfficialGroup[]
     */
    public function getOfficialGroups(User $user): array {
        return array_filter(
            $this->getService(EntityManagerInterface::class)->getRepository(OfficialGroup::class)->findAll(),
            fn(OfficialGroup $o) => $this->getService(PermissionHandler::class)->userInGroup($user, $o->getUsergroup())
        );
    }

    /**
     * Returns the full list of roles a user has. If $allow_inheritance_from_pivot is set to true, merges that list with
     * the roles of all other users the given user is the principal pivot for.
     * @param User $user
     * @param bool $allow_inheritance_from_pivot
     * @return string[]
     */
    public function getEffectiveRoles( User $user, bool $allow_inheritance_from_pivot = false ): array {
        $roles = $this->getService(RoleHierarchyInterface::class)->getReachableRoleNames( $user->getRoles() );
        if ($allow_inheritance_from_pivot) foreach ($this->getService(UserHandler::class)->getAllPivotUserRelationsFor( $user, true, false ) as $pivot)
            $roles = [...$roles, ...$this->getService(RoleHierarchyInterface::class)->getReachableRoleNames( $pivot->getRoles() )];

        return array_values( array_unique( $roles ) );
    }

    /**
     * Returns true if the given user has the provided role. Optionally includes the roles of all secondary pivot users
     * in the check.
     * @param User $user User to check
     * @param string $role Role to check
     * @param bool $allow_inheritance_from_pivot Set to true to include secondary pivots.
     * @return bool True if the user has the given role, otherwise false.
     */
    public function hasRole( User $user, string $role, bool $allow_inheritance_from_pivot = false ): bool {
        return in_array( $role, $this->getEffectiveRoles( $user, $allow_inheritance_from_pivot ) );
    }

    /**
     * Returns true if the given user has at least one of provided roles. Optionally includes the roles of all secondary
     * pivot users in the check.
     * @param User $user User to check
     * @param string[] $roles Roles to check. If this array is empty, false is returned.
     * @param bool $allow_inheritance_from_pivot Set to true to include secondary pivots.
     * @return bool True if the user has at least one of given roles, otherwise false.
     */
    public function hasAnyRole( User $user, array $roles, bool $allow_inheritance_from_pivot = false ): bool {
        return count(array_intersect( $roles, $this->getEffectiveRoles( $user, $allow_inheritance_from_pivot ) )) > 0;
    }

    /**
     * Returns true if the given user has at all of provided roles. Optionally includes the roles of all secondary
     * pivot users in the check.
     * @param User $user User to check
     * @param string[] $roles Roles to check. If this array is empty, true is returned.
     * @param bool $allow_inheritance_from_pivot Set to true to include secondary pivots.
     * @return bool True if the user has all of given roles, otherwise false.
     */
    public function hasAllRoles( User $user, array $roles, bool $allow_inheritance_from_pivot = false ): bool {
        $roles = array_values( array_unique( $roles ) );
        return count(array_intersect( $roles, $this->getEffectiveRoles( $user, $allow_inheritance_from_pivot ) )) === count($roles);
    }

    /**
     * @param User $user
     * @param int $referenceType
     * @return string[]
     */
    public function adminTools( User $user, int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL ): array {

        $trans = $this->getService(TranslatorInterface::class);
        $url = $this->getService(UrlGeneratorInterface::class);

        if ( $this->hasRole($user, 'ROLE_CROW') )
            return [
                $trans->trans('Dashboard', [], 'admin')    => $url->generate('admin_dashboard', referenceType: $referenceType),
                $trans->trans('Kampagnen', [], 'admin')    => $url->generate('admin_campaigns', referenceType: $referenceType),
                $trans->trans('Users', [], 'admin')        => $url->generate('admin_users', referenceType: $referenceType),
                $trans->trans('Foren-Mod.', [], 'admin')   => $url->generate('admin_reports_forum_posts', referenceType: $referenceType),
                $trans->trans('Städte', [], 'admin')       => $url->generate('admin_town_list', referenceType: $referenceType),
                $trans->trans('Zukunft', [], 'admin')      => $url->generate('admin_changelogs', referenceType: $referenceType),
                $trans->trans('AntiSpam', [], 'admin')     => $url->generate('admin_spam_domain_view', referenceType: $referenceType),
                $trans->trans('Apps', [], 'admin')         => $url->generate('admin_app_view', referenceType: $referenceType),
                $trans->trans('Saisons', [], 'admin')      => $url->generate('admin_seasons_view', referenceType: $referenceType),
                $trans->trans('Gruppen', [], 'admin')      => $url->generate('admin_group_view', referenceType: $referenceType),
                $trans->trans('Dateisystem', [], 'admin')  => $url->generate('admin_file_system_dash', referenceType: $referenceType),
                $trans->trans('Angriffsplan', [], 'admin') => $url->generate('admin_schedule_attacks', referenceType: $referenceType),
            ];

        if ( $this->hasRole($user, 'ROLE_ELEVATED') )
            return [
                $trans->trans('Dashboard', [], 'admin') => $url->generate('admin_dashboard', referenceType: $referenceType),
                $trans->trans('Kampagnen', [], 'admin') => $url->generate('admin_campaigns', referenceType: $referenceType),
                $trans->trans('Zukunft', [], 'admin')   => $url->generate('admin_changelogs', referenceType: $referenceType),
                $trans->trans('Kurztexte', [], 'admin') => $url->generate('admin_reports_snippets', referenceType: $referenceType)
            ];

        return [];
    }

}