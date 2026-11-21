// The few questions the bridge asks of a value of unknown origin before the engine sees it.

export type Unknown = Record<string, unknown>;

/** a JSON object: not null, not a list */
export const isObject = (v: unknown): v is Unknown => !!v && typeof v === 'object' && !Array.isArray(v);

/** the engine's own words for a seat that does not exist, for the places where the bridge is the one to say them */
export const unknownSeat = (v: unknown): string => `unknown seat ${String(v)}`;
