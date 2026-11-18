<?php

declare(strict_types=1);

namespace Kabbout\Tests\Identity\UserInterface;

use Kabbout\Identity\Application\EnterAsGuest;
use Kabbout\Identity\Domain\Chance;
use Kabbout\Identity\Domain\Players;
use Kabbout\Identity\Infrastructure\CachePlayers;
use Kabbout\Identity\Infrastructure\Security\GuestCookie;
use Kabbout\Tests\Identity\Domain\ListedPlayers;
use Kabbout\Tests\Identity\Domain\ScriptedChance;
use Kabbout\Tests\Identity\Domain\Secrets;
use Kabbout\Tests\Support\ListedLog;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/** The two routes of /api/session, through Symfony Security: who comes in, who is recognised, and what never leaves the server. */
final class IdentityEndpointTest extends WebTestCase
{
    private const string NAME = '/^C[0-9]{7}$/D';

    public function testWhoeverComesInIsGivenANameAndTheirSecretInACookieThePageCannotRead(): void
    {
        $client = self::client();

        $answer = self::ask($client, 'POST');

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertSame(['version', 'player'], array_keys($answer));
        self::assertSame(1, $answer['version']);
        self::assertIsArray($answer['player']);
        self::assertSame(['name'], array_keys($answer['player']));
        self::assertIsString($answer['player']['name']);
        self::assertMatchesRegularExpression(self::NAME, $answer['player']['name']);

        $cookies = $client->getResponse()->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame(GuestCookie::NAME, $cookies[0]->getName());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', (string) $cookies[0]->getValue());
        self::assertSame([true, 'strict', '/api'], [$cookies[0]->isHttpOnly(), $cookies[0]->getSameSite(), $cookies[0]->getPath()]);
        // over plain HTTP, as the game is served on a player's own machine, the cookie must not ask for HTTPS
        self::assertFalse($cookies[0]->isSecure());
    }

    public function testOverHttpsTheCookieOnlyTravelsOverHttps(): void
    {
        $client = self::client();

        self::ask($client, 'POST', ['HTTPS' => 'on']);

        self::assertTrue($client->getResponse()->headers->getCookies()[0]->isSecure());
    }

    public function testTheSameCookieIsTheSamePlayerEveryTimeAndComingInTwiceMakesNoSecondGuest(): void
    {
        $client = self::client();
        $entered = self::ask($client, 'POST');
        $secret = self::secretOf($client);

        $first = self::ask($client, 'GET');
        $second = self::ask($client, 'GET');
        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        $again = self::ask($client, 'POST');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([$entered, $entered, $entered], [$first, $second, $again]);
        // the secret is sent once: who is already a player gets no cookie again
        self::assertSame([], $client->getResponse()->headers->getCookies());
        self::assertSame($secret, self::secretOf($client));
    }

    public function testWithoutACookieNobodyIsAskingEvenRightAfterSomebodyWas(): void
    {
        $client = self::client();
        self::assertSame(['version' => 1, 'player' => null], self::ask($client, 'GET'));
        self::assertResponseStatusCodeSame(200);

        self::ask($client, 'POST');
        self::assertNotNull(self::ask($client, 'GET')['player']);
        // the same server, the next request, from somebody else: who was recognised a moment ago does not stay
        $client->getCookieJar()->clear();

        self::assertSame(['version' => 1, 'player' => null], self::ask($client, 'GET'));
    }

    public function testTwoVisitorsAreTwoPlayersUnderTwoNames(): void
    {
        $client = self::client();
        $ana = self::ask($client, 'POST');
        $hers = self::secretOf($client);
        $client->getCookieJar()->clear();
        $ben = self::ask($client, 'POST');

        self::assertNotSame($ana, $ben);
        self::assertNotSame($hers, self::secretOf($client));
        self::holding($client, $hers);
        self::assertSame($ana, self::ask($client, 'GET'));
    }

    public function testAnUnknownAnAlteredAndAnExpiredSecretAreNobodyAndAreAnsweredAlike(): void
    {
        $client = self::client();
        $clock = new MockClock();
        self::getContainer()->set(Players::class, new CachePlayers(new ArrayAdapter(clock: $clock), new LockFactory(new InMemoryStore()), $clock));
        self::ask($client, 'POST');
        $secret = self::secretOf($client);
        $nobody = self::answerTo($client, null);

        $unknown = self::answerTo($client, str_repeat('ab', 32));
        $altered = self::answerTo($client, Secrets::altered($secret));
        $noSecret = self::answerTo($client, 'not a secret');
        // 30 days is how long a guest nobody sees is kept, give or take the day their last visit was written
        $clock->modify('+32 days');
        $expired = self::answerTo($client, $secret);

        self::assertSame([200, '{"version":1,"player":null}'], [$nobody[0], $nobody[1]]);
        self::assertSame([$nobody, $nobody, $nobody, $nobody], [$unknown, $altered, $noSecret, $expired]);
    }

    public function testASecretThatIsNobodysDoesNotKeepAVisitorOut(): void
    {
        $client = self::client();
        // what a browser still holds of a guest the server has forgotten
        self::holding($client, str_repeat('ab', 32));

        $answer = self::ask($client, 'POST');

        self::assertResponseStatusCodeSame(201);
        self::assertNotSame(str_repeat('ab', 32), self::secretOf($client));
        self::assertSame($answer, self::ask($client, 'GET'));
    }

