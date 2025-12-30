import {TranslationStrings} from "./strings";
import {AjaxV1Response, Fetch} from "../../v2/fetch";
import {TranslatableAPI} from "../index";

export type ResponseIndex = {
    strings: TranslationStrings,
}

export type Media = {
    id: string
    url: string,
    format: string,
    size: number,
    x: number,
    y: number,
    f: number
}

export type MediaSet  = Media & {
    conversions: Media[]
}

export type MediaGroup =  {
    default: MediaSet|null,
    round: MediaSet|null,
    small: MediaSet|null,
}

export type ResponseMedia = {
    avatar: MediaGroup|null,
    pending: MediaGroup|null,
    history: MediaGroup[]
}

export type PixelCrop = {
    x: number,
    y: number,
    width: number,
    height: number
}

export class AvatarCreatorAPI extends TranslatableAPI<ResponseIndex> {

    constructor() { super('user/settings/avatar', '/index') }

    public getMedia(): Promise<ResponseMedia> {
        return this.fetch.from('/media')
            .request().get() as Promise<ResponseMedia>;
    }

    public deleteMedia(): Promise<boolean> {
        return this.fetch.from('/media')
            .request().delete().then(() => true);
    }

    public uploadMedia(mime,data,cropDefault = null,cropSmall = null, cropRound = null, format=null, pending = false): Promise<boolean|number> {
        return this.fetch.from('/media').bodyDeterminesSuccess(true).withErrorMessages()
            .request().put({mime,data,format,pending,crop: {
                default: cropDefault,
                small: cropSmall,
                round: cropRound
            }}).then(() => true);
    }

}
