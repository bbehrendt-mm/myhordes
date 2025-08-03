import * as React from "react";
import {Image, Group, Rect} from "react-konva";
import {useContext, useLayoutEffect, useRef} from "react";
import {AssetHelper, ScaleHelper} from "./Map";
import Konva from "konva";
import {GifImage} from "../konva-utils";
import {MovementControls, ZombieCount} from "./api";

export type MapLayerActorShift = {
    dx?: number
    dy?: number
    shifted?: boolean
}

type MapZombieCache = {
    x: number,
    y: number,
    l: boolean,
    d: boolean,
}

type MapLayerActorZombieProps = MapLayerActorShift & {
    zombies: ZombieCount,
    corridors: MovementControls,
    next: boolean,
    rid: string,
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

    return <>
        <Group ref={elementOuterRef} { ...scaler.xy(0, 0) }>
            <Group ref={elementInnerRef} { ...scaler.xy(0, 0) }>
                <GifImage src={ theme.actors.player } {...scaler.centerAt(0.5, 0.5, 0.0541, 0.1081)} />
            </Group>
        </Group>
    </>

}

export const MapActorZombiesLayer = (props: MapLayerActorZombieProps) => {

    const {scaler} = useContext(ScaleHelper);
    const assets = useContext(AssetHelper);

    const elementOuterRef = useRef<Konva.Group>(null);
    const tweenRefOuter = useRef<Konva.Tween>(null);

    const reset = () => {
        tweenRefOuter.current?.reset();
        tweenRefOuter.current = null;
    }

    useLayoutEffect(() => {
        if (!props?.dx && !props?.dy) return;
        reset();

        tweenRefOuter.current = new Konva.Tween({
            onFinish: () => {
                tweenRefOuter.current?.reset();
                tweenRefOuter.current = null;
            },
            node: elementOuterRef.current,
            duration: 1,
            easing: Konva.Easings.EaseInOut,
            ...(!props.next ? scaler.xy(-props.dx, props.dy) : scaler.xy(0, 0)),
        });
        tweenRefOuter.current.play();

    }, [props?.dx, props?.dy, props?.next]);

    const positions = [
        [ [0.375, 0.425], [0.375, 0.625] ], // mw
        [ [0.575, 0.625], [0.375, 0.625] ], // me
        [ [0.375, 0.625], [0.375, 0.425] ], // mn
        [ [0.375, 0.625], [0.575, 0.625] ], // ms

        ...( props.corridors?.w ? [ [ [0.175, 0.375], [0.375, 0.625] ] ] : [] ),
        ...( props.corridors?.e ? [ [ [0.625, 0.825], [0.375, 0.625] ] ] : [] ),
        ...( props.corridors?.n ? [ [ [0.375, 0.625], [0.175, 0.375] ] ] : [] ),
        ...( props.corridors?.s ? [ [ [0.375, 0.625], [0.625, 0.825] ] ] : [] ),
    ];

    const ss_key = `mh_rex_zc_${props.rid}`
    let cache: Array<MapZombieCache> = [];
    if ( (props.zombies.active + props.zombies.killed) > 0 ) {
        try {
            cache = JSON.parse( window.sessionStorage.getItem(ss_key) ?? '[]' );
        } catch (e) {}

        if (cache.filter(v => v.d).length !== props.zombies.killed || cache.filter(v => !v.d).length !== props.zombies.active) {

            if ( cache.length === (props.zombies.active + props.zombies.killed) ) {

                cache = cache.map( (v, i) => {
                    return { ...v, d: i >= props.zombies.active }
                } );

            } else {
                cache = [];
                for (let i = 0; i < (props.zombies.active + props.zombies.killed); i++) {
                    const p = positions[ Math.floor((Math.random()*positions.length)) ];
                    cache.push({
                        x: Math.random() * (p[0][1] - p[0][0]) + p[0][0],
                        y: Math.random() * (p[1][1] - p[1][0]) + p[1][0],
                        l: Math.random() > 0.5,
                        d: i >= props.zombies.active
                    })
                }
            }

            try {
                window.sessionStorage.setItem(ss_key, JSON.stringify(cache));
            } catch (e) {}
        }
    }
    else window.sessionStorage.removeItem(ss_key);


    // { positions.map(([[x_min,x_max],[y_min,y_max]], key) => <Rect key={`${key}-dbg`} fill="#ffffff" opacity={0.5} { ...scaler.whxy(x_max - x_min, y_max - y_min, x_min, y_min) } />) }

    return <>
        <Group ref={elementOuterRef} { ...(props.next ? scaler.xy(props.dx, -props.dy) : scaler.xy(0, 0)) }>
            { cache.filter(z => z.d).map( (z, key) => <Image key={`${key}-d`}
                image={ assets.images[z.l ? assets.theme.actors.zombie_dead_local : assets.theme.actors.zombie_dead] }
                {...scaler.centerAt( z.x, z.y, 0.0813, 0.120)}
            /> ) }
            { cache.filter(z => !z.d).map( (z, key) => <GifImage key={`${key}-a`}
                src={ z.l ? assets.theme.actors.zombie_local : assets.theme.actors.zombie }
                {...scaler.centerAt( z.x, z.y, 0.0813, 0.120)}
            /> ) }
        </Group>
    </>

}