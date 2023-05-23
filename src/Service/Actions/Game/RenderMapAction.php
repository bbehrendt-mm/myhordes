<?php

namespace App\Service\Actions\Game;

use App\Entity\Citizen;
use App\Entity\Town;
use App\Entity\Zone;
use App\Entity\ZombieEstimation;
use App\Service\CitizenHandler;
use App\Service\TownHandler;
use App\Service\ZoneHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Contracts\Translation\TranslatorInterface;

class RenderMapAction
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly TownHandler $town_handler,
        private readonly ZoneHandler $zone_handler,
        private readonly CitizenHandler $citizen_handler,
        private readonly Packages $asset,
    ) { }

    /**
     * @param Town|null $town
     * @param Citizen|null $activeCitizen
     * @param bool $admin
     * @return array
     */
    public function __invoke(
        Town $town = null,
        Citizen $activeCitizen = null,
        bool $admin = false
    ): array
    {
        $town = $town ?? $activeCitizen?->getTown();
        $zones = []; $range_x = [PHP_INT_MAX,PHP_INT_MIN]; $range_y = [PHP_INT_MAX,PHP_INT_MIN];

        $citizen_is_shaman = $admin ||
            ($this->citizen_handler->hasRole($activeCitizen, 'shaman')
                || $activeCitizen->getProfession()->getName() == 'shaman');

        $soul_zones_ids = $citizen_is_shaman
            ? array_map(function(Zone $z) { return $z->getId(); },$this->zone_handler->getSoulZones( $town ) )
            : [];

        $upgraded_map = $this->town_handler->getBuilding($town, 'item_electro_#00', true) !== null || $admin;

        $local_zones = [];
        $citizen_zone = $activeCitizen?->getZone();

        $scavenger_sense = $activeCitizen !== null ? $activeCitizen->getProfession()->getName() === 'collec' : $admin;
        $scout_sense     = $activeCitizen !== null ? $activeCitizen->getProfession()->getName() === 'hunter' : $admin;

        $citizen_zone_cache = [];
        foreach ($town->getCitizens() as $citizen)
            if ($citizen->getAlive() && $citizen->getZone() !== null) {
                if (!isset($citizen_zone_cache[$citizen->getZone()->getId()])) $citizen_zone_cache[$citizen->getZone()->getId()] = [$citizen];
                else $citizen_zone_cache[$citizen->getZone()->getId()][] = $citizen;
            }

        $rand_backup = mt_rand(PHP_INT_MIN, PHP_INT_MAX);
        mt_srand($this->em->getRepository(ZombieEstimation::class)->findOneBy(['town' => $town, 'day' => $town->getDay()])?->getSeed() ?? 0);

        foreach ($town->getZones() as $zone) {
            $x = $zone->getX();
            $y = $zone->getY();

            $range_x = [ min($range_x[0], $x), max($range_x[1], $x) ];
            $range_y = [ min($range_y[0], $y), max($range_y[1], $y) ];

            $current_zone = ['x' => $x, 'y' => $y];

            if ($admin)
                $current_zone['id'] = $zone->getId();

            if ( in_array( $zone->getId(), $soul_zones_ids) )
                $current_zone['s'] = true;

            if ($citizen_zone !== null
                && max( abs( $citizen_zone->getX() - $zone->getX() ), abs( $citizen_zone->getY() - $zone->getY() ) ) <= 2
                && ( abs( $citizen_zone->getX() - $zone->getX() ) + abs( $citizen_zone->getY() - $zone->getY() ) ) < 4
            ) $local_zones[] = $zone;

            if (!$admin && $zone->getDiscoveryStatus() <= Zone::DiscoveryStateNone) {
                if ($current_zone['s'] ?? false)
                    $zones[] = $current_zone;
                continue;
            }

            if ($admin) {
                $current_zone['t'] = true;
                $current_zone['z'] = $zone->getZombies();
                $current_zone['d'] = $this->zone_handler->getZoneDangerLevelNumber($zone, mt_rand(PHP_INT_MIN, PHP_INT_MAX), true);
            } else {
                $current_zone['t'] = $zone->getDiscoveryStatus() >= Zone::DiscoveryStateCurrent;
                if ($zone->getDiscoveryStatus() >= Zone::DiscoveryStateCurrent) {
                    if ($zone->getZombieStatus() >= Zone::ZombieStateExact) {
                        $current_zone['z'] = $zone->getZombies();
                    }
                    if ($zone->getZombieStatus() >= Zone::ZombieStateEstimate) {
                        $current_zone['d'] = $this->zone_handler->getZoneDangerLevelNumber($zone, mt_rand(PHP_INT_MIN, PHP_INT_MAX), $upgraded_map);
                    }
                }
            }


            if ($zone->isTownZone()) {
                $current_zone['td'] = $town->getDevastated();
                $current_zone['r'] = [
                    'n' => $town->getName(),
                    'b' => false,
                    'e' => false,
                ];
            } elseif ($zone->getPrototype()) {
                if ($admin) {
                    $current_zone['r'] = [
                        'n' => $this->translator->trans( $zone->getPrototype()->getLabel(), [], 'game' ) . ($zone->getBuryCount() > 0 ? " (" . $this->translator->trans('Verschüttet', [], 'admin') . ")" : ""),
                        'b' => $zone->getBuryCount() > 0,
                        'e'=> $zone->getPrototype()->getExplorable()
                    ];
                } else {
                    $current_zone['r'] = [
                        'n' => $zone->getBuryCount() > 0
                            ? $this->translator->trans( 'Verschüttete Ruine', [], 'game' )
                            : $this->translator->trans( $zone->getPrototype()->getLabel(), [], 'game' ),
                        'b' => $zone->getBuryCount() > 0,
                        'e' => $zone->getPrototype()->getExplorable()
                    ];
                }
            }

            if ($activeCitizen !== null && $activeCitizen->getZone() === $zone) $current_zone['cc'] = true;

            if (!$zone->isTownZone() && ($admin || !$activeCitizen->getVisitedZones()->contains( $zone )))
                $current_zone['g'] = true;

            if (!$zone->isTownZone() && $zone->getTag()) $current_zone['tg'] = $zone->getTag()->getRef();
            if (!$zone->isTownZone() && $activeCitizen?->getZone() === null
                && (!$town->getChaos() || $admin)
                && !empty( $citizen_zone_cache[$zone->getId()] ?? [] ) )
                $current_zone['c'] = array_map( fn(Citizen $c) => $c->getName(), $citizen_zone_cache[$zone->getId()] );
            elseif ($zone->isTownZone())
                $current_zone['co'] = count( array_filter( $town->getCitizens()->getValues(), fn(Citizen $c) => $c->getAlive() && $c->getZone() === null ) );

            $zones[] = $current_zone;
        }

        mt_srand($rand_backup);

        return [
            'geo' => [ 'x0' => $range_x[0], 'x1' => $range_x[1], 'y0' => $range_y[0], 'y1' => $range_y[1] ],
            'zones' => $zones,
            'lid' => $citizen_zone?->getId() ?? 0,
            'local' => array_map( function(Zone $z) use ($activeCitizen, $town, $citizen_zone, $scavenger_sense, $scout_sense) {
                $local = $citizen_zone === $z;
                $adjacent = ( abs( $citizen_zone->getX() - $z->getX() ) + abs( $citizen_zone->getY() - $z->getY() ) ) <= 1;
                $obj = [
                    'xr' => $z->getX() - $citizen_zone->getX(), 'yr' => $z->getY() - $citizen_zone->getY(),
                    'v' => $z->getDiscoveryStatus() > Zone::DiscoveryStateNone
                ];

                if ( $z->isTownZone() ) {
                    $obj['r'] = $this->asset->getUrl('build/images/ruin/town.gif');
                    if ($local) $obj['n'] = $town->getName();
                } elseif ($z->getPrototype() && $z->getBuryCount() > 0 && $obj['v']) {
                    $obj['r'] = $this->asset->getUrl('build/images/ruin/burried.gif');
                    if ($local) $obj['n'] = $this->translator->trans( 'Verschüttete Ruine', [], 'game' );
                } elseif ($z->getPrototype() && $obj['v']) {
                    $obj['r'] = $this->asset->getUrl("build/images/ruin/{$z->getPrototype()->getIcon()}.gif");
                    if ($local) $obj['n'] = $this->translator->trans( $z->getPrototype()->getLabel(), [], 'game' );
                }

                if (!$local && $adjacent && !$z->isTownZone()) {
                    $obj['vv'] = $activeCitizen->getVisitedZones()->contains($z);
                    if ($scavenger_sense && $z->getDiscoveryStatus() > Zone::DiscoveryStateNone) {
                        $obj['ss'] = $z->getDigs() > 0 || ($z->getPrototype() && $z->getRuinDigs() > 0);
                        if (!$obj['ss']) $obj['se'] = 2;
                    }
                    if ($scout_sense) {
                        $obj['sh'] = $z->getPersonalScoutEstimation($activeCitizen);
                        $obj['se'] = $obj['sh'] >= 9 ? 2 : ($obj['sh'] >= 5 ? 1 : 0);
                    }
                }

                if ($local) {
                    $obj['x']  = $z->getX(); $obj['y'] = $z->getY();
                    $obj['z']  = $z->getZombies();
                    $obj['zc'] = max(0, $z->getInitialZombies() - $z->getZombies());
                    $obj['c']  = $z->getCitizens()->count() + ($z->isTownZone()
                            ? count( array_filter( $town->getCitizens()->getValues(), fn(Citizen $c) => $c->getAlive() && $c->getZone() === null ) )
                            : 0);
                }

                return $obj;
            }, $local_zones )
        ];
    }
}