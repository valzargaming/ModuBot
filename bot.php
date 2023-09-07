<?php

/*
 * This file is a part of the ModuBot project.
 *
 * Copyright (c) 2023-present Valithor Obsidion <valithor@valzargaming.com>
 */

use \ModuBot\ModuBot;
use \Discord\Discord;
//use \Discord\Helpers\CacheConfig;
use \React\EventLoop\Loop;
//use \WyriHaximus\React\Cache\Redis as RedisCache;
//use \Clue\React\Redis\Factory as Redis;
use \React\Filesystem\Factory as FilesystemFactory;
use \Monolog\Logger;
use \Monolog\Level;
use \Monolog\Formatter\LineFormatter;
use \Monolog\Handler\StreamHandler;
use \Discord\WebSockets\Intents;
use \React\Http\Browser;

ini_set('display_errors', 1);
error_reporting(E_ALL);

set_time_limit(0);
ignore_user_abort(1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1'); // Unlimited memory usage
define('MAIN_INCLUDED', 1); // Token and SQL credential files may be protected locally and require this to be defined to access
require getcwd() . '/token.php'; // $token
include getcwd() . '/vendor/autoload.php';

$loop = Loop::get();
$streamHandler = new StreamHandler('php://stdout', Level::Info);
$streamHandler->setFormatter(new LineFormatter(null, null, true, true));
$logger = new Logger('ModuBot', [$streamHandler]);
$discord = new Discord([
    'loop' => $loop,
    'logger' => $logger,
    /* // Disabled for debugging
    'cache' => new CacheConfig(
        $interface = new RedisCache(
            (new Redis($loop))->createLazyClient('127.0.0.1:6379'),
            'dphp:cache:
        '),
        $compress = true, // Enable compression if desired
        $sweep = false // Disable automatic cache sweeping if desired
    ), 
    */
    /*'socket_options' => [
        'dns' => '8.8.8.8', // can change dns
    ],*/
    'token' => $token,
    'loadAllMembers' => true,
    'storeMessages' => true, // Because why not?
    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::MESSAGE_CONTENT,
]);
include 'stats_object.php'; 
$stats = new Stats();
$stats->init($discord);
$browser = new Browser($loop);
$filesystem = FilesystemFactory::create($loop);
include 'functions.php'; // execIn ckground(), portIsAvailable()
include 'variable_functions.php';
include 'modubot.php';
include 'Handler.php';
include 'MessageHandler.php';

// TODO: Add a timer and a callable function to update these IP addresses every 12 hours
$external_ip = file_get_contents('http://ipecho.net/plain');
//$modubot_ip = gethostbyname('www.modubot.com');
$vzg_ip = gethostbyname('www.valzargaming.com');

$webapi = null;
$socket = null;
$options = array(
    'sharding' => false, // Enable sharding of the bot, allowing it to be run on multiple servers without conflicts, and suppressing certain responses where a shard may be handling the request
    'shard' => false, // Whether this instance is a shard

    'loop' => $loop,
    'discord' => $discord,
    'browser' => $browser,
    'filesystem' => $filesystem,
    'logger' => $logger,
    'stats' => $stats,

    'webapi' => &$webapi,
    'socket' => &$socket,

    'github' => 'https://github.com/valzargaming/modubot',
    'command_symbol' => '@ModuBot',
    'owner_id' => '116927250145869826', // Valithor
    'technician_id' => '116927250145869826', // Valithor
    'server_settings' => [ // Server specific settings
        '923969098185068594' => [
            'enabled' => true,
            'name' => 'Valithor\'s server',
            'Host' => 'Valithor',
            'legacy' => true,
            'moderate' => false,
        ],
    ],
    'primary_guild_id' => '798026051304554556', // The guild where this bot is supported
    'legacy' => true,
    'moderate' => true,
    'badwords' => [
        /* Format:
            'word' => 'bad word' // Bad word to look for
            'duration' => duration ['1 minute', '1 hour', '1 day', '1 week', '1 month', '999 years'] // Duration of the ban
            'reason' => 'reason' // Reason for the ban
            'category' => rule category ['racism/discrimination', 'toxic', 'advertisement'] // Used to group bad words together by category
            'method' => detection method ['exact', 'str_contains', 'str_ends_with', 'str_starts_with'] // Exact ignores partial matches, str_contains matches partial matches, etc.
            'warnings' => 1 // Number of warnings before a ban
        */
        //['word' => 'badwordtestmessage', 'duration' => '1 minute', 'reason' => 'Violated server rule.', 'category' => 'test', 'method' => 'str_contains', 'warnings' => 1], // Used to test the system
        ['word' => 'discord.gg', 'duration' => '999 years', 'reason' => 'You must not post unauthorized Discord invitation links in any channel.', 'category' => 'advertisement', 'method' => 'str_contains', 'warnings' => 2],
        ['word' => 'discord.com', 'duration' => '999 years', 'reason' => 'You must not post unauthorized Discord invitation links in any channel.', 'category' => 'advertisement', 'method' => 'str_contains', 'warnings' => 2],
    ],
    'folders' => array(
        //
    ),
    'files' => array( // Server-specific file paths MUST start with the server name as defined in server_settings unless otherwise specified
        // Fun
        'insults_path' => 'insults.txt',
        'status_path' => 'status.txt',
    ),
    'channel_ids' => array(
        'welcome' => '798026051544023052', // #welcome
        'staff_bot' => '798026051723722808', // #staff-bot
        'webhooks' => '1149399138581106809', // #webhooks
    ),
    'role_ids' => array(
        'Developer' => '798026051304554565',
        'Administrator' => '798026051304554564',
        'Moderator' => '798026051304554563',
        'Contributor' => '798026051304554561',
        'Verified' => '798026051304554560',
        '18+' => '798026051304554559',
    ),
    'functions' => array(
        'ready' => [
            'status_changer_timer' => $status_changer_timer,
            'status_changer_random' => $status_changer_random,
        ],
        'message' => [
            'on_message' => $on_message,
        ],
        'GUILD_MEMBER_ADD' => [
            // 
        ],
        'misc' => [ // Custom functions
            //
        ],
    ),
);
//$options['welcome_message'] = "Welcome to the ModuBot Discord Server! Please read the rules and verify your account using the `approveme` command. Failure to verify in a timely manner will result in an automatic removal from the server.";

$modubot = new ModuBot($options);
$global_error_handler = function (int $errno, string $errstr, ?string $errfile, ?int $errline) use ($modubot) {
    if (
        ($channel = $modubot->discord->getChannel($modubot->channel_ids['staff_bot']))
        && ! str_ends_with($errstr, 'Connection timed out')
        && ! str_ends_with($errstr, 'No route to host')
    )
    {
        $msg = "[$errno] Fatal error on `$errfile:$errline`: $errstr ";
        if (isset($modubot->technician_id) && $tech_id = $modubot->technician_id) $msg = "<@{$tech_id}>, $msg";
        $channel->sendMessage($msg);
    }
};
set_error_handler($global_error_handler);
include 'webapi.php'; // $socket, $webapi, webapiFail(), webapiSnow();
$modubot->run();