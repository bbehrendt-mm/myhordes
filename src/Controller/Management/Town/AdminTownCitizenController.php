<?php

namespace App\Controller\Management\Town;

use App\Annotations\AdminLogProfile;
use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Entity\ActionEventLog;
use App\Entity\Citizen;
use App\Entity\CitizenEscortSettings;
use App\Entity\CitizenHomePrototype;
use App\Entity\CitizenHomeUpgrade;
use App\Entity\CitizenHomeUpgradeCosts;
use App\Entity\CitizenHomeUpgradePrototype;
use App\Entity\CitizenProfession;
use App\Entity\CitizenRole;
use App\Entity\CitizenStatus;
use App\Entity\CitizenVote;
use App\Entity\CitizenWatch;
use App\Entity\Complaint;
use App\Entity\ComplaintReason;
use App\Entity\DigTimer;
use App\Entity\HeroicActionPrototype;
use App\Entity\HeroSkillPrototype;
use App\Entity\Picto;
use App\Entity\PictoComment;
use App\Entity\PictoPrototype;
use App\Entity\SpecialActionPrototype;
use App\Entity\Town;
use App\Entity\TownRankingProxy;
use App\Entity\ZombieEstimation;
use App\Entity\Zone;
use App\Enum\Configuration\CitizenProperties;
use App\Enum\Configuration\TownSetting;
use App\Response\AjaxResponse;
use App\Service\CitizenHandler;
use App\Service\ConfMaster;
use App\Service\ErrorHelper;
use App\Service\EventProxyService;
use App\Service\JSONRequestParser;
use App\Service\TownHandler;
use App\Service\ZoneHandler;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[GateKeeperProfile(allow_during_attack: true)]
class AdminTownCitizenController extends AdminActionController
{
    /**
     * @param Town $town
     * @return Response
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/citizens', name: 'admin_town_citizens')]
    #[IsGranted('spy', 'town')]
    public function town_explorer_citizens(Town $town): Response {
		$disabled_profs = $this->conf->getTownConfiguration($town)->get(TownSetting::DisabledJobs);
		$professions = array_filter($this->entity_manager->getRepository( CitizenProfession::class )->findSelectable(),
			fn(CitizenProfession $p) => !in_array($p->getName(),$disabled_profs)
		);

		$complaints = [];
		$votes = [];
		$roles = [];

		/** @var CitizenRole $votableRole */
		foreach ($this->entity_manager->getRepository(CitizenRole::class)->findVotable() as $votableRole) {
			$votes[$votableRole->getId()] = [];
			$roles[$votableRole->getId()] = $votableRole;
		}

		foreach ($town->getCitizens() as $citizen) {
			$comp = $this->entity_manager->getRepository(Complaint::class)->findBy(['culprit' => $citizen]);
			if (count($comp) > 0)
				$complaints[$citizen->getUser()->getName()] = $comp;

			foreach ($roles as $roleId => $role) {
				/** @var CitizenVote $vote */
				$vote = $this->entity_manager->getRepository(CitizenVote::class)->findOneByCitizenAndRole($citizen, $role);
				if ($vote) {
					if(isset($votes[$roleId][$vote->getVotedCitizen()->getUser()->getName()])) {
						$votes[$roleId][$vote->getVotedCitizen()->getUser()->getName()][] = $vote->getAutor();
					} else {
						$votes[$roleId][$vote->getVotedCitizen()->getUser()->getName()] = [
							$vote->getAutor()
						];
					}
				}
			}
		}

		$all_complaints = array_map( fn(ActionEventLog $a) => [
			'on' => $a->getType() === ActionEventLog::ActionEventComplaintIssued,
			'from' => $a->getCitizen(),
			'to' => $this->entity_manager->getRepository(Citizen::class)->find($a->getOpt1()),
			'reason' => $this->entity_manager->getRepository(ComplaintReason::class)->find($a->getOpt2()),
			'time' => $a->getTimestamp()
		], $this->entity_manager->getRepository(ActionEventLog::class)->findBy([
			'type' => [ActionEventLog::ActionEventComplaintIssued,ActionEventLog::ActionEventComplaintRedacted],
			'citizen' => $town->getCitizens()->getValues(),
		], ['timestamp' => 'DESC']));

