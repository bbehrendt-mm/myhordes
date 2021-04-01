<?php

namespace App\Controller\Admin;

use App\Annotations\GateKeeperProfile;
use App\Entity\AccountRestriction;
use App\Entity\Citizen;
use App\Entity\CitizenProfession;
use App\Entity\CitizenRole;
use App\Entity\CitizenStatus;
use App\Entity\ConnectionWhitelist;
use App\Entity\ItemPrototype;
use App\Entity\Picto;
use App\Entity\PictoPrototype;
use App\Entity\ShadowBan;
use App\Entity\TwinoidImport;
use App\Entity\TwinoidImportPreview;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Entity\UserPendingValidation;
use App\Exception\DynamicAjaxResetException;
use App\Response\AjaxResponse;
use App\Service\AdminActionHandler;
use App\Service\AntiCheatService;
use App\Service\CrowService;
use App\Service\ErrorHelper;
use App\Service\JSONRequestParser;
use App\Service\PermissionHandler;
use App\Service\TwinoidHandler;
use App\Service\UserFactory;
use App\Service\UserHandler;
use App\Structures\TownConf;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 * @GateKeeperProfile(allow_during_attack=true)
 * @method User getUser
 */
class AdminUserController extends AdminActionController
{
    /**
     * @Route("jx/admin/users", name="admin_users")
     * @param AntiCheatService $as
     * @return Response
     */
    public function users(AntiCheatService $as): Response
    {
        $report = $as->createMultiAccountReport();
        return $this->render( 'ajax/admin/users/index.html.twig', $this->addDefaultTwigArgs("admin_users_ban", [
            'ma_report' => $report
        ]));
    }

    /**
     * @Route("jx/admin/users/{id}/account/view", name="admin_users_account_view", requirements={"id"="\d+"})
     * @param int $id
     * @return Response
     */
    public function users_account_view(int $id): Response
    {
        /** @var User $user */
        $user = $this->entity_manager->getRepository(User::class)->find($id);
        if (!$user) return $this->redirect( $this->generateUrl('admin_users') );

        $validations = $this->isGranted('ROLE_ADMIN') ? $this->entity_manager->getRepository(UserPendingValidation::class)->findByUser($user) : [];

        return $this->render( 'ajax/admin/users/account.html.twig', $this->addDefaultTwigArgs("admin_users_account", [
            'user' => $user,
            'validations' => $validations,
        ]));
    }

    /**
     * @Route("api/admin/users/{id}/account/do/{action}/{param}", name="admin_users_account_manage", requirements={"id"="\d+"})
     * @param int $id
     * @param string $action
     * @param JSONRequestParser $parser
     * @param UserFactory $uf
     * @param TwinoidHandler $twin
     * @param UserHandler $userHandler
     * @param PermissionHandler $perm
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param CrowService $crow
     * @param string $param
     * @return Response
     */
    public function user_account_manager(int $id, string $action, JSONRequestParser $parser, UserFactory $uf,
                                         TwinoidHandler $twin, UserHandler $userHandler, PermissionHandler $perm,
                                         UserPasswordEncoderInterface $passwordEncoder, CrowService $crow,
                                         string $param = ''): Response
    {
        /** @var User $user */
        $user = $this->entity_manager->getRepository(User::class)->find($id);
        if (!$user) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if (empty($param)) $param = $parser->get('param', '');

        if (in_array($action, [
            'delete_token', 'invalidate', 'validate', 'twin_full_reset', 'twin_main_reset', 'delete', 'rename',
            'shadow', 'whitelist', 'unwhitelist', 'etwin_reset', 'overwrite_pw', 'initiate_pw_reset',
            'enforce_pw_reset', 'change_mail' ]) && !$this->isGranted('ROLE_ADMIN'))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if ($action === 'grant' && $param !== 'NONE' && !$userHandler->admin_canGrant( $this->getUser(), $param ))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if ($action === 'grant' && $param === 'NONE' && !$this->isGranted('ROLE_ADMIN'))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if (!$userHandler->admin_canAdminister( $this->getUser(), $user )) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        switch ($action) {
            case 'validate':
                if ($user->getValidated() || $user->getEternalID())
                    return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                $pf = $this->entity_manager->getRepository(UserPendingValidation::class)->findOneByUserAndType($user, UserPendingValidation::EMailValidation);
                if ($pf) {
                    $this->entity_manager->remove($pf);
                    $user->setPendingValidation(null);
                }
                $user->setValidated(true);
                $perm->associate($user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultUserGroup ));
                $this->entity_manager->persist($user);
                break;

            case 'invalidate':
                if (!$user->getValidated() || $user->getEternalID())
                    return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                $user->setValidated(false);
                $perm->disassociate($user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultUserGroup ));
                $uf->announceValidationToken( $uf->ensureValidation( $user, UserPendingValidation::EMailValidation ) );
                $this->entity_manager->persist($user);
                break;

