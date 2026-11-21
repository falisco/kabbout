// Kabbout, a belote contrée table. Rules, scoring and robots come from the engine (engine/src);
// the modules here only draw, pace and listen. Bundled into app.js by `npm run build:web`.
import { initAudio } from './audio.js';
import { advance, askServer } from './flow.js';
import { detectLang, setLang } from './i18n/index.js';
import { initInput } from './input.js';
import { preloadCards } from './labels.js';
import { OLD_KEYS, migrateStorage, removeKey } from './storage.js';
import { loadConventions, loadPrefs, overlay, prefs } from './store.js';

migrateStorage();
loadPrefs();
loadConventions();
// an earlier version saved the game in progress; this one does not, and clears what was left
removeKey(OLD_KEYS.save);
setLang(detectLang(prefs.lang));
preloadCards();
initInput();
initAudio({ soundOn: () => prefs.sound, musicOn: () => prefs.music });
overlay.modal = 'menu';
advance();
// the game does not wait for the server, and plays on whatever becomes of the question
askServer().catch(() => {});
