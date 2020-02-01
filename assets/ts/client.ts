export default class Client {

    constructor() {}

    private static key( name: string, group: string|null ): string {
        return 'myh.' + (group === null ? 'default' : group) + '.' + name;
    }

    private static get_var(storage: Storage, name: string, group: string|null = null, default_value: any = null ): any | null {
        const item = storage.getItem( this.key( name, group ) );
        if (item === null) return default_value;
        try {
            return JSON.parse(item);
        } catch (e) {
            return item;
        }
    }

    private static set_var( storage: Storage, name: string, group: string|null, value: any ): boolean {
        try {
            if (value === null)
                storage.removeItem( this.key( name, group ) );
            storage.setItem( this.key( name, group ), value );
            return true;
        } catch (e) {
            return false;
        }
    }

    set( name: string, group: string|null, value: any, session_only: boolean ): boolean {
        return Client.set_var( session_only ? window.sessionStorage : window.localStorage, name, group, value );
    }

    get( name: string, group: string|null = null, default_value: any = null ): any {
        return Client.get_var( window.sessionStorage, name, group, Client.get_var( localStorage, name, group, default_value ) );
    }
}