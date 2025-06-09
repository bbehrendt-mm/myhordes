import {TranslationStrings} from "./strings";
import {TranslatableAPI} from "../index";

type Decal = {
    i: string;
    h: number,
    w: number,
    x: number,
    y: number,
}

export type AssetResponse = {
    tiles: {
        exit: string;
        room: string;
        '1' : string;
        '2' : string;
        '3' : string;
        '4' : string;
        '5' : string;
        '6' : string;
        '7' : string;
        '8' : string;
        '9' : string;
        '10': string;
        '11': string;
        '12': string;
        '13': string;
        '14': string;
        '15': string;
    };
    doors: {
        open_up: {
            [key: number]: string;
        };
        open_down: {
            [key: number]: string;
        };
        open: {
            [key: number]: string;
        };
        closed: {
            [key: number]: string;
        };
    };
    decals: { [key: string]: Decal }
};

export type ExplorationStatus = {
    paused: boolean;
    exit: number;
    shifted: boolean | null;
    activity: number;
    floor: number;
}

export type ExplorationTileset = {
    tile: number | null;
    door: number | null;
    elev: number;
    deco: number | null;
}

export type ZoneResponse = {
    status: ExplorationStatus;
    tileset: ExplorationTileset;
}


export class RuinExplorationAPI extends TranslatableAPI<TranslationStrings> {

    constructor() { super('game/beyond/e-ruin') }

    public assets(theme: string): Promise<AssetResponse> {
        return this.fetch.from(`/assets/${theme}`)
            .request().get() as Promise<AssetResponse>;
    }

    public zone(): Promise<ZoneResponse> {
        return this.fetch.from(`/explore`)
            .request().get() as Promise<ZoneResponse>;
    }

    public move(dx: number, dy: number, dz: number = 0): Promise<ZoneResponse> {
        return this.fetch.from(`/explore`)
            .request().patch({dx,dy,dz}) as Promise<ZoneResponse>;
    }
}