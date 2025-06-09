import * as React from "react";
import {Group, Layer, Rect, Line, Image} from "react-konva";
import {useContext, useLayoutEffect, useRef, useState} from "react";
import Konva from "konva";
import {ExplorationTileset} from "./api";
import {ScaleHelper} from "./Map";
import {MapScaler} from "./scaler";

interface MapSetup {
    h: number,
    w: number,
}

interface MapProperties {
    theme: string
}


interface MovementArrowProps {
    visible: boolean,
    rotation: 0|90|180|270,
    onClick?: () => void,
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

    return <Group ref={elementRef} rotation={props.rotation} onClick={props.onClick} {...(visible ? oncreenProps : offscreenProps)}>
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

type LayerUIProps = {
    onMove: (dx: number, dy: number) => void,
}

export const LayerUI = (props: LayerUIProps) => {
    return <Layer>
        <MovementArrow onClick={() => props.onMove(0, 1)} visible={true} rotation={0}/>
        <MovementArrow onClick={() => props.onMove(1, 0)} visible={true} rotation={90}/>
        <MovementArrow onClick={() => props.onMove(0,-1)} visible={true} rotation={180}/>
        <MovementArrow onClick={() => props.onMove(-1,0)} visible={true} rotation={270}/>
    </Layer>
}