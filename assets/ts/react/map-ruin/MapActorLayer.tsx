import * as React from "react";
import {Layer, Image, Group} from "react-konva";
import {useContext, useEffect, useLayoutEffect, useRef} from "react";
import {AssetHelper, ScaleHelper} from "./Map";
import Konva from "konva";
import {GifImage} from "../konva-utils";

export type MapLayerActorShift = {
    dx?: number
    dy?: number
    shifted?: boolean
}

export const MapActorPlayerLayer = (props: MapLayerActorShift) => {

    const {scaler} = useContext(ScaleHelper);
    const {theme} = useContext(AssetHelper);

    const elementOuterRef = useRef<Konva.Group>(null);
    const elementInnerRef = useRef<Konva.Group>(null);

    const tweenRefInner = useRef<Konva.Tween>(null);
    const tweenRefOuter = useRef<Konva.Tween>(null);

    const reset = () => {
        tweenRefOuter.current?.reset();
        tweenRefOuter.current = null;
        tweenRefInner.current?.reset();
        tweenRefInner.current = null;
    }

    useLayoutEffect(() => {
        if (!props?.dx && !props?.dy) return;
        reset();

        tweenRefOuter.current = new Konva.Tween({
            node: elementOuterRef.current,
            duration: 1,
            easing: Konva.Easings.EaseInOut,
            ...scaler.xy(-props.dx, props.dy),
        });
        tweenRefOuter.current.play();

        tweenRefInner.current = new Konva.Tween({
            onFinish: () => {
                tweenRefOuter.current?.reset();
                tweenRefOuter.current = null;
                tweenRefInner.current?.reset();
                tweenRefInner.current = null;
            },
            node: elementInnerRef.current,
            duration: 1.5,
            easing: Konva.Easings.EaseInOut,
            ...scaler.xy(props.dx, -props.dy),
        });
        tweenRefInner.current.play();

    }, [props?.dx, props?.dy]);

    return <Layer>
        <Group ref={elementOuterRef} { ...scaler.xy(0, 0) }>
            <Group ref={elementInnerRef} { ...scaler.xy(0, 0) }>
                <GifImage src={ theme.actors.player } {...scaler.centerAt(0.5, 0.5, 0.0541, 0.1081)} />
            </Group>
        </Group>
    </Layer>

}