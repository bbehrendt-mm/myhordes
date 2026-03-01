import * as React from "react";
import {useContext, useEffect, useRef, useState} from "react";
import {
    InventoryAPI,
    InventoryBagData,
    InventoryBankData,
    InventoryMods,
    InventoryResponse,
    Item,
    TransportResponse
} from "./api";
import {Tooltip} from "../misc/Tooltip";
import {Const, Global} from "../../defaults";
import {TranslationStrings} from "./strings";
import {useVault} from "../../v2/client-modules/Vault";
import {VaultItemEntry, VaultStorage} from "../../v2/typedef/vault_td";
import {BaseMounter} from "../index";
import {emitSignal, useBroadcastSignal, useSignal} from "../../v2/client-modules/Signal";
import {ServerInducedSignalProps} from "../../v2/fetch";
import {ItemTooltip, useTranslations, useSharedWorkerMessages} from "../utils";
import {randomUUIDv4} from "../../shims";

declare var $: Global;
declare var c: Const;

interface TutorialStateConfig {
    tutorial: number,
    stage: string,
}

interface TutorialConfig {
    from: TutorialStateConfig,
    to: TutorialStateConfig|null,
    restrict: "a"|"b"|null,
}

type InventoryType = keyof TranslationStrings['type'];

interface mountProps {
    etag: string,
    locked: boolean|string,

    inventoryAId: number,
    inventoryAType: InventoryType | 'none',
    inventoryALabel: string|null,

    inventoryBId: number,
    inventoryBType: InventoryType | 'none',
    inventoryBLabel: string|null,

    reload: string|null,
    reset: boolean,

    steal: boolean,
    log: boolean,
    uncloak: boolean,
    hide: number|null,

    tutorial?: TutorialConfig|null,

    onItemClick?: (i: Item) => void,
    filterEnabledItems?: (i: Item, v: VaultItemEntry|null) => boolean,
}

interface passiveMountProps {
    parent: HTMLElement,
    id: number,
    max: number,
    link?: string,
}

interface escortMountProps {
    parent: HTMLElement,
    etag: string,
    rucksackId: number,
    floorId: number,
    reload: string|null,
    name: string,
}

interface standaloneItemMountProps {
    item: number,
    extra?: string|null,
    line?: boolean,
    inline?: boolean,
    labelClass?: string,
}

interface InventoryBagLoadedSignalProps {
    id: number,
    inventory: InventoryResponse,
    element: HTMLElement
}

interface InventoryTransferSignalProps {
    from: number,
    to: number,
    direction: string,
    item: number|null,
    response: TransportResponse,
}

export class HordesInventory extends BaseMounter<mountProps>{

    protected item_cache: {[key:number]: InventoryBagData} = {}

    protected render(props: mountProps): React.ReactNode {
        return <HordesInventoryWrapper
            {...props}
            setCache={(id: number, items: InventoryBagData|null): void => { this.item_cache[id] = items; } }
            parent={this.parent}
        />;
    }

    public cachedBagData(id: number) {
        return this.item_cache[id] ?? null;
    }
}

export class HordesPassiveInventory extends BaseMounter<passiveMountProps>{
    protected render(props: passiveMountProps): React.ReactNode {
        return <HordesPassiveInventoryWrapper {...props} parent={this.parent} />;
    }
}

export class HordesStandaloneItem extends BaseMounter<standaloneItemMountProps>{
    protected render(props: standaloneItemMountProps): React.ReactNode {
        return <HordesStandaloneItemWrapper {...props} />;
    }
}

export class HordesEscortInventory extends BaseMounter<escortMountProps>{
    protected render(props: escortMountProps): React.ReactNode {
        return <HordesEscortInventoryWrapper {...props} parent={this.parent} />;
    }
}

interface InventoryGlobal {
    api: InventoryAPI,
    strings: TranslationStrings|null
}

export const Globals = React.createContext<InventoryGlobal>(null);

function sort(a: Item, b: Item): number {
    return a.s
        .map((v,i) => [v,b.s[i]])
        .reduce((carry, [a,b]) => carry === 0 ? b-a : carry, 0 );
}

function getBagSorter(vaultData?: VaultStorage<VaultItemEntry>) {
    const BAG_ITEM_PRIORITY = [
        ['essential', (a: Item) => a.e && !vaultData?.[a.p]?.heavy],
        ['essential+heavy', (a: Item) => a.e && vaultData?.[a.p]?.heavy],
        ['carrier+heavy', (a: Item) => vaultData?.[a.p]?.heavy && vaultData?.[a.p]?.extension],
        ['heavy', (a: Item) => vaultData?.[a.p]?.heavy],
        // ['empty-heavy'],
        ['carrier', (a: Item) => vaultData?.[a.p]?.extension],
        ['regular', (a: Item) => !!vaultData?.[a.p]],
        ['loading', (a: Item) => !vaultData?.[a.p]],
        // ['empty-regular'],
    ] as const satisfies [string, (a: Item) => boolean][];

    return {
        BAG_ITEM_PRIORITY,
        bagSort: (a: Item, b: Item) => {
            const va = vaultData?.[a.p];
            const vb = vaultData?.[b.p];

            // Loaded items first
            if (!va || !vb) return a ? 1 : 0;

            for (const [, check] of BAG_ITEM_PRIORITY) {
                if (check(a) && !check(b)) return -1;
                if (check(b) && !check(a)) return 1;
            }

            // Default
            return sort(a, b);
        }
    }
}

