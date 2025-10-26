<?php

namespace App\Configuration;

use App\Enum\DropMod;
use App\Event\Game\EventHooks\Christmas\NightlyGift1Event;
use App\Event\Game\EventHooks\Christmas\NightlyGift2Event;
use App\Event\Game\EventHooks\Christmas\NightlyGift3Event;
use MyHordes\Plugins\Interfaces\ConfigurationProviderInterface;

class HalloweenEventOverride implements ConfigurationProviderInterface
{
    public function data(): array
    {
        return [
            'halloween' => [
                'conf' => [
                    'event_dig' => [
                        'replace' => [
                            'food_noodles_hot_#00'=> [ 'food_candies_#00' ],
                            'meat_#00'            => [ 'food_candies_#00' ],
                            'vegetable_tasty_#00' => [ 'food_candies_#00' ],
                            'dish_tasty_#00'      => [ 'food_candies_#00' ],
                            'chama_tasty_#00'     => [ 'food_candies_#00' ],
                            'woodsteak_#00'       => [ 'food_candies_#00' ],
                            'egg_#00'             => [ 'food_candies_#00' ],
                            'apple_blue_#00'      => [ 'food_candies_#00' ],
                            'moldy_food_#00'      => [ 'food_candies_#00' ],
                        ]
                    ]
                ],
            ],
        ];
    }
}