		$langs = [];
		$langs_alive = [];
		foreach ($town->getCitizens() as $citizen) {
			$lang = $citizen->getUser()->getLanguage() ?? 'multi';
			if (!isset($langs[$lang]))
				$langs[$lang] = $langs_alive[$lang] = 0;
			$langs[$lang]++;
			if ($citizen->getActive()) $langs_alive[$lang]++;
		}

		return $this->render('ajax/manage/towns/explorer_citizen.html.twig', $this->addDefaultTwigArgs(null, array_merge([
			'town' => $town,
			'day' => $town->getDay(),
			'tab' => "citizens",
			"itemPrototypes" => $this->getOrderedItemPrototypes($this->getUser()->getAdminLang() ?? $this->getUser()->getLanguage()),
			'citizenStati' => $this->getOrderedCitizenStatus($this->getUser()->getAdminLang() ?? $this->getUser()->getLanguage()),
			'citizenRoles' => $this->getOrderedCitizenRoles($this->getUser()->getAdminLang() ?? $this->getUser()->getLanguage()),
			'pictoPrototypes' => $this->getOrderedPictoPrototypes($this->getUser()->getAdminLang() ?? $this->getUser()->getLanguage()),
			'citizenProfessions' => $professions,
			'citizen_langs' => $langs,
			'citizen_langs_alive' => $langs_alive,
			'complaints' => $complaints,
			'all_complaints' => $all_complaints,
			'votes' => $votes,
		])));
	}

    /**
     * @param Town $town
     * @param JSONRequestParser $parser
     * @param ZoneHandler $handler
     * @param TownHandler $townHandler
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/teleport', name: 'admin_teleport_citizen')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function teleport_citizen(Town $town, JSONRequestParser $parser, ZoneHandler $handler, TownHandler $townHandler): Response
    {
        $targets = $parser->get_array('targets');
        if (empty($targets))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $to = $parser->get('to');
        if ($to !== 'town' && ( !is_array($to) || count($to) !== 2 ))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $target_zone = $to === 'town' ? null : $this->entity_manager->getRepository(Zone::class)->findOneByPosition($town,$to[0],$to[1]);
        if ($target_zone === null && $to !== 'town') return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $old_zones = [];
        $cp_target_zone = !$target_zone || $handler->isZoneUnderControl($target_zone);

        $escort = $parser->get('escort', false);

        foreach (array_unique($targets) as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);

            if (!$citizen || $citizen->getTown() !== $town)
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if ($citizen->getZone() === $target_zone) continue;

            $movers = [$citizen];

            if($escort) {
                foreach ($citizen->getValidLeadingEscorts() as $escort)
                    $movers[] = $escort->getCitizen();
            } else {
                foreach ($citizen->getLeadingEscorts() as $escort) {
                    $escort->getCitizen()->getEscortSettings()->setLeader(null);
                    $this->entity_manager->persist($escort->getCitizen());
                }
            }

            foreach ($movers as $mover){
                if ($mover->getZone()) {
                    if (!isset($old_zones[$mover->getZone()->getId()]))
                        $old_zones[$mover->getZone()->getId()] = [$mover->getZone(), $handler->isZoneUnderControl( $mover->getZone() )];

                    if ($dig_timer = $mover->getCurrentDigTimer()) {
                        $dig_timer->setPassive(true);
                        $this->entity_manager->persist( $dig_timer );
                    }

                    $mover->getZone()->removeCitizen( $mover );
                }

                if ($target_zone) $target_zone->addCitizen( $mover );
                $this->entity_manager->persist($mover);
            }
        }

        foreach ($old_zones as $old_zone_data) {
            $handler->handleCitizenCountUpdate($old_zone_data[0],$old_zone_data[1]);
            $this->entity_manager->persist($old_zone_data[0]);
        }

        if ($target_zone) {
            $upgraded_map = $townHandler->getBuilding($town,'item_electro_#00', true);
            $target_zone
                ->setDiscoveryStatus( Zone::DiscoveryStateCurrent )
                ->setZombieStatus( max($upgraded_map ? Zone::ZombieStateExact : Zone::ZombieStateEstimate, $target_zone->getZombieStatus() ) );
            $handler->handleCitizenCountUpdate($target_zone,$cp_target_zone);
            $this->entity_manager->persist($target_zone);
        }

        $this->clearTownCaches($town);

        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser
     * @param ConfMaster $cf
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/alias', name: 'admin_alias_citizen')]
    #[IsGranted('ROLE_SUB_ADMIN')]
    #[AdminLogProfile(enabled: true)]
    public function alias_citizen(Town $town, JSONRequestParser $parser, ConfMaster $cf): Response
    {
        $alias = $parser->trimmed('alias');
        $targets = $parser->get_array('targets');
        if ($alias != null && !$alias)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        if (empty($targets))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        if (count($targets) > 1)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var Citizen $citizen */
        $citizen = $this->entity_manager->getRepository(Citizen::class)->find($targets[0]);

        $town_conf = $cf->getTownConfiguration($citizen->getTown());

        $citizen_alias_active = $town_conf->get(TownSetting::OptFeatureCitizenAlias);

        if(!$citizen_alias_active)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $citizen->setAlias($alias);
        $this->clearTownCaches($town);

        try {
            $this->entity_manager->persist( $citizen );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }
        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser
     * @return Response
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/citizen_infos', name: 'get_citizen_infos')]
    #[IsGranted('spy', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function get_citizen_infos(Town $town, JSONRequestParser  $parser): Response {
        $citizen_id = $parser->get('citizen_id', -1);
        $citizen = $this->entity_manager->getRepository(Citizen::class)->find($citizen_id);

        if(!$citizen || $citizen->getTown() !== $town)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $rucksack = $this->renderView("ajax/game/inventory.html.twig", [
            'size' => $this->inventory_handler->getSize( $citizen->getInventory() ),
            'items' => $citizen->getInventory()->getItems()
        ]);

        $chest = $this->renderView("ajax/game/inventory.html.twig", [
            'size' => $this->inventory_handler->getSize( $citizen->getHome()->getChest() ),
            'items' => $citizen->getHome()->getChest()->getItems()
        ]);

        $pictos = $this->renderView("ajax/manage/towns/distinctions.html.twig", [
            'pictos' => $this->entity_manager->getRepository(Picto::class)->findPictoByUserAndTown($citizen->getUser(), $citizen->getTown()),
        ]);

        $props = $this->renderView("ajax/manage/towns/citizen_props.html.twig", [
            'skills' => $this->entity_manager->getRepository(HeroSkillPrototype::class)->findBy(['id' =>  $citizen->property( CitizenProperties::ActiveSkillIDs )]),
            'props' => array_combine(
                array_map( fn(CitizenProperties $p) => $p->value, CitizenProperties::validCases() ),
                array_map( fn(CitizenProperties $p) => json_encode( $citizen->property($p) ), CitizenProperties::validCases() ),
            ),
        ]);

        return AjaxResponse::success(true, [
            'desc' => $citizen->getHome()->getDescription(),
            'rucksack' => $rucksack,
            'chest' => $chest,
            'pictos' => $pictos,
            'props' => $props,
        ]);
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/clear_citizen_attribs', name: 'clear_citizen_attribs')]
    #[IsGranted('ROLE_CROW')]
    #[AdminLogProfile(enabled: true)]
    public function clear_citizen_attribs(Town $town, JSONRequestParser  $parser) {
        $id = $parser->get_int('id');
        $clear = $parser->get('clear');

        $citizen = $this->entity_manager->getRepository(Citizen::class)->find($id);
        if (!$citizen || $citizen->getTown() !== $town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        switch ($clear) {
            case 'citizen-custom-message':
                $this->entity_manager->persist( $citizen->getHome()->setDescription( null ) );
                $this->entity_manager->persist( $citizen->setLastWords( '' ) );
                $this->entity_manager->persist( $citizen->getRankingEntry()->setLastWords( null ) );
                break;
            default:
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        $this->clearTownCaches($town);
        $this->entity_manager->flush();
        return AjaxResponse::success();
    }


    /**
     * @param Town $town
     * @param JSONRequestParser $parser
     * @param CitizenHandler $handler
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/modify_prof', name: 'admin_modify_profession')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function modify_profession(Town $town, JSONRequestParser $parser, CitizenHandler $handler): Response
    {
        $pro_id = $parser->get_int('profession');
        $targets = $parser->get_array('targets');

        $disabled_profs = $this->conf->getTownConfiguration($town)->get(TownSetting::DisabledJobs);

        if (empty($targets))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var CitizenProfession $profession */
        $profession = ($pro_id === -1 ? $this->entity_manager->getRepository(CitizenProfession::class)->findDefault() : $this->entity_manager->getRepository(CitizenProfession::class)->find($pro_id));
        if (!$profession || in_array($profession->getName(), $disabled_profs))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        foreach (array_unique($targets) as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);
            if (!$citizen || $citizen->getTown() !== $town)
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if ($citizen->getProfession() !== $profession) {
                $handler->applyProfession($citizen, $profession);
                $this->entity_manager->persist($citizen);
            }
        }

        $this->clearTownCaches($town);
        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @param int $id Town ID
     * @param JSONRequestParser $parser The Request Parser
     * @param EventProxyService $proxy
     * @return Response
     */
    #[Route(path: 'api/manage/town/proxy/{id<\d+>}/picto/give', name: 'admin_town_give_picto')]
    #[IsGranted("ROLE_SUB_ADMIN")]
    #[AdminLogProfile(enabled: true)]
    public function town_give_picto(int $id, JSONRequestParser $parser, EventProxyService $proxy): Response
    {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        $townRanking = $this->entity_manager->getRepository(TownRankingProxy::class)->find($id);
        /** @var Town $town */
        if (!$town && !$townRanking) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        if (!$town && $townRanking)
            $town = $townRanking;

        $prototype_id = $parser->get('prototype');
        $number = $parser->get('number', 1);
        $to = $parser->get_array( 'to' );
        $text = $parser->trimmed( 'text' );

        /** @var PictoPrototype $pictoPrototype */
        $pictoPrototype = $this->entity_manager->getRepository(PictoPrototype::class)->find($prototype_id);

        foreach ($town->getCitizens() as $citizen) {
            /** @var Citizen $citizen */
            if (!in_array( $citizen->getId(), $to )) continue;

            $picto = $this->entity_manager->getRepository(Picto::class)->findByUserAndTownAndPrototype($citizen->getUser(), $town, $pictoPrototype, false);
            if (null === $picto) {
                $picto = new Picto();
                $picto->setPrototype($pictoPrototype)
                    ->setPersisted(2)
                    ->setUser($citizen->getUser());
                if (is_a($town, Town::class))
                    $picto->setOld($town->getSeason() === null)->setTown($town);
                else
                    $picto->setTownEntry($town);
                $citizen->getUser()->addPicto($picto);
            }

            $picto->setCount($picto->getCount() + $number)->setDisabled(false)->setManual(true);

            if (!empty($text)) {

                $comment = ($picto->getId() !== null ? $this->entity_manager->getRepository(PictoComment::class)->findOneBy(['picto' => $picto]) : null)
                    ?? (new PictoComment())->setPicto( $picto )->setOwner( $citizen->getUser() )->setDisplay( true );

                $comment->setText( $text );
                $this->entity_manager->persist($comment);
            }

            $this->entity_manager->persist($citizen->getUser());
            $this->entity_manager->persist($picto);
        }

        $this->entity_manager->flush();

        foreach ($town->getCitizens() as $citizen) {
            /** @var Citizen $citizen */
            if (!in_array($citizen->getId(), $to)) continue;

            $proxy->pictosPersisted( $citizen->getUser(), $town->getSeason() );
        }

        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser The Request Parser
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/home/manage', name: 'admin_town_manage_home')]
    #[IsGranted('ROLE_SUB_ADMIN')]
    #[AdminLogProfile(enabled: true)]
    public function town_manage_home(Town $town, JSONRequestParser $parser): Response
    {
        $target   = $parser->get('target');
        $citizens = $parser->get_array('citizen');
        $dif      = $parser->get_int('dif', 0);
        $t_id     = $parser->get_int('id', -1);

        if ($dif === 0) return AjaxResponse::success();

        foreach ($citizens as $cid) {

            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($cid);
            if (!$citizen || $citizen->getTown() !== $town || !$citizen->getAlive()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if (!$citizen->getHome()->getPrototype()->getAllowSubUpgrades() && in_array($target, ['proto','sub']))
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            switch ($target) {

                case 'home':
                    $new_proto = $this->entity_manager->getRepository(CitizenHomePrototype::class)->findOneByLevel( $citizen->getHome()->getPrototype()->getLevel() + $dif );
                    if (!$new_proto) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    $citizen->getHome()->setPrototype( $new_proto );
                    $this->entity_manager->persist($citizen->getHome());
                    break;

                case 'sub':
                    $upgrade = $this->entity_manager->getRepository(CitizenHomeUpgrade::class)->find($t_id);
                    if ($upgrade === null || $upgrade->getHome() !== $citizen->getHome())
                        return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    if ($upgrade->getLevel() + $dif <= 0) {
                        $citizen->getHome()->removeCitizenHomeUpgrade($upgrade);
                        $upgrade->setHome(null);
                        $this->entity_manager->persist($citizen->getHome());
                        $this->entity_manager->remove($upgrade);
                    } else {
                        $level_proto = $this->entity_manager->getRepository(CitizenHomeUpgradeCosts::class)->findOneBy(['prototype' => $upgrade->getPrototype(), 'level' => $upgrade->getLevel() + $dif]);

                        if ($level_proto === null) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                        $this->entity_manager->persist($upgrade->setLevel( $upgrade->getLevel() + $dif ));
                    }

                    break;

                case 'proto':
                    $upgrade_proto = $this->entity_manager->getRepository(CitizenHomeUpgradePrototype::class)->find($t_id);
                    if ($upgrade_proto === null)
                        return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    foreach ($citizen->getHome()->getCitizenHomeUpgrades() as $upgrade) if ($upgrade->getPrototype() === $upgrade_proto)
                        return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    $level_proto = $this->entity_manager->getRepository(CitizenHomeUpgradeCosts::class)->findOneBy(['prototype' => $upgrade_proto, 'level' => $dif]);

                    if ($level_proto === null) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    $new_upgrade = (new CitizenHomeUpgrade())->setLevel($dif)->setPrototype($upgrade_proto);
                    $citizen->getHome()->addCitizenHomeUpgrade($new_upgrade);

                    $this->entity_manager->persist($citizen->getHome());
                    $this->entity_manager->persist($new_upgrade);
                    break;

                default: return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
            }
        }

        $this->clearTownCaches($town);
        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser The Request Parser
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/status/manage', name: 'admin_town_manage_status')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function town_manage_status(Town $town, JSONRequestParser $parser): Response
    {
        $status_id = $parser->get_int('status');
        $targets = $parser->get_array('targets', []);

        $control = $parser->get_int('control', 0) > 0;

        /** @var CitizenStatus $citizenStatus */
        $citizenStatus = $this->entity_manager->getRepository(CitizenStatus::class)->find($status_id);
        if (!$citizenStatus) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        foreach ($targets as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);
            if (!$citizen || $citizen->getTown() !== $town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if ($control) $this->citizen_handler->inflictStatus( $citizen, $citizenStatus, true );
            else $this->citizen_handler->removeStatus( $citizen, $citizenStatus );

            $this->entity_manager->persist($citizen);
        }

        $this->clearTownCaches($town);
        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    private function town_manage_pseudo_role(Town $town, JSONRequestParser $parser, TownHandler $townHandler): Response {
        $targets = $parser->get_array('targets');
        $control = $parser->get_int('control', 0) > 0;

        $citizens = [];
        foreach ($targets as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);
            if (!$citizen || $citizen->getTown() !== $town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            $citizens[] = $citizen;
        }

        switch ($parser->get('role')) {

            case '_ban_':
                $null = null;
                foreach ($citizens as $citizen) {
                    if($control) {
                        $this->citizen_handler->updateBanishment($citizen, null, null, $null, true);
                    } else {
                        $citizen->setBanished(false);
                    }
                    $this->entity_manager->persist($citizen);
                }
                break;
            case '_esc_':
                $c1 = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'tg_hide']);
                $c2 = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'tg_tomb']);

                foreach ($citizens as $citizen) {
                    if (!$citizen->getZone() || !$citizen->getAlive() || $citizen->activeExplorerStats() || $citizen->getStatus()->contains($c1) || $citizen->getStatus()->contains($c2)) continue;

                    if (!$control) {
                        if ($citizen->getEscortSettings()) $this->entity_manager->remove($citizen->getEscortSettings());
                        $citizen->setEscortSettings(null);

                    } elseif (!$citizen->getEscortSettings())
                        $citizen->setEscortSettings((new CitizenEscortSettings())->setCitizen($citizen)->setAllowInventoryAccess(true)->setForceDirectReturn(false));
                    else $citizen->getEscortSettings()->setAllowInventoryAccess(true)->setForceDirectReturn(false);
                    $this->entity_manager->persist($citizen);
                }
                break;
            case '_nw_':
                $watchers = $this->entity_manager->getRepository(CitizenWatch::class)->findCurrentWatchers($town);
                foreach ($citizens as $citizen) {
                    $activeCitizenWatcher = null;

                    foreach ($watchers as $watcher)
                        if ($watcher->getCitizen() === $citizen){
                            $activeCitizenWatcher = $watcher;
                            break;
                        }

                    if ($control) {
                        if ($activeCitizenWatcher) continue;
                        $citizenWatch = (new CitizenWatch())->setCitizen($citizen)->setDay($town->getDay());
                        $town->addCitizenWatch($citizenWatch);
                        $this->entity_manager->persist($citizenWatch);
                    } else {
                        if ($activeCitizenWatcher === null) continue;
                        $town->removeCitizenWatch($activeCitizenWatcher);
                        $citizen->removeCitizenWatch($activeCitizenWatcher);
                        $this->entity_manager->remove($activeCitizenWatcher);
                    }

                    $this->entity_manager->persist($citizen);
                }
                break;
            case '_sh_':
                $armag_day   = $this->entity_manager->getRepository(SpecialActionPrototype::class)->findOneBy(['name' => "special_armag_d"]);
                $armag_night = $this->entity_manager->getRepository(SpecialActionPrototype::class)->findOneBy(['name' => "special_armag_n"]);

                foreach ($this->entity_manager->getRepository(HeroicActionPrototype::class)->findAll() as $heroic_action)
                    foreach ($citizens as $citizen) {
                        $citizen->addHeroicAction( $heroic_action );
                        $this->citizen_handler->removeStatus($citizen,'tg_hero');

                        $citizen->addSpecialAction($armag_day);
                        $citizen->addSpecialAction($armag_night);

                        $this->entity_manager->persist( $citizen );
                    }
                break;
            case '_wt_':
                if (!$townHandler->getBuilding($town,'item_tagger_#00'))
                    return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                /** @var ZombieEstimation $est */
                $est = $this->entity_manager->getRepository(ZombieEstimation::class)->findOneBy(['town' => $town, 'day' => $town->getDay()]);
                if (!$est) return AjaxResponse::error( ErrorHelper::ErrorInternalError );

                foreach ($citizens as $citizen) {
                    if (!$control)
                        $est->removeCitizen($citizen);
                    else $est->addCitizen($citizen);
                }

                $this->entity_manager->persist($est);

                break;
            case '_rst_':
                if ($control)
                    foreach ($citizens as $citizen) {
                        $locked = $citizen->hasStatus( 'tg_stats_locked' );
                        if ($locked)
                            $this->citizen_handler->removeStatus( $citizen, 'tg_stats_locked' );

                        foreach ($citizen->getStatus() as $status)
                            if (!$status->getHidden()) $this->citizen_handler->removeStatus( $citizen, $status );

                        if ($locked) $this->citizen_handler->inflictStatus( $citizen, 'tg_stats_locked' );
                        $this->entity_manager->persist($citizen);
                    }

                break;
            case '_dig_':
                if ($control)
                    foreach ($citizens as $citizen) {
                        $dig = ($citizen->getZone() && !$citizen->getZone()->isTownZone())
                            ? ($citizen->getCurrentDigTimer() ?? (new DigTimer())->setZone( $citizen->getZone() )->setCitizen( $citizen ))
                            : null;
                        if ($dig) {
                            $dig->setTimestamp(new \DateTime('now - 24hours'));
                            $this->entity_manager->persist($dig);
                        }

                    }
                break;
            default: return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        }

        $this->clearTownCaches($town);
        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser The Request Parser
     * @param TownHandler $handler
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/role/manage', name: 'admin_town_manage_role')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function town_manage_role(Town $town, JSONRequestParser $parser, TownHandler $handler): Response
    {
        if (in_array($parser->get('role'), ['_ban_','_esc_','_nw_','_sh_','_wt_','_rst_', '_dig_'] ))
            return $this->town_manage_pseudo_role($town,$parser,$handler);

        $role_id = $parser->get_int('role');
        $targets = $parser->get_array('targets');

        $control = $parser->get_int('control', 0) > 0;

        /** @var CitizenRole $citizenRole */
        $citizenRole = $this->entity_manager->getRepository(CitizenRole::class)->find($role_id);
        if (!$citizenRole) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        foreach ($targets as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);
            if (!$citizen || $citizen->getTown() !== $town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if ($control) $this->citizen_handler->addRole($citizen, $citizenRole);
            else $this->citizen_handler->removeRole($citizen, $citizenRole);

            $this->entity_manager->persist($citizen);
        }

        $this->clearTownCaches($town);
        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser The Request Parser
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/pp/alter', name: 'admin_town_alter_pp')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function town_alter_points(Town $town, JSONRequestParser $parser): Response
    {
        $point = $parser->get('point', '');
        if (!in_array($point, ['ap','bp','mp','sp','gh','cc','cn'])) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $number = $parser->get_int('num', 6);

        $control = $parser->get_int('control', 0);
        if (!in_array($control, [-1,0,1])) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $targets = $parser->get_array('targets');

        foreach ($targets as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);
            if (!$citizen || $citizen->getTown() !== $town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if (!$citizen->getActive()) continue;

            switch ($point) {
                case 'ap': $this->citizen_handler->setAP($citizen, false, ($control === 0) ? $number : $citizen->getAp() + $control * $number); break;
                case 'bp': $this->citizen_handler->setBP($citizen, false, ($control === 0) ? $number : $citizen->getBp() + $control * $number); break;
                case 'mp': $this->citizen_handler->setPM($citizen, false, ($control === 0) ? $number : $citizen->getPm() + $control * $number); break;
                case 'sp': $this->citizen_handler->setSP($citizen, false, ($control === 0) ? $number : $citizen->getSp() + $control * $number); break;
                case 'gh': $citizen->setGhulHunger( max(0, ($control === 0) ? $number : $citizen->getGhulHunger() + $control * $number) ); break;
                case 'cc': $citizen->setCampingChance( min(100,max(0, ($control === 0) ? $number : $citizen->getCampingChance() + $control * $number)) / 100.0 ); break;
                case 'cn': $citizen->setCampingCounter( max(0, ($control === 0) ? $number : $citizen->getCampingCounter() + $control * $number) ); break;
                default: break;
            }

            $this->entity_manager->persist($citizen);
        }

        $this->clearTownCaches($town);
        $this->entity_manager->flush();
        return AjaxResponse::success();
    }
}
