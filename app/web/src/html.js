// Building markup from text.

/** text of unknown origin, safe to put in markup or in a quoted attribute */
export const esc = (text) => String(text).replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);
