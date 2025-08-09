import * as React from "react";
import {Layer, Rect, Stage, Image} from "react-konva";
import {createContext, MutableRefObject, useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {AssetResponse, ExplorationTileset, ZoneResponse} from "./api";
import {Globals} from "./Wrapper";
import {LayerUI} from "./MapUILayer";
import {MapScaler} from "./scaler";
import {MapBackdropLayer, MapLayerShift} from "./MapBackdropLayer";
import {MapActorPlayerLayer, MapActorZombiesLayer} from "./MapActorLayer";
import {MapFOWLayer} from "./MapFOWLayer";
import {Global} from "../../defaults";
import {useSignal} from "../../v2/client-modules/Signal";

declare var $: Global;

interface MapSetup {
    h: number,
    w: number,
}

interface MapProperties {
    theme: string,
    etag: string,
    reload: string
}

export const ScaleHelper = createContext<{ scaler: MapScaler, ref: MutableRefObject<MapScaler> }>(null);

export const AssetHelper = createContext<{ theme: AssetResponse, images: {[key: string]: HTMLImageElement}}>(null)

export const MapCore = (props: {setup: MapSetup, properties: MapProperties}) => {
    const globals = useContext(Globals);

    const progressing = useRef(false);

    const scaler = useRef<MapScaler>( new MapScaler(props.setup.w, props.setup.h) );
    const [etag, setEtag] = useState<number>(0);

    const [currentZone, setCurrentZone] = useState<ZoneResponse>();
    const [nextZone, setNextZone] = useState<{r: ZoneResponse, s: MapLayerShift}>();

    const imageCache = useRef<{[key: string]: HTMLImageElement}>({});
    const [themeAssets, setThemeAssets] = useState<AssetResponse>();
    const [themeImages, setThemeImages] = useState<{[key: string]: HTMLImageElement}>();
    const [missingImages, setMissingImages] = useState<number>(-1);
    const [totalImages, setTotalImages] = useState<number>(-1);

    useEffect(() => {
        globals.api.assets(props.properties.theme).then(a => {
            setThemeAssets(a);
            const sources = [a.fog[0], a.fog[1]];
            [
                ...Object.values(a.tiles),
                ...Object.values(a.ui),
                ...Object.values(a.actors),
            ].forEach( src => sources.push(src) );
            Object.values(a.doors).forEach( s => Object.values(s).forEach( d => sources.push(d.i) ) );
            Object.values(a.decals).forEach( s => sources.push(s.i) );

            setMissingImages( sources.length );
            setTotalImages( sources.length );

            sources.forEach( src => {
                const loaded = () => {
                    setMissingImages( s=> s - 1);
                    imageCache.current[src] = image;
                }

                const image = document.createElement('img');
                image.src = src;
                image.onload = loaded;
                image.onerror = loaded;
            } )
        });

        return () => {
            imageCache.current = {};
            setThemeAssets(null);
            setMissingImages(-1);
            setTotalImages(-1);
        }
    }, [props.properties.theme]);

    useEffect(() => {
        if (missingImages === 0) {
            setThemeImages(imageCache.current);
            return () => setThemeImages(null);
        }
    }, [missingImages]);

    useEffect(() => {
        scaler.current.update(props.setup.w, props.setup.h);
        setEtag(e => e+1);
    }, [props.setup.w, props.setup.h]);

    useEffect(() => {
        if (!progressing.current) {
            globals.api.zone().then(z => setCurrentZone((prev) => {
                if (!prev) return z;
                else if ( z.status.shifted !== prev.status.shifted || z.status.floor !== prev.status.floor ) {
                    updateZone(z, 0, 0, z.status.floor - prev.status.floor, false);
                    return prev;
                } else return z;
            }));
        }
        progressing.current = false;
    }, [props.properties.etag]);

    const ready = currentZone && themeImages && themeAssets && missingImages === 0;

    const updateZone = (r: ZoneResponse, dx: number = 0, dy: number = 0, dz: number = 0, chain = true) => {
        setNextZone( { r, s: { dx, dy, dz, tileset: r.tileset, shifted: r.status.shifted } } )
        if (!chain) return;
        if (
            r.status.zombies.active > 0 || r.tileset.door || r.tileset.tile < 0 ||
            currentZone.status.zombies.active > 0 || currentZone.tileset.door || currentZone.tileset.tile < 0
        ) {
            progressing.current = true;
            $.ajax.load(null, props.properties.reload, false);
        }
        else document
            .querySelectorAll('hordes-inventory[data-inventory-b-type="desert"]')
            .forEach( e => (e as HTMLElement).dataset.inventoryBId = `${r.status.floor}` )
    }

    const shift = () => {
        if (!currentZone.tileset.door || (!currentZone.status.shifted && currentZone.status.move === null)) return;
        progressing.current = true;

        if (currentZone.tileset.door.l != 0)
            globals.api.move(0, 0, currentZone.tileset.door.l)
                .then( (r) => updateZone(r, 0, 0, currentZone.tileset.door.l));
        else globals.api.shift( !currentZone.status.shifted ).then( (r) => updateZone(r) );
    }

    useSignal( 'eruin-map-shift', shift, [currentZone] );

    //return <canvas height={props.setup.h} width={props.setup.w}/>
    return <Stage className="canvas" height={props.setup.h} width={props.setup.w}>
        <ScaleHelper.Provider value={ {scaler: scaler.current, ref: scaler} }>
            { ready && <>
                <AssetHelper.Provider value={{ theme: themeAssets, images: themeImages }}>
                    <Layer>
                        <MapBackdropLayer
                            shifted={currentZone.status.shifted}
                            current={currentZone.tileset}
                            next={nextZone?.s ?? null}
                            onStartShift={ shift }
                            onShiftCompleted={() => {
                                setCurrentZone( prev => { return nextZone.r } );
                                setNextZone(null);
                            }}
                        />
                        { (!nextZone || ( nextZone.r.status.shifted === currentZone.status.shifted && nextZone.r.status.floor === currentZone.status.floor )) && <>
                            <MapActorZombiesLayer next={false} rid={currentZone.status.rid} zombies={currentZone.status.zombies} corridors={currentZone.status.corridors} { ...nextZone?.s ?? {} } />
                            { nextZone && <MapActorZombiesLayer next={true} rid={nextZone.r.status.rid} zombies={nextZone.r.status.zombies} corridors={nextZone.r.status.corridors} { ...nextZone.s } /> }
                        </> }
                        <MapActorPlayerLayer { ...(nextZone?.s ?? {}) } noox={ (nextZone?.r ?? currentZone).status.timeout < 60 } />
                        <MapFOWLayer shadowColor="black" shadowOpacity={0.5} shadowDistance={0.5} shadowBlur={0.5}/>
                    </Layer>

                    <LayerUI
                        timeout={ (nextZone?.r ?? currentZone).status.timeout }
                        activity={ (nextZone?.r ?? currentZone).status.activity }
                        direction={ (nextZone?.r ?? currentZone).status.exit }
                        controls={{
                            ...((nextZone?.r ?? currentZone)?.status?.move ?? {}),
                            s: ((nextZone?.r ?? currentZone)?.status?.move ?? {})?.s || (nextZone?.r ?? currentZone).status.shifted
                        }}
                        onMove={ (dx, dy) => {
                            if (currentZone.status.shifted)
                                globals.api.shift(false).then(r => updateZone(r));
                            else globals.api.move(dx, dy).then(r => updateZone(r, dx, dy));
                        } }
                    />
                </AssetHelper.Provider>
            </> }

            { !ready && <>
                <LayerLoading percent={ missingImages > 0 ? (1 - missingImages/totalImages) : 0}/>
            </> }
        </ScaleHelper.Provider>
    </Stage>
}

const LayerLoading = (props: {percent: number}) => {
    const {scaler} = useContext(ScaleHelper);

    return <Layer>
        <Rect stroke="#00ff00" fill="transparent" strokeWidth={ scaler.s(0.005) } { ...scaler.centerAt( 0.5, 0.5, 0.8, 0.075 ) } />
        <Rect fill="#00ff00" { ...scaler.centerAt( 0.5, 0.5, 0.78 * props.percent, 0.055 ) } />
    </Layer>
}