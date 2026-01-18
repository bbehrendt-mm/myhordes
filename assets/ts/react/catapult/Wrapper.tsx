import * as React from "react";
import {useContext, useRef, useState} from "react";
import {BaseMounter, ReactMapBootstrapData} from "../index";
import {TranslationStrings} from "./strings";
import {CatapultAPI} from "./api";
import {useTranslations} from "../utils";
import {Map} from "../map/Wrapper";
import {SingleInventory, StandaloneItem} from "../inventory/Wrapper";
import {Global} from "../../defaults";

declare var $: Global;

export class HordesCatapult extends BaseMounter<{  }>{
    protected render(props: {map: ReactMapBootstrapData, inventory: number}): React.ReactNode {
        return <Catapult {...props} parent={this.parent} />;
    }
}

export const Globals = React.createContext<{
    strings: TranslationStrings,
    api: CatapultAPI
}>(null);

const Catapult = (
    props: {
        map: ReactMapBootstrapData,
        inventory: number,
        parent: HTMLElement
    }) => {

    const apiRef = useRef<CatapultAPI>( new CatapultAPI() );
    const strings = useTranslations( apiRef.current );

    const [etag, setEtag] = useState<number>(0);

    const [targetZone, setTargetZone] = useState<{x: number, y: number}|null>();
    const [targetItem, setTargetItem] = useState<{i: number, p: number}|null>();

    return <Globals.Provider value={{ strings: strings, api: apiRef.current }}>
        <div className="row-flex gap">
            <div className="cell rw-6 flex column element-gap">
                <SingleInventory
                    id={props.inventory} parent={props.parent}
                    onItemClick={i => setTargetItem({i: i.i, p: i.p})}
                    filterEnabledItems={(i,v) => !i.e && v?.cata}
                />
                { strings && <CatapultSummary targetZone={targetZone} targetItem={targetItem} onSuccessfulThrow={() => {
                    setTargetItem(null);
                }}/> }
                {!strings && <div className="loading"/>}
            </div>
            <div className="cell rw-6">
                <Map {...props.map} etag={etag} id="catapult"
                     onActivateZoneChanged={zone => setTargetZone(zone)}
                />
            </div>
        </div>
    </Globals.Provider>
};

const CatapultSummary = (props: {
    targetZone: {x: number, y: number}|null,
    targetItem: {i: number, p: number}|null,
    onSuccessfulThrow: () => void
}) => {
    const globals = useContext(Globals);

    return <div className="note">
        <div className="center">
            <div><strong>{globals.strings.common.object}</strong></div>
            { props.targetItem && <StandaloneItem item={props.targetItem.p} line={true} inline={true} /> }
            { !props.targetItem && <span>-</span> }
        </div>
        <div className="row">
            <div className="padded cell rw-6">
                <div className="center">
                    <div><strong>X</strong></div>
                    <div>{ props.targetZone?.x ?? '-' }</div>
                </div>
            </div>
            <div className="padded cell rw-6">
                <div className="center">
                    <div><strong>Y</strong></div>
                    <div>{ props.targetZone?.y ?? '-' }</div>
                </div>
            </div>
        </div>
        <div className="row">
            <div className="padded cell rw-12" data-disabled={ (!props.targetItem || !props.targetZone || (props.targetZone?.x === 0 && props.targetZone?.y === 0)) ? 'disabled' : '' }>
                <button onClick={() => {
                    $.html.addLoadStack();
                    globals.api.catapult( props.targetItem.i, props.targetZone.x, props.targetZone.y )
                        .then(({success, message}) => {
                            if (message) $.html.message(success ? 'notice' : 'error', message);
                            if (success) props.onSuccessfulThrow();
                        })
                        .finally(() => $.html.removeLoadStack())
                }}>{globals.strings.common.confirm}</button>
            </div>
        </div>
    </div>
}
