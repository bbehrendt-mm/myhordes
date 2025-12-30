import * as React from "react";

import {AvatarCreatorAPI, ResponseMedia} from "./api";
import {useContext, useEffect, useLayoutEffect, useRef, useState} from "react";
import {TranslationStrings} from "./strings";
import {Global} from "../../defaults";
import {BaseMounter} from "../index";
import {useTranslations} from "../utils";
import {Buffer} from "buffer";
import {AvatarModeEdit} from "./AvatarEditorV2";

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
    data: Buffer
}

const AvatarCreatorWrapper = ( {maxSize}: {maxSize: number} ) => {

    const api = useRef<AvatarCreatorAPI>(new AvatarCreatorAPI());
    const index = useTranslations( api.current );
    const uploadRef = useRef<HTMLInputElement>();

    const [etag, setEtag] = useState<number>(0);
    const [image, setImage] = useState<TransientMedia>(null);
    const [media, setMedia] = useState<ResponseMedia>(null);
    const [mode, setMode] = useState<'empty'|'view'|'new'|'edit'>('empty')

    const hasMedia = media !== null && (media.default !== null || media.round !== null || media.small !== null);

    useEffect( () => {
        setMedia(null);
        api.current.getMedia().then( media => {
            setImage(null);
            setMedia(media);
            if (media.default !== null || media.round !== null || media.small !== null) setMode('view');
            else setMode('empty');
        } );
    }, [etag] )

    return (
        <Globals.Provider value={{
            api: api.current,
            strings: index?.strings,
            setMode, setTransientImage: setImage,
            refresh: () => setEtag(etag + 1),
        }}>
            <div className="row">
                <div className="padded cell rw-12">
                    {(!index || !media) && <div className="loading"/>}
                    {(index && media) && <>
                        { mode === 'empty' && <AvatarModeEmpty/> }
                        { mode === 'new' && <AvatarModeNew maxSize={maxSize} hasMedia={hasMedia}/> }
                        { mode === 'edit' && <AvatarModeEdit mime={ image.mime } data={ image.data } hasMedia={hasMedia}/> }
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
        }

        const file = uploadRef.current.files[0];
        if (maxSize > 0 && file.size >= maxSize) {
            $.html.error(globals.strings.common.error_too_large);
            globals.setMode(hasMedia ? 'view' : 'empty');
        }

        const type_info = file.type.split('/',2);
        if (type_info.length < 2 || type_info[0] !== 'image') {
            $.html.error(globals.strings.common.error_unknown_format);
            globals.setMode(hasMedia ? 'view' : 'empty');
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
        <input ref={uploadRef} className="hidden" type="file" accept=".gif,.jpg,.jpeg,.jif,.jfif,.png,.webp,.bmp,.heic"/>
    </>
}
