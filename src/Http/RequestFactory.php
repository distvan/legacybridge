<?php

declare(strict_types=1);

namespace LegacyBridge\Http;

use Psr\Http\Message\ServerRequestInterface;

final class RequestFactory
{
    /**
     * Create a PSR-7 ServerRequest from PHP superglobals
     *
     * @return ServerRequestInterface
     */
    public function fromGlobals(): ServerRequestInterface
    {
        //$_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
    }
}