            case 'refresh_tokens': case 'regen_tokens':
                if ($user->getEternalID())
                    return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                foreach ($this->entity_manager->getRepository(UserPendingValidation::class)->findByUser($user) as $pf) {
                    /** @var $pf UserPendingValidation */
                    if ($action === 'regen_tokens') $pf->generatePKey();
                    $uf->announceValidationToken( $pf );
                    $this->entity_manager->persist( $pf );
                }
                break;

            case 'initiate_pw_reset':case 'enforce_pw_reset':
                if ($action === 'enforce_pw_reset')
                    $user->setPassword(null);
                $uf->announceValidationToken( $uf->ensureValidation( $user, UserPendingValidation::ResetValidation ) );
                $this->entity_manager->persist($user);
                break;

            case 'overwrite_pw':
                if (empty($param) || $user->getEternalID()) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                $user->setPassword( $passwordEncoder->encodePassword( $user, $param ) );
                $this->entity_manager->persist($user);
                break;

            case 'change_mail':
                if ($user->getEternalID()) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                $user->setEmail( $param ?? null );
                $this->entity_manager->persist($user);
                break;

            case 'delete_token':
                if ($user->getEternalID())
                    return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                /** @var $pv UserPendingValidation */
                if (!$parser->has('tid') || ($pv = $this->entity_manager->getRepository(UserPendingValidation::class)->find((int)$parser->get('tid'))) === null)
                    return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                if ($pv->getUser()->getId() !== $id)
                    return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                $this->entity_manager->remove($pv);
                break;

            case 'twin_full_reset'://, 'twin_main_reset'
                foreach ($user->getTwinoidImports() as $import) {
                    $user->removeTwinoidImport($import);
                    $this->entity_manager->remove($import);
                }

                $pending = $this->entity_manager->getRepository(TwinoidImportPreview::class)->findOneBy(['user' => $user]);
                if ($pending) {
                    $user->setTwinoidImportPreview(null);
                    $pending->setUser(null);
                    $this->entity_manager->remove($pending);
                }

                $twin->clearImportedData( $user, null, true );
                $user->setTwinoidID(null);
                $this->entity_manager->persist($user);
                break;

            case 'twin_main_reset':

                $main = $this->entity_manager->getRepository(TwinoidImport::class)->findOneBy(['user' => $user, 'main' => true]);
                if ($main) {
                    $twin->clearPrimaryImportedData( $user );
                    $main->setMain( false );
                    $this->entity_manager->persist($main);
                }
                break;

            case 'etwin_reset':

                if (empty($param) || $user->getEternalID() === null)
                    return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                $user
                    ->setEternalID( null )
                    ->setEmail( $param )
                    ->setPassword( null );
                $this->entity_manager->persist($user);
                break;

            case 'rename':
                if (empty($param)) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                $user->setName( $param );
                $this->entity_manager->persist($user);
                break;

            case 'delete':
                if ($user->getEternalID())
                    return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                $userHandler->deleteUser($user);
                $this->entity_manager->persist($user);
                break;

            case 'shadow':
                if (empty($param)) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

