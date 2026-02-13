import {TranslationStrings} from "./strings";
import {Fetch} from "../../v2/fetch";
import {TranslatableAPI} from "../index";

export type ResponseList = {
    subscriptions: Array<NotificationSubscription>,
}

export type ResponseSingle = {
    subscription: NotificationSubscription,
}

export type ResponseTest = {
    status: number,
    success: boolean,
    expired: boolean
}

export type NotificationSubscription = {
    id: string,
    hash: string,
    desc: string|null,
    expired: boolean
}

export type Setting = {
    'type': string,
    delay?: boolean,
    text: string,
    help: string
}

export class NotificationManagerAPI extends TranslatableAPI<TranslationStrings> {
    constructor() { super( 'user/settings/notifications' ) }

    public list( type: string ): Promise<ResponseList> {
        return this.fetch.from(`/${type}`)
            .request().get() as Promise<ResponseList>;
    }

    public put( type: string, payload: object, desc: string|null = null ): Promise<ResponseSingle> {
        return this.fetch.from(`/${type}`).withErrorMessages().throwResponseOnError()
            .request().put({ desc,payload }) as Promise<ResponseSingle>;
    }

    public delete( type: string, id: string ): Promise<boolean> {
        return this.fetch.from(`/${type}/${id}`)
            .request().delete().then(() => true).catch(e=> {
                console.error(e);
                return false;
            });
    }

    public edit( type: string, id: string, desc: string ): Promise<ResponseSingle> {
        return this.fetch.from(`/${type}/${id}`)
            .request().patch({desc}) as Promise<ResponseSingle>;
    }

    public test( type: string, id: string ): Promise<ResponseTest> {
        return this.fetch.from(`/${type}/${id}/test`)
            .request().post() as Promise<ResponseTest>;
    }
}