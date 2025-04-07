import * as React from "react";
import {useEffect, useLayoutEffect, useRef, useState} from "react";
import {Const, Global} from "../../defaults";
import {HordesCaptchaAPI, ResponseIndex} from "./api";
import {ReactDialogMounter} from "../index";
import {dialogShim} from "../../shims";

declare var c: Const;
declare var $: Global;

type Props = {
    selector: string
}

export class HordesCaptcha extends ReactDialogMounter<Props> {

	protected resolved: boolean = false;
	protected nonce: boolean = true;
	protected captchaActivator?: HTMLElement;
	public captchaData?: ResponseIndex;

	protected findActivatorAsync(parent: HTMLElement, props: Props): Promise<HTMLElement> {
		var captcha = this;
        return new Promise<HTMLElement>((resolve, reject) => {
			var activator = this.findActivator(parent, props);

			var observer = new MutationObserver((mutations) => {
				mutations.forEach((mutation) => {
					console.log('mutation', mutation);
					if (mutation.type === "attributes" && mutation.attributeName === "data-captcha") {
						captcha.captchaActivator = mutation.target as HTMLElement;

						if(captcha.nonce && captcha.captchaActivator.dataset.captcha) {
							captcha.nonce = false;
							captcha.captchaData = JSON.parse(captcha.captchaActivator.dataset.captcha);
							console.log('captcha data', captcha.captchaData);

							if(!captcha.resolved) {
								resolve(activator);
								captcha.resolved = true;
							} else {
								captcha.open();
							}
						}
					}
				});
			});

			observer.observe(activator, {
				attributes: true,
				attributeFilter: ["data-captcha"],
			});
		});
	}

    protected findActivator(parent: HTMLElement, props: Props): HTMLElement {
        return parent.querySelector(props.selector);
    }

    protected renderReact(callback: (a:any)=>void, props: Props) {
        return <HordesCaptchaDialog
            setCallback={callback}
			onClose={() => {
				this.captchaActivator.dataset.captcha = '';
				this.nonce = true;
			}}
			captcha={this}
        />
    }

	public open() {

	}
}

const HordesCaptchaDialog = (props: {
	captcha: HordesCaptcha, 
    setCallback: (any)=>void
	onClose: ()=>void,
	loadOnSuccess?: string,
}) => {
    const [open, setOpen] = useState<boolean>(false);
    const [sending, setSending] = useState<boolean>(false);

    const dialog = useRef<HTMLDialogElement>(null);
    const form = useRef<HTMLFormElement>(null);

    const api = useRef(new HordesCaptchaAPI())
	const index = props.captcha.captchaData;

	props.captcha.open = () => {
		setOpen(true);
	};

    useEffect(() => {
		setOpen(true);
        return () => props.setCallback(null);
    }, []);

    const confirmDialog = () => {
        setSending(true);
        api.current.submit({
            ...$.html.serializeForm(form.current),
        }).then( r => {
			// $.html.notice( index.strings.common.success );
			setSending(false);
			cancelDialog();
		}).catch(error => {
			setSending(false);
			console.log(error);
			$.html.error( c.errors['com'] )
		});
    }

    const cancelDialog = () => {
        dialog.current.close();
        setOpen(false);
		props.onClose();
    }

    useLayoutEffect(() => {
        dialogShim(dialog.current);
    }, [open]);

    useLayoutEffect(() => {
        if (open && dialog.current) {
            dialog.current.showModal();

            const esc_handler = (e) => {
                if (e.keyCode === 27) {
                    e.preventDefault();
                    cancelDialog();
                }
            }
            document.addEventListener('keydown', esc_handler );
            return () => document.removeEventListener('keydown', esc_handler);
        }
    }, [open]);

    return open && <>
        <dialog ref={dialog}>
            <form method="dialog" action={props.captcha.captchaData.submit} ref={form} onKeyDown={e => {
                if (e.key === "enter") confirmDialog();
            }} onSubmit={() => confirmDialog()}>
            	<div className="modal-title">{index.strings.common.title}</div>
                <div className="modal-content">
					<p className="small bold">{index.strings.common.prompt}</p>
					<p className="small">
						<span>{ index.strings.quizz.prompt }</span>
						{ index.strings.quizz.options.map( ({id,value}) => <label className="block" key={`opt_${id}`}><input type="radio" name="quizz_answer" value={id} />&nbsp;{value}</label> ) }
					</p>
                </div>
				<div id="modal-actions">
					<button type="button" disabled={sending} className="modal-button small inline" onClick={() => cancelDialog()}>{ index.strings.common.abort }</button>
					<button type="button" disabled={sending} className="modal-button small inline" onClick={() => confirmDialog()}>{ index.strings.common.submit }</button>
				</div>
            </form>

        </dialog>
    </>
}