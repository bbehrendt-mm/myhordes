<?php

namespace App\Service\Actions\Game;

use App\Entity\Town;
use App\Entity\ZombieEstimation;
use App\Enum\Configuration\TownSetting;
use App\Service\ConfMaster;
use App\Structures\TownConf;
use Doctrine\ORM\EntityManagerInterface;

readonly class PrepareZombieAttackEstimationAction
{

    public function __construct(
        private EntityManagerInterface $entity_manager,
        private ConfMaster $conf,
    ) {}

    public function forTown(Town $town, int $future = 3) {
        if ($future < 0) return;
        $d = $town->getDay();

        for ($current_day = $d; $current_day <= ( $d + $future ); $current_day++)
            if (!$this->entity_manager->getRepository(ZombieEstimation::class)->findOneByTown( $town, $current_day ))
                $town->addZombieEstimation( ($this)( $this->conf->getTownConfiguration( $town ), $current_day) );
    }

    private function deshift( int $value, int $bound_min, int $bound_max, float &$off_min, float &$off_max ): bool {

        $bound_min = ($value - $bound_min) / $value;
        $bound_max = ($bound_max - $value) / $value;

        if ($off_min > $bound_min) {
            $off_max += ($off_min - $bound_min);
            $off_min = $bound_min;
            return true;
        } elseif ($off_max > $bound_max) {
            $off_min += ($off_max - $bound_max);
            $off_max = $bound_max;
            return true;
        }

        return false;
    }

    public function __invoke(TownConf $conf, int $day, ?float $fixed = null): ZombieEstimation
    {
        $const_ratio_base = 0.5;
        $const_ratio_low = 0.75;

        $max_ratio = match( $conf->get(TownSetting::OptFeatureAttacks) ) {
            'hard' => 3.1,
            'easy' => $const_ratio_low,
            default => 1.1,
        };

        $ratio_min = ($day <= 3 ? $const_ratio_low : $max_ratio);
        $ratio_max = ($day <= 3 ? ($day <= 1 ? $const_ratio_base : $const_ratio_low) : $max_ratio);

        $min = round( $ratio_min * pow(max(1,$day-1) * 0.75 + 2.5,3) );
        $max = round( $ratio_max * pow($day * 0.75 + 3.5,3) );

        if ($fixed === null) {
            $value = mt_rand($min,$max);
            if ($value > ($min + 0.5 * ($max-$min))) $value = mt_rand($min,$max);
        } else $value = $min + max(0.0, min( $fixed, 1.0 )) * ($max - $min);

        $factor = match(true) {
            $day <= 15 => 1,
            $day <= 20 => 0.75,
            $day <= 30 => 0.5,
            $day <= 40 => 0.25,
            default    => 0.15,
        };

        $off_min = mt_rand(
            round($factor * ($conf->get(TownSetting::OptModifierEstimOffsetMin) - $conf->get(TownSetting::OptModifierEstimInitialShift))),
            round($factor * ($conf->get(TownSetting::OptModifierEstimOffsetMax) - $conf->get(TownSetting::OptModifierEstimInitialShift)))
        );

        $off_max = round($factor * ($conf->get(TownSetting::OptModifierEstimVariance) - (2 * $conf->get(TownSetting::OptModifierEstimInitialShift)))) - $off_min;

        $shift_min = mt_rand(0, $conf->get(TownSetting::OptModifierEstimInitialShift) * $factor * 100) / 10000;
        $shift_max = ($factor * $conf->get(TownSetting::OptModifierEstimInitialShift) / 100) - $shift_min;

        // Rebase offsets and shifts
        $this->deshift( $value, $min, $max, $shift_min, $shift_max );

        $target_min = round($value - ($value * $shift_min));
        $target_max = round($value + ($value * $shift_max));

        $o1_prc = $off_min / 100;
        $o2_prc = $off_max / 100;
        $rebound_min = $this->deshift( $target_min, $min, $max, $o1_prc, $o2_prc );
        $rebound_max = $this->deshift( $target_max, $min, $max, $o1_prc, $o2_prc );
        if ($rebound_min || $rebound_max) {
            $off_min = round( $o1_prc * 100 );
            $off_max = round( $o2_prc * 100 );

            $protect = match(true) {
                $day <= 30 => 3,
                default    => 1
            };

            if ($off_min < $protect) {
                $off_max -= ($protect - $off_min);
                $off_min = $protect;
            } elseif ($off_max < $protect) {
                $off_min -= ($protect - $off_max);
                $off_max = $protect;
            }
        }

        if ($conf->get(TownSetting::OptFeatureAttacks) === 'hard')
            $value = mt_rand( $target_min, $target_max );

        return (new ZombieEstimation())
                ->setDay( $day )
                ->setZombies( $value )
                ->setOffsetMin( $off_min )
                ->setOffsetMax( $off_max )
                ->setTargetMin( $target_min )
                ->setTargetMax( $target_max )
        ;
    }

}