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

const resize_game_menu = function() {
    let game_menu = document.querySelector('#gma');
    let game_menu_elems = document.querySelectorAll('#gma>div.game-bar>ul');
    let game_menu_burger = document.querySelector('#gma>div.game-bar>ul.text-menu>li.burger-button');
    let game_menu_hide = document.querySelectorAll('#gma>div.game-bar>ul.text-menu>li:not(.burger-button)');

    game_menu_burger.style.display = 'none';
    for (let i = 0; i < game_menu_hide.length; i++)
        game_menu_hide[i].style.display = null;

    let content_width = 0;
    for (let i = 0; i < game_menu_elems.length; i++)
        content_width += game_menu_elems[i].offsetWidth;

    if (game_menu.offsetWidth - content_width < 80) {
        game_menu_burger.style.display = null;
        for (let i = 0; i < game_menu_hide.length; i++)
            game_menu_hide[i].style.display = 'none';
    } else {
        let dropdown = document.querySelector('#gma>.game-dropdown');
        if (dropdown) {
            dropdown.classList.add('hidden');
            dropdown.style.height = '0px'
        }
    }
};

const resize_map = function() {
    let outer_maps = document.querySelectorAll('.map');
    for (let i = 0; i < outer_maps.length; i++) {
        let map = outer_maps[i];
        let scroll_area = map.querySelector('.scroll-plane');
        if (!scroll_area) continue;

        const scroll_range_x = Math.max(0,scroll_area.clientWidth - map.clientWidth);
        const scroll_range_y = Math.max(0,scroll_area.clientHeight - map.clientHeight);

        scroll_area.setAttribute('x-scroll-range-x', "" + scroll_range_x);
        scroll_area.setAttribute('x-scroll-range-y', "" + scroll_range_y);
        map.dispatchEvent(new Event("x-center", {
            bubbles: false, cancelable: true
        }));
    }
};

const resizer = function() {
    resize_game_menu();
    resize_map();

    //console.log(game_menu.offsetWidth, content_width, game_menu.offsetWidth - content_width);
};
window.addEventListener("resize", resizer);