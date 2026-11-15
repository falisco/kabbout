// Sound. Three layers on one output: recorded card and chip noises (Kenney, CC0, in sounds.js),
// short synthesised chimes that tell what just happened without looking (your turn, trick won,
// round and game results), and a quiet synthesised background music.

/** base64 MP3s by family, set by sounds.js */
const SOUNDS = () => /** @type {any} */ (window).SND_DATA;

/** which sounds the player wants; given by initAudio so that this module knows nothing of where settings live */
let settings = { soundOn: () => true, musicOn: () => false };

/** @type {AudioContext} */
let audioContext = null;
/** where effects and music are sent; both end in a limiter so that overlapping sounds never clip */
let fxOut = null;
let musicOut = null;

function setup() {
  audioContext = new (window.AudioContext || /** @type {any} */ (window).webkitAudioContext)();
  const limiter = audioContext.createDynamicsCompressor();
  limiter.threshold.value = -12;
  limiter.ratio.value = 8;
  limiter.connect(audioContext.destination);
  fxOut = audioContext.createGain();
  fxOut.connect(limiter);
  musicOut = audioContext.createGain();
  musicOut.gain.value = 0;
  musicOut.connect(limiter);
}
export function unlockAudio() {
  try {
    if (!audioContext) setup();
    if (audioContext.state === 'suspended') audioContext.resume();
    loadSounds();
    if (settings.musicOn() && !musicTimer) updateMusic();
  } catch {}
}

// Recorded effects.
const soundBuffers = {};
let soundsLoading = false;
function loadSounds() {
  if (soundsLoading || !audioContext || !SOUNDS()) return;
  soundsLoading = true;
  for (const [k, list] of Object.entries(SOUNDS())) {
    soundBuffers[k] = [];
    list.forEach((b64) => {
      const bin = atob(b64);
      const u = new Uint8Array(bin.length);
      for (let i = 0; i < bin.length; i++) u[i] = bin.charCodeAt(i);
      audioContext
        .decodeAudioData(u.buffer)
        .then((buf) => soundBuffers[k].push(buf))
        .catch(() => {});
    });
  }
}
function sample(k, vol = 0.7, delay = 0) {
  const l = soundBuffers[k];
  if (!l || !l.length) return;
  const src = audioContext.createBufferSource();
  const g = audioContext.createGain();
  src.buffer = l[(Math.random() * l.length) | 0];
  src.playbackRate.value = 0.94 + Math.random() * 0.12;
  g.gain.value = vol;
  src.connect(g).connect(fxOut);
  src.start(audioContext.currentTime + delay);
}

// Synthesised notes.
/** frequency of the note `n` semitones above A4 */
const hz = (n) => 440 * 2 ** (n / 12);
/** A soft struck note: a sine and its octave, fading out over `len` seconds. */
function bell(out, n, at, len, vol) {
  const g = audioContext.createGain();
  g.gain.setValueAtTime(0.0001, at);
  g.gain.exponentialRampToValueAtTime(vol, at + 0.012);
  g.gain.exponentialRampToValueAtTime(0.0001, at + len);
  g.connect(out);
  for (const [mult, part] of [
    [1, 1],
    [2, 0.25],
  ]) {
    const o = audioContext.createOscillator();
    const p = audioContext.createGain();
    o.frequency.value = hz(n) * mult;
    p.gain.value = part;
    o.connect(p).connect(g);
    o.start(at);
    o.stop(at + len + 0.05);
  }
}
/** A dull low knock, falling in pitch. */
function thud(at, from, vol) {
  const o = audioContext.createOscillator();
  const g = audioContext.createGain();
  o.frequency.setValueAtTime(from, at);
  o.frequency.exponentialRampToValueAtTime(from / 2, at + 0.16);
  g.gain.setValueAtTime(vol, at);
  g.gain.exponentialRampToValueAtTime(0.0001, at + 0.2);
  o.connect(g).connect(fxOut);
  o.start(at);
  o.stop(at + 0.25);
}
/**
 * The chimes, as [semitones above A4, start (s), length (s)] and a volume.
 * Good news goes up (major), bad news comes down (minor); the bigger the news, the longer the phrase.
 */
const CHIMES = {
  turn: {
    vol: 0.1,
    notes: [
      [7, 0, 0.35],
      [12, 0.09, 0.5],
    ],
  },
  trickUs: { vol: 0.06, notes: [[10, 0, 0.3]] },
  belote: {
    vol: 0.08,
    notes: [
      [10, 0, 0.25],
      [15, 0.1, 0.5],
    ],
  },
  roundWin: {
    vol: 0.11,
    notes: [
      [3, 0, 0.4],
      [7, 0.11, 0.4],
      [10, 0.22, 0.4],
      [15, 0.33, 0.9],
    ],
  },
  roundLose: {
    vol: 0.09,
    notes: [
      [8, 0, 0.45],
      [5, 0.16, 0.45],
      [0, 0.32, 0.9],
    ],
  },
  gameWin: {
    vol: 0.11,
    notes: [
      [3, 0, 0.3],
      [7, 0.12, 0.3],
      [10, 0.24, 0.3],
      [15, 0.36, 0.5],
      [10, 0.52, 1.5],
      [15, 0.52, 1.5],
      [19, 0.52, 1.5],
    ],
  },
  gameLose: {
    vol: 0.09,
    notes: [
      [5, 0, 0.6],
      [3, 0.25, 0.6],
      [0, 0.5, 0.6],
      [-4, 0.75, 1.5],
    ],
  },
};
function chime(k, delay = 0) {
  const c = CHIMES[k];
  const t = audioContext.currentTime + delay;
  for (const [n, at, len] of c.notes) bell(fxOut, n, t + at, len, c.vol);
}

