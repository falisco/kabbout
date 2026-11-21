# What building Kabbout turned up in Symfony AI and the PHP MCP SDK

Symfony AI 1.0 and the PHP MCP SDK were in pre-release while this was built. This is what we met that was worth telling upstream, with the versions in use, what we did about it here, and where it stands. Two were reported with a fix; four were already known upstream or have been fixed since.

Versions: `symfony/ai-bundle`, `symfony/ai-agent`, `symfony/ai-platform`, `symfony/ai-anthropic-platform` and `symfony/mcp-bundle` v1.0.0-BETA1, `mcp/sdk` v0.9.0, PHP 8.5, Symfony 8.1.

## Reported, with a fix

### An answer cut while the model was thinking said nothing of the cut

- Where: `symfony/ai-anthropic-platform`.
- What happened: `Claude::__construct()` sets 1000 output tokens when none is asked for. The current Claude models think before they answer, and thinking counts in that budget. In the coach's first evaluation run, about one answer in fifteen came back with a thinking block and no text, and the agent failed on `asText()` with `Unexpected response type: expected "TextResult", got "ThinkingResult"`, which says nothing of the limit.
- Here: `max_tokens: 4096` on both agents, in `server/config/packages/ai.yaml`. Since then, 45 answers out of 45 came back as text.
- Upstream: [symfony/ai#2988](https://github.com/symfony/ai/issues/2988), and the fix proposed in [symfony/ai#2989](https://github.com/symfony/ai/pull/2989): a `MaxOutputTokensException` when the limit cuts a response before any text, as the streamed path already throws. Both open.

### A model's mistake that the model may never be shown

- Where: `mcp/sdk`, `CallToolHandler`.
- What happened: an argument that a tool's input schema refuses (a card written `jh` where the pattern wants `JH`) is answered with the protocol error `-32602`, which a client may or may not hand to its model. A card that exists but is not legal comes back from our own code as a result marked `isError`, which the model reads and corrects. Since revision 2025-11-25 the specification lists input validation among those tool execution errors (SEP-1303).
- Here: the patterns stay in the schemas, and everything the game itself refuses is a result the model can read, with the legal moves.
- Upstream: [php-sdk#763](https://github.com/modelcontextprotocol/php-sdk/issues/763), and the change sent as [php-sdk#764](https://github.com/modelcontextprotocol/php-sdk/pull/764): a tool result marked `isError` from that revision on, `-32602` kept for the earlier ones. Both open.

## Met here, already known or fixed upstream

### A platform cannot be chosen from one variable

- Where: `symfony/ai-bundle`.
- What happened: we wanted one environment variable to switch the AI on, as `DATABASE_URL` does for a database. The platform is the name of a configuration node, fixed when the container is built: a variable can give the key at run time, not the platform.
- Here: one platform (Anthropic), `ANTHROPIC_API_KEY` for the key and `KABBOUT_AI_MODEL` for the model.
- Upstream: [symfony/ai#402](https://github.com/symfony/ai/issues/402), a platform DSN, open.

### An MCP tool's answer was sent pretty-printed

- Where: `mcp/sdk` v0.9.0.
- What happened: a tool returning an array was answered with `structuredContent` and with the same value as a text block, indented. Our `new_game` answer measured 2,009 bytes compact and 5,820 in the text block, on every call of a forty-call deal.
- Here: every tool goes through one class (`server/src/Game/UserInterface/Mcp/Door.php`), which returns the result itself with the text on one line.
- Upstream: fixed by [php-sdk#647](https://github.com/modelcontextprotocol/php-sdk/pull/647), in v0.10.0.

### Nothing said how an MCP tool learns who is calling

- Where: `symfony/mcp-bundle` documentation.
- What happened: a table of the game belongs to a player, so each tool must know who is asking. The session API invites keeping the player in the MCP session, and in the 2026-07-28 revision a session does not outlive a request: nothing fails, the tool just meets a stranger each time.
- Here: over HTTP, a stateless Symfony Security firewall on the endpoint authenticates each request; over stdio, the process enters as one guest (`server/config/packages/security.yaml`, `Door.php`).
- Upstream: the bundle's documentation on `main` now has a section on access control with a firewall.

### Structured output beside a thinking block

- Where: `symfony/ai-platform` with the Anthropic bridge.
- What happened: an agent with no tool, asked with `response_format`, whose answer comes as a thinking block then text: the object is read (`SymfonyAiConventionsModelTest::testAnAnswerBesideThinkingIsReadAllTheSame`). What the issue below says is then skipped is not relied on here: the domain checks the card whatever the platform did.
- Upstream: [symfony/ai#2945](https://github.com/symfony/ai/issues/2945), open; this is its case B seen from a second application.
