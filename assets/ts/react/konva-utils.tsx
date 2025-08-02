import {MutableRefObject, useEffect} from "react";
import * as React from "react";

import {ImageConfig} from "konva/lib/shapes/Image";
import {Image} from "react-konva"
import Konva from "konva";

declare var gifler: any;

export const GifImage = (props: Omit<ImageConfig, 'image'> & {src: string, imageRef?: MutableRefObject<Konva.Image>}) => {
    const imageRef = props.imageRef ?? React.useRef(null);
    const canvasRef = React.useRef(document.createElement('canvas'));

    useEffect(() => {
            // use external library to parse and draw gif animation
            gifler(props.src).frames(canvasRef.current, (ctx, frame) => {
                // update canvas size
                canvasRef.current.width = frame.width;
                canvasRef.current.height = frame.height;
                // update canvas that we are using for Konva.Image
                ctx.drawImage(frame.buffer, 0, 0);
                // update Konva.Image
                imageRef.current?.getLayer()?.batchDraw();
            });
    }, []);

    return (
        <Image {...props} src={null} ref={imageRef} image={canvasRef.current} />
    );
}