import * as React from "react";
import {useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {ReactionsAPI, ReactionSet} from "./api";
import {TranslationStrings} from "./strings";
import {Global} from "../../defaults";
import {BaseMounter} from "../index";
import {useTranslations} from "../utils";
import {EmoteResponse, TwinoEditorAPI} from "../twino-editor/api";

declare var $: Global;

export interface mountProps {
    uuid: string
}

type ReactionGlobals = {
    reactionSet: ReactionSet|null
    updateReactionSet: (reactionSet: ReactionSet) => void
    api: ReactionsAPI,
    twinoApi: TwinoEditorAPI,
    strings: TranslationStrings|null
}

export const Globals = React.createContext<ReactionGlobals>(null);

export class HordesReactionsUI extends BaseMounter<mountProps>{
    protected render(props: mountProps): React.ReactNode {
        return <HordesReactionsUIWrapper {...props} />;
    }
}

const HordesReactionsUIWrapper = (props: mountProps) => {

    const api = useRef(new ReactionsAPI());
    const twinoApi = useRef(new TwinoEditorAPI());
    const strings = useTranslations(api.current);

    const [reaction, setReaction] = useState<ReactionSet>(null);

    const ready = strings && reaction;

    useEffect(() => {
        api.current.get(props.uuid).then(r => setReaction(r));
        return () => setReaction(null);
    }, [props.uuid]);

    return <Globals.Provider value={{
        api: api.current, twinoApi: twinoApi.current, strings, reactionSet: reaction,
        updateReactionSet: r => setReaction(r)
    }}>
        <div className="reactions flex wrap">
            { !ready && <>
                <Reaction count={0}/>
                <Reaction count={200}/>
                <Reaction count={400}/>
                <Reaction count={600}/>
                <Reaction count={800}/>
            </> }
            { ready && <>
                { reaction.reactions.map( r => <Reaction count={r.count} path={r.path} id={r.id} key={r.id}/> ) }
                <AddReactionButton/>
            </> }
        </div>
    </Globals.Provider>
};

const Reaction = ( props: {path?: string|null, count: number, id?: number} ) => {

    const {updateReactionSet, reactionSet, api} = useContext(Globals);
    const self = useRef<HTMLDivElement>(null);
    const [saving, setSaving] = useState<boolean>(false);

    useLayoutEffect(() => {
        if (!self || props.path) return;

        self.current.style.opacity = '0';
        const animation = self.current.animate([
            {opacity: 0},
            {opacity: 1, offset: 0.2},
            {opacity: 0, offset: 0.4},
            {opacity: 0},
        ], {
            delay: props.count,
            duration: 2000,
            easing: 'ease-in-out',
            fill: 'none',
            iterations: Infinity,
        });

        return () => animation.cancel();
    }, [props.path, props.count]);

    const click = () => {
        if (!props.id || !reactionSet || saving) return;

        setSaving(true);
        ( props.id === reactionSet.own ? api.remove(reactionSet.id) : api.put(reactionSet.id, props.id) )
            .then(r => updateReactionSet(r))
            .finally(() => setSaving(false));
    }

    return <div ref={self} className={`reaction flex middle center ${ props.path ? '' : 'pending' } ${ (props.id && props.id === reactionSet?.own) ? 'mine' : '' }`} onClick={click}>
        { props.path && <>
            <img src={props.path} alt="" />
            <span>{props.count ?? 0}</span>
        </> }
    </div>

}

const AddReactionButton = () => {

    const self = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState<boolean>(false);

    useLayoutEffect(() => {
        if (!open) return;

        const close = (e: PointerEvent) => {
            if (self.current && !self.current.contains(e.target as Node))
                setOpen(false);
        }
        document.addEventListener('click', close );
        return () => document.removeEventListener('click', close);
    }, [open]);


    return <div ref={self} className="add-reaction-parent">
        <div className="reaction add-reaction flex middle center" onClick={()=> setOpen(o => !o)}/>
        { open && <EmoteSelector close={() => setOpen(false) }/> }
    </div>
}

const EmoteSelector = (props: {close: ()=>void}) => {
    const {reactionSet, updateReactionSet, api, twinoApi} = useContext(Globals);

    const [emotes, setEmotes] = useState<EmoteResponse>(null)
    const [saving, setSaving] = useState<boolean>(false);

    useEffect(() => {
        twinoApi.emotes(reactionSet.me, 'reactions').then(r => setEmotes(r));
    }, []);

    return <div className="emote-popup">
        {!emotes && <div className="loading" />}
        { emotes && <div className={`flex wrap ${saving ? 'disabled' : ''}`}>
            { Object.values( emotes.result )
                .sort( (a,b) => a.orderIndex - b.orderIndex )
                .map( emote => <img key={ emote.id } className="pointer" alt={ emote.tag } src={ emote.url } onClick={() => {
                    setSaving(true);
                    api.put( reactionSet.id, emote.id )
                        .then( r => updateReactionSet(r))
                        .finally(() => props.close())
                }}/>)
            }
        </div> }
    </div>
}
