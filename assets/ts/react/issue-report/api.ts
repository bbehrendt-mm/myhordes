import {TranslationStrings} from "./strings";
import {Fetch} from "../../v2/fetch";
import {TranslatableAPI} from "../index";

export type ResponseIndex = {
    strings: TranslationStrings
}

export type ResponseReport = {
    success: boolean
}

export type FileUpload = {
    file: string,
    ext: string,
    content: string
}

export class IssueReportAPI extends TranslatableAPI<ResponseIndex> {
    constructor() { super( 'user/issues' ) }


    public report(data: object, files: FileUpload[] = []): Promise<ResponseReport> {
        return this.fetch.from('/').withErrorMessages().throwResponseOnError()
            .request().put({
                ...data,
                issue_attachments: files
            }) as Promise<ResponseReport>;
    }

}