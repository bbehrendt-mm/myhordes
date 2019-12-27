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

    load( target: Node, url: string, push_history: boolean = false ) {
        if (target === null) target = this.lastNode;
        if (target === null) {
            alert('ERROR_NO_TARGET_NODE');
            return;
        } else this.lastNode = target;

        let ajax_instance = this;
        url = this.prepareURL(url);

        if (push_history) history.pushState( url, '', url );

        let request = new XMLHttpRequest();
        request.responseType = 'document';
        request.addEventListener('load', function(e) {
            // Get URL
            if (push_history) history.replaceState( this.responseURL, '', this.responseURL );

            // Get content, style and script tags
            let content_source = this.responseXML.querySelectorAll('html>body>:not(script)');
            let style_source = this.responseXML.querySelectorAll('html>head>style');
            let script_source = this.responseXML.querySelectorAll('script');

            // Get the ajax intention; assume "native" if no intention is given
            let ajax_intention = this.responseXML.querySelector('html').getAttribute('x-ajax-intention');
            ajax_intention = ajax_intention ? ajax_intention :  'native';

            // Clear the target
            {let c; while ((c = target.firstChild)) target.removeChild(c);}

            // Move nodes from the AJAX document to the current document
            for (let i = 0; i < style_source.length; i++)
                target.appendChild( style_source[i] );
            for (let i = 0; i < content_source.length; i++) {
                let buttons = content_source[i].querySelectorAll('button[x-ajax-href],a[x-ajax-href]');
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
                    window.location.href = '/' + base;
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