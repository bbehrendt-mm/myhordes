type Field = {
    title: string;
    help: string[];
}

export type TranslationStrings = {
    apps: {
        list: {
            headline: string;
            description: string;
            maintenance: string;
            testMode: string;
            sk: string;
            icon: string;
            no_icon: string;
        },
        details: {
            notice: string;
            warning_pk_1: string;
            warning_pk_2: string;
            warning_pw_1: string;
            warning_pw_2: string;
            contact: string;
            notice_secure_1: string;
            notice_secure_2: string;
            warning_maintenance_1: string;
            warning_maintenance_2: string;
            confirm: string;
            cancel: string;

            dev: {
                help: string;
                save: string;
                headline: string;
                description: string;
                fields: {
                    email: Field
                    url: Field
                    dev_url: Field
                    sk: Field
                }
            }

        }
    }
}