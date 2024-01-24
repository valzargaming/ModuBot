<?php

/*
 * This file is a part of the ModuBot project.
 *
 * Copyright (c) 2023-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace ModuBot\Interfaces;

use Discord\Helpers\Collection;
use Discord\Parts\User\Member;
use React\Promise\PromiseInterface;
use \ArrayIterator;
use \Traversable;

interface HandlerInterface
{
    public function get(): array;
    public function set(array $handlers): self;
    public function pull(int|string $index, ?callable $default = null): array;
    public function fill(array $commands, array $handlers): self;
    public function pushHandler(callable $callback, int|string|null $command = null): self;
    public function count(): int;
    public function first(): array;
    public function last(): array;
    public function isset(int|string $offset): bool;
    public function has(array ...$indexes): bool;
    public function filter(callable $callback): self;
    public function find(callable $callback): array;
    public function clear(): self;
    public function map(callable $callback): self;
    public function merge(object $handler): self;
    public function toArray(): array;
    public function offsetExists(int|string $offset): bool;
    public function offsetGet(int|string $offset): array;
    public function offsetSet(int|string $offset, callable $callback): self;
    public function getIterator(): Traversable;
    public function __debugInfo(): array;

    public function checkRank(?Member $member, array $allowed_ranks = []): bool;
}

namespace ModuBot;

use ModuBot\Interfaces\HandlerInterface;
use Discord\Helpers\Collection;
use Discord\Parts\User\Member;
use \ArrayIterator;
use \Traversable;

class Handler implements HandlerInterface
{
    protected ModuBot $modubot;
    protected array $handlers = [];
    
    public function __construct(ModuBot &$modubot, array $handlers = [])
    {
        $this->modubot = $modubot;
        $this->handlers = $handlers;
    }
    
    public function get(): array
    {
        return [$this->handlers];
    }
    
    public function set(array $handlers): self
    {
        $this->handlers = $handlers;
        return $this;
    }

    public function pull(int|string $index, ?callable $default = null): array
    {
        if (isset($this->handlers[$index])) {
            $default = $this->handlers[$index];
            unset($this->handlers[$index]);
        }

        return [$default];
    }

    public function fill(array $commands, array $handlers): self
    {
        if (count($commands) !== count($handlers)) {
            throw new \Exception('Commands and Handlers must be the same length.');
            return $this;
        }
        foreach ($handlers as $handler) $this->pushHandler($handler);
        return $this;
    }

    public function pushHandler(callable $callback, int|string|null $command = null): self
    {
        if ($command) $this->handlers[$command] = $callback;
        else $this->handlers[] = $callback;
        return $this;
    }

    public function count(): int
    {
        return count($this->handlers);
    }

    public function first(): array
    {
        return [array_shift(array_shift($this->toArray()) ?? [])];
    }
    
    public function last(): array
    {
        return [array_pop(array_shift($this->toArray()) ?? [])];
    }

    public function isset(int|string $offset): bool
    {
        return $this->offsetExists($offset);
    }
    
    public function has(array ...$indexes): bool
    {
        foreach ($indexes as $index)
            if (! isset($this->handlers[$index]))
                return false;
        return true;
    }
    
    public function filter(callable $callback): static
    {
        $static = new static($this->modubot, []);
        foreach ($this->handlers as $command => $handler)
            if ($callback($handler))
                $static->pushHandler($handler, $command);
        return $static;
    }
    
    public function find(callable $callback): array
    {
        foreach ($this->handlers as $handler)
            if ($callback($handler))
                return [$handler];
        return [];
    }

    public function clear(): self
    {
        $this->handlers = [];
        return $this;
    }

    public function map(callable $callback): static
    {
        return new static($this->modubot, array_combine(array_keys($this->handlers), array_map($callback, array_values($this->handlers))));
    }
    
    /**
     * @throws Exception if toArray property does not exist
     */
    public function merge(object $handler): self
    {
        if (! property_exists($handler, 'toArray')) {
            throw new \Exception('Handler::merge() expects parameter 1 to be an object with a method named "toArray", ' . gettype($handler) . ' given');
            return $this;
        }
        $toArray = $handler->toArray();
        $this->handlers = array_merge($this->handlers, array_shift($toArray));
        return $this;
    }
    
    public function toArray(): array
    {
        return [$this->handlers];
    }
    
    public function offsetExists(int|string $offset): bool
    {
        return isset($this->handlers[$offset]);
    }

    public function offsetGet(int|string $offset): array
    {
        return [$this->handlers[$offset] ?? null];
    }
    
    public function offsetSet(int|string $offset, callable $callback): self
    {
        $this->handlers[$offset] = $callback;
        return $this;
    }

    public function setOffset(int|string $newOffset, callable $callback): self
    {
        if ($offset = $this->getOffset($callback) === false) $offset = $newOffset;
        unset($this->handlers[$offset]);
        $this->handlers[$newOffset] = $callback;
        return $this;
    }
    
    public function getOffset(callable $callback): int|string|false
    {
        return array_search($callback, $this->handlers);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->handlers);
    }

    public function __debugInfo(): array
    {
        return ['handlers' => array_keys($this->handlers)];
    }

    public function checkRank(?Member $member, array $allowed_ranks = []): bool
    {
        if (empty($allowed_ranks)) return true;
        if (! $member) return false;
        $resolved_ranks = [];
        if (! isset($this->modubot->guilds[$member->guild_id])) {
            $this->modubot->logger->warning("Guild `{$member->guild_id}` not configuration in guilds");
            return false;
        }
        if (! isset($this->modubot->guilds[$member->guild_id]['roles'])) {
            $this->modubot->logger->warning("Guild `{$member->guild_id}` does not have roles configured");
            return false;
        }
        foreach ($allowed_ranks as $rank) {
            if (! isset($this->modubot->guilds[$member->guild_id]['roles'][$rank])) {
                $this->modubot->logger->warning("Guild `{$member->guild_id}` does not have role `{$rank}` configured");
                continue;
            }
            $resolved_ranks[] = $this->modubot->guilds[$member->guild_id]['roles'][$rank];
        }
        if (! empty(array_intersect($resolved_ranks, array_map('strval', array_keys($member->roles->toArray()))))) return true;
        //else $this->modubot->logger->debug("Member `{$member->id}` does not have any of the required roles: " . implode(', ', $allowed_ranks));
        return false;
    }
}