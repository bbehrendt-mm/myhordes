import {MutableRefObject, useLayoutEffect} from "react";

export function useSlidingAnimation(show: boolean, render: boolean, animation: MutableRefObject<Animation>, root: MutableRefObject<HTMLDivElement>) {

    useLayoutEffect(() => {
        if (!render || (animation.current && animation.current.playState !== "finished")) return;

        root.current.style.width = root.current.style.height = 'auto';
        const openBounds = root.current.getBoundingClientRect();

        const frames = show ? [
            {height: 0, width: 0},
            {height: `${openBounds.height}px`, width: `${openBounds.width}px`},
        ] : [
            {height: `${openBounds.height}px`, width: `${openBounds.width}px`},
            {height: 0, width: 0},
        ]


        const clear = () => {
            root.current.style.width = root.current.style.height = null;
        }

        animation.current = root.current.animate(frames, {
            duration: 100,
            easing: 'ease-in-out',
            fill: 'none',
        });

        animation.current.onfinish = clear;
        animation.current.oncancel = clear;
    }, [show]);

}
