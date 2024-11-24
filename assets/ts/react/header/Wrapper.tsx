import * as React from "react";
import {Global} from "../../defaults";
import {BaseMounter} from "../index";
import {useContext, useEffect, useRef, useState} from "react";
import {TranslationStrings} from "./strings";
import {App, HeaderAPI} from "./api";
import {Tooltip} from "../tooltip/Wrapper";

declare var $: Global;

interface mountProps {
    user: number
    town: number
    impersonating: boolean
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

    console.log('S', test.current, props, strings);

    return <Globals.Provider value={{api: api.current, strings}}>
        { strings && <div className="main-header">
            {props.impersonating && <ImpersonationWidget/>}
            <AppWidget user={props.user}/>
            <LogoutWidget user={props.user}/>
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

//const ClockWidget = (props: {town: number}) => {
//    const globals = useContext(Globals);
//
//    const [clock, setClock] = useState(null);
//
//    return clock && <ul className="clock">
//
//    </ul>
//}