function processSlots(items: Item[], vaultData?: VaultStorage<VaultItemEntry>, inventorySize: number = 0, inventoryHeavySlots: number = 0): SlotProps[] {
    const slots: SlotProps[] = [];

    const {BAG_ITEM_PRIORITY, bagSort} = getBagSorter(vaultData);
    items.sort(bagSort);

    const getItemStep = (a: Item) => BAG_ITEM_PRIORITY.find(([, check]) => check(a))?.[0] ?? 'loading';

    let heavyUsed = 0;
    let currentStep: typeof BAG_ITEM_PRIORITY[number][0] = BAG_ITEM_PRIORITY[0][0];

    const lightItems = items.reduce((total, item) => total + (!vaultData?.[item.p]?.heavy ? 1 : 0), 0);
    const inventoryOverflow = Math.max(0, items.length - inventorySize);
    const lightItemsRoom = (inventorySize + inventoryOverflow) - inventoryHeavySlots;

    // Light items can use heavy item slots
    let heavyUsedByLight = 0;
    if (lightItems > lightItemsRoom) {
        heavyUsedByLight = lightItems - lightItemsRoom;
        heavyUsed += heavyUsedByLight;
    }

    for (let i = 0; (i < inventorySize) || items.length > 0; i++) {
        let currentItem = items.shift();
        let vault = currentItem ? vaultData?.[currentItem.p] : undefined;
        const step = currentItem ? getItemStep(currentItem) : currentStep;
        currentStep = step;

        // Exit essential step if we don't have any more items
        if (step === 'essential' && !currentItem) {
            if (heavyUsed < inventoryHeavySlots) {
                currentStep = 'carrier+heavy';
            } else {
                currentStep = 'carrier';
            }
        }

        // Exit heavy steps if we don't have any more items and heavy slots
        if ((step === 'carrier+heavy' || step === 'heavy') && !currentItem && heavyUsed >= inventoryHeavySlots) {
            currentStep = 'carrier';
        }

        let heavy = false;
        switch(currentStep) {
            case 'essential':
                break;
            case 'essential+heavy':
                heavyUsed++;
                break;
            case 'carrier+heavy':
            case 'heavy':
                heavyUsed++;
                heavy = true;
                break;
            case 'carrier':
            case 'regular':
            case 'loading':
                // Heavy items are finished : Flush out extra empty heavy slots
                while(heavyUsed < inventoryHeavySlots) {
                    heavyUsed++;
                    i++;
                    slots.push({
                        free: true,
                        heavy: true,
                    });
                }
                break;
        }

        const isHeavySlot = (heavy && !(currentItem && currentItem.e) && (!vault || vault.heavy)) || (currentItem && !currentItem.e && (heavyUsedByLight && heavyUsedByLight-- > 0));

        if (currentItem && isHeavySlot && !heavy) {
            // Rotate the light items to have the "last" items use the heavy slots (and not the first ones)
            items.unshift(currentItem);
            currentItem = items.pop();
            vault = currentItem ? vaultData?.[currentItem.p] : undefined;
        }

        const data: SlotProps = {
            free: !currentItem,
            heavy: isHeavySlot,
            over: heavy && (heavyUsed > inventoryHeavySlots),
        };

        if (currentItem) {
            data.itemInfo = {
                item: currentItem,
                vault
            };
        }

        slots.push(data);

        if (!currentItem && !items.length) {
            // Flush out extra empty slots
            while(i < (inventorySize - 1)) {
                i++;
                slots.push({
                    free: true,
                    heavy: heavyUsed < inventoryHeavySlots,
                });
                if (heavyUsed < inventoryHeavySlots) {
                    heavyUsed++;
                }
            }
        }
    }

    return slots;
}

const extractAllItems = (data: InventoryBankData | InventoryBagData) => {
    return data.bank
        ? (data as InventoryBankData).categories.map(c => c.items).reduce((c,v) => [...c,...v], [])
        : (data as InventoryBagData).items
}

const commonInventoryResponseHandler = (s: TransportResponse, d: string, t: TutorialConfig|null = null, log: boolean = false, url: string = null, reset: boolean = false )=> {
    // Display messages
    if (s.messages?.length > 0)
        $.html.message(s.success ? 'notice' : 'error', s.messages);
    else if (s.errors?.length > 0)
        $.html.error( s.errors.map( e => c.errors[e] ?? null ).join('<hr/>') )

    // If a tutorial dataset was attached to this element, apply it
    if (s.success && t && (!t.restrict || ( t.restrict === 'a' && d !== 'up' ) || ( t.restrict === 'b' && d === 'up' ))) {
        if (t.to === null) $.html.conditionalFinishTutorialStage( t.from.tutorial, t.from.stage, true );
        else $.html.conditionalSetTutorialStage( t.from.tutorial, t.from.stage, t.to.tutorial, t.to.stage );
    }

    // If the log mode is enabled, update all surrounding logs
    if (!s.reload && log)
        document.querySelectorAll('hordes-log[data-etag]').forEach((logElem) => {
            const [et_static, et_custom = '0'] = (logElem as HTMLElement).dataset.etag.split('-');
            (logElem as HTMLElement).dataset.etag = `${et_static}-${parseInt(et_custom)+1}`;
        });

    if (s.reload && url) $.ajax.load(null, url);
    else if (s.reload) window.location.reload();
    else if (reset) {
        document.querySelectorAll('[data-proxy-template][data-deferred="1"]').forEach(e => {
            const proxy = document.getElementById((e as HTMLElement).dataset.proxyTemplate);
            if (!proxy) return;

            proxy.remove();
            (e as HTMLElement).style.display = null;
            (e as HTMLElement).dataset.deferred = '0';
            (e as HTMLElement).setAttribute('id', (e as HTMLElement).dataset.proxyTemplate);
        });
    }
}

