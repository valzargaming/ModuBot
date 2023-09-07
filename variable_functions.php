<?php

/*
 * This file is a part of the ModuBot project.
 *
 * Copyright (c) 2023-present Valithor Obsidion <valithor@valzargaming.com>
 */ 

use ModuBot\ModuBot;
use Discord\Parts\Channel\Message;
use Discord\Parts\User\Activity;
use React\Promise\PromiseInterface;

$status_changer_random = function (ModuBot $modubot): bool
{ // on ready
    if (! $modubot->files['status_path']) {
        unset($modubot->timers['status_changer_timer']);
        $modubot->logger->warning('status_path is not defined');
        return false;
    }
    if (! $status_array = file($modubot->files['status_path'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) {
        unset($modubot->timers['status_changer_timer']);
        $modubot->logger->warning("unable to open file `{$modubot->files['status_path']}`");
        return false;
    }
    list($status, $type, $state) = explode('; ', $status_array[array_rand($status_array)]);
    if (! $status) return false;
    $activity = new Activity($modubot->discord, [ // Discord status            
        'name' => $status,
        'type' => (int) $type, // 0, 1, 2, 3, 4 | Game/Playing, Streaming, Listening, Watching, Custom Status
    ]);
    $modubot->statusChanger($activity, $state);
    return true;
};
$status_changer_timer = function (ModuBot $modubot) use ($status_changer_random): void
{ // on ready
    if (! isset($modubot->timers['status_changer_timer'])) $modubot->timers['status_changer_timer'] = $modubot->discord->getLoop()->addPeriodicTimer(120, function () use ($modubot, $status_changer_random) { $status_changer_random($modubot); });
};

$on_message = function (ModuBot $modubot, Message $message, ?array $message_filtered = null): ?PromiseInterface
{ // on message
    $message_array = $message_filtered ?? $modubot->filterMessage($message);
    if (! $message_array['called']) return null; // Not a command
    if (! $message_array['message_content']) { // No command given
        $random_responses = ['You can see a full list of commands by using the `help` command.'];
        if (count($random_responses) > 0) return $modubot->sendMessage($message->channel, "<@{$message->author->id}>, " . $random_responses[rand(0, count($random_responses)-1)]);
    }
    return null;
};