<?php

namespace App\Enum;

use App\Entity\Item;
use App\Entity\ItemPrototype;
use App\Enum\Configuration\TownSetting;
use App\Structures\TownConf;
use App\Translation\T;

enum AutomaticAccountMarkerType: string {
    case SuspiciousDeath = 'suspicious-death';
    case Mayor = 'mayor';

    public function delayInDays(): int {
        return match($this) {
            self::SuspiciousDeath => 14,
            self::Mayor => 10,
        };
    }

    public function limit(): int {
        return match($this) {
            self::SuspiciousDeath => 3,
            self::Mayor => 1,
        };
    }

    public function label(): string {
        return match($this) {
            self::SuspiciousDeath => T::__('Verdächtiger Tod', 'admin'),
            self::Mayor => T::__('Teilnehmer einer Spieler-erzeugten öffentlichen Stadt', 'admin'),
        };
    }

    public function help(): string {
        return match($this) {
            self::SuspiciousDeath => T::__('Wird für Tode an Tag 3 oder früher gesetzt. Blockiert den Beitritt zu neuen Städten wenn aktiv.', 'admin'),
            self::Mayor => T::__('Wird gesetzt, wenn der Spieler eine öffentliche Stadt erstellt oder dieser beitritt. Blockiert das Erstellen oder Betreten fremder Spieler-erstellten öffentlichen Städte wenn aktiv.', 'admin'),
        };
    }

    public function isRelatedToTown(): bool {
        return match($this) {
            self::SuspiciousDeath, self::Mayor => true,
        };
    }

    public function isRelatedToDeath(): bool {
        return match($this) {
            self::SuspiciousDeath => true,
            self::Mayor => false,
        };
    }

}
