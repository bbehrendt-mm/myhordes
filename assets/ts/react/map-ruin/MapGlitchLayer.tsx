import * as React from "react";
import {Group, Layer, Rect, Line, Image, Text} from "react-konva";
import {useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import Konva from "konva";
import {ExplorationTileset, MovementControls} from "./api";
import {AssetHelper, ScaleHelper} from "./Map";
import {MapScaler} from "./scaler";
import {Globals} from "./Wrapper";
import {useCountdown} from "../utils";
import {GifImage} from "../konva-utils";
import * as inspector from "node:inspector";



export const LayerGlitches = (props: {enabled: boolean}) => {

    const {scaler} = useContext(ScaleHelper);

    const [c, setC] = useState(false);
    const [etag, setETag] = useState(0);
    const [displacements, setDisplacements] = useState<[string,number,number][]>([]);

    useCountdown(
        props.enabled ? 512 : -1,
        s => `${s}`,
        s => {
            if (!props.enabled) return;
            if (s <= 32) setETag(n => (n+1) % 2)

            if (c) {
                setDisplacements([]);
                setC(false);
                return;
            }

            let cc = false;
            let dc = [];
            const glitchLine = (
                color: (n: number) => string,
                chance: number, incrementChance: number, max: number, maxHeight: number, maxStrength: number = 1 ) => {
                if (Math.random() < chance) {
                    const d = [];
                    do {
                        d.push( [ color(Math.random() * maxStrength), Math.random() * maxHeight, Math.random() ] )
                    } while (d.length < max && Math.random() < incrementChance);

                    cc = true;
                    dc = [...dc, ...d];
                }
            }

            glitchLine(
                v => `rgb(${Math.ceil(255 * v)},0,0)`,
                0.2, 0.75, 14, 0.01
            );

            glitchLine(
                v => `rgb(${Math.ceil(255 * v)},0,0)`,
                0.1, 0.75, 2, 0.1
            );

            glitchLine(
                v => `rgb(0,${Math.ceil(255 * v)},0)`,
                0.1, 0.5, 3, 0.05
            );

            glitchLine(
                v => `rgb(0,${Math.ceil(255 * v)},0)`,
                0.8, 0.9, 32, 0.01, 0.1
            );

            if (cc) {
                setC(true);
                setDisplacements(dc);
            }
        },
        16,
        [etag,props.enabled]
    )

    return <Layer listening={false}>
        { props.enabled && <>
            <Group globalCompositeOperation="lighter">
                { displacements.map(([color, height, top], i) => <Rect key={`${i}`} fill={color} {...scaler.whxy(1,height,0,top)} />) }
            </Group>
        </> }
    </Layer>
}