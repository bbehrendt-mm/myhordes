import {MediaGroup, MediaSet} from "./api";
import {useContext, useEffect, useState} from "react";
import {Globals, SignalCache} from "./WrapperV2";
import * as React from "react";
import {byteToText} from "../../v2/utils";

type AvatarViewerProps = {
    current?: MediaGroup,
    pending?: MediaGroup,
    signalCache: SignalCache
}

export const AvatarModeView = (props: AvatarViewerProps) => {
    const globals = useContext(Globals)

    const [pending, setPending] = useState<MediaGroup>(null);

    useEffect(() => {
        if (!props.pending) return;

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

    return <div className="flex column large-gap">
        { pending && <div className="flex column">
            <div className="small"><strong>{ globals.strings.common.view_pending }</strong></div>
            <div><AvatarMediaGroup mediaGroup={ pending } pending={true} /></div>
        </div> }

        { pending && <>
            <div className="flex">
                <div><button onClick={()=> globals.api.deleteMedia(props.pending.id).then(() => globals.refresh())}>
                    { globals.strings.common.action_delete }
                </button></div>
            </div>
        </> }

        { props.current && <div className="flex column">
            { props.pending &&  <div className="small"><strong>{globals.strings.common.view_current}</strong></div> }
            <div><AvatarMediaGroup mediaGroup={ props.current } /></div>
        </div> }

        { !pending && <>
            <div className="flex">
                <div><button onClick={()=> globals.setMode('new')}>
                    { globals.strings.common.action_edit }
                </button></div>

                <div><button onClick={()=> globals.setMode('edit')}>
                    { globals.strings.common.action_modify }
                </button></div>

                <div><button onClick={()=> globals.api.deleteMedia(props.current.id).then(() => globals.refresh())}>
                    { globals.strings.common.action_delete }
                </button></div>
            </div>
        </> }
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
            { mediaSet.url && <div className={`avatar ${type || ''} no-arma`}>
                <img src={mediaSet.url} alt="" />
            </div>}
            { !mediaSet.url && !pending && <img src={globals.strings.common.view_warn} alt="" />}
            { !mediaSet.url && pending && <div className="loading"/>}
        </div>
        <div className="cell rw-8 flex column">
            { mediaSet.conversions.map((conversion, index) => <div className="flex middle" key={index}>

                { conversion.url && <>
                    <div className="flex-none"><img alt="" src={ globals.strings.common.view_check } /></div>
                    <span className="small">{globals.strings.common.info
                        .replace('{x}', `${conversion.x}`)
                        .replace('{y}', `${conversion.y}`)
                        .replace('{size}', byteToText(conversion.size))
                    }</span>
                </> }

                { !conversion.url && !pending && <>
                    <div className="flex-none"><img alt="" src={ globals.strings.common.view_warn } /></div>
                    <span className="small">Missing: { conversion.id }</span>
                </> }

                { !conversion.url && pending && <>
                    <span className="small">TODO Conversion pending...</span>
                </> }
            </div>) }
        </div>
    </div>
}
