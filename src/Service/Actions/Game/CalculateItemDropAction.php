<?php

namespace App\Service\Actions\Game;



use App\Entity\Citizen;
use App\Entity\CitizenProfession;
use App\Entity\Item;
use App\Entity\ItemGroup;
use App\Entity\ItemPrototype;
use App\Entity\PictoPrototype;
use App\Entity\RuinZone;
use App\Entity\Town;
use App\Entity\Zone;
use App\Entity\ZoneActivityMarker;
use App\Enum\Configuration\TownSetting;
use App\Enum\ItemDropType;
use App\Enum\ZoneActivityMarkerType;
use App\Service\ConfMaster;
use App\Service\DoctrineCacheService;
use App\Service\GameProfilerService;
use App\Service\ItemFactory;
use App\Service\PictoHandler;
use App\Service\RandomGenerator;
use App\Structures\EventConf;
use App\Structures\TownConf;
use ArrayHelpers\Arr;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class CalculateItemDropAction
{
    private array $event_cache = [];
    private array $town_cache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RandomGenerator $random_generator,
        private readonly DoctrineCacheService $doctrineCache,
        private readonly ConfMaster $conf,
        private readonly GameProfilerService $gps,
        private readonly PictoHandler $picto_handler,
        private readonly ItemFactory $item_factory,
    ) {

    }

    /**
     * @param EventConf[] $events
     * @param ItemDropType $type
     * @param bool $isEventDig
     * @return ItemPrototype|null
     */
    private function determineItemPrototypeForZoneDig(
        array $events,
        TownConf $conf,
        ItemDropType $type,
        bool &$isEventDig = false,
    ): ?ItemPrototype
    {
        $filtered_events = $type === ItemDropType::TownZoneDig ? [] : array_filter(
            $events,
            fn(EventConf $e) =>
                $e->get(EventConf::EVENT_DIG_DESERT_GROUP, null) &&
                $e->get(EventConf::EVENT_DIG_DESERT_CHANCE, 1.0) > 0
        );

        $item_group_name = match ($type) {
            ItemDropType::ZoneEmptyDig => 'empty_dig',
            ItemDropType::ZoneDig, ItemDropType::TownZoneDig => 'base_dig',
            ItemDropType::ZoneEventDig
                => $this->random_generator->pick( $filtered_events )?->get(EventConf::EVENT_DIG_DESERT_GROUP, null),
            default => null,
        };

        if ($item_group_name === null) return null;

        $group = $this->doctrineCache->getEntityByIdentifier(ItemGroup::class, $item_group_name);
        $item_prototype = $group
            ? $this->random_generator->pickItemPrototypeFromGroup($group, $conf, $events)
            : null;
        $isEventDig = $item_prototype && $type === ItemDropType::ZoneEventDig;

        return $item_prototype;
    }

    /**
     * @param EventConf[] $events
     * @param Zone $zone
     * @param bool $isEventDig
     * @return ItemPrototype|null
     */
    private function determineItemPrototypeForRuinDig(
        array $events,
        TownConf $conf,
        Zone $zone,
        bool &$isEventDig = false,
    ): ?ItemPrototype {

        $event_confs = [];
        foreach ($events as $ev)
            foreach ($ev->get(EventConf::EVENT_DIG_RUINS, []) as $e)
                if ($e['name'] === $zone->getPrototype()->getIcon())
                    $event_confs[] = $e;

        $event_conf = $this->random_generator->pick( $event_confs );

        $named_groups = $conf->get( TownSetting::OptModifierOverrideNamedDrops );
        $group = $event_conf
            ? ( $this->random_generator->chance( $event_conf['chance'] ?? 0 )
                ? $this->doctrineCache->getEntityByIdentifier(ItemGroup::class, $event_conf['group'] ?? '')
                : $zone->getPrototype()->getDropByNames( $named_groups ) )
            : $zone->getPrototype()->getDropByNames( $named_groups );

        $isEventDig = $group?->getName() === (($event_conf ?? [])['group'] ?? '');

        return $group
            ? $this->random_generator->pickItemPrototypeFromGroup( $group, $conf, $events )
            : null;
    }

    /**
     * @param EventConf[] $events
     * @param TownConf $conf
     * @param RuinZone $ruinZone
     * @return ItemPrototype|null
     */
    private function determineItemPrototypeForERuinDig(
        array $events,
        TownConf $conf,
        RuinZone $ruinZone,
    ): ?ItemPrototype {

        $group = $ruinZone->getZone()->getPrototype()->getDropByNames( $conf->get( TownSetting::OptModifierOverrideNamedDrops ) );
        return $group ? $this->random_generator->pickItemPrototypeFromGroup( $group, $conf, $events ) : null;
    }

    /**
     * @param EventConf[] $events
     * @param ItemDropType $type
     * @return ItemPrototype|null
     */
    private function determineItemPrototypeForTrashDig(
        array $events,
        TownConf $conf,
        ItemDropType $type,
    ): ?ItemPrototype
    {
        $item_group_name = match ($type) {
            ItemDropType::TrashEmptyDig => 'trash_bad',
            ItemDropType::TrashDig => 'trash_good',
            default => null,
        };

        if ($item_group_name === null) return null;
        $group = $this->doctrineCache->getEntityByIdentifier(ItemGroup::class, $item_group_name);
        return $group
            ? $this->random_generator->pickItemPrototypeFromGroup($group, $conf, $events)
            : null;
    }

    public function __invoke(
        Citizen $citizen,
        Zone|RuinZone $zone,
        ItemDropType $type,
        bool $dryRun = false,
    ): ?Item
    {
        if ($type === ItemDropType::None) return null;

        $ruinZone = $zone instanceof RuinZone ? $zone : null;
        $zone = $zone instanceof RuinZone ? $zone->getZone() : $zone;

        $events = ( $this->event_cache[ $zone->getTown()->getId() ] ??= $this->conf->getCurrentEvents( $zone->getTown() ) );
        $conf = ( $this->town_cache[ $zone->getTown()->getId() ] ??= $this->conf->getTownConfiguration( $zone->getTown() ) );

        $event = false;

        $max_redraws = 10;
        $redraw_count = 0;

        $translation = [];
        foreach ($events as $e)
            foreach ($e->get(EventConf::EVENT_DIG_REPLACE, []) as $source => $items) {
                Arr::set( $translation, $source, [
                    ...Arr::get( $translation, $source, [] ),
                    ...$items
                ] );
            }

        do {
            $redraw_count++;
            $item_prototype = match ($type) {
                ItemDropType::None => null,

                ItemDropType::ZoneEmptyDig,
                ItemDropType::ZoneDig,
                ItemDropType::ZoneEventDig,
                ItemDropType::TownZoneDig => $this->determineItemPrototypeForZoneDig($events, $conf, $type, $event),

                ItemDropType::RuinDig => $this->determineItemPrototypeForRuinDig($events, $conf, $zone, $event),

                ItemDropType::ERuinDig => $this->determineItemPrototypeForERuinDig($events, $conf, $ruinZone),

                ItemDropType::TrashEmptyDig,
                ItemDropType::TrashDig => $this->determineItemPrototypeForTrashDig($events, $conf, $type),
            };

            if ($item_prototype && !empty($translation[$item_prototype->getName()]))
                $item_prototype = $this->doctrineCache->getEntityByIdentifier(ItemPrototype::class, $this->random_generator->pick($translation[$item_prototype->getName()]));

            $itemMarkerType = $item_prototype ? ZoneActivityMarkerType::scavengedItemIncurs( $item_prototype ) : null;
            $itemLimit = $itemMarkerType?->configuredLimit( $conf ) ?? -1;

            if ($itemLimit >= 0 && $itemMarkerType)
                $redraw = $zone->getTown()->getTownZone()->getActivityMarkersFor( $itemMarkerType )->count() >= $itemLimit;
            else $redraw = false;

            if ($redraw && $redraw_count > $max_redraws) $item_prototype = null;

        } while ($redraw && $redraw_count <= $max_redraws);

        $item = $item_prototype
            ? $this->item_factory->createItem(
                $item_prototype,
                poison: $type === ItemDropType::ERuinDig && $item_prototype->hasProperty("found_poisoned") && $this->random_generator->chance(0.90)
            )
            : null;

        if (!$dryRun) {

            if ($item_prototype && $itemMarkerType) $this->em->persist($zone->getTown()->getTownZone()->addActivityMarker( (new ZoneActivityMarker())
                ->setCitizen( $citizen )
                ->setTimestamp( new DateTime() )
                ->setType( $itemMarkerType )
            ));

            $this->gps->recordDigResult($item_prototype, $citizen, match ($type) {
                ItemDropType::None,
                ItemDropType::ZoneEmptyDig,
                ItemDropType::ZoneDig,
                ItemDropType::ZoneEventDig,
                ItemDropType::TownZoneDig,
                ItemDropType::TrashEmptyDig,
                ItemDropType::TrashDig => null,
                ItemDropType::RuinDig,
                ItemDropType::ERuinDig => $zone->getPrototype(),
            }, match ($type) {
                ItemDropType::None => 'unknown',
                ItemDropType::ZoneEmptyDig,
                ItemDropType::ZoneDig,
                ItemDropType::ZoneEventDig,
                ItemDropType::TownZoneDig => 'scavenge',
                ItemDropType::RuinDig => 'ruin_scavenge',
                ItemDropType::ERuinDig => 'eruin_scavenge',
                ItemDropType::TrashEmptyDig,
                ItemDropType::TrashDig => 'trash_scavenge',
            }, $event);

            if ($item) $this->gps->recordItemFound( $item_prototype, $citizen, match ($type) {
                ItemDropType::None,
                ItemDropType::ZoneEmptyDig,
                ItemDropType::ZoneDig,
                ItemDropType::ZoneEventDig,
                ItemDropType::TownZoneDig,
                ItemDropType::TrashEmptyDig,
                ItemDropType::TrashDig => null,
                ItemDropType::RuinDig,
                ItemDropType::ERuinDig => $zone->getPrototype(),
            }, match($type) {
                ItemDropType::None => 'unknown',
                ItemDropType::ZoneEmptyDig,
                ItemDropType::ZoneDig,
                ItemDropType::ZoneEventDig,
                ItemDropType::RuinDig,
                ItemDropType::ERuinDig => 'scavenge',
                ItemDropType::TownZoneDig => 'scavenge_town',
                ItemDropType::TrashEmptyDig,
                ItemDropType::TrashDig => 'trash',
            });

            if ($item_prototype?->getName() === 'chest_xl_#00')
                $this->picto_handler->give_picto($citizen, 'r_chstxl_#00');

        }

        return $item;
    }
}