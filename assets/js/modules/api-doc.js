import "rapidoc"

window.addEventListener('DOMContentLoaded', () => {
    document.querySelector('rapi-doc').loadSpec(require('../swagger.json'));
})
