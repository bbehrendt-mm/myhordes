"use strict";

import {HordesPollCreator} from "../react/poll-creator/Wrapper";
import {Shim} from "../react";

customElements.define('hordes-poll-creator', class HordesPollCreatorElement extends Shim<HordesPollCreator> {

    protected generateInstance(): HordesPollCreator {
        return new HordesPollCreator();
    }

    protected generateProps(): object | null {
        return {};
    }

}, { });
