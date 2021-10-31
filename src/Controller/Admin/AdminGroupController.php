<?php

namespace App\Controller\Admin;

use App\Annotations\GateKeeperProfile;
use App\Controller\CustomAbstractController;
use App\Entity\OfficialGroup;
use App\Entity\OfficialGroupMessageLink;
use App\Entity\Season;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Entity\UserGroupAssociation;
use App\Response\AjaxResponse;
use App\Service\ErrorHelper;
use App\Service\JSONRequestParser;
use App\Service\MediaService;
use App\Service\PermissionHandler;
use App\Service\RandomGenerator;
use App\Structures\MyHordesConf;
use App\Translation\T;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 * @GateKeeperProfile(allow_during_attack=true)
 */
class AdminGroupController extends CustomAbstractController
{
    /**
     * @Route("jx/admin/groups/all", name="admin_group_view")
     * @return Response
     */
    public function groups_view(PermissionHandler $perm): Response
    {
        $groups = $this->entity_manager->getRepository(OfficialGroup::class)->findAll();

        $member = [];
        $members = [];
        foreach ($groups as $group) {
            $members[$group->getId()] = (!$group->getAnon() || $this->isGranted('ROLE_ADMIN') ) ? $perm->usersInGroup($group->getUsergroup()) : [];
            $member[$group->getId()]  = ($perm->userInGroup( $this->getUser(), $group->getUsergroup() ));
        }

        return $this->render( 'ajax/admin/groups/list.html.twig', $this->addDefaultTwigArgs(null, [
            'groups' => $groups,
            'group_members' => $members,
            'in_group' => $member,
        ]));
    }

    /**
     * @Route("jx/admin/groups/new", name="admin_group_new")
     * @return Response
     */
    public function group_new(): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) $this->redirect($this->generateUrl('admin_group_view'));
        return $this->render( 'ajax/admin/groups/edit.html.twig', $this->addDefaultTwigArgs(null, [
            'current_group' => null,
            'members' => [],
            'icon_max_size' => $this->conf->getGlobalConf()->get(MyHordesConf::CONF_AVATAR_SIZE_UPLOAD, 3145728)
        ]));
    }

    /**
     * @Route("jx/admin/groups/{id<-?\d+>}", name="admin_group_edit")
     * @return Response
     */
    public function group_edit(int $id, PermissionHandler $perm): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) $this->redirect($this->generateUrl('admin_group_view'));
        $group_meta = $this->entity_manager->getRepository(OfficialGroup::class)->find($id);
        if (!$group_meta)  $this->redirect($this->generateUrl('admin_group_view'));

        return $this->render( 'ajax/admin/groups/edit.html.twig', $this->addDefaultTwigArgs(null, [
            'current_group' => $group_meta,
            'members' => $perm->usersInGroup($group_meta->getUsergroup()),
            'icon_max_size' => $this->conf->getGlobalConf()->get(MyHordesConf::CONF_AVATAR_SIZE_UPLOAD, 3145728)
        ]));
    }

    /**
     * @Route("api/admin/groups/update/{id<-?\d+>}", name="admin_group_update")
     * @param int $id
     * @return Response
     */
    public function update_group(int $id, JSONRequestParser $parser, MediaService $media, PermissionHandler $perm): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if (!$parser->has_all(['name','lang','desc'], true)) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($id < 0) {

            $base_group = (new UserGroup())->setType(UserGroup::GroupOfficialGroup);
            $group_meta = (new OfficialGroup())->setUsergroup($base_group);

        } else {
            $group_meta = $this->entity_manager->getRepository(OfficialGroup::class)->find($id);
            if (!$group_meta) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

            $base_group = $group_meta->getUsergroup();
        }

        $base_group->setName(trim($parser->get('name')));
        $group_meta
            ->setAnon( (bool)$parser->get('anon') )
            ->setLang( $parser->get('lang', 'multi', ['de','en','fr','es','multi']) )
            ->setDescription($parser->get('desc'));

        $am = $parser->get_array('m_add');
        $rm = $parser->get_array('m_rem');

        $messages = [];
        if (!empty($am) || !empty($rm))
            $messages = array_map(fn(OfficialGroupMessageLink $m) => $m->getMessageGroup(), $this->entity_manager->getRepository(OfficialGroupMessageLink::class)->findBy(['officialGroup' => $group_meta]));

        foreach ($am as $add_id) {

            if (in_array($add_id, $rm)) continue;

            $target_user = $this->entity_manager->getRepository(User::class)->find((int)$add_id);
            if (!$target_user) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

            if ($perm->userInGroup($target_user, $base_group)) continue;

            $perm->associate( $target_user, $base_group, UserGroupAssociation::GroupAssociationTypeOfficialGroupMember );
            foreach ($messages as $message)
                if (!$perm->userInGroup($target_user, $message))
                    $perm->associate( $target_user, $message, UserGroupAssociation::GroupAssociationTypeOfficialGroupMessageMember )
                        ->setRef1( $message->getRef2() < (time() - 5184000) ? $message->getRef1() : 0  )->setRef2( 0 );
        }

        foreach ($rm as $rem_id) {
            $target_user = $this->entity_manager->getRepository(User::class)->find((int)$rem_id);
            if (!$target_user) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

            if (!$perm->userInGroup($target_user, $base_group)) continue;

            $perm->disassociate( $target_user, $base_group );
            foreach ($messages as $message)
                if ($this->entity_manager->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $target_user, 'association' => $message, 'associationType' => UserGroupAssociation::GroupAssociationTypeOfficialGroupMessageMember]))
                    $perm->disassociate($target_user, $message);
        }

        if ($parser->has('icon') && $parser->get('icon') !== false) {
            $payload = $parser->get_base64('icon');
            if (strlen( $payload ) > $this->conf->getGlobalConf()->get(MyHordesConf::CONF_AVATAR_SIZE_UPLOAD))
                return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

            if ($media->resizeImage( $payload, function(int &$w, int &$h, bool &$fit): bool {
                    if ($w / $h < 0.1 || $h / $w < 0.1 || $h < 16 || $w < 16)
                        return false;

                    if ( max($w,$h) > 200 || min($w,$h < 90) )
                        $w = $h = min(200,max(90,$w,$h));

                    return $fit = true;
                }, $w_final, $h_final, $processed_format ) !== MediaService::ErrorNone)
                return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

            $group_meta->setIcon($payload)->setAvatarName(md5($payload))->setAvatarExt( strtolower( $processed_format ) );
        }

        try {
            $this->entity_manager->persist($group_meta);
            $this->entity_manager->persist($base_group);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }
}