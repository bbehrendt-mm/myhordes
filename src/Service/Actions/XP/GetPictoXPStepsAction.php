<?php

namespace App\Service\Actions\XP;

use App\Entity\NotificationSubscription;
use App\Entity\Picto;
use App\Entity\PictoPrototype;
use App\Entity\PictoRollup;
use App\Entity\Season;
use App\Entity\TownRankingProxy;
use App\Entity\User;
use App\Enum\UserSetting;
use ArrayHelpers\Arr;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class GetPictoXPStepsAction
{
    public function __invoke(): Collection
    {
        $pt_2   = [ 1 => 2,  3 => 1,  5 => 1,  8 => 1, 10 => 1 ];
        $pt_5 = [ 1 => 5, 3 => 2, 5 => 2, 8 => 2, 10 => 2 ];
        $pt_7 = [ 1 => 7, 3 => 2, 5 => 2, 8 => 2, 10 => 2 ];

        $pt_2_6 = [ 6 => 2, 12 => 1, 18 => 1, 24 => 1 ];
        $pt_2_10 = [ 10 => 2, 20 => 1, 30 => 1, 50 => 1 ];
        $pt_2_15 = [ 15 => 2, 30 => 1, 45 => 1, 60 => 1 ];

        $p_job = [50 => 4, 100 => 7];

        return new ArrayCollection([
            'r_thermal_#00' => $pt_2,
            'r_ebcstl_#00' =>  $pt_2,
            'r_ebpmv_#00' =>   $pt_2,
            'r_ebgros_#00' =>  $pt_2,
            'r_ebcrow_#00' =>  $pt_2,
            'r_maso_#00'   =>  $pt_2,
            'r_wondrs_#00' =>  [15 => 2, 30 => 1, 45 => 1, 60 => 1],

            'r_batgun_#00' =>  $pt_5,
            'r_door_#00'   =>  $pt_5,
            'r_explo2_#00' =>  $pt_5,
            'r_ebuild_#00' =>  $pt_5,
            'r_chstxl_#00' =>  [ 1 => 5, 2 => 2, 3 => 2, 5 => 2 ],

            'r_dnucl_#00'  =>  $pt_7,
            'r_watgun_#00' =>  $pt_7,
            'r_cmplst_#00' =>  $pt_7,

            'r_tronco_#00' =>  [ 1 => 8, 2 => 2, 3 => 2, 5 => 2 ],

            'r_cobaye_#00' =>  $pt_2_6,
            'r_solban_#00' =>  $pt_2_6,
            'r_explor_#00' =>  $pt_2_6,
            'r_collec_#00' =>  $pt_2_6,
            'r_guard_#00'  =>  $pt_2_6,
            'r_ruine_#00'  =>  $pt_2_6,

            'r_repair_#00' =>  $pt_2_10,
            'r_plundr_#00' =>  $pt_2_10,
            'r_camp_#00'   =>  $pt_2_10,
            'r_digger_#00' =>  $pt_2_10,

            'r_theft_#00'  =>  $pt_2_15,
            'r_cgarb_#00'  =>  $pt_2_15,
            'r_cwater_#00' =>  $pt_2_15,
            'r_cooked_#00' =>  $pt_2_15,

            'r_jbasic_#00' =>  $p_job,
            'r_jtamer_#00' =>  $p_job,
            'r_jrangr_#00' =>  $p_job,
            'r_jermit_#00' =>  $p_job,
            'r_jcolle_#00' =>  $p_job,
            'r_jguard_#00' =>  $p_job,
            'r_jtech_#00'  =>  $p_job,
        ]);
    }
}