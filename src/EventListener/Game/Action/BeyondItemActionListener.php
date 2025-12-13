<?php


namespace App\EventListener\Game\Action;

use App\Entity\ActionCounter;
use App\Entity\Citizen;
use App\Entity\CitizenStatus;
use App\Entity\EventActivationMarker;
use App\Entity\ItemPrototype;
use App\Entity\PrivateMessage;
use App\Entity\Zone;
use App\Enum\ActionCounterType;
use App\Enum\Configuration\TownSetting;
use App\Enum\Game\TransferItemModality;
use App\Event\Game\Actions\CustomActionProcessorEvent;
use App\EventListener\ContainerTypeTrait;
use App\Service\CitizenHandler;
use App\Service\CrowService;
use App\Service\EventProxyService;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\LogTemplateHandler;
use App\Service\PictoHandler;
use App\Service\RandomGenerator;
use App\Service\TownHandler;
use App\Service\ZoneHandler;
use App\Structures\ItemRequest;
use App\Structures\TownConf;
use App\Translation\T;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

#[AsEventListener(event: CustomActionProcessorEvent::class, method: 'onCustomAction',  priority: -10)]
final class BeyondItemActionListener implements ServiceSubscriberInterface
{
    use ContainerTypeTrait;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public static function getSubscribedServices(): array
    {
        return [
            EntityManagerInterface::class,
            RandomGenerator::class,
            TownHandler::class,
            PictoHandler::class,
            InventoryHandler::class,
            CitizenHandler::class,
            LogTemplateHandler::class,
            EventProxyService::class,
            ZoneHandler::class,
            ItemFactory::class,
            CrowService::class,
        ];
    }