                $this->entity_manager->persist(
                    (new AccountRestriction())
                        ->setActive(true)
                        ->setConfirmed(true)
                        ->setPublicReason($param)
                        ->setOriginalDuration(-1)
                        ->setExpires(null)
                        ->setModerator( $this->getUser() )
                        ->addConfirmedBy( $this->getUser() )
                        ->setCreated( new \DateTime() )
                        ->setRestriction( AccountRestriction::RestrictionGameplay )
                );

                $n = $crow->createPM_moderation( $user, CrowService::ModerationActionDomainAccount, CrowService::ModerationActionTargetGameBan, CrowService::ModerationActionImpose, -1, $param );
                if ($n) $this->entity_manager->persist($n);
                break;

            case 'whitelist':
                if ($user->getConnectionWhitelists()->isEmpty()) $user->getConnectionWhitelists()->add( $wl = new ConnectionWhitelist() );
                else $wl = $user->getConnectionWhitelists()->getValues()[0];

                $wl->addUser($user);
                if (!is_array($param)) $param = [$param];
                foreach ($param as $other_user_id) {
                    /** @var User $other_user */
                    $other_user = $this->entity_manager->getRepository(User::class)->find($other_user_id);
                    if (!$other_user) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                    $wl->addUser($other_user);
                }

                $this->entity_manager->persist($wl);
                break;

            case 'unwhitelist':
                if (!is_array($param)) $param = [$param];
                foreach ($param as $other_user_id) {
                    /** @var User $other_user */
                    $other_user = $this->entity_manager->getRepository(User::class)->find($other_user_id);
                    if (!$other_user) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

                    foreach ($user->getConnectionWhitelists() as $wl)
                        $wl->removeUser($other_user);

                    foreach ($user->getConnectionWhitelists() as $wl)
                        if ($wl->getUsers()->count() < 2) $this->entity_manager->remove($wl);
                        else $this->entity_manager->persist($wl);
                }
                break;

