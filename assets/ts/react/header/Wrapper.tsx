import * as React from "react";
import {useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {HeaderAPI} from "./api";
import {TranslationStrings} from "./strings";
import {Global} from "../../defaults";
import {BaseMounter} from "../index";
import {useTranslations} from "../utils";
import {HordesHeaderAPIWidget, HordesStandaloneAppPopupWrapper} from "./Apps";
import {HordesHeaderModLinksWidget} from "./ModLinks";
import {HordesHeaderClockWidget} from "./Clock";
import {string} from "prop-types";
import {Tooltip} from "../tooltip/Wrapper";

declare var $: Global;

export interface mountProps {
    appId: number
    home: string,
}

type HeaderGlobals = {
    api: HeaderAPI,
    strings: TranslationStrings|null
}

export const Globals = React.createContext<HeaderGlobals>(null);

export class HordesHeaderUI extends BaseMounter<{}>{
    protected render(props: {}): React.ReactNode {
        return <HordesHeaderUIWrapper {...props} />;
    }
}

export class HordesSingularAppUI extends BaseMounter<mountProps>{
    protected render(props: mountProps): React.ReactNode {
        return <HordesStandaloneAppPopupWrapper {...props} />;
    }
}

const HordesHeaderUIWrapper = (props: {}) => {

    const api = useRef(new HeaderAPI());
    const strings = useTranslations(api.current);


    return <Globals.Provider value={{ api: api.current, strings }}>
        <HordesHeaderAPIWidget/>
        <HordesHeaderModLinksWidget/>
        <HordesHeaderClockWidget/>
        { strings && <div className="game-logout">
            <Tooltip additionalClasses="help" textContent={strings.logout.title}/>
            <a href={ strings.logout.url }/>
        </div> }
    </Globals.Provider>
};
