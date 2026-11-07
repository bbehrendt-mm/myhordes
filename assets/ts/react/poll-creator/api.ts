import {ResponseIndex} from "./strings";
import {TranslatableAPI} from "../index";

export type PollAnswerPayload = {
    title: { [lang: string]: string };
    desc: { [lang: string]: string };
};

export type PollPayload = {
    title: { [lang: string]: string };
    preview: { [lang: string]: string };
    desc: { [lang: string]: string };
    start: string;
    end: string;
    premature: boolean;
    date_premature: string;
    multiple: boolean;
    answers: PollAnswerPayload[];
};

export type PollCreateResponse = {
    success: boolean;
    url: string;
};

export class PollCreatorAPI extends TranslatableAPI<ResponseIndex> {
    constructor() {
        super('admin/polls');
    }

    public createPoll(data: PollPayload): Promise<PollCreateResponse> {
        return this.fetch.from('')
            .request().post(data) as Promise<PollCreateResponse>;
    }
}
