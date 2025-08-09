<?php

namespace App\Controller;

use App\Annotations\GateKeeperProfile;
use App\Annotations\Semaphore;
use App\Entity\ItemPrototype;
use App\Entity\RuinZone;
use App\Entity\Zone;
use App\Entity\ZoneActivityMarker;
use App\Entity\ZonePrototype;
use App\Enum\Configuration\TownSetting;
use App\Enum\ScavengingActionType;
use App\Enum\ZoneActivityMarkerType;
use App\Response\AjaxResponse;
use App\Service\ActionHandler;
use App\Service\CitizenHandler;
use App\Service\ConfMaster;
use App\Service\CrowService;
use App\Service\DeathHandler;
use App\Service\DoctrineCacheService;
use App\Service\ErrorHelper;
use App\Service\EventFactory;
use App\Service\EventProxyService;
use App\Service\GameFactory;
use App\Service\GameProfilerService;
use App\Service\HookExecutor;
use App\Service\InventoryHandler;
use App\Service\PictoHandler;
use App\Service\ItemFactory;
use App\Service\JSONRequestParser;
use App\Service\LogTemplateHandler;
use App\Service\RandomGenerator;
use App\Service\TimeKeeperService;
use App\Service\TownHandler;
use App\Service\User\UserUnlockableService;
use App\Service\UserHandler;
use App\Service\ZoneHandler;
use App\Structures\ItemRequest;
use App\Structures\TownConf;
use App\Translation\T;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[GateKeeperProfile(only_alive: true, only_with_profession: true, only_in_ruin: true)]
#[Semaphore('town', scope: 'town')]
class ExplorationController extends InventoryAwareController implements HookedInterfaceController
{

    protected ZoneHandler $zone_handler;
    protected ItemFactory $item_factory;

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
     * @param DoctrineCacheService $doctrineCache
     * @param EventProxyService $events
     * @param HookExecutor $hookExecutor
     * @param UserUnlockableService $u
     */
    public function __construct(
        EntityManagerInterface $em, InventoryHandler $ih, CitizenHandler $ch, ActionHandler $ah, TimeKeeperService $tk,
        DeathHandler $dh, PictoHandler $ph, TranslatorInterface $translator, GameFactory $gf, RandomGenerator $rg,
        ItemFactory $if, ZoneHandler $zh, LogTemplateHandler $lh, ConfMaster $conf, Packages $a, UserHandler $uh,
        CrowService $armbrust, TownHandler $th, DoctrineCacheService $doctrineCache, EventProxyService $events, HookExecutor $hookExecutor, UserUnlockableService $u)
    {
        parent::__construct($em, $ih, $ch, $ah, $dh, $ph, $translator, $lh, $tk, $rg, $conf, $zh, $uh, $armbrust, $th, $a, $doctrineCache, $events, $hookExecutor, $u);
        $this->item_factory = $if;
        $this->zone_handler = $zh;
    }

    public function before(): bool
    {
        if ($s = $this->zone_handler->updateRuinZone( $this->getActiveCitizen()->activeExplorerStats() )) {
            $this->addFlash( 'error', $s );
            try {
                $this->entity_manager->flush();
            } catch (Exception $e) {}
            return false;
        } else return true;
    }
    
    protected function getCurrentRuinZone(): RuinZone {
        $citizen = $this->getActiveCitizen();
        $ex = $citizen->activeExplorerStats();
        return $this->entity_manager->getRepository(RuinZone::class)->findOneByPosition($citizen->getZone(), $ex->getX(), $ex->getY(), $ex->getZ());
    }

    /**
     * @return Response
     */
    #[Route(path: 'jx/beyond/explore', name: 'exploration_dashboard')]
    public function explore(): Response
    {
        $citizen = $this->getActiveCitizen();
        $ex = $citizen->activeExplorerStats();
        $ruinZone = $this->getCurrentRuinZone();

        $imprint_source = [];
        $imprint_goal = [];
        if ($citizen->getProfession()->getName() === 'tech') {
            $imprint_source['tech'] = [];
            $imprint_goal['tech'] = $ruinZone->getPrototype()?->getKeyImprint();
        }

       if ($ruinZone->getPrototype()?->getKeyImprintAlternative()) {
           $imprint_source['noodles'] = $this->entity_manager->getRepository(ItemPrototype::class)->findBy(['name' => ['food_noodles_#00', 'pharma_#00']]);
           $imprint_goal['noodles'] = $ruinZone->getPrototype()?->getKeyImprintAlternative();
       }

        return $this->render( 'ajax/game/beyond/ruin.html.twig', $this->addDefaultTwigArgs(null, [
            'prototype' => $citizen->getZone()->getPrototype(),
            'exploration' => $ex,
            'zone' => $ruinZone,
            'heroics' => $this->getHeroicActions(),
            'escaping' => $ex->getEscaping(),
            'zone_zombies' => $ruinZone->getZombies(),
            'shifted' => $ex->getInRoom(),
            'scavenge' => !$ex->getScavengedRooms()->contains($ruinZone),
            'can_imprint' => !empty($imprint_goal),
            'imprint_source' => $imprint_source,
            'imprint_goal' => $imprint_goal,

            'ruin_map_data' => [
                'name' => $this->generateRuinName($citizen->getZone()),
            ],
        ]) );
    }

