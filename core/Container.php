<?php

namespace Core;

/**
 * Simple Service Container für Framework-Komponenten
 */
class Container
{
    private array $services = [];
    private array $singletons = [];

    public function bind(string $name, callable $resolver): void
    {
        $this->services[$name] = $resolver;
    }

    public function singleton(string $name, callable $resolver): void
    {
        $this->singletons[$name] = $resolver;
    }

    public function get(string $name)
    {
        if (isset($this->singletons[$name])) {
            static $instances = [];
            if (!isset($instances[$name])) {
                $instances[$name] = $this->singletons[$name]($this);
            }
            return $instances[$name];
        }

        if (isset($this->services[$name])) {
            return $this->services[$name]($this);
        }

        throw new \RuntimeException("Service not found: {$name}");
    }
}