type SingleInventoryProps = {
    id: number,
    label?: string,
    type?: InventoryType,
    locked?: boolean,
    parent: HTMLElement,
    onItemClick?: (i: Item) => void,
    filterEnabledItems?: (i: Item, v: VaultItemEntry|null) => boolean,
}

export const SingleInventory = (props: SingleInventoryProps) => {

    const item_cache = useRef<{[key:number]: InventoryBagData}>({});
    const etag = useRef<string>(randomUUIDv4());

    return <HordesInventoryWrapper
        className="display-plain"

        inventoryAId={props.id}
        inventoryAType={props.type ?? 'rucksack'}
        etag={etag.current}
        locked={props.locked ?? false}
        inventoryALabel={props.label ?? null}
        inventoryBId={null} inventoryBType={'none'} inventoryBLabel={''}

        reload={null} reset={false} steal={false} log={false} uncloak={false}
        hide={0} setCache={(id: number, items: InventoryBagData|null): void => { item_cache.current[id] = items; } }
        parent={props.parent}
        onItemClick={props.onItemClick}
        filterEnabledItems={props.filterEnabledItems}
    />

}

const HordesInventoryWrapper = (props: mountProps & {className?: string} &
    {setCache: (i:number,items:InventoryBagData|null) => void} &
    {parent: HTMLElement}
) => {
    const api = useRef( new InventoryAPI() );

    const [internalETag, setInternalETag] = useState(0);
    const [internalETagB, setInternalETagB] = useState(0);

    const strings = useTranslations( api.current );
    const [loading, setLoading] = useState<boolean>( false );

    const [theftMode, setTheftMode] = useState<boolean>( false );

    const [inventoryA, setInventoryA] = useState<InventoryResponse>(null);
    const [inventoryB, setInventoryB] = useState<InventoryResponse>(null);

    useEffect(() => {
        if (!props.inventoryAId || props.inventoryAType === 'none') return;
        api.current.inventory( props.inventoryAId ).then(r => {
            setInventoryA(r);
            setCache(props.inventoryAId, r)
        });
    }, [props.inventoryAId, props.inventoryAType, props.etag, internalETag]);

    useEffect(() => {
        if (!props.inventoryBId || props.inventoryBType === 'none') return;
        api.current.inventory( props.inventoryBId ).then(r => {
            setInventoryB(r);
            setCache(props.inventoryBId, r)
        });
    }, [props.inventoryBId, props.inventoryBType, props.etag, internalETag, internalETagB]);

    useSignal<ServerInducedSignalProps>(
        'inventory-changed',
        () => setInternalETag(e => e+1)
    )

    useSignal<ServerInducedSignalProps>(
        'inventory-changed-b',
        () => setInternalETagB(e => e+1)
    )

    useSignal<InventoryBagLoadedSignalProps>(
        'inventory-bag-loaded',
        ({id,inventory,element}) => {
            if (element === props.parent || (id !== props.inventoryAId && id !== props.inventoryBId))
                return;

            setCache(id, inventory);

            if (props.inventoryAId === id) setInventoryA(inventory);
            else if (props.inventoryBId === id) setInventoryB(inventory);
        },
        [props.inventoryAId, props.inventoryBId]
    )

    const setCache = (id: number, inventory: InventoryResponse) => {
        props.setCache(id,inventory.bank ? null : (inventory as InventoryBagData));
        if (!inventory.bank)
            emitSignal<InventoryBagLoadedSignalProps>('inventory-bag-loaded', {id,inventory,element: props.parent})
    }

    const manageTransfer = (item: number|null, from: number, to: number, direction: string, mod: string = null) =>{
        setLoading(true);

        if (theftMode) mod = 'theft';

        api.current.transfer( item, from, to, direction, mod ).then(s => {
            emitSignal<InventoryTransferSignalProps>('item-transfer', { item, from, to, direction, response: s })

            // Update individual inventories
            const toA = (direction === 'down' || direction === 'down-all') ? s.source : s.target;
            const toB = (direction === 'down' || direction === 'down-all') ? s.target : s.source;
            if (toA) {
                setInventoryA(toA);
                setCache(props.inventoryAId, toA)
            }
            if (toB) {
                setInventoryB(toB);
                setCache(props.inventoryBId, toB)
            }

            commonInventoryResponseHandler(s, direction, props.tutorial, props.log, props.reload, props.reset);

            setLoading(false);
            setTheftMode(false);
        }).catch(() => setLoading(false))
    }

    const handleTransfer = (from: number|null|undefined, to: number|null|undefined, direction: string) => {
        if (props.onItemClick)
            return props.onItemClick;
        else if (from && to)
            return (i:Item) => manageTransfer(i.i, from, to, direction);
        else return () => {};
    }

    const lock_a = props.locked === true || props.locked === 'a';
    const lock_b = props.locked === true || props.locked === 'b';

    return <Globals.Provider value={{api: api.current, strings}}>
        { props.inventoryAType !== 'none' && <>
            <SwitchInventory id={props.inventoryAId} type={props.inventoryAType} label={props.inventoryALabel} inventory={inventoryA} locked={lock_a || loading || theftMode} className={props.className} enableItemCallback={props.filterEnabledItems} onItemClick={handleTransfer( props.inventoryAId, props.inventoryBId, 'down' )} />
        </> }

        { props.inventoryAType !== 'none' && props.uncloak && strings && !props.locked && <div className="note"><b>{strings.global.warning}</b>:&nbsp;{strings.actions["uncloak-warn"]}</div>}

        { props.inventoryAType === 'rucksack' && !lock_a && props.inventoryBType !== 'none' && <>
            <button onClick={() => manageTransfer(null, props.inventoryAId, props.inventoryBId, 'down-all')}>
                <img src={strings?.actions["down-all-icon"] ?? ''} alt={strings?.actions[`down-all-${props.inventoryBType}`] ?? strings?.actions['down-all-any'] ?? ''}/>
                &nbsp;
                {strings?.actions[`down-all-${props.inventoryBType}`] ?? strings?.actions['down-all-any'] ?? ''}
            </button>
        </>}

        { props.inventoryAType === 'rucksack' && props.inventoryBType === 'bank' && !lock_b && <>
            { props.steal && !theftMode && <button onClick={() => {
                if (confirm(strings?.actions["steal-confirm"] ?? '?')) setTheftMode(true);
            }}>
                <img src={strings?.actions["steal-icon"] ?? ''} alt={strings?.actions["steal-btn"] ?? ''}/>
                &nbsp;
                { strings?.actions["steal-btn"] ?? '' }
                <Tooltip additionalClasses="help" html={strings?.actions["steal-tooltip"] ?? ''}/>
            </button> }
            { props.steal && theftMode && <>
                <div className="help">{strings.actions["steal-box"]}</div>
                <button onClick={() => setTheftMode(false)}>{strings.global.abort ?? ''}</button>
            </> }
        </> }

        { props.inventoryAType === 'rucksack' && props.inventoryBType === 'desert' && props.hide !== null && !lock_a && <>
            <button onClick={() => {
                if (confirm(strings?.actions["hide-confirm"] ?? '?')) manageTransfer(null, props.inventoryAId, props.inventoryBId, 'down-all', 'hide')
            }}>
                <img src={strings?.actions["hide-icon"] ?? ''} alt={strings?.actions["hide-btn"] ?? ''}/>
                &nbsp;
                { strings?.actions["hide-btn"] ?? '' }
                { props.hide > 0 && <>
                    &nbsp;
                    (<div className="ap">{ props.hide }</div>)
                </> }
                { props.uncloak && <>
                    <img className="icon right" alt="" src={ strings?.actions["uncloak-icon"] }/>
                </>}

                <Tooltip additionalClasses="help" html={strings?.actions["hide-tooltip"] ?? ''}/>
                { props.uncloak }
            </button>
        </> }

        { props.inventoryBType !== 'none' && <>
            <SwitchInventory
                id={props.inventoryBId} type={props.inventoryBType} inventory={inventoryB}
                label={props.inventoryBLabel}
                locked={lock_b || loading} theft={theftMode}
                onItemClick={handleTransfer( props.inventoryBId, props.inventoryAId, 'up' )}
                enableItemCallback={props.filterEnabledItems}
                className={props.className}
            />
        </>}

        { props.inventoryBType === 'desert' && strings && inventoryB && !inventoryB.bank && (inventoryB as InventoryBagData).items.filter(i => i.h).length > 0 &&
            <div className="help">
                {strings.actions["hidden-help1"]}
                &nbsp;
                <a className="help-button">
                    {strings.global.help}
                    <Tooltip additionalClasses="help" html={strings.actions["hidden-help2"]}/>
                </a>
            </div>}
    </Globals.Provider>
};

