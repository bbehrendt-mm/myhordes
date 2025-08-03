import * as React from "react";
import {Layer, Image, Group} from "react-konva";
import {useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {Decal, ExplorationTileset} from "./api";
import {AssetHelper, ScaleHelper} from "./Map";
import Konva from "konva";

export type MapLayerShift = {
    tileset: ExplorationTileset,
    dx: number
    dy: number
    dz: number,
    shifted: boolean
}

type MapBackdropLayerProps = {
    current: ExplorationTileset,
    shifted: boolean,
    next: MapLayerShift | null,
    onShiftCompleted: ()=>void,
    onStartShift: ()=>void,
}

const decodeTile = (tile: number) => tile < 0 ? 'exit' : `${tile}`

export const MapBackdropLayer = (props: MapBackdropLayerProps) => {

    const {scaler} = useContext(ScaleHelper);

    const elementRef = useRef<Konva.Group>(null);
    const thisRef = useRef<Konva.Group>(null);
    const nextRef = useRef<Konva.Group>(null);
    const tweenRef = useRef<Konva.Tween>(null);

    useLayoutEffect(() => {
        if (!props.next) return;

        if (props.next.dx === 0 && props.next.dy === 0 && props.next.dz === 0) {

            tweenRef.current = new Konva.Tween({
                onFinish: () => props.onShiftCompleted(),
                node: nextRef.current,
                duration: 1,
                easing: Konva.Easings.EaseInOut,
                opacity: 1,
                pixelSize: 1,
            });

        } else if (props.next.dx === 0 && props.next.dy === 0 && props.next.dz !== 0) {

            tweenRef.current = new Konva.Tween({
                onFinish: () => props.onShiftCompleted(),
                node: nextRef.current,
                duration: 1,
                easing: Konva.Easings.EaseInOut,
                opacity: 1,
                pixelSize: 1,
            });

        } else {
            tweenRef.current = new Konva.Tween({
                onFinish: () => props.onShiftCompleted(),
                node: elementRef.current,
                duration: 1,
                easing: Konva.Easings.EaseInOut,
                ...scaler.xy(-props.next.dx, props.next.dy),
            });
        }

        tweenRef.current.play();

        return () => {
            tweenRef.current?.reset();
            tweenRef.current = null;
        }

    }, [props.next]);

    useLayoutEffect(() => {
        thisRef.current?.cache();
        nextRef.current?.cache();
    });

    return <>
        <Group ref={elementRef} { ...scaler.xy(0, 0) }>
            <Group ref={thisRef}>
                <MapTile shifted={ props.shifted } data={props.current} onDoorClick={ () => props.onStartShift() } />
            </Group>
            { props.next && <Group
                { ...scaler.xy(props.next.dx, -props.next.dy) }
                ref={nextRef}
                {...( (props.next.dx === 0 && props.next.dy === 0) ? {
                    opacity: 0,
                    filters: [Konva.Filters.Pixelate],
                    pixelSize: scaler.s(0.1),
                } : {
                    opacity: 1
                } )}
            >
                <MapTile shifted={props.next.shifted} data={props.next.tileset} />
            </Group> }
        </Group>
    </>

}

const MapTile = (props: { shifted: boolean, data: ExplorationTileset, onDoorClick?: ()=> void }) => {

    const {scaler} = useContext(ScaleHelper);
    const assets = useContext(AssetHelper);

    const groupRef = useRef<Konva.Group>(null);
    const hoverImageRef = useRef<Konva.Image>(null);
    const [doorHovering, setDoorHovering] = useState(false);

    const deco = props.data.deco.toString(2).padStart(32, '0').split("").reverse().join("");
    const deco_elements = [];
    for (let i = 0; i < 16; i++)
        if (deco[i] === '1') deco_elements.push( [i+1, deco[i+16] === '0' ? 'a' : 'b'] );

    let door: Decal|null = null;
    if (props.data.door) {
        if (!props.data.door.o) door = assets.theme.doors.closed[props.data.door.t];
        else if (props.data.door.l === 0) door = assets.theme.doors.open[props.data.door.t];
        else if (props.data.door.l < 0) door = assets.theme.doors.open_down[props.data.door.t];
        else if (props.data.door.l > 0) door = assets.theme.doors.open_up[props.data.door.t];
    }

    useLayoutEffect(() => {
        groupRef.current?.cache();
    });

    useLayoutEffect(() => {
        hoverImageRef.current?.cache();
    }, [door]);

    return <Group ref={groupRef}>
        <Image image={ assets.images[ assets.theme.tiles[props.shifted ? 'room' : decodeTile(props.data.tile)] ] } { ...scaler.whxy() } />
        { !props.shifted && deco_elements
            .map( ([index, variant]) => {
                const base = assets.theme.decals[`${index}${variant}`] ?? assets.theme.decals[`${index}`] ?? null;
                return base ? {...base, id: `${index}${variant}`} : null
            } )
            .filter( d => d !== null)
            .map( d => <Image key={ d.id } image={ assets.images[ d.i ] } { ...scaler.d(d) } /> )
        }
        { !props.shifted && door &&  <>
            <Image
                ref={hoverImageRef} opacity={doorHovering ? 1 : 0}
                image={ assets.images[ door.i ] } { ...scaler.d(door) }
                filters={ [Konva.Filters.Blur] }
                blurRadius={ scaler.s(0.08) }
            />
            <Image
                image={ assets.images[ door.i ] } { ...scaler.d(door) }
                onMouseEnter={() => setDoorHovering(true)} onMouseLeave={() => setDoorHovering(false)}
                onClick={ props.onDoorClick ? () => props.onDoorClick() : ()=> null }
            />
        </> }
    </Group>
}