            case 'grant':
                switch ($param) {
                    case 'NONE':
                        $user->setRightsElevation( User::ROLE_USER );
                        $perm->disassociate( $user, $perm->getDefaultGroup(UserGroup::GroupTypeDefaultOracleGroup));

                        $perm->disassociate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultElevatedGroup ) );
                        $perm->disassociate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultModeratorGroup ) );
                        $perm->disassociate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultAdminGroup ) );
                        break;
                    case 'ROLE_ORACLE':
                        if ( $user->getRightsElevation() === User::ROLE_CROW )
                            $user->setRightsElevation( User::ROLE_ORACLE );
                        else $user->setRightsElevation( max($user->getRightsElevation(), User::ROLE_ORACLE) );
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultElevatedGroup ) );
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultOracleGroup));
                        $perm->disassociate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultModeratorGroup ) );
                        $perm->disassociate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultAdminGroup ) );
                        break;
                    case 'ROLE_CROW':
                        $user->setRightsElevation( max($user->getRightsElevation(), User::ROLE_CROW) );
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultElevatedGroup ) );
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultOracleGroup));
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultModeratorGroup ) );
                        $perm->disassociate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultAdminGroup ) );
                        break;
                    case 'ROLE_ADMIN':
                        $user->setRightsElevation( max($user->getRightsElevation(), User::ROLE_ADMIN) );
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultElevatedGroup ) );
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultOracleGroup));
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultModeratorGroup ) );
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultAdminGroup ) );
                        break;
                    case 'ROLE_SUPER':
                        $user->setRightsElevation( max($user->getRightsElevation(), User::ROLE_SUPER) );
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultElevatedGroup ) );
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultOracleGroup));
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultModeratorGroup ) );
                        $perm->associate( $user, $perm->getDefaultGroup( UserGroup::GroupTypeDefaultAdminGroup ) );
                        break;
                    default: breaK;
                }
                $this->entity_manager->persist($user);
                break;

            default: return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        }

        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException, [$e->getMessage()] );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("jx/admin/users/{id}/ban/view", name="admin_users_ban_view", requirements={"id"="\d+"})
     * @param int $id
     * @return Response
     */
    public function users_ban_view(int $id): Response
    {
        $user = $this->entity_manager->getRepository(User::class)->find($id);
        if (!$user) throw new DynamicAjaxResetException(Request::createFromGlobals());

        return $this->render( 'ajax/admin/users/ban.html.twig', $this->addDefaultTwigArgs("admin_users_ban", [
            'user' => $user,
            'existing' => $this->entity_manager->getRepository(AccountRestriction::class)->findBy(['user' => $user], ['active' => 'ASC', 'confirmed' => 'ASC', 'created' => 'ASC'])
        ]));
    }

    private function requires_crow_confirmation(AccountRestriction $a): bool {
        return $a->getOriginalDuration() > 2592000;
    }

    private function requires_admin_confirmation(AccountRestriction $a): bool {
        return $a->getOriginalDuration() >= 31536000 || ($a->getRestriction() & AccountRestriction::RestrictionGameplay) === AccountRestriction::RestrictionGameplay;
    }

    private function check_ban_confirmation(AccountRestriction $a): bool {

        if ($a->getConfirmed()) return true;

        if (!$this->user_handler->hasRole( $this->getUser(), 'ROLE_ADMIN' )) {

            if ($this->requires_crow_confirmation($a) && count($a->getConfirmedBy()) < 2) return false;
            if ($this->requires_admin_confirmation($a)) {
                $confirmed_by_admin = false;
                foreach ($a->getConfirmedBy() as $u) if ($this->user_handler->hasRole( $u, 'ROLE_ADMIN' )) $confirmed_by_admin = true;
                if (!$confirmed_by_admin) return false;
            }
        }

        $a->setConfirmed(true)->setExpires( $a->getOriginalDuration() < 0 ? null : (new \DateTime())->setTimestamp( time() + $a->getOriginalDuration() ) );
        if ($a->getRestriction() & AccountRestriction::RestrictionSocial)
            $this->entity_manager->persist($this->crow_service->createPM_moderation( $a->getUser(), CrowService::ModerationActionDomainAccount, CrowService::ModerationActionTargetForumBan, CrowService::ModerationActionImpose, $a->getOriginalDuration() < 0 ? null : $a->getOriginalDuration(), $a->getPublicReason() ));
        if ($a->getRestriction() & AccountRestriction::RestrictionGameplay)
            $this->entity_manager->persist($this->crow_service->createPM_moderation( $a->getUser(), CrowService::ModerationActionDomainAccount, CrowService::ModerationActionTargetGameBan, CrowService::ModerationActionRevoke, -1, $a->getPublicReason() ));
        return true;
    }

    /**
     * @Route("api/admin/users/{uid}/ban/{bid}/confirm", name="admin_users_ban_confirm", requirements={"uid"="\d+","bid"="\d+"})
     * @param int $uid
     * @param int $bid
     * @return Response
     */
    public function users_confirm_ban(int $uid, int $bid): Response
    {
        $a = $this->entity_manager->getRepository(AccountRestriction::class)->find($bid);
        if ($a === null || $a->getUser()->getId() !== $uid) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (!$a->getActive() || $a->getExpires() && $a->getExpires() < new \DateTime()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ($a->getConfirmedBy()->contains($this->getUser())) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $a->addConfirmedBy($this->getUser());
        $this->check_ban_confirmation($a);

        $this->entity_manager->persist($a);
        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/users/{uid}/ban/{bid}/disable", name="admin_users_ban_disable", requirements={"uid"="\d+","bid"="\d+"})
     * @param int $uid
     * @param int $bid
     * @return Response
     */
    public function users_disable_ban(int $uid, int $bid): Response
    {
        $a = $this->entity_manager->getRepository(AccountRestriction::class)->find($bid);
        if ($a === null || $a->getUser()->getId() !== $uid) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (!$a->getActive() || $a->getExpires() && $a->getExpires() < new \DateTime()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $is_admin_confirmed = $is_super_confirmed = false;
        foreach ($a->getConfirmedBy() as $confirmer) {
            if ($this->user_handler->hasRole($confirmer, 'ROLE_ADMIN')) $is_admin_confirmed = true;
            if ($this->user_handler->hasRole($confirmer, 'ROLE_SUPER')) $is_super_confirmed = true;
        }

        if ($is_super_confirmed && !$this->user_handler->hasRole( $this->getUser(), 'ROLE_SUPER' )) return AjaxResponse::error(ErrorHelper::ErrorPermissionError);
        if ($is_admin_confirmed && !$this->user_handler->hasRole( $this->getUser(), 'ROLE_ADMIN' )) return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $a->setActive(false)->setInternalReason($a->getInternalReason() . " ~ [DISABLED BY {$this->getUser()->getName()}]");
        $this->entity_manager->persist($a);

        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/users/{id}/ban", name="admin_users_ban", requirements={"id"="\d+"})
     * @param int $id
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function users_ban(int $id, JSONRequestParser $parser): Response
    {
        if (!$parser->has_all(['reason','duration','restriction'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $user = $this->entity_manager->getRepository(User::class)->find($id);
        if (!$user) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ($this->user_handler->hasRole( $user, 'ROLE_CROW' ) && !$this->user_handler->hasRole( $this->getUser(), 'ROLE_ADMIN' ))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);
        if ($this->user_handler->hasRole( $user, 'ROLE_ADMIN' ) && !$this->user_handler->hasRole( $this->getUser(), 'ROLE_SUPER' ))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);
        if ($this->user_handler->hasRole( $user, 'ROLE_SUPER' ))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $reason    = trim($parser->get('reason'));
        $note      = trim($parser->get('note'));
        $duration  = $parser->get_int('duration');
        $mask      = $parser->get_int('restriction', 0);

        if ($duration === 0 || $mask <= 0 || ($mask & ~(AccountRestriction::RestrictionSocial | AccountRestriction::RestrictionGameplay | AccountRestriction::RestrictionProfile)))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $a = (new AccountRestriction())
            ->setUser($user)
            ->setRestriction( $mask )
            ->setOriginalDuration( $duration )
            ->setCreated( new \DateTime() )
            ->setActive( true )
            ->setConfirmed( false )
            ->setModerator( $this->getUser() )
            ->setPublicReason( $reason )
            ->setInternalReason( $note )
            ->addConfirmedBy( $this->getUser() );
        $this->check_ban_confirmation($a);
        $this->entity_manager->persist($a);

        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/users/{id}/ban/lift", name="admin_users_ban_lift", requirements={"id"="\d+"})
     * @return Response
     */
    public function users_ban_lift(int $id, AdminActionHandler $admh): Response
    {                
        if ($admh->liftAllBans($this->getUser()->getId(), $id))
            return AjaxResponse::success();

        return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
    }

    /**
     * @Route("api/admin/users/find", name="admin_users_find")
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function users_find(JSONRequestParser $parser, EntityManagerInterface $em): Response
    {
        if (!$parser->has_all(['name'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        $searchName = $parser->get('name');
        $user = $em->getRepository(User::class)->findOneBy(array('name' => $searchName));
        
        if (isset($user))
            return AjaxResponse::success( true, ['url' => $this->generateUrl('admin_users_ban_view', ['id' => $user->getId()])] );

        return AjaxResponse::error(ErrorHelper::ErrorInternalError);
    }

    /**
     * @Route("jx/admin/users/fuzzyfind", name="admin_users_fuzzyfind")
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function users_fuzzyfind(JSONRequestParser $parser, EntityManagerInterface $em): Response
    {
        if (!$parser->has_all(['name'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $parts = explode(':', $parser->get('name'), 2);
        list($query,$searchName) = count($parts) === 2 ? $parts : ['u', $parts[0]];

        switch ($query) {
            case 'i': $users = $em->getRepository(User::class)->findBy(['id' => (int)$searchName]); break; // ID
            case 'u': $users = $em->getRepository(User::class)->findByNameContains($searchName); break; // Username & Display name
            case 'd': $users = $em->getRepository(User::class)->findByDisplayNameContains($searchName); break; // Only Display name
            case 'e': $users = $this->isGranted('ROLE_ADMIN') ? $em->getRepository(User::class)->findByMailContains($searchName) : []; break; // Mail
            case 'ue':case 'eu': $users = $this->isGranted('ROLE_ADMIN') ? $em->getRepository(User::class)->findByNameOrMailContains($searchName) : []; break; // Username & Mail
            case 't':  // Twinoid ID
                $users = $em->getRepository(User::class)->findBy(['twinoidID' => (int)$searchName]);
                foreach ($em->getRepository(TwinoidImportPreview::class)->findBy(['twinoidID' => (int)$searchName]) as $ip)
                    if (!in_array($ip->getUser(), $users)) $users[] = $ip->getUser();
                break;
            case 'et': $users = $em->getRepository(User::class)->findBy(['eternalID' => $searchName]); break; // EternalTwin ID
            case 'v0': $users = $em->getRepository(User::class)->findBy(['validated' => false]); break; // Non-validated
            case 'x':  $users = $em->getRepository(User::class)->findAboutToBeDeleted(); break; // Non-validated
            case 'ro': $users = $em->getRepository(User::class)->findBy(['rightsElevation' => [User::ROLE_ORACLE]]); break; // Is Oracle
            case 'rc': $users = $em->getRepository(User::class)->findBy(['rightsElevation' => [User::ROLE_CROW]]);   break; // Is Crow
            case 'ra': $users = $em->getRepository(User::class)->findBy(['rightsElevation' => [User::ROLE_ADMIN, User::ROLE_SUPER]]);  break; // Is Admin
            case 'rb': $users = $em->getRepository(User::class)->findBy(['rightsElevation' => [User::ROLE_SUPER]]);  break; // Is Brainbox
            default: $users = [];
        }

        return $this->render( 'ajax/admin/users/list.html.twig', $this->addDefaultTwigArgs("admin_users_citizen", [
            'users' => $users,
            'nohref' => $parser->get('no-href',false)
        ]));
    }

    /**
     * @Route("jx/admin/users/{id}/citizen/view", name="admin_users_citizen_view", requirements={"id"="\d+"})
     * @param int $id
     * @return Response
     */
    public function users_citizen_view(int $id): Response
    {
        $user = $this->entity_manager->getRepository(User::class)->find($id);

        /** @var Citizen $citizen */
        $citizen = $user->getActiveCitizen();
        if ($citizen) {
            $active = true;
            $town = $citizen->getTown();
            $alive = $citizen->getAlive();
        }                    
        else {
            $active = false;
            $alive = false;
            $town = null;
        }

        $pictoProtos = $this->entity_manager->getRepository(PictoPrototype::class)->findAll();
        usort($pictoProtos, function ($a, $b) {
            return strcmp($this->translator->trans($a->getLabel(), [], 'game'), $this->translator->trans($b->getLabel(), [], 'game'));
        });

        $itemPrototypes = $this->entity_manager->getRepository(ItemPrototype::class)->findAll();
        usort($itemPrototypes, function ($a, $b) {
            return strcmp($this->translator->trans($a->getLabel(), [], 'items'), $this->translator->trans($b->getLabel(), [], 'items'));
        });

        $citizenStati = $this->entity_manager->getRepository(CitizenStatus::class)->findAll();
        usort($citizenStati, function ($a, $b) {
            return strcmp($this->translator->trans($a->getLabel(), [], 'game'), $this->translator->trans($b->getLabel(), [], 'game'));
        });

        $disabled_profs = $town ? $this->conf->getTownConfiguration($town)->get(TownConf::CONF_DISABLED_JOBS, []) : [];
        $professions = array_filter($this->entity_manager->getRepository( CitizenProfession::class )->findSelectable(),
            fn(CitizenProfession $p) => !in_array($p->getName(),$disabled_profs)
        );

        $citizenRoles = $this->entity_manager->getRepository(CitizenRole::class)->findAll();

        return $this->render( 'ajax/admin/users/citizen.html.twig', $this->addDefaultTwigArgs("admin_users_citizen", [
            'town' => $town,
            'active' => $active,
            'alive' => $alive,
            'user' => $user,
            'user_citizen' => $citizen,
            'itemPrototypes' => $itemPrototypes,
            'pictoPrototypes' => $pictoProtos,
            'citizenStati' => $citizenStati,
            'citizenRoles' => $citizenRoles,
            'citizenProfessions' => $professions,
            'citizen_id' => $citizen ? $citizen->getId() : -1,
        ]));        
    }

    /**
     * @Route("api/admin/users/{id}/citizen/headshot", name="admin_users_citizen_headshot", requirements={"id"="\d+"})
     * @param int $id
     * @param AdminActionHandler $admh
     * @return Response
     */
    public function users_citizen_headshot(int $id, AdminActionHandler $admh): Response
    {                
        if ($admh->headshot($this->getUser()->getId(), $id))
            return AjaxResponse::success();

        return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
    }

    /**
     * @Route("api/admin/users/{id}/citizen/engagement/{cid}", name="admin_users_citizen_engage", requirements={"id"="\d+","cid"="\d+"})
     * @param int $id
     * @param int $cid
     * @return Response
     */
    public function users_update_engagement(int $id, int $cid): Response
    {
        /** @var User $user */
        $user = $this->entity_manager->getRepository(User::class)->find($id);
        if (!$user) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ($user->getActiveCitizen()) $this->entity_manager->persist($user->getActiveCitizen()->setActive(false));

        if ($cid !== 0) {
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($cid);
            if (!$citizen || $citizen->getUser() !== $user || !$citizen->getAlive())
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
            $this->entity_manager->persist($citizen->setActive(true));
        }

        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/users/{id}/citizen/confirm_death", name="admin_users_citizen_confirm_death", requirements={"id"="\d+"})
     * @return Response
     */
    public function users_citizen_confirm_death(int $id, AdminActionHandler $admh): Response
    {                
        if ($admh->confirmDeath($this->getUser()->getId(), $id))
            return AjaxResponse::success();

        return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
    }

    /**
     * @Route("jx/admin/users/{id}/pictos/view", name="admin_users_pictos_view", requirements={"id"="\d+"})
     * @param int $id
     * @return Response
     */
    public function users_pictos_view(int $id): Response
    {
        $user = $this->entity_manager->getRepository(User::class)->find($id);

        $pictos = $this->entity_manager->getRepository(Picto::class)->findByUser($user);
        $protos = $this->entity_manager->getRepository(PictoPrototype::class)->findAll();
        usort($protos, function($a, $b) {
            return strcmp($this->translator->trans($a->getLabel(), [], 'game'), $this->translator->trans($b->getLabel(), [], 'game'));
        });

        return $this->render( 'ajax/admin/users/pictos.html.twig', $this->addDefaultTwigArgs("admin_users_pictos", [
            'user' => $user,
            'pictos' => $pictos,
            'pictoPrototypes' => $protos
        ]));        
    }

    /**
     * @Route("/api/admin/users/{id}/picto/give", name="admin_user_give_picto", requirements={"id"="\d+"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Add or remove an item from the bank
     * @param int $id User ID
     * @param JSONRequestParser $parser The Request Parser
     * @return Response
     */
    public function user_give_picto($id, JSONRequestParser $parser): Response
    {
        $user = $this->entity_manager->getRepository(User::class)->find($id);
        if(!$user) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        $prototype_id = $parser->get('prototype');
        $number = $parser->get('number', 1);

        /** @var PictoPrototype $pictoPrototype */
        $pictoPrototype = $this->entity_manager->getRepository(PictoPrototype::class)->find($prototype_id);

        $picto = $this->entity_manager->getRepository(Picto::class)->findByUserAndTownAndPrototype($user, null, $pictoPrototype);
        if (null === $picto) {
            $picto = new Picto();
            $picto->setPrototype($pictoPrototype)
                ->setPersisted(2)
                ->setUser($user);
            $user->addPicto($picto);
            $this->entity_manager->persist($user);
        }

        $picto->setCount($picto->getCount() + $number);

        $this->entity_manager->persist($picto);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

}
