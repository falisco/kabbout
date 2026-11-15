// Reaching the page's elements.

/** @returns {any} the element matching `selector`; the page's markup is fixed, so it is there */
export const $ = (selector) => document.querySelector(selector);