interface InventoryProps {
    id: number,
    "type": InventoryType,
    label?: string|null,
    locked: boolean,
    inventory: InventoryResponse,
    onItemClick: (i: Item) => void,
    theft?: boolean,
    className?: string,
    enableItemCallback?: (i: Item, v: VaultItemEntry|null) => boolean,
}

interface InventoryPropsBag extends InventoryProps {
    inventory: InventoryBagData,
}

interface InventoryPropsBank extends InventoryProps {
    inventory: InventoryBankData,
}

const SwitchInventory = (props: InventoryProps) => {
    const globals = useContext(Globals);
    const loaded = props.inventory && globals.strings;

    return <>
        { !loaded && <ul className={`inventory inventory-react ${props.type} ${props.className ?? ''}`}>
            <li className="placeholder">
                <div className="loading"/>
            </li>
        </ul> }
        { loaded && props.inventory.bank && <BankInventory {...(props as InventoryPropsBank)} /> }
        { loaded && !props.inventory.bank && <BagInventory {...(props as InventoryPropsBag)} /> }
    </>
}

interface SlotProps {
    free: boolean,
    itemInfo?: {
        item: Item,
        vault?: VaultItemEntry,
    },
    heavy?: boolean,
    over?: boolean,
}

interface BagInventorySlotProps extends SlotProps {
    bag: InventoryPropsBag,
    vaultData: VaultStorage<VaultItemEntry>,
}

