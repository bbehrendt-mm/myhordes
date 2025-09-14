import {DependencyList, MutableRefObject, useEffect, useLayoutEffect, useRef, useState} from "react";
import {Tooltip} from "./misc/Tooltip";
import * as React from "react";
import {Item} from "./inventory/api";
import {VaultItemEntry} from "../v2/typedef/vault_td";
import {sharedWorkerMessageHandler} from "../v2/init";
import {html} from "../v2/helpers";
import {element} from "prop-types";

/**
 * Generates two boolean states and a setter; the first one can be directly set by the setter, the second one will
 * become true once the setter sets a truthful value, and then never becomes false again.
 * @param {boolean} init
 */
export function useStickyToggle(init: boolean): [boolean, boolean, (v: boolean) => void] {

    const [show, setShow] = useState(init);
    const [render, setRender] = useState(init);

    return [
        show, render, (value: boolean) => {
            setShow(value);
            if (value) setRender(value);
        }
    ]
}

/**
 * Adds a collapse/slide animation to an element
 * @param init Initial state (false = collapsed)
 * @param groupRef Reference to the element to animate
 * @param elementQuerySelector Query element selector to return the element/s that need to be toggled
 * @param options Keyframe animation options. Pass a number to simply set the duration.
 * @param onBeginAnimation Callback function called on each hideable element when the animation starts. Optional.
 * @param onEndAnimation Callback function called on each hideable element when the animation ends. Optional.
 * @param toggleElementCallback Function that is used to show/hide individual elements. Default simply sets "display: none". Optional.
 */
export function useSlider(
    init: boolean,
    groupRef: MutableRefObject<HTMLElement>,
    elementQuerySelector: string,
    options:  KeyframeAnimationOptions|number,
    onBeginAnimation?: (show: boolean, index: number, element: HTMLElement) => void,
    onEndAnimation?: (show: boolean, index: number, element: HTMLElement) => void,
    toggleElementCallback?: (show: boolean, element: HTMLElement) => void,
): [boolean, boolean, boolean, (v: boolean) => void] {

    const defaultOptions: KeyframeAnimationOptions = {easing: "ease-in-out", fill: "none"};

    const settings: KeyframeAnimationOptions = typeof options !== "object"
        ? {duration: options, ...defaultOptions}
        : {...defaultOptions, ...options};

    const delay = settings.delay ?? 0;

    const setHidden = toggleElementCallback ?? ((show, element) => {
        element.style.display = show ? null : "none";
    });

    const beginAnimation = onBeginAnimation ?? (() => null);
    const endAnimation = onEndAnimation ?? (() => null);

    const [initial, setInitial] = useState<boolean>(true);

    const [state, setState] = useState(init);
    const [show, setShow] = useState(init);
    const [render, setRender] = useState(init);

    const animation = useRef<Animation>()

    useLayoutEffect(() => {
        if (!render || initial) return;
        groupRef.current.querySelectorAll(elementQuerySelector).forEach(
            (e,i) => beginAnimation( true, i, e as HTMLElement )
        );
    }, [render,initial]);

    useLayoutEffect(() => {
        if (initial) return;

        let full = 0
        let empty = 0;

        const elements = groupRef.current.querySelectorAll(elementQuerySelector);
        elements.forEach( e => setHidden( false, e as HTMLElement ) );
        empty = groupRef.current.clientHeight;
        elements.forEach( e => setHidden( true, e as HTMLElement ) );
        full = groupRef.current.clientHeight;

        animation.current = groupRef.current.animate([
            {overflow: 'hidden', height: `${show ? empty : full}px`},
            {overflow: 'hidden', height: `${show ? full : empty}px`},
        ], {...settings, delay: 0});

        animation.current.addEventListener('finish', () => {
            animation.current.commitStyles();
            elements.forEach( (e,i) => endAnimation( show, i, e as HTMLElement ) );
            window.setTimeout(() => {
                animation.current = null;
                groupRef.current.style.overflow = null;
                groupRef.current.style.height = null;
            }, 16)
            if (!show) setRender(false);
        })

    }, [show,initial]);

    return [
        state, show, render, (value: boolean) => {
            if (show !== state || animation.current) return;

            const f = () => {
                setInitial(false);
                setShow(value);
                if (value) setRender(true);
            }

            setState(value);
            if (render && !value)
                groupRef.current.querySelectorAll(elementQuerySelector).forEach( (e,i) => beginAnimation( value, i, e as HTMLElement ) );

            if (delay > 0)
                window.setTimeout(f, delay)
            else f();

        }
    ]
}

export function ItemTooltip(props: {
    data: VaultItemEntry,
    addendum?: {className: string, text: string}|false|null,
    children?: any
}) {
    return <Tooltip additionalClasses="item">
        <h1 className="flex right large-gap">
            {props.data?.name ?? '???'}
            {props.addendum && <span className={props.addendum.className}>{props.addendum.text}</span>}
            <img style={{objectFit: 'contain'}} src={props.data?.icon ?? ''} alt={props.data?.name ?? '...'}/>
        </h1>
        <div dangerouslySetInnerHTML={{__html: props.data?.desc ?? '???'}}/>
        { props.children ?? null }
    </Tooltip>
}

export function useSharedWorkerMessages<T>(
    message: string|string[],
    callback: (data: T) => void,
    connection: string = 'live',
    deps: DependencyList = [],
) {
    if (typeof message === "string") message = [message];

    useEffect(() => {
        const messageHandlers = message.map(m => sharedWorkerMessageHandler(connection, m, callback));
        messageHandlers.forEach( m => html().addEventListener('mercureMessage', m) );
        return () => messageHandlers.forEach( m => html().removeEventListener('mercureMessage', m));
    }, deps);
}