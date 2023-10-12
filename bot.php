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

$web_address = '127.0.0.1';
$http_port = 55555;

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
//$filesystem = FilesystemFactory::create($loop);
include 'functions.php'; // execIn ckground(), portIsAvailable()
include 'variable_functions.php';
include 'modubot.php';
include 'Handler.php';
include 'MessageHandler.php';
include 'HttpHandler.php';

// TODO: Add a timer and a callable function to update these IP addresses every 12 hours
//$modubot_ip = gethostbyname('www.modubot.com');
$vzg_ip = gethostbyname('www.valzargaming.com');
$http_whitelist = [$vzg_ip];
$http_key = getenv('WEBAPI_TOKEN') ?? '';

$webapi = null;
$socket = null;
$options = array(
    'sharding' => false, // Enable sharding of the bot, allowing it to be run on multiple servers without conflicts, and suppressing certain responses where a shard may be handling the request
    'shard' => false, // Whether this instance is a shard

    'loop' => $loop,
    'discord' => $discord,
    'browser' => $browser,
    //'filesystem' => $filesystem,
    'logger' => $logger,
    'stats' => $stats,

    'webapi' => &$webapi,
    'socket' => &$socket,
    'web_address' => $web_address,
    'http_port' => $http_port,
    'http_key' => $http_key,
    'http_whitelist' => $http_whitelist,

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

use React\Socket\SocketServer;
use React\Http\HttpServer;
use React\Http\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
$socket = new SocketServer(sprintf('%s:%s', '0.0.0.0', $http_port), [], $modubot->loop);
$last_path = '';
/**
 * This code block creates a new HttpServer object and defines a callback function that handles incoming HTTP requests.
 * The function extracts information from the request URI such as scheme, host, port, path, query and fragment.
 * If the path is empty or does not start with a forward slash, it sets the path to '/index'.
 * The function then sets the last_path variable to the full URI including query and fragment.
 * Finally, the function returns the response generated by the $modubot->httpHandler->handle() method.
 *
 * @param ServerRequestInterface $request The HTTP request object.
 * @return Response The HTTP response object.
 */
$webapi = new HttpServer($loop, function (ServerRequestInterface $request) use ($modubot, &$last_path): Response//Interface
{
    $scheme = $request->getUri()->getScheme();
    $host = $request->getUri()->getHost();
    $port = $request->getUri()->getPort();
    $path = $request->getUri()->getPath();
    if ($path === '' || $path[0] !== '/' || $path === '/') $path = '/index';
    $query = $request->getUri()->getQuery();
    $fragment = $request->getUri()->getFragment(); // Only used on the client side, ignored by the server
    $last_path = "$scheme://$host:$port$path". ($query ? "?$query" : '') . ($fragment ? "#$fragment" : '');
    //$modubot->logger->info('[WEBAPI URI] ' . preg_replace('/(?<=key=)[^&]+/', '********', $last_path););
    return $modubot->httpHandler->handle($request);
});
$webapi->on('error', function (Exception $e, ?\Psr\Http\Message\RequestInterface $request = null) use ($modubot, $socket, &$last_path) {
    if (
        str_starts_with($e->getMessage(), 'Received request with invalid protocol version')
    ) return; // Ignore this error, it's not important
    $last_path = preg_replace('/(?<=key=)[^&]+/', '********', $last_path);
    $error = '[WEBAPI] ' . $e->getMessage() . ' [' . $e->getFile() . ':' . $e->getLine() . '] ' . str_replace('\n', PHP_EOL, $e->getTraceAsString());
    $modubot->logger->error("[WEBAPI] $error");
    if ($request) $modubot->logger->error('[WEBAPI] Request: ' .  preg_replace('/(?<=key=)[^&]+/', '********', $request->getRequestTarget()));
    if (str_starts_with($e->getMessage(), 'The response callback')) {
        $modubot->logger->info('[WEBAPI] ERROR - RESTART');
        if (isset($modubot->channel_ids['staff_bot']) && $channel = $modubot->discord->getChannel($modubot->channel_ids['staff_bot'])) {
            $builder = \Discord\Builders\MessageBuilder::new()
                ->setContent('Restarting due to error in HttpServer API...' . PHP_EOL . "Last path: `$last_path`")
                ->addFileFromContent("httpserver_error.txt",preg_replace('/(?<=key=)[^&]+/', '********', $error));
            $channel->sendMessage($builder);
        }
        $socket->close();
        if (! isset($modubot->timers['restart'])) $modubot->timers['restart'] = $modubot->discord->getLoop()->addTimer(5, function () use ($modubot) {
            \restart();
            $modubot->discord->close();
            die();
        });
    }
});

$modubot->run();