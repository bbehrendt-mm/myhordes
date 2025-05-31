import * as React from "react";
import {useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {ExternalApp, HeaderAPI, ModLink, TownClock} from "./api";
import {Global} from "../../defaults";
import {useCountdown, useSharedWorkerMessages, useStickyToggle, useTranslations} from "../utils";
import {Tooltip} from "../tooltip/Wrapper";
import Dialog from "../components/dialog";
import {randomUUIDv4} from "../../shims";
import {Globals, mountProps} from "./Wrapper";

declare var $: Global;



export const HordesHeaderClockWidget = () => {

    const globals = useContext(Globals)

    const [duringAttack, setDuringAttack] = useState(false);
    const [clockData, setClockData] = useState<TownClock>(null);
    const [timerString, setTimerString] = useState<string>(null);
    const [gameTime, setGameTime] = useState<string>(null);

    const refreshGameTime = (timestamp: number) => {
        if (!clockData) return;
        const date = new Date( timestamp );
        const dateString = `${date.getUTCHours()}:${`${date.getUTCMinutes()}`.padStart(2,'0')}`;
        if (dateString !== gameTime) setGameTime(dateString);
    }

    const refreshClock = () => {
        globals.api.clock().then(c => {
            setClockData(c);
            setDuringAttack(c.attack <= 0);
            refreshGameTime( (c.timestamp + c.offset) * 1000 )
        });
    }

    useEffect(() => {
        if (!clockData?.offset) return;
        let handler = null;
        let timeout= null;
        handler = () => {
            refreshGameTime( (new Date()).getTime() + clockData.offset * 1000 );
            timeout = setTimeout(handler, 1000);
        }
        handler();
        return () => clearTimeout(timeout);
    }, [clockData?.offset]);

    useSharedWorkerMessages<void>(['attack-changed','attack-commence','attack-completed','citizenship-changed'], () => {
        refreshClock();
    }, 'live', [])

    useSharedWorkerMessages<{app: number}>('attack-completed', ({app}) => {
        refreshClock();
    }, 'live', [])

    useEffect(() => {
        refreshClock();
    }, []);

    useCountdown(
        (clockData?.attack ?? 0) * 1000,
        ms => {
            if (duringAttack) return '';
            if (ms <= 0) return "~0:00";
            return `~${Math.floor(ms / 3600000)}:${`${Math.round((ms % 3600000) / 60000)}`.padStart(2, '0')}`
        },
        (_, s) => setTimerString(s),
        1000,
        [clockData?.attack, duringAttack]
    )

    if (!globals.strings || !clockData) return;

    return clockData.show && <>
        <div className="game-clock" data-town-id={ clockData.id ?? 'ghost' }>
            <div className="town-name">{ clockData.desc }</div>
            <div className="town-day">
                { clockData.type === 'panda' && <span className="hardcore">
                    { globals.strings.clock.panda }
                </span> }
                { clockData.day >= 0 && <span className="day-number">
                    { globals.strings.clock.day.replace('{day}', `${clockData.day}`) }
                </span> }
            </div>
            { gameTime && <div className="town-time">
                <Tooltip additionalClasses="help" textContent={ globals.strings.clock.gametime }/>
                { gameTime }
            </div> }
            { (timerString || duringAttack) && <div className={`attack-time ${duringAttack ? 'during-attack' : ''}`}>
                <Tooltip additionalClasses="help" textContent={ globals.strings.clock.attack }/>
                { duringAttack ? '!!!!' : timerString }
            </div> }
        </div>
    </>

}