/** What each moment of the game sounds like. */
const EVENTS = {
  card: () => sample('place', 0.8),
  deal: () => {
    sample('shuffle', 0.6);
    for (let i = 0; i < 6; i++) sample('slide', 0.35, 1.1 + i * 0.09);
  },
  tick: () => sample('chip', 0.25),
  bid: () => sample('chip', 0.45),
  contre: () => {
    sample('stack', 0.6);
    thud(audioContext.currentTime, 130, 0.25);
  },
  /** a card that may not be played */
  refused: () => thud(audioContext.currentTime, 190, 0.12),
  turn: () => chime('turn'),
  win: () => {
    sample('shove', 0.5);
    chime('trickUs', 0.05);
  },
  lose: () => sample('shove', 0.4),
  belote: () => {
    sample('fan', 0.5);
    chime('belote', 0.05);
  },
  roundWin: () => {
    sample('handle', 0.5);
    chime('roundWin', 0.1);
  },
  roundLose: () => chime('roundLose'),
  gameWin: () => chime('gameWin'),
  gameLose: () => chime('gameLose'),
};
/** @param {keyof typeof EVENTS} k */
export function snd(k) {
  if (!settings.soundOn() || !audioContext) return;
  try {
    EVENTS[k]();
  } catch {}
}

// Background music.
// Eight slow chords (semitones above A4, around middle C) held by soft pads over a bass note,
// with a few notes of the C major pentatonic scale dropped in at random: it never repeats exactly.
const CHORD_SECONDS = 6;
const CHORDS = [
  [-9, -5, -2, 2], // C maj7
  [-12, -9, -5, -2], // A m7
  [-16, -12, -9, -5], // F maj7
  [-14, -10, -7, -5], // G 6
  [-17, -14, -10, -7], // E m7
  [-12, -9, -5, -2], // A m7
  [-19, -16, -12, -9], // D m7
  [-14, -9, -7, -4], // G sus
];
const MELODY = [3, 5, 7, 10, 12, 15];
let musicTimer = null;
let nextChord = 0;
let chordIndex = 0;

function pad(n, at, vol) {
  const o = audioContext.createOscillator();
  const f = audioContext.createBiquadFilter();
  const g = audioContext.createGain();
  o.type = 'triangle';
  o.frequency.value = hz(n);
  o.detune.value = Math.random() * 8 - 4;
  f.type = 'lowpass';
  f.frequency.value = 900;
  // each chord fades in while the previous one fades out
  g.gain.setValueAtTime(0, at);
  g.gain.linearRampToValueAtTime(vol, at + 2);
  g.gain.setValueAtTime(vol, at + CHORD_SECONDS - 0.5);
  g.gain.linearRampToValueAtTime(0, at + CHORD_SECONDS + 2);
  o.connect(f).connect(g).connect(musicOut);
  o.start(at);
  o.stop(at + CHORD_SECONDS + 2.1);
}
function scheduleChord(at) {
  const chord = CHORDS[chordIndex++ % CHORDS.length];
  for (const n of chord) pad(n, at, 0.02);
  pad(chord[0] - 12, at, 0.03);
  for (let beat = 1; beat < CHORD_SECONDS; beat += 1.5) {
    if (Math.random() < 0.45) bell(musicOut, MELODY[(Math.random() * MELODY.length) | 0], at + beat + Math.random() * 0.2, 2.2, 0.02);
  }
}
function musicTick() {
  if (!audioContext || !settings.musicOn()) return;
  try {
    // scheduled a little ahead on the audio clock, so that a busy page never makes it stumble
    while (nextChord < audioContext.currentTime + 1.5) {
      scheduleChord(Math.max(nextChord, audioContext.currentTime + 0.05));
      nextChord = Math.max(nextChord, audioContext.currentTime) + CHORD_SECONDS;
    }
  } catch {}
}
export function updateMusic() {
  clearInterval(musicTimer);
  musicTimer = null;
  if (!audioContext) return;
  const on = !!settings.musicOn();
  musicOut.gain.cancelScheduledValues(audioContext.currentTime);
  musicOut.gain.setTargetAtTime(on ? 1 : 0, audioContext.currentTime, on ? 0.6 : 0.15);
  if (on) {
    nextChord = Math.max(nextChord, audioContext.currentTime);
    musicTick();
    musicTimer = setInterval(musicTick, 500);
  }
}

/**
 * Audio may only start after a gesture: unlock on the first one, and go quiet while the page is hidden.
 * @param {{ soundOn: () => boolean, musicOn: () => boolean }} wanted the player's settings, asked each time
 */
export function initAudio(wanted) {
  settings = wanted;
  document.addEventListener('pointerdown', unlockAudio, { once: true });
  document.addEventListener('keydown', unlockAudio, { once: true });
  document.addEventListener('visibilitychange', () => {
    if (!audioContext) return;
    try {
      if (document.hidden) audioContext.suspend();
      else audioContext.resume();
    } catch {}
  });
}
