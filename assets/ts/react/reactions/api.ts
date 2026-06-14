import {TranslationStrings} from "./strings";
import {TranslatableAPI} from "../index";

export type ReactionSet = {
    id: string
    own: number|null,
    me: number,
    reactions: Reaction[]
}

type Reaction = {
    id: number
    count: number
    path: string
}

export class ReactionsAPI extends TranslatableAPI<TranslationStrings> {
    constructor() { super( 'user/reactions' ) }

    public get(id: string): Promise<ReactionSet> {
        return this.fetch.from(`/${id}`)
            .throwResponseOnError()
            .request().get() as Promise<ReactionSet>;
    }

    public remove(id: string): Promise<ReactionSet> {
        return this.fetch.from(`/${id}`)
            .throwResponseOnError()
            .request().delete() as Promise<ReactionSet>;
    }

    public put(id: string, emote: number): Promise<ReactionSet> {
        return this.fetch.from(`/${id}/${emote}`)
            .throwResponseOnError()
            .request().put() as Promise<ReactionSet>;
    }
}
