export default class HTML {

    constructor() {}

    serializeForm(form: ParentNode): object {
        let data: object = {};

        const input_fields = form.querySelectorAll('input');
        for (let i = 0; i < input_fields.length; i++) {
            const node_name = input_fields[i].getAttribute('name')
                ? input_fields[i].getAttribute('name')
                : input_fields[i].getAttribute('id');
            if (node_name)
                data[node_name] = input_fields[i].value;
        }

        return data;
    }

    selectErrorMessage( code: string, messages: object, base: object ) {
        if (!code) code = 'default';
        if (messages && messages.hasOwnProperty(code)) {
            if (typeof messages[code] === 'function')
                this.error( messages[code]() );
            else this.error( messages[code] );
        } else if ( base.hasOwnProperty(code) ) this.error( base[code] );
        else this.error( 'An error occurred. No further details are available.' );
    }

    error(message: string): void {
        alert('ERROR' + "\n" + message);
    }
}