<?php

namespace App\Controller;

use App\Annotations\GateKeeperProfile;
use App\Annotations\Semaphore;
use App\Entity\AccountRestriction;
use App\Entity\ActionCounter;
use App\Entity\ChatSilenceTimer;
use App\Entity\Citizen;
use App\Entity\CitizenEscortSettings;
use App\Entity\CitizenStatus;
use App\Entity\DigTimer;
use App\Entity\EscapeTimer;
use App\Entity\EscortActionGroup;
use App\Entity\HomeIntrusion;
use App\Entity\Item;
use App\Entity\ItemAction;
use App\Entity\ItemGroup;
use App\Entity\ItemPrototype;
use App\Entity\PictoPrototype;
use App\Entity\Recipe;
use App\Entity\RuinExplorerStats;
use App\Entity\Zone;
use App\Entity\ZoneActivityMarker;
use App\Entity\ZoneTag;
use App\Enum\ZoneActivityMarkerType;
use App\Response\AjaxResponse;
use App\Service\ActionHandler;
use App\Service\CitizenHandler;
use App\Service\ConfMaster;
use App\Service\CrowService;
use App\Service\DeathHandler;
use App\Service\ErrorHelper;
use App\Service\GameFactory;
use App\Service\GameProfilerService;
use App\Service\HTMLService;
use App\Service\InventoryHandler;
use App\Service\PictoHandler;
use App\Service\ItemFactory;
use App\Service\JSONRequestParser;
use App\Service\LogTemplateHandler;
use App\Service\RandomGenerator;
use App\Service\TimeKeeperService;
use App\Service\TownHandler;
use App\Service\UserHandler;
use App\Service\ZoneHandler;
use App\Structures\EventConf;
use App\Structures\ItemRequest;
use App\Structures\TownConf;
use App\Translation\T;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 * @GateKeeperProfile(only_alive=true, only_beyond=true)
 * @Semaphore("town", scope="town")
 */
class BeyondController extends InventoryAwareController
{

    const ErrorNoReturnFromHere     = ErrorHelper::BaseBeyondErrors + 1;
    const ErrorNotReachableFromHere = ErrorHelper::BaseBeyondErrors + 2;
    const ErrorZoneBlocked          = ErrorHelper::BaseBeyondErrors + 3;
    const ErrorZoneUnderControl     = ErrorHelper::BaseBeyondErrors + 4;
    const ErrorAlreadyWounded       = ErrorHelper::BaseBeyondErrors + 5;
    const ErrorNotDiggable          = ErrorHelper::BaseBeyondErrors + 6;
    const ErrorDoorClosed           = ErrorHelper::BaseBeyondErrors + 7;
    const ErrorChatMessageInvalid   = ErrorHelper::BaseBeyondErrors + 8;
    const ErrorTrashLimitHit        = ErrorHelper::BaseBeyondErrors + 9;
    const ErrorNoMovementWhileHiding= ErrorHelper::BaseBeyondErrors + 10;
    const ErrorEscortLimitHit       = ErrorHelper::BaseBeyondErrors + 11;
    const ErrorEscortFailure        = ErrorHelper::BaseBeyondErrors + 12;
    const ErrorTerrorized           = ErrorHelper::BaseBeyondErrors + 13;
    const ErrorEscortActionRefused  = ErrorHelper::BaseBeyondErrors + 14;
    const ErrorEscortFailureRuin    = ErrorHelper::BaseBeyondErrors + 15;
    const ErrorEscortTerrorized     = ErrorHelper::BaseBeyondErrors + 16;

    protected $game_factory;
    protected ZoneHandler $zone_handler;
    protected $item_factory;
    protected DeathHandler $death_handler;

    /**
     * BeyondController constructor.
     * @param EntityManagerInterface $em
     * @param InventoryHandler $ih
     * @param CitizenHandler $ch
     * @param ActionHandler $ah
     * @param TimeKeeperService $tk
     * @param DeathHandler $dh
     * @param PictoHandler $ph
     * @param TranslatorInterface $translator
     * @param GameFactory $gf
     * @param RandomGenerator $rg
     * @param ItemFactory $if
     * @param ZoneHandler $zh
     * @param LogTemplateHandler $lh
     * @param ConfMaster $conf
     * @param Packages $a
     * @param UserHandler $uh
     * @param CrowService $armbrust
     * @param TownHandler $th
     */
    public function __construct(
        EntityManagerInterface $em, InventoryHandler $ih, CitizenHandler $ch, ActionHandler $ah, TimeKeeperService $tk,
        DeathHandler $dh, PictoHandler $ph, TranslatorInterface $translator, GameFactory $gf, RandomGenerator $rg,
        ItemFactory $if, ZoneHandler $zh, LogTemplateHandler $lh, ConfMaster $conf, Packages $a, UserHandler $uh,
        CrowService $armbrust, TownHandler $th)
    {
        parent::__construct($em, $ih, $ch, $ah, $dh, $ph, $translator, $lh, $tk, $rg, $conf, $zh, $uh, $armbrust, $th, $a);
        $this->game_factory = $gf;
        $this->item_factory = $if;
        $this->zone_handler = $zh;
    }

    protected function deferZoneUpdate() {
        $str = $this->zone_handler->updateRuinZone( $this->getActiveCitizen()->getZone()->activeExplorerStats() );
        if ($str) $this->addFlash( 'error', $str );
        $str = $this->zone_handler->updateZone( $this->getActiveCitizen()->getZone(), null, $this->getActiveCitizen() );
        if ($str) $this->addFlash( 'notice', $str );
        $this->entity_manager->flush();
    }

    public function before(): bool
    {
        if (parent::before()) {
            $this->deferZoneUpdate();
            return true;
        } else return false;
    }