const BagInventorySlot = (props: BagInventorySlotProps) => {
    const globals = useContext(Globals);

    const className = React.useMemo(() => {
        const classes = new Set();

        if (!props.itemInfo) {
            classes.add('free');
        }

        if (props.itemInfo && props.itemInfo.item.e) {
            classes.add('bg-locked');
        }

        if (props.over) {
            classes.add('bg-over');
        }

        if (props.heavy) {
            classes.add('bg-heavy');
        } else if (props.itemInfo) {
            classes.add('bg-light');
        }

        return Array.from(classes.values()).join(' ');
    }, [props.itemInfo, props.over, props.heavy]);

    if (props.itemInfo) {
        return <React.Fragment>
            <SingleItem
                item={props.itemInfo.item}
                disabled={props.bag.enableItemCallback && !props.bag.enableItemCallback(props.itemInfo.item, props.itemInfo.vault ?? null)}
                blur={null}
                className={className}
                mods={props.bag.inventory.mods}
                data={props.itemInfo.vault ?? null}
                locked={props.bag.locked || props.itemInfo.item.e} onClick={props.bag.onItemClick}
            />
        </React.Fragment>;
    }
    return <li className={className}>
        {props.heavy && <Tooltip additionalClasses="help" html={ globals.strings?.global.heavy_slot } />}
    </li>
}

const BagInventory = (props: InventoryPropsBag) => {

    const globals = useContext(Globals);

    const vaultData = useVault<VaultItemEntry>(
        'items', props.inventory?.items?.map(v => v.p)
    )

    const label = props.label ?? globals.strings?.type[props.type] ?? '';

    const slots: SlotProps[] = React.useMemo(() => {
        const inventorySize = props.inventory.size ?? 0;
        const inventoryHeavySlots = props.inventory.heavy ?? 0;
        const isBag = props.type === 'rucksack';
        const items = [...props.inventory.items].sort(sort);
        if (!isBag) {
            // Not a player bag : Display items / free slots normally
            const slots: SlotProps[] = [];
            let free = inventorySize;
            for(const item of items) {
                free--;
                slots.push({
                    free: false,
                    itemInfo: {
                        item,
                        vault: vaultData?.[item.p],
                    },
                });
            }

            for (let i = 0; i < free; i++) {
                slots.push({
                    free: true,
                });
            }

            return slots;
        }

        return processSlots(items, vaultData, inventorySize, inventoryHeavySlots);
    }, [vaultData, props.type, props.inventory.items, props.inventory.size]);

    return <ul className={`inventory inventory-react ${props.type} ${props.className ?? ''}`}>
        {label !== null && label !== '' && <li className="title">{label}</li> }
        {props.type === 'desert' && !props.inventory.size && props.inventory.items.length === 0 && <li className="category label">
            <em className="small">{ globals.strings?.props.nothing ?? '' }</em>
        </li>}
        {slots.map((slot, i) => <BagInventorySlot
            key={i}
            free={slot.free}
            itemInfo={slot.itemInfo}
            heavy={slot.heavy}
            over={slot.over}
            bag={props}
            vaultData={vaultData}
        />)}
    </ul>
}

const BankInventory = (props: InventoryPropsBank) => {

    const globals = useContext(Globals);
    const datalistUuid = useRef(randomUUIDv4());

    const [searchString, setSearchString] = useState<string>('');

    const vaultData = useVault<VaultItemEntry>(
        'items', props.inventory?.categories?.map(c => c.items.map(i => i.p))?.reduce((c,a) => [...c,...a], [])
    )

    const [showCategories, setShowCategories] = useState<boolean>($.client.config.showBankCategories.get());

    const category_map = {};
    globals.strings.categories.forEach( ([id,name,sort]) => category_map[id] = [name,sort] );

    const normalizedSearchString = searchString?.normalize("NFD")?.replace(/[\u0300-\u036f]/g, "")?.toLowerCase() ?? '';

    return <>
        <p>
            <input type="search" placeholder={globals.strings.actions.search} list={`bk-list-${datalistUuid.current}`} value={searchString} onChange={
                e => setSearchString(e.target.value)
            } />
            <datalist id={`bk-list-${datalistUuid.current}`}>
                { Object.values( vaultData ?? {} ).map(v => <option key={v.id} value={v.name}/>) }
            </datalist>
        </p>
        <ul className={`inventory inventory-react ${props.type} ${props.theft ? 'theft' : ''} ${props.className ?? ''}`}>
            {props.inventory.categories.sort((c1, c2) => category_map[c1.id][1] - category_map[c2.id][1] || c1.id - c2.id).map(c => <React.Fragment key={c.id}>
                { showCategories && <li className="category">{category_map[c.id][0]}</li> }
                { c.items.sort(sort).map(i => <React.Fragment key={i.i}>
                    <SingleItem
                        disabled={props.enableItemCallback && !props.enableItemCallback(i, (vaultData ?? {})[i.p])}
                        blur={ searchString === '' ? null : !(vaultData ?? {})[i.p]?.name?.normalize("NFD").replace(/[\u0300-\u036f]/g, "")?.toLowerCase()?.includes( normalizedSearchString ) }
                        item={i} mods={props.inventory.mods} data={(vaultData ?? {})[i.p] ?? null}
                        locked={props.locked || i.e} onClick={props.onItemClick} highlightDefense={true}
                    />
                </React.Fragment>) }
            </React.Fragment>)}
        </ul>
        <label className="small">
            <input type="checkbox" checked={showCategories} onChange={e => {
                setShowCategories(!showCategories);
                $.client.config.showBankCategories.set(!showCategories);
            }} />
            &nbsp;
            { globals.strings.actions["show-categories"] ?? '' }
        </label>
    </>
}

