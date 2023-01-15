<?php

namespace App\Controller;

use App\Annotations\GateKeeperProfile;
use App\Annotations\Semaphore;
use App\Entity\ActionCounter;
use App\Entity\AdminReport;
use App\Entity\CampingActionPrototype;
use App\Entity\CauseOfDeath;
use App\Entity\Citizen;
use App\Entity\CitizenHomeUpgrade;
use App\Entity\CitizenHomeUpgradePrototype;
use App\Entity\CitizenRankingProxy;
use App\Entity\EventActivationMarker;
use App\Entity\ExpeditionRoute;
use App\Entity\FoundRolePlayText;
use App\Entity\HelpNotificationMarker;
use App\Entity\HeroicActionPrototype;
use App\Entity\HomeActionPrototype;
use App\Entity\HomeIntrusion;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\ItemAction;
use App\Entity\ItemGroupEntry;
use App\Entity\ItemPrototype;
use App\Entity\ItemTargetDefinition;
use App\Entity\LogEntryTemplate;
use App\Entity\PictoPrototype;
use App\Entity\PrivateMessage;
use App\Entity\Recipe;
use App\Entity\RuinZone;
use App\Entity\SpecialActionPrototype;
use App\Entity\TownLogEntry;
use App\Entity\ZombieEstimation;
use App\Entity\Zone;
use App\Entity\ZoneTag;
use App\Enum\AdminReportSpecification;
use App\Response\AjaxResponse;
use App\Service\ActionHandler;
use App\Service\CitizenHandler;
use App\Service\ConfMaster;
use App\Service\CrowService;
use App\Service\DeathHandler;
use App\Service\ErrorHelper;
use App\Service\InventoryHandler;
use App\Service\PictoHandler;
use App\Service\JSONRequestParser;
use App\Service\LogTemplateHandler;
use App\Service\RandomGenerator;
use App\Service\TimeKeeperService;
use App\Service\TownHandler;
use App\Service\UserHandler;
use App\Service\ZoneHandler;
use App\Structures\BankItem;
use App\Structures\ItemRequest;
use App\Structures\MyHordesConf;
use App\Structures\TownConf;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class InventoryAwareController
 * @package App\Controller
 * @GateKeeperProfile(only_alive=true, only_with_profession=true)
 * @Semaphore("town", scope="town")
 */
