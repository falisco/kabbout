// The words the robots give their bidding reasons in. A reason is free text (see BidRecord), so
// the language is chosen when the bid is made, not when it is shown.

export const LANGS = ['fr', 'en', 'ar'] as const;
export type Lang = (typeof LANGS)[number];
export const isLang = (v: unknown): v is Lang => (LANGS as readonly unknown[]).includes(v);

export interface Phrases {
  /** index letters of the jack and the ace */
  jack: string;
  ace: string;
  /** "jack and n trumps": the jack with `n` other trumps */
  jackAnd: (jack: string, n: number) => string;
  /** jack and nine alone in the suit */
  bare: (cards: string) => string;
  /** a card with two others in its suit */
  third: (card: string) => string;
  /** a card with three others in its suit */
  fourth: (card: string) => string;
  long: (cards: string) => string;
  trumps: (n: number) => string;
  ruff: (suit: string) => string;
  belote: string;
  support: string;
  sureCapot: string;
  pairWorth: (points: number) => string;
  noMargin: string;
  ours: string;
  cannotRaise: string;
  noTrump: string;
}

export const PHRASES: Record<Lang, Phrases> = {
  fr: {
    jack: 'V',
    ace: 'As',
    jackAnd: (jack, n) => `${jack} et ${n} atouts`,
    bare: (cards) => `${cards} secs`,
    third: (card) => `${card} troisième`,
    fourth: (card) => `${card} quatrième`,
    long: (cards) => `${cards} longs`,
    trumps: (n) => `${n} atouts`,
    ruff: (suit) => `coupe ${suit}`,
    belote: 'belote',
    support: 'soutien',
    sureCapot: 'huit plis sûrs',
    pairWorth: (points) => `jeu estimé à ${points} à deux`,
    noMargin: 'pas assez de marge',
    ours: 'contrat déjà dans le camp',
    cannotRaise: 'pas de quoi monter',
    noTrump: 'pas d’atout solide',
  },
  en: {
    jack: 'J',
    ace: 'A',
    jackAnd: (jack, n) => `${jack} and ${n} trumps`,
    bare: (cards) => `bare ${cards}`,
    third: (card) => `${card} third`,
    fourth: (card) => `${card} fourth`,
    long: (cards) => `long ${cards}`,
    trumps: (n) => `${n} trumps`,
    ruff: (suit) => `ruffs ${suit}`,
    belote: 'belote',
    support: 'support',
    sureCapot: 'eight sure tricks',
    pairWorth: (points) => `our two hands are worth about ${points}`,
    noMargin: 'not enough margin',
    ours: 'our side already holds the contract',
    cannotRaise: 'nothing to raise with',
    noTrump: 'no solid trump suit',
  },
  // Tunisian Arabic. The cards carry the French index letters, so the reasons name them that way.
  ar: {
    jack: 'V',
    ace: 'As',
    jackAnd: (jack, n) => `${jack} و ${n} أتو`,
    bare: (cards) => `${cards} وحدهم`,
    third: (card) => `${card} مع زوز`,
    fourth: (card) => `${card} مع ثلاثة`,
    long: (cards) => `${cards} طوال`,
    trumps: (n) => `${n} أتو`,
    ruff: (suit) => `نقص ${suit}`,
    belote: 'بلوط',
    support: 'نعاون شريكي',
    sureCapot: 'الثمانية بليات مضمونين',
    pairWorth: (points) => `لعبنا الزوز يسوى ${points}`,
    noMargin: 'ما فماش فارق كافي',
    ours: 'الطلب عندنا',
    cannotRaise: 'ما عنديش باش نزيد',
    noTrump: 'ما عنديش أتو قوي',
  },
};
