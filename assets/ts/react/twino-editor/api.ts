import {TranslationStrings} from "./strings";
import {Fetch} from "../../v2/fetch";
import {TranslatableAPI} from "../index";

export type EmoteListResponse = {
    result: {[index:string]: Emote}
    help?: string
}

export type EmoteResponse = EmoteListResponse & {
    mock: {[index:string]: string},
    snippets: null|{
        base: string,
        list: {[index:string]: Snippet}
    },
}

export type Emote = {
    tag: string,
    path: string,
    orderIndex: number,
    url: string,
    groups?: string[]
}

export type Snippet = {
    key: string,
    value: string,
    lang: string,
    role: string,
}

export class TwinoEditorAPI extends TranslatableAPI<TranslationStrings> {
    constructor() { super( 'user/soul/editor' ) }

    public emotes( user: number|null, context: string = 'common' ): Promise<EmoteResponse> {
        return this.fetch.from(`/${user ?? 'me'}/unlocks/${context}/emotes`)
            .request().get() as Promise<EmoteResponse>;
    }

    public games( user: number|null, context: string = 'common' ): Promise<EmoteListResponse> {
        return this.fetch.from(`/${user ?? 'me'}/unlocks/${context}/games`)
            .request().get() as Promise<EmoteListResponse>;
    }

    public ressources( user: number|null, context: string = 'common' ): Promise<EmoteListResponse> {
        return this.fetch.from(`/${user ?? 'me'}/unlocks/${context}/ressources`)
            .request().get() as Promise<EmoteListResponse>;
    }

    public rp( user: number|null, context: string = 'common' ): Promise<EmoteListResponse> {
        return this.fetch.from(`/${user ?? 'me'}/unlocks/${context}/rp`)
            .request().get() as Promise<EmoteListResponse>;
    }
}