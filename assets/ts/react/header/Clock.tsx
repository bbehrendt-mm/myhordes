import * as React from "react";
import {useContext, useEffect, useState} from "react";
import {TownClock} from "./api";
import {Global} from "../../defaults";
import {useCountdown, useSharedWorkerMessages} from "../utils";
import {Tooltip} from "../misc/Tooltip";
import {Globals} from "./Wrapper";
import {useSignal} from "../../v2/client-modules/Signal";

declare var $: Global;



export const HordesHeaderClockWidget = () => {

    const globals = useContext(Globals)

    const [duringAttack, setDuringAttack] = useState(false);
    const [clockData, setClockData] = useState<TownClock>(null);
    const [timerString, setTimerString] = useState<string>(null);
    const [timerMs, setTimerMs] = useState<number>(null);
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

    useSignal(
        'web-navigation',
        () => {
            if (!duringAttack && timerMs !== null && timerMs <= 0) refreshClock();
        }, [timerMs !== null && timerMs <= 0, duringAttack]
    );

    useEffect(() => {
        refreshClock();
    }, []);

    useCountdown(
        (clockData?.attack ?? 0) * 1000,
        ms => {
            setTimerMs(ms);
            if (duringAttack) return '';
            if (ms <= 0) return "~0:00";
            return `~${Math.floor(ms / 3600000)}:${`${Math.round((ms % 3600000) / 60000)}`.padStart(2, '0')}`
        },
        (_, s) => setTimerString(s),
        1000,
        [clockData, duringAttack],
        true
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
