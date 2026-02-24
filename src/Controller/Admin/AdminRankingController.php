<?php

namespace App\Controller\Admin;

use App\Annotations\AdminLogProfile;
use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Entity\ActionEventLog;
use App\Entity\AdminReport;
use App\Entity\BlackboardEdit;
use App\Entity\Building;
use App\Entity\BuildingPrototype;
use App\Entity\Citizen;
use App\Entity\CitizenEscortSettings;
use App\Entity\CitizenHome;
use App\Entity\CitizenHomePrototype;
use App\Entity\CitizenHomeUpgrade;
use App\Entity\CitizenHomeUpgradeCosts;
use App\Entity\CitizenHomeUpgradePrototype;
use App\Entity\CitizenProfession;
use App\Entity\CitizenRankingProxy;
use App\Entity\CitizenRole;
use App\Entity\CitizenStatus;
use App\Entity\CitizenVote;
use App\Entity\CitizenWatch;
use App\Entity\Complaint;
use App\Entity\ComplaintReason;
use App\Entity\CouncilEntry;
use App\Entity\DigTimer;
use App\Entity\EventActivationMarker;
use App\Entity\ExpeditionRoute;
use App\Entity\HeroExperienceEntry;
use App\Entity\HeroicActionPrototype;
use App\Entity\HeroSkillPrototype;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\ItemCategory;
use App\Entity\ItemPrototype;
use App\Entity\Picto;
use App\Entity\PictoComment;
use App\Entity\PictoPrototype;
use App\Entity\RuinExplorerStats;
use App\Entity\SpecialActionPrototype;
use App\Entity\Town;
use App\Entity\TownRankingProxy;
use App\Entity\User;
use App\Entity\ZombieEstimation;
use App\Entity\Zone;
use App\Enum\Configuration\CitizenProperties;
use App\Enum\Configuration\MyHordesSetting;
use App\Enum\Configuration\TownSetting;
use App\Enum\EventStages\BuildingEffectStage;
use App\Enum\EventStages\BuildingValueQuery;
use App\Enum\ItemPoisonType;
use App\Response\AjaxResponse;
use App\Service\Actions\Cache\InvalidateTagsInAllPoolsAction;
use App\Service\Actions\Game\GenerateTownNameAction;
use App\Service\Actions\Game\InitializeTownBuildingsAction;
use App\Service\AdminLog;
use App\Service\CitizenHandler;
use App\Service\ConfMaster;
use App\Service\CrowService;
use App\Service\ErrorHelper;
use App\Service\EventProxyService;
use App\Service\GameFactory;
use App\Service\GameProfilerService;
use App\Service\GazetteService;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\JSONRequestParser;
use App\Service\Maps\MapMaker;
use App\Service\Maps\MazeMaker;
use App\Service\NightlyHandler;
use App\Service\RandomGenerator;
use App\Service\TownHandler;
use App\Service\ZoneHandler;
use App\Structures\BankItem;
use App\Structures\EventConf;
use App\Structures\TownSetup;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[GateKeeperProfile(allow_during_attack: true)]
class AdminRankingController extends AdminActionController
{
    protected function clearTownCaches(Town $town): void
    {
        ($this->clear)("town_{$town->getId()}");
    }