const SingleItem = (props: { item: Item, data: VaultItemEntry | null, mods: InventoryMods, disabled?: boolean, locked: boolean, onClick?: (i:Item) => void, blur: null|boolean, className?: string, highlightDefense?: boolean })=> {
    const globals = useContext(Globals);

    return (globals.strings && props.data)
        ? <li
            className={`item ${props.className ?? ''} ${(props.blur === true && 'blur') || ''} ${(props.blur === false && 'focus') || ''} ${(props.disabled && 'disabled') || ''} ${(props.locked && 'locked') || ''} ${(props.item.b && 'broken') || ''} ${(props.item.h && 'banished_hidden') || ''} ${(props.item.c > 1 && 'counted') || ''} ${(props.item.c >= 100 && 'excessive') || ''} ${(props.highlightDefense && props.data.props.includes('defence') && 'defense') || ''}`}
            onClick={ props.locked ? () => undefined : () => props.onClick?.(props.item) }
        >
            <span className="item-icon"><img src={ props.data?.icon ?? '' } alt={ props.data?.name ?? '...' }/></span>
            {props.item.c > 1 && <span>{props.item.c}</span>}
            <ItemTooltip data={props.data} addendum={props.item.b && {className: 'broken', text: globals.strings.props.broken}}>
                { props.mods.has_drunk && props.data.props.includes('is_water') && <div className="item-addendum">{ globals.strings.props["drink-done"] }</div> }
                { props.item.e && <div className="item-tag item-tag-essential">{ globals.strings.props.essential }</div> }
                { props.data.props.includes('single_use') && <div className="item-tag item-tag-use-1">{ globals.strings.props.single_use }</div> }
                { props.data.heavy && <div className="item-tag item-tag-heavy">{ globals.strings.props.heavy }</div> }
                { ((props.data.deco ?? 0) > 0 || props.data.props.includes('deco')) && <div className="item-tag item-tag-deco">{ globals.strings.props.deco }</div> }
                { props.data.props.includes('defence') && <div className="item-tag item-tag-defense">{ globals.strings.props.defence }</div> }
                { props.data.props.includes('weapon') && <div className="item-tag item-tag-weapon">{ globals.strings.props.weapon }</div> }
                { props.data.watch != 0 && <div className="item-tag item-tag-weapon">
                    { globals.strings.props["nw-weapon"] }
                    {props.item.w && <>&nbsp;<em>{ props.item.w }</em></> }
                </div> }
                { props.mods.cata && props.data.cata && <div className="item-tag item-tag-catapult">{ globals.strings.props.catapult }</div> }
            </ItemTooltip>
        </li>
        :
        <li className="item locked pending"/>
}

