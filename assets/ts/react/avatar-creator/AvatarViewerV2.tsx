import {Media, MediaGroup, MediaSet} from "./api";
import {useContext, useEffect, useRef, useState} from "react";
import {Globals, SignalCache} from "./WrapperV2";
import * as React from "react";
import {byteToText} from "../../v2/utils";
import {useSlider} from "../utils";
import {Global} from "../../defaults";
import {Buffer} from "buffer";

declare var $: Global;

type AvatarViewerProps = {
    current?: MediaGroup,
    pending?: MediaGroup,
    history?: MediaGroup[],
    signalCache: SignalCache
}

export const AvatarModeView = (props: AvatarViewerProps) => {
    const globals = useContext(Globals)

    const [pending, setPending] = useState<MediaGroup>(null);
    const [activeHistoryElement, setActiveHistoryElement] = useState<string>(null);

    useEffect(() => {
        if (!props.pending) {
            setPending(null);
            return;
        }

        const base = JSON.parse( JSON.stringify( props.pending ) ) as MediaGroup;
        const source = (props.signalCache[props.pending?.id] ?? {});

        props.pending.default.conversions.forEach((conversion) => {
            base.default.conversions = base.default.conversions.map(c => source[c.id] ?? c);
        })
        base.default.conversions.forEach( c => {
            if (!base.default.url || (base.default.x * base.default.y) < (c.x * c.y))
                base.default = {...base.default, ...c}
        } )

        props.pending.round.conversions.forEach((conversion) => {
            base.round.conversions = base.round.conversions.map(c => source[c.id] ?? c);
        })
        base.round.conversions.forEach( c => {
            if (!base.round.url || (base.round.x * base.round.y) < (c.x * c.y))
                base.round = {...base.round, ...c}
        } )

        props.pending.small.conversions.forEach((conversion) => {
            base.small.conversions = base.small.conversions.map(c => source[c.id] ?? c);
        })
        base.small.conversions.forEach( c => {
            if (!base.small.url || (base.small.x * base.small.y) < (c.x * c.y))
                base.small = {...base.small, ...c}
        } )

        setPending(base);
    }, [props.pending, props.signalCache]);

    const has_pending_conversions = !!(
        pending?.default?.conversions.find( (m: Media) => !m.url ) ||
        pending?.round?.conversions.find( (m: Media) => !m.url ) ||
        pending?.small?.conversions.find( (m: Media) => !m.url ));

    return <div className="flex column element-gap">
        { pending && <div className="flex column">
            <div className="small"><strong>{ globals.strings.common.view_pending }</strong></div>
            { has_pending_conversions && <div className="warning" style={{margin: '16px 0'}}>
                <div className="flex large-gap middle">
                    <div className="flex-none"><div className="loading small"/></div>
                    <div className="flex-1">
                        { globals.strings.common.preview_help5 }
                    </div>
                </div>
            </div> }
            { !has_pending_conversions && <div className="help">
                <strong>{ globals.strings.common.preview_help }</strong><br/>
                <p>
                    <i>{ globals.strings.common.preview_help2 }</i>
                    {" "}
                    { globals.strings.common.preview_help3 }
                    { props.current && <>
                        {" "}
                        { globals.strings.common.preview_help4 }
                    </> }
                </p>
            </div> }
            <div><AvatarMediaGroup mediaGroup={ pending } pending={true} /></div>
        </div> }

        { pending && <>
            <div className="flex">
                <div><button disabled={ has_pending_conversions } onClick={()=> {
                    $.html.addLoadStack();
                    globals.api.promoteMedia(props.pending.id)
                        .then(() => {
                            $.html.notice( globals.strings.common.success_create );
                            $.html.removeLoadStack();
                            globals.refresh()
                        }).catch(() => $.html.removeLoadStack());
                }}>
                    { globals.strings.common.action_activate_preview }
                </button></div>
                <div><button disabled={ has_pending_conversions } onClick={()=> {
                    $.html.addLoadStack();
                    globals.api.deleteMedia(props.pending.id)
                        .then(() => {
                            $.html.notice( globals.strings.common.success_delete );
                            $.html.removeLoadStack();
                            globals.refresh()
                        })
                }}>
                    { globals.strings.common.action_delete_preview }
                </button></div>
            </div>
        </> }

        { props.current && <div className="flex column">
            { (props.pending || props.history) && <div className="small"><strong>{globals.strings.common.view_current}</strong></div> }
            <div><AvatarMediaGroup mediaGroup={ props.current } /></div>
        </div> }

        { !pending && <>
            <div className="flex">
                <div><button onClick={()=> globals.setMode('new')}>
                    { globals.strings.common.action_edit }
                </button></div>

                { props.current?.source && <div><button onClick={()=> {
                    $.html.addLoadStack();

                    globals.api.getMediaProps( props.current.id ).then( p => {
                        fetch(p.source).then(response => response.arrayBuffer().then(buffer => {
                            globals.setTransientImage({
                                mime: response.headers.get('content-type'),
                                data: Buffer.from(buffer),
                                id: props.current.id,
                                crops: {
                                    default: p.default ?? null,
                                    round: p.round ?? null,
                                    small: p.small ?? null,
                                }
                            });
                            $.html.removeLoadStack();
                            globals.setMode('edit');
                        })).catch(() => $.html.removeLoadStack());
                    } ).catch(() => $.html.removeLoadStack());
                }}>{ globals.strings.common.action_modify }
                </button></div> }

                <div><button onClick={()=> {
                    $.html.addLoadStack();
                    globals.api.deleteMedia(props.current.id).then(() => {
                        $.html.notice( globals.strings.common.success_delete );
                        $.html.removeLoadStack();
                        globals.refresh()
                    }).catch(() => $.html.removeLoadStack());
                }}>
                    { globals.strings.common.action_delete }
                </button></div>
            </div>
        </> }

        { props.history && <div className="flex column">
            <div className="small"><strong>{globals.strings.common.view_history}</strong></div>
            <div className="flex column large-gap">
                { props.history.map( group => <div key={group.id}><AvatarMediaGroupCollapse
                    mediaGroup={ group }
                    activeElement={activeHistoryElement}
                    setActiveElement={setActiveHistoryElement}
                /></div> ) }
            </div>
        </div> }


    </div>

}

