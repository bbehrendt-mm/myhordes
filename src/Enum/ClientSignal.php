<?php

namespace App\Enum;

enum ClientSignal: string
{
    case InventoryUpdated = 'inventory-changed';
    case InventoryFloorUpdated = 'inventory-changed-b';
    case InventoryHeadlessUpdate = 'inventory-changed-headless';
    case StatusUpdated = 'status-changed';
    case LogUpdated = 'log-changed';
}
