import * as React from "react";
import {Layer, Rect, Stage, Image} from "react-konva";
import {createContext, MutableRefObject, useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {AssetResponse, ExplorationTileset, ZoneResponse} from "./api";
import {Globals} from "./Wrapper";
import {LayerUI} from "./MapUILayer";
import {MapScaler} from "./scaler";
import {MapBackdropLayer, MapLayerShift} from "./MapBackdropLayer";

interface MapSetup {
    h: number,
    w: number,
}

interface MapProperties {
    theme: string
}

export const ScaleHelper = createContext<{ scaler: MapScaler, ref: MutableRefObject<MapScaler> }>(null);

export const AssetHelper = createContext<{ theme: AssetResponse, images: {[key: string]: HTMLImageElement}}>(null)

export const MapCore = (props: {setup: MapSetup, properties: MapProperties}) => {
    const globals = useContext(Globals);

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
            const sources = [];
            Object.values(a.tiles).forEach( src => sources.push(src) );
            Object.values(a.doors).forEach( s => Object.values(s).forEach( src => sources.push(src) ) );
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
        globals.api.zone().then(z => setCurrentZone(z) );
    }, []);

    const ready = currentZone && themeImages && themeAssets && missingImages === 0;

    //return <canvas height={props.setup.h} width={props.setup.w}/>
    return <Stage className="canvas" height={props.setup.h} width={props.setup.w}>
        <ScaleHelper.Provider value={ {scaler: scaler.current, ref: scaler} }>
            { ready && <>
                <AssetHelper.Provider value={{ theme: themeAssets, images: themeImages }}>
                    <MapBackdropLayer
                        current={currentZone.tileset}
                        next={nextZone?.s ?? null}
                        onShiftCompleted={() => {
                            setCurrentZone( prev => { return {...prev, tileset: nextZone.r.tileset } } );
                            setNextZone(null);
                        }}
                    />
                    <LayerUI onMove={ (dx, dy) => {
                        globals.api.move(dx, dy).then(r => {
                            setCurrentZone( prev => { return { ...prev, status: r.status } } )
                            setNextZone( { r, s: { dx, dy, tileset: r.tileset, shifted: false } } )
                            document
                                .querySelectorAll('hordes-inventory[data-inventory-b-type="desert"]')
                                .forEach( e => (e as HTMLElement).dataset.inventoryBId = `${r.status.floor}` )
                        })
                     } }/>
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