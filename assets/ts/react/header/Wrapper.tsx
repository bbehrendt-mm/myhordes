import * as React from "react";
import {Global} from "../../defaults";
import {BaseMounter} from "../index";
import {useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {TranslationStrings} from "./strings";
import {App, ClockResponse, HeaderAPI, Tool} from "./api";
import {Tooltip} from "../tooltip/Wrapper";

declare var $: Global;

interface mountProps {
    user: number
    town: number
    impersonating: boolean,
    day: number,
    schedule: number,
    mod: boolean,
}

type Globals = {
    api: HeaderAPI,
    strings: TranslationStrings
}

export const Globals = React.createContext<Globals>(null);

export class HordesPageHeader extends BaseMounter<mountProps>{
    protected render(props: mountProps): React.ReactNode {
        return <HordesPageHeaderWrapper {...props} />;
    }
}

const HordesPageHeaderWrapper = (props: mountProps) => {

    const api = useRef(new HeaderAPI());
    const [strings, setStrings] = useState<TranslationStrings>();

    const test = useRef(window.crypto.randomUUID());

    useEffect(() => {
        api.current.index().then( s => setStrings( s ) );
        return () => { console.log('!!!!!') }
    }, []);

    return <Globals.Provider value={{api: api.current, strings}}>
        { strings && <div className="main-header">
            {props.impersonating && <ImpersonationWidget/>}
            <AppWidget user={props.user}/>
            <LogoutWidget user={props.user}/>
            <ClockWidget town={props.town} day={props.day} schedule={props.schedule}/>
            <ToolWidget mod={props.mod}/>
        </div> }
    </Globals.Provider>
};

const ImpersonationWidget = () => {
    const globals = useContext(Globals);

    return <div>
        <a className="button inline small" onClick={() => window.location.href = globals.strings.imp.url}>{ globals.strings.imp.cancel }</a>
    </div>
}

const LogoutWidget = (props: {user: number}) => {
    const globals = useContext(Globals);

    return (props.user > 0) && <div className="logout">
        <a target="_self" href={globals.strings.logout.url}>
            <img alt={globals.strings.logout.tooltip} src={globals.strings.logout.icon}/>
            <Tooltip html={globals.strings.logout.tooltip}/>
        </a>
    </div>
}

const App = (props: {app: App}) => {
    return <li className="app-external">
        <a target="_blank" href={props.app.u}>
            <img alt={props.app.n} src={props.app.p}/>
            <div><div>{ props.app.n }</div></div>
        </a>
    </li>
}

const AppWidget = (props: {user: number}) => {
    const globals = useContext(Globals);

    const [apps, setApps] = useState<App[]>(null);

    useEffect(() => {
        globals.api.apps().then(v => setApps(v.apps))
    }, [props.user])

    const normal_apps = apps?.filter(a => !a.w) ?? [];
    const wiki_apps = apps?.filter(a => a.w) ?? [];

    return (apps?.length > 0) && <div className="apps">
        <h1>
            <img src={ globals.strings.apps.icon } alt={ globals.strings.apps.directory }/>
            <span>{ globals.strings.apps.directory }</span>
        </h1>
        <div className="apps-list">
            <p>{ globals.strings.apps.help }</p>
            <ul>
                { normal_apps.map(a => <App app={a} key={a.i} />) }
                { normal_apps.length > 0 && wiki_apps.length > 0 && <li className="hr" /> }
                { wiki_apps.map(a => <App app={a} key={a.i} />) }
            </ul>
        </div>
    </div>
}

const ClockWidget = (props: {town: number, day: number, schedule: number}) => {
    const globals = useContext(Globals);

    const [clock, setClock] = useState<ClockResponse>(null);
    const [tick, setTick] = useState(0);

    const serverTimeElement = useRef<HTMLSpanElement>()
    const nextAttackElement = useRef<HTMLSpanElement>()

    const tsNextFullMinute = (d: Date = null) => {
        const ref = d ?? new Date();
        const interim = new Date(Math.ceil(ref.getTime() / 60000) * 60000);
        if (interim.getTime() > ref.getTime()) return interim;
        else {
            interim.setTime(ref.getTime() + 60000);
            return interim;
        }
    }

    useEffect(() => {
        globals.api.clock().then(v => setClock(v));
    }, [props.day, props.schedule, props.town]);

    useLayoutEffect(() => {
        if (!clock) return;

        if (serverTimeElement.current) {
            const date = new Date();
            const tsOffset = 1000 * (clock.offset + ((new Date()).getTimezoneOffset() * 60));

            date.setTime(date.getTime() + tsOffset);
            serverTimeElement.current.innerText = `${ `${date.getHours()}`.padStart(2, '0') }:${ `${date.getMinutes()}`.padStart(2, '0') }`;
        }

        if (nextAttackElement.current) {
            const diff = Math.ceil(((clock.attack * 1000) - (new Date()).getTime())/60000);
            nextAttackElement.current.innerText = `~${ Math.floor(diff / 60) }:${ `${ diff % 60 }`.padStart(2, '0') }`;
        }

        const t = setTimeout(() => setTick(tick+1), tsNextFullMinute().getTime() - (new Date()).getTime());
        return () => { clearTimeout(t) }
    }, [clock, tick])

    return clock && <ul className="clock">
        <li className="town-name">{ clock.town ?? globals.strings.clock.no_town }</li>
        { clock.town && <li>
            {clock.hc && <span className="hardcore">{globals.strings.clock.hardcore}</span>}
            <span>{globals.strings.clock.day.replace('{day}', `${props.day}`)}</span>
        </li> }
        <li>
            <span ref={serverTimeElement}>{ clock.offset }</span>
            <Tooltip html={globals.strings.clock.time}/>
        </li>
        <li>
            <span ref={nextAttackElement}>{ clock.attack }</span>
            <Tooltip html={globals.strings.clock.next}/>
        </li>
    </ul>
}

const ToolWidget = (props: {mod: boolean}) => {
    const globals = useContext(Globals);

    const [tools, setTools] = useState<Tool[]>([]);

    useEffect(() => {
        globals.api.tools().then(v => setTools(v.tools));
    }, [props.mod]);

    return tools.length > 0 && <div className="tools">
        <h1><span>{ props.mod ? globals.strings.tools.admin : globals.strings.tools.community }</span></h1>
        <div className="scrollMenu">
            <ul>
                { tools.map((tool,i) => <li key={i}>
                    <a href={tool.u}>
                        <span>{tool.n}</span>
                    </a>
                </li>) }
            </ul>
        </div>
    </div>
}