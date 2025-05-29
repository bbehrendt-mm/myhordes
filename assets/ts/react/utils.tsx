import {DependencyList, useEffect, useState} from "react";
import {Tooltip} from "./tooltip/Wrapper";
import * as React from "react";
import {VaultItemEntry} from "../v2/typedef/vault_td";
import {TranslatableAPI} from "./index";

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
        { props.data?.desc ?? '???' }
        { props.children ?? null }
    </Tooltip>
}