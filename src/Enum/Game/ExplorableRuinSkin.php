<?php

namespace App\Enum\Game;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use function React\Promise\map;

enum ExplorableRuinSkin: string
{
    case Bunker = 'bunker';
    case Hotel = 'hotel';
    case Hospital = 'hospital';

    public function isVerticallyInverse(): bool {
        return $this === self::Bunker;
    }

    /**
     * @return Collection<string,string>
     */
    public function assetsTiles(): Collection {
        return new ArrayCollection([
            'exit' => "build/images/explore/{$this->value}/exit.png",
            'room' => "build/images/explore/{$this->value}/room.png",
            1  => "build/images/explore/{$this->value}/0010.png",
            2  => "build/images/explore/{$this->value}/0100.png",
            3  => "build/images/explore/{$this->value}/0110.png",
            4  => "build/images/explore/{$this->value}/0001.png",
            5  => "build/images/explore/{$this->value}/0011.png",
            6  => "build/images/explore/{$this->value}/0101.png",
            7  => "build/images/explore/{$this->value}/0111.png",
            8  => "build/images/explore/{$this->value}/1000.png",
            9  => "build/images/explore/{$this->value}/1010.png",
            10 => "build/images/explore/{$this->value}/1100.png",
            11 => "build/images/explore/{$this->value}/1110.png",
            12 => "build/images/explore/{$this->value}/1001.png",
            13 => "build/images/explore/{$this->value}/1011.png",
            14 => "build/images/explore/{$this->value}/1101.png",
            15 => "build/images/explore/{$this->value}/1111.png",
        ]);
    }

    /**
     * @param bool $open
     * @param int $level
     * @return Collection<int,string>
     */
    public function assetDoors(bool $open, int $level = 0): Collection {

        $level = $this->isVerticallyInverse() ? (-$level) : $level;
        $postfix = match (true) {
            $open && $level < 0 => 'o_b',
            $open && $level === 0 => 'o',
            $open && $level > 0 => 'o_r',
            !$open => 'c',
        };

        $d = 0.1014;

        return new ArrayCollection([
            1 => [
                'w' => $d,
                'h' => $d,
                'x' => 0.17,
                'y' => 0.32,
                'i' =>"build/images/explore/{$this->value}/dtl{$postfix}.png"
            ],
            2 => [
                'w' => $d,
                'h' => $d,
                'x' => 0.45,
                'y' => 0.32,
                'i' =>"build/images/explore/{$this->value}/dt{$postfix}.png"
            ],
            3 => [
                'w' => $d,
                'h' => $d,
                'x' => 0.83 - $d,
                'y' => 0.32,
                'i' =>"build/images/explore/{$this->value}/dtr{$postfix}.png"
            ],
            4 => [
                'w' => $d,
                'h' => $d,
                'x' => 0.34,
                'y' => 0.45,
                'i' =>"build/images/explore/{$this->value}/dl{$postfix}.png"
            ],
            5 => [
                'w' => $d,
                'h' => $d,
                'x' => 0.66 - $d,
                'y' => 0.55 - $d,
                'i' =>"build/images/explore/{$this->value}/dr{$postfix}.png"
            ],
            6 => [
                'w' => $d,
                'h' => $d,
                'x' => 0.17,
                'y' => 0.68 - $d,
                'i' =>"build/images/explore/{$this->value}/dbl{$postfix}.png"
            ],
            7 => [
                'w' => $d,
                'h' => $d,
                'x' => 0.55 - $d,
                'y' => 0.68 - $d,
                'i' =>"build/images/explore/{$this->value}/db{$postfix}.png"
            ],
            8 => [
                'w' => $d,
                'h' => $d,
                'x' => 0.83 - $d,
                'y' => 0.68 - $d,
                'i' =>"build/images/explore/{$this->value}/dbr{$postfix}.png"
            ],
        ]);
    }

