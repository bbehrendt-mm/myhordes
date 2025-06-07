import * as React from "react";

export const GlassFrameDecals = () => {
    const component = [
        "tl", "tr", "bl", "br", "t0l", "t1", "t0r",
        "l0t", "l1", "l0m", "l0b", "l2",
        "r0t", "r1", "r0b",
        "b"
    ];

    return <div className="frame-plane">
        { component.map( (c,i) => <div key={c} className={c}/> ) }
    </div>
}