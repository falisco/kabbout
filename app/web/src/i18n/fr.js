// Every French text of the screen. Same keys as en.js: a string, a table, or a function when the
// text takes values, a plural or a conjugation. Markup in a text is trusted: the texts are
// written here, and the only values of unknown origin (the robots' reasons) are escaped by the caller.

const ranks = { 7: '7', 8: '8', 9: '9', T: '10', J: 'Valet', Q: 'Dame', K: 'Roi', A: 'As' };
const suits = { S: 'Pique', H: 'Cœur', D: 'Carreau', C: 'Trèfle' };
const cardName = (r, s) => `${ranks[r]} de ${suits[s].toLowerCase()}`;
const plural = (n, one, many) => `${n} ${n > 1 ? many : one}`;

export default {
  // the page
  docTitle: 'Kabbout — belote contrée',
  docDesc: 'Kabbout : la belote contrée comme au café, à quatre, avec trois habitués à votre table. Huit plis, pas un de moins.',
  tagline: 'La belote contrée comme au café. Huit plis, pas un de moins.',
  kicker: 'Belote contrée',

  // cards and bids
  ranks,
  suits,
  cardName,
  capot: 'Kabbout',
  you: 'Vous',
  // the three regulars, by seat: an opponent, the partner, the other opponent
  robots: { 1: 'Tonton Salah', 2: 'Selim', 3: 'Tata Mongia' },
  youLower: 'vous',
  us: 'Nous',
  them: 'Eux',
  target: 'Objectif',
  pts: 'pts',

  // top rail and small controls
  scoreTitle: 'Feuille de marque',
  scoreAria: 'Score, ouvrir la feuille de marque',
  lastTitle: 'Pli précédent et enchères',
  rules: 'Règles',
  settings: 'Paramètres',
  menu: 'Menu',
  tip: 'Conseil',
  chat: 'Phrases rapides',
  cardValues: 'Valeur des cartes',
  undo: 'Annuler',
  badgeContre: 'Contré',
  badgeSurcontre: 'Surcontré',
  bidding: 'Enchères',
  tricksStar: (us, them) => `Plis : nous ${us}, eux ${them}`,

  // seats
  bubPass: 'Passe',
  bubContre: 'Contre !',
  bubSurcontre: 'Surcontre !',
  dealer: 'Donneur',
  dealerLetter: 'D',
  partner: 'partenaire',
  notPlayable: (name) => `${name} (non jouable)`,
  forced: 'Carte forcée',
  yourTurn: 'À vous de jouer',

  // the auction
  bidLine: (type, name, me, bid) =>
    type === 'pass'
      ? `<b>${name}</b> ${me ? 'passez' : 'a passé'}`
      : type === 'contre'
        ? `<b>${name}</b> ${me ? 'contrez' : 'contre'} !`
        : type === 'surcontre'
          ? `<b>${name}</b> ${me ? 'surcontrez' : 'surcontre'} !`
          : `<b>${name}</b> ${me ? 'annoncez' : 'annonce'} <b>${bid}</b>`,
  histAria: 'Historique des enchères',
  colCard: 'Carte',
  colTrump: 'Atout',
  colPlain: 'Non atout',
  skipTuto: 'Passer le tutoriel',
  gotIt: 'Compris',
  contreYou: (name) => `${name} vous contre !`,
  contreSub: (bid) => `Votre équipe joue <b>${bid}</b>. Surcontrer multiplie le contrat par 4.`,
  bestBid: (bid, who) => `Meilleure annonce : <b>${bid}</b> (${who})`,
  noBidYet: 'Personne n’a encore annoncé',
  decrease: 'Diminuer',
  increase: 'Augmenter',
  announce: (bid) => `Annoncer ${bid}`,
  chooseTrump: 'Choisir l’atout',
  yourBid: 'À vous d’annoncer',
  pass: 'Passer',
  contre: 'Contrer',
  surcontre: 'Surcontrer',

  // what happens at the table
  tipBid: (bid) => `Conseil : annoncer ${bid}`,
  tipAct: (type) => `Conseil : ${type === 'pass' ? 'passer' : type === 'contre' ? 'contrer' : 'surcontrer'}`,
  tipCard: (r, suit, reason) => `Conseil : ${ranks[r].toLowerCase()} ${suit} <small>(${reason})</small>`,
  tipReasons: {
    master: 'carte maîtresse, elle devrait faire le pli',
    lead: 'la meilleure entame d’après les cartes déjà tombées',
    drawTrumps: 'jouez atout pour faire tomber ceux des adversaires',
    duck: 'laissez ce pli : vos fortes cartes serviront mieux plus tard',
    feedPartner: 'votre partenaire est maître : donnez-lui des points',
    keepStrong: 'votre partenaire est maître : gardez vos fortes cartes',
    ruff: 'coupez pour prendre le pli',
    win: 'cette carte prend le pli',
    discard: 'vous ne pouvez pas prendre le pli : donnez le moins possible',
  },
  redeal: 'Tout le monde passe : nouvelle donne',
  sayBid: (name, type, value, suit) =>
    `${name} ${type === 'pass' ? 'passe' : type === 'contre' ? 'contre' : type === 'surcontre' ? 'surcontre' : `annonce ${value} ${suits[suit].toLowerCase()}`}`,
  contractToast: (name, me, bid, mult) =>
    `${me ? 'Vous jouez' : `${name} joue`} ${bid}${mult === 2 ? ' · contré' : mult === 4 ? ' · surcontré' : ''}`,
  sayPlayed: (name, r, s) => `${name} joue ${cardName(r, s).toLowerCase()}`,
  belote: 'Belote !',
  rebelote: 'Rebelote !',
  sayTrick: (name, me) => (me ? 'Vous remportez le pli' : `${name} remporte le pli`),

  // tutorial
  coach: {
    bid: '<b>Les enchères.</b> Choisissez une valeur (− +) et un atout, puis Annoncer. Main faible ? Passez.',
    contre: '<b>Contrer</b> : vous pariez que les adversaires chuteront. Les points sont doublés ; ils peuvent surcontrer (×4).',
    play: '<b>À vous de jouer.</b> Touchez une carte en surbrillance. Les cartes grisées sont interdites : il faut fournir la couleur, couper, surcouper, ou monter à l’atout.',
  },

  // quick phrases, and what each of the three regulars answers (by seat)
  phrases: {
    hello: 'Aslema !',
    wellPlayed: 'Bien joué !',
    thanks: 'Merci !',
    sorry: 'Désolé…',
    tea: 'Le thé est pour toi !',
    bye: 'À demain, même table !',
  },
  replies: {
    hello: { 1: 'Aslema. On distribue ?', 2: 'Aslema, partenaire !', 3: 'Aslema ! Prêt à perdre ?' },
    wellPlayed: { 1: 'La chance, c’est tout.', 2: 'On est une équipe !', 3: 'Ne t’habitue pas.' },
    thanks: { 1: 'Hmm.', 2: 'Avec plaisir !', 3: 'De rien, de rien.' },
    sorry: { 1: 'Trop tard !', 2: 'Pas grave, on se refait.', 3: 'On pardonne, on n’oublie pas.' },
    tea: { 1: 'On verra à la fin.', 2: 'Avec des pignons !', 3: 'Le perdant paie, c’est la règle.' },
    bye: { 1: 'Même heure.', 2: 'À demain !', 3: 'Ramène de la monnaie.' },
  },

  // menu
  menuSub: (partner, o1, o2, target) =>
    `Vous et <b>${partner}</b> contre <b>${o1}</b> et <b>${o2}</b>. Partie en <b>${target} points</b>, belote-rebelote comprise.`,
  resume: 'Reprendre la partie',
  newGame: 'Nouvelle partie',
  stats: (won, played) => `Parties gagnées : ${won} sur ${played}`,
  robotLevel: 'Niveau des robots',
  levels: { easy: 'Facile', normal: 'Normal', expert: 'Expert' },
  language: 'Langue',
  scoringLabel: 'Comptage',
  scorings: { ffb: 'Avec contrat', tunisian: 'Sans contrat (tunisien)' },
  scoringNext: 'Le comptage choisi vaudra pour la prochaine partie.',
  scoringLine: (name) => `Comptage : ${name.toLowerCase()}`,

  // end of a deal
  roundTitle: (made, takersAreUs) => (made ? 'Contrat rempli !' : takersAreUs ? 'Vous êtes dedans' : 'Ils sont dedans !'),
  kabboutUs: 'Kabbout !',
  kabboutThem: 'Vous êtes kabbout…',
  kabboutUsSub: 'Les huit plis. Tout le café est au courant.',
  kabboutThemSub: 'Pas un pli. On paie le thé et on redistribue.',
  roundSub: (bid, mult, who, takersAreUs, takerPts, defencePts) =>
    `Contrat <b>${bid}</b>${mult > 1 ? ` <b>${mult === 2 ? 'contré' : 'surcontré'}</b>` : ''} de ${who} · ${takersAreUs ? 'Nous' : 'Eux'} ${takerPts} pts, ${takersAreUs ? 'Eux' : 'Nous'} ${defencePts} pts`,
  rowTricks: 'Plis',
  rowBelote: 'Belote',
  rowContract: (mult) => (mult === 2 ? 'Contrat (contre ×2)' : mult === 4 ? 'Contrat (surcontre ×4)' : 'Contrat'),
  roundTotal: 'Total de la donne',
  score: 'Score',
  tricksWon: (us, them, target) => `Plis gagnés : nous ${us}, eux ${them} · Objectif ${target}`,
  roundNote: {
    ffb: '<b>Le décompte.</b> Le contrat est rempli si l’équipe du preneur fait au moins sa valeur (plis + belote). Sinon, la défense marque 160 + le contrat. Les points sont arrondis à la dizaine.',
    tunisian:
      '<b>Le décompte.</b> Le contrat est rempli si l’équipe du preneur fait au moins sa valeur (plis + belote) : chacun marque alors ses points. Sinon, la défense marque 160. Le contrat ne s’ajoute jamais.',
  },
  seeResult: 'Voir le résultat',
  nextDeal: 'Donne suivante',

  // end of a game
  win: 'Victoire !',
  lose: 'Défaite',
  winSub: (partner) => `${partner} et vous remportez la partie. Le thé est pour eux.`,
  loseSub: (o1, o2) => `${o1} et ${o2} remportent la partie. Le thé est pour vous.`,
  finalScore: 'Score final',
  dealsPlayed: (n) => plural(n, 'donne jouée', 'donnes jouées'),
  replay: 'Rejouer',

  // last trick, score sheet
  lastTrick: 'Pli précédent',
  wonBy: (name) => `Remporté par <b>${name}</b>`,
  lastTrickRule: 'Le pli précédent ne se consulte qu’une fois le pli suivant complet, avant qu’il soit ramassé, jamais avant de jouer sa carte.',
  dealBids: 'Enchères de la donne',
  close: 'Fermer',
  colContract: 'Contrat',
  sheetDown: 'dedans',
  noDeals: 'Aucune donne terminée pour l’instant.',

  // rules
  rulesTitle: 'Règles de la contrée',
  rulesBody: (target, values, scoring) => `<p class="sub">Au café, personne ne dit capot. On dit <b>kabbout</b>, et on le dit fort.</p>
      <h3>La donne</h3><ul><li>32 cartes, 8 par joueur, distribuées 3-2-3. Les partenaires sont face à face.</li><li>Le joueur qui suit le donneur parle en premier et entame le premier pli. On tourne dans le sens inverse des aiguilles d’une montre.</li></ul>
      <h3>Les enchères</h3><ul><li>On annonce un nombre de points (80 à 160, par 10) et un atout. Au-delà : <b>Kabbout</b> (le capot : faire les huit plis), que rien ne dépasse.</li><li>Chaque annonce doit dépasser la précédente. Trois passes après une annonce closent les enchères. Quatre passes d’entrée : nouvelle donne.</li><li>Un adversaire peut <b>contrer</b> à son tour de parole (contrat ×2) : le contrat est figé, et seule l’équipe du preneur peut encore <b>surcontrer</b> (×4).</li></ul>
      <h3>Le jeu</h3><ul><li>Fournir la couleur demandée. À l’atout, monter si possible.</li><li>Sans la couleur : couper, et surcouper si un adversaire a déjà coupé. Si votre partenaire est maître, vous jouez ce que vous voulez.</li><li>Si vous ne pouvez pas surcouper, vous jouez la carte de votre choix : on n’est pas obligé de sous-couper.</li><li>Le pli précédent ne se consulte qu’une fois le pli suivant complet, avant qu’il soit ramassé.</li></ul>
      <h3>Les points</h3>
      ${values}
      <ul><li>Dix de der : +10 au dernier pli. Total : 162 points. Les huit plis pour une équipe : le der vaut 100, soit 252.</li><li>Belote-Rebelote (roi et dame d’atout) : 20 points, qui comptent pour le contrat. Pas d’autres annonces en contrée.</li><li>Les points de chaque donne sont arrondis à la dizaine la plus proche (85 donne 90, 84 donne 80).</li></ul>
      <h3>Le contrat</h3><ul><li>Rempli si l’équipe du preneur réunit au moins la valeur annoncée (plis + belote), même si la défense fait plus de points.</li>${
        scoring === 'tunisian'
          ? `<li><b>Comptage sans contrat (tunisien)</b> : la valeur annoncée ne s’ajoute jamais au score.</li><li>Rempli : chaque équipe marque les points qu’elle a faits.</li><li>Chuté : le preneur ne marque rien, la défense marque 160, et la belote, même si elle était au preneur.</li><li>Contré : l’équipe qui gagne la donne marque 320 et la belote ; l’autre ne marque rien. Surcontré : 640.</li><li>Kabbout demandé : 500 s’il est réussi, 500 à la défense s’il chute (×2 ou ×4 si contré). Les huit plis sans l’avoir demandé : 250.</li>`
          : `<li><b>Comptage avec contrat</b> : règles officielles de la Fédération Française de Belote, « points faits + points demandés ».</li><li>Rempli : le preneur marque son contrat plus ses points, la défense marque les siens.</li><li>Chuté : le preneur ne marque rien, la défense marque 160 + le contrat, et la belote, même si elle était au preneur.</li><li>Contré : l’équipe qui gagne la donne marque 320 + contrat ×2 et la belote ; l’autre ne marque rien. Surcontré : 640 + contrat ×4.</li><li>Kabbout demandé : 500 s’il est réussi, 500 à la défense s’il chute (×2 ou ×4 si contré).</li>`
      }<li>Première équipe à ${target} points.</li></ul>`,

  // settings
  off: 'Off',
  on: 'On',
  setMusic: 'Musique',
  setSound: 'Effets sonores',
  setHl: 'Surbrillance du joueur actif',
  setWhy: 'Raisonnement des robots (dévoile leurs cartes)',
  setAutoForced: 'Jouer seul les cartes forcées',
  setUndo: 'Annuler un mauvais clic',
  setLearn: 'Mode apprentissage (conseils)',
  reviewTuto: 'Revoir le tutoriel',
  leaveTable: 'Quitter la table',
  credits: 'Cartes : Mesmedir (OpenGameArt) d’après Dmitry Fomin, domaine public (CC0), index français ajoutés. Sons : Kenney, domaine public (CC0).',
  valuesSub: 'Dix de der : +10 · Belote-Rebelote : 20 · Total d’une donne : 162',
};
