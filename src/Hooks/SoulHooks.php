<?php

namespace App\Hooks;

use App\Entity\CitizenProfession;
use App\Entity\PictoPrototype;
use App\Entity\User;
use App\Service\Actions\XP\GetPictoXPStepsAction;
use App\Service\User\UserUnlockableService;

class SoulHooks extends HooksCore {


    private function counter(User $user, string $template = null, string $subject = null): array {
        /** @var UserUnlockableService $unlockService */
        $unlockService = $this->container->get(UserUnlockableService::class);

        $num = $unlockService->hasRecordedHeroicExperienceFor( $user, template: $template, subject: $subject, season: true, total: $count );
        return [
            'achieved' => $num > 0,
            'total' => $count,
        ];
    }

	public function earnXHP(): string {
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();

        /** @var GetPictoXPStepsAction $action */
        $action = $this->container->get(GetPictoXPStepsAction::class);

        $day_data = [
            [
                'value' => 1,
                'valueNote'     => $this->translator->trans('pro überlebtem Tag', [], 'soul'),
                'description'   => $this->translator->trans('Überlebe in einer Stadt, solange du kannst!', [], 'soul'),
                'repeat' => true,
                ...$this->counter($user, template: 'hxp_survived_days_base'),
            ],
            [
                'value' => 2,
                'valueNote'     => null,
                'description'   => $this->translator->trans('Überlebe mindestens {days} Tage in einer Pandämonium-Stadt', ['days' => 5], 'soul'),
                'repeat' => true,
                ...$this->counter($user, template: 'hxp_panda_day5'),
            ],
            [
                'value' => 5,
                'valueNote'     => null,
                'description'   => $this->translator->trans('Überlebe mindestens {days} Tage in einer Pandämonium-Stadt', ['days' => 10], 'soul'),
                'repeat' => true,
                ...$this->counter($user, template: 'hxp_panda_day10'),
            ],
            ...array_map(function(CitizenProfession $p) use ($user) {
                return [
                    'value' => 2,
                    'valueNote'     => null,
                    'description'   => $this->translator->trans('Überlebe mindestens {days} Tage als {profession} in einer Stadt.', ['days' => 10, 'profession' => $this->translator->trans($p->getLabel(), [], 'game')], 'soul'),
                    'repeat' => false,
                    ...$this->counter($user, subject: "profession_day10_{$p->getName()}"),
                ];
            }, array_filter( $this->em->getRepository(CitizenProfession::class)->findSelectable(), fn(CitizenProfession $p) => $p->getName() !== 'shaman' )),
            [
                'value' => 5,
                'valueNote'     => null,
                'description'   => $this->translator->trans('Überlebe insgesamt mindestens {days} Tage in einer Stadt.', ['days' => 15], 'soul'),
                'repeat' => true,
                ...$this->counter($user, template: 'hxp_common_day15'),
            ],
            [
                'value' => 10,
                'valueNote'     => null,
                'description'   => $this->translator->trans('Überlebe insgesamt mindestens {days} Tage in einer Stadt.', ['days' => 30], 'soul'),
                'repeat' => false,
                ...$this->counter($user, subject: 'common_day30'),
            ],
        ];

        $multi_picto_db = [
            'r_surgrp_#00' => [0 => [null,2]],
            'r_surlst_#00' => [5 => [8, 7], 9 => [13,14], 14 => [null,21]],
            'r_suhard_#00' => [5 => [null, 7]],
        ];

        $picto_db = ($action)()->toArray();
        $picto_data = [];

        foreach ($multi_picto_db as $id => $points) {

            $picto_proto = $this->em->getRepository(PictoPrototype::class)->findOneByName($id);
            if (!$picto_proto) continue;

            foreach ($points as $day => [$tillDay, $point])
                $picto_data[] = [
                    'value' => $point,
                    'valueNote' => $day > 0
                        ? ( $tillDay !== null
                            ? $this->translator->trans('Wenn du zwischen {day1} und {day2} Tage in der Stadt überlebt hast!', ['day1' => $day, 'day2' => $tillDay], 'soul')
                            : $this->translator->trans('Wenn du zwischen mindestens {day} Tage in der Stadt überlebt hast!', ['day' => $day], 'soul')
                        )
                        : null,
                    'valuePost' => null,
                    'icon'  => $picto_proto->getIcon(),
                    'name'  => $this->translator->trans($picto_proto->getLabel(), [], 'game'),
                    'repeat' => true,
                    'achieved' => false,
                    'total' => 0,
                    'sub' => []
                ];
        }

        foreach ($picto_db as $id => $points) {
            $picto_proto = $this->em->getRepository(PictoPrototype::class)->findOneByName($id);
            if (!$picto_proto) continue;

            foreach ($points as $count => $value)
                if (!isset($picto_data[$picto_proto->getName()]))
                    $picto_data[$picto_proto->getName()] = [
                        'value' => $value,
                        'valueNote' => null,
                        'valuePost' =>"× $count",
                        'icon'  => $picto_proto->getIcon(),
                        'name'  => $this->translator->trans($picto_proto->getLabel(), [], 'game'),
                        'repeat' => false,
                        'sub' => [],
                        ...$this->counter($user, subject: "picto_{$id}" . ( $count > 1 ? "__$count" : "" ) ),
                    ];
                else {
                    if (!isset($picto_data[$picto_proto->getName()]['sub'][$value]))
                        $picto_data[$picto_proto->getName()]['sub'][$value] = [
                            'value' => $value,
                            'list' => []
                        ];
                    $picto_data[$picto_proto->getName()]['sub'][$value]['list'][] = [
                        'name' => "× $count",
                        ...$this->counter($user, subject: "picto_{$id}" . ($count > 1 ? "__$count" : "")),
                    ];
                }
        }

        $other_data = [
            [
                'value' => 10,
                'valueNote'     => $this->translator->trans('pro geworbenem Spieler', [], 'soul'),
                'description'   => $this->translator->trans('Lade neue Spieler zu MyHordes ein und kümmere dich rührend um sie, bis sie das erste Mal Erfahrung für eine Fähigkeit ausgeben.', [], 'soul'),
                'repeat'        => true,
                ...$this->counter($user, template: 'hxp_ref_first'),
            ],
            [
                'value' => 2,
                'valueNote'     => $this->translator->trans('pro gefressenem Bürger', [], 'soul'),
                'description'   => $this->translator->trans('Dezimiere als Ghul die Bewohner deiner Stadt', [], 'soul'),
                'repeat' => true,
                ...$this->counter($user, template: 'hxp_ghoul_aggression'),
            ],
        ];

        return $this->twig->render('partials/hooks/soul/hxp_earnings.html.twig', [
            'days' => $day_data,
            'pictos' => $picto_data,
            'others' => $other_data,
        ]);

	}
}