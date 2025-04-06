<?php

namespace App\Service\Actions\Game;

use App\Entity\ZombieEstimation;
use App\Enum\Configuration\TownSetting;
use App\Service\RandomGenerator;
use App\Structures\TownConf;
use App\Structures\WatchtowerEstimation;

readonly class EstimateZombieAttackAction
{

    public function __construct(
        private RandomGenerator $random,
    ) {}

    private function calculate_offsets(&$offsetMin, &$offsetMax, $nbRound, $min_spread = 10): void {
        $end = min($nbRound, 24);

        for ($i = 0; $i < $end; $i++) {
            $spendable = (max(0, $offsetMin) + max(0, $offsetMax)) / (24 - $i);
            $calc_next = fn() => mt_rand( floor($spendable * 250), floor($spendable * 1000) ) / 1000.0;

            if ($offsetMin + $offsetMax > $min_spread) {
                $increase_min = $this->random->chance($offsetMin / ($offsetMin + $offsetMax));
                $alter = $calc_next();
                if ($this->random->chance(0.25)){
                    $alterMax = $calc_next();
                    $offsetMin = max(0, $offsetMin - $alter);
                    $offsetMax = max(0, $offsetMax - $alterMax);
                } else {
                    if ($increase_min && $offsetMin > 0) $offsetMin = max(0, $offsetMin - $alter);
                    else $offsetMax = max(0, $offsetMax - $alter);
                }
            }
        }
    }

    public function __invoke(
        TownConf $conf, ZombieEstimation $estimation,
        ?int $citizens = null, float $citizen_ratio = 1.0,
        int $subtract_weighted_citizens = 0, int $blocks = 1,
        float $penalty_factor = 1.0, ?int $fallback_seed = 0,

    ): WatchtowerEstimation
    {

        $offsetMin = $estimation->getOffsetMin();
        $offsetMax = $estimation->getOffsetMax();
        $cc_offset = $conf->get(TownSetting::OptModifierWtOffset);

        $citizen_count = max(0, (($citizens ?? $estimation->getCitizens()->count()) * $citizen_ratio + $cc_offset) - $subtract_weighted_citizens);

        $rand_backup = mt_rand(PHP_INT_MIN, PHP_INT_MAX);
        mt_srand($estimation->getSeed() ?? $fallback_seed);

        $this->calculate_offsets($offsetMin, $offsetMax,
                                 $citizen_count,
                                 $conf->get(TownSetting::OptModifierEstimSpread) - $conf->get(TownSetting::OptModifierEstimInitialShift)
        );

        // We've set a pre-defined seed before, which will impact randomness of all mt_rand calls after this function
        // We're trying to set a new random seed to combat side effects
        mt_srand($rand_backup);

        $min = round(($estimation->getTargetMin() - ($estimation->getTargetMin() * $offsetMin / 100)) * $penalty_factor);
        $max = round(($estimation->getTargetMax() + ($estimation->getTargetMax() * $offsetMax / 100)) * $penalty_factor);

        if ($blocks > 1) {
            $min = floor($min / $blocks) * $blocks;
            $max = ceil($max / $blocks) * $blocks;
        }

        $quality = min($citizen_count/24.0, 1);

        $estim = new WatchtowerEstimation();
        $estim->setMin($min);
        $estim->setMax($max);
        $estim->setEstimation($quality);
        $estim->setVisible( round($quality * 100) >= $conf->get(TownSetting::OptModifierWtThreshold) );
        $estim->setFuture(0);

        return $estim;
    }

}