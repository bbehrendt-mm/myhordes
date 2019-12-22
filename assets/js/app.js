// LESS files
require('../less/theme.less');
require('../less/grid.less');
require('../less/mixins.less');
require('../less/mobile.less');

// Packages - FontAwesome
require('@fortawesome/fontawesome-free/less/fontawesome.less');
require('@fortawesome/fontawesome-free/js/all.js');

// CSS files
require('../css/app.css');

// JavaScript and TypeScript files
import Ajax from '../ts/ajax'
import HTML from '../ts/html'

// Get the base URL
const base_node = document.getElementsByTagName('base');
const url = base_node.length === 0 ? '' : base_node[0].getAttribute('href');

let $ = {
    ajax: new Ajax(url),
    html: new HTML()
};
window.$ = $;

document.addEventListener('DOMContentLoaded', function() {
    $.ajax.load( document.getElementById('content'), document.documentElement.getAttribute('x-ajax-landing'), true );
}, {once: true, passive: true});

window.addEventListener('popstate', function(event) {
    $.ajax.load( document.getElementById('content'), event.state, false );
});