    public function onCustomAction( CustomActionProcessorEvent $event ): void {
        switch ($event->type) {
            // Discover a random ruin
            case 12:
            {
                $list = [];
                foreach ($event->citizen->getTown()->getZones() as $zone)
                    if ($zone->getDiscoveryStatus() === Zone::DiscoveryStateNone && $zone->getPrototype())
                        $list[] = $zone;

                $selected = $this->getService(RandomGenerator::class)->pick($list);
                if ($selected) {
                    $upgraded_map = $this->getService(TownHandler::class)->getBuilding($event->citizen->getTown(),'item_electro_#00', true);
                    $event->cache->setTargetZone($selected);
                    $event->cache->addTag('zone');
                    $selected->setDiscoveryStatus( Zone::DiscoveryStateCurrent );
                    if ($upgraded_map) $selected->setZombieStatus( Zone::ZombieStateExact );
                    else $selected->setZombieStatus( max( $selected->getZombieStatus(), Zone::ZombieStateEstimate ) );

                    $event->cache->altered_map_discovery = true;
                }
                break;

            }

            // Sandballs, bitches!
            case 20: {

                if ($event->target === null) {
                    // Hordes-like - there is no target, there is only ZUUL
                    $list = $event->citizen->getZone()->getCitizens()->filter( function(Citizen $c) use ($event): bool {
                        return $c->getAlive() && $c !== $event->citizen && ($c->getSpecificActionCounter(ActionCounterType::SandballHit, $event->citizen->getId())->getLast() === null || $c->getSpecificActionCounter(ActionCounterType::SandballHit, $event->citizen->getId())->getLast()->getTimestamp() < (time() - 1800));
                    } )->getValues();
                    $sandball_target = $this->getService(RandomGenerator::class)->pick( $list );

                } else $sandball_target = $event->target;

                if (!$this->getService(EntityManagerInterface::class)->getRepository(EventActivationMarker::class)->findOneBy(['town' => $event->citizen->getTown(), 'active' => true, 'event' => 'christmas']))
                    $sandball_target = null;

                if ($sandball_target !== null) {
                    $this->getService(PictoHandler::class)->give_picto($event->citizen, 'r_sandb_#00');

                    $this->getService(InventoryHandler::class)->forceRemoveItem( $event->item );
                    $event->cache->addConsumedItem($event->item);

                    $event->cache->setTargetCitizen($sandball_target);
                    $sandball_target->getSpecificActionCounter(ActionCounterType::SandballHit, $event->citizen->getId())->increment();

                    $hurt = !$this->getService(CitizenHandler::class)->isWounded($sandball_target) && $this->getService(RandomGenerator::class)->chance( $event->townConfig->get(TownSetting::OptModifierSandballNastyness) );
                    if ($hurt) $this->getService(CitizenHandler::class)->inflictWound($sandball_target);

                    $this->getService(EntityManagerInterface::class)->persist( $this->getService(LogTemplateHandler::class)->sandballAttack( $event->citizen, $sandball_target, $hurt ) );
                    $this->getService(EntityManagerInterface::class)->persist($sandball_target);


                } else $event->cache->addTag('fail');

                break;
            }

            // Flare
            case 21 :
                $criteria = new Criteria();
                $criteria->andWhere($criteria->expr()->eq('town', $event->citizen->getTown()));
                $criteria->andWhere($criteria->expr()->neq('discoveryStatus', Zone::DiscoveryStateCurrent));
                $zones = $this->getService(EntityManagerInterface::class)->getRepository(Zone::class)->matching($criteria)->getValues();
                if(count($zones) > 0) {
                    /** @var Zone $zone */
                    $zone = $this->getService(RandomGenerator::class)->pick($zones);
                    $zone->setDiscoveryStatus(Zone::DiscoveryStateCurrent);
                    $zone->setZombieStatus( max( $zone->getZombieStatus(), $this->getService(TownHandler::class)->getBuilding($event->citizen->getTown(), 'item_electro_#00', true) ? Zone::ZombieStateExact : Zone::ZombieStateEstimate ) );
                    $this->getService(EntityManagerInterface::class)->persist($zone);
                    $this->getService(InventoryHandler::class)->forceRemoveItem( $event->item );
                    $event->cache->addConsumedItem($event->item);
                    $event->cache->addTag($zone->getPrototype() ? 'flare_ok_ruin' : 'flare_ok');
                    $event->cache->setTargetZone($zone);
                    $event->cache->altered_map_discovery = true;
                } else {
                    $event->cache->addTag('flare_fail');
                }
                break;

            // Trick or treat, motherfucker
            case 23: {

                if (!$event->target || !$this->getService(EntityManagerInterface::class)->getRepository(EventActivationMarker::class)->findOneBy(['town' => $event->citizen->getTown(), 'active' => true, 'event' => 'halloween']))
                    break;

                $event->cache->setTargetCitizen($event->target);
                $already_scared = $event->target->hasStatus('tg_was_scared');

                $success = !$already_scared && !$event->target->hasStatus('terror') && $this->getService(RandomGenerator::class)->chance( 0.5 );

                $event->citizen->addStatus($this->getService(EntityManagerInterface::class)->getRepository(CitizenStatus::class)->findOneByName('tg_scary_mask'));

                if ($success) {
                    $this->getService(PictoHandler::class)->give_picto($event->citizen, 'r_decofeist_#00');
                    $event->target->addStatus($this->getService(EntityManagerInterface::class)->getRepository(CitizenStatus::class)->findOneByName('terror'));
                    $event->target->addStatus($this->getService(EntityManagerInterface::class)->getRepository(CitizenStatus::class)->findOneByName('tg_msk_scared'));
                    $event->cache->addMessage(T::__('Du schleichst dich von hinten an {target} heran und es gelingt dir, ihm einen tierischen Schrecken einzujagen!', 'items'), translationDomain: 'items' );
                } else $event->cache->addMessage(T::__('Du schleichst dich von hinten an {target} heran. Dabei machst du allerdings so einen Lärm, dass {target} dich bereits frühzeitig bemerkt. Das hat wohl nicht geklappt...', 'items'), translationDomain: 'items');

                if (!$already_scared) $event->target->addStatus($this->getService(EntityManagerInterface::class)->getRepository(CitizenStatus::class)->findOneByName('tg_was_scared'));
                $this->getService(EntityManagerInterface::class)->persist( $this->getService(LogTemplateHandler::class)->scaryMaskAttack( $event->citizen, $event->target, $success ) );
                $this->getService(EntityManagerInterface::class)->persist($event->target);

                break;
            }

            case 31:

                $red_zones  = $this->getService(ZoneHandler::class)->getSoulZones( $event->citizen->getTown(), false, true );
                $blue_zones = $this->getService(ZoneHandler::class)->getSoulZones( $event->citizen->getTown(), true, false );

                if (!empty($red_zones) && !empty($blue_zones) && $this->getService(RandomGenerator::class)->chance( 0.5 )) $zones = $red_zones;
                elseif (!empty($blue_zones)) $zones = $blue_zones;
                elseif (!empty($red_zones)) $zones = $red_zones;
                else $zones = [];

                /** @var Zone $zone */
                $zone = $this->getService(RandomGenerator::class)->pick($zones);

                if ($zone) {
                    $event->cache->addMessage(
                        T::__('Während du dich konzentrierst, spürst du für einen kurzen Moment die Aura eines deiner verstorbenen Mitbürger. Sie scheint von {location} zu kommen...', 'game'),
                        ['location' => "<span class=\"tool\">[ {$zone->getX()} / {$zone->getY()} ]</span>"]
                    );
                    $this->getService(CrowService::class)->postAsPM($event->citizen, '', '', PrivateMessage::TEMPLATE_CROW_SANCTUARY, data: [$zone->getX(), $zone->getY()]);
                } else $event->cache->addMessage(
                    T::__('Du spürst nichts als Ruhe.', 'game'),
                );

                break;

            // Tamer Dog Fetch Action
            case 10501: case 10502:

                // The tamer does not work if the door is closed
                if (!$event->citizen->getTown()->getDoor()) {
                    $event->cache->addTag('fail');
                    $event->cache->addTag('door-closed');
                    break;
                }

                $source = $event->type === 10501 ? $event->citizen->getHome()->getChest() : $event->town->getBank();
                $target = $event->citizen->getInventory();

                $item = $event->type === 10501
                    ? $event->target
                    : ($this->getService(InventoryHandler::class)->fetchSpecificItems($event->town->getBank(), [new ItemRequest($event->target->getName())]))[0] ?? null;

                $em = $this->getService(EntityManagerInterface::class);
                if (!$item) {

                    if ($event->type === 10502) {
                        if ($event->item->getPrototype()->getName() === 'tamed_pet_#00' || $event->item->getPrototype()->getName() === 'tamed_pet_drug_#00' )
                            $event->item->setPrototype( $em->getRepository(ItemPrototype::class)->findOneBy(['name' => 'tamed_pet_off_#00']) );
                    }

                    $event->cache->addTag('fail');
                    $event->cache->addTag('impossible');
                    break;
                }

                if (($s = $this->getService(EventProxyService::class)->transferItem($event->citizen, $item, $source, $target, TransferItemModality::Tamer)) === InventoryHandler::ErrorNone) {
                    if ($event->item->getPrototype()->getName() === 'tamed_pet_#00' || $event->item->getPrototype()->getName() === 'tamed_pet_drug_#00' )
                        $event->item->setPrototype( $em->getRepository(ItemPrototype::class)->findOneBy(['name' => 'tamed_pet_off_#00']) );

                    if ($event->type === 10502)
                        $this->getService(EntityManagerInterface::class)->persist(
                            $this->getService(LogTemplateHandler::class)->bankItemTamerTakeLog( $event->citizen, $item->getPrototype(), $item->getBroken() )
                        );

                } else {
                    $event->cache->addTag('fail');
                    $event->cache->addTag('impossible');
                }

                break;

            // Photo_4 action on ruin
            case 12001:
                // Grant blueprint if available on a ruin.
                $zone_handler = $this->getService(ZoneHandler::class);
                $item_factory = $this->getService(ItemFactory::class);
                $em = $this->getService(EntityManagerInterface::class);

                $citizen = $event->citizen;

                if ($citizen->getZone()->getBlueprint() === Zone::BlueprintAvailable && $citizen->getZone()->getBuryCount() <= 0) {
                    // Spawn BP.
                    $bp_name = ($zone_handler->getZoneKm($citizen->getZone()) < 10)
                        ? 'bplan_u_#00'
                        : 'bplan_r_#00';
                    $bp_item_prototype = $em->getRepository(ItemPrototype::class)->findOneBy(['name' => $bp_name]);
                    $bp_item = $item_factory->createItem( $bp_item_prototype );

                    $this->getService(EventProxyService::class)->placeItem($event->citizen, $bp_item, inventories: [$citizen->getInventory(), $citizen->getZone()->getFloor()]);

                    // Set zone blueprint.
                    $citizen->getZone()->setBlueprint(Zone::BlueprintFound);

                    $event->cache->addTag("bp-found");
                    $event->cache->addSpawnedItem($bp_item);
                }
                break;
        }
    }

}
