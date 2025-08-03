import * as React from "react";
import {Const, Global} from "../../defaults";
import {TranslationStrings} from "./strings";
import {BaseMounter} from "../index";
import {RuinExplorationAPI} from "./api";
import {GlassFrameDecals} from "./FrameDecals";
import {MapCore} from "./Map";
import {useLayoutEffect, useRef, useState} from "react";
import {useTranslations} from "../utils";

declare var $: Global;
declare var c: Const;


interface mountProps {
    origin: number,
    theme: string,
    name: string,
    etag: string,
    reload: string,
}

export interface RuinMapGlobal {
    api: RuinExplorationAPI,
    strings: TranslationStrings|null,
    name: string,
}

export const Globals = React.createContext<RuinMapGlobal>(null);

export class HordesRuinExplorationMap extends BaseMounter<mountProps> {
    protected render(props: mountProps): React.ReactNode {
        return <HordesRuinExplorationMapWrapper {...props}/>
    }
}



const HordesRuinExplorationMapWrapper = (props: mountProps) => {

    const apiRef = useRef<RuinExplorationAPI>( new RuinExplorationAPI() );
    const strings = useTranslations(apiRef.current);

    const [size, setSize] = useState<{width: number, height: number}>(null)

    const debounce = useRef<number>(null);
    const map = useRef<HTMLDivElement>();
    const observer = useRef<ResizeObserver>(new ResizeObserver(([entry]) => {
        const {width, height} = entry.contentRect;

        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => {
            setSize({width, height})
        }, 500);
    }));

    useLayoutEffect(() => {
        const element = map.current;
        observer.current.observe(element);
        setSize({width: element.offsetWidth, height: element.offsetHeight});
        return () => observer.current.unobserve(element);
    }, []);

    return <div className="ruin_map_react">
        <div ref={map} className="map">
            <Globals.Provider value={{
                api: apiRef.current,
                strings,
                name: props.name,
            }}>
                { size && strings && <MapCore setup={{
                    h: size.height, w: size.width
                }} properties={{
                    theme: props.theme,
                    etag: props.etag,
                    reload: props.reload,
                }}/> }
            </Globals.Provider>

            <div className="frame">
                <GlassFrameDecals/>
            </div>
        </div>
        <div className="controls"></div>

    </div>
}