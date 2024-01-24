<?php

/*
 * This file is a part of the ModuBot project.
 *
 * Copyright (c) 2023-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace ModuBot\Interfaces;

use Discord\Parts\Channel\Message;
use React\Promise\PromiseInterface;

interface MessageHandlerInterface extends HandlerInterface
{
    public function handle(Message $message): ?PromiseInterface;
}

interface MessageHandlerCallbackInterface
{
    public function __invoke(Message $message, array $message_filtered, string $command): ?PromiseInterface;
}

namespace ModuBot;

use ModuBot\Interfaces\MessageHandlerInterface;
use ModuBot\Interfaces\MessageHandlerCallbackInterface;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Guild;
use Discord\Parts\User\Member;
use Discord\Helpers\Collection;
use React\Promise\PromiseInterface;

class MessageHandlerCallback implements MessageHandlerCallbackInterface
{  
    private $callback;

    public function __construct(callable $callback)
    {
        $reflection = new \ReflectionFunction($callback);
        $parameters = $reflection->getParameters();

        $expectedParameterTypes = [Message::class, 'array', 'string'];

        if (count($parameters) !== $count = count($expectedParameterTypes)) {
            throw new \InvalidArgumentException("The callback must take exactly $count parameters: " . implode(', ', $expectedParameterTypes));
        }

        foreach ($parameters as $index => $parameter) {
            if (! $parameter->hasType()) {
                throw new \InvalidArgumentException("Parameter $index must have a type hint.");
            }

            $type = $parameter->getType();
            $name = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            if ($name !== $expectedParameterTypes[$index]) {
                throw new \InvalidArgumentException("Parameter $index must be of type {$expectedParameterTypes[$index]}.");
            }
        }

        $this->callback = $callback;
    }

    public function __invoke(Message $message, array $message_filtered = [], string $command = ''): ?PromiseInterface
    {
        return call_user_func($this->callback, $message, $message_filtered, $command);
    }
}

class MessageHandler extends Handler implements MessageHandlerInterface
{
    protected array $required_permissions;
    protected array $match_methods;
    protected array $descriptions;

    public function __construct(ModuBot &$modubot, array $handlers = [], array $required_permissions = [], array $match_methods = [], array $descriptions = [])
    {
        parent::__construct($modubot, $handlers);
        $this->required_permissions = $required_permissions;
        $this->match_methods = $match_methods;
        $this->descriptions = $descriptions;
    }

    public function get(): array
    {
        return [$this->handlers, $this->required_permissions, $this->match_methods, $this->descriptions];
    }

    public function set(array $handlers, array $required_permissions = [], array $match_methods = [], array $descriptions = []): self
    {
        parent::set($handlers);
        $this->required_permissions = $required_permissions;
        $this->match_methods = $match_methods;
        $this->descriptions = $descriptions;
        return $this;
    }

    public function pull(int|string $index, ?callable $defaultCallables = null, array $default_required_permissions = null, array $default_match_methods = null, array $default_descriptions = null): array
    {
        $return = [];
        $return[] = parent::pull($index, $defaultCallables);

        if (isset($this->required_permissions[$index])) {
            $default_required_permissions = $this->required_permissions[$index];
            unset($this->required_permissions[$index]);
        }
        $return[] = $default_required_permissions;

        if (isset($this->match_methods[$index])) {
            $default_match_methods = $this->match_methods[$index];
            unset($this->match_methods[$index]);
        }
        $return[] = $default_match_methods;

        if (isset($this->descriptions[$index])) {
            $default_descriptions = $this->descriptions[$index];
            unset($this->descriptions[$index]);
        }
        $return[] = $default_descriptions;

        return $return;
    }

    public function fill(array $commands, array $handlers, array $required_permissions = [], array $match_methods = [], array $descriptions = []): self
    {
        if (count($commands) !== count($handlers)) {
            throw new \Exception('Commands and Handlers must be the same length.');
            return $this;
        }
        foreach($commands as $command) {
            parent::pushHandler(array_shift($handlers), $command);
            $this->pushPermission(array_shift($required_permissions), $command);
            $this->pushMethod(array_shift($match_methods), $command);
            $this->pushDescription(array_shift($descriptions), $command);
        }
        return $this;
    }
    
    public function pushPermission(array $required_permissions, int|string|null $command = null): ?self
    {
        if ($command) $this->required_permissions[$command] = $required_permissions;
        else $this->required_permissions[] = $required_permissions;
        return $this;
    }

    public function pushMethod(string $method, int|string|null $command = null): ?self
    {
        if ($command) $this->match_methods[$command] = $method;
        else $this->match_methods[] = $method;
        return $this;
    }

    public function pushDescription(string $description, int|string|null $command = null): ?self
    {
        if ($command) $this->descriptions[$command] = $description;
        else $this->descriptions[] = $description;
        return $this;
    }

    public function first(): array
    {
        $toArray = $this->toArray();
        $return = [];
        $return[] = array_shift(array_shift($toArray) ?? []);
        $return[] = array_shift(array_shift($toArray) ?? []);
        $return[] = array_shift(array_shift($toArray) ?? []);
        return $return;
    }
    
    public function last(): array
    {
        $toArray = $this->toArray();
        $return = [];
        $return[] = array_pop(array_shift($toArray) ?? []);
        $return[] = array_pop(array_shift($toArray) ?? []);
        $return[] = array_pop(array_shift($toArray) ?? []);
        return $return;
    }

    public function find(callable $callback): array
    {
        foreach ($this->handlers as $index => $handler)
            if ($callback($handler))
                return [$handler, $this->required_permissions[$index] ?? [], $this->match_methods[$index] ?? 'str_starts_with', $this->descriptions[$index] ?? ''];
        return [];
    }

    public function clear(): self
    {
        parent::clear();
        $this->required_permissions = [];
        $this->match_methods = [];
        $this->descriptions = [];
        return $this;
    }
    
    // TODO: Review this method
    public function map(callable $callback): static
    {
        $arr = array_combine(array_keys($this->handlers), array_map($callback, array_values($this->toArray())));
        return new static($this->modubot, array_shift($arr) ?? [], array_shift($arr) ?? [], array_shift($arr) ?? [], array_shift($arr) ?? []);
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
        $this->handlers = array_merge($this->handlers, array_shift($toArray) ?? []);
        $this->required_permissions = array_merge($this->required_permissions, array_shift($toArray) ?? []);
        $this->match_methods = array_merge($this->match_methods, array_shift($toArray) ?? []);
        $this->descriptions = array_merge($this->descriptions, array_shift($toArray) ?? []);
        return $this;
    }

    public function toArray(): array
    {
        $toArray = parent::toArray();
        $toArray[] = $this->required_permissions ?? [];
        $toArray[] = $this->match_methods ?? [];
        $toArray[] = $this->descriptions ?? [];
        return $toArray;
    }

    public function offsetGet(int|string $offset): array
    {
        $return = parent::offsetGet($offset);
        $return[] = $this->required_permissions[$offset] ?? null;
        $return[] = $this->match_methods[$offset] ?? null;
        $return[] = $this->descriptions[$offset] ?? null;
        return $return;
    }
    
    public function offsetSet(int|string $offset, callable $callback, ?array $required_permissions = [], ?string $method = 'str_starts_with', ?string $description = ''): self
    {
        parent::offsetSet($offset, $callback);
        $this->required_permissions[$offset] = $required_permissions;
        $this->match_methods[$offset] = $method;
        $this->descriptions[$offset] = $description;
        return $this;
    }
    
    public function setOffset(int|string $newOffset, callable $callback, ?array $required_permissions = [], ?string $method = 'str_starts_with', ?string $description = ''): self
    {
        parent::setOffset($newOffset, $callback);
        if ($offset = $this->getOffset($callback) === false) $offset = $newOffset;
        unset($this->required_permissions[$offset]);
        unset($this->match_methods[$offset]);
        unset($this->descriptions[$offset]);
        $this->required_permissions[$newOffset] = $required_permissions;
        $this->match_methods[$newOffset] = $method;
        $this->descriptions[$newOffset] = $description;
        return $this;
    }

    public function __debugInfo(): array
    {
        return ['modubot' => isset($this->modubot) ? $this->modubot instanceof ModuBot : false, 'handlers' => array_keys($this->handlers)];
    }

    //Unique to MessageHandler
    
    public function handle(Message $message): ?PromiseInterface
    {
        // if (! $message->member) return $message->reply('Unable to get Discord Member class. Commands are only available in guilds.');
        $message_filtered = $this->modubot->filterMessage($message);
        foreach ($this->handlers as $command => $callback) {
            switch ($this->match_methods[$command]) {
                case 'exact':
                $method_func = function () use ($callback, $message_filtered, $command): ?callable
                {
                    if ($message_filtered['message_content_lower'] == $command)
                        return $callback; // This is where the magic happens
                    return null;
                };
                break;
                case 'str_contains':
                    $method_func = function () use ($callback, $message_filtered, $command): ?callable
                    {
                        if (str_contains($message_filtered['message_content_lower'], $command)) 
                            return $callback; // This is where the magic happens
                        return null;
                    };
                    break;
                case 'str_ends_with':
                    $method_func = function () use ($callback, $message_filtered, $command): ?callable
                    {
                        if (str_ends_with($message_filtered['message_content_lower'], $command)) 
                            return $callback; // This is where the magic happens
                        return null;
                    };
                    break;
                case 'str_starts_with':
                default:
                    $method_func = function () use ($callback, $message_filtered, $command): ?callable
                    {
                        if (str_starts_with($message_filtered['message_content_lower'], $command)) 
                            return $callback; // This is where the magic happens
                        return null;
                    };
            }
            if (! $message->member) return null;
            if ($callback = $method_func()) { // Command triggered
                $required_permissions = $this->required_permissions[$command] ?? [];
                if ($lowest_rank = array_pop($required_permissions)) {
                    if (! isset(reset($this->modubot->guilds)['roles'][$lowest_rank])) {
                        $this->modubot->logger->warning("Unable to find role ID for rank `$lowest_rank`");
                        throw new \Exception("Unable to find role ID for rank `$lowest_rank`");
                    } elseif (! $this->checkRank($message->member, $this->required_permissions[$command] ?? [])) return $this->modubot->reply($message, 'Rejected! You need to have at least the <@&' . reset($this->modubot->guilds)['roles'][$lowest_rank] . '> rank.');
                }
                return $callback($message, $message_filtered, $command);
            }
        }
        if (empty($this->handlers)) $this->modubot->logger->info('No message handlers found!');
        return null;
    }

    // Don't forget to use ->setAllowedMentions(['parse'=>[]]) on the MessageBuilder object to prevent all roles being pinged
    public function generateHelp(?Member $member = null, $complete = false): string
    { // TODO: Review this method for junk code
        $guild_id = $member ? $member->guild_id : key($this->modubot->guilds);
        if (! $complete && ! $guild = $this->modubot->discord->guilds->get('id', $guild_id)) return "Unable to get guild information for guild `$guild_id`";
        if ($guild_id && isset($this->modubot->guilds[$guild_id], $this->modubot->guilds[$guild_id]['roles'])) $ranks = $this->modubot->guilds[$guild_id]['roles'];
        else $ranks = array_keys(reset($this->modubot->guilds)['roles']); // Default to first guild
        $ranks[] = 'everyone';
        //var_dump($ranks);

        $array = [];
        foreach (array_keys($this->handlers) as $command) {
            //var_dump($command);
            $required_permissions = $this->required_permissions[$command] ?? [];
            $lowest_rank = array_pop($required_permissions) ?? 'everyone';
            $role = null;
            if (! is_numeric($lowest_rank) && $lowest_rank !== 'everyone')
                if ($complete || (! $member || $role = $guild->roles->get('name', $lowest_rank)))
                    $lowest_rank = $role ? $role->id : $lowest_rank;
            if ($complete || ! $member) $array[$lowest_rank][] = $command;
            elseif ($lowest_rank == 'everyone') {
                //$this->modubot->logger->debug("Lowest rank for `$command` is everyone");
                $array[$lowest_rank][] = $command;
            }
            elseif ($this->checkRank($member, $this->required_permissions[$command])) {
                //$this->modubot->logger->debug("Member `{$member->id}` has at least the required rank for `$command`");
                $array[$lowest_rank][] = $command;
            }
            //else $this->modubot->logger->debug("Member `{$member->id}` does not have any of the required roles: " . implode(', ', $required_permissions));
        }
        $string = '';
        $array = array_reverse($array, true); // Sort by highest rank first
        foreach (array_keys($array) as $rank) {
            if (is_numeric($rank)) $string .= '<@&' . $rank . '>: `';
            else $string .= '@' . $rank . ': `'; // everyone
            asort($array[$rank]);
            $string .= implode('`, `', $array[$rank]);
            $string .= '`' . PHP_EOL;
        }
        return $string;
    }

    // Don't forget to use ->setAllowedMentions(['parse'=>[]]) on the MessageBuilder object to prevent all roles being pinged
    public function __toString(): string
    {
        return $this->generateHelp();
    }
}