const AvatarMediaGroupCollapse = ({mediaGroup, activeElement, setActiveElement}: {mediaGroup: MediaGroup, activeElement: string|null, setActiveElement: (e:string|null) => void}) => {
    const globals = useContext(Globals)

    const ref = useRef<HTMLDivElement>(null);

    const [currentState, showElements, renderElements, setShowElements] = useSlider(
        false,
        ref,
        '[data-slide="1"]',
        250,
        null,
        null
    )

    useEffect(() => {
        setShowElements(activeElement === mediaGroup.id);
    }, [activeElement]);

    return <div ref={ref} className="avatar-history-element">
        <div className="flex large-gap middle">
            <div className="flex-none pointer" onClick={() => {
                setActiveElement(showElements ? null : mediaGroup.id)
            }}>
                <div className="avatar small no-arma"><img alt="" src={ mediaGroup.small.url ?? mediaGroup.default.url ?? mediaGroup.round.url } /></div>
            </div>
            <div className="flex-1">

            </div>
            <div className="flex-none flex middle">
                <img className="pointer"
                     alt={ globals.strings.common.action_activate_preview }
                     title={ globals.strings.common.action_activate_preview }
                     src={ globals.strings.common.action_icon_restore }
                     onClick={ () => {
                         $.html.addLoadStack();
                         globals.api.promoteMedia(mediaGroup.id).then(() => {
                             $.html.notice( globals.strings.common.success_create );
                             $.html.removeLoadStack();
                             globals.refresh()
                         }).catch(() => $.html.removeLoadStack());
                     } }
                />
                <img className="pointer"
                     alt={ globals.strings.common.action_delete_preview }
                     title={ globals.strings.common.action_delete_preview }
                     src={ globals.strings.common.action_icon_delete }
                     onClick={ () => {
                         $.html.addLoadStack();
                         globals.api.deleteMedia(mediaGroup.id).then(() => {
                             $.html.notice( globals.strings.common.success_delete );
                             $.html.removeLoadStack();
                             globals.refresh()
                         }).catch(() => $.html.removeLoadStack());
                     } }
                />
            </div>
        </div>
        <div data-slide={1}>
            { renderElements && <AvatarMediaGroup mediaGroup={mediaGroup} pending={false}/>}
        </div>
    </div>
}

const AvatarMediaGroup = ({mediaGroup, pending}: {mediaGroup: MediaGroup, pending?: boolean}) => {
    const globals = useContext(Globals)

    return <div className="flex column large-gap">
        { mediaGroup.default && <AvatarMediaSet pending={pending} text={ globals.strings.common.format_default } mediaSet={ mediaGroup.default } /> }
        { mediaGroup.default && (mediaGroup.round || mediaGroup.small) && <hr className="section"/> }
        { mediaGroup.round && <AvatarMediaSet pending={pending} text={ globals.strings.common.format_round } type="round" mediaSet={ mediaGroup.round } /> }
        { mediaGroup.round && mediaGroup.small && <hr className="section"/> }
        { mediaGroup.round && <AvatarMediaSet pending={pending} text={ globals.strings.common.format_small } type="small" mediaSet={ mediaGroup.small } /> }
    </div>
}

const AvatarMediaSet = ({pending, text, type, mediaSet}: {pending?: boolean, text?: string, type?: string|null, mediaSet: MediaSet}) => {
    const globals = useContext(Globals);

    return <div className="row-flex gap v-center">
        <div className="cell rw-4 center flex-column">
            { text && <div className="small">{text}</div> }
            { mediaSet.url && <div className="flex middle center">
                <div className="flex-none"><div style={{width: '0px', height: '105px'}}></div></div>
                <div className={`avatar ${type || ''} no-arma`}>
                    <img src={mediaSet.url} alt="" />
                </div>
            </div>}
            { !mediaSet.url && !pending && <img src={globals.strings.common.view_warn} alt="" />}
            { !mediaSet.url && pending && <div className="loading"/>}
        </div>
        <div className="cell rw-8 flex column">
            { mediaSet.conversions.map((conversion, index) => <div className="flex middle" key={index}>

                { conversion.url && <>
                    <div className="flex-none"><img alt="" src={ globals.strings.common.view_check } /></div>
                    <span className="small" title={globals.strings.common.info
                        .replace('{x}', `${conversion.x}`)
                        .replace('{y}', `${conversion.y}`)
                        .replace('{size}', byteToText(conversion.size))
                    }>{ globals.strings.formats[conversion.id] ?? 'unknown' }</span>
                </> }

                { !conversion.url && !pending && <>
                    <div className="flex-none"><img alt="" src={ globals.strings.common.view_warn } /></div>
                    <span className="small" title={ globals.strings.common.missing }>{ globals.strings.formats[conversion.id] ?? 'unknown' }</span>
                </> }

                { !conversion.url && pending && <>
                    <div className="flex-none"><div className="loading smaller"/></div>
                    <span className="small" title={ globals.strings.common.pending }>{ globals.strings.formats[conversion.id] ?? 'unknown' }</span>
                </> }
            </div>) }
        </div>
    </div>
}
