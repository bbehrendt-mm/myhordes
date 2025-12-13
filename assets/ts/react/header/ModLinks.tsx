import * as React from "react";
import {useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {ExternalApp, HeaderAPI, ModLink} from "./api";
import {Global} from "../../defaults";
import {useSharedWorkerMessages, useStickyToggle, useTranslations} from "../utils";
import {Globals, mountProps} from "./Wrapper";
import {useSlidingAnimation} from "./commons";

declare var $: Global;



export const HordesHeaderModLinksWidget = () => {

    const globals = useContext(Globals)

    const root = useRef<HTMLDivElement>();
    const animation = useRef<Animation>()

    const [show, render, setRender] = useStickyToggle(false);

    const [openInSameWindow, setOpenInSameWindow] = useState(false);
    const [modHeader, setModHeader] = useState<string|null>(null);
    const [modList, setModList] = useState<ModLink[]>([]);

    const refreshLinks = () => {
        globals.api.mods().then(r => {
            setOpenInSameWindow(r.same ?? false);
            setModHeader(r.cat ?? null);
            setModList(r.links ?? []);
        })
    }

    useEffect(() => {
        refreshLinks();
    }, []);

    useSlidingAnimation(show,render,animation,root);

    if (!modHeader || modList.length === 0 || !globals.strings) return;

    return <>
        <div className={`header-directory mod-directory ${show ? 'open' : 'closed'}`} ref={root}
             onMouseOver={() => setRender(true)} onClick={() => setRender(!show)}
             onMouseOut={() => setRender(false)}
        >
            <img alt={modHeader} src={globals.strings.mods.list.icon} className="header-directory-icon"/>
            {render && <div className="header-listing-body mod-listing-body">
                <h4>{modHeader}</h4>
                <ul>{ modList.sort( (a,b) => a.sort - b.sort ).map( (link,i) => <li
                    key={i}
                    onClick={() => openInSameWindow ? (window.location.href = link.url) : window.open(link.url, '_blank')}
                >
                    <div><img alt={link.name} src={globals.strings.mods.list.no_icon}/></div>
                    <div className="label">
                        <span className="name">{link.name}</span>
                    </div>
                </li> ) }</ul>
            </div>}
        </div>
    </>

}
