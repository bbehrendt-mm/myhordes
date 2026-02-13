import {TranslationStrings} from "./strings";
import {TranslatableAPI} from "../index";


export class CatapultAPI extends TranslatableAPI<TranslationStrings> {
    constructor() { super( 'game/town/facilities/catapult' ) }

    public catapult(item: number, x: number, y: number): Promise<{success: boolean, message?: string}> {
        return this.fetch.from(`/${item}`)
            .request().post({x,y}) as Promise<{success: boolean, message?: string}>;
    }

}
