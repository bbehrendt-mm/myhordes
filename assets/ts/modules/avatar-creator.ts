"use strict";

// This is a react shim
// It's purpose is to map a react node to a custom web component

// Import the actual react code
import {Shim} from "../react";
import {HordesAvatarCreator2} from "../react/avatar-creator/WrapperV2";

// Define web component <hordes-avatar-creator-2 />
customElements.define('hordes-avatar-creator-2', class HordesAvatarCreator2Element extends Shim<HordesAvatarCreator2> {

    protected mountsLazily(): boolean { return true; }

    protected generateProps(): object {
        return {
            maxSize: parseInt(this.dataset.maxSize) ?? 0,
        };
    }

    protected generateInstance(): HordesAvatarCreator2 {
        return new HordesAvatarCreator2();
    }

}, {  });
