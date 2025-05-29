import {Fetch} from "../../v2/fetch";
import {MapData, MapRoute} from "./typedef";
import {TranslatableAPI} from "../index";

export type RuntimeMapStrings = {
    zone: string,
    distance: string,
    distanceTown: string,
    distanceSelf: string,
    danger: string[],
    tags: string[],
    mark: string,
    'global': string,
    routes: string,
    map: string,
    close: string,
    position: string,
    horror: string[],
}

export class BeyondMapAPI extends TranslatableAPI<RuntimeMapStrings> {
    constructor() { super( 'game/map', '/index' ) }

    public map(endpoint: string): Promise<MapData> {
        return this.fetch.from(`/${endpoint}/map`)
            .request().secure().get() as Promise<MapData>;
    }

    public routes(endpoint: string): Promise<MapRoute[]> {
        return this.fetch.from(`/${endpoint}/routes`)
            .request().get() as Promise<MapRoute[]>;
    }
}