import * as React from "react";
import {Group, Layer, Rect, Stage, Line} from "react-konva";
import {RuinMapGlobal} from "./Wrapper";
import {MutableRefObject, useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import Konva from "konva";
import {off} from "process";

interface MapSetup {
    h: number,
    w: number,
}

interface MapPosition {
    x: number,
    y: number,
}

class MapScaler {
    private w: number;
    private h: number;
    private parent: MutableRefObject<MapScaler>;

    constructor(w: number, h: number, parent: MutableRefObject<MapScaler> = null) {
        this.update(w,h,parent);
    }

    update(w: number, h: number, parent: MutableRefObject<MapScaler> = null) {
        this.w = w;
        this.h = h;
        this.parent = parent;
    }

    x(v: number = 1): number { return v * (this.parent?.current ? this.parent.current.x(this.w) : this.w); }
    y(v: number = 1): number { return v * (this.parent?.current ? this.parent.current.y(this.h) : this.h); }
    s(v: number = 1): number { return (this.x(v) + this.y(v))/2 }

    p(x: number = 1, y: number = 1): [number,number] {
        return [this.x(x),this.y(y)];
    }

    pl(list: [number,number][]): number[] {
        return list.map( p => this.p(...p) ).reduce( (c: number[], p) => [...c, p[0], p[1]], [] )
    }

    wh(x: number = 1, y: number = 1): {height: number, width: number} {
        return {
            width: this.x(x),
            height: this.y(y),
        }
    }

    xy(x: number = 1, y: number = 1): {x: number, y: number} {
        return {
            x: this.x(x),
            y: this.y(y),
        }
    }

    centerAt(x: number, y: number, w: number, h: number): {height: number, width: number, x: number, y: number, offset: {x: number, y: number}} {
        return {
            offset: this.xy(w/2,h/2),
            ...this.wh(w,h),
            ...this.xy(x,y),
        }
    }
}

const ScaleHelper = React.createContext<{ scaler: MapScaler, ref: MutableRefObject<MapScaler> }>(null);

export const MapCore = (props: {setup: MapSetup, position: MapPosition}) => {
    const scaler = useRef<MapScaler>( new MapScaler(props.setup.w, props.setup.h) );

    const [etag, setEtag] = useState<number>(0);

    useEffect(() => {
        scaler.current.update(props.setup.w, props.setup.h);
        setEtag(e => e+1);
    }, [props.setup.w, props.setup.h]);

    //return <canvas height={props.setup.h} width={props.setup.w}/>
    return <Stage className="canvas" height={props.setup.h} width={props.setup.w}>
        <ScaleHelper.Provider value={ {scaler: scaler.current, ref: scaler} }>
            <LayerUI/>
        </ScaleHelper.Provider>
    </Stage>
}

interface MovementArrowProps {
    visible: boolean,
    rotation: 0|90|180|270,
}

const MovementArrow = (props: MovementArrowProps) => {
    const {scaler, ref} = useContext(ScaleHelper);

    const [visible, setVisible] = useState(false);

    const elementRef = useRef<Konva.Group>(null);
    const tweenRef = useRef<Konva.Tween>(null);

    const dim = [0.28, 0.10];

    const offscreenProps = {
        opacity: 0,
        ...((props.rotation === 0 || props.rotation === 180)
                ? scaler.centerAt( 0.5, props.rotation === 0 ? 0 : 1, dim[0], dim[1] )
                : scaler.centerAt( props.rotation === 90 ? 1 : 0, 0.5, dim[0], dim[1] )
        )
    }

    const oncreenProps = {
        opacity: 1,
        ...((props.rotation === 0 || props.rotation === 180)
                ? scaler.centerAt( 0.5, props.rotation === 0 ? 0.1 : 0.9, dim[0], dim[1] )
                : scaler.centerAt( props.rotation === 90 ? 0.9 : 0.1, 0.5, dim[0], dim[1] )
        )
    }

    const subScaler = useRef<MapScaler>( new MapScaler( dim[0], dim[1], ref ) );

    useLayoutEffect(() => {
        if (props.visible === visible) return;

        tweenRef.current = new Konva.Tween({
            onFinish: () => setVisible(props.visible),
            node: elementRef.current,
            duration: 0.5,
            easing: Konva.Easings.EaseInOut,
            opacity: (props.visible ? oncreenProps : offscreenProps).opacity,
            x: (props.visible ? oncreenProps : offscreenProps).x,
            y: (props.visible ? oncreenProps : offscreenProps).y,
        });
        tweenRef.current.play();

        return () => {
            tweenRef.current?.finish();
            tweenRef.current = null;
        }

    }, [props.visible]);

    return <Group ref={elementRef} rotation={props.rotation} onClick={() => console.log('click', props.rotation)} {...(visible ? oncreenProps : offscreenProps)}>
        <ScaleHelper.Provider value={{scaler: subScaler.current, ref: subScaler}}>
            <MovementChevron/>
        </ScaleHelper.Provider>
    </Group>
}

const MovementChevron = () => {
    const {scaler} = useContext(ScaleHelper);

    const [hover, setHover] = useState(false);

    return <Line
        onMouseEnter={() => setHover(true)} onMouseLeave={() => setHover(false)}
        fill={`rgba(215,255,91,${hover ? 0.7 : 0.3})`} stroke={`rgba(215,255,91,${hover ? 0.9 : 0.7})`}
        strokeWidth={scaler.s(0.02)} lineJoin="round"
        points={ scaler.pl([ [0,1], [0.5,0], [1,1] ]) } closed
    />
}

const LayerUI = () => {
    return <Layer>
        <MovementArrow visible={true} rotation={0}/>
        <MovementArrow visible={true} rotation={90}/>
        <MovementArrow visible={true} rotation={180}/>
        <MovementArrow visible={true} rotation={270}/>
    </Layer>
}
