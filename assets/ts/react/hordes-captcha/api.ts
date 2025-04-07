import {TranslationStrings} from "./strings";
import {Fetch} from "../../v2/fetch";

export type ResponseIndex = {
	strings: TranslationStrings,
	submit: string,
    captcha: string
}

export type ResponseHordesCaptcha = {
    success: boolean
}

export class HordesCaptchaAPI {

    private fetch: Fetch;

    constructor() {
        this.fetch = new Fetch( 'user/captcha/hordes' );
    }

    public index(): Promise<ResponseIndex> {
        return this.fetch.from('/')
            .request().withCache().get() as Promise<ResponseIndex>;
    }

    public submit(data: object): Promise<ResponseHordesCaptcha> {
        return this.fetch.from('/').withErrorMessages().throwResponseOnError()
            .request().post(data) as Promise<ResponseHordesCaptcha>;
    }

}