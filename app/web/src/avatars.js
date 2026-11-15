// The faces of the three regulars of the café, drawn for the round seat markers.
// Flat drawings in a 64 × 64 box; the seat's ring is drawn by the page around them.

const BG = '#172a47';
const INK = '#1b2232';
const BRASS = '#d6ae62';
const eyes = (y = 30.5) => `<circle cx="26.6" cy="${y}" r="1.7" fill="${INK}"/><circle cx="37.4" cy="${y}" r="1.7" fill="${INK}"/>`;

/** Am Salah: an older man, grey at the temples, a thick moustache and the red chéchia. */
const salah = `
  <path d="M8 64c0-11 10-17 24-17s24 6 24 17z" fill="#efe7d6"/>
  <path d="M27 47l5 7 5-7" fill="none" stroke="#c9bfa8" stroke-width="1.6" stroke-linejoin="round"/>
  <rect x="27" y="39" width="10" height="10" rx="4" fill="#c98d62"/>
  <ellipse cx="32" cy="31" rx="12.5" ry="14.5" fill="#d9a074"/>
  <path d="M19.4 30c-1.300-3 -1-6 .6-8l1.400 1.500zM44.600 30c1.300-3 1-6-.6-8l-1.400 1.500z" fill="#d8dce2"/>
  <ellipse cx="19.300" cy="32" rx="2" ry="3" fill="#d9a074"/><ellipse cx="44.700" cy="32" rx="2" ry="3" fill="#d9a074"/>
  <path d="M19.500 24.500C18 8.500 46 8.500 44.500 24.500c-8-2.600-17-2.600-25 0z" fill="#c22f33"/>
  <path d="M19.500 24.500c8-2.600 17-2.600 25 0l-.2-2.600c-8-2.300-16.600-2.300-24.600 0z" fill="#9c2327"/>
  <path d="M23 27.200q3.600-2.200 6.600-.4M34.400 26.800q3-1.800 6.600.4" fill="none" stroke="#6f7480" stroke-width="1.800" stroke-linecap="round"/>
  ${eyes()}
  <path d="M32 31v5.200q-1.600.9-2.600 0" fill="none" stroke="#b97a50" stroke-width="1.300" stroke-linecap="round"/>
  <path d="M32 38.200c-3.400-2.300-8-1.500-11 2.300 3.600 1.900 8 1.500 11-.6 3 2.100 7.400 2.500 11 .6-3-3.800-7.600-4.600-11-2.300z" fill="#e9e6df"/>
  <path d="M28.500 43.200q3.500 1.500 7 0" fill="none" stroke="#a5613f" stroke-width="1.200" stroke-linecap="round"/>`;

/** Selim: a young geek, round glasses, headphones over a mop of dark hair, a hoodie. */
const selim = `
  <path d="M8 64c0-11 10-17 24-17s24 6 24 17z" fill="#2f8f8a"/>
  <path d="M24 48.500c2 5 14 5 16 0l2.500 1.500c-3 7.500-18 7.500-21 0z" fill="#226f6b"/>
  <path d="M29.500 52v7M34.500 52v7" stroke="#f6f1e4" stroke-width="1.200" stroke-linecap="round"/>
  <rect x="27" y="39" width="10" height="10" rx="4" fill="#cf9a6c"/>
  <ellipse cx="32" cy="31" rx="12.300" ry="14.300" fill="#e2af82"/>
  <path d="M19.600 29c-2-9 3.500-16 12.400-16s14.400 7 12.400 16c-1.500-4.500-3.500-7-6-8-4 2.600-9.800 3-14.800 1.200-1.800 1.300-3.200 3.600-4 6.800z" fill="#221c26"/>
  <path d="M17.500 31C15 16 24 9.500 32 9.500S49 16 46.500 31" fill="none" stroke="#f6f1e4" stroke-width="2.600" stroke-linecap="round"/>
  <rect x="14" y="27" width="6" height="11" rx="3" fill="#f08f76"/><rect x="44" y="27" width="6" height="11" rx="3" fill="#f08f76"/>
  ${eyes(31)}
  <circle cx="26.600" cy="31" r="4.600" fill="#ffffff22" stroke="${INK}" stroke-width="1.500"/><circle cx="37.400" cy="31" r="4.600" fill="#ffffff22" stroke="${INK}" stroke-width="1.500"/>
  <path d="M31.200 31h1.600" stroke="${INK}" stroke-width="1.500"/>
  <path d="M22.800 25.600q3.400-1.600 6.400-.2M34.800 25.400q3-1.400 6.400.2" fill="none" stroke="#221c26" stroke-width="1.500" stroke-linecap="round"/>
  <path d="M27.500 39.500q4.500 3.600 9 0" fill="none" stroke="#a5613f" stroke-width="1.500" stroke-linecap="round"/>`;

