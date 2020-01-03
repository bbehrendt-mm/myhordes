import Ajax from "./ajax";
import HTML from "./html";

export interface Global {
    ajax: Ajax,
    html: HTML
}

export interface Const {
    errors: object
}