    /**
     * @param TownRankingProxy $town_proxy
     * @param int $act
     * @return Response
     */
    #[Route(path: 'api/admin/town/proxy/{id<\d+>}/event-tag/{act}', name: 'admin_town_event_tag_control', requirements: ['act' => '\d+'])]
    #[IsGranted('ROLE_CROW')]
    #[AdminLogProfile(enabled: true)]
    public function ranking_event_toggle_town(TownRankingProxy $town_proxy, int $act): Response
    {
        $town_proxy->setEvent( $act !== 0 );
        $this->entity_manager->persist($town_proxy);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @param TownRankingProxy $town_proxy
     * @param int $act
     * @param JSONRequestParser $request
     * @param InvalidateTagsInAllPoolsAction $uncache
     * @return Response
     */
    #[Route(path: 'api/admin/town/proxy/{id<\d+>}/unrank/{act}', name: 'admin_town_town_ranking_control', requirements: ['act' => '\d+'])]
    #[IsGranted('ROLE_CROW')]
    #[AdminLogProfile(enabled: true)]
    public function ranking_toggle_town(TownRankingProxy $town_proxy, int $act, JSONRequestParser $request, InvalidateTagsInAllPoolsAction $uncache): Response
    {
        $flag = $request->get("flag") ?? TownRankingProxy::DISABLE_RANKING;

        //$town_proxy->setDisabled( $act !== 0 );
        if($act)
            $town_proxy->addDisableFlag($flag);
        else
            $town_proxy->removeDisableFlag($flag);

        $this->entity_manager->persist($town_proxy);
        $this->entity_manager->flush();

        foreach ($town_proxy->getCitizens() as $citizen) {
            if(($flag & TownRankingProxy::DISABLE_SOULPOINTS) === TownRankingProxy::DISABLE_SOULPOINTS) {
                $this->entity_manager->persist($citizen->getUser()
                    ->setSoulPoints($this->user_handler->fetchSoulPoints($citizen->getUser(), false))
                    ->setImportedSoulPoints($this->user_handler->fetchImportedSoulPoints($citizen->getUser()))
                );
            }

            if(($flag & TownRankingProxy::DISABLE_PICTOS) === TownRankingProxy::DISABLE_PICTOS) {
                foreach ($this->entity_manager->getRepository(Picto::class)->findNotPendingByUserAndTown($citizen->getUser(), $town_proxy) as $picto)
                    if (!$picto->isManual())
                        $this->entity_manager->persist($picto->setDisabled($citizen->hasDisableFlag(CitizenRankingProxy::DISABLE_PICTOS) || $town_proxy->hasDisableFlag(TownRankingProxy::DISABLE_PICTOS)));
            }

            if(($flag & TownRankingProxy::DISABLE_HXP) === TownRankingProxy::DISABLE_HXP) {
                foreach ($this->entity_manager->getRepository(HeroExperienceEntry::class)->findBy(['town' => $town_proxy]) as $hxp)
                    $this->entity_manager->persist($hxp->setDisabled($citizen->hasDisableFlag(CitizenRankingProxy::DISABLE_HXP) || $town_proxy->hasDisableFlag(TownRankingProxy::DISABLE_HXP)));
                ($uncache)("user-{$citizen->getUser()->getId()}-hxp");
            }
        }


        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @param TownRankingProxy $town_proxy
     * @param int $cid
     * @param int $act
     * @param JSONRequestParser $parser
     * @param InvalidateTagsInAllPoolsAction $uncache
     * @return Response
     */
    #[Route(path: 'api/admin/town/proxy/{id<\d+>}/unrank_single/{cid}/{act}', name: 'admin_town_citizen_ranking_control', requirements: ['cid' => '\d+', 'act' => '\d+'])]
    #[IsGranted('ROLE_CROW')]
    #[AdminLogProfile(enabled: true)]
    public function ranking_toggle_citizen(TownRankingProxy $town_proxy, int $cid, int $act, JSONRequestParser $parser, InvalidateTagsInAllPoolsAction $uncache): Response
    {
        $citizen_proxy = $this->entity_manager->getRepository(CitizenRankingProxy::class)->find($cid);
        if (!$citizen_proxy) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (!$town_proxy->getCitizens()->contains($citizen_proxy))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $flag = $parser->get('flag');
        if (!$flag)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ($act) {
            $citizen_proxy->addDisableFlag($flag);
        } else {
            $citizen_proxy->removeDisableFlag($flag);
        }

        if (!$citizen_proxy->hasDisableFlag(CitizenRankingProxy::DISABLE_NOTHING) && $citizen_proxy->getResetMarker()) {
            $this->entity_manager->remove( $citizen_proxy->getResetMarker() );
            $citizen_proxy->setResetMarker(null);
        }
        $this->entity_manager->persist($citizen_proxy);
        $this->entity_manager->flush();

        if(($flag & CitizenRankingProxy::DISABLE_SOULPOINTS) === CitizenRankingProxy::DISABLE_SOULPOINTS) {
            $this->entity_manager->persist($citizen_proxy->getUser()
                ->setSoulPoints( $this->user_handler->fetchSoulPoints( $citizen_proxy->getUser(), false ) )
                ->setImportedSoulPoints( $this->user_handler->fetchImportedSoulPoints( $citizen_proxy->getUser() ) )
            );
        }
        if(($flag & CitizenRankingProxy::DISABLE_PICTOS) === CitizenRankingProxy::DISABLE_PICTOS) {
            foreach ($this->entity_manager->getRepository(Picto::class)->findNotPendingByUserAndTown($citizen_proxy->getUser(), $town_proxy) as $picto)
                if (!$picto->isManual())
                    $this->entity_manager->persist($picto->setDisabled($citizen_proxy->hasDisableFlag(CitizenRankingProxy::DISABLE_PICTOS)));
        }
        if(($flag & CitizenRankingProxy::DISABLE_HXP) === CitizenRankingProxy::DISABLE_HXP) {
            foreach ($this->entity_manager->getRepository(HeroExperienceEntry::class)->findBy(['citizen' => $citizen_proxy]) as $hxp)
                $this->entity_manager->persist($hxp->setDisabled($citizen_proxy->hasDisableFlag(CitizenRankingProxy::DISABLE_HXP)));
            ($uncache)("user-{$citizen_proxy->getUser()->getId()}-hxp");
        }

        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

}