    public function testTheSecretIsInNoAnswerAndInNoLineOfTheLog(): void
    {
        $client = self::client();
        $log = self::getContainer()->get('logger');
        self::assertInstanceOf(ListedLog::class, $log);

        $said = [(string) json_encode(self::ask($client, 'POST'))];
        $secret = self::secretOf($client);
        $said[] = (string) json_encode(self::ask($client, 'GET'));
        $said[] = (string) json_encode(self::ask($client, 'POST'));
        // a cookie turned away, one digit from the real one: what is written of it would show the secret too
        self::holding($client, Secrets::altered($secret));
        $said[] = (string) json_encode(self::ask($client, 'GET'));
        $said[] = (string) json_encode(self::ask($client, 'POST'));

        $written = implode("\n", [...$said, ...$log->lines]);
        self::assertStringNotContainsString($secret, $written);
        // nor a piece of it: its start, as a trace cuts a text, or its middle
        self::assertStringNotContainsString(substr($secret, 0, 12), $written);
        self::assertStringNotContainsString(substr($secret, 20, 20), $written);
        // the log was listening while all of this happened
        self::assertNotSame([], $log->lines);
    }

    public function testWhenThePlayersCannotBeReadWhoeverAsksIsNobodyTheOtherRoutesStillAnswerAndTheLogSaysWhy(): void
    {
        $client = self::client();
        $players = new ListedPlayers();
        self::getContainer()->set(Players::class, $players);
        $log = self::getContainer()->get('logger');
        self::assertInstanceOf(ListedLog::class, $log);
        self::ask($client, 'POST');
        $players->unavailable = true;

        self::assertSame(['version' => 1, 'player' => null], self::ask($client, 'GET'));
        self::assertResponseStatusCodeSame(200);
        $client->request('GET', '/api/capabilities');
        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('[error] The players cannot be read', implode("\n", $log->lines));

        // and nobody comes in: no name is given that would not be kept
        $answer = self::ask($client, 'POST');
        self::assertResponseStatusCodeSame(503);
        self::assertSame(['error' => ['code' => 'players_unavailable', 'message' => 'Nobody can come in right now; try again.']], $answer);
        self::assertSame([], $client->getResponse()->headers->getCookies());
    }

    /** @return iterable<string, array{string|null, int}> */
    public static function whereARequestComesFrom(): iterable
    {
        yield 'the page itself' => ['same-origin', 201];
        yield 'the visitor, by hand' => ['none', 201];
        yield 'a client that is no browser' => [null, 201];
        yield 'another site' => ['cross-site', 403];
        yield 'another site of the same domain' => ['same-site', 403];
        yield 'anything else' => ['SAME-ORIGIN ', 403];
    }

    #[DataProvider('whereARequestComesFrom')]
    public function testOnlyTheGamesOwnPageMayComeInForItsVisitor(?string $from, int $status): void
    {
        $client = self::client();

        $answer = self::ask($client, 'POST', null === $from ? [] : ['HTTP_SEC_FETCH_SITE' => $from]);

        self::assertResponseStatusCodeSame($status);
        if (403 === $status) {
            self::assertSame(['error' => ['code' => 'cross_site', 'message' => 'Only the game’s own page may enter for its visitor.']], $answer);
            self::assertSame([], $client->getResponse()->headers->getCookies());
            self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        }
    }

    public function testWhenEveryNameDrawnIsTakenTheServerSaysSoAndNobodyComesIn(): void
    {
        $client = self::client();
        $players = new ListedPlayers();
        self::getContainer()->set(Players::class, $players);
        // one draw for the first guest, then every draw the second is allowed
        self::getContainer()->set(Chance::class, new ScriptedChance(array_fill(0, 1 + EnterAsGuest::DRAWS, 5)));
        self::ask($client, 'POST');
        $client->getCookieJar()->clear();

        $answer = self::ask($client, 'POST');

        self::assertResponseStatusCodeSame(503);
        self::assertSame(['error' => ['code' => 'no_name_free', 'message' => 'No name could be found for a new guest; try again.']], $answer);
        self::assertSame([], $client->getResponse()->headers->getCookies());
        self::assertCount(1, $players->known);
    }

    public function testTheSessionIsOnlyAskedByGetAndEnteredByPost(): void
    {
        $client = self::client();

        $answer = self::ask($client, 'DELETE');

        self::assertResponseStatusCodeSame(405);
        self::assertSame(['error' => ['code' => 'method_not_allowed', 'message' => 'Method Not Allowed']], $answer);
    }

    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        // one kernel for the whole test: what is put in the container stays
        $client->disableReboot();

        return $client;
    }

    /**
     * @param array<string, string> $server
     *
     * @return array<array-key, mixed>
     */
    private static function ask(KernelBrowser $client, string $method, array $server = []): array
    {
        $client->request($method, '/api/session', server: $server);
        $answer = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($answer);

        return $answer;
    }

    /** the secret the browser holds, as the server sent it */
    private static function secretOf(KernelBrowser $client): string
    {
        $cookie = $client->getCookieJar()->get(GuestCookie::NAME, '/api');
        self::assertNotNull($cookie);

        return $cookie->getValue();
    }

    private static function holding(KernelBrowser $client, string $presented): void
    {
        $client->getCookieJar()->clear();
        $client->getCookieJar()->set(new Cookie(GuestCookie::NAME, $presented, path: '/api'));
    }

    /** @return array{int, string, array<string, list<string|null>>} what GET /api/session answers to a cookie, or to none: status, body and headers, the date apart */
    private static function answerTo(KernelBrowser $client, ?string $presented): array
    {
        $client->getCookieJar()->clear();
        if (null !== $presented) {
            self::holding($client, $presented);
        }
        $client->request('GET', '/api/session');
        $response = $client->getResponse();
        $headers = $response->headers->all();
        unset($headers['date']);

        return [$response->getStatusCode(), (string) $response->getContent(), $headers];
    }
}