class InventoryAwareController extends CustomAbstractController
    implements HookedInterfaceController
{
    protected DeathHandler $death_handler;
    protected ActionHandler $action_handler;
    protected PictoHandler $picto_handler;
    protected LogTemplateHandler $log;
    protected RandomGenerator $random_generator;
    protected ZoneHandler $zone_handler;
    protected LogTemplateHandler $logTemplateHandler;
    protected UserHandler $user_handler;
    protected CrowService $crow;
    protected TownHandler $town_handler;
    protected Packages $asset;

    public function __construct(
        EntityManagerInterface $em, InventoryHandler $ih, CitizenHandler $ch, ActionHandler $ah, DeathHandler $dh, PictoHandler $ph,
        TranslatorInterface $translator, LogTemplateHandler $lt, TimeKeeperService $tk, RandomGenerator $rd, ConfMaster $conf,
        ZoneHandler $zh, UserHandler $uh, CrowService $armbrust, TownHandler $th, Packages $asset)
    {
        parent::__construct($conf, $em, $tk, $ch, $ih, $translator);
        $this->action_handler = $ah;
        $this->picto_handler = $ph;
        $this->log = $lt;
        $this->random_generator = $rd;
        $this->zone_handler = $zh;
        $this->death_handler = $dh;
        $this->logTemplateHandler = $lt;
        $this->user_handler = $uh;
        $this->crow = $armbrust;
        $this->town_handler = $th;
        $this->asset = $asset;
    }

    public function before(): bool
    {
        if ($this->citizen_handler->hasRole($this->getActiveCitizen(), 'ghoul') && !$this->getActiveCitizen()->hasSeenHelpNotification('ghoul')) {
            $this->addFlash('popup-ghoul', $this->renderView('ajax/game/notifications/ghoul.html.twig'));
            $this->getActiveCitizen()->addHelpNotification( $this->entity_manager->getRepository(HelpNotificationMarker::class)->findOneByName('ghoul') );
            $this->entity_manager->persist($this->getActiveCitizen());
            $this->entity_manager->flush();
        } else if ($this->citizen_handler->hasRole($this->getActiveCitizen(), 'shaman') && !$this->getActiveCitizen()->hasSeenHelpNotification('shaman')) {
            $this->addFlash('popup-shaman', $this->renderView('ajax/game/notifications/shaman.html.twig'));
            $this->getActiveCitizen()->addHelpNotification( $this->entity_manager->getRepository(HelpNotificationMarker::class)->findOneByName('shaman') );
            $this->entity_manager->persist($this->getActiveCitizen());
            $this->entity_manager->flush();
        } else if ($this->citizen_handler->hasRole($this->getActiveCitizen(), 'guide') && !$this->getActiveCitizen()->hasSeenHelpNotification('guide')) {
            $this->addFlash('popup-shaman', $this->renderView('ajax/game/notifications/guide.html.twig'));
            $this->getActiveCitizen()->addHelpNotification( $this->entity_manager->getRepository(HelpNotificationMarker::class)->findOneByName('guide') );
            $this->entity_manager->persist($this->getActiveCitizen());
            $this->entity_manager->flush();
        } else if ($this->getActiveCitizen()->getTown()->getInsurrectionProgress() >= 100 && !$this->getActiveCitizen()->hasSeenHelpNotification('insurrection') ) {
            $this->addFlash('popup-insurrection', $this->renderView('ajax/game/notifications/insurrection.html.twig', ['revolutionist' => $this->getActiveCitizen()->hasStatus('tg_revolutionist')]));
            $this->getActiveCitizen()->addHelpNotification( $this->entity_manager->getRepository(HelpNotificationMarker::class)->findOneByName('insurrection') );
            $this->entity_manager->persist($this->getActiveCitizen());
            $this->entity_manager->flush();
        } else if ($this->getActiveCitizen()->getTown()->getForceStartAhead() && !$this->getActiveCitizen()->hasSeenHelpNotification('stranger') ) {
            $this->addFlash('popup-stranger', $this->renderView('ajax/game/notifications/stranger.html.twig', ['population' => $this->getActiveCitizen()->getTown()->getPopulation()]));
            $this->getActiveCitizen()->addHelpNotification( $this->entity_manager->getRepository(HelpNotificationMarker::class)->findOneByName('stranger') );
            $this->entity_manager->persist($this->getActiveCitizen());
            $this->entity_manager->flush();
        }
        return true;
    }

    protected function renderLog( ?int $day, $citizen = null, $zone = null, ?int $type = null, ?int $max = null, $silence_indicators = false ): Response {
        $entries = [];

        # Try to fetch one more log to check if we must display the "show more entries" message
        $nb_to_fetch = (is_null($max) or $max <= 0) ? $max : $max + 1;

        /** @var TownLogEntry $entity */
        foreach ($this->entity_manager->getRepository(TownLogEntry::class)->findByFilter(
            $this->getActiveCitizen()->getTown(),
            $day, $citizen, $zone, $type, $nb_to_fetch ) as $idx=>$entity) {

                /** @var LogEntryTemplate $template */
                $template = $entity->getLogEntryTemplate();
                if (!$template)
                    continue;
                $entityVariables = $entity->getVariables();
                if($citizen !== null && $entity->getHidden())
                    continue;
                $entries[$idx] = [
                    'timestamp' => $entity->getTimestamp(),
                    'class'     => $template->getClass(),
                    'type'      => $template->getType(),
                    'id'        => $entity->getId(),
                    'hidden'    => $entity->getHidden(),
                ];

                $variableTypes = $template->getVariableTypes();
                $transParams = $this->logTemplateHandler->parseTransParams($variableTypes, $entityVariables);
                try {
                    $entries[$idx]['text'] = $this->translator->trans($template->getText(), $transParams, 'game');
                }
                catch (Exception $e) {
                    $entries[$idx]['text'] = "null";
                }
            }

        if ($day < 0) $day = $this->getActiveCitizen()->getTown()->getDay();

        $show_more_entries = false;
        if ($nb_to_fetch != $max) {
            $show_more_entries = count($entries) > $max;
            $entries = array_slice($entries, 0, $max);
        }

        return $this->render( 'ajax/game/log_content.html.twig', [
            'show_silence' => $silence_indicators,
            'day' => $day,
            'today' => $day === $this->getActiveCitizen()->getTown()->getDay(),
            'entries' => $entries,
            'show_more_entries' => $show_more_entries,
            'canHideEntry' => $this->getActiveCitizen()->getAlive() && $this->getActiveCitizen()->getProfession()->getHeroic() && $this->user_handler->hasSkill($this->getUser(), 'manipulator') && $this->getActiveCitizen()->getZone() === null && $this->getActiveCitizen()->getSpecificActionCounterValue(ActionCounter::ActionTypeRemoveLog) < $this->user_handler->getMaximumEntryHidden($this->getUser()),
        ] );
    }

    /**
     * @param Inventory[] $inventories
     * @param ItemTargetDefinition $definition
     * @return array
     */
    private function decodeActionItemTargets( array $inventories, ItemTargetDefinition $definition ) {
        $targets = [];

        switch ($definition->getSpawner()) {
            case ItemTargetDefinition::ItemSelectionType: case ItemTargetDefinition::ItemSelectionTypePoison:
                foreach ($inventories as &$inv)
                    foreach ($inv->getItems() as &$item)
                        if ($this->action_handler->targetDefinitionApplies($item,$definition))
                            $targets[] = [ $item->getId(), $this->translator->trans( $item->getPrototype()->getLabel(), [], 'items' ), "build/images/item/item_{$item->getPrototype()->getIcon()}.gif" ];

                break;
            case ItemTargetDefinition::ItemTypeSelectionType:
                if ($definition->getTag())
                    foreach ($this->inventory_handler->resolveItemProperties($definition->getTag()) as &$prop)
                        /** @var $prop ItemPrototype */
                        $targets[] = [ $prop->getId(), $this->translator->trans( $prop->getLabel(), [], 'items' ), "build/images/item/item_{$prop->getIcon()}.gif", $prop ];

                if ($definition->getPrototype())
                    $targets[] = [ $definition->getPrototype()->getId(), $this->translator->trans( $definition->getPrototype()->getLabel(), [], 'items' ), "build/images/item/item_{$definition->getPrototype()->getIcon()}.gif", $definition->getPrototype() ];

                break;
            case ItemTargetDefinition::ItemHeroicRescueType:

                foreach ($this->getActiveCitizen()->getTown()->getCitizens() as $citizen)
                    if ($citizen->getAlive() && $citizen->getZone() && round( sqrt(pow($citizen->getZone()->getX(),2 ) + pow($citizen->getZone()->getY(),2 )) ) <= 2)
                        $targets[] = [ $citizen->getId(), $citizen->getName(), "build/images/item/item_cart.gif" ];

                break;
            case ItemTargetDefinition::ItemCitizenType: case ItemTargetDefinition::ItemCitizenVoteType:

                foreach ($this->getActiveCitizen()->getTown()->getCitizens() as $citizen)
                    if ($citizen->getAlive() && $citizen != $this->getActiveCitizen())
                        $targets[] = [ $citizen->getId(), $citizen->getName(), "build/images/professions/{$citizen->getProfession()->getIcon()}.gif" ];

                break;
            case ItemTargetDefinition::ItemCitizenOnZoneType: case ItemTargetDefinition::ItemCitizenOnZoneSBType:

                foreach ($this->getActiveCitizen()->getTown()->getCitizens() as $citizen)
                    if ($citizen->getAlive() && $citizen != $this->getActiveCitizen() && $citizen->getZone() === $this->getActiveCitizen()->getZone()) {
                        if ($definition->getSpawner() !== ItemTargetDefinition::ItemCitizenOnZoneSBType || $citizen->getSpecificActionCounter(ActionCounter::ActionTypeSandballHit)->getLast() === null || $citizen->getSpecificActionCounter(ActionCounter::ActionTypeSandballHit)->getLast()->getTimestamp() < (time() - 1800))
                            $targets[] = [ $citizen->getId(), $citizen->getName(), "build/images/professions/{$citizen->getProfession()->getIcon()}.gif" ];
                    }

                break;
        }

        // Sort target by display name
        usort($targets, function($a, $b) { return strcmp($a[1], $b[1]);});
        return $targets;
    }

    protected function getHeroicActions(): array {
        $ret = [];

        $av_inv = [$this->getActiveCitizen()->getInventory(), $this->getActiveCitizen()->getZone() ? $this->getActiveCitizen()->getZone()->getFloor() : $this->getActiveCitizen()->getHome()->getChest()];

        $this->action_handler->getAvailableIHeroicActions( $this->getActiveCitizen(),  $available, $crossed, $used );
        if (empty($available) && empty($crossed)) return [];

        foreach ($available as $a) $ret[] = [ 'id' => $a->getId(), 'action' => $a->getAction(), 'renderer' => null, 'targets' => $a->getAction()->getTarget() ? $this->decodeActionItemTargets( $av_inv, $a->getAction()->getTarget() ) : null, 'target_mode' => $a->getAction()->getTarget() ? $a->getAction()->getTarget()->getSpawner() : 0, 'crossed' => false ];
        foreach ($crossed as $c)   $ret[] = [ 'id' => $c->getId(), 'action' => $c->getAction(), 'renderer' => null, 'targets' => null, 'target_mode' => 0, 'crossed' => true ];
        foreach ($used as $c)      $ret[] = [ 'id' => $c->getId(), 'action' => $c->getAction(), 'renderer' => null, 'targets' => null, 'target_mode' => 0, 'crossed' => true, 'used' => true ];

        return $ret;
    }

    protected function getSpecialActions(): array {
        $ret = [];

        $av_inv = [$this->getActiveCitizen()->getInventory(), $this->getActiveCitizen()->getZone() ? $this->getActiveCitizen()->getZone()->getFloor() : $this->getActiveCitizen()->getHome()->getChest()];

        $this->action_handler->getAvailableISpecialActions( $this->getActiveCitizen(),  $available, $crossed );
        if (empty($available) && empty($crossed)) return [];

        foreach ($available as $a) $ret[] = [ 'id' => $a->getId(), 'icon' => $a->getIcon(), 'action' => $a->getAction(), 'renderer' => null, 'targets' => $a->getAction()->getTarget() ? $this->decodeActionItemTargets( $av_inv, $a->getAction()->getTarget() ) : null, 'target_mode' => $a->getAction()->getTarget() ? $a->getAction()->getTarget()->getSpawner() : 0, 'crossed' => false ];
        foreach ($crossed as $c)   $ret[] = [ 'id' => $c->getId(), 'icon' => $c->getIcon(), 'action' => $c->getAction(), 'renderer' => null, 'targets' => null, 'target_mode' => 0, 'crossed' => true ];
        return $ret;
        
    }

    protected function getCampingActions(): array {
      $ret = [];
      if (!$this->getTownConf()->get(TownConf::CONF_FEATURE_CAMPING, false)) return $ret;

      $this->action_handler->getAvailableCampingActions( $this->getActiveCitizen(), $available, $crossed );

      foreach ($available as $a) $ret[] = [ 'id' => $a->getId(), 'item' => null, 'action' => $a, 'renderer' => null, 'targets' => null, 'crossed' => false ];
      foreach ($crossed as $c)   $ret[] = [ 'id' => $c->getId(), 'item' => null, 'action' => $c, 'renderer' => null, 'targets' => null, 'crossed' => true ];

      usort($ret, fn($a,$b) => $a['id'] <=> $b['id']);

      return $ret;
    }

    protected function getHomeActions(): array {
        $ret = [];

        $av_inv = [$this->getActiveCitizen()->getInventory(), $this->getActiveCitizen()->getHome()->getChest()];
        $this->action_handler->getAvailableHomeActions( $this->getActiveCitizen(), $available, $crossed );

        foreach ($available as $a) $ret[] = [ 'id' => $a->getId(), 'icon' => $a->getIcon(), 'item' => null, 'action' => $a->getAction(), 'renderer' => null, 'targets' => $a->getAction()->getTarget() ? $this->decodeActionItemTargets( $av_inv, $a->getAction()->getTarget() ) : null, 'target_mode' => $a->getAction()->getTarget() ? $a->getAction()->getTarget()->getSpawner() : 0, 'crossed' => false ];
        foreach ($crossed as $c)   $ret[] = [ 'id' => $c->getId(), 'icon' => $c->getIcon(), 'item' => null, 'action' => $c->getAction(), 'renderer' => null, 'targets' => null, 'target_mode' => 0, 'crossed' => true ];

        return $ret;
    }

    protected function getItemActions(): array {
        $ret = [];

        $av_inv = [$this->getActiveCitizen()->getInventory() ];

        if($this->getActiveCitizen()->getZone()) {
            if ($this->conf->getTownConfiguration($this->getActiveCitizen()->getTown())->get(TownConf::CONF_MODIFIER_FLOOR_ASMBLY, false)) {
                if(!$this->getActiveCitizen()->activeExplorerStats())
                    $av_inv[] =  $this->getActiveCitizen()->getZone()->getFloor();
                else {
                    $ex = $this->getActiveCitizen()->activeExplorerStats();
                    $ruinZone = $this->entity_manager->getRepository(RuinZone::class)->findOneByPosition($this->getActiveCitizen()->getZone(), $ex->getX(), $ex->getY());
                    $av_inv[] = $ruinZone->getFloor();
                }
            }
        } else {
            $av_inv[] = $this->getActiveCitizen()->getHome()->getChest();
        }

        $items = [];
        foreach ($this->getActiveCitizen()->getInventory()->getItems() as $item) $items[] = $item;
        if ($this->getActiveCitizen()->getZone() === null) foreach ($this->getActiveCitizen()->getHome()->getChest()->getItems() as $item) $items[] = $item;

        foreach ($items as $item) if (!$item->getBroken() || $this->getActiveCitizen()->getZone()) {

            $this->action_handler->getAvailableItemActions( $this->getActiveCitizen(), $item, $available, $crossed, $messages, $this->getActiveCitizen()->getZone() !== null );
            if (empty($available) && empty($crossed)) continue;

            foreach ($available as $a) $ret[] = [ 'id' => $a->getId(), 'item' => $item, 'broken' => $item->getBroken(), 'action' => $a, 'renderer' => $a->getRenderer(), 'targets' => $item->getBroken() ? null : ($a->getTarget() ? $this->decodeActionItemTargets( $av_inv, $a->getTarget() ) : null), 'target_mode' => $item->getBroken() ? 0 : ($a->getTarget() ? $a->getTarget()->getSpawner() : 0), 'crossed' => false, 'message' => null ];
            foreach ($crossed as $c)   $ret[] = [ 'id' => $c->getId(), 'item' => $item, 'broken' => $item->getBroken(), 'action' => $c, 'renderer' => $c->getRenderer(), 'targets' => null, 'target_mode' => 0, 'crossed' => true, 'message' => $item->getBroken() ? null : ($messages[$c->getId()] ?? null) ];
        }

        return $ret;
    }

    protected function getItemCombinations(bool $inside): array {
        $town = $this->getActiveCitizen()->getTown();
        $source_inv = $this->getActiveCitizen()->getZone() ? [ $this->getActiveCitizen()->getInventory() ] : [ $this->getActiveCitizen()->getInventory(), $this->getActiveCitizen()->getHome()->getChest() ];

        if ($this->getActiveCitizen()->getZone() && $this->getTownConf()->get( TownConf::CONF_MODIFIER_FLOOR_ASMBLY, false )) $source_inv[] = $this->getActiveCitizen()->getZone()->getFloor();

        $recipes = $this->entity_manager->getRepository(Recipe::class)->findByType( [Recipe::ManualAnywhere, $inside ? Recipe::ManualInside : Recipe::ManualOutside] );
        $out = [];
        $source_db = [];
        foreach ($recipes as $recipe) {
            /** @var Recipe $recipe */
            $found_provoking = false;
            foreach ($recipe->getProvoking() as $proto)
                if ($this->inventory_handler->countSpecificItems( $source_inv, $proto )) {
                    $found_provoking = true;
                    break;
                }

            if (!$found_provoking) continue;
            $out[] = $recipe;

            if ($recipe->getSource())
                foreach ($recipe->getSource()->getEntries() as $entry)
                    /** @var ItemGroupEntry $entry */
                    if (!isset( $source_db[ $entry->getPrototype()->getId() ] ))
                        $source_db[ $entry->getPrototype()->getId() ] = $this->inventory_handler->countSpecificItems( $source_inv, $entry->getPrototype() );
        }

        return [ 'recipes' => $out, 'source_items' => $source_db ];
    }

    protected function renderInventoryAsBank( Inventory $inventory ): array {
        $qb = $this->entity_manager->createQueryBuilder();
        $qb
            ->select('i.id', 'c.label as l1', 'cr.label as l2', 'SUM(i.count) as n')->from('App:Item','i')
            ->where('i.inventory = :inv')->setParameter('inv', $inventory);
        if ($this->getTownConf()->get(TownConf::CONF_MODIFIER_POISON_STACK, false))
            $qb->groupBy('i.prototype', 'i.broken');
        else $qb->groupBy('i.prototype', 'i.broken', 'i.poison');
        $qb
            ->leftJoin('App:ItemPrototype', 'p', Join::WITH, 'i.prototype = p.id')
            ->leftJoin('App:ItemCategory', 'c', Join::WITH, 'p.category = c.id')
            ->leftJoin('App:ItemCategory', 'cr', Join::WITH, 'c.parent = cr.id')
            ->addOrderBy('c.ordering','ASC');

        if ($this->getUser()->getClassicBankSort()) $qb->addOrderBy('n', 'DESC');

        $qb
            ->addOrderBy('p.icon', 'DESC')
            ->addOrderBy('i.id', 'ASC');

        $data = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);

        $final = [];
        $cache = [];

        foreach ($data as $entry) {
            $label = $entry['l2'] ?? $entry['l1'] ?? 'Sonstiges';
            if (!isset($final[$label])) $final[$label] = [];
            $final[$label][] = [ $entry['id'], $entry['n'] ];
            $cache[] = $entry['id'];
        }

        $item_list = $this->entity_manager->getRepository(Item::class)->findAllByIds($cache);
        foreach ( $final as $label => &$entries )
            $entries = array_map(function( array $entry ) use (&$item_list): BankItem { return new BankItem( $item_list[$entry[0]], $entry[1] ); }, $entries);

        return $final;
    }

    public function generic_devour_api(Citizen $aggressor, Citizen $victim): AjaxResponse {
        if ($aggressor->getId() === $victim->getId())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if ($aggressor->getZone()) {
            if (!$victim->getZone() || $victim->getZone()->getId() !== $aggressor->getZone()->getId())
                return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        } else {
            if ($victim->getZone())
                return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        }

        $notes = [];

        if ($victim->getAlive()) {

            if ($this->citizen_handler->hasStatusEffect($aggressor, 'tg_ghoul_eat'))
                return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

            if ($victim->hasRole('ghoul')) {
                $this->addFlash('notice', $this->translator->trans('Du kannst diesen Bürger nicht angreifen... er riecht nicht wie die anderen. Moment... Dieser Bürger ist ein Ghul, genau wie du!', [], 'game'));
                return AjaxResponse::success();
            }

            $notes[] = $this->translator->trans( 'Mit weit aufgerissenem Maul stürzt du dich auf {citizen}. Unter der Wucht deiner brutalen Schläge und Tritte sackt er ziemlich schnell zusammen.', ['{citizen}' => $victim->getName()], 'game' );
            $notes[] = $this->translator->trans( 'Mit ein paar unschönen Tritten gegen seinen Kopf vergewisserst du dich, dass er garantiert nicht mehr aufstehen wird. Na los! Bring deinen Job zuende und verspeise ihn!', [], 'game' );

            $give_ap = 7;

            if ($aggressor->getZone()) {
                $this->entity_manager->persist($this->log->citizenBeyondGhoulAttack($aggressor, $victim, true));
                $this->entity_manager->persist($this->log->citizenBeyondGhoulAttack($aggressor, $victim, false));
            } else {
                $cc = 0;
                foreach ($aggressor->getTown()->getCitizens() as $c)
                    if ($c->getAlive() && !$c->getZone() && $c->getId() !== $aggressor->getId() && $c->getId() !== $victim->getId()) $cc++;
                $cc = (float)$cc / (float)$aggressor->getTown()->getPopulation(); // Completely arbitrary

                if ($this->random_generator->chance($cc)) {

                    $this->entity_manager->persist($this->log->citizenTownGhoulAttack($aggressor,$victim));
                    $notes[] = $this->translator->trans( 'Gut gemacht!', [], 'game' );

                } else $notes[] = $this->translator->trans( 'Du wurdest beobachtet! Die anderen Bürger wurden gewarnt!', [], 'game' );
            }

            if ($give_ap > $aggressor->getAp())
                $this->citizen_handler->setAP($aggressor, false, $give_ap, null );

            $aggressor->setGhulHunger( max(0, $aggressor->getGhulHunger() - 65) );
            $this->picto_handler->give_picto($aggressor, 'r_cannib_#00');
            $this->citizen_handler->removeStatus($aggressor, 'tg_air_ghoul');

            $stat_down = false;
            if (!$this->citizen_handler->hasStatusEffect($aggressor, 'drugged') && $this->citizen_handler->hasStatusEffect($victim, 'drugged')) {
                $stat_down = true;
                $this->citizen_handler->inflictStatus( $aggressor, 'drugged' );
            }

            if (!$this->citizen_handler->hasStatusEffect($aggressor, 'addict') && $this->citizen_handler->hasStatusEffect($victim, 'addict')) {
                $stat_down = true;
                $this->citizen_handler->inflictStatus( $aggressor, 'addict' );
            }

            if (!$this->citizen_handler->hasStatusEffect($aggressor, 'drunk') && $this->citizen_handler->hasStatusEffect($victim, 'drunk')) {
                $stat_down = true;
                $this->citizen_handler->inflictStatus( $aggressor, 'drunk' );
                $this->citizen_handler->inflictStatus( $aggressor, 'tg_no_hangover' );
            }

            if ($stat_down)
                $notes[] = $this->translator->trans( 'Einige gesundheitliche Askekte deines Opfers sind auf dich übergegangen ...', [], 'game' );

            $this->citizen_handler->inflictStatus( $aggressor, 'tg_ghoul_eat' );

            $this->death_handler->kill($victim, CauseOfDeath::GhulEaten);
            $this->entity_manager->persist($this->log->citizenDeath( $victim ) );

        } else {

            if ($this->citizen_handler->hasStatusEffect($aggressor, 'tg_ghoul_corpse'))
                return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

            if ($aggressor->getZone() || !$victim->getHome()->getHoldsBody())
                return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

            // $this->entity_manager->persist( $this->log->citizenDisposal($aggressor, $victim, Citizen::Ghoul) );

            $aggressor->setGhulHunger( max(0, $aggressor->getGhulHunger() - 10) );
            $victim->getHome()->setHoldsBody(false);
            $victim->setDisposed(Citizen::Ghoul);
            $this->picto_handler->give_picto($aggressor, 'r_cannib_#00');

            $notes[] = $this->translator->trans('Nicht so appetitlich wie frisches Menschenfleisch, aber es stillt nichtsdestotrotz deinen Hunger... zumindest ein bisschen. Wenigstens war das Fleisch noch halbwegs zart.', [], 'game');
            $this->citizen_handler->inflictStatus( $aggressor, 'tg_ghoul_corpse' );
        }

        if ($notes)
            $this->addFlash('notice', implode('<hr />', $notes));

        $this->entity_manager->persist($aggressor);
        $this->entity_manager->persist($victim);

        try {
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    public function generic_attack_api(Citizen $aggressor, Citizen $defender): Response {
        if ($aggressor->getId() === $defender->getId() || !$defender->getAlive())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if ($aggressor->getZone()) {
            if (!$defender->getZone() || $defender->getZone()->getId() !== $aggressor->getZone()->getId())
                return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        } else {
            if ($defender->getZone())
                return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        }

        $ap = $this->getTownConf()->get(TownConf::CONF_MODIFIER_ATTACK_AP, 5);
        if ($this->citizen_handler->isTired($aggressor) || $aggressor->getAp() < $ap)
            return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        $attack_protect = $this->getTownConf()->get(TownConf::CONF_MODIFIER_ATTACK_PROTECT, false) ||
            ($aggressor->getUser()->getAllSoulPoints() < $this->conf->getGlobalConf()->get(MyHordesConf::CONF_ANTI_GRIEF_SP, 20));
        if ($attack_protect) {
            foreach ($aggressor->getTown()->getCitizens() as $c)
                if ($c->getAlive() && $c->hasRole('ghoul'))
                    $attack_protect = false;
        }

        if ($defender->hasRole('ghoul')) {

            $this->citizen_handler->setAP($aggressor, true, -$ap);
            $this->addFlash('notice',
                $this->translator->trans('Mit aller Gewalt greifst du {citizen} an! Du hast den Überraschungsmoment auf deiner Seite und am Ende trägt {citizen} eine schwere Verletzung davon.', ['{citizen}' => $defender->getName()], 'game')
                . "<hr />" .
                $this->translator->trans('Plötzlich sackt {citizen} in sich zusammen, seine Augen drehen sich nach hinten, und mit einem schauerhaften Gurgeln löst sich sein ganzer Körper vor deinen Augen auf und hinterlässt nur den üblen Geruch von Tod und Verwesung! Es gibt keinen Zweifel mehr: {citizen} war ein Ghul!!', ['{citizen}' => $defender->getName()], 'game')
            );
            $this->entity_manager->persist($this->log->citizenAttack($aggressor, $defender, true));
            $this->death_handler->kill($defender, CauseOfDeath::GhulBeaten);
            $this->entity_manager->persist($this->log->citizenDeath( $defender ) );

        } elseif ($attack_protect) {

            $this->addFlash('error', $this->translator->trans('Bleib mal ganz geschmeidig! In dieser Stadt gibt es keine Ghule, also solltest du auch nicht herumlaufen und grundlos Leute verprügeln. M\'Kay?', [], 'game'));

        } elseif ( $this->citizen_handler->isWounded( $defender ) ) {

            $this->addFlash('error', $this->translator->trans('{citizen} ist bereits verletzt; ihn erneut anzugreifen wird dir nichts bringen.', ['{citizen}' => $defender->getName()], 'game'));

        } else {

            $this->citizen_handler->setAP( $aggressor, true, -$ap );
            $wound = $this->random_generator->chance( $this->getTownConf()->get(TownConf::CONF_MODIFIER_ATTACK_CHANCE, 0.5) );
            $this->entity_manager->persist($this->log->citizenAttack($aggressor, $defender, $wound));
            if ($wound) {
                $this->addFlash('notice',
                    $this->translator->trans('Mit aller Gewalt greifst du {citizen} an! Du hast den Überraschungsmoment auf deiner Seite und am Ende trägt {citizen} eine schwere Verletzung davon.', ['{citizen}' => '<span>' . $defender->getName() . '</span>'], 'game')
                );
                $this->citizen_handler->inflictWound($defender);
            } else {
                $this->addFlash('notice',
                    $this->translator->trans('Mit aller Gewalt greifst du {citizen} an! Ihr tauscht für eine Weile Schläge aus, bis ihr euch schließlich größtenteils unverletzt voneinander trennt.', ['{citizen}' => '<span>' . $defender->getName() . '</span>'], 'game')
                );
            }

            if (!$defender->getZone()) {
                $this->crow->postAsPM( $defender, '', '', $wound ? PrivateMessage::TEMPLATE_CROW_AGGRESSION_SUCCESS : PrivateMessage::TEMPLATE_CROW_AGGRESSION_FAIL, $aggressor->getId() );
            }
        }

        $this->entity_manager->persist($aggressor);
        $this->entity_manager->persist($defender);

        try {
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success( );
    }

    public function generic_item_api(Inventory &$up_target, Inventory &$down_target, bool $allow_down_all, JSONRequestParser $parser, InventoryHandler $handler, Citizen $citizen = null, $hide = false, ?int &$processed = null): AjaxResponse {
        $item_id = $parser->get_int('item', -1);
        $direction = $parser->get('direction', '');
        $allowed_directions = ['up','down'];
        if ($allow_down_all) $allowed_directions[] = 'down-all';
        $item = $item_id < 0 ? null : $this->entity_manager->getRepository(Item::class)->find( $item_id );

        $carrier_items = ['bag_#00','bagxl_#00','cart_#00','pocket_belt_#00'];

        $drop_carriers = false;

        if($citizen === null)
            $citizen = $this->getActiveCitizen();

        if ($direction === 'down' && $allow_down_all && $item && in_array($item->getPrototype()->getName(), $carrier_items)) {

            $has_other_carriers = !empty(array_filter($citizen->getInventory()->getItems()->getValues(), function(Item $i) use ($carrier_items, $item) {
                return $i !== $item && in_array($i->getPrototype()->getName(), /*$carrier_items*/[]);   // Fix watcher/belt abuse
            }));

            if (!$has_other_carriers) {
                $direction = 'down-all';
                $drop_carriers = true;
            }
        }

        if (in_array($direction, $allowed_directions)) {

            $inv_source = $direction === 'up' ? $down_target : $up_target;
            $inv_target = $direction !== 'up' ? $down_target : $up_target;

            $items = [];
            if ($direction !== 'down-all') {
                $item = $this->entity_manager->getRepository(Item::class)->find( $item_id );
                if ($item && $item->getInventory()) $items = [$item];
            } else{
                $items = array_filter($citizen->getInventory()->getItems()->getValues(), function(Item $i) use ($carrier_items, $drop_carriers) {
                    return ($drop_carriers || !in_array($i->getPrototype()->getName(), $carrier_items)) && !$i->getEssential();
                });
            }

            $bank_up = null;
            if ($inv_source->getTown()) $bank_up = true;
            if ($inv_target->getTown()) $bank_up = false;
            $bank_theft = $parser->get_int('theft', 0) > 0;

            if ($bank_theft && (!$this->conf->getTownConfiguration($citizen->getTown())->isNightMode() ))
                return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable);

            $floor_up = null;
            if ($inv_source->getZone() || $inv_source->getRuinZone() || $inv_source->getRuinZoneRoom() ) $floor_up = true;
            if ($inv_target->getZone() || $inv_target->getRuinZone() || $inv_target->getRuinZoneRoom() ) $floor_up = false;

            $steal_up = null;
            if ($inv_source->getHome() && $inv_source->getHome()->getId() !== $citizen->getHome()->getId()) $steal_up = true;
            if ($inv_target->getHome() && $inv_target->getHome()->getId() !== $citizen->getHome()->getId()) $steal_up = false;

            $errors = [];
            $item_count = count($items);
            $dead = false;

            if ($steal_up === true && $citizen->getSpecificActionCounterValue(ActionCounter::ActionTypeSendPMItem, $inv_source->getHome()->getCitizen()->getId()) > 0)
                return AjaxResponse::error(InventoryHandler::ErrorTransferStealPMBlock);

            $target_citizen = $inv_target->getCitizen() ?? $inv_source->getCitizen() ?? $citizen;

            $has_hidden = false;
            /** @var Item $current_item */
            foreach ($items as $current_item){
                if($floor_up && ($this->citizen_handler->hasStatusEffect($target_citizen, 'tg_tomb') || $this->citizen_handler->hasStatusEffect($target_citizen, 'tg_hide'))) {
                    $errors[] = InventoryHandler::ErrorTransferBlocked;
                    break;
                }
                if ($current_item->getPrototype()->getName() == 'soul_red_#00' && $floor_up) {
                    // We pick a read soul in the World Beyond
                    if ( $target_citizen && !$this->citizen_handler->hasStatusEffect($target_citizen, "tg_shaman_immune") ) {
                        $dead = true;

                        // Produce logs
                        $this->entity_manager->persist( $this->log->beyondItemLog( $target_citizen, $current_item->getPrototype(), false, $current_item->getBroken(), false ) );

                        // He is not immune, he dies.
                        $this->death_handler->kill( $target_citizen, CauseOfDeath::Haunted );
                        $this->entity_manager->persist( $this->log->citizenDeath( $target_citizen ) );

                        // The red soul vanishes too
                        $this->inventory_handler->forceRemoveItem($current_item);
                    } elseif ( !$target_citizen->hasRole('shaman') && $target_citizen->getProfession()->getName() !== 'shaman' && $this->citizen_handler->hasStatusEffect($target_citizen, "tg_shaman_immune"))
                        $this->addFlash('notice', $this->translator->trans('Du nimmst diese wandernde Seele und betest, dass der Schamane weiß, wie man diesen Trank zubereitet! Und du überlebst! Was für ein Glück, du hätten keine müde Mark auf den Scharlatan gewettet.', [], "game"));
                }

                if(!$dead){
                    if (($error = $handler->transferItem(
                            $citizen,
                            $current_item, $inv_source, $inv_target, $bank_theft ? InventoryHandler::ModalityBankTheft : InventoryHandler::ModalityNone, $this->getTownConf()->get(TownConf::CONF_MODIFIER_CARRY_EXTRA_BAG, false)
                        )) === InventoryHandler::ErrorNone) {

                        if ($bank_up !== null) {

                            if ($bank_theft) {

                                if ($this->random_generator->chance(0.6)) {
                                    $this->entity_manager->persist( $this->log->bankItemStealLog( $citizen, $current_item->getPrototype(), false, $current_item->getBroken() ) );
                                    $this->addFlash('error',$this->translator->trans('Dein Diebstahlversuch ist gescheitert! Du bist entdeckt worden!', [], "game"));
                                } else {
                                    $this->entity_manager->persist( $this->log->bankItemStealLog( $citizen, $current_item->getPrototype(), true, $current_item->getBroken() ) );
                                    $this->addFlash('notice',$this->translator->trans('Du hast soeben {item} aus der Bank gestohlen. Dein Name wird nicht im Register erscheinen...', ['{item}' => $this->log->wrap($this->log->iconize($current_item), 'tool')], "game"));
                                }

                            } else {
                                $this->entity_manager->persist( $this->log->bankItemLog( $target_citizen, $current_item->getPrototype(), !$bank_up, $current_item->getBroken() ) );
                                if ($bank_up)
                                    $this->addFlash('notice',$this->translator->trans('Du hast soeben folgenden Gegenstand aus der Bank genommen: {item}. <strong>Sei nicht zu gierig</strong> oder deine Mitbürger könnten dich für einen <strong>Egoisten</strong> halten...', ['{item}' => $this->log->wrap($this->log->iconize($current_item), 'tool')], "game"));
                            }
                        }
                        if ($floor_up !== null) {
                            if($floor_up && $current_item->getPrototype()->getName() == 'soul_blue_#00' && $current_item->getFirstPick()) {
                                $current_item->setFirstPick(false);
                                // In the "Job" version of the shaman, the one that pick a blue soul for the 1st time gets the "r_collec" picto
                                if ($this->getTownConf()->is(TownConf::CONF_FEATURE_SHAMAN_MODE, ['job', 'both'], "normal"))
                                    $this->picto_handler->give_picto($target_citizen, "r_collec2_#00");
                                $this->entity_manager->persist($current_item);
                            }
                            if (!$hide && !$current_item->getHidden()) $this->entity_manager->persist( $this->log->beyondItemLog( $target_citizen, $current_item->getPrototype(), !$floor_up, $current_item->getBroken(), false ) );
                            elseif ($hide && !$floor_up) {
                                $others = false;
                                if (!$target_citizen->getTown()->getChaos() && $target_citizen->getZone()) foreach ($target_citizen->getZone()->getCitizens() as $c) if (!$c->getBanished()) $others = true;
                                if ($others)
                                    $this->entity_manager->persist( $this->log->beyondItemLog( $target_citizen, $current_item->getPrototype(), !$floor_up, $current_item->getBroken(), true ) );
                            }
                        }
                        if ($steal_up !== null) {

                            $this->citizen_handler->inflictStatus($target_citizen, 'tg_steal');
                            $victim_home = $steal_up ? $inv_source->getHome() : $inv_target->getHome();

                            // Give picto steal
                            $pictoName = "r_theft_#00";
                            if(!$victim_home->getCitizen()->getAlive())
                                $pictoName = "r_plundr_#00";

                            $isSanta = false;
                            $isLeprechaun = false;
                            $hasExplodingDoormat = false;

                            if ($this->inventory_handler->countSpecificItems($citizen->getInventory(), "christmas_suit_full_#00") > 0){
                                if($victim_home->getCitizen()->getAlive()
                                    && $this->entity_manager->getRepository(EventActivationMarker::class)->findOneBy(['town' => $citizen->getTown(), 'active' => true, 'event' => 'christmas']))
                                    $pictoName = "r_santac_#00";
                                $isSanta = true;
                            }

                            if ($this->inventory_handler->countSpecificItems($citizen->getInventory(), "leprechaun_suit_#00") > 0){
                                if($victim_home->getCitizen()->getAlive()
                                    && $this->entity_manager->getRepository(EventActivationMarker::class)->findOneBy(['town' => $citizen->getTown(), 'active' => true, 'event' => 'stpatrick']))
                                    $pictoName = "r_lepre_#00";
                                $isLeprechaun = true;
                            }

                            if ($this->inventory_handler->countSpecificItems($victim_home->getChest(), "trapma_#00") > 0)
                                $hasExplodingDoormat = true;

                            $this->picto_handler->give_picto($citizen, $pictoName);

                            $alarm = ($this->entity_manager->getRepository(CitizenHomeUpgrade::class)->findOneByPrototype(
                                    $victim_home,
                                    $this->entity_manager->getRepository(CitizenHomeUpgradePrototype::class)->findOneByName( 'alarm' ) ) && $victim_home->getCitizen()->getAlive());

                            if($steal_up) {
                                if ($hasExplodingDoormat && $victim_home->getCitizen()->getAlive()) {

                                    if ($this->citizen_handler->isWounded($citizen)) {
                                        $this->death_handler->kill($citizen, CauseOfDeath::ExplosiveDoormat);
                                        $this->entity_manager->persist($this->log->citizenDeath( $citizen ) );
                                    }
                                    else {
                                        $this->citizen_handler->inflictWound( $citizen );
                                        $dm = $this->inventory_handler->fetchSpecificItems($victim_home->getChest(), [new ItemRequest('trapma_#00')]);
                                        if (!empty($dm)) $this->inventory_handler->forceRemoveItem(array_pop($dm));
                                    }
    
                                    $this->entity_manager->persist( $this->log->townSteal( $victim_home->getCitizen(), $citizen, $current_item->getPrototype(), $steal_up, false, $current_item->getBroken() ) );
                                    if ($citizen->getAlive()) {
                                        $this->addFlash( 'notice',
                                                         $this->translator->trans('Huch! Scheint, als würde dein Mitbürger nicht wollen, dass jemand seine Sachen durchstöbert. Unter deinen Füßen ist etwas explodiert und hat dich gegen die Wand geschleudert. Du wurdest verletzt!', ['victim' => $victim_home->getCitizen()->getName()], 'game') .
                                                         "<hr/>" .
                                                         $this->translator->trans('Der Diebstahl, den du gerade begangen hast, wurde bemerkt! Die Bürger werden gewarnt, dass du den(die,das) {item} bei {victim} gestohlen hast.', ['victim' => $victim_home->getCitizen()->getName(), '{item}' => "<strong><img alt='' src='{$this->asset->getUrl( "build/images/item/item_{$current_item->getPrototype()->getIcon()}.gif" )}'> {$this->translator->trans($current_item->getPrototype()->getLabel(),[],'items')}</strong>"], 'game')
                                        );
                                    } else {
                                        $this->addFlash( 'notice',
                                                         $this->translator->trans('Tja, das hast du davon bei einem paranoiden Pyromanen einbrechen zu wollen. Deine Einzelteile besprenkeln nun seine vier Wände. Das ist lange nicht so spaßig, wie es klingt: Irgendjemand wird hier putzen müssen.', ['victim' => $victim_home->getCitizen()->getName()], 'game')
                                        );
                                    }

                                } elseif ($isSanta || $isLeprechaun) {
                                    $this->entity_manager->persist( $this->log->townSteal( $victim_home->getCitizen(), null, $current_item->getPrototype(), $steal_up, $isSanta, $current_item->getBroken(), $isLeprechaun ) );
                                    $this->entity_manager->persist( $this->log->townSteal( $victim_home->getCitizen(), $citizen, $current_item->getPrototype(), $steal_up, false, $current_item->getBroken(), false )->setAdminOnly(true) );
                                    $this->addFlash( 'notice', $this->translator->trans($isSanta ? 'Dank deines Kostüms konntest du {item} von {victim} stehlen, <strong>ohne erkannt zu werden</strong>.<hr/>Ho ho ho.' : 'Dank deines Kostüms konntest du {item} von {victim} stehlen, <strong>ohne erkannt zu werden</strong>.<hr/>Was für ein guter Morgen!', [
                                        '{victim}' => $victim_home->getCitizen()->getName(),
                                        '{item}' => $this->log->wrap($this->log->iconize($current_item))], 'game') );
                                } elseif ($alarm) {
                                    $this->entity_manager->persist( $this->log->townSteal( $victim_home->getCitizen(), $citizen, $current_item->getPrototype(), $steal_up, false, $current_item->getBroken() ) );
                                    $this->addFlash( 'notice', $this->translator->trans('Der Diebstahl, den du gerade begangen hast, wurde bemerkt! Die Bürger werden gewarnt, dass du den(die,das) {item} bei {victim} gestohlen hast.', ['victim' => $victim_home->getCitizen()->getName(), '{item}' => "<strong><img alt='' src='{$this->asset->getUrl( "build/images/item/item_{$current_item->getPrototype()->getIcon()}.gif" )}'> {$this->translator->trans($current_item->getPrototype()->getLabel(),[],'items')}</strong>"], 'game'));
                                    //$this->citizen_handler->inflictStatus( $citizen, 'terror' );
                                    //$this->addFlash( 'notice', $this->translator->trans('{victim}s Alarmanlage hat die halbe Stadt aufgeweckt und dich zu Tode erschreckt!', ['{victim}' => $victim_home->getCitizen()->getName()], 'game') );
                                } elseif ($this->random_generator->chance(0.5) || !$victim_home->getCitizen()->getAlive()) {
                                    if ($victim_home->getCitizen()->getAlive()){
                                        $this->entity_manager->persist( $this->log->townSteal( $victim_home->getCitizen(), $citizen, $current_item->getPrototype(), $steal_up, false, $current_item->getBroken() ) );
                                        $this->addFlash( 'notice', $this->translator->trans('Der Diebstahl, den du gerade begangen hast, wurde bemerkt! Die Bürger werden gewarnt, dass du den(die,das) {item} bei {victim} gestohlen hast.', ['victim' => $victim_home->getCitizen()->getName(), '{item}' => "<strong><img alt='' src='{$this->asset->getUrl( "build/images/item/item_{$current_item->getPrototype()->getIcon()}.gif" )}'> {$this->translator->trans($current_item->getPrototype()->getLabel(),[],'items')}</strong>"], 'game'));
                                    } else {
                                        $this->entity_manager->persist( $this->log->townLoot( $victim_home->getCitizen(), $citizen, $current_item->getPrototype(), $steal_up, false, $current_item->getBroken() ) );
                                        $this->addFlash( 'notice', $this->translator->trans('Du hast dir folgenden Gegenstand unter den Nagel gerissen: {item}. Dein kleiner Hausbesuch bei † {victim} ist allerdings aufgeflogen...<hr /><strong>Dieser Gegenstand wurde in deiner Truhe abgelegt.</strong>', ['{item}' => $this->log->wrap($this->log->iconize($current_item)), '{victim}' => $victim_home->getCitizen()->getName()], 'game') );
                                    }
                                } else {
                                    $this->addFlash( 'notice', $this->translator->trans('Es ist dir gelungen, {item} von {victim} zu stehlen <strong>ohne entdeckt zu werden</strong>. Nicht schlecht!', [
                                        '{victim}' => $victim_home->getCitizen(),
                                        '{item}' => $this->log->wrap($this->log->iconize($current_item))
                                    ], 'game') );
                                }
    
                                $this->crow->postAsPM( $victim_home->getCitizen(), '', '', PrivateMessage::TEMPLATE_CROW_THEFT, $current_item->getPrototype()->getId() );
                            } else {
                                $messages = [ $this->translator->trans('Du hast den(die,das) {item} bei {victim} abgelegt...', ['{item}' => "<strong><img alt='' src='{$this->asset->getUrl( "build/images/item/item_{$current_item->getPrototype()->getIcon()}.gif" )}'> {$this->translator->trans($current_item->getPrototype()->getLabel(),[],'items')}</strong>",  '{victim}' => "<strong>{$victim_home->getCitizen()->getName()}</strong>"], 'game')];
                                if ( !$isSanta && !$isLeprechaun && ($this->random_generator->chance(0.1) || $alarm) ) {
                                    $messages[] = $this->translator->trans('Du bist bei deiner Aktion aufgeflogen! <strong>Deine Mitbürger wissen jetz Bescheid!</strong>', [], 'game');
                                    $this->entity_manager->persist( $this->log->townSteal( $victim_home->getCitizen(), $citizen, $current_item->getPrototype(), $steal_up, false, $current_item->getBroken() ) );
                                }

                                $this->addFlash( 'notice', implode('<hr/>', $messages) );
                            }

                            $intrusion = $this->entity_manager->getRepository(HomeIntrusion::class)->findOneBy(['intruder' => $citizen, 'victim' => $victim_home->getCitizen()]);
                            if ($intrusion) $this->entity_manager->remove($intrusion);
                        }
                        if(!$floor_up && $hide) {
                            $has_hidden = true;
                            $current_item->setHidden(true);
                        } else {
                            $current_item->setHidden(false);
                        }
                        if ($current_item->getInventory())
                            $this->entity_manager->persist($current_item);
                        else $this->entity_manager->remove($current_item);

                    } else {
                        if ($error === InventoryHandler::ErrorBankTheftFailed)
                            $this->entity_manager->persist( $this->log->bankItemStealLog( $citizen, $current_item->getPrototype(), false, $current_item->getBroken() ) );
                        $errors[] = $error;
                    }
                }
            }

            if ($has_hidden) {
                $this->citizen_handler->setAP($citizen, true, -2);
                $citizen->getZone()?->setItemsHiddenAt( new \DateTimeImmutable() );
                $this->entity_manager->persist($citizen);
            }

            try {
                $this->entity_manager->flush();
            } catch (Exception $e) {
                return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
            }

            $processed = max(0, $item_count - count($errors));

            if (count($errors) < $item_count || ($item_count === 0 && empty($error))) {
                return AjaxResponse::success();
            } else if (count($errors) > 0)
                return AjaxResponse::error($errors[0]);
        }
        return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
    }

    public function generic_recipe_api(JSONRequestParser $parser, ActionHandler $handler, ?callable $trigger_after = null): Response {
        $citizen = $this->getActiveCitizen();
        $town = $citizen->getTown();

        if (!$parser->has_all(['id'], true))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $id = (int)$parser->get('id');

        /** @var Recipe $recipe */
        $recipe = $this->entity_manager->getRepository(Recipe::class)->find( $id );
        if ($recipe === null || !in_array($recipe->getType(), [Recipe::ManualAnywhere, Recipe::ManualOutside, Recipe::ManualInside]))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($recipe->getName() === 'com027' && !$citizen->getZone() ) {

            $lab = $this->entity_manager->getRepository(CitizenHomeUpgradePrototype::class)->findOneByName('lab');
            $home_lab_upgrade = $lab ? $this->entity_manager->getRepository(CitizenHomeUpgrade::class)->findOneByPrototype($citizen->getHome(), $lab) : null;
            if ($home_lab_upgrade) {
                $this->addFlash("error", $this->translator->trans('Dafür solltest du dein Labor verwenden...', [], 'game'));
                if (!$this->citizen_handler->hasStatusEffect($citizen, 'tg_tried_pp')) {
                    $this->citizen_handler->inflictStatus($citizen, 'tg_tried_pp');
                    $this->entity_manager->persist($citizen);
                    $this->entity_manager->flush();
                    return AjaxResponse::success();
                }
            }
        }

        if (($error = $handler->execute_recipe( $citizen, $recipe, $remove, $message )) !== ActionHandler::ErrorNone ) {
            if ($error === ErrorHelper::ErrorItemsMissing && in_array($recipe->getType(), [Recipe::ManualAnywhere, Recipe::ManualInside, Recipe::ManualOutside])) {
                $items = "";
                $count = 0;
                foreach ($recipe->getSource()->getEntries() as $entry) {
                    if (!empty($items)) {
                        if (++$count < $recipe->getSource()->getEntries()->count()-1)
                            $items .= ", ";
                        else
                            $items .= " " . $this->translator->trans("und", [], "global") . " ";
                    }
                    $items .= $this->log->wrap($this->log->iconize($entry->getPrototype()));
                }
                $this->addFlash("error", $this->translator->trans('Du brauchst noch folgende Gegenstände: {list}.', ["{list}" => $items], 'game'));
                return AjaxResponse::success();
            } else {
                return AjaxResponse::success($error);
            }
        } else try {

            if ($trigger_after) $trigger_after($recipe);

            $this->entity_manager->persist($town);
            $this->entity_manager->persist($citizen);
            foreach ($remove as $e) $this->entity_manager->remove( $e );
            $this->entity_manager->flush();
            if ($message) $this->addFlash( 'notice', $message );
            return AjaxResponse::success();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }
    }

    public function get_public_map_blob( $displayType, $class = 'day' ): array {
        $zones = []; $range_x = [PHP_INT_MAX,PHP_INT_MIN]; $range_y = [PHP_INT_MAX,PHP_INT_MIN];

        $citizen_is_shaman =
            ($this->citizen_handler->hasRole($this->getActiveCitizen(), 'shaman')
                || $this->getActiveCitizen()->getProfession()->getName() == 'shaman');

        $soul_zones_ids = $citizen_is_shaman
            ? array_map(function(Zone $z) { return $z->getId(); },$this->zone_handler->getSoulZones( $this->getActiveCitizen()->getTown() ) )
            : [];

        $upgraded_map = $this->town_handler->getBuilding($this->getActiveCitizen()->getTown(), 'item_electro_#00', true) !== null;

        $local_zones = [];
        $citizen_zone = $this->getActiveCitizen()->getZone();

        $scavenger_sense = $this->getActiveCitizen()->getProfession()->getName() === 'collec';
        $scout_sense     = $this->getActiveCitizen()->getProfession()->getName() === 'hunter';

        $citizen_zone_cache = [];
        foreach ($this->getActiveCitizen()->getTown()->getCitizens() as $citizen)
            if ($citizen->getAlive() && $citizen->getZone() !== null) {
                if (!isset($citizen_zone_cache[$citizen->getZone()->getId()])) $citizen_zone_cache[$citizen->getZone()->getId()] = [$citizen];
                else $citizen_zone_cache[$citizen->getZone()->getId()][] = $citizen;
            }

        $rand_backup = mt_rand(PHP_INT_MIN, PHP_INT_MAX);
        mt_srand($this->entity_manager->getRepository(ZombieEstimation::class)->findOneBy(['town' => $this->getActiveCitizen()->getTown(), 'day' => $this->getActiveCitizen()->getTown()->getDay()])?->getSeed() ?? 0);

        foreach ($this->getActiveCitizen()->getTown()->getZones() as $zone) {
            $x = $zone->getX();
            $y = $zone->getY();

            $range_x = [ min($range_x[0], $x), max($range_x[1], $x) ];
            $range_y = [ min($range_y[0], $y), max($range_y[1], $y) ];

            $current_zone = ['x' => $x, 'y' => $y];
            if ( in_array( $zone->getId(), $soul_zones_ids) )
                $current_zone['s'] = true;

            if ($citizen_zone !== null
                && max( abs( $citizen_zone->getX() - $zone->getX() ), abs( $citizen_zone->getY() - $zone->getY() ) ) <= 2
                && ( abs( $citizen_zone->getX() - $zone->getX() ) + abs( $citizen_zone->getY() - $zone->getY() ) ) < 4
            ) $local_zones[] = $zone;

            if ($zone->getDiscoveryStatus() <= Zone::DiscoveryStateNone) {
                if ($current_zone['s'] ?? false)
                    $zones[] = $current_zone;
                continue;
            }

            $current_zone['t'] = $zone->getDiscoveryStatus() >= Zone::DiscoveryStateCurrent;
            if ($zone->getDiscoveryStatus() >= Zone::DiscoveryStateCurrent) {
                if ($zone->getZombieStatus() >= Zone::ZombieStateExact)
                    $current_zone['z'] = $zone->getZombies();
                if ($zone->getZombieStatus() >= Zone::ZombieStateEstimate)
                    $current_zone['d'] = $this->zone_handler->getZoneDangerLevelNumber( $zone, mt_rand(PHP_INT_MIN, PHP_INT_MAX), $upgraded_map );
            }

            if ($zone->isTownZone()) {
                $current_zone['td'] = $this->getActiveCitizen()->getTown()->getDevastated();
                $current_zone['r'] = [
                    'n' => $this->getActiveCitizen()->getTown()->getName(),
                    'b' => false,
                    'e' => false,
                ];
            } elseif ($zone->getPrototype()) $current_zone['r'] = [
                'n' => $zone->getBuryCount() > 0
                    ? $this->translator->trans( 'Verschüttete Ruine', [], 'game' )
                    : $this->translator->trans( $zone->getPrototype()->getLabel(), [], 'game' ),
                'b' => $zone->getBuryCount() > 0,
                'e' => $zone->getPrototype()->getExplorable()
            ];

            if ($this->getActiveCitizen()->getZone() === $zone) $current_zone['cc'] = true;

            if (!$zone->isTownZone() && !$this->getActiveCitizen()->getVisitedZones()->contains( $zone ))
                $current_zone['g'] = true;

            if (!$zone->isTownZone() && $zone->getTag()) $current_zone['tg'] = $zone->getTag()->getRef();
            if (!$zone->isTownZone() && $this->getActiveCitizen()->getZone() === null
                && !$this->getActiveCitizen()->getTown()->getChaos()
                && !empty( $citizen_zone_cache[$zone->getId()] ?? [] ) )
                $current_zone['c'] = array_map( fn(Citizen $c) => $c->getName(), $citizen_zone_cache[$zone->getId()] );
            elseif ($zone->isTownZone())
                $current_zone['co'] = count( array_filter( $this->getActiveCitizen()->getTown()->getCitizens()->getValues(), fn(Citizen $c) => $c->getAlive() && $c->getZone() === null ) );

            $zones[] = $current_zone;
        }

        mt_srand($rand_backup);

        $all_tags = [];
        $last = 0;
        foreach ($this->entity_manager->getRepository(ZoneTag::class)->findBy([], ['ref' => 'ASC']) as $i => $tag) {
            for (;$last <= $i-1;$last++) $all_tags[] = '';
            $all_tags[] = $tag->getRef() !== ZoneTag::TagNone ? $this->translator->trans( $tag->getLabel(), [], 'game' ) : '';
            $last++;
        }

        return [
            'displayType' => $displayType,
            'className' => $class,
            'etag'  => time(),
            'fx' => !$this->getActiveCitizen()->getUser()->getDisableFx(),
            'map' => [
                'geo' => [ 'x0' => $range_x[0], 'x1' => $range_x[1], 'y0' => $range_y[0], 'y1' => $range_y[1] ],
                'zones' => $zones,
                'local' => array_map( function(Zone $z) use ($citizen_zone,$scavenger_sense,$scout_sense) {
                    $local = $citizen_zone === $z;
                    $adjacent = ( abs( $citizen_zone->getX() - $z->getX() ) + abs( $citizen_zone->getY() - $z->getY() ) ) <= 1;
                    $obj = [
                        'xr' => $z->getX() - $citizen_zone->getX(), 'yr' => $z->getY() - $citizen_zone->getY(),
                        'v' => $z->getDiscoveryStatus() > Zone::DiscoveryStateNone
                    ];

                    if ( $z->isTownZone() ) {
                        $obj['r'] = $this->asset->getUrl('build/images/ruin/town.gif');
                        if ($local) $obj['n'] = $this->getActiveCitizen()->getTown()->getName();
                    } elseif ($z->getPrototype() && $z->getBuryCount() > 0) {
                        $obj['r'] = $this->asset->getUrl('build/images/ruin/burried.gif');
                        if ($local) $obj['n'] = $this->translator->trans( 'Verschüttete Ruine', [], 'game' );
                    } elseif ($z->getPrototype()) {
                        $obj['r'] = $this->asset->getUrl("build/images/ruin/{$z->getPrototype()->getIcon()}.gif");
                        if ($local) $obj['n'] = $this->translator->trans( $z->getPrototype()->getLabel(), [], 'game' );
                    }

                    if (!$local && $adjacent && !$z->isTownZone()) {
                        $obj['vv'] = $this->getActiveCitizen()->getVisitedZones()->contains($z);
                        if ($scavenger_sense && $z->getDiscoveryStatus() > Zone::DiscoveryStateNone) {
                            $obj['ss'] = $z->getDigs() > 0 || ($z->getPrototype() && $z->getRuinDigs() > 0);
                            if (!$obj['ss']) $obj['se'] = 2;
                        }
                        if ($scout_sense) {
                            $obj['sh'] = $z->getPersonalScoutEstimation($this->getActiveCitizen());
                            $obj['se'] = $obj['sh'] >= 9 ? 2 : ($obj['sh'] >= 5 ? 1 : 0);
                        }
                    }

                    if ($local) {
                        $obj['x']  = $z->getX(); $obj['y'] = $z->getY();
                        $obj['z']  = $z->getZombies();
                        $obj['zc'] = max(0, $z->getInitialZombies() - $z->getZombies());
                        $obj['c']  = $z->getCitizens()->count() + ($z->isTownZone()
                            ? count( array_filter( $this->getActiveCitizen()->getTown()->getCitizens()->getValues(), fn(Citizen $c) => $c->getAlive() && $c->getZone() === null ) )
                            : 0);
                    }

                    return $obj;
                }, $local_zones )
            ],
            'routes' => array_map( fn(ExpeditionRoute $route) => [
                'id' => $route->getId(),
                'owner' => $route->getOwner()->getName(),
                'length' => $route->getLength(),
                'label' => $route->getLabel(),
                'stops' => array_map( fn(array $stop) => ['x' => $stop[0], 'y' => $stop[1]], $route->getData() ),
            ], $this->entity_manager->getRepository(ExpeditionRoute::class)->findByTown( $this->getActiveCitizen()->getTown() ) ),
            'strings' => [
                'zone' => $this->translator->trans('Zone', [], 'game'),
                'distance' => $this->translator->trans('Entfernung', [], 'game'),
                'danger' => [
                    $this->translator->trans('Isolierte Zombies', [], 'game'),
                    $this->translator->trans('Die Zombies verstümmeln', [], 'game'),
                    $this->translator->trans('Horde der Zombies', [], 'game'),
                ],
                'tags' => array_values($all_tags),
                'mark' => $this->translator->trans('Mark.', [], 'game'),
                'global' => $this->translator->trans('Global', [], 'game'),
                'routes' => $this->translator->trans('Routen', [], 'game'),
                'map'    => $this->translator->trans('Karte', [], 'game'),
                'close'  => $this->translator->trans('Schließen', [], 'game'),
                'position' => $this->translator->trans('Position:', [], 'game'),
            ]
        ];
    }

    /**
     * @param int $id
     * @param ItemTargetDefinition|null $target
     * @param Inventory[] $inventories
     * @param object|null $return
     * @return bool
     */
    private function extract_target_object(int $id, ?ItemTargetDefinition $target, array $inventories, ?object &$return): bool {
        $return = null;
        if (!$target) return true;

        switch ($target->getSpawner()) {
            case ItemTargetDefinition::ItemSelectionType: case ItemTargetDefinition::ItemSelectionTypePoison:
                $return = $this->entity_manager->getRepository(Item::class)->find( $id );
                if (!$return) return false;

                foreach ($inventories as $inventory)
                    if ($inventory->getItems()->contains( $return ))
                        return true;

                return false;
            case ItemTargetDefinition::ItemTypeSelectionType:
                $return = $this->entity_manager->getRepository(ItemPrototype::class)->find( $id );
                if (!$return) return false;
                return true;
            case ItemTargetDefinition::ItemHeroicRescueType:
                $return = $this->entity_manager->getRepository(Citizen::class)->find( $id );
                if ($return->getTown()->getId() !== $this->getActiveCitizen()->getTown()->getId()) {
                    $return = null;
                    return false;
                }
                return true;
            case ItemTargetDefinition::ItemCitizenType: case ItemTargetDefinition::ItemCitizenVoteType:
                $return = $this->entity_manager->getRepository(Citizen::class)->find( $id );
                if (!$return->getAlive() || $return->getTown()->getId() !== $this->getActiveCitizen()->getTown()->getId()) {
                    $return = null;
                    return false;
                }
                return true;
            case ItemTargetDefinition::ItemCitizenOnZoneType: case ItemTargetDefinition::ItemCitizenOnZoneSBType:
                $return = $this->entity_manager->getRepository(Citizen::class)->find( $id );
                if (!$return->getAlive() || $return->getZone() !== $this->getActiveCitizen()->getZone()) {
                    $return = null;
                    return false;
                } else if ( $target->getSpawner() === ItemTargetDefinition::ItemCitizenOnZoneSBType && $return->getSpecificActionCounter(ActionCounter::ActionTypeSandballHit)->getLast() !== null && $return->getSpecificActionCounter(ActionCounter::ActionTypeSandballHit)->getLast()->getTimestamp() >= (time() - 1800) ) {
                    $return = null;
                    return false;
                }
                return true;
            default: return false;
        }
    }

    public function generic_heroic_action_api(JSONRequestParser $parser, ?callable $trigger_after = null): Response {
        $target_id = (int)$parser->get('target', -1);
        $action_id = (int)$parser->get('action', -1);

        /** @var Item|ItemPrototype|null $target */
        $target = null;
        /** @var HeroicActionPrototype|null $heroic */
        $heroic = ($action_id < 0) ? null : $this->entity_manager->getRepository(HeroicActionPrototype::class)->find( $action_id );

        if ( !$heroic ) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $citizen = $this->getActiveCitizen();

        $zone = $citizen->getZone();
        if (!$citizen->getProfession()->getHeroic() || !$citizen->getHeroicActions()->contains( $heroic )) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if (!$this->extract_target_object( $target_id, $heroic->getAction()->getTarget(), [ $citizen->getInventory(), $zone ? $zone->getFloor() : $citizen->getHome()->getChest() ], $target ))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $item = null;
        if (($error = $this->action_handler->execute( $citizen, $item, $target, $heroic->getAction(), $msg, $remove )) === ActionHandler::ErrorNone) {

            $heroic_action = $heroic->getAction();
            if ($trigger_after) $trigger_after($heroic_action);
            $citizen->removeHeroicAction($heroic);
            $citizen->addUsedHeroicAction($heroic);

            // Add the picto Heroic Action
            $picto = $this->entity_manager->getRepository(PictoPrototype::class)->findOneByName("r_heroac_#00");
            $this->picto_handler->give_picto($citizen, $picto);

            $this->entity_manager->persist($citizen);
            foreach ($remove as $remove_entry)
                $this->entity_manager->remove($remove_entry);
            try {
                $this->entity_manager->flush();
            } catch (Exception $e) {
                return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
            }

            if ($msg) $this->addFlash( 'notice', $msg );
        } elseif ($error === ActionHandler::ErrorActionForbidden) {
            if (!empty($msg)) $msg = $this->translator->trans($msg, [], 'game');
            return AjaxResponse::error($error, ['message' => $msg]);
        }
        else return AjaxResponse::error( $error );

        return AjaxResponse::success();
    }

    public function generic_special_action_api(JSONRequestParser $parser, ?callable $trigger_after = null): Response {
        $target_id = (int)$parser->get('target', -1);
        $action_id = (int)$parser->get('action', -1);

        /** @var Item|ItemPrototype|null $target */
        $target = null;
        /** @var SpecialActionPrototype|null $special */
        $special = ($action_id < 0) ? null : $this->entity_manager->getRepository(SpecialActionPrototype::class)->find( $action_id );

        if ( !$special ) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $citizen = $this->getActiveCitizen();

        $zone = $citizen->getZone();
        if (!$citizen->getSpecialActions()->contains( $special )) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if (!$this->extract_target_object( $target_id, $special->getAction()->getTarget(), [ $citizen->getInventory(), $zone ? $zone->getFloor() : $citizen->getHome()->getChest() ], $target ))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $item = null;
        if (($error = $this->action_handler->execute( $citizen, $item, $target, $special->getAction(), $msg, $remove )) === ActionHandler::ErrorNone) {

            $special_action = $special->getAction();
            if ($trigger_after) $trigger_after($special_action);
            if ( $special->getConsumable() ) $citizen->removeSpecialAction($special);

            // Special handler for the ARMA action
            $arma_actions = ['special_armag','special_armag_d','special_armag_n'];
            if (in_array( $special->getName(), $arma_actions))
                foreach ($citizen->getSpecialActions() as $specialAction)
                    if (in_array( $specialAction->getName(), $arma_actions))
                        $citizen->removeSpecialAction($specialAction);

            $this->entity_manager->persist($citizen);
            foreach ($remove as $remove_entry)
                $this->entity_manager->remove($remove_entry);
            try {
                $this->entity_manager->flush();
            } catch (Exception $e) {
                return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
            }

            if ($msg) $this->addFlash( 'notice', $msg );
        } elseif ($error === ActionHandler::ErrorActionForbidden) {
            if (!empty($msg)) $msg = $this->translator->trans($msg, [], 'game');
            return AjaxResponse::error($error, ['message' => $msg]);
        }
        else return AjaxResponse::error( $error );

        return AjaxResponse::success();
    }

    public function generic_home_action_api(JSONRequestParser $parser): Response {
        $target_id = (int)$parser->get('target', -1);
        $action_id = (int)$parser->get('action', -1);

        /** @var Item|ItemPrototype|null $target */
        $target = null;
        /** @var HomeActionPrototype|null $home_action */
        $home_action = ($action_id < 0) ? null : $this->entity_manager->getRepository(HomeActionPrototype::class)->find( $action_id );

        if ( !$home_action ) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $citizen = $this->getActiveCitizen();

        if ($citizen->getZone()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if (!$this->extract_target_object( $target_id, $home_action->getAction()->getTarget(), [ $citizen->getInventory(), $citizen->getHome()->getChest() ], $target ))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $item = null;
        if (($error = $this->action_handler->execute( $citizen, $item, $target, $home_action->getAction(), $msg, $remove )) === ActionHandler::ErrorNone) {
            $this->entity_manager->persist($citizen);
            foreach ($remove as $remove_entry)
                $this->entity_manager->remove($remove_entry);
            try {
                $this->entity_manager->flush();
            } catch (Exception $e) {
                return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
            }

            if ($msg) $this->addFlash( 'notice', $msg );
        } elseif ($error === ActionHandler::ErrorActionForbidden) {
            if (!empty($msg)) $msg = $this->translator->trans($msg, [], 'game');
            return AjaxResponse::error($error, ['message' => $msg]);
        }
        else return AjaxResponse::error( $error );

        return AjaxResponse::success();
    }

    public function generic_camping_action_api(JSONRequestParser $parser): Response {
      if (!$this->getTownConf()->get(TownConf::CONF_FEATURE_CAMPING, false))
          return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

      $action_id = (int)$parser->get('action', -1);

      /** @var Item|ItemPrototype|null $target */
      $target = null;
      /** @var CampingActionPrototype|null $heroic */
      $camping = ($action_id < 0) ? null : $this->entity_manager->getRepository(CampingActionPrototype::class)->find( $action_id );

      if ( !$camping ) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
      $citizen = $this->getActiveCitizen();

      $zone = $citizen->getZone();
      if ($zone && $zone->getX() === 0 && $zone->getY() === 0 ) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

      $item = null;
      if (($error = $this->action_handler->execute( $citizen, $item, $target, $camping->getAction(), $msg, $remove )) === ActionHandler::ErrorNone) {

        switch($camping->getName()){
            case 'cm_campsite_improve':
                $this->entity_manager->persist($this->log->beyondCampingImprovement($citizen));
                break;
            case 'cm_campsite_hide':
            case 'cm_campsite_tomb':
                // Remove citizen from escort
                foreach ($citizen->getLeadingEscorts() as $escorted_citizen) {
                    $escorted_citizen->getCitizen()->getEscortSettings()->setLeader( null );
                    $this->entity_manager->persist($escorted_citizen);
                }

                if ($citizen->getEscortSettings()) $this->entity_manager->remove($citizen->getEscortSettings());
                $citizen->setEscortSettings(null);

                $this->entity_manager->persist($this->log->beyondCampingHide($citizen));
                break;
            case 'cm_campsite_unhide':
            case 'cm_campsite_untomb':
                $this->entity_manager->persist($this->log->beyondCampingUnhide($citizen));
                break;
        }

        $this->entity_manager->persist($citizen);
        foreach ($remove as $remove_entry)
            $this->entity_manager->remove($remove_entry);
        try {
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        if ($msg) $this->addFlash( 'notice', $msg );
      } elseif ($error === ActionHandler::ErrorActionForbidden) {
        if (!empty($msg)) $msg = $this->translator->trans($msg, [], 'game');
        return AjaxResponse::error($error, ['message' => $msg]);
      }
      else return AjaxResponse::error( $error );

      return AjaxResponse::success();
    }

    public function generic_action_api(JSONRequestParser $parser, ?callable $trigger_after = null, ?Citizen $base_citizen = null): Response {
        $item_id =   (int)$parser->get('item',   -1);
        $target_id = (int)$parser->get('target', -1);
        $action_id = (int)$parser->get('action', -1);

        /** @var Item|null $item */
        $item   = ($item_id < 0)   ? null : $this->entity_manager->getRepository(Item::class)->find( $item_id );
        /** @var Item|ItemPrototype|null $target */
        $target = null;
        /** @var ItemAction|null $action */
        $action = ($action_id < 0) ? null : $this->entity_manager->getRepository(ItemAction::class)->find( $action_id );

        $escort_mode = $base_citizen !== null;
        if ( !$item || !$action || $item->getBroken() ) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        if ( $escort_mode && $item->getPoison()->poisoned() ) return AjaxResponse::error( BeyondController::ErrorEscortActionRefused );
        $citizen = $base_citizen ?? $this->getActiveCitizen();

        $zone = $citizen->getZone();
        if ($zone && !$this->zone_handler->check_cp($zone) && !$action->getAllowWhenTerrorized() && $this->citizen_handler->hasStatusEffect($citizen, 'terror') && !$this->zone_handler->check_cp($this->getActiveCitizen()->getZone()))
            return AjaxResponse::error( $citizen === $this->getActiveCitizen() ? BeyondController::ErrorTerrorized : BeyondController::ErrorEscortTerrorized );

        if (!$action->getAllowedAtGate() && $zone && $zone->isTownZone())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        // $secondary_inv = $zone ? $zone->getFloor() : $citizen->getHome()->getChest();
        if($zone) {
            if($this->conf->getTownConfiguration($this->getActiveCitizen()->getTown())->get(TownConf::CONF_MODIFIER_FLOOR_ASMBLY, false)) {
                if(!$this->getActiveCitizen()->activeExplorerStats()) {
                    $secondary_inv = $zone->getFloor();
                } else {
                    $ex = $this->getActiveCitizen()->activeExplorerStats();
                    $ruinZone = $this->entity_manager->getRepository(RuinZone::class)->findOneByPosition($this->getActiveCitizen()->getZone(), $ex->getX(), $ex->getY());
                    $secondary_inv = $ruinZone->getFloor();
                }
            } else {
                $secondary_inv = null;
            }
        } else {
            $secondary_inv = $citizen->getHome()->getChest();
        }

        if (!$citizen->getInventory()->getItems()->contains( $item ) && ($secondary_inv && !$secondary_inv->getItems()->contains( $item ))) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        if (!$this->extract_target_object( $target_id, $action->getTarget(), [ $citizen->getInventory(), $secondary_inv ], $target ))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        $url = null;

        if (($error = $this->action_handler->execute( $citizen, $item, $target, $action, $msg, $remove, false, $escort_mode )) === ActionHandler::ErrorNone) {

            if ($trigger_after) $trigger_after($action);

            if ($action->getName() == 'improve') {
                $this->entity_manager->persist($this->log->beyondCampingItemImprovement($citizen, $item->getPrototype()));
            }

            $this->entity_manager->persist($citizen);
            if ($item->getInventory())
                $this->entity_manager->persist($item);
            foreach ($remove as $remove_entry)
                $this->entity_manager->remove($remove_entry);
            try {
                $this->entity_manager->flush();
            } catch (Exception $e) {
                return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
            }

            if ($msg) $this->addFlash( 'notice', $msg );
            
            if($text = $this->entity_manager->getRepository(FoundRolePlayText::class)->findNextUnreadText($this->getUser())){
                /** @var FoundRolePlayText $text */
                $url = $this->generateUrl("soul_rp", ['page' => 1, 'id' => $text->getId()]);
            }

        } elseif ($error === ActionHandler::ErrorActionForbidden) {
            if (!empty($msg)) $msg = $this->translator->trans($msg, [], 'items');
            return AjaxResponse::error($error, ['message' => $msg]);
        }
        else return AjaxResponse::error( $error );
        return AjaxResponse::success( true, ['url' => $url] );
    }

    public function reportCitizen( Citizen|CitizenRankingProxy $citizen, AdminReportSpecification $specification, JSONRequestParser $parser, RateLimiterFactory $reportToModerationLimiter ): Response {

        $user = $this->getUser();
        $reason = $parser->get_int('reason', 0, 0, 13);
        if ($reason === 0) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $proxy   = is_a( $citizen, CitizenRankingProxy::class ) ? $citizen : $citizen->getRankingEntry();
        $citizen = $proxy->getCitizen();

        if ($specification === AdminReportSpecification::CitizenAnnouncement && (!$citizen || $citizen->getTown()->getId() !== $this->getActiveCitizen()->getTown()->getId()) )
            return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable );

        $payload = match ($specification) {
            AdminReportSpecification::CitizenAnnouncement => $citizen?->getHome()->getDescription(),
            AdminReportSpecification::CitizenLastWords => $proxy->getLastWords(),
            AdminReportSpecification::CitizenTownComment => $proxy->getComment(),
            default => null
        };

        if (empty( $payload ))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $reports = $this->entity_manager->getRepository(AdminReport::class)->findBy(['citizen' => $proxy, 'specification' => $specification->value]);
        foreach ($reports as $report)
            if ($report->getSourceUser()->getId() == $user->getId())
                return AjaxResponse::success();

        $report_count = count($reports) + 1;

        if (!$reportToModerationLimiter->create( $user->getId() )->consume()->isAccepted())
            return AjaxResponse::error( ErrorHelper::ErrorRateLimited);

        $details = $parser->trimmed('details');
        $newReport = (new AdminReport())
            ->setSourceUser($user)
            ->setReason( $reason )
            ->setDetails( $details ?: null )
            ->setTs(new \DateTime('now'))
            ->setCitizen( $proxy )
            ->setSpecification( $specification );

        try {
            $this->entity_manager->persist($newReport);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        try {
            $this->crow->triggerExternalModNotification( match ($specification) {
                AdminReportSpecification::CitizenAnnouncement => "A citizen's home message has been reported.",
                AdminReportSpecification::CitizenLastWords => "A citizen's last words have been reported.",
                AdminReportSpecification::CitizenTownComment => "A town comment has been reported.",
                default => '[invalid]'
            }, $proxy, $newReport, "This is report #{$report_count}." );
        } catch (\Throwable $e) {}

        $message = $this->translator->trans('Du hast die Nachricht von {username} dem Raben gemeldet. Wer weiß, vielleicht wird {username} heute Nacht stääärben...', ['{username}' => '<span>' . $proxy->getUser()->getName() . '</span>'], 'game');
        $this->addFlash('notice', $message);
        return AjaxResponse::success( );
    }
}
