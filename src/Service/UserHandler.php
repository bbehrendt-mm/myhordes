<?php

namespace App\Service;

use App\Entity\AccountRestriction;
use App\Entity\AntiSpamDomains;
use App\Entity\Avatar;
use App\Entity\Award;
use App\Entity\AwardPrototype;
use App\Entity\CauseOfDeath;
use App\Entity\Changelog;
use App\Entity\CitizenRankingProxy;
use App\Entity\ConsecutiveDeathMarker;
use App\Entity\FeatureUnlock;
use App\Entity\HeroSkillPrototype;
use App\Entity\Picto;
use App\Entity\Season;
use App\Entity\Shoutbox;
use App\Entity\ShoutboxEntry;
use App\Entity\SocialRelation;
use App\Entity\TwinoidImport;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Entity\UserGroupAssociation;
use Doctrine\ORM\QueryBuilder;
use Imagick;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserHandler
{
    const NoError                            = 0;
    const ErrorAvatarBackendUnavailable      = ErrorHelper::BaseAvatarErrors +  1;
    const ErrorAvatarTooLarge                = ErrorHelper::BaseAvatarErrors +  2;
    const ErrorAvatarFormatUnsupported       = ErrorHelper::BaseAvatarErrors +  3;
    const ErrorAvatarImageBroken             = ErrorHelper::BaseAvatarErrors +  4;
    const ErrorAvatarResolutionUnacceptable  = ErrorHelper::BaseAvatarErrors +  5;
    const ErrorAvatarProcessingFailed        = ErrorHelper::BaseAvatarErrors +  6;
    const ErrorAvatarInsufficientCompression = ErrorHelper::BaseAvatarErrors +  7;

    const ImageProcessingForceImagick = 0;
    const ImageProcessingPreferImagick = 1;
    const ImageProcessingDisableImagick = 2;


    private EntityManagerInterface $entity_manager;
    private RoleHierarchyInterface $roles;
    private ContainerInterface $container;
    private CrowService $crow;
    private MediaService $media;
    private TranslatorInterface $translator;

    public function __construct( EntityManagerInterface $em, RoleHierarchyInterface $roles,ContainerInterface $c, CrowService $crow, MediaService $media, TranslatorInterface  $translator)
    {
        $this->entity_manager = $em;
        $this->container = $c;
        $this->roles = $roles;
        $this->crow = $crow;
        $this->media = $media;
        $this->translator = $translator;
    }

    public function fetchSoulPoints(User $user, bool $all = true, bool $useCached = false): int {
        if ($useCached) return $all ? $user->getAllSoulPoints() : $user->getSoulPoints();
        $p_soul = $all ? $this->entity_manager->getRepository(TwinoidImport::class)->findOneBy(['user' => $user, 'main' => true]) : null;
        if ($p_soul) $p_soul =['www.hordes.fr' => 'fr', 'www.die2nite.com' => 'en', 'www.dieverdammten.de' => 'de', 'www.zombinoia.com' => 'es'][$p_soul->getScope()] ?? 'none';
        return array_reduce( array_filter(
            $this->entity_manager->getRepository(CitizenRankingProxy::class)->findBy(['disabled' => false, 'user' => $user, 'confirmed' => true]),
            function(CitizenRankingProxy $c) use ($all,$p_soul) { return $c->getTown() && !$c->getTown()->getDisabled() && ($c->getImportLang() === null || ($all && $c->getImportLang() === $p_soul) ); }
        ), fn(int $carry, CitizenRankingProxy $next) => $carry + ($next->getPoints() ?? 0), 0 );
    }

    public function fetchImportedSoulPoints(User $user): int {
        $p_soul = $this->entity_manager->getRepository(TwinoidImport::class)->findOneBy(['user' => $user, 'main' => true]);
        if ($p_soul === null) return 0;
        $p_soul = ['www.hordes.fr' => 'fr', 'www.die2nite.com' => 'en', 'www.dieverdammten.de' => 'de', 'www.zombinoia.com' => 'es'][$p_soul->getScope()] ?? 'none';
        return array_reduce( array_filter(
            $this->entity_manager->getRepository(CitizenRankingProxy::class)->findBy(['disabled' => false, 'user' => $user, 'confirmed' => true]),
            function(CitizenRankingProxy $c) use ($p_soul) { return $c->getTown() && !$c->getTown()->getDisabled() && $c->getImportLang() === $p_soul; }
        ), fn(int $carry, CitizenRankingProxy $next) => $carry + ($next->getPoints() ?? 0), 0 );
    }

    public function getPoints(User $user){
        $sp = $this->fetchSoulPoints($user);
        $pictos = $this->entity_manager->getRepository(Picto::class)->findNotPendingByUser($user);
        $points = 0;

        if($sp >= 100)  $points += 13;
        if($sp >= 500)  $points += 33;
        if($sp >= 1000) $points += 66;
        if($sp >= 2000) $points += 132;
        if($sp >= 3000) $points += 198;

        foreach ($pictos as $picto) {
            switch($picto["name"]){
                case "r_heroac_#00": case "r_explor_#00":
                    if ($picto["c"] >= 15)
                        $points += 3.5;
                    if ($picto["c"] >= 30)
                        $points += 6.5;
                    break;
                case "r_cookr_#00": case "r_cmplst_#00": case "r_camp_#00":case "r_drgmkr_#00": case "r_jtamer_#00":
                case "r_jrangr_#00": case "r_jguard_#00": case "r_jermit_#00": case "r_jtech_#00": case "r_jcolle_#00":
                    if ($picto["c"] >= 10)
                        $points += 3.5;
                    if ($picto["c"] >= 25)
                        $points += 6.5;
                    break;
                case "r_animal_#00": case "r_plundr_#00":
                    if ($picto["c"] >= 30)
                        $points += 3.5;
                    if ($picto["c"] >= 60)
                        $points += 6.5;
                    break;
                case "r_chstxl_#00": case "r_ruine_#00":
                    if ($picto["c"] >= 5)
                        $points += 3.5;
                    if ($picto["c"] >= 10)
                        $points += 6.5;
                    break;
                case "r_buildr_#00":
                    if ($picto["c"] >= 100)
                        $points += 3.5;
                    if ($picto["c"] >= 200)
                        $points += 6.5;
                    break;
                case "r_nodrug_#00":
                    if ($picto["c"] >= 20)
                        $points += 3.5;
                    if ($picto["c"] >= 75)
                        $points += 6.5;
                    break;
                case "r_ebuild_#00":
                    if ($picto["c"] >= 1)
                        $points += 3.5;
                    if ($picto["c"] >= 3)
                        $points += 6.5;
                    break;
                case "r_digger_#00":
                    if ($picto["c"] >= 50)
                        $points += 3.5;
                    if ($picto["c"] >= 300)
                        $points += 6.5;
                    break;
                case "r_deco_#00":
                    if ($picto["c"] >= 100)
                        $points += 3.5;
                    if ($picto["c"] >= 250)
                        $points += 6.5;
                    break;
                case "r_explo2_#00":
                    if ($picto["c"] >= 5)
                        $points += 3.5;
                    if ($picto["c"] >= 15)
                        $points += 6.5;
                    break;
                case "r_guide_#00":
                    if ($picto["c"] >= 300)
                        $points += 3.5;
                    if ($picto["c"] >= 1000)
                        $points += 6.5;
                    break;
                case "r_theft_#00":
                    if ($picto["c"] >= 10)
                        $points += 3.5;
                    if ($picto["c"] >= 30)
                        $points += 6.5;
                    break;
                case "r_maso_#00": case "r_guard_#00":
                    if ($picto["c"] >= 20)
                        $points += 3.5;
                    if ($picto["c"] >= 40)
                        $points += 6.5;
                    break;
                case "r_surlst_#00":
                    if ($picto["c"] >= 10)
                        $points += 3.5;
                    if ($picto["c"] >= 15)
                        $points += 6.5;
                    if ($picto["c"] >= 30)
                        $points += 10;
                    if ($picto["c"] >= 50)
                        $points += 13;
                    if ($picto["c"] >= 100)
                        $points += 16.5;
                    break;
                case "r_suhard_#00":
                    if ($picto["c"] >= 5)
                        $points += 3.5;
                    if ($picto["c"] >= 10)
                        $points += 6.5;
                    if ($picto["c"] >= 20)
                        $points += 10;
                    if ($picto["c"] >= 40)
                        $points += 13;
                    break;
                case "r_doutsd_#00":
                    if($picto["c"] >= 20)
                        $points += 3.5;
                    break;
                case "r_door_#00":
                    if($picto["c"] >= 1)
                        $points += 3.5;
                    if($picto["c"] >= 5)
                        $points += 6.5;
                    break;
                case "r_wondrs_#00":
                    if($picto["c"] >= 20)
                        $points += 3.5;
                    if($picto["c"] >= 50)
                        $points += 6.5;
                    break;
                case "r_rp_#00":
                    if($picto["c"] >= 5)
                        $points += 3.5;
                    if($picto["c"] >= 10)
                        $points += 6.5;
                    if($picto["c"] >= 20)
                        $points += 10;
                    if($picto["c"] >= 30)
                        $points += 13;
                    if($picto["c"] >= 40)
                        $points += 16.5;
                    if($picto["c"] >= 60)
                        $points += 20;
                    break;
                case "r_winbas_#00":
                    if($picto["c"] >= 2)
                        $points += 13;
                    if($picto["c"] >= 5)
                        $points += 20;
                    break;
                case "r_wintop_#00":
                    if($picto["c"] >= 1)
                        $points += 20;
                    break;
                case "r_killz_#00":
                    if($picto["c"] >= 100)
                        $points += 3.5;
                    if($picto["c"] >= 200)
                        $points += 6.5;
                    if($picto["c"] >= 300)
                        $points += 10;
                    if($picto["c"] >= 800)
                        $points += 13;
                    break;
                case "r_cannib_#00":
                    if ($picto["c"] >= 10)
                        $points += 3.5;
                    if ($picto["c"] >= 40)
                        $points += 6.5;
                    break;
            }
        }

        return $points;
    }

    public function computePictoUnlocks(User $user) {

        $cache = [];

        $pictos = $this->entity_manager->getRepository(Picto::class)->findNotPendingByUser($user);
        foreach ($pictos as $picto)
            $cache[$picto['id']] = $picto['c'];

        $skip_proto = [];
        $remove_awards = [];
        $award_awards = [];

        /** @var Award $award */
        foreach ($user->getAwards() as $award) {
            if ($award->getPrototype()) $skip_proto[] = $award->getPrototype();
            if ($award->getPrototype() && $award->getPrototype()->getAssociatedPicto() &&
                (!isset($cache[$award->getPrototype()->getAssociatedPicto()->getId()]) || $cache[$award->getPrototype()->getAssociatedPicto()->getId()] < $award->getPrototype()->getUnlockQuantity())
            )
                $remove_awards[] = $award;
        }

        foreach ($this->entity_manager->getRepository(AwardPrototype::class)->findAll() as $prototype)
            if (!in_array($prototype,$skip_proto) &&
                (isset($cache[$prototype->getAssociatedPicto()->getId()]) && $cache[$prototype->getAssociatedPicto()->getId()] >= $prototype->getUnlockQuantity())
            ) {
                $user->addAward($award = (new Award())->setPrototype($prototype));
                $this->entity_manager->persist($award_awards[] = $award);
            }

        if (!empty($award_awards))
            $this->entity_manager->persist($this->crow->createPM_titleUnlock($user, $award_awards));

        foreach ($remove_awards as $r) {
            $user->removeAward($r);
            $this->entity_manager->remove($r);
        }


    }

    public function hasSkill(User $user, $skill){
        if(is_string($skill)) {
            $skill = $this->entity_manager->getRepository(HeroSkillPrototype::class)->findOneBy(['name' => $skill]);
            if($skill === null)
                return false;
        }

        $skills = $this->entity_manager->getRepository(HeroSkillPrototype::class)->getUnlocked($user->getAllHeroDaysSpent());
        return in_array($skill, $skills);
    }

    public function hasSeenLatestChangelog(User $user, ?string $fallback_lang): bool {

        $lang = $user->getLanguage() ?? $fallback_lang ?? 'de';
        $latest_cl = $this->entity_manager->getRepository(Changelog::class)->findBy(['lang' => $lang], ['date' => 'DESC'], 1);
        if (empty($latest_cl)) return true;

        $seen_cl = $user->getLatestChangelog();
        if ($seen_cl === null) return false;

        return $latest_cl[0] === $seen_cl;
    }

    public function setSeenLatestChangelog(User $user, ?string $fallback_lang) {

        $lang = $user->getLanguage() ?? $fallback_lang ?? 'de';
        $latest_cl = $this->entity_manager->getRepository(Changelog::class)->findBy(['lang' => $lang], ['date' => 'DESC'], 1);
        if (empty($latest_cl)) return;

        $user->setLatestChangelog($latest_cl[0]);
        $this->entity_manager->persist($user);
    }

    public function deleteUser(User $user) {
        $user
            ->setEmail("$ deleted <{$user->getId()}>")->setDisplayName(null)
            ->setName("$ deleted <{$user->getId()}>")
            ->setDeleteAfter(null)
            ->setPassword(null)
            ->setLastActionTimestamp( null )
            ->setRightsElevation(0);

        if ($user->getAvatar()) {
            $this->entity_manager->remove($user->getAvatar());
            $user->setAvatar(null);
        }

         $user_coalitions = $this->entity_manager->getRepository(UserGroupAssociation::class)->findBy( [
                'user' => $user,
                'associationType' => [
                    UserGroupAssociation::GroupAssociationTypeCoalitionMember,
                    UserGroupAssociation::GroupAssociationTypeCoalitionMemberInactive,
                    UserGroupAssociation::GroupAssociationTypeCoalitionInvitation
                ] ]
        );

        foreach ($user_coalitions as $coalition) {
            $destroy = $coalition->getAssociationLevel() === UserGroupAssociation::GroupAssociationLevelFounder;
            if ($destroy) {
                foreach ($this->entity_manager->getRepository(UserGroupAssociation::class)->findBy( [
                    'association' => $coalition->getAssociation()
                ]) as $assoc ) $this->entity_manager->remove($assoc);
                $this->entity_manager->remove( $coalition->getAssociation() );
            } else {
                $this->entity_manager->remove( $coalition );
                /** @var Shoutbox|null $shoutbox */
                if ($shoutbox = $this->getShoutbox($coalition)) {
                    $shoutbox->addEntry(
                        (new ShoutboxEntry())
                            ->setType( ShoutboxEntry::SBEntryTypeLeave )
                            ->setTimestamp( new DateTime() )
                            ->setUser1( $user )
                    );
                    $this->entity_manager->persist($shoutbox);
                }
            }
        }

        $citizen = $user->getActiveCitizen();
        if ($citizen) {
            $r = [];
            $this->container->get(DeathHandler::class)->kill( $citizen, CauseOfDeath::Headshot, $r );
            foreach ($r as $re) $this->entity_manager->remove($re);
        }
    }

    /**
     * Checks if the given user has specified roles. The relation type is controlled by $any. If $roles is an empty
     * array, this function will return true in AND mode and false in OR mode.
     * @param User $user User to check
     * @param array $roles Roles to check for
     * @param bool $any Set true to check if any of the given roles apply (OR); set false to check if all roles apply
     * (AND).
     * @return bool
     */
    public function hasRoles(User $user, array $roles, bool $any = false): bool {
        $effectiveRoles = $this->roles->getReachableRoleNames( $user->getRoles() );
        foreach ($roles as $role) if ($any === in_array( $role, $effectiveRoles )) return $any;
        return !$any;
    }

    /**
     * Checks if the user has a specific role.
     * @param User $user User to check
     * @param string $role Role to check for
     * @return bool True if the user has the given role; false otherwise.
     */
    public function hasRole(User $user, string $role) {
        return in_array( $role, $this->roles->getReachableRoleNames( $user->getRoles() ) );
    }

    /**
     * Returns a list of grant-able roles
     * @return string[]
     */
    public function admin_validRoles(): array {
        return ['ROLE_ORACLE', 'ROLE_CROW', 'ROLE_ADMIN', 'ROLE_SUPER'];
    }

    /**
     * Checks if a principal user can perform administrative actions on a specific user account
     * @param User $principal User to perform the administrative action
     * @param User $target User to be administered
     * @return bool
     */
    public function admin_canAdminister( User $principal, User $target ): bool {
        // Only crows and admins can administer
        if (!$this->hasRoles( $principal, ['ROLE_CROW','ROLE_ADMIN'], true )) return false;

        // Crows / Admins can administer themselves
        if ($principal === $target) return true;

        // Nobody can administer a super admin
        if ($this->hasRole( $target, 'ROLE_SUPER' )) return false;

        // Only super admins can administer admins
        if ($this->hasRole( $target, 'ROLE_ADMIN' ) && !$this->hasRole( $principal, 'ROLE_SUPER')) return false;

        // Only admins can administer crows
        if ($this->hasRole( $target, 'ROLE_CROW' ) && !$this->hasRole( $principal, 'ROLE_ADMIN')) return false;

        return true;
    }

    /**
     * Checks if the given user can grant a specific role
     * @param User $principal
     * @param string $role
     * @return bool
     */
    public function admin_canGrant( User $principal, string $role ): bool {
        // Only admins can grant roles
        if (!$this->hasRole( $principal, 'ROLE_ADMIN' )) return false;

        // Make sure only valid roles can be granted
        if (!in_array($role, $this->admin_validRoles())) return false;

        // Only super admins can grant admin role
        if ($role === 'ROLE_ADMIN' && !$this->hasRole( $principal, 'ROLE_SUPER' )) return false;

        // Super admin role can be granted by admins only if no super admin exists yet
        if ($role === 'ROLE_SUPER' &&  !$this->hasRole( $principal, 'ROLE_SUPER' ) &&
            $this->entity_manager->getRepository(User::class)->findByLeastElevationLevel(User::ROLE_SUPER)
        ) return false;

        return true;
    }

    public function setUserBaseAvatar( User $user, $payload, int $imagick_setting = self::ImageProcessingForceImagick, string $ext = null, int $x = 100, int $y = 100 ): int {

        // Processing limit: 3MB
        if (strlen( $payload ) > 3145728) return self::ErrorAvatarTooLarge;

        $e = $imagick_setting === self::ImageProcessingDisableImagick
            ? MediaService::ErrorBackendMissing
            : $this->media->resizeImage( $payload, function(int &$w, int &$h, bool &$fit): bool {
            if ($w / $h < 0.1 || $h / $w < 0.1 || $h < 16 || $w < 16)
                return false;

            if ( max($w,$h) > 200 || min($w,$h < 90) )
                $w = $h = min(200,max(90,$w,$h));

            return $fit = true;
        }, $w_final, $h_final, $processed_format );

        switch ($e) {
            case MediaService::ErrorNone:
                break;
            case MediaService::ErrorBackendMissing:
                if ($imagick_setting === self::ImageProcessingForceImagick)
                    return self::ErrorAvatarBackendUnavailable;
                $processed_format = $ext;
                $w_final = $x ?: 100;
                $h_final = $y ?: 100;
                break;
            case MediaService::ErrorInputBroken: return self::ErrorAvatarImageBroken;
            case MediaService::ErrorInputUnsupported: return self::ErrorAvatarFormatUnsupported;
            case MediaService::ErrorDimensionMismatch: return self::ErrorAvatarResolutionUnacceptable;
            case MediaService::ErrorProcessingFailed: default:
                return self:: ErrorAvatarProcessingFailed;
        }

        // Storage limit: 1MB
        if (strlen($payload) > 1048576) return self::ErrorAvatarInsufficientCompression;

        $name = md5( $payload );
        if (!($avatar = $user->getAvatar())) {
            $avatar = new Avatar();
            $user->setAvatar($avatar);
        }

        $avatar
            ->setChanged(new DateTime())
            ->setFilename( $name )
            ->setSmallName( $name )
            ->setFormat( $processed_format ?? 'null' )
            ->setImage( $payload )
            ->setX( $w_final ?? 0 )
            ->setY( $h_final ?? 0 )
            ->setSmallImage( null );

        return self::NoError;
    }

    public function setUserSmallAvatar( User $user, $payload = null, ?int $x = null, ?int $y = null, ?int $dx = null, ?int $dy = null ): int {
        $avatar = $user->getAvatar();

        if (!$avatar || $avatar->isClassic())
            return self::ErrorAvatarFormatUnsupported;

        // Processing limit: 3MB
        if ($payload !== null && strlen( $payload ) > 3145728) return self::ErrorAvatarTooLarge;

        if ($payload === null) {
            if (
                $x < 0 || $dx < 0 || $x + $dx > $avatar->getX() ||
                $y < 0 || $dy < 0 || $y + $dy > $avatar->getY()
            ) return self::ErrorAvatarFormatUnsupported;

            $payload = stream_get_contents( $avatar->getImage() );
            $e = $this->media->cropImage( $payload, $dx, $dy, $x, $y, function(int &$w, int &$h, bool &$fit): bool {
                    if ($w < 90 || $h < 30 || ($h/$w != 3))
                        $w = ($h = max(30, $h)) * 3;

                    $fit = true;
                    return true;
                }, $w_final, $h_final, $processed_format, false );

            switch ($e) {
                case MediaService::ErrorNone:
                    break;
                case MediaService::ErrorBackendMissing:
                    return self::ErrorAvatarBackendUnavailable;
                case MediaService::ErrorInputBroken: return self::ErrorAvatarImageBroken;
                case MediaService::ErrorInputUnsupported: return self::ErrorAvatarFormatUnsupported;
                case MediaService::ErrorDimensionMismatch: return self::ErrorAvatarResolutionUnacceptable;
                case MediaService::ErrorProcessingFailed: default:
                return self:: ErrorAvatarProcessingFailed;
            }

            if ($processed_format !== $avatar->getFormat())
                return self::ErrorAvatarFormatUnsupported;
        }

        if (strlen($payload) > 1048576) return self::ErrorAvatarInsufficientCompression;

        $name = md5( (new DateTime())->getTimestamp() );

        $avatar
            ->setSmallName( $name )
            ->setSmallImage( $payload );

        return self::NoError;
    }

    public function getCoalitionMembership(User $user): ?UserGroupAssociation {
        return $this->entity_manager->getRepository(UserGroupAssociation::class)->findOneBy( [
                'user' => $user,
                'associationType' => [UserGroupAssociation::GroupAssociationTypeCoalitionMember, UserGroupAssociation::GroupAssociationTypeCoalitionMemberInactive] ]
        );
    }

    /**
     * @param User|UserGroup|UserGroupAssociation $principal
     * @return Shoutbox|null
     */
    public function getShoutbox($principal): ?Shoutbox {

        if (is_a($principal, User::class)) $principal = $this->getCoalitionMembership($principal);
        if (is_a($principal, UserGroupAssociation::class) && in_array($principal->getAssociationType(),
            [UserGroupAssociation::GroupAssociationTypeCoalitionMember, UserGroupAssociation::GroupAssociationTypeCoalitionMemberInactive]
            )) $principal = $principal->getAssociation();
        if (is_a($principal, UserGroup::class) && $principal->getType() === UserGroup::GroupSmallCoalition)
            return $this->entity_manager->getRepository(Shoutbox::class)->findOneBy(['userGroup' => $principal]);

        return null;
    }

    public function getConsecutiveDeathLock(User $user, bool &$warning = null): bool {
        /** @var ConsecutiveDeathMarker $cdm */
        $cdm = $this->entity_manager->getRepository(ConsecutiveDeathMarker::class)->findOneBy(['user' => $user]);

        $warning = $cdm ? ($cdm->getDeath()->getRef() === CauseOfDeath::Dehydration && $cdm->getNumber() === 2) : false;
        return $cdm ? ($cdm->getDeath()->getRef() === CauseOfDeath::Dehydration && $cdm->getNumber() >= 3 && $cdm->getTimestamp() > (new \DateTime('today - 2week'))) : false;
    }

    /**
     * @param User $user
     * @param int|null $full_member_count
     * @param bool|null $active
     * @return User[]
     */
    public function getAvailableCoalitionMembers(User $user, ?int &$full_member_count = null, ?bool &$active = null): array {
        /** @var UserGroupAssociation|null $user_coalition */
        $user_coalition = $this->entity_manager->getRepository(UserGroupAssociation::class)->findOneBy( [
                'user' => $user,
                'associationType' => [UserGroupAssociation::GroupAssociationTypeCoalitionMember, UserGroupAssociation::GroupAssociationTypeCoalitionMemberInactive] ]
        );

        /** @var UserGroupAssociation[] $all_coalition_members */
        $all_coalition_members = $user_coalition ? $this->entity_manager->getRepository(UserGroupAssociation::class)->findBy( [
            'association' => $user_coalition->getAssociation(),
            'associationType' => [UserGroupAssociation::GroupAssociationTypeCoalitionMember, UserGroupAssociation::GroupAssociationTypeCoalitionMemberInactive]
        ]) : [];

        $full_member_count = count($all_coalition_members);
        $active = false;

        $valid_members = [];
        foreach ($all_coalition_members as $member)
            if (
                $member->getAssociationType() === UserGroupAssociation::GroupAssociationTypeCoalitionMember &&
                $member->getUser()->getLastActionTimestamp() !== null &&
                $member->getUser()->getLastActionTimestamp()->getTimestamp() > (time() - 432000) &&
                $member->getUser()->getActiveCitizen() === null &&
                !$this->getConsecutiveDeathLock($member->getUser())
            ) {
                if ($member->getUser() === $user) $active = true;
                else $valid_members[] = $member->getUser();
            }


        return $active ? $valid_members : [];
    }

    public function getActiveRestrictions(User $user): int {
        $r = AccountRestriction::RestrictionNone;

        /** @var QueryBuilder $qb */
        $qb = $this->entity_manager->getRepository(AccountRestriction::class)->createQueryBuilder('a');
        foreach ($qb
                     ->select('a.restriction AS r')
                     ->andWhere('a.user = :user' )->setParameter('user', $user)
                     ->andWhere('(a.active = TRUE AND a.confirmed = true)')
                     ->andWhere('(a.expires IS NULL or a.expires > :now)')->setParameter('now', new DateTime())
                     ->getQuery()->getResult() as $entry)

            $r |= $entry['r'];

        return $r;
    }

    public function getActiveRestrictionExpiration(User $user, ?int $restriction): ?DateTime {
        $dt = null;

        $qb = $this->entity_manager->getRepository(AccountRestriction::class)->createQueryBuilder('a');
        foreach ($qb
                     ->andWhere('a.user = :user' )->setParameter('user', $user)
                     ->andWhere('(a.active = TRUE AND a.confirmed = true)')
                     ->andWhere('a.restriction != :nores')->setParameter('nores', AccountRestriction::RestrictionNone)
                     ->andWhere('(a.expires IS NULL or a.expires > :now)')->setParameter('now', new DateTime())
                     ->getQuery()->getResult() as $entry)
            /** @var AccountRestriction $entry */
            if ( $restriction === null || ($entry->getRestriction() & $restriction) === $restriction ) {
                if ($entry->getExpires() === null) return null;
                if ($dt === null || $entry->getExpires() > $dt)
                    $dt = $entry->getExpires();
            }

        return $dt;
    }

    public function isRestricted(User $user, ?int $restriction = null): bool {
        $r = $this->getActiveRestrictions($user);
        return $restriction === null ? ($r !== AccountRestriction::RestrictionNone) : (($r & $restriction) === $restriction);
    }

    protected array $_relation_cache = [];
    public function checkRelation( User $user, User $relation, int $type ) {
        if ($user === $relation) return false;
        $key = "{$user->getId()}:{$relation->getId()}:{$type}";
        return
            $this->_relation_cache[$key] ??
            ( $this->_relation_cache[$key] = (bool)$this->entity_manager->getRepository(SocialRelation::class)->findOneBy(['owner' => $user, 'related' => $relation, 'type' => $type]) );
    }

    public function checkFeatureUnlock(User $user, $feature, bool $deduct): bool {
        /** @var FeatureUnlock $r */
        $r = $this->entity_manager->getRepository(FeatureUnlock::class)->findOneActiveForUser(
            $user,
            $this->entity_manager->getRepository(Season::class)->findLatest(),
            $feature
        );

        // If we're not in deduct mode or don't have an entity, simply return if an entity was found
        if (!$deduct || $r === null) return $r !== null;

        // If we're in deduct mode and the feature has a town count expiration, reduce town count
        if ($r->getExpirationMode() === FeatureUnlock::FeatureExpirationTownCount)
            $this->entity_manager->persist( $r->setTownCount( max(0,$r->getTownCount() - 1 )) );
        return true;
    }

    /**
     * Tests if the username wanted is valid
     * Uses Levenshtein's algo
     * @param string $name The username to test
     * @return bool The validity of the username
     */
    public function isNameValid(string $name): bool {
        $invalidNames = ['Der Rabe', 'Le Corbeau', 'The Crow', 'El Cuervo'];
        $closestDistance = 99999;
        foreach ($invalidNames as $invalidName) {
            $dist = levenshtein($name,$invalidName);
            if ($dist < $closestDistance) {
                $closestDistance = $dist;
            }
        }

        return !preg_match('/[^\w]/', $name) && strlen($name) >= 3 && strlen($name) <= 16 && $closestDistance > 2;
    }
}