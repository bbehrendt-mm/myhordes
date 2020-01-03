interface ajaxCallback { (data: object, code: number): void }

export default class Ajax {

    base: string;
    lastNode: Node;

    constructor(baseUrl: string) {
        if (baseUrl.length == 0 || baseUrl.slice(-1) != '/')
            baseUrl += '/';
        this.base = baseUrl;
        this.lastNode = null;
    }

    prepareURL(url: string): string {
        if (url.slice(0,4) !== 'http' && url.slice(0,this.base.length) !== this.base) url = this.base + url;
        return url;
    }
    prepareTarget(target: Node): Node {
        if (target === null) target = this.lastNode;
        if (target === null) {
            alert('ERROR_NO_TARGET_NODE');
            return null;
        } else this.lastNode = target;
        return target;
    }

    render( url: string, target: Node, result_document: Document, push_history: boolean, replace_history: boolean ) {
        // Get URL
        if (push_history) history.pushState( url, '', url );
        if (replace_history) history.replaceState( url, '', url );

        // Get content, style and script tags
        let content_source = result_document.querySelectorAll('html>body>:not(script)');
        let style_source = result_document.querySelectorAll('html>head>style');
        let script_source = result_document.querySelectorAll('script');

        // Get the ajax intention; assume "native" if no intention is given
        let ajax_intention = result_document.querySelector('html').getAttribute('x-ajax-intention');
        ajax_intention = ajax_intention ? ajax_intention :  'native';

        // Clear the target
        {let c; while ((c = target.firstChild)) target.removeChild(c);}

        let ajax_instance = this;

        // Move nodes from the AJAX document to the current document
        for (let i = 0; i < style_source.length; i++)
            target.appendChild( style_source[i] );
        for (let i = 0; i < content_source.length; i++) {
            let buttons = content_source[i].querySelectorAll('*[x-ajax-href]');
            for (let b = 0; b < buttons.length; b++)
                buttons[b].addEventListener('click', function(e) {
                    e.preventDefault();
                    ajax_instance.load( target, buttons[b].getAttribute('x-ajax-href'), push_history )
                }, {once: true, capture: true});
            target.appendChild( content_source[i] );
        }

        for (let i = 0; i < script_source.length; i++) {
            eval(script_source[i].innerText);
        }

        // If ajax intention is 'native', trigger a DOMContentLoaded event on the document
        if (ajax_intention === 'native')
            window.document.dispatchEvent(new Event("DOMContentLoaded", {
                bubbles: true, cancelable: true
            }));
        window.dispatchEvent(new Event("resize", {
            bubbles: true, cancelable: true
        }));
    }

    load( target: Node, url: string, push_history: boolean = false ) {
        let ajax_instance = this;
        if (!(target = this.prepareTarget( target ))) return;
        url = this.prepareURL(url);

        if (push_history) history.pushState( url, '', url );

        let request = new XMLHttpRequest();
        request.responseType = 'document';
        request.addEventListener('load', function(e) {
            // Check if a reset header is set
            if (this.getResponseHeader('X-AJAX-Control') === 'reset') {
                window.location.href = ajax_instance.base;
                return;
            }

            ajax_instance.render( this.responseURL, target, this.responseXML, false, true );
        });
        request.addEventListener('error', function(e) {
            alert('Error loading page.');
        });
        request.open('GET', url);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('X-Request-Intent', 'WebNavigation');
        request.send();
    };

    send( url: string, data: object, callback: ajaxCallback ) {
        url = this.prepareURL(url);
        const base = this.base;

        let request = new XMLHttpRequest();
        request.responseType = 'json';
        request.addEventListener('load', function(e) {
            switch ( this.getResponseHeader('X-AJAX-Control') ) {
                case 'reset':
                    window.location.href = base;
                    return;
                case 'cancel':
                    return;
                case 'process': default: break;
            }
            callback( this.response, this.status );
        });
        request.addEventListener('error', function(e) {
            alert('Error transferring data.');
        });
        request.open('POST', url);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('X-Request-Intent', 'JSONDataExchange');
        request.setRequestHeader('Content-Type', 'application/json');
        request.send( JSON.stringify(data) );
    }
}