import * as React from "react";
import {Group, Layer, Rect, Line, Image, Text} from "react-konva";
import {useContext, useLayoutEffect, useRef, useState} from "react";
import Konva from "konva";
import {MovementControls} from "./api";
import {AssetHelper, ScaleHelper} from "./Map";
import {MapScaler} from "./scaler";
import {Globals} from "./Wrapper";
import {useCountdown} from "../utils";
import {GifImage} from "../konva-utils";
import {Global} from "../../defaults";

declare var $: Global;

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
    const assets = useContext(AssetHelper);

    const [hover, setHover] = useState(false);

    const elementRef = useRef<Konva.Group>(null);

    useLayoutEffect(() => {
        elementRef.current.cache();
    }, [scaler.cache(), hover]);

    return <Group ref={elementRef}>
        <Line
            onMouseEnter={() => setHover(true)} onMouseLeave={() => setHover(false)}
            fill="transparent" stroke="transparent"
            strokeWidth={scaler.s(0.02)} lineJoin="round"
            points={ scaler.pl([ [0,1], [0.5,0], [1,1] ]) } closed
        />
        <Group listening={false}>
            { hover && <Image listening={false} image={ assets.images[assets.theme.ui.arrow_2] } { ...scaler.whxy() } /> }
            { !hover && <>
                <Image image={ assets.images[assets.theme.ui.arrow_0] } { ...scaler.whxy() } />
                <Image image={ assets.images[assets.theme.ui.arrow_1] } { ...scaler.whxy() } />
            </> }
        </Group>
    </Group>

    //return <Line
    //    onMouseEnter={() => setHover(true)} onMouseLeave={() => setHover(false)}
    //    fill={`rgba(215,255,91,${hover ? 0.7 : 0.3})`} stroke={`rgba(215,255,91,${hover ? 0.9 : 0.7})`}
    //    strokeWidth={scaler.s(0.02)} lineJoin="round"
    //    points={ scaler.pl([ [0,1], [0.5,0], [1,1] ]) } closed
    ///>
}

type LayerUIFrameProps = {
    timeout: number,
    activity: number,
    direction: number|null,
}

const UIFrame = (props: LayerUIFrameProps) => {
    const globals = useContext(Globals);
    const {scaler} = useContext(ScaleHelper);
    const assets = useContext(AssetHelper);

    const [timeout, setTimeout] = useState(`${Math.floor(Math.max(0, props.timeout/3))}`);
    const [activity, setActivity] = useState(props.activity);
    const [direction, setDirection] = useState(props.direction);

    const tweenRef = useRef<Konva.Tween>(null);
    const tweenRefRotation = useRef<Konva.Tween>(null);
    const elementRef = useRef<Konva.Image>(null);
    const elementRefRotation = useRef<Konva.Image>(null);

    const statics = useRef<Konva.Group>();

    useLayoutEffect(() => {
        statics.current.cache();
        elementRefRotation.current?.cache();
    }, [scaler.cache()]);

    useLayoutEffect(() => {
        if (props.activity === activity) return;

        tweenRef.current = new Konva.Tween({
            onFinish: () => setActivity(props.activity),
            node: elementRef.current,
            duration: 0.2,
            easing: Konva.Easings.Linear,
            y: scaler.y( 0.894 + (0.090 * (1-props.activity)) ),
            h: scaler.y( 0.090 * props.activity),
        });
        tweenRef.current.play();

        return () => {
            tweenRef.current?.finish();
            tweenRef.current = null;
        }

    }, [props.activity]);

    useLayoutEffect(() => {
        if (props.direction === direction) return;

        if (!elementRefRotation.current) {
            setDirection(props.direction);
            return;
        }

        tweenRefRotation.current = new Konva.Tween({
            onFinish: () => setDirection(props.direction),
            node: elementRefRotation.current,
            duration: 1,
            easing: Konva.Easings.EaseInOut,
            rotation: props.direction,
        });
        tweenRefRotation.current.play();

        return () => {
            tweenRefRotation.current?.finish();
            tweenRefRotation.current = null;
        }

    }, [props.direction]);

    useCountdown(
        props.timeout * 1000,
        ms => `${Math.floor(ms/3000)}`,
        (ms, s) => {
            if (ms <= 0) $.ajax.load(null, globals.reload, false);
            else setTimeout(s)
        },
        250,
        [props.timeout],
        true
    )

    const h = 0.090 * activity;

    return <Group listening={false}>
        <Group ref={statics}>
            <Image image={ assets.images[assets.theme.ui.frame] } { ...scaler.whxy() } />
            <Text text={ globals.name } lineHeight={0.6} align="left" verticalAlign="top" fill="#205319" fontStyle="bold" fontFamily="visitor2" fontSize={ scaler.s(0.045) } { ...scaler.whxy(0.398, 0.085, 0.02, 0.015) } />
            <Text text={ `${globals.strings.common.oxygen}:` } lineHeight={0.6}  align="right" verticalAlign="top" fill="#205319" fontStyle="bold" fontFamily="visitor2" fontSize={ scaler.s(0.045) } { ...scaler.whxy(0.398, 0.085, 0.585, 0.015) } />
            <Image image={ assets.images[assets.theme.ui.oxygen] }  { ...scaler.whxy(0.057, 0.057, 0.92, 0.058) } />
        </Group>
        <GifImage imageRef={elementRef} src={ assets.theme.ui.scanner } { ...scaler.whxy( 0.13, h, 0.850, 0.894 + (0.090 - h)) } />
        { props.direction !== null && <Image ref={elementRefRotation} image={ assets.images[assets.theme.ui.suggest] } { ...scaler.centerAt(0.5, 0.5, 1, 1) }
                                             rotation={ direction ?? props.direction }
                                             filters={ [ Konva.Filters.HSV ] } hue={-90}
        /> }
        <Text text={ `${timeout}` } lineHeight={0.6}  align="right" verticalAlign="top" fill="#d7ff5b" fontStyle="bold" fontFamily="visitor2" fontSize={ scaler.s(0.07) } { ...scaler.whxy(0.325, 0.085, 0.585, 0.055) } />
    </Group>
}

type LayerUIProps = {
    timeout: number,
    activity: number,
    direction: number|null,
    onMove: (dx: number, dy: number) => void,
    controls?: MovementControls|null
}

export const LayerUI = (props: LayerUIProps) => {
    return <Layer>
        <MovementArrow onClick={() => props.onMove(0, 1)} visible={props.controls?.n} rotation={0}/>
        <MovementArrow onClick={() => props.onMove(1, 0)} visible={props.controls?.e} rotation={90}/>
        <MovementArrow onClick={() => props.onMove(0,-1)} visible={props.controls?.s} rotation={180}/>
        <MovementArrow onClick={() => props.onMove(-1,0)} visible={props.controls?.w} rotation={270}/>
        <UIFrame timeout={ props.timeout } activity={ props.activity } direction={ props.direction } />
    </Layer>
}