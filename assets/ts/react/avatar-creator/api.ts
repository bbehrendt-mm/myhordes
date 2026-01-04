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
    id: string,
    source?: boolean,
    default: MediaSet|null,
    round: MediaSet|null,
    small: MediaSet|null,
}

export type MediaGroupWorkCopy =  {
    source: string,
    default: PixelCrop|null,
    round: PixelCrop|null,
    small: PixelCrop|null,
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

    public getMediaProps(id: string): Promise<MediaGroupWorkCopy> {
        return this.fetch.from(`/media/${id}/props`)
            .request().get() as Promise<MediaGroupWorkCopy>;
    }

    public async deleteMedia(id: string = null): Promise<boolean> {
        await this.fetch.from(id === null ? '/media' : `/media/${id}`)
            .request().delete();
        return true;
    }

    public async promoteMedia(id: string): Promise<boolean> {
        await this.fetch.from(`/media/${id}`)
            .request().post();
        return true;
    }

    public async uploadMedia(
        mime: string,
        data: string|null,
        cropDefault = null, cropSmall = null, cropRound = null,
        format = null, pending = false
    ): Promise<boolean | number> {
        await this.fetch.from('/media').bodyDeterminesSuccess(true).withErrorMessages()
            .request().put({
                mime, data, format, pending, crop: {
                    default: cropDefault,
                    small: cropSmall,
                    round: cropRound
                }
            });
        return true;
    }

    public async patchMedia(
        id: string,
        cropDefault = null, cropSmall = null, cropRound = null
    ): Promise<boolean | number> {
        await this.fetch.from(`/media/${id}`).bodyDeterminesSuccess(true).withErrorMessages()
            .request().patch({
                crop: {
                    default: cropDefault,
                    small: cropSmall,
                    round: cropRound
                }
            });
        return true;
    }

}