    protected function addDefaultTwigArgs( ?string $section = null, ?array $data = null, $merge_map = true ): array {
        $zone = $this->getActiveCitizen()->getZone();
        $blocked = !$this->zone_handler->check_cp($zone, $cp);
        $escape = $this->get_escape_timeout( $this->getActiveCitizen(), true );
        $citizen_tired = $this->getActiveCitizen()->getAp() <= 0 || $this->citizen_handler->isTired( $this->getActiveCitizen());
        $citizen_hidden = !$this->activeCitizenIsNotCamping();

        $scavenger_sense = $this->getActiveCitizen()->getProfession()->getName() === 'collec';
        $scout_level = null;
        $scout_sense = false;

        if ($this->getActiveCitizen()->getProfession()->getName() === 'hunter') {
            $scout_level = $zone->getScoutLevel();
            $scout_sense = true;
        }

        $scout_movement = $this->inventory_handler->countSpecificItems(
            $this->getActiveCitizen()->getInventory(), $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'vest_on_#00'])
        ) > 0;

        $trash_count = ($this->getActiveCitizen()->getBanished() || $this->getActiveCitizen()->getTown()->getDevastated()) ? $this->getActiveCitizen()->getSpecificActionCounterValue(ActionCounter::ActionTypeTrash) : 0;

        $rucksack_sizes = [];
        $escort_actions = [];
        foreach ($this->getActiveCitizen()->getValidLeadingEscorts() as $escort) {
            if ($escort->getAllowInventoryAccess())
                $rucksack_sizes[ $escort->getCitizen()->getId() ] = $this->inventory_handler->getSize( $escort->getCitizen()->getInventory() );
            $escort_actions[ $escort->getCitizen()->getId() ] = $this->action_handler->getAvailableItemEscortActions( $escort->getCitizen() );
        }


        $zone_players = count($zone->getCitizens());

        if ($zone->isTownZone()) {
            $zone_players += $this->entity_manager->getRepository(Citizen::class)->count(['town' => $this->getActiveCitizen()->getTown(), 'zone' => null, 'alive' => true]);
        }

        $allow_movement = (!$blocked || $escape > 0 || $scout_movement) && !$citizen_tired && !$citizen_hidden && ($this->getActiveCitizen()->getEscortSettings() === null || $this->getActiveCitizen()->getEscortSettings()->getLeader() === null);
        return parent::addDefaultTwigArgs( $section, array_merge( [
            'zone_players' => $zone_players,
            'zone_zombies' => max(0,$zone->getZombies()),
            'zone_splatter' => max(0, $zone->getInitialZombies() - $zone->getZombies()),
            'can_attack_citizen' => !$this->citizen_handler->isTired($this->getActiveCitizen()) && $this->getActiveCitizen()->getAp() >= $this->getTownConf()->get(TownConf::CONF_MODIFIER_ATTACK_AP, 5) && !$this->citizen_handler->isWounded($this->getActiveCitizen()) && !$zone->isTownZone(),
            'can_devour_citizen' => $this->getActiveCitizen()->hasRole('ghoul') && !$zone->isTownZone(),
            'allow_devour_citizen' => !$this->citizen_handler->hasStatusEffect($this->getActiveCitizen(), 'tg_ghoul_eat'),
            'zone_cp' => $cp,
            'zone'  =>  $zone,
            'allow_movement' => $allow_movement,
            'active_scout_mode' => $scout_movement,
            'scout_level' => $scout_level,
            'scout_sense' => $scout_sense,
            'scavenger_sense' => $scavenger_sense,
            'heroics' => $this->getHeroicActions(),
            'specials' => $this->getSpecialActions(),
            'actions' => $this->getItemActions(),
            'camping' => $this->getCampingActions(),
            'recipes' => $this->getItemCombinations(false),
            'km' => $this->zone_handler->getZoneKm($zone),
            'town_ap' => $this->zone_handler->getZoneAp($zone),
            'lock_trash' => $trash_count >= ( $this->getActiveCitizen()->getProfession()->getName() === 'collec' ? 4 : 3 ),
            'citizen_hidden' => $citizen_hidden,
            'rucksack_sizes' => $rucksack_sizes,
            'escort_actions' => $escort_actions,
            'can_explore' => $zone->getPrototype() && $zone->getPrototype()->getExplorable() &&
                !$this->citizen_handler->hasStatusEffect( $this->getActiveCitizen(), ['terror'] ) &&
                !$this->citizen_handler->isWounded( $this->getActiveCitizen() ) &&
                (!$blocked || $scout_movement) && !$zone->activeExplorerStats() && !$this->getActiveCitizen()->currentExplorerStats(),
            'exploration_blocked_wound'     => $zone->getPrototype() && $zone->getPrototype()->getExplorable() && $this->citizen_handler->isWounded( $this->getActiveCitizen() ),
            'exploration_blocked_blocked'   => $zone->getPrototype() && $zone->getPrototype()->getExplorable() && ($blocked && !$scout_movement),
            'exploration_blocked_infection' => false,
            'exploration_blocked_terror'    => $zone->getPrototype() && $zone->getPrototype()->getExplorable() && $this->citizen_handler->hasStatusEffect( $this->getActiveCitizen(), 'terror' ),
            'exploration_blocked_in_use'    => $zone->getPrototype() && $zone->getPrototype()->getExplorable() && $zone->activeExplorerStats(),
            'exploration_blocked_already'   => $zone->getPrototype() && $zone->getPrototype()->getExplorable() && $this->getActiveCitizen()->currentExplorerStats(),
            'tired' => $this->citizen_handler->isTired($this->getActiveCitizen()),
            'status_info' => [
                'can_drink' => !$this->citizen_handler->hasStatusEffect($this->getActiveCitizen(), 'hasdrunk'),
                'can_eat' => !$this->citizen_handler->hasStatusEffect($this->getActiveCitizen(), 'haseaten')
            ],
        ], $data, $merge_map ? [
            'map_public_json'   => json_encode( $this->get_public_map_blob( $allow_movement ? 'beyond' : 'beyond-static', $this->getTownConf()->isNightTime() ? 'night' : 'day' ) )
        ] : []) );
    }

    public function get_escape_timeout(Citizen $c, bool $allow_desperate = false): int {
        $active_timer = $this->entity_manager->getRepository(EscapeTimer::class)->findActiveByCitizen( $c, false, $allow_desperate );
        return ($active_timer && (!$active_timer->getDesperate() || $allow_desperate)) ? ($active_timer->getTime()->getTimestamp() - (new DateTime())->getTimestamp()) : -1;
    }

    public function uncoverHunter(Citizen $c): bool {
        $prot = $this->inventory_handler->fetchSpecificItems( $c->getInventory(), [new ItemRequest('vest_on_#00')] );
        if ($prot) {
            $prot[0]->setPrototype( $this->entity_manager->getRepository( ItemPrototype::class )->findOneBy(['name' => 'vest_off_#00']) );
            return true;
        } else return false;
    }

    /**
     * @Route("jx/beyond/desert/{sect}", name="beyond_dashboard")
     * @param TownHandler $th
     * @param bool $inline
     * @param string|null $sect
     * @return Response
     */
    public function desert(TownHandler $th, string $sect = ''): Response
    {
        $request = Request::createFromGlobals();
        $inline = $request->headers->get('X-Render-Target') === 'beyond_desert_content';

        if (!$this->getActiveCitizen()->getHasSeenGazette())
            return $this->redirect($this->generateUrl('game_newspaper'));
            
        $town = $this->getActiveCitizen()->getTown();
        $zone = $this->getActiveCitizen()->getZone();

        $watchtower = $th->getBuilding($town, 'item_tagger_#00',  true);
        if ($watchtower) switch ($watchtower->getLevel()) {
            case 4: $port_distance = 1;  break;
            case 5: $port_distance = 2;  break;
            default:$port_distance = 0; break;
        } else $port_distance = 0;
        $distance = round(sqrt( pow($zone->getX(),2) + pow($zone->getY(),2) ));

        $can_enter = $distance <= $port_distance && !$this->getActiveCitizen()->isCamping();
        $is_on_zero = $zone->getX() == 0 && $zone->getY() == 0;

        $citizen_tired = $this->getActiveCitizen()->getAp() <= 0 || $this->citizen_handler->isTired( $this->getActiveCitizen());

        $blocked = !$this->zone_handler->check_cp($zone, $cp);
        $escape = $this->get_escape_timeout( $this->getActiveCitizen() );
        $escape_desperate = ($escape < 0) ? $this->get_escape_timeout( $this->getActiveCitizen(), true ) : -1;

        $require_ap = ($is_on_zero && $th->getBuilding($town, 'small_labyrinth_#00',  true));

        if (!$is_on_zero && $this->getTownConf()->get(TownConf::CONF_FEATURE_CAMPING, false)) {
            // Camping Information
            $camping_zone_texts = [
                1 => T::__("Wenn du hier schläfst, kannst du dich gleich selbst umbringen. Das geht schneller und du kannst deinen Tod selbst bestimmen.", 'game'),
                2 => "", // T::__('text','domain')
                3 => T::__("Hier ist so gut wie nichts, mit dem du dich verstecken könntest. Du fühlst dich leicht schutzlos...", 'game'),
                4 => T::__("Außer ein paar 'natürlichen' Schutzgelegenheiten bietet diese Zone nicht viel. Du musst dich irgendwie durchwursteln.", 'game'),
                5 => T::__("Wenn man hier bisschen sucht, lässt sich bestimmt ein adäquates Versteck finden.", 'game'),
                6 => T::__("An diesem Ort gibt es ein paar gute Versteckmöglichkeiten. Wenn du hier heute Nacht schlafen möchtest...", 'game'),
                7 => T::__("In diesem Sektor gibt es ein paar wirklich gute Unterschlupfmöglichkeiten.", 'game'),
                8 => T::__("Das ist der ideale Ort, um hier zu schlafen. An Versteckmöglichkeiten mangelt es wahrlich nicht.", 'game'),
            ];
            $zone_camping_base = ($zone->getPrototype() ? $zone->getPrototype()->getCampingLevel() : 0) + ($zone->getImprovementLevel() );
            if ($zone_camping_base <= 0) {
                $camping_zone = $camping_zone_texts[1];
            } else if ($zone_camping_base <= 2) {
                $camping_zone = $camping_zone_texts[2];
            } else if ($zone_camping_base <= 4) {
                $camping_zone = $camping_zone_texts[3];
            } else if ($zone_camping_base <= 6) {
                $camping_zone = $camping_zone_texts[4];
            } else if ($zone_camping_base <= 8) {
                $camping_zone = $camping_zone_texts[5];
            } else if ($zone_camping_base <= 10) {
                $camping_zone = $camping_zone_texts[6];
            } else if ($zone_camping_base <= 12) {
                $camping_zone = $camping_zone_texts[7];
            } else {
                $camping_zone = $camping_zone_texts[8];
            }

            $camping_zombie_texts = [
                0 => '', // T::__('text','domain')
                1 => T::__("Die Anwesenheit von ein paar Zombies in dieser Umgebung beunruhigt dich etwas...", 'game'),
                2 => T::__("Die große Anzahl der herumstreunenden Zombies ist bestimmt kein Vorteil... Verstecken könnte etwas schwierig werden.", 'game'),
            ];
            if ($zone->getZombies() >= 11) {
                $camping_zombies = $camping_zombie_texts[2];
            } else if ($zone->getZombies() >= 5) {
                $camping_zombies = $camping_zombie_texts[1];
            } else {
                $camping_zombies = $camping_zombie_texts[0];
            }

            $camping_chance_texts = [
                0 => T::__("Du schätzt, dass deine Überlebenschancen hier quasi Null sind... Besser gleich 'ne Zyanidkapsel schlucken.", 'game'),
                1 => T::__("Du schätzt, dass deine Überlebenschancen hier sehr gering sind. Vielleicht hast du ja Bock 'ne Runde Kopf oder Zahl zu spielen?", 'game'),
                2 => T::__("Du schätzt, dass deine Überlebenschancen hier gering sind. Hmmm... schwer zu sagen, wie das hier ausgeht.", 'game'),
                3 => T::__("Du schätzt, dass deine Überlebenschancen hier mittelmäßig sind. Ist allerdings einen Versuch wert.. obwohl, Unfälle passieren schnell...", 'game'),
                4 => T::__("Du schätzt, dass deine Überlebenschancen hier zufriedenstellend sind - vorausgesetzt du erlebst keine böse Überraschung.", 'game'),
                5 => T::__("Du schätzt, dass deine Überlebenschancen hier korrekt sind. Jetzt heißt's nur noch Daumen drücken!", 'game'),
                6 => T::__("Du schätzt, dass deine Überlebenschancen hier gut sind. Du müsstest hier problemlos die Nacht verbringen können.", 'game'),
                7 => T::__("Du schätzt, dass deine Überlebenschancen hier optimal sind. Niemand wird dich sehen - selbst wenn man mit dem Finger auf dich zeigt.", 'game'),
            ];
            $survival_chance = $this->getActiveCitizen()->getCampingChance() > 0
            ? $this->getActiveCitizen()->getCampingChance()
            : $this->citizen_handler->getCampingChance($this->getActiveCitizen());
            if ($survival_chance <= .10) {
                $camping_chance = $camping_chance_texts[0];
            } else if ($survival_chance <= .3) {
                $camping_chance = $camping_chance_texts[1];
            } else if ($survival_chance <= .50) {
                $camping_chance = $camping_chance_texts[2];
            } else if ($survival_chance <= .65) {
                $camping_chance = $camping_chance_texts[3];
            } else if ($survival_chance <= .80) {
                $camping_chance = $camping_chance_texts[4];
            } else if ($survival_chance <= .9) {
                $camping_chance = $camping_chance_texts[5];
            } else if ($survival_chance < 1) {
                $camping_chance = $camping_chance_texts[6];
            } else if ($survival_chance == 1) {
                $camping_chance = $camping_chance_texts[7];
            } else {
                $camping_chance = "";
            }

            $camping_improvable = ($survival_chance < $this->citizen_handler->getCampingChance($this->getActiveCitizen())) ? $this->translator->trans("Nicht weit entfernt von deinem aktuellen Versteck erblickst du ein noch besseres Versteck... Hmmm...vielleicht solltest du umziehen?", [], 'game') : "";

            $camping_blueprint = "";
            $blueprintFound = false;
            if ($zone->getBuryCount() <= 0) {
                if ($zone->getBlueprint() === Zone::BlueprintAvailable) {
                    $camping_blueprint = T::__("Du erhälst einen Bauplan, wenn Du in diesem Gebäude campst.", 'game');
                } else if ($zone->getBlueprint() === Zone::BlueprintFound) {
                    $camping_blueprint = T::__("Hier wurde bereits ein Bauplan gefunden.", 'game');
                    $blueprintFound = true;
                }
            } else $camping_blueprint = T::__("Du erhälst einen Bauplan wenn Du in diesem Gebäude campst, aber du musst zunächst die Zone aufräumen.", 'game');

            // Uncomment next line to show camping values in game interface.
            #$camping_debug = "DEBUG CampingChances\nSurvivalChance for Comparison: " . $survival_chance . "\nCitizenCampingChance: " . $this->getActiveCitizen()->getCampingChance() . "\nCitizenHandlerCalculatedChance: " . $this->citizen_handler->getCampingChance($this->getActiveCitizen()) . "\nCalculationValues:\n" . str_replace( ',', "\n", str_replace( ['{', '}'], '', json_encode($this->citizen_handler->getCampingValues($this->getActiveCitizen()), 8) ) );
        }

        $zone_tags = [];
        if(!$is_on_zero) {
            $zone_tags = $this->entity_manager->getRepository(ZoneTag::class)->findAll();
        }

        $has_hidden_items =
            ($this->getActiveCitizen()->getBanished() || $town->getChaos()) &&
            !$this->getActiveCitizen()->getZone()->getFloor()->getItems()->filter(function(Item $i) { return $i->getHidden(); })->isEmpty();

        $floorItems = $zone->getFloor()->getItems()->toArray();
        usort($floorItems, function ($a, $b) {
            return strcmp($this->translator->trans($a->getPrototype()->getLabel(), [], 'items'), $this->translator->trans($b->getPrototype()->getLabel(), [], 'items'));
        });

        $args = $this->addDefaultTwigArgs(null, [
            'hidden_items' => $has_hidden_items,
            'scout' => $this->getActiveCitizen()->getProfession()->getName() === 'hunter',
            'allow_enter_town' => $can_enter,
            'doors_open' => $town->getDoor(),
            'town' => $town,
            'show_ventilation'  => $is_on_zero && $th->getBuilding($town, 'small_ventilation_#00',  true) !== null,
            'allow_ventilation' => $this->getActiveCitizen()->getProfession()->getHeroic(),
            'show_sneaky' => $is_on_zero && $this->getActiveCitizen()->hasRole('ghoul'),
            'enter_costs_ap' => $require_ap,
            'allow_floor_access' => !$is_on_zero,
            'can_escape' => !$this->citizen_handler->isWounded( $this->getActiveCitizen() ) && !$citizen_tired,
            'can_attack' => !$citizen_tired && !$this->citizen_handler->hasStatusEffect($this->getActiveCitizen(), 'wound2'),
            'can_attack_nr' => $citizen_tired ? 'tired' : ( $this->citizen_handler->isWounded($this->getActiveCitizen()) ? 'wounded' : false ),
            'can_escape_nr' => $citizen_tired ? 'tired' : ( $this->citizen_handler->isWounded($this->getActiveCitizen()) ? 'wounded' : false ),
            'zone_blocked' => $blocked,
            'zone_escape' => $escape,
            'zone_escape_desperate' => $escape_desperate,
            'digging' => $this->getActiveCitizen()->isDigging(),
            'dig_ruin' => $this->getActiveCitizen()->getZone()->getActivityMarkerFor( ZoneActivityMarkerType::RuinDig, $this->getActiveCitizen() ) === null,
            'actions' => $this->getItemActions(),
            'floorItems' => $floorItems,
            'other_citizens' => $zone->getCitizens(),
            'log' => ($zone->getX() === 0 && $zone->getY() === 0) ? '' : $this->renderLog( -1, null, $zone, null, 20, true )->getContent(),
            'day' => $this->getActiveCitizen()->getTown()->getDay(),
            'camping_zone' => $camping_zone ?? '',
            'camping_zombies' => $camping_zombies ?? '',
            'camping_chance' => $camping_chance ?? '',
            'camping_improvable' => $camping_improvable ?? '',
            'camping_blueprint' => $camping_blueprint ?? '',
            'blueprintFound' => $blueprintFound ?? '',
            'camping_debug' => $camping_debug ?? '',
            'zone_tags' => $zone_tags ?? [],
            'sect' => $sect,
        ], !$inline);

        return $inline
            ? $this->renderBlocks( 'ajax/game/beyond/desert.html.twig', ['content','js'], [ 'ajax/game/game.html.twig' => 'gma' ], $args )
            : $this->render( 'ajax/game/beyond/desert.html.twig', $args );
    }

    /**
     * @Route("api/beyond/desert/log", name="beyond_desert_log_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function log_desert_api(JSONRequestParser $parser): Response {
        $zone = $this->getActiveCitizen()->getZone();
        if (!$zone || ($zone->getX() === 0 && $zone->getY() === 0))
            return $this->renderLog((int)$parser->get('day', -1), null, null, null, 0, true);
        return $this->renderLog((int)$parser->get('day', -1), null, $zone, null, null, true);
    }

    protected function activeCitizenIsNotEscorted() {
        $c = $this->getActiveCitizen();
        return !$c->getEscortSettings() || !$c->getEscortSettings()->getLeader();
    }

    protected function activeCitizenIsNotCamping() {
        $c = $this->getActiveCitizen();
        return
            !$c->getStatus()->contains($this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'tg_hide'])) &&
            !$c->getStatus()->contains($this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'tg_tomb']));
    }

    protected function activeCitizenCanAct() {
        return $this->activeCitizenIsNotEscorted() && $this->activeCitizenIsNotCamping();
    }

    /**
     * @Route("api/beyond/trash", name="beyond_trash_controller", condition="")
     * @param InventoryHandler $handler
     * @param GameProfilerService $gps
     * @return Response
     */
    public function trash_api(InventoryHandler $handler, GameProfilerService $gps): Response {

        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $citizen = $this->getActiveCitizen();
        $town = $citizen->getTown();
        if (!$town->getChaos() && (!$citizen->getBanished() || $citizen->getZone()->getX() !== 0 || $citizen->getZone()->getY() !== 0))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if ($citizen->getAp() <= 0 || $this->citizen_handler->isTired( $citizen ))
            return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        $trashlock = $citizen->getSpecificActionCounter(ActionCounter::ActionTypeTrash);

        $limit = $citizen->getProfession()->getName() === 'collec' ? 4 : 3;
        if ($trashlock->getCount() >= $limit) return AjaxResponse::error(self::ErrorTrashLimitHit);

        $inv_target = $citizen->getInventory();
        $inv_source = null;

        $good = $this->random_generator->chance(0.125);

        $item_group = $this->entity_manager->getRepository(ItemGroup::class)->findOneBy(['name' => $good ? 'trash_good' : 'trash_bad']);
        $proto = $this->random_generator->pickItemPrototypeFromGroup( $item_group, $this->getTownConf() );
        if (!$proto)
            return AjaxResponse::errorMessage( $this->translator->trans('Obwohl du minutenlang den Stadtmüll durchwühlst, findest du <strong>nichts Nützliches</strong>...', [], 'game') );

        $item = $this->item_factory->createItem($proto);
        $gps->recordItemFound( $proto, $citizen, null, 'trash' );

        if (($error = $handler->transferItem(
            $citizen,
            $item,$inv_source, $inv_target, InventoryHandler::ModalityAllowMultiHeavy
        )) === InventoryHandler::ErrorNone) {

            $trashlock->increment();
            $this->citizen_handler->setAP($citizen, true, -1);
            $this->addFlash( 'notice', $this->translator->trans( 'Beim Durchwühlen des Mülls, der am Stadtrand herumliegt, findest du schließlich folgendes: {item}.<hr />Du hast <strong>1 Aktionspunkt(e)</strong> verbraucht.', [
                '{item}' => "<span class='tool'> <img alt='' src='{$this->asset->getUrl( "build/images/item/item_{$item->getPrototype()->getIcon()}.gif" )}'> {$this->translator->trans($item->getPrototype()->getLabel(), [], 'items')}</span>"
            ], 'game' ));

            try {
                $this->entity_manager->persist($item);
                $this->entity_manager->persist($citizen);
                $this->entity_manager->persist($trashlock);
                $this->entity_manager->flush();
            } catch (Exception $e) {
                return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
            }
            return AjaxResponse::success();
        } else return AjaxResponse::error($error);
    }

    /**
     * @Route("api/beyond/bury_rucksack", name="beyond_bury_rucksack_controller", condition="")
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @return Response
     */
    public function bury_rucksack_api(JSONRequestParser $parser, InventoryHandler $handler): Response {

        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $citizen = $this->getActiveCitizen();
        $down_inv = $citizen->getZone()->getFloor();
        $up_inv   = $citizen->getInventory();
        $town = $citizen->getTown();
        if ((!$town->getChaos() && !$citizen->getBanished()) || $citizen->getZone()->isTownZone())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if ($citizen->getAp() < 2 || $this->citizen_handler->isTired( $citizen ))
            return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        $hide_items = $hide_success = true;
        foreach ($citizen->getZone()->getCitizens() as $fellow_citizen)
            if(!$fellow_citizen->getBanished() && !$town->getChaos()) // If there's a non-banished citizen on the zone during a non-chaos town, the items are not hidden
                $hide_success = false;

        if (!$this->zone_handler->check_cp( $this->getActiveCitizen()->getZone() ) && $this->get_escape_timeout( $this->getActiveCitizen() ) < 0 && $this->uncoverHunter($this->getActiveCitizen()))
            $this->addFlash('collapse', $this->translator->trans('Deine <strong>Tarnung ist aufgeflogen</strong>!', [], 'game'));
        $r = $this->generic_item_api( $up_inv, $down_inv, true, $parser, $handler, $citizen, $hide_items, $processed);
        if ($r->isSuccessResponse() && $hide_items && $processed > 0) {
            if (!$hide_success)
                $this->addFlash('notice', $this->translator->trans('Ein oder mehrere nicht-verbannte Bürger in der Umgebung haben dich dabei beobachtet.<hr/>Du hast 2 Aktionspunkte verbraucht.', [], 'game'));
            else $this->addFlash('notice', $this->translator->trans('Du brauchst eine Weile, bis du alle Gegenstände versteckt hast, die du bei dir trägst... Ha Ha... Du hast 2 Aktionspunkte verbraucht.', [], 'game'));

        } elseif ($r->isSuccessResponse() && !$hide_items && $processed > 0)
            $this->addFlash('notice', $this->translator->trans('Du kannst keine Gegenstände verstecken, solange jemand zuschaut ...', [], 'game'));
        elseif ($r->isSuccessResponse() && $hide_items && $processed === 0) {
            $this->addFlash('notice', $this->translator->trans('Du hast keine Gegenstände, die du verstecken könntest.', [], 'game'));
            return AjaxResponse::success();
        }

        return $r;
    }

    /**
     * @Route("api/beyond/desert/chat", name="beyond_desert_chat_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function chat_desert_api(JSONRequestParser $parser, HTMLService $html): Response {
        if ($this->user_handler->isRestricted($this->getActiveCitizen()->getUser(), AccountRestriction::RestrictionTownCommunication))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if ($this->getActiveCitizen()->getZone()->isTownZone())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $message = $parser->get('msg', null);
        if (!$message || mb_strlen($message) < 2 || !$html->htmlPrepare($this->getActiveCitizen()->getUser(), 0, ['core_rp'], $message, $this->getActiveCitizen()->getTown(), $insight) || $insight->text_length < 2 || $insight->text_length > 256 )
            return AjaxResponse::error(self::ErrorChatMessageInvalid);

        $message = $html->htmlDistort( $message,
            ($this->citizen_handler->hasStatusEffect($this->getActiveCitizen(), 'drunk') ? HTMLService::ModulationDrunk : HTMLService::ModulationNone) |
            ($this->citizen_handler->hasStatusEffect($this->getActiveCitizen(), 'terror') ? HTMLService::ModulationTerror : HTMLService::ModulationNone) |
            ($this->citizen_handler->hasStatusEffect($this->getActiveCitizen(), 'wound1') ? HTMLService::ModulationHead : HTMLService::ModulationNone)
            , $this->getActiveCitizen()->getTown()->getRealLanguage($this->generatedLangsCodes) ?? $this->getUserLanguage(), $d );

        try {
            $this->entity_manager->persist( $this->log->beyondChat( $this->getActiveCitizen(), $message ) );
            $this->entity_manager->flush(  );
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/exit/{special}", name="beyond_desert_exit_controller")
     * @param string $special
     * @param TownHandler $th
     * @return Response
     */
    public function desert_exit_api(TownHandler $th, string $special = 'normal'): Response {
        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $citizen = $this->getActiveCitizen();
        $zone = $citizen->getZone();
        $town = $citizen->getTown();

        $watchtower = $th->getBuilding($town, 'item_tagger_#00',  true);
        if ($watchtower && $special === 'normal') switch ($watchtower->getLevel()) {
            case 4: $port_distance = 1;  break;
            case 5: $port_distance = 2;  break;
            default:$port_distance = 0; break;
        } else $port_distance = 0;
        $distance = round(sqrt( pow($zone->getX(),2) + pow($zone->getY(),2) ));

        if ($distance > $port_distance)
            return AjaxResponse::error( self::ErrorNoReturnFromHere );

        switch ($special) {
            case 'normal':
            case "normal-escort":
                if (!$citizen->getTown()->getDoor())
                    return AjaxResponse::error( self::ErrorDoorClosed );
                break;
            case 'sneak':
                if (!$citizen->hasRole('ghoul'))
                    return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
                if (!$citizen->getTown()->getDoor())
                    return AjaxResponse::error( self::ErrorDoorClosed );
                break;
            case 'hero':
                if (!$citizen->getProfession()->getHeroic())
                    return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
                break;
            default: return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        }

        $movers = [];
        $movers[] = $citizen;
        if ($special === 'normal-escort' || ($special === 'normal' && $distance > 0) || $special === 'hero')
            foreach ($citizen->getValidLeadingEscorts() as $escort) {
                if ($special !== 'hero' || $escort->getCitizen()->getProfession()->getHeroic())
                    $movers[] = $escort->getCitizen();
            }
        else
            foreach ($citizen->getLeadingEscorts() as $escort)
                $escort->getCitizen()->getEscortSettings()->setLeader(null);

        $others_are_here = $zone->getCitizens()->count() > count($movers);

        $labyrinth = ($zone->getX() === 0 && $zone->getY() === 0 && in_array($special, ['normal', 'normal-escort', 'sneak']) && $th->getBuilding($town, 'small_labyrinth_#00',  true));

        foreach ($movers as $mover){
            // Check if the labyrinth is built and the user enters from 0/0
            if ($labyrinth && ($mover->getAp() <= 0 || $this->citizen_handler->isTired($mover)))
                return AjaxResponse::error( $mover->getId() === $citizen->getId() ? ErrorHelper::ErrorNoAP : BeyondController::ErrorEscortFailure );
        }

        $cp_ok = $this->zone_handler->check_cp( $zone );

        foreach ($movers as $mover) {
            // If labyrinth is active, deduct 1AP
            if ($labyrinth) $this->citizen_handler->setAP($mover, true, -1);

            // Disable the escort
            if ($mover->getEscortSettings()) {
                $remove[] = $mover->getEscortSettings();
                $mover->setEscortSettings(null);
            }

            // Disable the dig timer
            if ($dig_timer = $mover->getCurrentDigTimer()) {
                $dig_timer->setPassive(true);
                $this->entity_manager->persist( $dig_timer );
            }

            if(($special === 'normal' || $special === 'normal-escort') && ($zone->getX() !== 0 || $zone->getY() !== 0)
                && $others_are_here)
                $this->entity_manager->persist($this->log->citizenTeleport($mover, $zone));

            // Remove zone from citizen
            $mover->setZone( null );
            $zone->removeCitizen( $mover );
            foreach ($this->entity_manager->getRepository(HomeIntrusion::class)->findBy(['victim' => $mover]) as $homeIntrusion)
                $this->entity_manager->remove($homeIntrusion);

            // Produce log entries
            if ($special !== 'sneak') {
                $this->entity_manager->persist( $this->log->doorPass( $mover, true ) );
                $this->entity_manager->persist($mover);
            }
        }

        $this->zone_handler->handleCitizenCountUpdate( $zone, $cp_ok, $movers[array_key_last($movers)] );

        try {
            $this->entity_manager->persist($citizen);
            $this->entity_manager->persist($zone);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/enter", name="beyond_desert_enter_ruin_controller")
     * @return Response
     */
    public function ruin_enter_api() {
        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        $citizen = $this->getActiveCitizen();

        // Make sure the ruin is explorable
        if (!$citizen->getZone()?->getPrototype()?->getExplorable())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        // Make sure the citizen is not wounded or terrorized
        if ($this->citizen_handler->isWounded( $citizen ) || $this->citizen_handler->hasStatusEffect( $citizen, ['terror'] ))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        // Make sure the citizen has not already explored the ruin today
        if ($citizen->currentExplorerStats()) {
            $this->addFlash('error', $this->translator->trans('Du kannst heute nicht hierher zurückkehren... Dein Körper würde das nicht durchstehen.', [], 'game'));
            return AjaxResponse::success();
        }

        // Make sure no one else is exploring the ruin right now
        if ($citizen->getZone()->activeExplorerStats()) {
            $this->addFlash('error', $this->translator->trans('Jemand anders untersucht gerade diesen Ort.', [], 'game'));
            return AjaxResponse::success();
        }

        // Block exploring if currently escorting citizens
        if (!empty($citizen->getValidLeadingEscorts()))
            return AjaxResponse::error( self::ErrorEscortFailureRuin );

        // Block exploring if the zone is controlled by zombies
        $scout_movement = $this->inventory_handler->countSpecificItems(
                $this->getActiveCitizen()->getInventory(), $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'vest_on_#00'])
            ) > 0;
        if (!$this->zone_handler->check_cp( $citizen->getZone() ) && !$scout_movement)
            return AjaxResponse::error( self::ErrorZoneBlocked );

        // Make sure the citizen has enough AP
        if ($this->citizen_handler->isTired( $citizen ) || $citizen->getAp() < 1)
            return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        // Entering a ruin disables the dig timer
        if ($dig_timer = $citizen->getCurrentDigTimer()) {
            $dig_timer->setPassive(true);
            $this->entity_manager->persist( $dig_timer );
        }

        // Disable escort mode for citizens entering a ruin
        if ($citizen->getEscortSettings()) {
            $this->entity_manager->remove( $citizen->getEscortSettings() );
            $citizen->setEscortSettings(null);
        }

        // Begin the exploration!
        $this->picto_handler->give_picto($citizen, 'r_ruine_#00', 1);
        $this->citizen_handler->setAP( $citizen, true, -1 );

        $citizen->addExplorerStat((new RuinExplorerStats())->setActive(true)->setTimeout( (new DateTime())->add(DateInterval::createFromDateString(
            $this->getTownConf()->get($citizen->getProfession()->getName() === 'collec' ?
                TownConf::CONF_TIMES_EXPLORE_COLLEC :
                TownConf::CONF_TIMES_EXPLORE_NORMAL, '+5min')
        ) )));
        $this->entity_manager->persist($citizen);
        try {
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }
        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/move", name="beyond_desert_move_controller")
     * @param JSONRequestParser $parser
     * @param GameProfilerService $gps
     * @return Response
     */
    public function desert_move_api(JSONRequestParser $parser, GameProfilerService $gps): Response {
        $citizen = $this->getActiveCitizen();
        $zone = $citizen->getZone();

        if ( !$this->activeCitizenIsNotCamping() )
            return AjaxResponse::error( self::ErrorNoMovementWhileHiding );

        if (!$this->activeCitizenIsNotEscorted()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $px = $parser->get('x', PHP_INT_MAX);
        $py = $parser->get('y', PHP_INT_MAX);

        $cp_ok = $this->zone_handler->check_cp( $zone );
        $scout_movement = $this->inventory_handler->countSpecificItems(
            $this->getActiveCitizen()->getInventory(), $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'vest_on_#00'])
        ) > 0;

        if (abs($px - $zone->getX()) + abs($py - $zone->getY()) !== 1) return AjaxResponse::error( self::ErrorNotReachableFromHere );
        if (!$cp_ok && $this->get_escape_timeout( $citizen, true ) < 0 && !$scout_movement) return AjaxResponse::error( self::ErrorZoneBlocked );

        /** @var Zone $new_zone */
        $new_zone = $this->entity_manager->getRepository(Zone::class)->findOneByPosition( $citizen->getTown(), $px, $py );
        if (!$new_zone) return AjaxResponse::error( self::ErrorNotReachableFromHere );

        $cp_ok_new_zone = $this->zone_handler->check_cp($new_zone, $cp_before_new_zone);
        if($cp_before_new_zone <= 0) $cp_ok_new_zone = null;

        $movement_interrupted = false;

        if ($this->citizen_handler->hasStatusEffect($citizen, 'wound4') && $this->random_generator->chance(0.20)) {
            $this->addFlash('notice', $this->translator->trans('Wenn du anfängst zu gehen, greift ein sehr starker Schmerz in dein Bein. Du fällst stöhnend zu Boden. Man verliert eine Aktion...', [], 'game'));
            $this->entity_manager->persist($this->log->outsideMoveoutsideMoveFailInjury( $citizen ));
            $movement_interrupted = true;
        }

        if ($zone->getZombies() > 0 && $this->citizen_handler->hasStatusEffect($citizen, 'terror') && $this->random_generator->chance(0.05)) {
            $this->addFlash('notice', $this->translator->trans('Als du dich umschaust, <strong>überfällt dich eine plötzliche, unkontrollierbare Panik</strong>! Es ist unmöglich, auch nur einen Schritt weiterzugehen.<hr/>Deine Bewegung wurde <strong>unterbrochen</strong> und du hast <strong>1 AP</strong> verloren.', [], 'game'));
            $this->entity_manager->persist($this->log->outsideMoveoutsideMoveFailTerror( $citizen ));
            $movement_interrupted = true;
        }

        if ($movement_interrupted) {
            $this->citizen_handler->setAP( $citizen, true, -1 );
            $this->entity_manager->persist($citizen);
            $this->entity_manager->flush();
            return AjaxResponse::success();
        }

        $movers = [$citizen];
        foreach ($citizen->getValidLeadingEscorts() as $escort)
            $movers[] = $escort->getCitizen();

        $scouts = [];

        $others_are_here = $zone->getCitizens()->count() > count($movers);
        $away_from_town = (abs($zone->getX()) + abs($zone->getY())) < (abs($new_zone->getX()) + abs($new_zone->getY()));

        foreach ($movers as $mover) {
            // Check if citizen moves as a scout
            $scouts[$mover->getId()] = $this->inventory_handler->countSpecificItems(
                    $mover->getInventory(), $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'vest_on_#00'])
                ) > 0;

            // Check if citizen can move (zone not blocked and enough AP)
            if (!$cp_ok && $this->get_escape_timeout( $mover, true ) < 0 && !$scouts[$mover->getId()]) return AjaxResponse::error( self::ErrorZoneBlocked );
            if ($mover->getAp() < 1 || $this->citizen_handler->isTired( $mover ))
                return AjaxResponse::error( $citizen->getId() === $mover->getId() ? ErrorHelper::ErrorNoAP : BeyondController::ErrorEscortFailure );

            // Check if escortee wants to go home
            if (count($movers) > 1 && $mover->getEscortSettings() && $mover->getEscortSettings()->getForceDirectReturn() && $away_from_town)
                return AjaxResponse::errorMessage( $this->translator->trans('{citizen} möchte nicht in diese Richtung gehen! <strong>Er bittet dich darum, ihn in die Stadt zu bringen...</strong>', ['{citizen}' => "<span>{$mover->getName()}</span>"], 'game') );

            $movement_interrupted = false;
            if ($mover !== $citizen && $this->citizen_handler->hasStatusEffect($mover, 'wound4') && $this->random_generator->chance(0.20)) {
                $this->entity_manager->persist($this->log->outsideMoveoutsideMoveFailInjury( $mover ));
                $movement_interrupted = true;
            }

            if ($mover !== $citizen && $zone->getZombies() > 0 && $this->citizen_handler->hasStatusEffect($mover, 'terror') && $this->random_generator->chance(0.05)) {
                $this->entity_manager->persist($this->log->outsideMoveoutsideMoveFailTerror( $mover ));
                $movement_interrupted = true;
            }

            if ($movement_interrupted) {
                $this->citizen_handler->setAP( $mover, true, -1 );
                $this->entity_manager->persist($mover);
                $this->entity_manager->flush();
                return AjaxResponse::error( BeyondController::ErrorEscortFailure );
            }
        }

        foreach ($movers as $mover) {
            // Moving disables the dig timer
            if ($dig_timer = $mover->getCurrentDigTimer()) {
                $dig_timer->setPassive(true);
                $this->entity_manager->persist( $dig_timer );
            }

            // Moving invalidates any escape timer the user may have had
            foreach ($this->entity_manager->getRepository(EscapeTimer::class)->findAllByCitizen($mover) as $et)
                $this->entity_manager->remove( $et );

            // Single movers get their escort mode disabled
            if (count($movers) === 1 && $mover->getEscortSettings()) {
                $es = $mover->getEscortSettings();
                $mover->setEscortSettings(null);
                $this->entity_manager->remove( $es );
            }

            // Get them clothes dirty!
            $clothes = $this->inventory_handler->fetchSpecificItems($mover->getInventory(),[new ItemRequest('basic_suit_#00')]);
            if (!empty($clothes)) $clothes[0]->setPrototype( $this->entity_manager->getRepository( ItemPrototype::class )->findOneBy(['name' => 'basic_suit_dirt_#00']) );

            // Actually move to the new zone
            $zone->removeCitizen( $mover );
            $new_zone->addCitizen( $mover );

            // Scout check
            if ($mover->getProfession()->getName() === 'hunter' && !$new_zone->getActivityMarkerFor(ZoneActivityMarkerType::ScoutVisit, $mover)) {
                $new_zone->addActivityMarker( (new ZoneActivityMarker())
                    ->setCitizen( $mover )
                    ->setTimestamp( new DateTime() )
                    ->setType(ZoneActivityMarkerType::ScoutVisit)
                );
                if ($scouts[$mover->getId()] && !$this->zone_handler->check_cp( $new_zone )) {

                    $new_zed_count = $new_zone->getZombies();
                    $new_zone_lv = $new_zone->getScoutLevel();

                    if ($this->getTownConf()->isNightMode()) $new_zone_lv += 1;

                    $factor = pow( max(0, $new_zed_count - 3*$new_zone_lv), 1.0 + (max(0, $new_zed_count - 3*$new_zone_lv))/60.0 ) / 100.0;
                    if ($this->getTownConf()->isNightMode())
                        $factor *= 0.667;

                    if ($this->random_generator->chance($factor) && $this->uncoverHunter($mover)){
                        if ($mover->getId() === $citizen->getId())
                            $this->addFlash('notice', $this->translator->trans('Du wurdest von einem <strong>Zombie in der Zone entdeckt</strong>! Er hat sich in deine Richtung gedreht!<hr/>Deine Tarnung ist aufgeflogen!', [], 'game'));
                        else
                            $this->addFlash('notice', $this->translator->trans('Die Tarnung von {name} ist aufgeflogen!', ['{name}' => $mover->getName()], 'game'));
                        $gps->recordlostHood($mover, $new_zone, "outsideMove");
                    }
                }
            }

            // Set AP and increase walking distance counter
            $this->citizen_handler->setAP($mover, true, -1);
            $mover->setWalkingDistance( $mover->getWalkingDistance() + 1 );
            if ($mover->getWalkingDistance() > 10) {
                $this->citizen_handler->increaseThirstLevel($mover);
                $mover->setWalkingDistance( 0 );
            }

            $this->citizen_handler->inflictStatus($mover, "tg_chk_movewb");

            $smokeBombs = $zone->getChatSilenceTimers();
            $hideMove = false;
            foreach ($smokeBombs as $smokeBomb) {
                /** @var ChatSilenceTimer $smokeBomb */
                if($smokeBomb->getCitizen() == $mover){
                    if($smokeBomb->getTime() > new \DateTime("-1min")) {
                        $hideMove = true;
                    } else {
                        $zone->removeChatSilenceTimer($smokeBomb);
                        $this->entity_manager->remove($smokeBomb);
                    }
                }
                
            }

            // This text is a newly added one, but it breaks the "Sneak out of town"
            if ($others_are_here && !($zone->getX() === 0 && $zone->getY() === 0) && !$hideMove) $this->entity_manager->persist( $this->log->outsideMove( $mover, $zone, $new_zone, true  ) );
            if (!($new_zone->getX() === 0 && $new_zone->getY() === 0)) $this->entity_manager->persist( $this->log->outsideMove( $mover, $new_zone, $zone, false ) );

            // Banished citizen's stash check
            if(!$mover->getBanished() && $this->zone_handler->hasHiddenItem($new_zone) && $this->random_generator->chance(0.05)){
                $itemsForLog = [];
                $zone->setItemsHiddenAt(null);
                foreach ($new_zone->getFloor()->getItems() as $item) if ($item->getHidden()) {
                    /** @var Item $item */
                    if(isset($itemsForLog[$item->getPrototype()->getId()])) {
                        $itemsForLog[$item->getPrototype()->getId()]['count'] += 1;
                    } else {
                        $itemsForLog[$item->getPrototype()->getId()] = [
                            'item' => $item,
                            'count' => 1
                        ];
                    }
                }
                $this->entity_manager->persist($this->log->outsideFoundHiddenItems($mover, $itemsForLog));
                foreach ($new_zone->getFloor()->getItems() as $item) {
                    if($item->getHidden()){
                        $item->setHidden(false);
                        $this->entity_manager->persist($item);
                    }
                }
            }

            if ($new_zone->isTownZone() && $mover->getEscortSettings() && $mover->getEscortSettings()->getForceDirectReturn()) {
                // The citizen want to go back home. When we're on the town zone, make it go inside automatically
                $mover->setZone(null);
                $zone->removeCitizen($mover);
                $this->addFlash('notice', $this->translator->trans("{citizen} bedankt sich herzlich bei dir, dass du ihn nach Hause gebracht hast!", ['{citizen}' => $mover->getName()], 'game'));
                $this->entity_manager->persist($this->log->beyondEscortCitizenBackHome($mover, $mover->getEscortSettings()->getLeader()));
                $this->entity_manager->remove($mover->getEscortSettings());
                $mover->setEscortSettings(null);
            }

            $this->entity_manager->persist($mover);
        }

        $upgraded_map = $this->town_handler->getBuilding($citizen->getTown(),'item_electro_#00', true);

        $new_zone
            ->setDiscoveryStatus( Zone::DiscoveryStateCurrent )
            ->setZombieStatus( max($upgraded_map ? Zone::ZombieStateExact : Zone::ZombieStateEstimate, $new_zone->getZombieStatus() ) );

        try {
            $this->zone_handler->handleCitizenCountUpdate($zone, $cp_ok, $movers[array_key_last($movers)]);
            $this->zone_handler->handleCitizenCountUpdate($new_zone, $cp_ok_new_zone);
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );
        }

        // If the new zone is controlled by citizens, invalidate all escape timers
        if ($this->zone_handler->check_cp( $new_zone )) foreach ($this->entity_manager->getRepository(EscapeTimer::class)->findAllByZone($new_zone) as $et)
            $this->entity_manager->remove( $et );

        try {
            $this->entity_manager->persist($citizen);
            $this->entity_manager->persist($zone);
            $this->entity_manager->persist($new_zone);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/action", name="beyond_desert_action_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function action_desert_api(JSONRequestParser $parser): Response {
        if (!$this->activeCitizenCanAct())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $uncover_fun = function(ItemAction &$a) {

            if (!$a->getKeepsCover() && !$this->zone_handler->check_cp( $this->getActiveCitizen()->getZone() ) && $this->get_escape_timeout( $this->getActiveCitizen() ) < 0 && $this->uncoverHunter($this->getActiveCitizen()))
                $this->addFlash( 'collapse', $this->translator->trans('Deine <strong>Tarnung ist aufgeflogen</strong>!',[], 'game') );
        };

        return $this->generic_action_api($parser, $uncover_fun);
    }

    /**
     * @Route("api/beyond/desert/escort/action", name="beyond_desert_escort_action_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function escort_action_desert_api(JSONRequestParser $parser): Response {
        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if (!$parser->has_all(['citizen','meta','action'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var Citizen $citizen */
        $citizen = $this->entity_manager->getRepository(Citizen::class)->find( (int)$parser->get('citizen', -1) );
        if (!$citizen) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (!$citizen->getEscortSettings() ||
            !$citizen->getEscortSettings()->getLeader() ||
            $citizen->getEscortSettings()->getLeader()->getId() !== $this->getActiveCitizen()->getId()
        ) return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable);

        /** @var EscortActionGroup $esc_act */
        $esc_act = $this->entity_manager->getRepository(EscortActionGroup::class)->find( (int)$parser->get('meta', -1) );
        if (!$esc_act) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (!$citizen->getEscortSettings()->getAllowInventoryAccess()) {

            if ($parser->get('item') !== 'p' || $parser->get('action') !== 'p')
                return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable);

            $groups = $this->action_handler->getAvailableItemEscortActions($citizen, $esc_act);
            if (count($groups) !== 1) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            list($item,$action) = $groups[0]->getPrimaryAction();
            if (!$item || !$action) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            $parser->inject('item', $item->getId());
            $parser->inject('action', $action->getId());

        } else {
            $action  = $this->entity_manager->getRepository(ItemAction::class)->find( (int)$parser->get('action', -1) );
        }

        if (!$action) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        if (!$esc_act->getActions()->contains($action))
            return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable);

        $uncover_fun = function(ItemAction &$a) use ($citizen) {
            if (!$a->getKeepsCover() && !$this->zone_handler->check_cp( $citizen->getZone() ) && $this->get_escape_timeout( $citizen ) < 0 && $this->uncoverHunter($citizen))
                $this->addFlash( 'notice', $this->translator->trans('Die Tarnung von {name} ist aufgeflogen!', ['name' => $citizen], 'game') );
        };

        return $this->generic_action_api($parser, $uncover_fun, $citizen);
    }

    /**
     * @Route("api/beyond/desert/heroic", name="beyond_desert_heroic_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function heroic_desert_api(JSONRequestParser $parser): Response {
        $zone = $this->getActiveCitizen()->getZone();

        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $uncover_fun = function(ItemAction &$a) use ($zone) {
            if (!$a->getKeepsCover() && !$this->zone_handler->check_cp( $zone ) && $this->get_escape_timeout( $this->getActiveCitizen() ) < 0 && $this->uncoverHunter($this->getActiveCitizen()))
                $this->addFlash( 'collapse', $this->translator->trans('Deine <strong>Tarnung ist aufgeflogen</strong>!',[], 'game') );
        };

        return $this->generic_heroic_action_api( $parser, $uncover_fun);
    }

    /**
     * @Route("api/beyond/desert/special_action", name="beyond_desert_special_action_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function special_action_api(JSONRequestParser $parser): Response {
        return $this->generic_special_action_api( $parser );
    }

    /**
     * @Route("api/beyond/desert/camping", name="beyond_desert_camping_controller")
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @return Response
     */
    public function camping_desert_api(JSONRequestParser $parser, InventoryHandler $handler): Response {
        if (!$this->activeCitizenIsNotEscorted()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        return $this->generic_camping_action_api( $parser);
  }

    /**
     * @Route("api/beyond/desert/recipe", name="beyond_desert_recipe_controller")
     * @param JSONRequestParser $parser
     * @param ActionHandler $handler
     * @return Response
     */
    public function recipe_desert_api(JSONRequestParser $parser, ActionHandler $handler): Response {
        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $uncover_fun = function(Recipe &$r) {
            if (!$r->getStealthy() && !$this->zone_handler->check_cp( $this->getActiveCitizen()->getZone() ) && $this->get_escape_timeout( $this->getActiveCitizen() ) < 0 && $this->uncoverHunter($this->getActiveCitizen()))
                $this->addFlash( 'collapse', $this->translator->trans('Deine <strong>Tarnung ist aufgeflogen</strong>!',[], 'game') );
        };

        return $this->generic_recipe_api( $parser, $handler, $uncover_fun);
    }

    /**
     * @Route("api/beyond/desert/item", name="beyond_desert_item_controller")
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @return Response
     */
    public function item_desert_api(JSONRequestParser $parser, InventoryHandler $handler): Response {
        $down_inv = $this->getActiveCitizen()->getZone()->getFloor();
        $escort = $parser->get_int('escort', null);

        if ($this->getActiveCitizen()->getZone()->isTownZone())
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($escort !== null) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($escort);
            if ($citizen && ($es = $citizen->getEscortSettings()) && $es->getLeader() && $es->getLeader()->getId() === $this->getActiveCitizen()->getId() && $es->getAllowInventoryAccess()) {
                $up_inv   = $citizen->getInventory();
            }
            else return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        } else $up_inv   = $this->getActiveCitizen()->getInventory();

        if (!$this->zone_handler->check_cp( $this->getActiveCitizen()->getZone() ) && $this->get_escape_timeout( $this->getActiveCitizen() ) < 0 && $this->uncoverHunter($this->getActiveCitizen()))
            $this->addFlash( 'collapse', $this->translator->trans('Deine <strong>Tarnung ist aufgeflogen</strong>!',[], 'game') );
        return $this->generic_item_api( $up_inv, $down_inv, $escort === null, $parser, $handler, $this->getActiveCitizen());
    }

    /**
     * @Route("api/beyond/desert/escape", name="beyond_desert_escape_controller")
     * @return Response
     */
    public function escape_desert_api(): Response {
        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $citizen = $this->getActiveCitizen();
        if ($this->zone_handler->check_cp( $citizen->getZone() ) || $this->get_escape_timeout( $citizen, true ) > 0)
            return AjaxResponse::error( self::ErrorZoneUnderControl );

        if ($this->inventory_handler->countSpecificItems(
            $this->getActiveCitizen()->getInventory(), $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'vest_on_#00'])
        ) > 0)
            return AjaxResponse::error( self::ErrorZoneUnderControl );
        
        if($this->citizen_handler->hasStatusEffect($citizen, "terror"))
            return AjaxResponse::error(self::ErrorTerrorized);

        if ($this->citizen_handler->isWounded( $citizen ))
            return AjaxResponse::error( self::ErrorAlreadyWounded );

        $wound = $this->citizen_handler->inflictWound( $citizen );
        if ($wound !== null) switch ($wound->getName()) {
            case 'wound1': $wound = $this->translator->trans('Kopf', [], 'game'); break;
            case 'wound2': $wound = $this->translator->trans('Hand', [], 'game'); break;
            case 'wound3': $wound = $this->translator->trans('Arme', [], 'game'); break;
            case 'wound4': $wound = $this->translator->trans('Bein', [], 'game'); break;
            case 'wound5': $wound = $this->translator->trans('Auge', [], 'game'); break;
            case 'wound6': $wound = $this->translator->trans('Fuß', [], 'game'); break;
            default: $wound = null;
        }

        if ($wound !== null) {
            $this->addFlash('notice', $this->translator->trans('Bei deinem Fluchtversuch ist es einem Zombie gelungen dir eine Verletzung zuzufügen: {injury}! Du solltest hier besser schnell verschwinden!', ['injury' => "<strong>$wound</strong>"], 'game'));
            $this->entity_manager->persist( $this->log->escapeInjury($citizen));
        }
        try {
            $escape = (new EscapeTimer())
            ->setZone( $citizen->getZone() )
            ->setCitizen( $citizen )
            ->setDesperate( true )
            ->setTime( new DateTime('+5min') );
            $this->entity_manager->persist( $citizen );
            $this->entity_manager->persist( $escape );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/attack", name="beyond_desert_attack_controller")
     * @param RandomGenerator $generator
     * @return Response
     */
    public function attack_desert_api(RandomGenerator $generator): Response {
        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $citizen = $this->getActiveCitizen();
        $zone = $citizen->getZone();

        if ($this->citizen_handler->hasStatusEffect( $citizen, 'terror' ))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailableTerror );

        if ($this->zone_handler->check_cp( $zone ) || $this->get_escape_timeout( $citizen, true ) > 0)
            return AjaxResponse::error( self::ErrorZoneUnderControl );

        if ($this->inventory_handler->countSpecificItems($this->getActiveCitizen()->getInventory(), $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'vest_on_#00'])) > 0)
            return AjaxResponse::error( self::ErrorZoneUnderControl );

        if ($this->citizen_handler->hasStatusEffect($citizen, 'wound2')) {
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailableWounded);
        }

        if ($citizen->getAp() <= 0 || $this->citizen_handler->isTired( $citizen ))
            return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        $old_cp_ok = $this->zone_handler->check_cp($zone);

        $this->citizen_handler->setAP( $citizen, true, -1 );
        $ratio = 0.08;
        $messages = [];
        if ($this->citizen_handler->hasStatusEffect($citizen, "drunk"))
            $ratio /= 2;
        if ($generator->chance( $ratio )) {
            $zone->setZombies( $zone->getZombies() - 1 );
            $this->entity_manager->persist( $this->log->zombieKill($citizen, null, 1, 'barehand_attack'));
            // Add the picto Bare hands
            $this->picto_handler->give_picto($citizen, 'r_wrestl_#00');
            // Add the picto zed kill
            $this->picto_handler->give_picto($citizen, 'r_killz_#00');

            $messages[] = $this->translator->trans('Nach einem zähen Kampf gelingt es dir endlich <strong>einen Zombie</strong> gegen einen Felsen zu werfen... Sein Kopf ist <strong>explodiert</strong> und die ganze Soße klebt jetzt an deinen Füßen! Du torkelst ein paar Meter vom Ort des Geschehens weg und keuchst leise vor dich hin: Einer weniger.. Ha... ha...', [], 'game');
        } else {
            $this->entity_manager->persist( $this->log->zombieKillHandsFail($citizen));
            $messages[] = $this->translator->trans('Du stürzt dich auf eine dieser Kreaturen und <strong>umklammerst sie mit beiden Armen</strong>, um sie zu Fall zu bringen. Der Kontakt mit seiner <strong>verrotteten Haut</strong> bringt dich fast zum Kotzen... Du kämpfst und versuchst ihn irgendwie umzustoßen, doch ohne Erfolg. <strong>Das Biest hat dich mehrere Male um ein Haar gebissen!</strong> Erschöpft und demoralisiert lässt du von ihm ab, um dich zurückzuziehen...', [], 'game');
            if ($this->citizen_handler->hasStatusEffect($citizen, "drunk"))
                $messages[] = $this->translator->trans('Dein <strong>Trunkenheitszustand</strong> hilft dir wirklich nicht weiter. Das ist nicht gerade einfach, wenn sich alles dreht und du nicht mehr klar siehst.', [], 'game');
        }

        if (!empty($messages)) {
            $this->addFlash('notice', implode('<hr />', $messages));
        }

        $this->zone_handler->handleCitizenCountUpdate($zone, $old_cp_ok);

        try {
                $this->entity_manager->persist( $citizen );
                $this->entity_manager->persist( $zone );
                $this->entity_manager->flush();
            } catch (Exception $e) {
                return AjaxResponse::error( ErrorHelper::ErrorInternalError );
            }

            return AjaxResponse::success();
        }

    /**
     * @Route("api/beyond/desert/dig/{ext}", name="beyond_desert_dig_controller")
     * @param null|int|string $ext
     * @return Response
     */
    public function desert_dig_api($ext = null): Response {
        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $citizen = $this->getActiveCitizen();
        $zone = $citizen->getZone();

        if (!$this->zone_handler->check_cp( $zone ))
            return AjaxResponse::error( self::ErrorZoneBlocked );
        if ($zone->isTownZone())
            return AjaxResponse::error( self::ErrorNotDiggable );

        if ($ext === null)
            $target_citizens = [$citizen];
        elseif ($ext === 'all') {
            $target_citizens = [];
            foreach ($citizen->getValidLeadingEscorts() as $escort)
                $target_citizens[] = $escort->getCitizen();
        } elseif (is_numeric($ext)) {
            /** @var Citizen|null $t */
            $t = $this->entity_manager->getRepository(Citizen::class)->find( (int)$ext );
            if (!$t || !$t->getEscortSettings() || !$t->getEscortSettings()->getLeader() || $t->getEscortSettings()->getLeader()->getId() !== $citizen->getId())
                return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
            $target_citizens = [$t];
        } else $target_citizens = [];

        $allow_redig = $this->conf->getTownConfiguration($citizen->getTown())->get(TownConf::CONF_MODIFIER_ALLOW_REDIGS, false);

        foreach ($target_citizens as $target_citizen)
            try {
                $timer = $target_citizen->getCurrentDigTimer();
                if (!$timer) $timer = (new DigTimer())->setZone( $zone )->setCitizen( $target_citizen );
                else if (!$allow_redig || $timer->getTimestamp() > new DateTime()) {
                    if (count($target_citizens) === 1)
                        return AjaxResponse::error( self::ErrorNotDiggable );
                    else continue;
                }

                $timer->setPassive( false )->setTimestamp( new DateTime('-1sec') );
                $this->entity_manager->persist( $target_citizen );
                $this->entity_manager->persist( $timer );
            } catch (Exception $e) {
                return AjaxResponse::error( ErrorHelper::ErrorInternalError );
            }

        try {
            $this->entity_manager->persist( $zone );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/scavenge", name="beyond_desert_scavenge_controller")
     * @return Response
     */
    public function desert_scavenge_api(GameProfilerService $gps): Response {
        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $citizen = $this->getActiveCitizen();
        $zone = $citizen->getZone();

        if (!$zone->getPrototype() || $zone->getPrototype()->getExplorable() || $zone->getBuryCount() > 0)
            return AjaxResponse::error( self::ErrorNotDiggable );

        //$scout = $this->inventory_handler->countSpecificItems(
        //    $this->getActiveCitizen()->getInventory(), $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'vest_on_#00'])
        //) > 0;

        //if (!$this->zone_handler->check_cp( $zone ) && !$scout)
        //    return AjaxResponse::error( self::ErrorZoneBlocked );
        if ($zone->getX() === 0 && $zone->getY() === 0)
            return AjaxResponse::error( self::ErrorNotDiggable );

        if ($zone->getActivityMarkerFor( ZoneActivityMarkerType::RuinDig, $citizen ))
            return AjaxResponse::error( self::ErrorNotDiggable );

        if (!$this->zone_handler->check_cp( $this->getActiveCitizen()->getZone() ) && $this->get_escape_timeout( $this->getActiveCitizen() ) < 0 && $this->uncoverHunter($this->getActiveCitizen()))
            $this->addFlash( 'collapse', $this->translator->trans('Deine <strong>Tarnung ist aufgeflogen</strong>!',[], 'game') );

        if ($zone->getRuinDigs() > 0) {
            $factor = $this->zone_handler->getDigChanceFactor( $this->getActiveCitizen(), $zone );

            if ($zone->getPrototype()->getEmptyDropChance() >= 1) $total_dig_chance = 0;
            elseif ($zone->getPrototype()->getEmptyDropChance() <= 0) $total_dig_chance = 1;
            else $total_dig_chance = min(max(0.1, $factor * (1.0 - $zone->getPrototype()->getEmptyDropChance())), 0.95);

            $item_found = $this->random_generator->chance($total_dig_chance);

            $zone->addActivityMarker( (new ZoneActivityMarker())
                                          ->setCitizen( $citizen )
                                          ->setType( ZoneActivityMarkerType::RuinDig )
                                          ->setTimestamp( new DateTime() )
            );

            if ($item_found) {
                $zone->setRuinDigs( $zone->getRuinDigs() - 1 );

                $event_conf = null; $event_confs = [];
                foreach ($this->conf->getCurrentEvents($zone->getTown()) as $ev)
                    foreach ($ev->get(EventConf::EVENT_DIG_RUINS, []) as $e)
                        if ($e['name'] === $zone->getPrototype()->getIcon())
                            $event_confs[] = $e;

                if (!empty($event_confs)) $event_conf = $this->random_generator->pick( $event_confs );

                $named_groups = $this->getTownConf()->get( TownConf::CONF_OVERRIDE_NAMED_DROPS, [] );
                $group = $event_conf
                    ? ( $this->random_generator->chance($event_conf['chance'])
                        ? $this->entity_manager->getRepository(ItemGroup::class)->findOneBy(['name' => $event_conf['group']])
                        : $zone->getPrototype()->getDropByNames( $named_groups ) )
                    : $zone->getPrototype()->getDropByNames( $named_groups );

                $prototype = $group ? $this->random_generator->pickItemPrototypeFromGroup( $group, $this->getTownConf() ) : null;
                $gps->recordDigResult($prototype, $citizen, $zone->getPrototype(), 'ruin_scavenge', $event_conf && $group->getName() == $event_conf['group']);
                if ($prototype) {
                    $item = $this->item_factory->createItem( $prototype );
                    $gps->recordItemFound( $prototype, $citizen, $zone->getPrototype() );
                    $noPlaceLeftMsg = "";
                    if ($item) {
                        $inventoryDest = $this->inventory_handler->placeItem( $citizen, $item, [ $citizen->getInventory(), $zone->getFloor() ] );
                        if($inventoryDest === $zone->getFloor()){
                            $this->entity_manager->persist($this->log->beyondItemLog($citizen, $item->getPrototype(), true));
                            $noPlaceLeftMsg = "<hr />" . $this->translator->trans('Der Gegenstand, den du soeben gefunden hast, passt nicht in deinen Rucksack, darum bleibt er erstmal am Boden...', [], 'game');
                        }
                        $this->entity_manager->persist( $item );
                        $this->entity_manager->persist( $citizen->getInventory() );
                        $this->entity_manager->persist( $zone->getFloor() );
                    }

                    // If we get a Chest XL, we earn a picto
                    if ($prototype->getName() == 'chest_xl_#00') {
                        $pictoPrototype = $this->entity_manager->getRepository(PictoPrototype::class)->findOneBy(['name' => "r_chstxl_#00"]);
                        $this->picto_handler->give_picto($citizen, $pictoPrototype);
                    }

                    $distance = round(sqrt(pow($zone->getX(),2) + pow($zone->getY(),2)));
                    $pictoName = "";
                    if($distance >= 6 && $distance <= 17) {
                        $pictoName = "r_explor_#00";
                    } else if($distance >= 18) {
                        $pictoName = "r_explo2_#00";
                    }
                    if($pictoName != ""){
                        $picto = $this->entity_manager->getRepository(PictoPrototype::class)->findOneBy(['name' => $pictoName]);
                        $this->picto_handler->give_picto($citizen, $picto);
                    }
                    $this->addFlash( 'notice', $this->translator->trans( 'Als du folgendes Gebäude: {building} erkundest hast, lief es eiskalt den Rücken runter... Aber es war nicht umsonst! Du hast folgenden Gegenstand gefunden: {item}.', [
                        '{item}' => "<span class='tool'><img alt='' src='{$this->asset->getUrl( 'build/images/item/item_' . $prototype->getIcon() . '.gif' )}'> {$this->translator->trans($prototype->getLabel(), [], 'items')}</span>",
                        "{building}" => "<strong>" . $this->translator->trans($zone->getPrototype()->getLabel(), [], "game") . "</strong>"
                    ], 'game' ) . "$noPlaceLeftMsg");
                } else {
                    // Should not happen... This is an error case.
                    return AjaxResponse::error(ErrorHelper::ErrorInternalError);
                }
            } else {
                // Nothing found.
                $this->addFlash( 'notice', $this->translator->trans( 'Trotz all deiner Anstrengungen hast du hier leider nichts gefunden ...', [], 'game' ));
                $gps->recordDigResult(null, $citizen, $zone->getPrototype(), 'ruin_scavenge');
            }
        } else {
            $this->addFlash( 'notice', $this->translator->trans( 'Beim Durchsuchen der Ruine merkst du, dass es nichts mehr zu finden gibt. Leider...', [], 'game' ));
        }

        try {
            $this->entity_manager->persist($zone);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/uncover", name="beyond_desert_uncover_controller")
     * @return Response
     */
    public function desert_uncover_api(): Response {
        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $citizen = $this->getActiveCitizen();
        $zone = $citizen->getZone();

        if (!$zone->getPrototype() || $zone->getBuryCount() <= 0 || ($zone->getX() === 0 && $zone->getY() === 0))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if ($citizen->getAp() < 1 || $this->citizen_handler->isTired($citizen))
            return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        $this->citizen_handler->setAP($citizen, true, -1);
        $zone->setBuryCount( $zone->getBuryCount() - 1 );
        $this->entity_manager->persist( $this->log->outsideUncover( $citizen ) );

        $str = [];

        if($zone->getBuryCount() > 0)
            $str[] = $this->translator->trans('Du hast einen Teil des Sektors freigelegt, aber es gibt immer noch eine beträchtliche Menge an Trümmern, die den Weg versperren...',[], 'game');
        else {
            $str[] = $this->translator->trans('Herzlichen Glückwunsch, die Zone ist vollständig freigelegt worden! Du kannst nun mit der Suche nach Gegenständen im: {ruin} beginnen!',["{ruin}" => "<span>" . $this->translator->trans($zone->getPrototype()->getLabel(), [], 'game') . "</span>"], 'game');
            $this->entity_manager->persist( $this->log->outsideUncoverComplete( $citizen ) );
        }

        $str[] = $this->translator->trans("Du hast {count} Aktionspunkt(e) benutzt.", ['{count}' => "<strong>1</strong>", '{raw_count}' => 1], 'game');

        if (!$this->zone_handler->check_cp( $this->getActiveCitizen()->getZone() ) && $this->get_escape_timeout( $this->getActiveCitizen() ) < 0 && $this->uncoverHunter($this->getActiveCitizen()))
            $str[] = $this->translator->trans('Deine <strong>Tarnung ist aufgeflogen</strong>!',[], 'game');

        if(!empty($str))
            $this->addFlash( 'notice', implode("<hr />", $str) );

        $picto = $this->entity_manager->getRepository(PictoPrototype::class)->findOneBy(['name' => 'r_digger_#00']);
        $this->picto_handler->give_picto($citizen, $picto);

        try {
            $this->entity_manager->persist($zone);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/attack_citizen/{cid<\d+>}", name="beyond_desert_attack_citizen_controller")
     * @param int $cid
     * @return Response
     */
    public function desert_attack_api(int $cid): Response {
        $citizen = $this->getActiveCitizen();
        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        /** @var Citizen|null $target_citizen */
        $target_citizen = $this->entity_manager->getRepository(Citizen::class)->find( $cid );

        if (!$target_citizen || $target_citizen->getZone()->getId() !== $citizen->getZone()->getId())
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($target_citizen->activeExplorerStats() || ($citizen->getZone()->getX() == 0 && $citizen->getZone()->getY() == 0) )
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        return $this->generic_attack_api( $citizen, $target_citizen );
    }

    /**
     * @Route("api/beyond/desert/devour_citizen/{cid<\d+>}", name="beyond_desert_devour_citizen_controller")
     * @param int $cid
     * @return Response
     */
    public function desert_devour_api(int $cid): Response {
        $citizen = $this->getActiveCitizen();
        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        /** @var Citizen|null $target_citizen */
        $target_citizen = $this->entity_manager->getRepository(Citizen::class)->find( $cid );

        if (!$target_citizen || $target_citizen->getZone()->getId() !== $citizen->getZone()->getId() || $target_citizen->getZone()->isTownZone())
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($target_citizen->activeExplorerStats())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        return $this->generic_devour_api( $citizen, $target_citizen );
    }

    /**
     * @Route("api/beyond/desert/escort/self", name="beyond_desert_escort_self_controller")
     * @param JSONRequestParser $parser
     * @param ConfMaster $conf
     * @return Response
     */
    public function desert_escort_self_api(JSONRequestParser $parser, ConfMaster $conf): Response {
        if (!$conf->getTownConfiguration($this->getActiveCitizen()->getTown())->get( TownConf::CONF_FEATURE_ESCORT, true ))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if (!$this->activeCitizenIsNotCamping()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if (!$parser->has('on')) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $on = (bool)$parser->get('on');

        $cf_ruc = (bool)$parser->get('cf_ruc', false);
        $cf_ret = (bool)$parser->get('cf_ret', true);

        $citizen = $this->getActiveCitizen();

        if (!$on) {
            if ($citizen->getEscortSettings()) $this->entity_manager->remove($citizen->getEscortSettings());
            $citizen->setEscortSettings(null);
            $this->entity_manager->persist($this->log->beyondEscortDisable($citizen));
        } elseif ($on && !$citizen->getEscortSettings()) {
            $citizen->setEscortSettings((new CitizenEscortSettings())->setCitizen($citizen));
            $this->entity_manager->persist($this->log->beyondEscortEnable($citizen));
        }

        if ($on)
            $citizen->getEscortSettings()->setAllowInventoryAccess($cf_ruc)->setForceDirectReturn($cf_ret && !$citizen->getZone()->isTownZone());

        try {
            $this->entity_manager->persist( $citizen );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/escort/{cid<\d+>}", name="beyond_desert_escort_controller")
     * @param int $cid
     * @param JSONRequestParser $parser
     * @param ConfMaster $conf
     * @return Response
     */
    public function desert_escort_api(int $cid, JSONRequestParser $parser, ConfMaster $conf): Response {
        if (!$conf->getTownConfiguration($this->getActiveCitizen()->getTown())->get( TownConf::CONF_FEATURE_ESCORT, true ))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if (!$parser->has('on')) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $on = (bool)$parser->get('on');

        $citizen = $this->getActiveCitizen();

        /** @var Citizen|null $target_citizen */
        $target_citizen = $this->entity_manager->getRepository(Citizen::class)->find( $cid );

        if (!$target_citizen || $target_citizen->getZone() === null || $target_citizen->getZone()->getId() !== $citizen->getZone()->getId())
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if (!$citizen->getProfession()->getHeroic() && !$citizen->hasRole('guide'))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if ($citizen->getBanished())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if ($on && !$citizen->getUser()->getExpert())
            return AjaxResponse::errorMessage( $this->translator->trans( 'Du kannst diese Aktion im Lernmodus nicht ausführen. <strong>Zuerst musst du noch etwas Spielerfahrung sammeln</strong>.<hr/>Klicke auf den Link bei Lernmodus, um diesen Modus zu deaktivieren.', [], 'game' ) );

        $max_escort_size = $conf->getTownConfiguration($citizen->getTown())->get(TownConf::CONF_FEATURE_ESCORT_SIZE, 4);

        if ($on && $citizen->getLeadingEscorts()->count() >= $max_escort_size)
            return AjaxResponse::error( self::ErrorEscortLimitHit );

        if (!$target_citizen->getEscortSettings() ||
            ($on && $target_citizen->getEscortSettings()->getLeader() !== null) || (!$on && ($target_citizen->getEscortSettings()->getLeader() === null || $target_citizen->getEscortSettings()->getLeader()->getId() !== $citizen->getId())))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if ($citizen->getEscortSettings()) {
            $this->entity_manager->remove($citizen->getEscortSettings());
            $citizen->setEscortSettings(null);
        }

        if($on){
            $this->entity_manager->persist($this->log->beyondEscortTakeCitizen($citizen, $target_citizen));
        } else {
            $this->entity_manager->persist($this->log->beyondEscortReleaseCitizen($citizen, $target_citizen));
        }

        $target_citizen->getEscortSettings()->setLeader( $on ? $citizen : null );

        try {
            $this->entity_manager->persist( $citizen );
            $this->entity_manager->persist( $target_citizen );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/escort/all", name="beyond_desert_escort_drop_controller")
     * @param ConfMaster $conf
     * @return Response
     */
    public function desert_escort_api_drop_all(ConfMaster $conf): Response {
        if (!$conf->getTownConfiguration($this->getActiveCitizen()->getTown())->get( TownConf::CONF_FEATURE_ESCORT, true ))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if (!$this->activeCitizenCanAct()) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        $citizen = $this->getActiveCitizen();

        foreach ($citizen->getLeadingEscorts() as $escort) {
            $this->entity_manager->persist($this->log->beyondEscortReleaseCitizen($citizen, $escort->getCitizen()));
            $escort->setLeader(null);
            $this->entity_manager->persist($escort);
        }

        try {
            $this->entity_manager->persist( $citizen );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/rain", name="beyond_desert_shaman_rain")
     * @param JSONRequestParser $json
     * @return Response
     */
    public function desert_shaman_rain(JSONRequestParser $json): Response {
        if (!$this->activeCitizenCanAct())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        $citizen = $this->getActiveCitizen();

        $mode = $json->get('mode', null, ['role','job']);
        if (!$mode) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $zone = $citizen->getZone();

        // Forbidden if not outside
        if($zone == null)
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if ($mode === 'role') {

            if (!$citizen->hasRole('shaman'))
                return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

            if ($citizen->getPM() < 3)
                return AjaxResponse::error( ErrorHelper::ErrorNoMP );


        } elseif ($mode === 'job') {

            if ($citizen->getProfession()->getName() !== "shaman")
                return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

            if( $citizen->getAp() < 1 )
                return AjaxResponse::error( ErrorHelper::ErrorNoAP );
            else if ( $this->inventory_handler->countSpecificItems($citizen->getInventory(), 'soul_blue_#00') <= 0 ) {
                $this->addFlash('error', $this->translator->trans('Ohne die benötigten Elemente kannst du diesen Zauber nicht aussprechen.', [], 'game'));
                return AjaxResponse::success();
            }

        } else return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if (!$zone->getActivityMarkersFor( ZoneActivityMarkerType::ShamanRain )->isEmpty()) {
            $this->addFlash('error', $this->translator->trans('Diese Aktion auszuführen ist sinnlos. Heute wurde schon versucht, diese Zone zu reinigen.', [], 'game'));
            return AjaxResponse::success();
        }

        $str = [];
        $str[] = $this->translator->trans('Du vollführst einen Schamanentanz und betest zum Himmel, dass er Regen bringen und diese unselige Zone reinigen möge.', [], 'game');

        $success = $this->random_generator->chance(0.75); //same than Hordes

        if (!$success) {
            $str[] = $this->translator->trans('Doch nichts passiert... Soviel steht fest, du stehst nun wie ein lausiger Amateur da. Außer Blasen an den Füßen hat das alles nichts eingebracht...', [], 'game');
            $this->entity_manager->persist($this->log->failureShaman($citizen));
        } else {
            if (!$zone->isTownZone()) {
                $nbKills = min(mt_rand(3, 6), $zone->getZombies());
                $this->entity_manager->persist($this->log->zombieKillShaman($citizen, $nbKills));
                $zone->setZombies($citizen->getZone()->getZombies() - $nbKills);
                $this->entity_manager->persist($zone);
                $str[] = $this->translator->trans('Und die Energie, die in diesen Tanz gesteckt wurde, zahlt sich schließlich aus, die ersten Tropfen fallen auf die Zombies und du genießt diesen delikaten Moment, in dem ihr Fleisch wie Schnee in der Sonne schmilzt und du geduldig wartest, bis sich ihre Körper verflüssigen.', [], 'game');
            } else {
                $str[] = $this->translator->trans('Ob durch deine Kräfte oder unverschämtes Wetterglück, dicke Regentropfen fallen auf die Stadt herab und füllen ein wenig den Brunnen auf.', [], 'game');
                $town = $citizen->getTown();
                $town->setWell($town->getWell() + 5);
                $this->entity_manager->persist($town);
                $this->entity_manager->persist($this->log->wellAddShaman($citizen, 5));
            }
        }

        if ($mode === 'role')
            $citizen->setPM($citizen->getPM() - 3);

        elseif ($mode === 'job') {

            $citizen->setAp($citizen->getAp() - 1);
            $soul = $this->inventory_handler->fetchSpecificItems($citizen->getInventory(), [new ItemRequest("soul_blue_#00")]);
            if (!empty($soul)) $this->inventory_handler->forceRemoveItem(array_pop($soul));

        } else return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        //try {
            $this->entity_manager->persist( $citizen );
            $this->entity_manager->persist( $zone->addActivityMarker( (new ZoneActivityMarker())
                ->setType( ZoneActivityMarkerType::ShamanRain )
                ->setCitizen( $citizen )
                ->setTimestamp( new DateTime() )
            ) );
            $this->entity_manager->flush();
        //} catch (\Throwable $t) {
        //    return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        //}

        $this->addFlash('notice', implode("<hr />", $str));

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/zone_marker", name="beyond_desert_change_zone_marker")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function beyond_change_zone_marker(JSONRequestParser $parser): Response {
        $tagRef = $parser->get('tag', null);
        if ($tagRef < 0 || !is_numeric($tagRef) )
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $zone = $this->getActiveCitizen()->getZone();

        if(!$zone){
            return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable);
        }

        $tag = $this->entity_manager->getRepository(ZoneTag::class)->findOneBy(['ref' => $tagRef]);

        if(!$tag){
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        $zone->setTag($tag);

        try {
            $this->entity_manager->persist($zone);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }
}