    /**
     * @param GameProfilerService $gps
     * @param EventProxyService $proxyService
     * @return Response
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[Route(path: 'api/beyond/explore/scavenge', name: 'beyond_ruin_scavenge_controller')]
    public function scavenge_explore_api(GameProfilerService $gps, EventProxyService $proxyService): Response {
        $citizen = $this->getActiveCitizen();
        $ex = $citizen->activeExplorerStats();
        $ruinZone = $this->getCurrentRuinZone();

        if (!$ex->getInRoom() || $ex->getScavengedRooms()->contains( $ruinZone ))
            return AjaxResponse::error( BeyondController::ErrorNotDiggable );

        $prototype = null;

        // Calculate chances
        $d = $ruinZone->getDigs() + 1;
        $chances = $proxyService->citizenQueryDigChance( $citizen, $ruinZone, ScavengingActionType::DigExploration, $this->getTownConf()->isNightMode() );

        if ($this->random_generator->chance( $chances )) {
            $group = $ruinZone->getZone()->getPrototype()->getDropByNames( $this->getTownConf()->get( TownSetting::OptModifierOverrideNamedDrops ) );

            $redraw = false; $redraw_count = 0; $itemMarkerType = null;
            do {
                $redraw_count++;
                $prototype = $group ? $this->random_generator->pickItemPrototypeFromGroup( $group, $this->getTownConf(), $this->conf->getCurrentEvents( $citizen->getTown() ) ) : null;

                $itemMarkerType = $prototype ? ZoneActivityMarkerType::scavengedItemIncurs( $prototype ) : null;
                $itemLimit = $itemMarkerType?->configuredLimit( $this->getTownConf() ) ?? -1;

                if ($itemLimit >= 0 && $itemMarkerType)
                    $redraw = $ruinZone->getZone()->getActivityMarkersFor( $itemMarkerType )->count() >= ($itemLimit * ($ruinZone->getZ()+1));
                else $redraw = false;

                if ($redraw && $redraw_count >= 10) $prototype = null;

            } while ($redraw && $redraw_count < 10);

            if ($itemMarkerType && $prototype) $this->entity_manager->persist($ruinZone->getZone()->addActivityMarker( (new ZoneActivityMarker())
                ->setCitizen( $citizen )
                ->setTimestamp( new DateTime() )
                ->setType( $itemMarkerType )
            ));
        }

        $ruinZone->setDigs( $ruinZone->getDigs() + 1 );

        $gps->recordDigResult($prototype, $citizen, $ruinZone->getZone()->getPrototype(), 'eruin_scavenge');
        if ($prototype) {
            $item = $this->item_factory->createItem($prototype, false, $prototype->hasProperty("found_poisoned") && $this->random_generator->chance(0.90));
            $gps->recordItemFound( $prototype, $citizen, $ruinZone->getZone()->getPrototype() );
            $noPlaceLeftMsg = "";
            // $inventoryDest = $proxyService->placeItem($citizen, $item, [$citizen->getInventory(), $ruinZone->getRoomFloor()]);
            $inventoryDest = $proxyService->placeItem($citizen, $item, [$citizen->getInventory(), $ruinZone->getFloor()]);
            if ($inventoryDest === $ruinZone->getFloor())
                $noPlaceLeftMsg = "<hr />" . $this->translator->trans('Der Gegenstand, den du soeben gefunden hast, passt nicht in deinen Rucksack, darum bleibt er erstmal am Boden...', [], 'game');

            $this->entity_manager->persist($item);
            $this->entity_manager->persist($citizen->getInventory());
            // $this->entity_manager->persist($ruinZone->getRoomFloor());
            $this->entity_manager->persist($ruinZone->getFloor());

            $this->addFlash( 'notice', $this->translator->trans( 'Du hast {item} gefunden, als du die schmutzigen Ecken dieses elenden Ortes durchsucht hast!', [
                    '{item}' => "<span class='tool'><img alt='' src='{$this->asset->getUrl( 'build/images/item/item_' . $prototype->getIcon() . '.gif' )}'> {$this->translator->trans($prototype->getLabel(), [], 'items')}</span>"
                ], 'game' ) . "$noPlaceLeftMsg");
        } else {
            $messages = [ $this->translator->trans('Trotz all deiner Anstrengungen hast du hier leider nichts gefunden ...', [], 'game') ];
            if ($this->citizen_handler->hasStatusEffect($citizen, "drunk"))
                $messages[] = $this->translator->trans('Dein <strong>Trunkenheitszustand</strong> hilft dir wirklich nicht weiter. Das ist nicht gerade einfach, wenn sich alles dreht und du nicht mehr klar siehst.', [], 'game');
            $this->addFlash('notice', implode('<hr/>', $messages) );
        }

        $ex->getScavengedRooms()->add( $ruinZone );
        $this->entity_manager->persist($ex);

        try {
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @param JSONRequestParser $parser
     * @return Response
     */
    #[Route(path: 'api/beyond/explore/heroic', name: 'beyond_ruin_heroic_controller')]
    public function heroic_desert_api(JSONRequestParser $parser): Response {
        return $this->generic_heroic_action_api( $parser );
    }