const HordesPassiveInventoryWrapper = (props: passiveMountProps) => {

    const api = useRef( new InventoryAPI() )

    const strings = useTranslations( api.current );
    const [bag, setBag] = useState<InventoryBagData>(null);
    const [mayBeOutdated, setMayBeOutdated] = useState<boolean>(false);

    const vaultData = useVault<VaultItemEntry>(
        'items', bag ? extractAllItems( bag ).map(i => i.p) : null
    )

    useBroadcastSignal(
        ['inventory-bag-loaded', 'inventory-changed'],
        () => { setMayBeOutdated(true) },
        [props.id]
    );

    useSharedWorkerMessages(
        'inventory-changed',
        () => { setMayBeOutdated(true) },
        'live',
        [props.id]
    )

    useSignal(
        'web-navigation',
        () => {
            // Attempt to find an active bag
            const i = document.querySelector(`hordes-inventory[data-inventory-a-id="${props.id}"],hordes-inventory[data-inventory-b-id="${props.id}"]`);
            if (!i && mayBeOutdated) api.current.inventory(props.id).then(r => {
                if (!r.bank) setBag(r as InventoryBagData);
            });
            if (mayBeOutdated) setMayBeOutdated(false);
        },
        [mayBeOutdated]
    )

    useSignal<InventoryBagLoadedSignalProps>(
        'inventory-bag-loaded',
        ({id,inventory}) => {
            if (id === props.id) setBag(inventory as InventoryBagData);
        },
        [props.id]
    )

    useSignal<ServerInducedSignalProps>(
        'inventory-changed',
        () => {
            // Attempt to find an active bag
            const i = document.querySelector(`hordes-inventory[data-inventory-a-id="${props.id}"],hordes-inventory[data-inventory-b-id="${props.id}"]`);
            // No bag here, we need to update ourselves
            if (!i) api.current.inventory(props.id).then(r => {
                if (!r.bank) setBag(r as InventoryBagData);
            });
        },
        [props.id]
    )

    useSignal<ServerInducedSignalProps>(
        'inventory-changed-headless',
        () => {
            api.current.inventory(props.id).then(r => {
                if (!r.bank) setBag(r as InventoryBagData);
            });
        },
        [props.id]
    )

    useEffect(() => {
        if (!props.id) return;

        // Attempt to find an active bag
        const i = document.querySelector(`hordes-inventory[data-inventory-a-id="${props.id}"],hordes-inventory[data-inventory-b-id="${props.id}"]`);
        if (i) {
            setBag( (i as any).bag(props.id) ?? null )
        } else {
            api.current.inventory(props.id).then(r => {
                if (!r.bank) setBag(r as InventoryBagData);
            });
        }
    }, [props.id]);

    useEffect(() => {
        if (props.max <= 0 || !bag) return;

        const expand = Math.max(bag.items.length, bag.size) > props.max;
        props.parent.classList.toggle('expanded', expand);
        if (expand) {

            const moveGVBarOut = () => {
                const status = document.querySelector('.game-bar .status.rucksack_status_union') as HTMLElement;
                const bar = document.querySelector('.game-bar .status-ghoul') as HTMLElement;
                if (!bar || !status) return;
                bar.style.left = `-${ 8 + bar.clientWidth - status.clientWidth}px`;
            }
            const moveGVBarIn = () => {
                const bar = document.querySelector('.game-bar .status-ghoul') as HTMLElement;
                if (!bar) return;

                bar.style.left = null;
            }

            props.parent.addEventListener('mouseenter', moveGVBarOut);
            props.parent.addEventListener('mouseleave', moveGVBarIn);

            return () => {
                props.parent.removeEventListener('mouseenter', moveGVBarOut);
                props.parent.removeEventListener('mouseleave', moveGVBarIn);
            }
        }
    }, [bag]);

    useEffect(() => {
        const handler = () => $.ajax.load(null, props.link, true);
        props.parent.addEventListener('click', handler);
        return () => props.parent.removeEventListener('click', handler);
    }, [props.link])

    const slots = React.useMemo(() => {
        return processSlots(bag?.items ? [...bag.items] : [], vaultData, bag?.size ?? 0, bag?.heavy ?? 0);
    }, [bag, vaultData]);

    return <Globals.Provider value={{api: api.current, strings}}>
        {strings && bag && <>
            {slots.filter(slot => slot.itemInfo).map((slot,index) => <React.Fragment key={slot.itemInfo.item.i}><SingleItem
                item={slot.itemInfo.item} data={(vaultData ?? {})[slot.itemInfo.item.p] ?? null} mods={bag.mods} locked={true}
                blur={null} className={(props.max > 0 && index >= props.max) ? 'over' : ''}/>
            </React.Fragment>)}
            {slots.filter(slot => !slot.itemInfo).map((slot,index) =>
                <li key={index} className={`free ${(index+1+bag.items.length > props.max) ? 'over' : ''}`}/>)
            }
            <li className="more">
                <img src={strings.actions.more} alt="+"/>
            </li>
        </>}
    </Globals.Provider>

}

const HordesStandaloneItemWrapper = (props: standaloneItemMountProps) => {
    return <StandaloneItem {...props} />
}

export const StandaloneItem = (props: {
    item: number,
    extra?: string,
    line?: boolean
    inline?: boolean,
    labelClass?: string,
}) => {

    const vaultData = useVault<VaultItemEntry>(
        'items', [props.item]
    )

    const item = (vaultData ?? {})[props.item] ?? null;

    return <div className={props.line ? (props.inline ? 'inline-block' : '') : 'inline'}>
        { item && <>
            { props.line && <div className={`item-line ${props.inline ? 'inline' : ''}`}>
                <img alt={item.name} src={item.icon}/>
                <span className={props.labelClass}>{item.name}</span>
            </div> }
            { !props.line && <img alt={item.name} src={item.icon}/> }
            <ItemTooltip data={item}>
                { props.extra?.length > 0 && <>
                    <hr/>
                    <div dangerouslySetInnerHTML={{__html: props.extra}}/>
                </> }
            </ItemTooltip>
        </> }
    </div>
}

