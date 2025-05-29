import * as React from "react";
import {useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {ExternalApp, HeaderAPI, ModLink} from "./api";
import {Global} from "../../defaults";
import {useSharedWorkerMessages, useStickyToggle, useTranslations} from "../utils";
import {Tooltip} from "../tooltip/Wrapper";
import Dialog from "../components/dialog";
import {randomUUIDv4} from "../../shims";
import {Globals, mountProps} from "./Wrapper";

declare var $: Global;



export const HordesHeaderModLinksWidget = () => {

    const globals = useContext(Globals)

    const root = useRef<HTMLDivElement>();
    const animation = useRef<Animation>()

    const [show, render, setRender] = useStickyToggle(false);

    const [modHeader, setModHeader] = useState<string|null>(null);
    const [modList, setModList] = useState<ModLink[]>([]);

    const refreshLinks = () => {
        globals.api.mods().then(r => {
            setModHeader(r.cat ?? null);
            setModList(r.links ?? []);
        })
    }

    useEffect(() => {
        refreshLinks();
    }, []);

    useLayoutEffect(() => {
        if (!render || (animation.current && animation.current.playState !== "finished")) return;

        root.current.style.width = root.current.style.height = 'auto';
        const openBounds = root.current.getBoundingClientRect();

        const frames = show ? [
            {height: 0},
            {height: `${openBounds.height}px`},
        ] : [
            {height: `${openBounds.height}px`},
            {height: 0},
        ]

        const clear = () => {
            root.current.style.height = null;
        }

        animation.current = root.current.animate(frames, {
            duration: 100,
            easing: 'ease-in-out',
            fill: 'none',
        });

        animation.current.onfinish = clear;
        animation.current.oncancel = clear;
    }, [show]);

    if (!modHeader || modList.length === 0 || !globals.strings) return;

    return <>
        <div className={`mod-directory ${show ? 'open' : 'closed'}`} ref={root}
             onMouseOver={() => setRender(true)} onClick={() => setRender(!show)}
             onMouseOut={() => setRender(false)}
        >
            <span>{ modHeader }</span>
            <ul>{ modList.map( (link,i) => <li key={i}>
                <a href={link.url}>{link.name}</a>
            </li> ) }</ul>
        </div>
    </>

}
