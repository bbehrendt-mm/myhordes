import {MutableRefObject} from "react";
import {Decal} from "./api";

export class MapScaler {
    private w: number;
    private h: number;
    private parent: MutableRefObject<MapScaler>;

    constructor(w: number, h: number, parent: MutableRefObject<MapScaler> = null) {
        this.update(w,h,parent);
    }

    update(w: number, h: number, parent: MutableRefObject<MapScaler> = null) {
        this.w = w;
        this.h = h;
        this.parent = parent;
    }

    cache(): string { return `${this.x(1)}-${this.y(1)}` }

    x(v: number = 1): number { return v * (this.parent?.current ? this.parent.current.x(this.w) : this.w); }
    y(v: number = 1): number { return v * (this.parent?.current ? this.parent.current.y(this.h) : this.h); }
    s(v: number = 1): number { return (this.x(v) + this.y(v))/2 }

    p(x: number = 1, y: number = 1): [number,number] {
        return [this.x(x),this.y(y)];
    }

    pl(list: [number,number][]): number[] {
        return list.map( p => this.p(...p) ).reduce( (c: number[], p) => [...c, p[0], p[1]], [] )
    }

    wh(x: number = 1, y: number = 1): {height: number, width: number} {
        return {
            width: this.x(x),
            height: this.y(y),
        }
    }

    xy(x: number = 1, y: number = 1): {x: number, y: number} {
        return {
            x: this.x(x),
            y: this.y(y),
        }
    }

    whxy(w: number = 1, h: number = 1, x: number = 0, y: number = 0): {height: number, width: number, x: number, y: number} {
        return {
            ...this.wh(w,h),
            ...this.xy(x,y),
        }
    }

    d(o: Decal): {height: number, width: number, x: number, y: number} {
        return this.whxy( o.w, o.h, o.x, o.y )
    }

    centerAt(x: number, y: number, w: number, h: number): {height: number, width: number, x: number, y: number, offset: {x: number, y: number}} {
        return {
            offset: this.xy(w/2,h/2),
            ...this.wh(w,h),
            ...this.xy(x,y),
        }
    }
}