<?php

/*
 * This file is a part of the ModuBot project.
 *
 * Copyright (c) 2023-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace ModuBot;

use Discord\Builders\MessageBuilder;
use React\Promise\PromiseInterface;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Interactions\Command\Command;
use Discord\Parts\Permissions\RolePermission;
use Discord\Repository\Guild\GuildCommandRepository;
use Discord\Repository\Interaction\GlobalCommandRepository;

class Slash
{
    public ModuBot $modubot;

    public function __construct(ModuBot &$modubot) {
        $this->modubot = $modubot;
        $this->afterConstruct();
    }

    /*
    * This function is called after the constructor is finished.
    * It is used to load the files, start the timers, and start handling events.
    */
    protected function afterConstruct()
    {
        // 
    }
    public function updateCommands(?GlobalCommandRepository $commands): void
    {
        if ($this->modubot->shard) return; // Only run on the first shard

        // if ($command = $commands->get('name', 'ping')) $commands->delete($command->id);
        if (! $commands->get('name', 'ping')) $commands->save(new Command($this->modubot->discord, [
            'name'        => 'ping',
            'description' => 'Replies with Pong!',
        ]));

        // if ($command = $commands->get('name', 'ping')) $commands->delete($command->id);
        if (! $commands->get('name', 'help')) $commands->save(new Command($this->modubot->discord, [
            'name'          => 'help',
            'description'   => 'View a list of available commands',
            'dm_permission' => false,
        ]));

        // if ($command = $commands->get('name', 'pull')) $commands->delete($command->id);
        if (! $commands->get('name', 'pull')) $commands->save(new Command($this->modubot->discord, [
                'name'                       => 'pull',
                'description'                => "Update the bot's code",
                'dm_permission'              => false,
                'default_member_permissions' => (string) new RolePermission($this->modubot->discord, ['view_audit_log' => true]),
        ]));

        // if ($command = $commands->get('name', 'update')) $commands->delete($command->id);
        if (! $commands->get('name', 'update')) $commands->save(new Command($this->modubot->discord, [
                'name'                       => 'update',
                'description'                => "Update the bot's dependencies",
                'dm_permission'              => false,
                'default_member_permissions' => (string) new RolePermission($this->modubot->discord, ['view_audit_log' => true]),
        ]));

        // if ($command = $commands->get('name', 'stats')) $commands->delete($command->id);
        if (! $commands->get('name', 'stats')) $commands->save(new Command($this->modubot->discord, [
            'name'                       => 'stats',
            'description'                => 'Get runtime information about the bot',
            'dm_permission'              => false,
            'default_member_permissions' => (string) new RolePermission($this->modubot->discord, ['moderate_members' => true]),
        ]));

        // if ($command = $commands->get('name', 'invite')) $commands->delete($command->id);
        if (! $commands->get('name', 'invite')) $commands->save(new Command($this->modubot->discord, [
                'name'                       => 'invite',
                'description'                => 'Bot invite link',
                'dm_permission'              => false,
                'default_member_permissions' => (string) new RolePermission($this->modubot->discord, ['manage_guild' => true]),
        ]));

        $this->modubot->discord->guilds->get('id', $this->modubot->primary_guild_id)->commands->freshen()->done(function (?GuildCommandRepository $commands) {
            //
        });

        $this->declareListeners();
    }
    public function declareListeners(): void
    {
        $this->modubot->discord->listenCommand('ping', function (Interaction $interaction): PromiseInterface
        {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent('Pong!'));
        });

        $this->modubot->discord->listenCommand('help', function (Interaction $interaction): PromiseInterface
        {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent($this->modubot->messageHandler->generateHelp($interaction->member->roles)), true);
        });

        $this->modubot->discord->listenCommand('pull', function (Interaction $interaction): void
        {
            $this->modubot->logger->info('[GIT PULL]');
            \execInBackground('git pull');
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Updating code from GitHub...'));
        });
        
        $this->modubot->discord->listenCommand('update', function (Interaction $interaction): void
        {
            $this->modubot->logger->info('[COMPOSER UPDATE]');
            \execInBackground('composer update');
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Updating dependencies...'));
        });

        $this->modubot->discord->listenCommand('stats', function (Interaction $interaction): PromiseInterface
        {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent('ModuBot Stats')->addEmbed($this->modubot->stats->handle()));
        });
        
        $this->modubot->discord->listenCommand('invite', function (Interaction $interaction): PromiseInterface
        {
            return $interaction->respondWithMessage(MessageBuilder::new()->setContent($this->modubot->discord->application->getInviteURLAttribute('8')), true);
        });
    }
}