/**
 * Khalti Mongia: an Amazigh woman, the scarf tied over a band of coins, heavy earrings, the
 * brooches of the melia on her shoulders, and the traditional tattoos on forehead, cheeks and chin.
 */
const TATTOO = '#2c6b78';
const mongia = `
  <path d="M8 64c0-11 10-17 24-17s24 6 24 17z" fill="#8f2f3c"/>
  <path d="M8 64c1-9 8-14.500 18-16.300L32 58l6-10.300c10 1.800 17 7.300 18 16.300z" fill="#243f7a"/>
  <circle cx="20" cy="54" r="3.200" fill="none" stroke="${BRASS}" stroke-width="1.600"/><circle cx="44" cy="54" r="3.200" fill="none" stroke="${BRASS}" stroke-width="1.600"/>
  <path d="M20 54l3-3M44 54l-3-3" stroke="${BRASS}" stroke-width="1.400" stroke-linecap="round"/>
  <rect x="27" y="39" width="10" height="10" rx="4" fill="#b97d55"/>
  <path d="M17.500 30C15 12 49 12 46.500 30c1 7 .5 12-1.500 16l-5-3H24l-5 3c-2-4-2.500-9-1.500-16z" fill="#c9503f"/>
  <ellipse cx="32" cy="31.500" rx="11.800" ry="14" fill="#cd9468"/>
  <path d="M20 27C19 13 45 13 44 27c-7.500-4-16.500-4-24 0z" fill="#c9503f"/>
  <path d="M20.200 25.500c7.500-3.600 16.100-3.600 23.600 0l-.3 3c-7.300-3.300-15.700-3.300-23 0z" fill="#7c2330"/>
  <g fill="${BRASS}"><circle cx="23" cy="27" r="1.500"/><circle cx="27.400" cy="25.800" r="1.500"/><circle cx="32" cy="25.400" r="1.700"/><circle cx="36.600" cy="25.800" r="1.500"/><circle cx="41" cy="27" r="1.500"/></g>
  <circle cx="18.800" cy="38.500" r="3.400" fill="none" stroke="${BRASS}" stroke-width="1.700"/><circle cx="45.200" cy="38.500" r="3.400" fill="none" stroke="${BRASS}" stroke-width="1.700"/>
  <path d="M23.200 31.400q3.400-1.800 6.400-.3M34.400 31.100q3-1.500 6.400.3" fill="none" stroke="#3a2620" stroke-width="1.500" stroke-linecap="round"/>
  ${eyes(34)}
  <path d="M32 34.500v4q-1.400.8-2.400 0" fill="none" stroke="#a66c47" stroke-width="1.200" stroke-linecap="round"/>
  <path d="M28 41.200q4 2.600 8 0" fill="none" stroke="#8c3b3b" stroke-width="1.600" stroke-linecap="round"/>
  <g fill="none" stroke="${TATTOO}" stroke-width=".8" stroke-linecap="round">
    <path d="M32 28.600v3M30.600 29.600l1.400-1.400 1.400 1.400"/>
    <path d="M32 43v3.200M30.400 44.200h3.200M30.800 45.600h2.400"/>
    <path d="M23.200 38.200h2.200M24.300 37.100v2.200M38.600 38.200h2.200M39.700 37.100v2.200"/>
  </g>`;

const FACES = { 1: salah, 2: selim, 3: mongia };

/** The face of a robot, by its place after this screen's player (1, 2 or 3); '' for anyone else. */
export function avatarSVG(place) {
  const face = FACES[place];
  return face ? `<svg class="face" viewBox="0 0 64 64" aria-hidden="true"><rect width="64" height="64" fill="${BG}"/>${face}</svg>` : '';
}
