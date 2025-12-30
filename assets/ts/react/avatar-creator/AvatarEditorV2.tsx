import * as React from "react";

import {useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {byteToText} from "../../v2/utils";
import {centerCrop, Crop, makeAspectCrop, ReactCrop} from "react-image-crop";
import {Buffer} from "buffer";
import {Globals} from "./WrapperV2";

type PixelCrop = {
    x: number,
    y: number,
    width: number,
    height: number
}

type AvatarEditorProps = {
    mime: string,
    data: Buffer,
    hasMedia: boolean,
    squareCrop?: PixelCrop|null,
    circularCrop?: PixelCrop|null,
    classicCrop?: PixelCrop|null,
}

type AvatarImageMeta = {
    width: number,
    height: number,
    aspect?: number,
}

const importPixelCrop = (base: PixelCrop|null|undefined, w: number, h: number): Crop => {return base ? {
    x: (base.x / w) * 100,
    y: (base.y / h) * 100,
    width: (base.width / w) * 100,
    height: (base.height / h) * 100,
    unit: '%'
} : {
    x: 0,
    y: 0,
    width: 100,
    height: 100,
    unit: '%'
}}

const importCrop = (base: Crop|null|undefined, w: number, h: number): PixelCrop => {return base ? {
    x: Math.round((base.x * w)/100),
    y: Math.round((base.y * h)/100),
    width: Math.round((base.width * w)/100),
    height: Math.round((base.height * h)/100),
} : {
    x: 0,
    y: 0,
    width: w,
    height: h,
}}

const AvatarDimensionsDisplay = ({crop, defaultCrop, pixelCrop, w, h}: {crop?: Crop|null, defaultCrop?: Crop|null, pixelCrop?: PixelCrop|null, w?: null|number, h?: null|number}) => {
    const globals = useContext(Globals)

    const useCrop = (crop && (crop.width * crop.height > 0) && isFinite(crop.width * crop.height)) ? crop : defaultCrop;
    const px = pixelCrop ?? ((useCrop && w && h) ? importCrop(useCrop, w, h) : null);

    return <>{px && globals.strings.common.dimensions
        .replace('{x}', `${px.width}`)
        .replace('{y}', `${px.height}`)
    }</>
}

const AvatarImagePreview = ( {data, crop, defaultCrop, subclass, w, h}: {data?: string, crop?: Crop, defaultCrop?: Crop, subclass?: string, w?: number|null, h?: number|null} ) => {
    const useCrop = (crop && (crop.width * crop.height > 0) && isFinite(crop.width * crop.height)) ? crop : defaultCrop;
    const px = (useCrop && w && h) ? importCrop(useCrop, w, h) : null;

    const rw = px ? (px.width >= px.height ? 1 : (px.width / px.height)) : 1;
    const rh = px ? (px.height >= px.width ? 1 : (px.height / px.width)) : 1;

    return <>
        { data && px && <div className={`avatar ${subclass ?? ''} no-arma`} style={{
            overflow: 'hidden', boxSizing: 'content-box',
            ...(subclass === 'round' ? { width: `${40*rw}px`, height: `${40*rh}px` } : { }),
            ...(subclass === 'small' ? { width: `${90*rw}px`, height: `${90*rh}px` } : { }),
            ...(!subclass ? { width: `${100*rw}px`, height: `${100*rh}px` } : { }),
        }}><svg viewBox={`${px.x} ${px.y} ${px.width} ${px.height}`}>
            <image href={data} x={0} y={0} width={w} height={h}/>
        </svg></div> }
    </>
}

export const AvatarModeEdit = (props: AvatarEditorProps) => {
    const globals = useContext(Globals)

    const [dataString, setDataString] = useState<string>(null);

    const [fixedAspect, setFixedAspect] = useState<number>(null);

    const [edit, setEdit] = useState<null|'square'|'circle'|'classic'>(null);
    const [meta, setMeta] = useState<AvatarImageMeta>(null);
    const image = useRef<HTMLImageElement>(null);

    const circularCropIsEdited = useRef(!!props.circularCrop);
    const classicCropIsEdited = useRef(!!props.classicCrop);

    const [squareCropDefault, setSquareCropDefault] = useState<Crop>(null);
    const [circularCropDefault, setCircularCropDefault] = useState<Crop>(null);
    const [classicCropDefault, setClassicCropDefault] = useState<Crop>(null);

    const [squareCrop, setSquareCrop] = useState<Crop>(null);
    const [circularCrop, setCircularCrop] = useState<Crop>(null);
    const [classicCrop, setClassicCrop] = useState<Crop>(null);

    const activeCrop = edit === 'square' ? squareCrop : edit === 'circle' ? circularCrop : classicCrop;

    const recrop = (c: Crop, aspect: number, w: number = null, h: number = null): Crop => {
        const px = importCrop( c, w ?? meta?.width, h ?? meta?.height );
        const pxo = centerCrop(
            makeAspectCrop( { unit: 'px', width: px.width, height: px.height }, aspect, px.width, px.height ),
            px.width, px.height
        );
        return importPixelCrop({height: pxo.height, width: pxo.width, x: px.x + pxo.x, y: px.y + pxo.y}, w ?? meta?.width, h ?? meta?.height);
    }

    const setActiveCrop = (crop: Crop) => {
        if (edit === 'square') {
            setSquareCrop(crop);
            if (!circularCropIsEdited.current)
                setCircularCrop(recrop(crop, 1));

            if (!classicCropIsEdited.current)
                setClassicCrop(recrop(crop, 3));

        } else if (edit === 'circle') {
            setCircularCrop(crop);
            circularCropIsEdited.current = true;
        } else if (edit === 'classic') {
            setClassicCrop(crop);
            classicCropIsEdited.current = true;
        }
    };

    useEffect(() => {
        setDataString(`data:${props.mime};base64,${props.data.toString('base64')}`);
    }, [props.data,props.mime]);

    useLayoutEffect(() => {
        if (!image.current) return;
        const fun = () => {
            const tmp_meta = {width: image.current.naturalWidth, height: image.current.naturalHeight};
            setMeta({
                ...tmp_meta, aspect: tmp_meta.width / tmp_meta.height
            });
            const defaultCrop = importPixelCrop(null, tmp_meta.width, tmp_meta.height);

            setSquareCrop( importPixelCrop(props.squareCrop, tmp_meta.width, tmp_meta.height) );
            setSquareCropDefault( defaultCrop );

            setCircularCrop( props.circularCrop
                ? importPixelCrop(props.circularCrop, tmp_meta.width, tmp_meta.height)
                : recrop( defaultCrop, 1, tmp_meta.width, tmp_meta.height )
            );
            setCircularCropDefault(recrop( defaultCrop, 1, tmp_meta.width, tmp_meta.height ))

            setClassicCrop( props.classicCrop
                ? importPixelCrop(props.classicCrop, tmp_meta.width, tmp_meta.height)
                : recrop( defaultCrop, 3, tmp_meta.width, tmp_meta.height )
            );
            setClassicCropDefault(recrop( defaultCrop, 3, tmp_meta.width, tmp_meta.height ))
        };
        image.current.addEventListener('load', fun);
        return () => image.current.removeEventListener('load', fun);
    }, [dataString]);

    return <div className="flex column large-gap">
        <div className="row-flex h-center gap">
            <div className="cell rw-3 center flex column large-gap">
                <div className="small flex-none"><strong>{ globals.strings.common.format_upload }</strong></div>
                { meta && <div className="small flex-none"><strong>{globals.strings.common.info
                    .replace('{x}', `${meta.width}`)
                    .replace('{y}', `${meta.height}`)
                    .replace('{size}', byteToText(props.data.byteLength))
                }</strong></div> }
                { !meta && <div className="loading flex-none"/> }
                <div className="flex-1">
                    { dataString && <img style={{maxWidth: '100%'}} ref={image} alt={ globals.strings.common.format_upload } src={dataString} />}
                </div>
                <div className="small flex-none">
                    <button disabled={edit !== null} onClick={()=> globals.setMode('new')}>
                        { globals.strings.common.edit_redo }
                    </button>
                </div>
            </div>

            <div className="cell rw-3 center flex column large-gap">
                <div className="small flex-none"><strong>{ globals.strings.common.format_default }</strong></div>
                <div className="small flex-none"><strong><AvatarDimensionsDisplay crop={squareCrop} defaultCrop={squareCropDefault} w={meta?.width} h={meta?.height}/></strong></div>
                <div className="flex-1">
                    <AvatarImagePreview data={dataString} crop={squareCrop} defaultCrop={squareCropDefault} w={meta?.width} h={meta?.height} />
                </div>
                <div className="small flex-none">
                    { edit === null && <button onClick={()=> setEdit('square')}>
                        { globals.strings.common.edit_now }
                    </button> }
                    { edit === 'square' && <button onClick={()=> setEdit(null)}>
                        { globals.strings.common.edit_finish }
                    </button> }
                </div>
            </div>

            <div className="cell rw-3 center flex column large-gap">
                <div className="small flex-none"><strong>{ globals.strings.common.format_round }</strong></div>
                <div className="small flex-none"><strong><AvatarDimensionsDisplay crop={circularCrop} defaultCrop={circularCropDefault} w={meta?.width} h={meta?.height}/></strong></div>
                <div className="flex-1">
                    <AvatarImagePreview data={dataString} crop={circularCrop} defaultCrop={circularCropDefault} w={meta?.width} h={meta?.height} subclass="round"/>
                </div>
                <div className="small flex-none">
                    { edit === null && <button onClick={()=> setEdit('circle')}>
                        { globals.strings.common.edit_now }
                    </button> }
                    { edit === 'circle' && <button onClick={()=> setEdit(null)}>
                        { globals.strings.common.edit_finish }
                    </button> }
                </div>
            </div>

            <div className="cell rw-3 center flex column large-gap">
                <div className="small flex-none"><strong>{ globals.strings.common.format_small }</strong></div>
                <div className="small flex-none"><strong><AvatarDimensionsDisplay crop={classicCrop} defaultCrop={classicCropDefault} w={meta?.width} h={meta?.height}/></strong></div>
                <div className="flex-1">
                    <AvatarImagePreview data={dataString} crop={classicCrop} defaultCrop={classicCropDefault} w={meta?.width} h={meta?.height} subclass="small"/>
                </div>
                <div className="small flex-none">
                    { edit === null && <button onClick={()=> setEdit('classic')}>
                        { globals.strings.common.edit_now }
                    </button> }
                    { edit === 'classic' && <button onClick={()=> setEdit(null)}>
                        { globals.strings.common.edit_finish }
                    </button> }
                </div>
            </div>
        </div>

        { edit && <div className="flex column large-gap" style={{marginTop: '1rem'}}>
            { edit === 'square' && <div className="flex-none flex large-gap">
                <div className="note note-lightest">TODO Aspect Ratio</div>
                <div className="flex-1"><select value={fixedAspect ?? 0} onChange={(e)=> {
                    const value = parseFloat(e.target.value);
                    setFixedAspect(value === 0 ? null : value);
                    if (value !== 0) setSquareCrop(recrop(squareCrop, value));
                }}>
                    <option value={0}>TODO FREE</option>
                    { ![1,3,4/3,16/9].includes(meta.aspect) && <option value={meta.width/meta.height}>TODO LIKE ORIGINAL</option> }
                    <option value={1}>1:1</option>
                    <option value={3}>3:1</option>
                    <option value={4/3}>4:3</option>
                    <option value={16/9}>16:9</option>
                </select></div>
            </div> }
            <div className="flex-1 center">
                <ReactCrop
                    crop={activeCrop}
                    onChange={(crop, percentCrop) => setActiveCrop(percentCrop)}
                    aspect={edit === 'circle' ? 1 : (edit === 'classic' ? 3 : fixedAspect)}
                    minWidth={20}
                    minHeight={20}
                    circularCrop={edit === 'circle'}
                >
                    <img style={{maxWidth: '100%', maxHeight: '75dvh'}} src={dataString} alt=""/>
                </ReactCrop>
            </div>

        </div> }

        {!edit && meta && <>

            <div className="help">
                <p>{ globals.strings.common.edit_help }</p>
                <p>{ globals.strings.common.edit_help2 }</p>
            </div>

            <div className="note note-critical">
                { globals.strings.common.edit_help3 }
            </div>

            <div className="flex large-gap">
                <div>
                    <button onClick={() => {
                        globals.api.uploadMedia(
                            props.mime,
                            props.data.toString('base64'),
                            importCrop(squareCrop, meta.width, meta.height),
                            importCrop(classicCrop, meta.width, meta.height),
                            importCrop(circularCrop, meta.width, meta.height),
                        ).then( () => {
                            window.setTimeout(() => globals.refresh(), 1000);
                        } );
                    }}>{globals.strings.common.action_create}</button>
                </div>
                <div>
                    <button onClick={() => globals.setMode( props.hasMedia ? 'view' : 'empty' )}>
                        {globals.strings.common.action_cancel}
                    </button>
                </div>
            </div>

        </> }
    </div>

}
