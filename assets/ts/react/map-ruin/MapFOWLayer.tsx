import * as React from "react";
import {Layer, Image, Group, Rect, Shape} from "react-konva";
import {useContext, useEffect, useLayoutEffect, useRef} from "react";
import {AssetHelper, ScaleHelper} from "./Map";
import Konva from "konva";
import {GifImage} from "../konva-utils";
import {SceneContext} from "konva/lib/Context";

export type MapFOWProperties = {
    shadowColor: string,
    shadowOpacity: number,
    shadowDistance: number,
    shadowBlur: number,
}

export const MapFOWLayer = (props: MapFOWProperties) => {

    const poisonLayers = 6;

    const {scaler} = useContext(ScaleHelper);
    const assets = useContext(AssetHelper);

    const shadowGroupRef = useRef<Konva.Group>(null);

    const poisonRefs = Array.from(Array(poisonLayers).keys()).map( () => useRef<Konva.Group>(null) );
    const poisonAnimRef = useRef(null);
    const poisonInitialRotationRef = useRef(Array.from(Array(poisonLayers).keys()).map( () => 360 * Math.random() ));

    //const shadowClipFunc = (ctx: SceneContext) => {
    //    ctx.globalCompositeOperation = 'source-over';
    //    //ctx.fillRect(0, 0, scaler.x(2), scaler.y(2))
    //    //ctx.globalCompositeOperation = 'xor';
    //    ctx.beginPath();
    //    ctx.arc( scaler.x(1), scaler.y(1), scaler.s(props.shadowDistance / 2), 0, 2 * Math.PI, false );
    //    ctx.fill();
    //    ctx.globalCompositeOperation = 'source-over';
    //}

    const shadowShapeFunc = (ctx: SceneContext, shape: Konva.Shape) => {
        ctx.beginPath();
        ctx.moveTo(0, 0)
        ctx.lineTo(scaler.x(2), 0)
        ctx.lineTo(scaler.x(2), scaler.x(2))
        ctx.lineTo(0, scaler.x(2))
        ctx.closePath();
        ctx.fillStrokeShape(shape);

        ctx.globalCompositeOperation = 'xor';
        ctx.beginPath();
        ctx.arc( scaler.x(1), scaler.y(1), scaler.s(props.shadowDistance / 2), 0, 2 * Math.PI, false );
        ctx.closePath();
        ctx.fillStrokeShape(shape);

        ctx.globalCompositeOperation = 'source-over';
    }

    useLayoutEffect(() => {
        shadowGroupRef.current?.cache();
    }, []);

    useLayoutEffect(() => {
        const rotation = [
            1,
            -2,
            3,
            -4,
            5,
            -6
        ];

        for (let i = 0; i < poisonLayers; i++)
            poisonRefs[i].current?.cache();

        poisonAnimRef.current = new Konva.Animation((frame) => {
            const diff = (frame.timeDiff) / 1000;
            for (let i = 0; i < poisonLayers; i++)
                poisonRefs[i].current.rotate(diff * rotation[i] * 3);
        });

        poisonAnimRef.current.start();
        return () => poisonAnimRef.current?.stop();

    }, [scaler.cache()]);

    return <Group listening={false}>
        <Group {...scaler.xy(0, 0)} /*globalCompositeOperation="multiply"*/>
            { Array.from( Array(poisonLayers).keys()).map( i =>
                <Group key={i} opacity={ 0.3 }>
                    <Group rotation={ poisonInitialRotationRef.current[i] } ref={poisonRefs[i]} { ...scaler.centerAt(0.5, 0.5, 1, 1)} globalCompositeOperation="color-burn">
                        <Image image={ assets.images[assets.theme.fog[0]] } {...scaler.centerAt(0.5, 0.5, 0.7 + i * 0.3, 0.7 + i * 0.3)} />
                        <Image image={ assets.images[assets.theme.fog[1]] } {...scaler.centerAt(0.5, 0.5, 2* (0.7 + i * 0.3), 2*(0.7 + i * 0.3))} />
                    </Group>
                </Group>
            ) }
        </Group>
        <Group opacity={ props.shadowOpacity } filters={[Konva.Filters.Blur]} blurRadius={ scaler.s(props.shadowBlur) } ref={shadowGroupRef} { ...scaler.xy(-0.5, -0.5) }>
            <Shape {...scaler.wh(2,2)} sceneFunc={shadowShapeFunc} fill={ props.shadowColor } />
        </Group>
    </Group>

}