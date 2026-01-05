import * as React from "react";

import {AvatarCreatorAPI, Media, MediaSet, PixelCrop, ResponseMedia} from "./api";
import {useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {TranslationStrings} from "./strings";
import {Global} from "../../defaults";
import {BaseMounter} from "../index";
import {useSharedWorkerMessages, useTranslations} from "../utils";
import {Buffer} from "buffer";
import {AvatarModeEdit} from "./AvatarEditorV2";
import {AvatarModeView} from "./AvatarViewerV2";

declare var $: Global;

export class HordesAvatarCreator2 extends BaseMounter<{ maxSize: number }> {
    protected render(props: { maxSize: number }): React.ReactNode {
        return <AvatarCreatorWrapper {...props} />;
    }
}

type AvatarCreatorGlobals = {
    api: AvatarCreatorAPI,
    strings: TranslationStrings,
    setMode: (mode: 'empty'|'view'|'new'|'edit') => void,
    refresh: () => void,
    setTransientImage: (image: TransientMedia|null) => void,
}

export const Globals = React.createContext<AvatarCreatorGlobals>(null);

type TransientMedia = {
    mime: string,
    data: Buffer,
    id?: string,
    crops?: {
        default: PixelCrop|null,
        round: PixelCrop|null,
        small: PixelCrop|null,
    }
}

type MediaSignal = {
    media: string,
    collection: string,
    data: Media
}

export type SignalCache = {[key: string]: {[key: string]: Media}};

const AvatarCreatorWrapper = ( {maxSize}: {maxSize: number} ) => {

    const api = useRef<AvatarCreatorAPI>(new AvatarCreatorAPI());
    const index = useTranslations( api.current );

    const [loading, setLoading] = useState<boolean>(false);
    const [etag, setEtag] = useState<number>(0);
    const [image, setImage] = useState<TransientMedia>(null);
    const [media, setMedia] = useState<ResponseMedia>(null);
    const [mode, setMode] = useState<'empty'|'view'|'new'|'edit'>('empty')

    const [signalCache, setSignalCache] = useState<SignalCache>({})

    useSharedWorkerMessages<MediaSignal>('related-media-update', (data) => {
        if (data.collection !== 'avatar-pending') return;
        setSignalCache(c => {
            c = {...c};
            c[data.media] = c[data.media] ?? {};
            c[data.media][data.data.id] = data.data;
            return c;
        });
    }, 'live', [])

    const checkHasMedia = (m: ResponseMedia): boolean => m !== null && (m.avatar || m.pending || m.history) && true;

    const hasMedia = checkHasMedia(media);

    useEffect( () => {
        setLoading(true);
        $.html.addLoadStack();
        api.current.getMedia().then( media => {
            setImage(null);
            setMedia(media);
            if (checkHasMedia(media)) setMode('view');
            else setMode('empty');
            $.html.removeLoadStack();
            setLoading(false);
        } ).catch(() => $.html.removeLoadStack());
    }, [etag] )

    return (
        <Globals.Provider value={{
            api: api.current,
            strings: index?.strings,
            setMode, setTransientImage: setImage,
            refresh: () => setEtag(etag + 1),
        }}>
            <div className="row" style={{pointerEvents: loading ? 'none' : 'auto'}}>
                <div className="padded cell rw-12">
                    {(!index || !media) && <div className="loading"/>}
                    {(index && media) && <>
                        { mode === 'empty' && <AvatarModeEmpty/> }
                        { mode === 'view' && <AvatarModeView current={media.avatar} pending={media.pending} history={media.history} signalCache={signalCache} /> }
                        { mode === 'new' && <AvatarModeNew maxSize={maxSize} hasMedia={hasMedia}/> }
                        { mode === 'edit' && <AvatarModeEdit
                            original={ image.id } mime={ image.mime } data={ image.data } hasMedia={hasMedia}
                            squareCrop={ image.crops?.default ?? null }
                            circularCrop={ image.crops?.round ?? null }
                            classicCrop={ image.crops?.small ?? null }
                        /> }
                    </>}
                </div>
            </div>
        </Globals.Provider>
    )
}

const AvatarModeEmpty = () => {
    const globals = useContext(Globals)

    return <>
        <div className="row">
            <div className="cell rw-12">
                <div className="help">{ globals.strings.common.no_avatar }</div>
            </div>
        </div>
        <div className="row">
            <div className="cell rw-4 rw-md-6 rw-sm-12">
                <button onClick={()=> globals.setMode('new')}>
                    { globals.strings.common.action_create }
                </button>
            </div>
        </div>
    </>
}

const AvatarModeNew = ({maxSize, hasMedia}: {maxSize: number, hasMedia: boolean}) => {
    const globals = useContext(Globals)
    const uploadRef = useRef<HTMLInputElement>();

    const handleFileSelectionCancel = () => globals.setMode(hasMedia ? 'view' : 'empty');

    const handleFileSelection = ()=> {
        if (uploadRef.current.files.length !== 1) {
            $.html.error(globals.strings.common.error_single_file);
            globals.setMode(hasMedia ? 'view' : 'empty');
            return;
        }

        const file = uploadRef.current.files[0];
        if (maxSize > 0 && file.size >= maxSize) {
            $.html.error(globals.strings.common.error_too_large);
            globals.setMode(hasMedia ? 'view' : 'empty');
            return;
        }

        const type_info = file.type.split('/',2);
        if (type_info.length < 2 || type_info[0] !== 'image') {
            $.html.error(globals.strings.common.error_unknown_format);
            globals.setMode(hasMedia ? 'view' : 'empty');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(r) {
            globals.setTransientImage({mime: file.type, data: Buffer.from(r.target.result as ArrayBuffer)});
            globals.setMode('edit');
        };
        reader.readAsArrayBuffer(file);
    }


    useLayoutEffect(() => {
        uploadRef.current.addEventListener('change', handleFileSelection);
        uploadRef.current.addEventListener('cancel', handleFileSelectionCancel);
        uploadRef.current?.click();

        return () => {
            uploadRef.current.removeEventListener('change', handleFileSelection);
            uploadRef.current.removeEventListener('cancel', handleFileSelectionCancel);
        }
    }, []);

    return <>
        <div className="loading" />
        <input ref={uploadRef} className="hidden" type="file" accept=".gif,.jpg,.jpeg,.jif,.jfif,.png,.webp,.bmp,.heic,.avif"/>
    </>
}
