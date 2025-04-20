import dialogPolyfill from "dialog-polyfill";

export function dialogShim(element: HTMLDialogElement|null): boolean {

    if (typeof HTMLDialogElement === 'function') return false;
    if (!element || element.dataset.shimRegistered === '1') return true;

    dialogPolyfill.registerDialog(element);
    element.dataset.shimRegistered = '1';
    return true;
}

export function randomUUIDv4(): string {
    if ('randomUUID' in window.crypto) return window.crypto.randomUUID();
    return "10000000-1000-4000-8000-100000000000".replace(/[018]/g, c =>
        (+c ^ window.crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> +c / 4).toString(16)
    );
}