    /**
     * @param JSONRequestParser $parser
     * @return Response
     */
    #[Route(path: 'api/beyond/explore/action', name: 'beyond_ruin_action_controller')]
    public function action_desert_api(JSONRequestParser $parser): Response {
        return $this->generic_action_api( $parser );
    }

    /**
     * @param JSONRequestParser $parser
     * @param ActionHandler $handler
     * @return Response
     */
    #[Route(path: 'api/beyond/explore/recipe', name: 'beyond_ruin_recipe_controller')]
    public function recipe_desert_api(JSONRequestParser $parser, ActionHandler $handler): Response {
        return $this->generic_recipe_api( $parser, $handler);
    }

    public function generateRuinName(Zone $zone) {
        $ruinNames = [
            'deserted_hospital' => [T::__("Krankenhaus Beinapp", 'names'), T::__("Aesthetyxiations-Abteilung", 'names'), T::__("Krankenhaus Krankenstedt", 'names'), T::__("Waldorf Klinik", 'names'), T::__("STD Behandlungszentrum Bohlen", 'names'), T::__("Bill S. Preston Memorial Hospital", 'names'), T::__("Klinik von Dr. Quack Salber", 'names'), T::__("Wasserallergie-Behandlungszentrum", 'names'), T::__("Klinikum Altenburg", 'names'), T::__("Stadtkrankenhaus am Dorfplatz", 'names'), T::__("Blanko-Penisverkleinerungsklinik", 'names'), T::__("Chronische Erschöpfungsheilanstalt Dr. Sloth", 'names'), T::__("Bordeaux Grace", 'names'), T::__("George und Ralph Kinderkrankenhaus", 'names')],
            'deserted_hotel' => [T::__("Charlton Eston Hotel", 'names'), T::__("Motel Zauberstübchen", 'names'), T::__("Rabble Lodge", 'names'), T::__("Gasthof 'Zum Spanner'", 'names'), T::__("Zur Erbrochenen Krone", 'names'), T::__("Terminal Hotel", 'names'), T::__("Hotel Von Otto", 'names'), T::__("S+M B+B", 'names'), T::__("Zum Schlafen Reichts Motel", 'names'), T::__("Hotel Peculiar", 'names'), T::__("Liza Defloration Hotel", 'names'), T::__("Santas Absteige", 'names'), T::__("Chez Clem Hotel", 'names'), T::__("Drei Eingänge Hotel + Spa", 'names'), T::__("Hostel Partout", 'names'), T::__("Bumbling Inn", 'names'), T::__("Vajazzl Inn", 'names'), T::__("Hotel Venga", 'names')],
            'deserted_bunker' => [T::__("Verlassener Bunker", 'names'), T::__("Thermonuklear-Bunker", 'names'), T::__("Garrison-Haus", 'names'), T::__("Bastion der Angst", 'names'), T::__("Bunker der Wut", 'names'), T::__("Fallout Shelter", 'names'), T::__("Keine Hoffnung ohne Öffnung!", 'names'), T::__("Schattenfort", 'names'), T::__("Verlassene Trooper-Station", 'names'), T::__("Verwesungsversteck", 'names'), T::__("Knochenkeller", 'names'), T::__("Geheimes Testlabor", 'names'), T::__("Area 52.1 Bunker", 'names'), T::__("Area 33 Bunker", 'names'), T::__("Quarantäne-Zone", 'names')],
        ];
        return $ruinNames[$zone->getPrototype()->getIcon()][$zone->getId() % count($ruinNames[$zone->getPrototype()->getIcon()])];
    }

    protected function ruin_partial_item_action_args(): array {
        return [
            'citizen' => $this->getActiveCitizen(),
            'actions' => $this->getItemActions(),
            'recipes' => $this->getItemCombinations(false),
            'citizen_hidden' => false,
            'active_scout_mode' => false,
            'conf' => $this->getTownConf(),
            'controller_action' => 'beyond_ruin_action_controller',
            'controller_recipe' => 'beyond_ruin_recipe_controller',
            'controller_dash' => 'exploration_dashboard',
            'render_frame' => 'beyond_desert_content',
            'native_margins' => true,
        ];
    }

    /**
     * @return Response
     */
    #[Route(path: 'jx/beyond/partial/explore/actions', name: 'beyond_ruin_dashboard_partial_item_actions')]
    public function ruin_partial_item_actions(): Response
    {
        return $this->render( 'ajax/game/beyond/partials/item-actions.standalone.html.twig', $this->ruin_partial_item_action_args() );
    }
}
