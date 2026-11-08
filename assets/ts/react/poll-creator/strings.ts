export type TranslationStrings = {
    create_new_poll: string;
    editor_settings: string;
    current_edit_lang: string;
    help_edit_lang: string;
    help: string;
    poll_data: string;
    poll_title: string;
    poll_start: string;
    poll_end: string;
    poll_premature: string;
    poll_premature_start: string;
    poll_multi: string;
    short_description: string;
    poll_description: string;
    answers: string;
    answer_title: string;
    answer_desc: string;
    add_answer: string;
    finish_editing: string;
    submit_poll: string;
    confirm: string;
    missing_fields: string;
    min_answers_error: string;
    depends_on: string;
    no_dependency: string;
    success_notice: string;
};

export type PollDefaults = {
    startDate: string;
    endDate: string;
    minEndDate: string;
};

export type ResponseIndex = {
    langsCodes: string[];
    userLang: string;
    emotes: { [tag: string]: string };
    flags: { [lang: string]: string };
    icons: {
        arrowUp: string;
        arrowDown: string;
        delete: string;
    };
    defaults: PollDefaults;
    strings: TranslationStrings;
};
