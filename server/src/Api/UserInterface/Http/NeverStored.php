<?php

declare(strict_types=1);

namespace Kabbout\Api\UserInterface\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Nothing the API says is kept by a browser or a proxy: an answer is about one visitor at one moment.
 * This server only answers under /api, so the rule holds for every response, and no door repeats it.
 */
final readonly class NeverStored
{
    #[AsEventListener]
    public function __invoke(ResponseEvent $event): void
    {
        $event->getResponse()->headers->set('Cache-Control', 'no-store');
    }
}
