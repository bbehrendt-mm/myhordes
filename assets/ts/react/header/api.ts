import {TranslationStrings} from "./strings";
import {Fetch} from "../../v2/fetch";
import {TranslatableAPI} from "../index";

interface AppListResponse {
    apps?: ExternalApp[];
}

export type ExternalApp = {
    id: number
    name: string
    icon: string | null
    wiki: boolean
    testing: boolean
    auth: string
    pk: string | null
    maintenance: boolean
    url: string
    contact: {
        label: string | null
        uri: string | null
    };
    dev: {
        sk: string
        url: string
    } | null
}

interface ModListResponse {
    cat?: string,
    links?: ModLink[];
}

export type ModLink = {
    name: string
    url: string
}

export type TownClock = {
    id: number | null;
    type: string | null;
    desc: string;
    day: number;
    timestamp: number;
    attack: number;
    offset: number;
    show: boolean;
}


export class HeaderAPI extends TranslatableAPI<TranslationStrings> {
    constructor() { super( 'user/commons' ) }

    public apps(): Promise<AppListResponse> {
        return this.fetch.from('/apps')
            .throwResponseOnError().request().get() as Promise<AppListResponse>;
    }

    public app(app: number): Promise<ExternalApp> {
        return this.fetch.from(`/apps/${app}`)
            .throwResponseOnError().request().get().then(v => v.app) as Promise<ExternalApp>;
    }

    public updateApp(app: number, settings: object): Promise<boolean> {
        return this.fetch.from(`/apps/${app}`)
            .request().patch(settings).then(v => v.success) as Promise<boolean>;
    }

    public regenerateAppSK(app: number): Promise<string> {
        return this.fetch.from(`/apps/${app}`)
            .request().put().then(v => v.sk) as Promise<string>;
    }

    public mods(): Promise<ModListResponse> {
        return this.fetch.from('/mods')
            .throwResponseOnError().request().get() as Promise<ModListResponse>;
    }

    public clock(): Promise<TownClock> {
        return this.fetch.from('/clock')
            .throwResponseOnError().request().get() as Promise<TownClock>;
    }

}