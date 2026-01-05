<?php

declare(strict_types=1);

namespace LegacyBridge\Container;

use Psr\Container\NotFoundExceptionInterface;

final class LegacyContainer implements Container
{
    public function set(string $id, callable $factory): void
    {
        //store factory
    }

    public function get(string $id)
    {
        //resolve
    }

    public function has(string $id): bool
    {
        //check instance
    }
}
