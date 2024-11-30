import {TranslationStrings} from "./strings";
import {Fetch} from "../../v2/fetch";

export interface App {
    i: number
    w: boolean
    s: string
    n: string
    t: boolean
    p: string,
    u: string
}

interface AppResponse {
    apps: App[],
}

export interface Tool {
    n: string,
    u: string,
}

interface ToolResponse {
    tools: Tool[],
}

export interface ClockResponse {
    town: string|null,
    hc: boolean,
    offset: number,
    attack: number,
}

export class HeaderAPI {

    private fetch: Fetch;

    constructor() {
        this.fetch = new Fetch( 'user/header' );
    }

    public index(): Promise<TranslationStrings> {
        return this.fetch.from('/')
            .request().get() as Promise<TranslationStrings>;
    }

    public apps(): Promise<AppResponse> {
        return this.fetch.from('/apps')
            .request().get() as Promise<AppResponse>;
    }

    public clock(): Promise<ClockResponse> {
        return this.fetch.from('/clock')
            .request().get() as Promise<ClockResponse>;
    }

    public tools(): Promise<ToolResponse> {
        return this.fetch.from('/tools')
            .request().get() as Promise<ToolResponse>;
    }

}