    /**
     * @return Collection<string,array>
     */
    public function assetDecals(): Collection {
        return match ($this) {
            self::Bunker => new ArrayCollection([
                '1a' => [
                    'w' => 0.06,
                    'h' => 0.09,
                    'x' => 0.37,
                    'y' => 0.27,
                    'i' => "build/images/explore/bunker/wall_barrel_D.png"
                ],
                '1b' => [
                    'w' => 0.14,
                    'h' => 0.20,
                    'x' => 0.32,
                    'y' => 0.12,
                    'i' => "build/images/explore/bunker/wall_pipe_D.png"
                ],
                '3a' => [
                    'w' => 0.06,
                    'h' => 0.09,
                    'x' => 0.58,
                    'y' => 0.27,
                    'i' => "build/images/explore/bunker/wall_barrel_E.png"
                ],

                '3b' => [
                    'w' => 0.14,
                    'h' => 0.20,
                    'x' => 0.54,
                    'y' => 0.12,
                    'i' => "build/images/explore/bunker/wall_pipe_E.png"
                ],
                '4' => [
                    'w' => 0.09,
                    'h' => 0.08,
                    'x' => 0.18,
                    'y' => 0.31,
                    'i' => "build/images/explore/bunker/wall_hatch_B.png"
                ],
                '7' => [
                    'w' => 0.09,
                    'h' => 0.08,
                    'x' => 0.76,
                    'y' => 0.31,
                    'i' => "build/images/explore/bunker/wall_hatch_G.png"
                ],
                '10a' => [
                    'w' => 0.09,
                    'h' => 0.08,
                    'x' => 0.18,
                    'y' => 0.62,
                    'i' => "build/images/explore/bunker/wall_hatch_A.png"
                ],
                '10b' => [
                    'w' => 0.11,
                    'h' => 0.07,
                    'x' => 0.14,
                    'y' => 0.65,
                    'i' => "build/images/explore/bunker/wall_gutter_G.png"
                ],
                '13a' => [
                    'w' => 0.09,
                    'h' => 0.08,
                    'x' => 0.76,
                    'y' => 0.62,
                    'i' => "build/images/explore/bunker/wall_hatch_H.png"
                ],
                '13b' => [
                    'w' => 0.11,
                    'h' => 0.07,
                    'x' => 0.80,
                    'y' => 0.65,
                    'i' => "build/images/explore/bunker/wall_gutter_B.png"
                ],
                '14' => [
                    'w' => 0.04,
                    'h' => 0.18,
                    'x' => 0.34,
                    'y' => 0.69,
                    'i' => "build/images/explore/bunker/wall_grid_E.png"
                ],
                '16' => [
                    'w' => 0.04,
                    'h' => 0.18,
                    'x' => 0.62,
                    'y' => 0.69,
                    'i' => "build/images/explore/bunker/wall_grid_D.png"
                ]
            ]),

            self::Hotel => new ArrayCollection([
                '1' => [
                    'w' => 0.08,
                    'h' => 0.13,
                    'x' => 0.35,
                    'y' => 0.27,
                    'i' => "build/images/explore/hotel/wall_flowers_D.png"
                ],
                '2a' => [
                    'w' => 0.25,
                    'h' => 0.32,
                    'x' => 0.37,
                    'y' => 0.05,
                    'i' => "build/images/explore/hotel/zone_stain_top.png"
                ],
                '2b' => [
                    'w' => 0.09,
                    'h' => 0.18,
                    'x' => 0.52,
                    'y' => 0.06,
                    'i' => "build/images/explore/hotel/zone_dead_top.png"
                ],
                '3' => [
                    'w' => 0.08,
                    'h' => 0.13,
                    'x' => 0.57,
                    'y' => 0.27,
                    'i' => "build/images/explore/hotel/wall_flowers_E.png"
                ],
                '4a' => [
                    'w' => 0.09,
                    'h' => 0.12,
                    'x' => 0.22,
                    'y' => 0.31,
                    'i' => "build/images/explore/hotel/wall_palmtree_B.png"
                ],
                '4b' => [
                    'w' => 0.14,
                    'h' => 0.08,
                    'x' => 0.13,
                    'y' => 0.34,
                    'i' => "build/images/explore/hotel/wall_bench_B.png"
                ],
                '5a' => [
                    'w' => 0.18,
                    'h' => 0.09,
                    'x' => 0.30,
                    'y' => 0.39,
                    'i' => "build/images/explore/hotel/zone_dead_left.png"
                ],
                '5b' => [
                    'w' => 0.18,
                    'h' => 0.09,
                    'x' => 0.30,
                    'y' => 0.39,
                    'i' => "build/images/explore/hotel/zone_dead_right.png"
                ],
                '6a' => [
                    'w' => 0.18,
                    'h' => 0.09,
                    'x' => 0.54,
                    'y' => 0.39,
                    'i' => "build/images/explore/hotel/zone_dead_left.png"
                ],
                '6b' => [
                    'w' => 0.18,
                    'h' => 0.09,
                    'x' => 0.54,
                    'y' => 0.39,
                    'i' => "build/images/explore/hotel/zone_dead_right.png"
                ],
                '7a' => [
                    'w' => 0.09,
                    'h' => 0.12,
                    'x' => 0.70,
                    'y' => 0.31,
                    'i' => "build/images/explore/hotel/wall_palmtree_G.png"
                ],
                '7b' => [
                    'w' => 0.14,
                    'h' => 0.08,
                    'x' => 0.72,
                    'y' => 0.34,
                    'i' => "build/images/explore/hotel/wall_bench_G.png"
                ],
                '8a' => [
                    'w' => 0.32,
                    'h' => 0.25,
                    'x' => 0.05,
                    'y' => 0.37,
                    'i' => "build/images/explore/hotel/zone_stain_left.png"
                ],
                '8b' => [
                    'w' => 0.18,
                    'h' => 0.09,
                    'x' => 0.20,
                    'y' => 0.45,
                    'i' => "build/images/explore/hotel/zone_dead_left.png"
                ],
                '9a' => [
                    'w' => 0.32,
                    'h' => 0.25,
                    'x' => 0.65,
                    'y' => 0.37,
                    'i' => "build/images/explore/hotel/zone_stain_right.png"
                ],
                '9b' => [
                    'w' => 0.18,
                    'h' => 0.09,
                    'x' => 0.75,
                    'y' => 0.45,
                    'i' => "build/images/explore/hotel/zone_dead_right.png"
                ],
                '10' => [
                    'w' => 0.14,
                    'h' => 0.08,
                    'x' => 0.13,
                    'y' => 0.58,
                    'i' => "build/images/explore/hotel/wall_bench_A.png"
                ],
                '11a' => [
                    'w' => 0.18,
                    'h' => 0.09,
                    'x' => 0.30,
                    'y' => 0.53,
                    'i' => "build/images/explore/hotel/zone_dead_right.png"
                ],
                '11b' => [
                    'w' => 0.18,
                    'h' => 0.09,
                    'x' => 0.30,
                    'y' => 0.53,
                    'i' => "build/images/explore/hotel/zone_dead_left.png"
                ],
                '12a' => [
                    'w' => 0.18,
                    'h' => 0.09,
                    'x' => 0.54,
                    'y' => 0.53,
                    'i' => "build/images/explore/hotel/zone_dead_right.png"
                ],
                '12b' => [
                    'w' => 0.18,
                    'h' => 0.09,
                    'x' => 0.54,
                    'y' => 0.53,
                    'i' => "build/images/explore/hotel/zone_dead_left.png"
                ],
                '13' => [
                    'w' => 0.14,
                    'h' => 0.08,
                    'x' => 0.72,
                    'y' => 0.58,
                    'i' => "build/images/explore/hotel/wall_bench_H.png"
                ],
                '15a' => [
                    'w' => 0.25,
                    'h' => 0.32,
                    'x' => 0.37,
                    'y' => 0.65,
                    'i' => "build/images/explore/hotel/zone_stain_bottom.png"
                ],
                '15b' => [
                    'w' => 0.09,
                    'h' => 0.18,
                    'x' => 0.40,
                    'y' => 0.75,
                    'i' => "build/images/explore/hotel/zone_dead_bottom.png"
                ],
            ]),

            self::Hospital => new ArrayCollection([
                '1a' => [
                    'w' => 0.10,
                    'h' => 0.11,
                    'x' => 0.34,
                    'y' => 0.27,
                    'i' => 'build/images/explore/hospital/wall_dead_D.png'
                ],
                '3a' => [
                    'w' => 0.10,
                    'h' => 0.11,
                    'x' => 0.57,
                    'y' => 0.27,
                    'i' => 'build/images/explore/hospital/wall_dead_E.png'
                ],
                '1b' => [
                    'w' => 0.07,
                    'h' => 0.17,
                    'x' => 0.37,
                    'y' => 0.15,
                    'i' => 'build/images/explore/hospital/wall_bed_D.png'
                ],
                '3b' => [
                    'w' => 0.07,
                    'h' => 0.17,
                    'x' => 0.57,
                    'y' => 0.15,
                    'i' => 'build/images/explore/hospital/wall_bed_E.png'
                ],
                '5a' => [
                    'w' => 0.10,
                    'h' => 0.09,
                    'x' => 0.39,
                    'y' => 0.39,
                    'i' => 'build/images/explore/hospital/zone_dead_top.png'
                ],
                '5b' => [
                    'w' => 0.10,
                    'h' => 0.09,
                    'x' => 0.39,
                    'y' => 0.39,
                    'i' => 'build/images/explore/hospital/zone_dead_bottom.png'
                ],
                '6a' => [
                    'w' => 0.09,
                    'h' => 0.10,
                    'x' => 0.54,
                    'y' => 0.39,
                    'i' => 'build/images/explore/hospital/zone_dead_left.png'
                ],
                '6b' => [
                    'w' => 0.09,
                    'h' => 0.10,
                    'x' => 0.54,
                    'y' => 0.39,
                    'i' => 'build/images/explore/hospital/zone_dead_right.png'
                ],
                '8a' => [
                    'w' => 0.09,
                    'h' => 0.10,
                    'x' => 0.20,
                    'y' => 0.45,
                    'i' => 'build/images/explore/hospital/zone_dead_right.png'
                ],
                '8b' => [
                    'w' => 0.09,
                    'h' => 0.10,
                    'x' => 0.20,
                    'y' => 0.45,
                    'i' => 'build/images/explore/hospital/zone_dead_left.png'
                ],
                '9a' => [
                    'w' => 0.09,
                    'h' => 0.10,
                    'x' => 0.75,
                    'y' => 0.45,
                    'i' => 'build/images/explore/hospital/zone_dead_left.png'
                ],
                '9b' => [
                    'w' => 0.09,
                    'h' => 0.10,
                    'x' => 0.75,
                    'y' => 0.45,
                    'i' => 'build/images/explore/hospital/zone_dead_right.png'
                ],
                '11a' => [
                    'w' => 0.10,
                    'h' => 0.09,
                    'x' => 0.39,
                    'y' => 0.53,
                    'i' => 'build/images/explore/hospital/zone_dead_bottom.png'
                ],
                '11b' => [
                    'w' => 0.10,
                    'h' => 0.09,
                    'x' => 0.39,
                    'y' => 0.53,
                    'i' => 'build/images/explore/hospital/zone_dead_top.png'
                ],
                '12a' => [
                    'w' => 0.09,
                    'h' => 0.10,
                    'x' => 0.54,
                    'y' => 0.53,
                    'i' => 'build/images/explore/hospital/zone_dead_right.png'
                ],
                '12b' => [
                    'w' => 0.09,
                    'h' => 0.10,
                    'x' => 0.54,
                    'y' => 0.53,
                    'i' => 'build/images/explore/hospital/zone_dead_left.png'
                ],
                '14' => [
                    'w' => 0.04,
                    'h' => 0.09,
                    'x' => 0.33,
                    'y' => 0.78,
                    'i' => 'build/images/explore/hospital/wall_grid_K.png'
                ],
                '16' => [
                    'w' => 0.04,
                    'h' => 0.09,
                    'x' => 0.63,
                    'y' => 0.78,
                    'i' => 'build/images/explore/hospital/wall_grid_J.png'
                ]
            ]),
        };
    }

    public function actorPlayer(bool $no_ox): string {
        return $no_ox ? "build/images/explore/you_noox.gif" : "build/images/explore/you.gif";
    }

    public function actorZombie(bool $dead): string {
        return $dead ? "build/images/explore/{$this->value}/dead.png" : "build/images/explore/{$this->value}/zombie.gif";
    }

    public function fog(bool $cloud): string {
        return $cloud ? "build/images/explore/frames.gif" : "build/images/explore/white.png";
    }

    public function ui_frame(): string {
        return "build/images/explore/frame.png";
    }

    public function ui_oxygen(): string {
        return "build/images/icons/oxygen_small.gif";
    }
}


