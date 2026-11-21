# The server: where to find what

`server/` is the Symfony application. `src/` is cut by bounded context first (`Game`, `Assistant`, `Identity`, `Api`), then by layer (`Domain`, `Application`, `Infrastructure`, `UserInterface`), then by feature. `tests/` follows the same contexts and layers, with `tests/Architecture/` and `tests/Support/` beside them.

The AI of this entry is in two places: the context `Assistant` holds what a model does (the coach, the conventions), and the MCP server is a door of `Game`, because an MCP client plays a seat like anyone else and no model runs on our side there.

## You are looking for…

| …this                                                    | Open                                                                                                                                                                                                                                  |
| -------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| The Symfony AI bundle's configuration: platform, agents  | `config/packages/ai.yaml`                                                                                                                                                                                                             |
| Everything that calls Symfony AI                         | `src/Assistant/Infrastructure/SymfonyAi/`: one folder for the coach, one for the conventions                                                                                                                                          |
| The coach's six tools (`#[AsTool]`)                      | `src/Assistant/Infrastructure/SymfonyAi/Coach/CoachTools.php`                                                                                                                                                                         |
| The coach's system prompt and its conversation           | `CoachPrompt.php` and `CoachConversation.php`, in the same folder                                                                                                                                                                     |
| What the coach does with a question, model or not        | `src/Assistant/Application/Coach/` (`AskCoach`, `AnswerVisitor`)                                                                                                                                                                      |
| The guard that finds an invented number, card or trick   | `src/Assistant/Domain/Grounding/` (`GroundingGuard`); what is done about it: `src/Assistant/Application/Coach/AskCoach.php`                                                                                                           |
| Structured output: the class the model's answer must fit | `src/Assistant/Infrastructure/SymfonyAi/Conventions/CardDraft.php`                                                                                                                                                                    |
| The card the domain validates and the engine bids by     | `src/Game/Domain/ConventionCard.php`                                                                                                                                                                                                  |
| Whether the AI is on, and which feature is in what state | `src/Assistant/Domain/Settings/`, answered by `src/Assistant/Application/Capabilities/`                                                                                                                                               |
| How many questions a visitor may ask                     | a minute and a day: `src/Assistant/Application/Allowance/`; a deal: `src/Assistant/Application/Coach/CoachAllowance.php`; counted by `src/Assistant/Infrastructure/Limits/`; the numbers: `KABBOUT_COACH_*` in `config/services.yaml` |
| The two evaluations                                      | `src/Assistant/UserInterface/Console/` (`kabbout:coach:eval`, `kabbout:conventions:eval`) and their sets in `../eval/`                                                                                                                |
| The MCP server's configuration                           | `config/packages/mcp.yaml`                                                                                                                                                                                                            |
| The MCP tools and resource                               | `src/Game/UserInterface/Mcp/` (`TableTools`, `ReviewTools`, `Rules`); who is asking: `Door.php`                                                                                                                                       |
| What an MCP tool or a tool of the coach calls            | `src/Game/Application/Table/` (playing a seat) and `src/Game/Application/Analysis/` (reviewing a deal, one class per capability)                                                                                                      |
| The rules of belote                                      | not here: they are the TypeScript engine (`../engine/`), asked through `src/Game/Infrastructure/Bridge/`                                                                                                                              |
| Guests, their secret, Symfony Security                   | `src/Identity/`                                                                                                                                                                                                                       |
| The shape of every answer and error under `/api`         | `src/Api/`                                                                                                                                                                                                                            |
| The rules of this layout, as tests                       | `tests/Architecture/` (`LayersTest`, `DoorsTest`)                                                                                                                                                                                     |
| Which adapter is behind which port                       | `config/services.yaml`, for the ports with a choice to make; a port with one adapter is autowired to it                                                                                                                               |

## One question, followed through the code

"What did I do wrong?" sent to `POST /api/coach`:

1. `src/Assistant/UserInterface/Http/CoachController.php` reads the request and names the visitor.
2. `src/Assistant/Application/Coach/AnswerVisitor.php` counts the question against the visitor's allowance, then `AskCoach.php` asks the port `CoachModel`.
3. `src/Assistant/Infrastructure/SymfonyAi/Coach/SymfonyAiCoachModel.php` calls the bundle's agent `coach`.
4. The agent calls methods of `CoachTools.php`; each one calls a capability of `src/Game/Application/Analysis/`, which asks the engine.
5. Back in `AskCoach.php`, the answer is read by `src/Assistant/Domain/Grounding/GroundingGuard.php`, which lists every number, card or trick that no tool returned. `AskCoach` then decides: the answer goes back to the model once, with what it invented, if the first one came quickly enough; otherwise, and when the answer is empty, too long or used no tool, the player gets the plain summary made from the engine's numbers.

## Known limit of this layout

`src/Game/Domain/` still has its 20 value objects and enums and its two ports in one folder, beside `Analysis/`, `Table/` and `Exception/`.
