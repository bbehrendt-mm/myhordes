export type VaultRequest = {
    ids: number[],
}

export type VaultEntry = {
    id: number;
}

export type VaultItemEntry = VaultEntry & {
    name: string,
    desc: string,
    icon: string,
    props: string[],
    heavy: boolean,
    extension: boolean,
    deco: number|null,
    watch: number|null,
    cata: boolean,
}

export type VaultBuildingEntry = VaultEntry & {
    id: number,
    name: string,
    desc: string,
    icon: string,
    identifier: string,
    parent: number|null,
    order: number,
    defense: number,
    temp: boolean,
    levels: number,
    rsc: {
        p: number,
        c: number
    }[],
}

export type VaultStorage<V extends VaultEntry> = { [key: number]: V; }
