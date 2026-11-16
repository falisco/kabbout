// Every English text of the screen. Same keys as fr.js. The words of the game itself stay as the
// table says them: belote, rebelote, contre, surcontre, kabbout.

const ranks = { 7: '7', 8: '8', 9: '9', T: '10', J: 'Jack', Q: 'Queen', K: 'King', A: 'Ace' };
const suits = { S: 'Spades', H: 'Hearts', D: 'Diamonds', C: 'Clubs' };
const cardName = (r, s) => `${ranks[r]} of ${suits[s].toLowerCase()}`;
const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;

export default {
  // the page
  docTitle: 'Kabbout — belote contrée',
  docDesc: 'Kabbout: belote contrée the way cafés play it, two against two, with three regulars at your table. Eight tricks or it doesn’t count.',
  tagline: 'Belote contrée, café rules. Eight tricks or it doesn’t count.',
  kicker: 'Belote contrée',

  // cards and bids
  ranks,
  suits,
  cardName,
  capot: 'Kabbout',
  you: 'You',
  // the three regulars, by seat: an opponent, the partner, the other opponent
  robots: { 1: 'Uncle Salah', 2: 'Selim', 3: 'Auntie Mongia' },
  youLower: 'you',
  us: 'Us',
  them: 'Them',
  target: 'Target',
  pts: 'pts',

  // top rail and small controls
  scoreTitle: 'Score sheet',
  scoreAria: 'Score, open the score sheet',
  lastTitle: 'Last trick and bids',
  rules: 'Rules',
  settings: 'Settings',
  menu: 'Menu',
  tip: 'Tip',
  chat: 'Quick phrases',
  cardValues: 'Card values',
  undo: 'Undo',
  badgeContre: 'Contre',
  badgeSurcontre: 'Surcontre',
  bidding: 'Bidding',
  tricksStar: (us, them) => `Tricks: us ${us}, them ${them}`,

  // seats
  bubPass: 'Pass',
  bubContre: 'Contre!',
  bubSurcontre: 'Surcontre!',
  dealer: 'Dealer',
  dealerLetter: 'D',
  partner: 'partner',
  notPlayable: (name) => `${name} (not playable)`,
  forced: 'Forced card',
  yourTurn: 'Your turn',

  // the auction
  bidLine: (type, name, me, bid) =>
    type === 'pass'
      ? `<b>${name}</b> ${me ? 'pass' : 'passed'}`
      : type === 'contre'
        ? `<b>${name}</b> ${me ? 'say' : 'says'} contre!`
        : type === 'surcontre'
          ? `<b>${name}</b> ${me ? 'say' : 'says'} surcontre!`
          : `<b>${name}</b> ${me ? 'bid' : 'bids'} <b>${bid}</b>`,
  histAria: 'Bidding history',
  colCard: 'Card',
  colTrump: 'Trump',
  colPlain: 'Plain suit',
  skipTuto: 'Skip the tutorial',
  gotIt: 'Got it',
  contreYou: (name) => `${name} says contre!`,
  contreSub: (bid) => `Your side plays <b>${bid}</b>. A surcontre multiplies the contract by 4.`,
  bestBid: (bid, who) => `Highest bid: <b>${bid}</b> (${who})`,
  noBidYet: 'Nobody has bid yet',
  decrease: 'Lower',
  increase: 'Higher',
  announce: (bid) => `Bid ${bid}`,
  chooseTrump: 'Pick a trump',
  yourBid: 'Your bid',
  pass: 'Pass',
  contre: 'Contre',
  surcontre: 'Surcontre',

  // what happens at the table
  tipBid: (bid) => `Tip: bid ${bid}`,
  tipAct: (type) => `Tip: ${type === 'pass' ? 'pass' : type === 'contre' ? 'say contre' : 'say surcontre'}`,
  tipCard: (r, suit, reason) => `Tip: ${ranks[r].toLowerCase()} ${suit} <small>(${reason})</small>`,
  tipReasons: {
    master: 'top card, it should take the trick',
    lead: 'the best lead given the cards already played',
    drawTrumps: 'lead trumps to draw the opponents’',
    duck: 'let this trick go: your high cards will do more later',
    feedPartner: 'your partner is winning: give them points',
    keepStrong: 'your partner is winning: keep your high cards',
    ruff: 'ruff to take the trick',
    win: 'this card takes the trick',
    discard: 'you cannot take the trick: give away as little as you can',
  },
  redeal: 'Everybody passes: new deal',
  sayBid: (name, type, value, suit) =>
    `${name} ${type === 'pass' ? 'passes' : type === 'contre' ? 'says contre' : type === 'surcontre' ? 'says surcontre' : `bids ${value} ${suits[suit].toLowerCase()}`}`,
  contractToast: (name, me, bid, mult) => `${me ? 'You play' : `${name} plays`} ${bid}${mult === 2 ? ' · contre' : mult === 4 ? ' · surcontre' : ''}`,
  sayPlayed: (name, r, s) => `${name} plays the ${cardName(r, s).toLowerCase()}`,
  belote: 'Belote!',
  rebelote: 'Rebelote!',
  sayTrick: (name, me) => (me ? 'You take the trick' : `${name} takes the trick`),

  // tutorial
  coach: {
    bid: '<b>Bidding.</b> Choose a value (− +) and a trump suit, then Bid. Weak hand? Pass.',
    contre: '<b>Contre</b>: you bet the opponents will fail. Points are doubled; they may answer surcontre (×4).',
    play: '<b>Your turn.</b> Tap a highlighted card. Greyed cards are not allowed: you must follow suit, ruff, overruff, or play a higher trump.',
  },

  // quick phrases, and what each of the three regulars answers (by seat)
  phrases: {
    hello: 'Aslema!',
    wellPlayed: 'Well played!',
    thanks: 'Thanks!',
    sorry: 'Sorry…',
    tea: 'Tea’s on you!',
    bye: 'Same table tomorrow!',
  },
  replies: {
    hello: { 1: 'Aslema. Shall we deal?', 2: 'Aslema, partner!', 3: 'Aslema! Ready to lose?' },
    wellPlayed: { 1: 'Luck, nothing else.', 2: 'We make a team!', 3: 'Don’t get used to it.' },
    thanks: { 1: 'Hmm.', 2: 'Any time!', 3: 'Sure, sure.' },
    sorry: { 1: 'Too late!', 2: 'Never mind, we’ll get it back.', 3: 'Forgiven. Not forgotten.' },
    tea: { 1: 'We’ll see at the end.', 2: 'With pine nuts!', 3: 'Loser pays, that’s the rule.' },
    bye: { 1: 'Same time.', 2: 'See you tomorrow!', 3: 'Bring some change.' },
  },

  // menu
  aiPlanned: (variable) => `On its way: a coach that explains your deal, and bids you can say out loud. Both will wake up with ${variable}.`,
  aiOff: (variable) => `The AI is asleep on this server: write ${variable} in .env and start it again.`,
  menuSub: (partner, o1, o2, target) =>
    `You and <b>${partner}</b> against <b>${o1}</b> and <b>${o2}</b>. First to <b>${target} points</b>, belote-rebelote included.`,
  resume: 'Back to the game',
  newGame: 'New game',
  stats: (won, played) => `Games won: ${won} out of ${played}`,
  robotLevel: 'Robot level',
  levels: { easy: 'Easy', normal: 'Normal', expert: 'Expert' },
  language: 'Language',
  scoringLabel: 'Scoring',
  scorings: { ffb: 'With contract', tunisian: 'Without contract (Tunisian)' },
  scoringNext: 'The scoring you chose will apply to the next game.',
  scoringLine: (name) => `Scoring: ${name.toLowerCase().replace('tunisian', 'Tunisian')}`,

  // end of a deal
  roundTitle: (made, takersAreUs) => (made ? 'Contract made!' : takersAreUs ? 'You went down' : 'They went down!'),
  kabboutUs: 'Kabbout!',
  kabboutThem: 'You got kabbouted…',
  kabboutUsSub: 'All eight tricks. The whole café heard about it.',
  kabboutThemSub: 'Not a single trick. Pay for the tea and deal again.',
  roundSub: (bid, mult, who, takersAreUs, takerPts, defencePts) =>
    `Contract <b>${bid}</b>${mult > 1 ? ` <b>${mult === 2 ? 'contre' : 'surcontre'}</b>` : ''} by ${who} · ${takersAreUs ? 'Us' : 'Them'} ${takerPts} pts, ${takersAreUs ? 'Them' : 'Us'} ${defencePts} pts`,
  rowTricks: 'Tricks',
  rowBelote: 'Belote',
  rowContract: (mult) => (mult === 2 ? 'Contract (contre ×2)' : mult === 4 ? 'Contract (surcontre ×4)' : 'Contract'),
  roundTotal: 'Deal total',
  score: 'Score',
  tricksWon: (us, them, target) => `Tricks won: us ${us}, them ${them} · Target ${target}`,
  roundNote: {
    ffb: '<b>The count.</b> The contract is made if the bidding side scores at least its value (tricks + belote). Otherwise the defence scores 160 + the contract. Points are rounded to the nearest ten.',
    tunisian:
      '<b>The count.</b> The contract is made if the bidding side scores at least its value (tricks + belote): each side then scores its own points. Otherwise the defence scores 160. The contract is never added.',
  },
  seeResult: 'See the result',
  nextDeal: 'Next deal',

  // end of a game
  win: 'Victory!',
  lose: 'Defeat',
  winSub: (partner) => `${partner} and you win the game. Tea’s on them.`,
  loseSub: (o1, o2) => `${o1} and ${o2} win the game. Tea’s on you.`,
  finalScore: 'Final score',
  dealsPlayed: (n) => plural(n, 'deal played', 'deals played'),
  replay: 'Play again',

  // last trick, score sheet
  lastTrick: 'Last trick',
  wonBy: (name) => `Won by <b>${name}</b>`,
  lastTrickRule: 'The last trick may only be looked at once the next one is complete, before it is collected, never before playing your card.',
  dealBids: 'Bids of this deal',
  close: 'Close',
  colContract: 'Contract',
  sheetDown: 'down',
  noDeals: 'No deal finished yet.',

  // rules
  rulesTitle: 'Rules of belote contrée',
  rulesBody: (target, values, scoring) => `<p class="sub">Nobody at the café says capot. They say <b>kabbout</b>, and they say it loud.</p>
      <h3>The deal</h3><ul><li>32 cards, 8 each, dealt 3-2-3. Partners sit across from each other.</li><li>The player after the dealer speaks first and leads the first trick. Play goes counter-clockwise.</li></ul>
      <h3>Bidding</h3><ul><li>A bid is a number of points (80 to 160, by tens) and a trump suit. Above that: <b>Kabbout</b> (the capot: taking all eight tricks), which nothing beats.</li><li>Each bid must beat the one before. Three passes after a bid close the auction. Four passes at the start: new deal.</li><li>An opponent may say <b>contre</b> at their turn (contract ×2): the contract is then fixed, and only the bidding side may still say <b>surcontre</b> (×4).</li></ul>
      <h3>Play</h3><ul><li>Follow the suit led. On a trump lead, play a higher trump if you can.</li><li>Without the suit: ruff, and overruff if an opponent has already ruffed. If your partner is winning the trick, play what you like.</li><li>If you cannot overruff, play any card: you never have to underruff.</li><li>The last trick may only be looked at once the next one is complete, before it is collected.</li></ul>
      <h3>Points</h3>
      ${values}
      <ul><li>Last trick (dix de der): +10. Total: 162 points. All eight tricks for one side: the last trick is worth 100, 252 in all.</li><li>Belote-Rebelote (king and queen of trumps): 20 points, which count towards the contract. No other declarations in contrée.</li><li>Each deal’s points are rounded to the nearest ten (85 gives 90, 84 gives 80).</li></ul>
      <h3>The contract</h3><ul><li>Made if the bidding side scores at least the value bid (tricks + belote), even if the defence scores more.</li>${
        scoring === 'tunisian'
          ? `<li><b>Scoring without contract (Tunisian)</b>: the value bid is never added to the score.</li><li>Made: each side scores the points it made.</li><li>Failed: the bidder scores nothing, the defence scores 160, and the belote, even if the bidder held it.</li><li>Contre: the side that wins the deal scores 320 and the belote; the other scores nothing. Surcontre: 640.</li><li>Kabbout bid: 500 if made, 500 to the defence if it fails (×2 or ×4 on a contre). All eight tricks without bidding them: 250.</li>`
          : `<li><b>Scoring with contract</b>: official rules of the Fédération Française de Belote, “points made + points bid”.</li><li>Made: the bidder scores the contract plus their points, the defence scores its own.</li><li>Failed: the bidder scores nothing, the defence scores 160 + the contract, and the belote, even if the bidder held it.</li><li>Contre: the side that wins the deal scores 320 + contract ×2 and the belote; the other scores nothing. Surcontre: 640 + contract ×4.</li><li>Kabbout bid: 500 if made, 500 to the defence if it fails (×2 or ×4 on a contre).</li>`
      }<li>First side to ${target} points.</li></ul>`,

  // settings
  off: 'Off',
  on: 'On',
  setMusic: 'Music',
  setSound: 'Sound effects',
  setHl: 'Highlight the player to act',
  setWhy: 'Robots’ reasoning (reveals their cards)',
  setAutoForced: 'Play forced cards for me',
  setUndo: 'Undo a misclick',
  setLearn: 'Learning mode (tips)',
  reviewTuto: 'Replay the tutorial',
  leaveTable: 'Leave the table',
  credits: 'Cards: Mesmedir (OpenGameArt) after Dmitry Fomin, public domain (CC0). Sounds: Kenney, public domain (CC0).',
  valuesSub: 'Last trick: +10 · Belote-Rebelote: 20 · Total of a deal: 162',
};
