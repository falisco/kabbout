<?php

declare(strict_types=1);

namespace Kabbout\Tests\Api\UserInterface;

use Kabbout\Api\UserInterface\Http\ErrorController;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/** No HTML comes out of the API, whatever goes wrong. */
final class ErrorsTest extends WebTestCase
{
    /** @return iterable<string, array{string, string, int, string, string}> */
    public static function mistakes(): iterable
    {
        yield 'an address nothing answers' => ['GET', '/api/nothing-here', 404, 'not_found', 'Not Found'];
        yield 'the API with no address' => ['GET', '/api', 404, 'not_found', 'Not Found'];
        yield 'a method the address does not take' => ['POST', '/api/capabilities', 405, 'method_not_allowed', 'Method Not Allowed'];
    }

    #[DataProvider('mistakes')]
    public function testAMistakeOfTheClientIsAnsweredInJson(string $method, string $path, int $status, string $code, string $message): void
    {
        $client = self::createClient();
        $client->request($method, $path);

        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertJsonStringEqualsJsonString(
            (string) json_encode(['error' => ['code' => $code, 'message' => $message]]),
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testARefusedMethodStillSaysWhichOnesAreTaken(): void
    {
        $client = self::createClient();
        $client->request('DELETE', '/api/capabilities');

        self::assertResponseHeaderSame('Allow', 'GET');
    }

    public function testAFailureOfTheServerSaysNothingOfWhatWentWrong(): void
    {
        $response = (new ErrorController())(new \RuntimeException('the password of the database is hunter2'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('{"error":{"code":"server_error","message":"Internal Server Error"}}', $response->getContent());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function testOtherStatusesKeepTheirHeadersAndGetACode(): void
    {
        $limited = (new ErrorController())(new TooManyRequestsHttpException(30));
        self::assertSame(429, $limited->getStatusCode());
        self::assertSame('30', $limited->headers->get('Retry-After'));
        self::assertStringContainsString('"code":"too_many_requests"', (string) $limited->getContent());

        $teapot = (new ErrorController())(new \Symfony\Component\HttpKernel\Exception\HttpException(418));
        self::assertStringContainsString('"code":"client_error"', (string) $teapot->getContent());
    }
}
