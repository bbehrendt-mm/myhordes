import * as React from "react";
import {useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {ExternalApp, HeaderAPI} from "./api";
import {Global} from "../../defaults";
import {useSharedWorkerMessages, useStickyToggle, useTranslations} from "../utils";
import {Tooltip} from "../tooltip/Wrapper";
import Dialog from "../components/dialog";
import {randomUUIDv4} from "../../shims";
import {Globals, mountProps} from "./Wrapper";

declare var $: Global;

export const HordesStandaloneAppPopupWrapper = (props: mountProps) => {

    const api = useRef(new HeaderAPI());
    const strings = useTranslations(api.current);

    const [app, setApp] = useState<ExternalApp>(null);

    const refreshApps = () => {
        api.current.apps().then( r => setApp( r.apps.find(app => app.id === props.appId) ) )
    }

    useEffect(() => {
        refreshApps();
    }, []);

    return <Globals.Provider value={{ api: api.current, strings }}>
        { strings && app && <App app={app}  onClose={() => window.location.replace(props.home)} signalUpdate={() => refreshApps()}/> }
    </Globals.Provider>
};

const AppList = ({apps, onClick}: {apps: ExternalApp[], onClick: (app: ExternalApp) => void}) => {
    const globals = useContext(Globals)
    return apps.length > 0 ? <ul>
        { apps.sort( (a,b) =>
            ((a.testing ? 1 : 0) - (b.testing ? 1 : 0)) ||
            ((a.maintenance ? 1 : 0) - (b.maintenance ? 1 : 0)) ||
            a.name.localeCompare( b.name )
        ).map( app => <li key={app.id} className={ app.maintenance ? 'maintenance' : '' } onClick={() => onClick(app)}>
            <div><img alt={app.name} src={app.icon ?? globals.strings.apps.list.no_icon}/></div>
            <div className="label">
                <span className="name">{ app.name }</span>
                { app.testing && <span>{ globals.strings.apps.list.testMode }</span> }
                { app.dev?.sk && <span>{ globals.strings.apps.list.sk.replace('{sk}', app.dev?.sk) }</span> }
            </div>
            { app.maintenance && <Tooltip textContent={ globals.strings.apps.list.maintenance }/> }
        </li> ) }
    </ul> : null
}

export const App = ({app, onClose, signalUpdate}: {app: ExternalApp|null, onClose: () => void, signalUpdate: () => void}) => {
    const globals = useContext(Globals)

    const uuid = useRef(randomUUIDv4())
    const devForm = useRef<HTMLDivElement>()

    const [sk, setSK] = useState<string|null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => { setSK(null); }, [app?.id]);

    const save = () => {
        if (!devForm.current) return;

        setLoading(true);
        globals.api.updateApp( app.id, $.html.serializeForm( devForm.current ) ).finally( () => setLoading(false) );
    }

    const regenerate = () => {

        const n = `${Math.min(99, Math.round(Math.random() * 100))}`
        const num = prompt( globals.strings.apps.details.dev.fields.sk.help[4].replace('{num}', n), '' );
        if (num !== n) return;

        setLoading(true);
        globals.api.regenerateAppSK( app.id )
            .then(sk => {
                setSK(sk);
                signalUpdate();
            })
            .finally( () => setLoading(false) );
    }

    return <Dialog open={app !== null} onCancel={() => onClose()} onSubmit={() => requestAnimationFrame( () => onClose())}>
        { app && <>
            <div className="modal-title composed">
                <div className="flex large-gap middle">
                    {app?.icon && <img alt={app?.name} src={app?.icon}/>}
                    <span>{app?.name}</span>
                </div>

            </div>

            <div className="row external-app">
                <div className="cell padded header rw-12">
                    <p dangerouslySetInnerHTML={{__html: globals.strings.apps.details.notice}}/>
                    <ul>
                        <li>
                            <span className="critical">{globals.strings.apps.details.warning_pw_1}</span>
                            &nbsp;
                            <span>({globals.strings.apps.details.warning_pw_2})</span>
                        </li>
                        { app?.contact?.uri && <li dangerouslySetInnerHTML={{__html: globals.strings.apps.details.contact.replace('{contact}', `<a href="${app.contact.uri}" target="_blank">${app.contact.label ?? app.contact.uri}</a>`)}}/> }
                    </ul>

                    { app?.auth && <div className="secure">
                        <strong>{ globals.strings.apps.details.notice_secure_1 }</strong>
                        &nbsp;
                        <span>{ globals.strings.apps.details.notice_secure_2 }</span>
                    </div> }

                    { app?.maintenance && <div className="note note-warning">
                        <strong>{ globals.strings.apps.details.warning_maintenance_1 }</strong>
                        &nbsp;
                        <span>{ globals.strings.apps.details.warning_maintenance_2 }</span>
                    </div> }

                    <div className="forms">
                        { (!app?.auth || app?.pk) && <>
                            { <form action={ app.url } method={ app.auth ? 'post' : 'get' } target="_blank">
                                { app.auth && app.pk && <input type="hidden" name="key" value={ app.pk }/>}
                                <button type="submit" className="button" disabled={loading}>{ globals.strings.apps.details.confirm.replace('{app}', app.name) }</button>
                            </form> }
                        </> }

                        { app?.auth && !app?.pk && <form action={ app.url } method={ app.auth ? 'post' : 'get' } target="_blank">
                            <button disabled={true} type="submit" className="button">{ globals.strings.apps.details.confirm.replace('{app}', app.name) }</button>
                            <p>
                                <span className="critical">{ globals.strings.apps.details.warning_pk_1 }</span>
                                &nbsp;
                                <span dangerouslySetInnerHTML={{__html: globals.strings.apps.details.warning_pk_2}}/>
                            </p>
                        </form>}

                        <button type="button" className="button" disabled={loading} onClick={() => onClose()}>{ globals.strings.apps.details.cancel }</button>
                    </div>

                    { app?.dev && <div className="dev">
                        <h5>{globals.strings.apps.details.dev.headline}</h5>
                        <div ref={devForm}>
                            <div className="row">
                                <div className="cell padded rw-4 rw-md-12">
                                    <label htmlFor={`${uuid.current}_email`}>{ globals.strings.apps.details.dev.fields.email.title }</label>
                                    <span className="float-right">
                                        <a className="help-button">
                                            {globals.strings.apps.details.dev.help}
                                            <Tooltip additionalClasses="help">
                                                {globals.strings.apps.details.dev.fields.email.help.map((s,i) => <React.Fragment key={i}>
                                                    <span>{s}</span><br/>
                                                </React.Fragment>)}
                                            </Tooltip>
                                        </a>
                                    </span>
                                </div>
                                <div className="cell rw-8 rw-md-12">
                                    <input id={`${uuid.current}_email`} name="contact" type="text" defaultValue={app.contact.label} maxLength={190}/>
                                </div>
                            </div>

                            <div className="row">
                                <div className="cell padded rw-4 rw-md-12">
                                    <label htmlFor={`${uuid.current}_url`}>{ globals.strings.apps.details.dev.fields.url.title }</label>
                                    <span className="float-right">
                                        <a className="help-button">
                                            {globals.strings.apps.details.dev.help}
                                            <Tooltip additionalClasses="help">
                                                {globals.strings.apps.details.dev.fields.url.help.map((s,i) => <React.Fragment key={i}>
                                                    <span>{s}</span><br/>
                                                </React.Fragment>)}
                                            </Tooltip>
                                        </a>
                                    </span>
                                </div>
                                <div className="cell rw-8 rw-md-12">
                                    <input id={`${uuid.current}_url`} name="url" type="url" defaultValue={app.url} maxLength={190}/>
                                </div>
                            </div>

                            <div className="row">
                                <div className="cell padded rw-4 rw-md-12">
                                    <label htmlFor={`${uuid.current}_dev_url`}>{ globals.strings.apps.details.dev.fields.dev_url.title }</label>
                                    <span className="float-right">
                                        <a className="help-button">
                                            {globals.strings.apps.details.dev.help}
                                            <Tooltip additionalClasses="help">
                                                {globals.strings.apps.details.dev.fields.dev_url.help.map((s,i) => <React.Fragment key={i}>
                                                    <span>{s}</span><br/>
                                                </React.Fragment>)}
                                            </Tooltip>
                                        </a>
                                    </span>
                                </div>
                                <div className="cell rw-8 rw-md-12">
                                    <input id={`${uuid.current}_dev_url`} name="dev_url" type="url" defaultValue={app.dev.url} maxLength={190}/>
                                    { app.dev?.url && <form action={ app.dev.url } method={ app.auth ? 'post' : 'get' } target="_blank">
                                        { app.auth && app.pk && <input type="hidden" name="key" value={ app.pk }/>}
                                        <button type="submit" className="inline small float-right" disabled={loading}>{ globals.strings.apps.details.confirm.replace('{app}', app.name) }</button>
                                    </form> }
                                </div>
                            </div>

                            { app.auth && <div className="row">
                                <div className="cell padded rw-4 rw-md-12">
                                    <label htmlFor={`${uuid.current}_sk`}>{ globals.strings.apps.details.dev.fields.sk.title }</label>
                                    <span className="float-right">
                                        <a className="help-button">
                                            {globals.strings.apps.details.dev.help}
                                            <Tooltip additionalClasses="help">
                                                <span>{ globals.strings.apps.details.dev.fields.sk.help[0] }</span>
                                                &nbsp;
                                                <span className="critical">{ globals.strings.apps.details.dev.fields.sk.help[1] }</span>
                                                <br/><br/>
                                                <span>{ globals.strings.apps.details.dev.fields.sk.help[2] }</span>
                                            </Tooltip>
                                        </a>
                                    </span>
                                </div>
                                <div className="cell rw-8 rw-md-12">
                                    <input id={`${uuid.current}_sk`} name="sk" type="text" readOnly={true} value={sk ?? app.dev.sk}/>
                                    <button type="button" className="inline small float-right" disabled={loading} onClick={() => regenerate()}>{ globals.strings.apps.details.dev.fields.sk.help[3] }</button>
                                </div>
                            </div> }

                            <div className="row">
                                <div className="padded cell rw-12">
                                    <button type="button" className="button" disabled={loading} onClick={() => save()}>{ globals.strings.apps.details.dev.save }</button>
                                </div>
                            </div>
                        </div>
                    </div>}
                </div>
            </div>
        </>}

    </Dialog>
}


