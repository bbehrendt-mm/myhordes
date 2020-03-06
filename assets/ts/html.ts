import {Global} from "./defaults";

declare var $: Global;

export default class HTML {

    constructor() {}

    serializeForm(form: ParentNode): object {
        let data: object = {};

        const input_fields = form.querySelectorAll('input');
        for (let i = 0; i < input_fields.length; i++) {
            const node_name = input_fields[i].getAttribute('name')
                ? input_fields[i].getAttribute('name')
                : input_fields[i].getAttribute('id');
            if (node_name)
                data[node_name] = input_fields[i].value;
        }

        return data;
    }

    selectErrorMessage( code: string, messages: object, base: object, data: object = null ) {
        if (!code) code = 'default';
        if (messages && messages.hasOwnProperty(code)) {
            if (typeof messages[code] === 'function')
                this.error( messages[code](data) );
            else this.error( messages[code] );
        } else if ( base.hasOwnProperty(code) ) this.error( base[code] );
        else this.error( 'An error occurred. No further details are available.' );
    }

    message(label: string, msg: string ): void {
        let div = document.createElement('div');
        div.innerHTML = msg;
        div.classList.add( label );
        div.addEventListener('click', function() {
            div.classList.remove('show');
            setTimeout( function(node) { node.remove(); }, 500, div );
        });

        div = document.getElementById('notifications').appendChild( div );
        setTimeout( function(node) { node.classList.add('show'); }, 100, div );
    }

    error(msg: string): void {
        this.message('error',msg);
    }

    warning(msg: string): void {
        this.message('warning',msg);
    }

    notice(msg: string): void {
        this.message('notice',msg);
    }

    handleCountdown( element: Element ): void {
        let attr = parseInt(element.getAttribute('x-countdown'));
        const timeout = new Date( (new Date()).getTime() + 1000 * attr );

        const draw = function() {
            const seconds = Math.floor((timeout.getTime() - (new Date()).getTime())/1000);
            if (seconds < 0) return;

            const h = Math.floor(seconds/3600);
            const m = Math.floor((seconds - h*3600)/60);
            const s = seconds - h*3600 - m*60;
            element.innerHTML =
                (h > 0 ? (h + ':') : '') +
                (h > 0 ? (m > 9 ? (m + ':') : ('0' + m + ':')) : (m + ':')) +
                (s > 9 ? s : ('0' + s));
        };

        const f = function(no_chk = false) {
            if (!no_chk && !document.body.contains(element)) return;
            if ((new Date() > timeout)) {
                element.innerHTML = '--:--';
                element.dispatchEvent(new Event("expire", { bubbles: true, cancelable: true }));
            }
            else {
                draw();
                window.setTimeout(f,1000);
            }
        };

        f(true);
    }

    handleTooltip( element: HTMLElement): void {
        let parent = element.parentElement;
        if (!parent) return;

        parent.addEventListener('contextmenu', function(e) {
           e.preventDefault();
        }, false);
        parent.addEventListener('pointerenter', function(e) {
            element.style.display = 'block';
        });
        parent.addEventListener('pointermove', function(e) {
            element.style.top  = e.clientY + 'px';
            if (e.clientX + element.clientWidth + 25 > window.innerWidth) {
                element.style.left = (e.clientX - element.clientWidth - 50) + 'px';
            } else element.style.left = e.clientX + 'px';
        });
        parent.addEventListener('pointerleave', function(e) {
            element.style.display = 'none';
        });
    };

    handleTabNavigation( element: Element ): void {

        const hide_group = function(group: string) {

            let targets = element.querySelectorAll('*[x-tab-target][x-tab-group=' + group + ']');
            for (let t = 0; t < targets.length; t++)
                (<HTMLElement>targets[t]).style.display = 'none';
        };

        let controllers = element.querySelectorAll('*[x-tab-control][x-tab-group]');
        for (let i = 0; i < controllers.length; i++) {

            const group = controllers[i].getAttribute('x-tab-group');

            hide_group(group);

            let buttons = controllers[i].querySelectorAll( '[x-tab-id]' );
            for (let b = 0; b < buttons.length; b++) {
                const id = buttons[b].getAttribute('x-tab-id');
                buttons[b].addEventListener('click', function () {
                    hide_group( group );
                    for (let bi = 0; bi < buttons.length; bi++) buttons[bi].classList.remove('selected');
                    buttons[b].classList.add('selected');
                    let targets = element.querySelectorAll('*[x-tab-target][x-tab-group=' + group + '][x-tab-id= ' + id + ']');
                    for (let t = 0; t < targets.length; t++)
                        (<HTMLElement>targets[t]).style.display = null;
                    $.client.set( group, 'tabState', id, true );
                })
            }

            if (buttons.length > 0) {
                const pre_selection = $.client.get( group, 'tabState' );
                let target: Element | null = null;
                if (pre_selection !== null)
                    target = controllers[i].querySelector( '[x-tab-id=' + pre_selection + ']' );
                if (target === null)
                    target = buttons[0];
                if (target !== null) target.dispatchEvent(new Event("click", {
                    bubbles: true, cancelable: true
                }));
            }

        }

    }
}