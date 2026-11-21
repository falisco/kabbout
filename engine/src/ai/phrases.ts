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
  /** a hand that would open on the classic card, short of what this pair agreed */
  belowOpening: (points: number) => string;
  /** the aces kept back on the first raise, shown now that the opponents bid over the pair */
  acesNow: string;
  /** the conventions card read back to the players: see describeConventions */
  conventions: {
    open: (points: number) => string;
    /** what the partner holds: the subject of `adds` and `addsNothing` */
    jack: string;
    nine: string;
    threeTrumps: string;
    belote: string;
    ace: string;
    adds: (what: string, points: number) => string;
    addsNothing: (what: string) => string;
    atOnce: string;
    whenOverbid: string;
  };
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
    belowOpening: (points) => `sous l’ouverture convenue (${points})`,
    acesNow: 'as gardés pour la surenchère adverse',
    conventions: {
      open: (points) => `On ouvre avec une main qui vaut au moins ${points}.`,
      jack: 'Le valet d’atout',
      nine: 'Le neuf d’atout',
      threeTrumps: 'Trois atouts ou plus',
      belote: 'La belote',
      ace: 'Chaque as hors atout',
      adds: (what, points) => `${what} : le partenaire monte de ${points}.`,
      addsNothing: (what) => `${what} : le partenaire ne monte pas pour ça.`,
      atOnce: 'Le partenaire annonce ses as dès sa première enchère.',
      whenOverbid: 'Le partenaire garde ses as, et ne les annonce que si l’adversaire surenchérit.',
    },
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
    belowOpening: (points) => `short of the opening we agreed (${points})`,
    acesNow: 'aces kept for when they bid over us',
    conventions: {
      open: (points) => `We open on a hand worth at least ${points}.`,
      jack: 'The jack of trumps',
      nine: 'The nine of trumps',
      threeTrumps: 'Three trumps or more',
      belote: 'The belote',
      ace: 'Each ace outside trumps',
      adds: (what, points) => `${what}: the partner raises by ${points}.`,
      addsNothing: (what) => `${what}: the partner does not raise for it.`,
      atOnce: 'The partner shows its aces with its first bid.',
      whenOverbid: 'The partner keeps its aces, and shows them only if the opponents bid over us.',
    },
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
    belowOpening: (points) => `أقل من الفتحة اللي تفاهمنا عليها (${points})`,
    acesNow: 'الأسات خليتهم لين يزيدو علينا',
    conventions: {
      open: (points) => `نفتحو كي اليد تسوى ${points} على الأقل.`,
      jack: 'الفالي متاع الأتو',
      nine: 'التسعة متاع الأتو',
      threeTrumps: 'ثلاثة أتو ولا أكثر',
      belote: 'البلوط',
      ace: 'كل آس خارج الأتو',
      adds: (what, points) => `${what}: الشريك يزيد ${points}.`,
      addsNothing: (what) => `${what}: الشريك ما يزيدش عليه.`,
      atOnce: 'الشريك يعلن على أساتو من أول طلب.',
      whenOverbid: 'الشريك يخلي أساتو، وما يعلن عليهم كان كي الخصم يزيد علينا.',
    },
  },
};
