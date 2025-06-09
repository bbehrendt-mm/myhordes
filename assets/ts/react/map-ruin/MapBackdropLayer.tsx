import * as React from "react";
import {Layer, Image, Group} from "react-konva";
import {useContext, useEffect, useLayoutEffect, useRef} from "react";
import { ExplorationTileset } from "./api";
import {AssetHelper, ScaleHelper} from "./Map";
import Konva from "konva";

export type MapLayerShift = {
    tileset: ExplorationTileset,
    dx: number
    dy: number
    shifted: boolean
}

type MapBackdropLayerProps = {
    current: ExplorationTileset,
    next: MapLayerShift | null,
    onShiftCompleted: ()=>void,
}

const decodeTile = (tile: number) => tile < 0 ? 'exit' : `${tile}`

export const MapBackdropLayer = (props: MapBackdropLayerProps) => {

    const {scaler} = useContext(ScaleHelper);

    const elementRef = useRef<Konva.Group>(null);
    const tweenRef = useRef<Konva.Tween>(null);

    useLayoutEffect(() => {
        if (!props.next) return;

        tweenRef.current = new Konva.Tween({
            onFinish: () => props.onShiftCompleted(),
            node: elementRef.current,
            duration: 1,
            easing: Konva.Easings.EaseInOut,
            ...scaler.xy(-props.next.dx, props.next.dy),
        });
        tweenRef.current.play();

        return () => {
            tweenRef.current?.reset();
            tweenRef.current = null;
        }

    }, [props.next]);

    return <Layer>
        <Group ref={elementRef} { ...scaler.xy(0, 0) }>
            <MapTile {...props.current} />
            { props.next && <Group { ...scaler.xy(props.next.dx, -props.next.dy) }>
                <MapTile {...props.next.tileset} />
            </Group> }
        </Group>
    </Layer>

}

const MapTile = (data: ExplorationTileset) => {

    const {scaler} = useContext(ScaleHelper);
    const assets = useContext(AssetHelper);

    const deco = data.deco.toString(2).padStart(32, '0').split("").reverse().join("");
    const deco_elements = [];
    for (let i = 0; i < 16; i++)
        if (deco[i] === '1') deco_elements.push( [i+1, deco[i+16] === '0' ? 'a' : 'b'] );

    return <>
        <Image image={ assets.images[ assets.theme.tiles[decodeTile(data.tile)] ] } { ...scaler.wh() } { ...scaler.xy(0, 0) } />
        { deco_elements
            .map( ([index, variant]) => {
                const base = assets.theme.decals[`${index}${variant}`] ?? assets.theme.decals[`${index}`] ?? null;
                return base ? {...base, id: `${index}${variant}`} : null
            } )
            .filter( d => d !== null)
            .map( d => <Image key={ d.id } image={ assets.images[ d.i ] } { ...scaler.wh(d.w, d.h) } { ...scaler.xy(d.x, d.y) } /> )
        }
    </>
}