export const HordesHeaderAPIWidget = () => {

    const globals = useContext(Globals)

    const root = useRef<HTMLDivElement>();
    const animation = useRef<Animation>()

    const [show, render, setRender] = useStickyToggle(false);

    const [appList, setAppList] = useState<ExternalApp[]>([]);
    const [selectedApp, setSelectedApp] = useState<ExternalApp>(null);

    const [outdatedAppList, setOutdatedAppList] = useState<(number)[]>([]);

    const refreshApps = () => {
        globals.api.apps().then(r => {
            setOutdatedAppList([]);
            setAppList(r.apps ?? []);
            if (selectedApp?.id) setSelectedApp( r.apps.find(app => app.id === selectedApp.id) ?? null );
        })
    }

    const refreshApp = (app: number|null) => {
        if (!app) return;
        globals.api.app(app).then(new_app => {
            setOutdatedAppList(a => a.filter(id => id !== app && id !== new_app.id))
            setAppList(list => {
                return [
                    ...list.filter(a => a.id !== app && a.id !== new_app.id),
                    new_app,
                ]
            });
            if (selectedApp?.id === new_app.id) setSelectedApp( new_app );
        })
    }

    useEffect(() => {
        refreshApps();
    }, []);

    useSharedWorkerMessages<{app: number}>('external-app-update', ({app}) => {
        if (selectedApp && selectedApp.id === app) refreshApp(app);
        else if ( !outdatedAppList.includes( app ) ) setOutdatedAppList( a => [...a, app] );
    }, 'live', [selectedApp])

    useSharedWorkerMessages<{app: number}>('external-app-delete', ({app}) => {
        if (selectedApp && selectedApp.id === app) setSelectedApp(null);
        if (outdatedAppList.includes( app )) setOutdatedAppList( list => list.filter(id => id !== app) );
        setAppList(list => list.filter(a => a.id !== app))
    }, 'live', [selectedApp])

    useEffect(() => {
        if (show && outdatedAppList.length > 1) refreshApps();
        else if (show && outdatedAppList.length === 1) refreshApp( outdatedAppList[0] );
    }, [show, outdatedAppList])

    useLayoutEffect(() => {
        if (!render || (animation.current && animation.current.playState !== "finished")) return;

        root.current.style.width = root.current.style.height = 'auto';
        const openBounds = root.current.getBoundingClientRect();

        const frames = show ? [
            {height: 0, width: 0},
            {height: `${openBounds.height}px`, width: `${openBounds.width}px`},
        ] : [
            {height: `${openBounds.height}px`, width: `${openBounds.width}px`},
            {height: 0, width: 0},
        ]

        const clear = () => {
            root.current.style.width = root.current.style.height = null;
        }

        animation.current = root.current.animate(frames, {
            duration: 100,
            easing: 'ease-in-out',
            fill: 'none',
        });

        animation.current.onfinish = clear;
        animation.current.oncancel = clear;
    }, [show]);

    if (appList.length === 0 || !globals.strings) return;

    const hasApps = appList.some(app => !app.wiki);
    const hasWikis = appList.some(app => app.wiki);

    return <>
        <App app={selectedApp} onClose={() => setSelectedApp(null)} signalUpdate={() => refreshApp(selectedApp?.id ?? null)}/>
        <div className={`app-directory ${show ? 'open' : 'closed'}`} ref={root}
             onMouseOver={() => setRender(true)} onClick={() => setRender(!show)}
             onMouseOut={() => setRender(false)}
        >
            <img alt={globals.strings.apps.list.headline} src={globals.strings.apps.list.icon} className="app-icon"/>
            {render && <div className="app-listing-body">
                <h4>{globals.strings.apps.list.headline}</h4>
                <p>{globals.strings.apps.list.description}</p>
                {hasApps && <AppList apps={appList.filter(app => !app.wiki)} onClick={app => setSelectedApp(app)}/>}
                {hasApps && hasWikis && <hr/>}
                {hasWikis && <AppList apps={appList.filter(app => app.wiki)} onClick={app => setSelectedApp(app)}/>}
            </div>}
        </div>
    </>

}
