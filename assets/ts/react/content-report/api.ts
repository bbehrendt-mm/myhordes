import {TranslationStrings} from "./strings";
import {Fetch} from "../../v2/fetch";
import {API} from "../index";

export type ResponseIndex = {
    strings: TranslationStrings
}

export type ResponseReport = {
    message?: string,
}


export class ContentReportAPI extends API {

    constructor() { super( 'user/complaint' ) }

    public index(type: string): Promise<ResponseIndex> {
        return this.fetch.from(`/${type}`)
            .request().withCache().get() as Promise<ResponseIndex>;
    }

    public report(type: string, principal: number, form: object): Promise<ResponseReport> {
        return this.fetch.from(`/${type}/${principal}`).withErrorMessages().throwResponseOnError()
            .request().put(form) as Promise<ResponseReport>;
    }

}