<?php

declare(strict_types=1);

namespace LegacyBridge\Container;

use Psr\Container\ContainerInterface;

interface Container extends ContainerInterface
{
    /**
     * Register a service
     *
     * @param string $id
     * @param callable $factory
     * @return void
     */
    public function set(string $id, callable $factory): void;
}
