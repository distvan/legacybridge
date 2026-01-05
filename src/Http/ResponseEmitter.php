<?php

declare(strict_types=1);

namespace LegacyBridge\Http;

use Psr\Http\Message\ResponseInterface;

final class ResponseEmitter
{
    public function emit(ResponseInterface $response): void
    {
        //Headers, status_code, body
    }
}
