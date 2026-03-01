import {Fetch} from "../../v2/fetch";
import {TranslationStrings} from "./strings";
import {TranslatableAPI} from "../index";

export type Item = {
    /** Item ID */
    i: number,
    /** Item Prototype id */
    p: number,
    /** Item count */
    c: number,
    /** Watch attack points (tooltip) */
    w: string|null,
    /** is Broken */
    b: boolean,
    /** is Hidden */
    h: boolean,
    /** is Essential */
    e: boolean,
    /** Sort indexes */
    s: number[],
}

export type InventoryMods = {
    has_drunk?: boolean,
    cata?: boolean,
}

export type InventoryCategory = {
    id: number,
    items: Item[],
}

export type InventoryResponse = InventoryBagData | InventoryBankData | InventoryResourceData;

export type InventoryBaseData = {
    bank: boolean,
    rsc: boolean
    mods: InventoryMods
}

export type InventoryBagData = InventoryBaseData & {
    bank: false
    rsc: false
    size: number|null,
    heavy: number|null,
    items: Item[],
}

export type InventoryBankData = InventoryBaseData & {
    bank: true,
    rsc: false
    categories: InventoryCategory[]
}

export type InventoryResourceData = InventoryBaseData & {
    bank: false,
    rsc: true
    items: {p: number, c: number}[]
}


export type TransportResponse = {
    success: boolean,
    messages?: string,
    errors?: number[],
    source?: InventoryResponse,
    target?: InventoryResponse,
    reload?: boolean,
}

export class InventoryAPI  extends TranslatableAPI<TranslationStrings> {
    constructor() { super( 'game/inventory', '/index' ) }

    public inventory(id: number, rsc: number[] = []): Promise<InventoryResponse> {
        return this.fetch.from(`/${id}`, rsc.length > 0 ? {rsc: rsc.join(',')} : {})
            .request().get() as Promise<InventoryResponse>;
    }

    public transfer(id: number|null, from: number, to: number, d: string, mod: string = null): Promise<TransportResponse> {
        return this.fetch.from(id !== null ? `/${from}/${id}` : `/${from}`)
            .request().patch({d,mod,to}) as Promise<TransportResponse>;
    }
}
