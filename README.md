# Kabbout

![Kabbout, belote contrée with café rules](app/web/brand/social.png)

[![CI](https://github.com/falisco/kabbout/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/falisco/kabbout/actions/workflows/ci.yml)

Belote contrée for four: you and your partner against two opponents, at a quiet table with no chips. In French, English and Tunisian Arabic, counted the federation's way or the Tunisian way. The goal is an iOS / Android app, playable alone against robots and online with friends.

**The name.** At a Tunisian café table nobody says _capot_ when a side takes all eight tricks. They say _kabbout_ (كبّوط), and they say it loud. The emblem is the eight-pointed star of the tilework: one point per trick. During a deal a star in the middle of the table lights one point for each trick, in the colour of the side that took it; eight points of one colour and the whole screen says Kabbout.

## Run it

With Docker and nothing else:

```bash
git clone https://github.com/falisco/kabbout && cd kabbout
docker compose up
```

Open the address it prints, `http://localhost:8080`. That is the whole game: no key, no account, nothing to set. The port is published to this machine only (`127.0.0.1`); `compose.yaml` says what to change to open it to others.

Two services start: `app`, a Symfony application that serves the built game and `/api` on that one port, and `bridge`, the rules engine, which only `app` can reach. To change the port or to switch the AI on, copy `.env.example` to `.env`:

| Variable                  | What it does                                                                                                                                                                                                                                                                   |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `ANTHROPIC_API_KEY`       | Switches the AI on: an Anthropic API key. Empty: the AI is off. The key is in no answer and no log.                                                                                                                                                                            |
| `KABBOUT_AI_MODEL`        | The Claude model the coach asks, `claude-sonnet-5-5` unless set. On the evaluation below `claude-haiku-5-5` names the mistake as often and costs less, but its reasons are weaker; see there. A key with an empty model stops `app` at the start, with one line that says why. |
| `KABBOUT_PORT`            | The port the game is opened on, 8080 unless set.                                                                                                                                                                                                                               |
| `KABBOUT_COACH_LIMIT`     | Questions to the coach one visitor may ask in a minute, 6 unless set. Each one is a call to the model, on your key.                                                                                                                                                            |
| `KABBOUT_COACH_PER_DEAL`  | Questions one visitor may ask the coach about one deal, 2 unless set. The server counts them; the page shows how many are left.                                                                                                                                                |
| `KABBOUT_COACH_DAILY`     | Questions all visitors together may ask the coach in a day, 500 unless set: the most a day can cost on your key. Past it the coach says it is back tomorrow.                                                                                                                   |
| `KABBOUT_TRUSTED_PROXIES` | Behind a reverse proxy, its address or range (comma separated): a visitor is then the address the proxy forwards. Empty unless set: without it every player behind the proxy would be one visitor, sharing one count.                                                          |

`GET /api/capabilities` says what the server can do with the AI: for each feature (`coach`, `spoken_bids`, `referee`, `agent_seat`) a `state`, `planned`, `off` or `on`, and a `hint` when it is not on. The coach is in this version: `off` without a key, `on` with one. The others are `planned`, and the menu of the game says so in one line. The key is never part of an answer. An address or a method the API does not know is answered in JSON too (`404`, `405`), in the shape `{ "error": { "code", "message" } }`.

## Layout

| Folder     | Content                                                                                                                                                                                                                                                                                                                                                                                   |
| ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `engine/`  | The game engine in TypeScript: rules, bidding, scoring, robots. A pure state machine (`apply(state, seat, action)`) with no DOM and no timers, usable in the app and on a server. Each player only receives a `PlayerView`, which does not hold the others' cards.                                                                                                                        |
| `bridge/`  | The engine behind a small HTTP service, for the server side only: new game, action, view of a seat, legal moves, robot's move, replay of a recorded deal. It keeps nothing between two calls (the game travels in the request), draws from a seed sent by the caller, and has no dependency beyond Node. `contract/cases.json` records what it answers; both sides are tested against it. |
| `server/`  | The server side: a Symfony 8.1 application on PHP 8.5, cut by bounded context, then by layer (see [The server](#the-server)). The rules are not written a second time in PHP.                                                                                                                                                                                                             |
| `app/web/` | The game: `index.html` (layout), `styles.css`, `src/` (the interface in modules, see below), `test/` (tests of the game session and of the texts), `sounds.js` (CC0 sounds), `cards/`, `fonts/` (embedded: the page loads nothing from another site), `brand/` (app icons, share image). `npm run build:web` bundles `src/` and the engine into `app.js` (generated, not versioned).      |
| `e2e/`     | Functional tests in a real browser (Playwright): games played through the interface (taking a contract, contre, advice, quick phrases, tutorial), keyboard, undo, the three languages, layout on five phone formats, sounds, accessibility audit of every screen and dialog (axe, WCAG 2.2 AA).                                                                                           |
| `scripts/` | `serve.mjs` (local server with no dependency), `check-conformity.mjs` (the conformity checks of the CI) and `run-coverage.mjs` (what `npm run test:coverage` runs).                                                                                                                                                                                                                       |
| `tools/`   | `frcards.py` builds the cards from the CC0 deck (French indices, and the English court cards); `brand.mjs` draws the icon, the app icons, the card back and the share image from the star of the game (see `CREDITS.md`).                                                                                                                                                                 |

## Languages

French, English and Tunisian Arabic (derja, in Arabic letters). The game follows the browser's language (English when it speaks none of them) and the player can switch in the menu or the settings; the choice is remembered. `?lang=fr`, `?lang=en` or `?lang=ar` in the address forces one.

- Texts live in `app/web/src/i18n/` (`fr.js`, `en.js`, `ar.js`, same keys; `test/i18n.test.js` keeps them in step).
- In Arabic the texts read right to left (dialogs, bidding panel, messages); the table, the hand and the direction of play do not move, numbers stay 0-9 and a bid keeps its order (`90 ♥`).
- The three regulars are named in each language: Tonton Salah and Tata Mongia, Uncle Salah and Auntie Mongia, عم صالح and خالتي منجية.
- The words of the game stay as the table says them in both languages: belote, rebelote, contre, surcontre, kabbout.
- Court cards carry the index letters of the language: V D R in French and in Arabic, J Q K in English (`cards/en/`).
- The robots give their bidding reasons in the language of the screen (`engine/src/ai/phrases.ts`). A reason is written when the bid is made, so those already shown keep their language until the next deal.

## Rules

Those of the [official rules of belote contrée](https://www.ffbelote.org/wp-content/uploads/2016/01/regles-officielles-de-la-Belote-Contree-27-01-2016.pdf) of the Fédération Française de Belote (version of 27.01.2016), counting "points made + points bid", without the No Trump / All Trump variant.

- 32 cards shuffled at each deal and dealt 3-2-3; bids from 80 to 160 by tens, then the capot (shown as Kabbout), which nothing beats.
- Contre (×2) at one's turn to speak: the contract is fixed, only the side that was contred answers, by passing or with a surcontre (×4).
- Card play: follow suit, play a higher trump, ruff and overruff; no obligation to underruff; free choice when the partner is winning.
- Belote-rebelote 20 points, no other declarations, ten for the last trick; all eight tricks are worth 252.
- The contract is made as soon as the bidder reaches its value (tricks + belote), even if the defence scores more.
- Made: points made + contract, the defence keeps its points. Failed: 160 + contract to the defence, belote included whoever held it.
- Contre: 320 + contract × 2; surcontre: 640 + contract × 4; the belote goes to the winner of the deal, nothing to the other side (table of article 10.2, without the variant of note 1).
- Capot bid: 500, made or failed (× 2, × 4).
- Points rounded to the nearest ten; game to 1000 points, a tie beyond that is settled by one more deal.

These rules are pinned article by article in `engine/test/ffb-contree.test.ts`: any change to the engine that departs from them fails that file.

### Counting without contract (Tunisian)

The menu offers a second way of counting, the one of Tunisian cafés, where **the value bid is never added**: it only decides which side scores. A game keeps the count it started with; the choice is remembered for the next ones. The default is the federation's count above.

| Deal                                | Bidding side               | Defence                    |
| ----------------------------------- | -------------------------- | -------------------------- |
| Made                                | points made (+ its belote) | points made (+ its belote) |
| Made with all eight tricks, not bid | 250 (+ its belote)         | 0                          |
| Failed                              | 0                          | 160 + the belote           |
| Failed, the defence takes all eight | 0                          | 250 + the belote           |
| Contre / surcontre, made            | 320 / 640 + the belote     | 0                          |
| Contre / surcontre, failed          | 0                          | 320 / 640 + the belote     |
| Kabbout bid, made                   | 500 (× 2, × 4)             | 0                          |
| Kabbout bid, failed                 | 0                          | 500 (× 2, × 4)             |

Whether a contract is made is the same question in both counts. In the engine it is one field of the game (`scoring: 'ffb' | 'tunisian'`, set by `newGame`, `'ffb'` by default) and one table per count in `engine/src/scoring.ts`; `engine/test/tunisian-scoring.test.ts` pins this table. The expert robot plays its cards for the count in use; the robots' bidding is the same in both, calibrated on the federation's count (`npm run bench:robots -- --scoring tunisian` measures them).

## Interface (`app/web/src/`)

The screen never touches the engine directly: it goes through a **session**, which gives it the player's view (`PlayerView`), receives its actions and sends back the public events. Today's session runs the engine and the three robots in the page; an online session will offer the same from a server, without changing the rest.

| Module                                        | Role                                                                                                                                                                                                          |
| --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `session.js`                                  | The game as one seat lives it: view, legal moves, actions, robots, advice, undo. No DOM, tested.                                                                                                              |
| `game.js`                                     | This screen's game (`session`, current `view`) and the questions asked of it: my turn? forced card?                                                                                                           |
| `selectors.js`                                | Questions answered from a view alone (bids still possible, may I contre, which trick may be looked at). No state, tested.                                                                                     |
| `flow.js`                                     | How it unfolds: pace of the robots and of the pauses, the kabbout screen, undo, advice.                                                                                                                       |
| `feedback.js`                                 | What the table shows and sounds like for each event of the game.                                                                                                                                              |
| `speech.js`                                   | The bubbles the players speak in.                                                                                                                                                                             |
| `render/`                                     | The drawing, which reads the state and never changes it: `table.js` (score, seats, hand, trick, star of the tricks), `bidding.js` (auction), `modals.js` (dialogs), `transient.js` (messages, announcements). |
| `input.js`                                    | Clicks, taps and keys, turned into actions.                                                                                                                                                                   |
| `store.js`, `storage.js`                      | Presentation state and settings, in small groups by purpose; reading and writing local storage.                                                                                                               |
| `audio.js`                                    | Recorded card noises, synthesised chimes (your turn, trick, end of deal and of game, refused card), background music.                                                                                         |
| `i18n/`                                       | The language of the screen and every text, in French, English and Tunisian Arabic.                                                                                                                            |
| `star.js`                                     | The eight-pointed star: the logo, the tricks of the deal, the kabbout screen.                                                                                                                                 |
| `config.js`, `labels.js`, `html.js`, `dom.js` | Seats, the three regulars and the pace; names and markup of cards and bids; escaping; reaching the page.                                                                                                      |

## Robots

`engine/src/ai/`. A robot only receives its `PlayerView`: it never sees the others' cards. At the table they are three regulars of the café: Selim, your partner, against Uncle Salah and Auntie Mongia (Tonton Salah and Tata Mongia in French, عم صالح and خالتي منجية in Tunisian Arabic).

- **Bidding** (`bidding.ts`), the same at every level so that the partner can rely on it: opening at 80 on a trump base (jack-nine third, or jack fourth), even with no top card beside it, +10 per ace, per long trump, +20 for the belote; one raise of the partner's suit; capot only when it is certain. The contre is rare and calibrated on tens of thousands of deals: nine of trumps fourth, or third with two aces. The surcontre needs a margin of 30 points over the two hands.
- **Memory** (`memory.ts`): the cards played and what they reveal (a suit not followed, a trump not raised or not overruffed, the second card of a belote).
- **Card play**, three levels, set in the menu and the settings:

| Level    | What it does                                                                                                                                                      |
| -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `easy`   | Plays by rules of thumb (`policy.ts`) and forgets the tricks already played.                                                                                      |
| `normal` | Same rules, with the memory: stops drawing trumps when the opponents have none left, does not give an ace to a ruff, feeds its partner when the trick is safe.    |
| `expert` | Imagines 50 layouts of the hidden cards consistent with what it has seen and with the bidding, plays each possible card to the end and keeps the best on average. |

`npm run bench:robots` measures them in duplicate games (each deal played twice, sides swapped): normal takes about 10 points a deal from easy, expert more than 30 from normal. In the app the expert thinks for at most 0.12 s per card: on a slow device it imagines fewer layouts (12 at least). `engine/test/robots-strength.test.ts` keeps that order.

## Contract between the engine and its clients

The engine is made to run on a server too, facing clients it does not take at their word. `engine/test/contract.test.ts` keeps these guarantees:

- **What a player receives**: `viewFor(state, seat)` and `publicEvents(events)` hold neither the others' cards, nor a belote not yet announced, nor the robots' reasoning (which describes their cards and stays in the full state).
- **What the engine accepts**: `apply(state, seat, action, rng)` refuses with an `EngineError` any unknown seat, any action that is malformed, out of turn or against the rules, and only copies the known fields of an action into the state.
- **No saved game**: a game lives as long as the page. Only the settings, the language, the robots' level and the statistics are stored on the device.

## Bridge between the engine and the server

The rules stay in TypeScript, shared by the game in the browser and the server: a PHP copy would be a second rule book to keep in step. The server asks them through `bridge/`, which answers JSON over HTTP on the internal network only, since it hands out full game states.

| Call                           | Answer                                                                                                                                                                |
| ------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `POST /games`                  | A new game dealt from `seed` (32 to 64 hexadecimal digits; `scoring`, `target` and `dealer` as the engine takes them).                                                |
| `POST /games/apply`            | The game after one `action` of one `seat`, and the public events.                                                                                                     |
| `POST /games/view`             | What one seat may know: its hand and everything public.                                                                                                               |
| `POST /games/legal`            | The bids and cards open to a seat now; nothing out of turn.                                                                                                           |
| `POST /games/robot`            | What a robot of a given `level` does for the seat to act, from that seat's view alone.                                                                                |
| `POST /deals/replay`           | A recorded deal (count, dealer, auction, eight tricks) played again: hands, contract, tricks, score sheet.                                                            |
| `POST /analysis/summary`       | A recorded deal summed up for one `seat`: the auction, the contract, what each team made.                                                                             |
| `POST /analysis/trick`         | One `trick` (1 to 8) as it was played: the four cards, who led, who won, its points.                                                                                  |
| `POST /analysis/expert-choice` | What the expert would have played at the seat's turn in a `trick`, from what the seat could know: every legal card with its average, and the loss of the card played. |
| `POST /analysis/what-if`       | The deal finished by four experts from another `card` in a `trick`, next to the same from the card really played.                                                     |
| `POST /analysis/swings`        | The seat's decisions the expert would have made otherwise, the costliest first.                                                                                       |
| `GET /health`                  | `{ "ok": true }`                                                                                                                                                      |

- What the engine refuses, the bridge refuses: `422` with the engine's own message, and for a recorded deal the bid or the trick and seat where it goes wrong. A request it does not understand is a `400`.
- A seat's view never holds another hand, a belote not yet announced or a robot's reasoning; `bridge/test/bridge.test.ts` checks every view of a whole game.
- Every call that draws at random takes a seed, so the same question always gets the same answer, byte for byte. A seed is 128 to 256 bits and the draws are an HMAC-SHA256 stream: the engine's own generator takes 32 bits, few enough to try them all against the eight cards of a hand and find the three others. A view gives the hand sorted, not in the order it was dealt.
- A seed serves one call. On the PHP side a game keeps one secret `Seed` and derives a new one for each call (`$secret->derive('move 17')`); a seed used twice deals the same cards twice.
- On the PHP side, `Engine` is the only door, and the domain does not know how the engine writes things: an action is an `Action` holding a `Bid` or a `Card`, and its JSON shape belongs to `Infrastructure/Bridge/Mapping`. A robot's bid carries its reason, which names its cards; what the table may hear is `$action->withoutReason()`. The full state stays an opaque `GameState` that only goes back to the engine; what may be sent to a player is a `SeatView`. A recorded deal sent by a client is checked for its shape and stripped to its known fields by `DealRecord::fromArray()` before the engine judges it.
- The analysis calls replay the record first and refuse what `/deals/replay` refuses. They take no seed: their draws come from what is analysed (the seat's view for the expert's choice, the record for a what-if), so the same question about the same deal always gets the same answer, whatever the order a client wrote the fields in. The expert's choice is worked out from the seat's view alone: two deals that look the same from the seat get the same answer, whatever the hidden hands.
- `bridge/contract/cases.json` is recorded from the bridge (`npm run contract:record`) and checked against it by its tests; the PHP tests then make the same calls through `BridgeEngine`, against the running bridge and against a transport that replays the recording (`tests/Support`).

## The server

`server/src` is cut by bounded context first, then by layer. A test (`tests/Architecture/LayersTest.php`) reads the imports and fails when the rule below is broken.

| Context     | What it is about                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Game`      | The table. Its domain holds the words of the game (seat, suit, card, action, seed, game state, seat view, recorded deal) and one port, `Engine`. The rules themselves stay in TypeScript: the adapter `Infrastructure/Bridge` asks them through the bridge. A second port, `DealAnalysis`, looks at a recorded deal again; the application layer holds one class per capability (`SummariseDeal`, `ReviewTrick`, `AskExpertChoice`, `TryWhatIf`, `RankSwings`), shared by every way in. |
| `Assistant` | The AI features and whether they can be used. Its domain says what a feature is and which state it is in, from `AiSettings`; the application layer answers `GetCapabilities`; the HTTP controllers and the start-up check `kabbout:check` are its interface. The coach lives here: `AskCoach`, the guard that checks its answers (`Domain/Grounding`), and the Symfony AI bundle's agent behind the `CoachModel` port (`Infrastructure/Ai`).                                            |
| `Api`       | What every answer under `/api` has in common. Today one thing: errors are JSON, `{ "error": { "code", "message" } }`, the shape the bridge uses, and never an HTML page or the text of an exception.                                                                                                                                                                                                                                                                                    |

| Layer            | May know                                         | Holds                                                                                   |
| ---------------- | ------------------------------------------------ | --------------------------------------------------------------------------------------- |
| `Domain`         | its own context's domain, and nothing of Symfony | value objects that cannot exist in a wrong state, enums, ports (interfaces), exceptions |
| `Application`    | domains and applications                         | one class per question or action a client has                                           |
| `Infrastructure` | everything in its context                        | adapters of the ports: the bridge, the environment                                      |
| `UserInterface`  | everything in its context                        | HTTP controllers and console commands, thin                                             |

Left out on purpose until something needs them: command and query buses, repositories, domain events, a shared kernel.

The server runs on FrankenPHP in classic mode (a fresh PHP for each request, one process for PHP and the game's files). Only `server/docker/app.Dockerfile` and `server/docker/Caddyfile` know it.

## The coach

After a deal, the end-of-deal screen offers "Ask the coach": the player asks "what did I do wrong?", or anything about the deal, in French, English or Tunisian Arabic, and reads a short answer that names the trick it speaks of, with a button that shows that trick. The robots already play without any AI; the coach does what they cannot do, which is to explain.

**The engine computes, the model talks.** The page sends the record of the deal (count, dealer, auction, eight tricks), never a hand. The server has the engine play it again and refuses a deal the rules do not allow (`422`, with the trick at fault) before any model is asked. The model is an agent of the Symfony AI bundle (`symfony/ai-bundle` 1.0.0-BETA1, Anthropic platform), declared in `server/config/packages/ai.yaml` with its platform, its model, its limit of eight rounds of tool calls and its six tools (`#[AsTool]` on `Assistant/Infrastructure/Ai/CoachTools.php`), each one a capability of the game returned as it is:

| Tool             | What it returns                                                                                                                                                     |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `deal_summary`   | The auction, the contract, whether it was made, what each team made.                                                                                                |
| `trick`          | One trick: the four cards, who led, who won, its points.                                                                                                            |
| `expert_choice`  | What the expert robot would have played at the player's turn, knowing only what the player could know, with its averages and the table as the player could read it. |
| `what_if`        | How the deal ends from another card, next to how it ends from the card played, both finished by the same four experts.                                              |
| `expert_bids`    | The auction at each of the player's turns: the bids heard, what the player said, what the expert robot says with the same cards, and why.                           |
| `biggest_swings` | The player's decisions the expert would have made otherwise, ranked by what they cost on average over the deals it imagines.                                        |

- **A reason, not only a verdict.** With its choice the engine returns the table as a player who counts the cards read it at that moment (`bridge/src/reading.ts`): the cards fallen and those still out, by suit; where the eight trumps are; the master card of each suit and whether the player held it; the seats that showed a void; who held the trick; the points made so far; and, for each legal card, whether it is a trump, a master, and whether it holds the trick. It is built from the seat's view only. The coach argues from it ("three trumps were still out, the ace among them: your ten was not master"), and the guard holds those counts and cards to the engine's like the rest.
- **The auction too, against the expert's bids.** "Should I have bid?" is answered turn by turn: what the expert robot would have said with the same cards, having heard the same bids, with its reasons (`/analysis/bids`). The expert bids by conventions, so the coach says what it "would have bid" and never puts points lost on a bid: no tool measures that.
- **A lean is not a mistake, and the expert is not the truth.** A swing is called a mistake only from 30 points of loss on average (`CLEAR_LOSS`, a judgment, not a measure of the noise); below that the coach says at most that the expert slightly prefers another card. For the two costliest mistakes the engine also finishes the deal as it was really dealt from both cards, and says which ended better there: when it is the card played, the coach says so at once. Its instructions say what the expert does not see, a partner's choice of lead and most of the auction, so that a player who reads those can be told they were right. This came from a real deal: the coach called a small trump a mistake where the player had correctly placed the jack from their partner's lead.
- **A bad decision is not bad luck.** A swing is the expert's average for its own card minus its average for the card played, from the player's view alone. A card the expert would also have played costs nothing by construction, even when it lost the trick, and is never called a mistake.
- **The guard.** Every number and every card of an answer must be in a tool result of the conversation (`GroundingGuard`: digits of Latin and Arabic script, ordinals in letters, card names in the three languages, codes and suit symbols). An answer that states anything else is sent back to the model once, with what it invented; a second invention and the player reads a plain summary built from `biggest_swings` and `deal_summary`, with no model. The same summary answers when the platform fails or the model called no tool. What the guard cannot see is said in its class, and is a limit this version accepts: a rank or a suit named alone, numbers in letters beyond the tricks' ordinals and the small cardinals, and what a number is said of (the numbers of the tool results are one set, so a true number given to the wrong side passes). Where it cannot tell, it reads too much rather than too little: a good answer sent back costs one more try, an invention let through costs the player's trust.
- **`POST /api/coach`** takes `{ record, seat, lang, question }` (a question is 1 to 300 characters) and answers `{ version, answer: { text, source, tricks, cards }, left }`, `left` being the questions still open on that deal, `source` being `model` or `summary`. `400` for a body that is not one, `422` for a deal the engine refuses, `429` past the limit per visitor (`KABBOUT_COACH_LIMIT` a minute, with `Retry-After`), past the questions of the deal (`KABBOUT_COACH_PER_DEAL`, code `deal_quota`) or past those of the day (`KABBOUT_COACH_DAILY`, code `daily_quota`), `503` when the AI is off. The page shows the text as text, never as markup.
- **What may be asked, and by whom.** The HTTP controller is only a door: `AnswerVisitor` (application layer) takes a `Visitor`, counts the question against three limits behind the `CoachAllowance` port (a visitor's pace, the day for everybody, a visitor's questions on one deal), has the engine check the deal before anything is spent on it, and counts and remembers only what the model was paid for. Every count is changed under a lock (`symfony/lock`, a fixed number of lock files however many visitors), so questions sent at the same instant are counted one by one; checked on the running image, six at once on one deal: two answered, four refused. A visitor is an address today (a whole /64 for IPv6, the forwarded address behind a proxy named in `KABBOUT_TRUSTED_PROXIES`) and will be an account when there are accounts; the MCP server of a later step comes in through the same use case.
- **A question has its time.** The model's tools answer for 30 seconds and a question is worth 14 looks at the deal; past either they tell the model to answer with what it has. A rejected answer is tried again only if the first came within 20 seconds, and a call to the platform lasts 25 seconds at most. The page waits 70 seconds, longer than the server can take, so an answer that was paid for is read.
- **What the coach must not become.** Today the deal comes from the page, and the expert's choice needs only what one seat saw: asked in the middle of an online game with an invented rest of the deal, the coach would be advice on the next card. Nothing here plays online yet. When it does, the coach answers only about a finished deal the server itself holds, named by its identifier, never about a record a client sends.
- **A short conversation.** A visitor may ask about one deal `KABBOUT_COACH_PER_DEAL` times. The server keeps what was said about the deal for an hour (`CoachMemory`, in the coach's own cache pool, cleared of what has expired as questions come) and gives it to the model with the next question, so "and if I had kept my trump?" is read after the first answer. The page does not send the conversation back: an earlier answer shown to the model is one the coach really gave. The numbers of a new answer come from tools called again, and the guard holds it to them.
- **Without a key** the end-of-deal screen says in one line how to wake the coach; played from plain files, it says nothing.

**Measured, not claimed.** `eval/coach/` holds fifteen deals played by the expert robots, each with one card planted that the expert likes least; a deal is in the set only when the engine's analysis puts that card first among the swings, well ahead of the next. `npm run eval:coach` asks the coach "what did I do wrong?" about each, five in each language, and counts the answers written by the model that name the trick and the card. A plain summary always names them, so it is counted apart, as an answer the model did not give. The measure covers a first question about a deal with one large planted mistake: not a second question, not the auction, not a deal with only small differences, and not whether the reason given is sound.

| Date       | Model                                                                                   | Named the expected mistake     | Answers written by the model | Time for the 15 |
| ---------- | --------------------------------------------------------------------------------------- | ------------------------------ | ---------------------------- | --------------- |
| 2026-11-16 | `claude-haiku-5-5`                                                                      | 15/15 (fr 5/5, en 5/5, ar 5/5) | 15/15                        | 79 s            |
| 2026-11-16 | `claude-sonnet-5-5`                                                                     | 15/15 (fr 5/5, en 5/5, ar 5/5) | 15/15                        | 76 s            |
| 2026-11-16 | `claude-opus-5-5`                                                                       | 15/15 (fr 5/5, en 5/5, ar 5/5) | 15/15                        | 137 s           |
| 2026-11-16 | `claude-haiku-5-5`, arguing from the table                                              | 15/15 (fr 5/5, en 5/5, ar 5/5) | 15/15                        | not timed       |
| 2026-11-16 | `claude-sonnet-5-5`, arguing from the table                                             | 15/15 (fr 5/5, en 5/5, ar 5/5) | 15/15                        | 104 s           |
| 2026-11-16 | `claude-sonnet-5-5`, with the auction tool                                              | 15/15 (fr 5/5, en 5/5, ar 5/5) | 15/15                        | 118 s           |
| 2026-11-16 | `claude-sonnet-5-5`, with clear and slight swings                                       | 15/15 (fr 5/5, en 5/5, ar 5/5) | 15/15                        | 122 s           |
| 2026-11-16 | `claude-sonnet-5-5`, hands sorted at the replay, a stricter reading of cards and tricks | 15/15 (fr 5/5, en 5/5, ar 5/5) | 15/15                        | 120 s           |
| 2026-11-16 | `claude-sonnet-5-5`, with the limits on time and on looks at the deal                   | 15/15 (fr 5/5, en 5/5, ar 5/5) | 15/15                        | 121 s           |

The rows "arguing from the table" are the coach, asked to give the reason from the table reading. The count is the same for both; the default is Sonnet for what the count does not measure. Read side by side, Sonnet's reasons follow from the reading and end on a lesson to keep; Haiku's were sometimes turned around (the expert's card argued as the one to keep), said who held a card nobody could know, slipped into markdown, and its Tunisian Arabic lost its thread on the longer answers. That is a reading of fifteen answers each by the author, not a measure, and nothing checks that a reason is sound: the guard holds the cards and the numbers, not the logic.

One run each, and a model does not answer twice the same way: read these as "all three find the planted mistake", not as a ranking. What the measure did find is in the first runs, before two corrections. With card codes left for the model to translate, the answers in Tunisian Arabic named the wrong suit ("9 كور" for the 9 of clubs) and the guard sent them back: 12/15 for Haiku and 11/15 for Sonnet, 2/5 and 1/5 in Arabic. Each tool result now carries the name of its cards in the player's language. And the guard read a rank cited alone ("your 10", "the 7, 8 and 9 of clubs") as an invented number, and missed a card joined to an Arabic particle ("و9 سباتة"); it now reads both, and holds a trick the answer points at to the tricks a tool looked at. The measure counts whether the mistake is named, not how well it is explained: the larger models illustrate more (a what-if, the next best card).

## Commands

Node 22.18+ (TypeScript run natively, no compilation).

```bash
npm install                 # development tools
npm run serve               # builds app.js and serves the game on http://127.0.0.1:4173
npm test                    # engine (FFB rules, contract, non-regression on 33 games replayed card for card), bridge, game session, texts
npm run test:e2e            # functional browser tests (installed Chrome locally, Chromium in CI)
E2E_BASE_URL=http://localhost:8080 npx playwright test --project=desktop  # the same tests against `docker compose up`
npm run test:coverage       # unit and browser tests together; fails under 80% of lines or branches run (report in coverage/)
npm run lint                # ESLint: shadowed names, unused code, functions over 60 lines
npm run check               # everything: types, format, lint, conformity, unit, browser, coverage
npm run bridge              # starts the bridge on http://127.0.0.1:8787 (BRIDGE_HOST, BRIDGE_PORT)
npm run check:php           # PHP side: coding standard, PHPStan at its strictest, PHPUnit against a running bridge; fails under 80% of lines run (PHP 8.5 with pcov, or Docker)
npm run contract:record     # after an intended change of the bridge: records bridge/contract/cases.json again
npm run eval:coach          # asks the coach about the 15 deals of eval/coach/ and prints how often it names the expected mistake (needs ANTHROPIC_API_KEY in .env; calls the model)
npm run eval:coach:build    # after an intended change of the search or the analysis: builds eval/coach/ again
npm run bench:robots        # measures the robots' levels against each other (add `-- --scoring tunisian` for the other count)
npm run test:golden:update  # after an intended change of rules or robots: records the reference games again
node tools/brand.mjs        # after a change of the emblem: draws the icons, the card back and the share image again
```

URL parameters for tests: `?seed=42` makes the deal and the robots' choices reproducible, `?fast` makes the robots instant, `?lang=en` sets the language.

## Continuous integration

`.github/workflows/ci.yml`, on every push to `main` or `develop` and every pull request:

1. **Quality**: type checking, format (Prettier), lint (ESLint), conformity (expected cards, CC0 and font credits, nothing loaded from another site, a Content-Security-Policy and no inline script or style, no `eval`, no third-party brand name, no secret in the repository), `npm audit` at moderate level.
2. **Unit and non-regression tests**: rules, scoring, bidding, robots, 160 simulated games, 33 reference games replayed card for card (both counts, the three robot levels), the three dictionaries, settings and storage, the screen's questions about a game.
3. **PHP side**: coding standard (php-cs-fixer, Symfony rules), static analysis (PHPStan at its strictest), `composer audit`, and the PHPUnit tests with the bridge running; fails under 80% of the lines of `server/src` run.
4. **Browser tests and coverage** (after the first two): Chromium, desktop and one phone (375 wide) for every test, four more phone formats for the layout (portrait 360 and 320 wide, landscape 812 and 667), and WebKit as an iPhone 13 (Safari's engine) for the layout, the sounds and whole games; fails under 80% of lines or branches run by unit and browser tests together; coverage report attached, Playwright report and traces on failure.
5. **One command** (after the first two): builds the two images, `docker compose up --wait`, asks `/api/capabilities`, then runs the browser tests on the desktop project against the game as Symfony serves it.

## Branches

Git flow. `main` only holds released versions, each one tagged; `develop` is where finished work meets. A piece of work lives on `feature/<nn>-<name>`, cut from `develop` and merged back with a merge commit once `npm run check` and `npm run check:php` are green. A version is prepared on `release/<x.y.z>`, then merged into `main` (tagged `v<x.y.z>`) and back into `develop`; an urgent fix to a released version goes through `hotfix/<x.y.z>` the same way.

## Roadmap

1. ~~Extract the engine with tests~~
2. ~~Plug `app/web` into `engine/` and remove the copy of the rules~~
3. ~~French, English and Tunisian Arabic, the Tunisian count, and a name: Kabbout~~
4. Mobile app with Capacitor (solo, offline) + AdMob advertising, GDPR / ATT consent
5. Online server (private tables by code, replacement robots, reconnection)

## Licence

The code is under the [MIT licence](LICENSE). Cards and sounds are in the public domain (CC0); the fonts are under the SIL Open Font License, whose text travels with them in `app/web/fonts/OFL.txt`. Details in [CREDITS.md](CREDITS.md).
