import {DependencyList, useEffect, useRef, useState} from "react";
import {Tooltip} from "./tooltip/Wrapper";
import * as React from "react";
import {VaultItemEntry} from "../v2/typedef/vault_td";
import {TranslatableAPI} from "./index";
import {sharedWorkerMessageHandler} from "../v2/init";
import {html} from "../v2/helpers";

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
 *
 * @param from
 * @param deps
 * @param before
 * @param after
 */
export function useTranslations<T extends object>(
    from: TranslatableAPI<T>,
    deps: DependencyList = [],
    before: () => void = () => {},
    after: (s: T) => void = () => {},
): T {
    const [strings, setStrings] = useState<T>(null);
    useEffect(() => {
        before();
        from.index().then(s => {
            setStrings(s);
            after(s);
        });
    }, deps);
    return strings;
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

type countdownRef = {
    init: boolean,
    last: number|null,
    formatted: string|null,
    remaining: number|null,
}

export function useCountdown(
    ms: number,
    formatter: (remaining: number) => string,
    callback: (remaining: number, formatted: string) => void|boolean,
    interval: number = 1000,
    deps: DependencyList = [],
) {

    const ref = useRef<countdownRef>({
        init: true,
        last: null,
        formatted: null,
        remaining: null,
    });

    useEffect(() => {
        let timeout = null;
        let handler = null;

        if (ref.current.init) {
            ref.current.init = false;
            ref.current.last = (new Date()).getTime();
            ref.current.formatted = null;
            ref.current.remaining = ms;
        }

        handler = () => {
            const now = (new Date()).getTime();

            ref.current.remaining -= (now - ref.current.last);
            ref.current.last = now;

            let do_continue = ref.current.remaining > 0;
            const formatted = formatter(ref.current.remaining);
            if (formatted !== ref.current.formatted) {
                const result = callback( ref.current.remaining, formatted );
                ref.current.formatted = formatted;
                if (result === false) do_continue = false;
            }

            if (do_continue) timeout = setTimeout(handler, interval);
        }

        handler();

        return () => {
            clearTimeout(timeout);
            ref.current.init = true;
        }
    }, deps);
}