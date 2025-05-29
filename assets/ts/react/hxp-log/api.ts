import {TranslationStrings} from "./strings";
import {Fetch} from "../../v2/fetch";
import {TranslatableAPI} from "../index";

export interface LogEntry {
    id: number
    timestamp: number,
    value: number,
    text: string,
    'type': number,
    reset: boolean,
    outdated: boolean,
    past: string|null,
}

export interface LogEntryResponse {
    entries: LogEntry[],
    additional: boolean
}


export class HxpLogAPI extends TranslatableAPI<TranslationStrings> {
    constructor() { super( 'user/soul/skills/hxp', '/index' ) }

    public logs(after: number = null, focus: number = null): Promise<LogEntryResponse> {
        return this.fetch.from('/', { after, focus })
            .request().get() as Promise<LogEntryResponse>;
    }

}