const HordesEscortInventoryWrapper = (props: escortMountProps) => {

    const api = useRef( new InventoryAPI() )

    const [open, setOpen] = useState<boolean>(false);
    const [loading, setLoading] = useState<boolean>(false);

    const strings = useTranslations( api.current );

    const [bag, setBag] = useState<InventoryBagData>(null);
    const [floor, setFloor] = useState<InventoryBagData>(null);

    const bagVaultData = useVault<VaultItemEntry>(
        'items', bag ? extractAllItems( bag ).map(i => i.p) : null
    )

    const floorVaultData = useVault<VaultItemEntry>(
        'items', floor ? extractAllItems( floor ).map(i => i.p) : null
    )

    useEffect(() => {
        if (!props.rucksackId) return;

        api.current.inventory(props.rucksackId).then(r => {
            if (!r.bank) setBag(r as InventoryBagData);
        });

    }, [props.rucksackId, props.etag]);

    useSignal<InventoryBagLoadedSignalProps>(
        'inventory-bag-loaded',
        ({id,inventory}) => {
            if (id === props.floorId) setFloor(inventory as InventoryBagData);
        },
        [props.floorId, open]
    )

    useEffect(() => {
        if (!props.floorId || !open) return;

        // Attempt to find an active bag
        const i = document.querySelector(`hordes-inventory[data-inventory-a-id="${props.floorId}"],hordes-inventory[data-inventory-b-id="${props.floorId}"]`);
        if (i) setFloor( (i as any).bag(props.floorId) ?? null )

    }, [props.floorId, open]);

    const manageTransfer = (item: number|null, from: number, to: number, direction: string) =>{
        setLoading(true);

        api.current.transfer( item, from, to, direction ).then(s => {
            emitSignal<InventoryTransferSignalProps>('item-transfer', { item, from, to, direction, response: s })

            // Update individual inventories
            const toA = direction === 'down' ? s.source : s.target;
            const toB = direction === 'down' ? s.target : s.source;

            if (toA) setBag(toA as InventoryBagData);
            if (toB) {
                setFloor(toB as InventoryBagData);
                emitSignal<InventoryBagLoadedSignalProps>('inventory-bag-loaded', {id: props.floorId, inventory: toB, element: props.parent})
            }

            commonInventoryResponseHandler(s, direction, null, true, props.reload, false);

            setLoading(false);
        }).catch(() => setLoading(false))
    }

    const handleTransfer = (from: number, to: number, direction: string) => {
        return (i:Item) => manageTransfer(i.i, from, to, direction);
    }

    const loaded = bag && strings;

    const itemList = bag?.items?.sort(sort);
    const lightItems = itemList?.filter(i => bag.heavy === 0 || !(bagVaultData ?? {})[i.p]?.heavy);
    const heavyItems = itemList?.filter(i => bag.heavy > 0 && (bagVaultData ?? {})[i.p]?.heavy);

    return <Globals.Provider value={{api: api.current, strings}}>
        { !loaded && <div className="loading"/> }
        { loaded && <ul className="inventory rucksack-escort">
            {lightItems.map((item,index) => <React.Fragment key={item.i}><SingleItem
                className={bag.size > 0 && bag.heavy > 0 ? (item.e
                    ? "bg-locked"
                    : (index < (bag.size - bag.heavy) ? "bg-light" : (
                            index < bag.size
                                ? "bg-heavy"
                                : "bg-over"
                        ))
                ) : ''}
                item={item} data={(bagVaultData ?? {})[item.p] ?? null} mods={bag.mods} locked={item.e || loading}
                onClick={handleTransfer(props.rucksackId, props.floorId, 'down')}
                blur={null}/>
            </React.Fragment>)}
            {bag.size > 0 && Array.from(Array(Math.max(0, bag.size - lightItems.length - bag.heavy)).keys()).map(i =>
                <li key={i} className="free"/>)
            }
            {heavyItems.map((item,index) => <React.Fragment key={item.i}><SingleItem
                className={index >= bag.heavy ? "bg-over" : "bg-heavy"}
                item={item} data={(bagVaultData ?? {})[item.p] ?? null} mods={bag.mods} locked={item.e || loading}
                onClick={handleTransfer(props.rucksackId, props.floorId, 'down')}
                blur={null}/>
            </React.Fragment>)}
            {bag.heavy > 0 && Array.from(Array(Math.max(0, bag.heavy - heavyItems.length - Math.max(0, lightItems.length - (bag.size - Math.max(bag.heavy,heavyItems.length))))).keys()).map(i =>
                <li key={i} className="free bg-heavy"/>)
            }
            { loaded && props.floorId > 0  && <li className="item plus" onClick={() => setOpen(!open)}>
                <img alt="+" src={strings.actions["more-btn"]}/>
                <Tooltip html={ strings.actions.pickup.replace('{citizen}', props.name) } />
            </li> }
        </ul> }
        { loaded && floor && open && <div className="tooltip-dummy"><ul className="inventory desert-escort">
            {floor?.items?.sort(sort).map((item,index) => <React.Fragment key={item.i}><SingleItem
                item={item} data={(floorVaultData ?? {})[item.p] ?? null} mods={floor.mods} locked={item.e}
                onClick={handleTransfer(props.floorId, props.rucksackId, 'up')}
                blur={null}/>
            </React.Fragment>)}
            { floor.items.length === 0 && <li className="category label">
                <em className="small">{strings.props.nothing}</em>
            </li>}
        </ul></div>}

    </Globals.Provider>
}
