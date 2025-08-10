import {DependencyList, MutableRefObject, useEffect, useLayoutEffect, useState} from "react";
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

function createShader(gl: WebGLRenderingContext, type: GLenum, source: string): WebGLShader|null {
    const shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);
    const success = gl.getShaderParameter(shader, gl.COMPILE_STATUS);
    if (success) {
        return shader;
    }
    console.error(gl.getShaderInfoLog(shader));
    gl.deleteShader(shader);
    return null;
}

function createProgram(gl: WebGLRenderingContext, vertexShader: WebGLShader, fragmentShader: WebGLShader): WebGLProgram|null {
    const program = gl.createProgram();
    gl.attachShader(program, vertexShader);
    gl.attachShader(program, fragmentShader);
    gl.linkProgram(program);
    const success = gl.getProgramParameter(program, gl.LINK_STATUS);
    if (success) {
        return program;
    }
    console.error(gl.getProgramInfoLog(program));
    gl.deleteProgram(program);
    return null;
}

export function useWebGL(
    input: HTMLCanvasElement[],
    output: HTMLCanvasElement|null,
    shader: string,
    deps: DependencyList = null
) {
    const [webGLActive, setWebGLActive] = useState<boolean>(false);

    // language=GLSL
    const source_v = `
        attribute vec2 a_position;
        varying vec2 v_texCoord;
        void main() {
            v_texCoord = a_position * 0.5 + 0.5;
            v_texCoord.y = 1.0 - v_texCoord.y;
            gl_Position = vec4(a_position, 0.0, 1.0);
        }
    `;

    // language=GLSL
    const source_f = `
        precision mediump float;
        varying vec2 v_texCoord;
        varying vec2 v_screenCoord;
        ${ input.map( (_,i) => `uniform sampler2D u_image_${i+1};` ).join("\n") }
        ${ shader }
    `

    useLayoutEffect(() => {
        if (
            input.length == 0 ||
            !input.reduce( (carry, canvas) => carry && canvas.height > 0 && canvas.width > 0, true ) ||
            !output
        ) return;

        const gl = output.getContext('webgl');
        if (!gl) return;

        const shader_v = createShader(gl, gl.VERTEX_SHADER, source_v);
        const shader_f = createShader(gl, gl.FRAGMENT_SHADER, source_f);
        if (!shader_v || !shader_f) return;
        const program = createProgram(gl, shader_v, shader_f);
        if (!program) return;

        const positionAttributeLocation = gl.getAttribLocation(program, "a_position");
        const imageUniformLocations = input.map( (_,i) => gl.getUniformLocation(program, `u_image_${i+1}`) );

        const positionBuffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([
            // First tri
            -1, -1,  1, -1, -1,  1,
            // Second tri
            -1,  1,  1, -1, 1,  1,
        ]), gl.STATIC_DRAW);

        const textures = input.map( (_,i) => {
            const tx = gl.createTexture();
            gl.bindTexture(gl.TEXTURE_2D, tx);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
            return tx;
        } )

        gl.viewport(0, 0, gl.canvas.width, gl.canvas.height);
        gl.useProgram(program);
        gl.enableVertexAttribArray(positionAttributeLocation);
        gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
        gl.vertexAttribPointer(positionAttributeLocation, 2, gl.FLOAT, false, 0, 0);
        input.forEach( (_,i) => gl.uniform1i(imageUniformLocations[i], i) );
        gl.clearColor(0, 0, 0, 0);

        setWebGLActive(true)

        const render = () => {
            textures.forEach( (tx,i) => {
                gl.activeTexture(gl.TEXTURE0 + i);
                gl.bindTexture(gl.TEXTURE_2D, tx);
                gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, input[i]);
            } );

            gl.clear(gl.COLOR_BUFFER_BIT);
            gl.drawArrays(gl.TRIANGLES, 0, 6);
            let error = gl.getError();
            if (error !== gl.NO_ERROR) {
                console.error(`WebGL Error ${error}`);
                setWebGLActive(false);
            }
        }

        const ref = { stop: false };

        const step = () => {
            if (ref.stop) return;
            render();
            requestAnimationFrame(step);
        }

        step();

        return () => {
            ref.stop = true;
            gl.deleteProgram(program)
            gl.deleteShader(shader_v)
            gl.deleteShader(shader_f)
            gl.deleteBuffer(positionBuffer)
            textures.forEach( tx => gl.deleteTexture(tx));
        }

    }, deps);

    return webGLActive;

}