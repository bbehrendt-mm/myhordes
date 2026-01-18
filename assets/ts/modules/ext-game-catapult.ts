"use strict";

// This is a react shim
// It's purpose is to map a react node to a custom web component

// Import the actual react code
import {PersistentShim} from "../react";
import {HordesCatapult} from "../react/catapult/Wrapper";

customElements.define('hordes-catapult', class HordesCatapultElement extends PersistentShim<HordesCatapult> {
    protected generateInstance(): HordesCatapult {
        return new HordesCatapult();
    }

    protected generateProps(): object {
        return {
            map: JSON.parse(this.dataset.map),
            inventory: parseInt(this.dataset.inventoryId ?? '0')
        };
    }

    protected static observedAttributeNames() {
        return ['data-map', 'data-inventory-id'];
    }

}, {  });
