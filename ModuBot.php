<?php

/*
 * This file is a part of the ModuBot project.
 *
 * Copyright (c) 2023-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace ModuBot;

use ModuBot\Slash;
use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\BigInt;
use Discord\Helpers\Collection;
//use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Guild\Role;
use Discord\Parts\User\Activity;
use Discord\Parts\User\Member;
//use Discord\Parts\User\User;
use Discord\Repository\Interaction\GlobalCommandRepository;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\Http\Browser;
use React\Http\HttpServer;
use React\Http\Message\Response as HttpResponse;
use React\Promise\PromiseInterface;
use React\Socket\SocketServer;
//use React\EventLoop\TimerInterface;
//use React\Filesystem\Factory as FilesystemFactory;

class ModuBot
{
    public bool $sharding = false;
    public bool $shard = false;
    public string $welcome_message = '';
    
    public MessageHandler $messageHandler;
    public HttpHandler $httpHandler;

    public Slash $slash;

    public StreamSelectLoop $loop;
    public Discord $discord;
    public bool $ready = false;
    public Browser $browser;
    public $filesystem;
    public Logger $logger;
    public $stats;

    public $filecache_path = '';
    
    protected HttpServer $webapi;
    protected SocketServer $socket;
    protected string $web_address;
    protected int $http_port;

    protected array $dwa_sessions = [];
    protected array $dwa_timers = [];
    protected array $dwa_discord_ids = [];
    
    public collection $verified; // This probably needs a default value for Collection, maybe make it a Repository instead?
    public collection $pending;    
    public array $softbanned = []; // List of ckeys and discord IDs that are not allowed to go through the verification process

    public array $timers = [];

    public array $server_settings = [];
    public array $enabled_servers = [];
    public bool $moderate = false; // Whether or not to moderate the servers using the badwords list
    public array $badwords = [
        /* Format:
            'word' => 'bad word' // Bad word to look for
            'duration' => duration ['1 minute', '1 hour', '1 day', '1 week', '1 month', '999 years'] // Duration of the ban
            'reason' => 'reason' // Reason for the ban
            'category' => rule category ['racism/discrimination', 'toxic', 'advertisement'] // Used to group bad words together by category
            'method' => detection method ['exact', 'contains'] // Exact ignores partial matches, contains matches partial matchesq
            'warnings' => 1 // Number of warnings before a ban
        */
        ['word' => 'badwordtestmessage', 'duration' => '1 minute', 'reason' => 'Violated server rule.', 'category' => 'test', 'method' => 'contains', 'warnings' => 1], // Used to test the system
        
        ['word' => 'beaner', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'chink', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'coon', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'exact', 'warnings' => 1],
        ['word' => 'fag', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'gook', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'kike', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'nigg', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'nlgg', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'niqq', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'tranny', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        
        ['word' => 'cunt', 'duration' => '1 minute', 'reason' => 'You must not be toxic or too agitated in any channel.', 'category' => 'toxic', 'method' => 'exact', 'warnings' => 5],
        ['word' => 'fuck you', 'duration' => '1 minute', 'reason' => 'You must not be toxic or too agitated in any channel.', 'category' => 'toxic', 'method' => 'exact', 'warnings' => 5],
        ['word' => 'retard', 'duration' => '1 minute', 'reason' => 'You must not be toxic or too agitated in any channel.', 'category' => 'toxic', 'method' => 'exact', 'warnings' => 5],
        ['word' => 'kys', 'duration' => '1 minute', 'reason' => 'You must not be toxic or too agitated in any channel.', 'category' => 'toxic', 'method' => 'exact', 'warnings' => 1], // This is more severe than the others, so ban after only one warning
        
        ['word' => 'discord.gg', 'duration' => '999 years', 'reason' => 'You must not post unauthorized Discord invitation links in any channel.', 'category' => 'advertisement', 'method' => 'contains', 'warnings' => 2],
        ['word' => 'discord.com', 'duration' => '999 years', 'reason' => 'You must not post unauthorized Discord invitation links in any channel.', 'category' => 'advertisement', 'method' => 'contains', 'warnings' => 2],
    ];
    public array $badwords_warnings = []; // Array of [$ckey]['category'] => integer] for how many times a user has recently infringed for a specific category
    public bool $legacy = true; // If true, the bot will use the file methods instead of the SQL ones
    
    public $functions = array(
        'ready' => [],
        'messages' => [],
        'misc' => [],
    );
    public $server_funcs_uncalled = []; // List of functions that are available for use by other functions, but otherwise not called via a message command
    
    public string $command_symbol = '@ModuBot'; // The symbol that the bot will use to identify commands if it is not mentioned
    public string $owner_id = '116927250145869826'; // Valithor's Discord ID
    public string $technician_id = '116927250145869826'; // Valithor's Discord ID
    public string $embed_footer = ''; // Footer for embeds, this is set in the ready event

    public string $github = 'https://github.com/valzargaming/modubot'; // Link to the bot's github page
    public string $auth_url = 'https://www.valzargaming.com/?login'; // Link to the bot's authentication page
    public string $banappeal = ''; // Users can appeal their bans here
    public string $rules = ''; // Link to the server rules
    public bool $webserver_online = false;
    
    public array $folders = [];
    public array $files = [];
    public array $guilds = [];
    
    public array $discord_config = []; // This variable and its related function currently serve no purpose, but I'm keeping it in case I need it later

    /**
     * Creates a ModuBot client instance.
     * 
     * @throws E_USER_ERROR
     */
    public function __construct(array $options = [], array $server_options = [])
    {
        if (php_sapi_name() !== 'cli') trigger_error('DiscordPHP will not run on a webserver. Please use PHP CLI to run a DiscordPHP bot.', E_USER_ERROR);

        // x86 need gmp extension for big integer operation
        if (PHP_INT_SIZE === 4 && ! BigInt::init()) trigger_error('ext-gmp is not loaded. Permissions will NOT work correctly!', E_USER_WARNING);
        
        $options = $this->resolveOptions($options);
        
        $this->loop = $options['loop'];
        $this->browser = $options['browser'];
        //$this->filesystem = $options['filesystem'];
        $this->stats = $options['stats'];
        
        $this->filecache_path = getcwd() . '/json/';
        if (isset($options['filecache_path']) && is_string($options['filecache_path'])) {
            if (! str_ends_with($options['filecache_path'], '/')) $options['filecache_path'] .= '/';
            $this->filecache_path = $options['filecache_path'];
        }
        if (!file_exists($this->filecache_path)) mkdir($this->filecache_path, 0664, true);
        
        if (isset($options['command_symbol']) && $options['command_symbol']) $this->command_symbol = $options['command_symbol'];
        if (isset($options['owner_id'])) $this->owner_id = $options['owner_id'];
        if (isset($options['technician_id'])) $this->technician_id = $options['technician_id'];
        if (isset($options['banappeal'])) $this->banappeal = $options['banappeal'];
        if (isset($options['rules'])) $this->rules = $options['rules'];
        if (isset($options['github'])) $this->github = $options['github'];
        if (isset($options['legacy']) && is_bool($options['legacy'])) $this->legacy = $options['legacy'];
        if (isset($options['moderate']) && is_bool($options['moderate'])) $this->moderate = $options['moderate'];
        if (isset($options['badwords']) && is_array($options['badwords'])) $this->badwords = $options['badwords'];
                
        if (isset($options['discord']) && ($options['discord'] instanceof Discord)) $this->discord = $options['discord'];
        elseif (isset($options['discord_options']) && is_array($options['discord_options'])) $this->discord = new Discord($options['discord_options']);
        else $this->logger->error('No Discord instance or options passed in options!');
        require 'slash.php';
        $this->slash = new Slash($this);
        
        if (isset($options['functions'])) foreach (array_keys($options['functions']) as $key1) foreach ($options['functions'][$key1] as $key2 => $func) $this->functions[$key1][$key2] = $func;
        else $this->logger->warning('No functions passed in options!');
        
        if (isset($options['files'])) $this->files = $options['files'] ?? [];
        else $this->logger->warning('No files passed in options!');
        if (isset($options['guilds'])) $this->guilds = $options['guilds'] ?? [];
        else $this->logger->warning('No guilds passed in options!');

        $this->enabled_servers = array_keys(array_filter($this->server_settings, function($settings) {
            return isset($settings['enabled']) && $settings['enabled'];
        }));
        
        $this->afterConstruct($options, $server_options);
    }

    /**
     * This method is called after the object is constructed.
     * It initializes various properties, starts timers, and starts handling events.
     *
     * @param array $options An array of options.
     * @param array $server_options An array of server options.
     * @return void
     */
    protected function afterConstruct(array $options = [], array $server_options = []): void
    {
        $this->httpHandler = new HttpHandler($this, [], $options['http_whitelist'] ?? [], $options['http_key'] ?? '');
        $this->messageHandler = new MessageHandler($this);
        $this->generateServerFunctions();
        $this->generateGlobalFunctions();
        $this->logger->debug('[COMMAND LIST] ' . $this->messageHandler->generateHelp(null, true));
        if (isset($this->discord)) {
            $this->discord->once('ready', function () use ($options) {
                $this->ready = true;
                $this->logger->info("logged in as {$this->discord->user->displayname} ({$this->discord->id})");
                $this->logger->info('------');
                if (isset($options['webapi'], $options['socket'], $options['web_address'], $options['http_port'])) {
                    $this->logger->info('setting up HttpServer API');
                    $this->webapi = $options['webapi'];
                    $this->socket = $options['socket'];
                    $this->web_address = $options['web_address'];
                    $this->http_port = $options['http_port'];
                    $this->webapi->listen($this->socket);
                }
                $this->logger->info('------');
                if (! $softbanned = $this->VarLoad('softbanned.json')) {
                    $softbanned = [];
                    $this->VarSave('softbanned.json', $softbanned);
                }
                $this->softbanned = $softbanned;
                if (! $badwords_warnings = $this->VarLoad('badwords_warnings.json')) {
                    $badwords_warnings = [];
                    $this->VarSave('badwords_warnings.json', $badwords_warnings);
                }
                $this->badwords_warnings = $badwords_warnings;
                $this->embed_footer = $this->github 
                    ? $this->github . PHP_EOL
                    : '';
                $this->embed_footer .= "{$this->discord->username}#{$this->discord->discriminator} by valithor" . PHP_EOL;

                // Initialize configurations
                if (! $discord_config = $this->VarLoad('discord_config.json')) $discord_config = [];
                foreach ($this->discord->guilds as $guild) if (!isset($discord_config[$guild->id])) $this->SetConfigTemplate($guild, $discord_config);
                $this->discord_config = $discord_config; // Declared, but not currently used for anything
                
                if (! empty($this->functions['ready'])) foreach ($this->functions['ready'] as $func) $func($this);
                else $this->logger->debug('No ready functions found!');
                $this->discord->application->commands->freshen()->done(function (GlobalCommandRepository $commands): void
                {
                    $this->slash->updateCommands($commands);
                });
                
                $this->discord->on('message', function (Message $message): void
                {
                    $message_filtered = $this->filterMessage($message);
                    if (! $this->messageHandler->handle($message, $message_filtered)) { // This section will be deprecated in the future
                        if (! empty($this->functions['message'])) foreach ($this->functions['message'] as $func) $func($this, $message, $message_filtered); // Variable functions
                        else $this->logger->debug('No message variable functions found!');
                    }
                });
                $this->discord->on('GUILD_MEMBER_ADD', function (Member $guildmember): void
                {
                    if ($this->shard) return;                    
                    $this->joinRoles($guildmember);
                    foreach ($this->functions['GUILD_MEMBER_ADD'] as $func) $func($this, $guildmember);
                    if (empty($this->functions['GUILD_MEMBER_ADD'])) $this->logger->debug('No message functions found!');
                });
                $this->discord->on('GUILD_CREATE', function (Guild $guild): void
                {
                    if (!isset($this->discord_config[$guild->id])) $this->SetConfigTemplate($guild, $this->discord_config);
                });
            });

        }
    }
    /**
     * Resolves the given options array by validating and setting default values for each option.
     *
     * @param array $options An array of options to be resolved.
     * @return array The resolved options array.
     */
    protected function resolveOptions(array $options = []): array
    {
        if (! isset($options['sharding']) || ! is_bool($options['sharding'])) $options['sharding'] = false;
        $this->sharding = $options['sharding'];
        
        if (! isset($options['shard']) || ! is_bool($options['shard'])) $options['shard'] = false;
        $this->shard = $options['shard'];

        if (! isset($options['welcome_message']) || ! is_string($options['welcome_message'])) $options['welcome_message'] = '';        
        $this->welcome_message = $options['welcome_message'];
        
        if (! isset($options['logger']) || ! ($options['logger'] instanceof Logger)) {
            $streamHandler = new StreamHandler('php://stdout', Level::Info);
            $streamHandler->setFormatter(new LineFormatter(null, null, true, true));
            $options['logger'] = new Logger(self::class, [$streamHandler]);
        }
        $this->logger = $options['logger'];

        if (isset($options['folders'])) foreach ($options['folders'] as $key => $value) if (! is_string($value) || ! file_exists($value) || ! is_dir($value)) {
            $this->logger->warning("`$value` is not a valid folder path!");
            unset($options['folders'][$key]);
        }
        if (isset($options['files'])) foreach ($options['files'] as $key => $value) if (! is_string($value) || (! file_exists($value) && ! @touch($value))) {
            $this->logger->warning("`$value` is not a valid file path!");
            unset($options['files'][$key]);
        }
        if (isset($options['guilds'])) foreach ($options['guilds'] as $key => $value) {
            if (! is_numeric($key) || ! is_array($value)) {
                $this->logger->warning("Guild `$key` does not have a valid configuration!");
                unset($options['guilds'][$key]);
                continue;
            }
            if (! isset($value['roles']) || ! is_array($value['roles'])) {
                $this->logger->warning("Guild `$key` does not have a valid roles array!");
                unset($options['guilds'][$key]);
            } else foreach ($value['roles'] as $role_name => $role_id) {
                if (! is_numeric($role_id)) {
                    $this->logger->warning("Guild `$key` has a non-numeric role: `$role_name`!");
                    unset($options['guilds'][$key]);
                    break;
                }
            }
            if (! isset($value['channels']) || ! is_array($value['channels'])) {
                $this->logger->warning("Guild `$key` does not have a valid channels array!");
                unset($options['guilds'][$key]);
            } else foreach ($value['channels'] as $channel_name => $channel_id) {
                if (! is_numeric($channel_id)) {
                    $this->logger->warning("Guild `$key` has a non-numeric channel: `$channel_name`!");
                    unset($options['guilds'][$key]);
                    break;
                }
            }
        }
        if (isset($options['functions'])) foreach ($options['functions'] as $key => $array) {
            if (! is_array($array)) {
                $this->logger->warning("`$key` is not a valid function array!");
                unset($options['functions'][$key]);
                continue;
            }
            foreach ($array as $func) if (! is_callable($func)) {
                $this->logger->warning("`$func` is not a valid function!");
                unset($options['functions'][$key]);
            }
        }
        if (! isset($options['loop']) || ! ($options['loop'] instanceof LoopInterface)) $options['loop'] = Loop::get();
        $options['browser'] = $options['browser'] ?? new Browser($options['loop']);
        //$options['filesystem'] = $options['filesystem'] ?? FileSystemFactory::create($options['loop']);
        return $options;
    }

    /**
     * This method generates server functions based on the server settings.
     * It loops through the server settings and generates server functions for each enabled server.
     * For each server, it generates the following message-related functions, prefixed with the server name:
     * - configexists: checks if the server configuration exists.
     * 
     * @return void
     */
    protected function generateServerFunctions(): void
    {    
        /* This function can be used to generate multiple variations of the same function, but it's not currently used
        foreach ($this->server_settings as $key => $settings) {
            if (! isset($settings['enabled']) || ! $settings['enabled']) continue;
            $server = strtolower($key);

            $serverconfigexists = function (?Message $message = null) use ($key): PromiseInterface|bool
            {
                if (isset($this->server_settings[$key])) {
                    if ($message) return $message->react("👍");
                    return true;
                }
                if ($message) return $message->react("👎");
                return false;
            };
            $this->logger->info("Generating {$server}configexists command.");
            $this->messageHandler->offsetSet($server.'configexists', $serverconfigexists, ['Owner', 'Administrator']);
        }
        */
    }

    /**
     * This class method generates global functions for the ModuBot class.
     * It adds message handlers for "ping", "help", "commands", "cpu", "checkip", "softban", and "unsoftban" commands.
     * It also defines a log handler function for retrieving logs for a specified server.
     * 
     * Commands can be called by mentioning the bot or by using the command symbol, followed by the command's name, which is equal to the key used to add the command to the message handler.
     * For example, the "ping" command can be called by mentioning the bot or by using the command symbol, followed by "ping".
     * The third parameter of the MessageHandlerCallback constructor is an array of roles that are allowed to use the command. If the array is empty, the command is available to everyone.
     * (TODO): Add a parameter that allows the command to be used by anyone possessing a permission, such as "Administrator", rather than a specific role.
     * The fourth parameter is a string that alters how the command is called. Default is "exact" which means the command must be called exactly as it is defined.
     * Alternative values are "str_contains", 'str_ends_with', and 'str_starts_with'.
     * (NYI) The fifth parameter is a description of the command that is used if the help command is proceeded by the command name.
     * @return void
     */
    protected function generateGlobalFunctions(): void
    {
        /**
         * Adds a message handler for the "ping" command that replies with "Pong!".
         */
        $this->messageHandler->offsetSet('ping', new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command): PromiseInterface
        {
            return $this->reply($message, 'Pong!');
        }));

        /**
         * Adds a new message handler callback for the "help" command and sets it as both the "help" and "commands" offset in the message handler.
         */
        $help = new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command): PromiseInterface
        {
            return $this->reply($message, $this->messageHandler->generateHelp($message->member), 'help.txt', true);
        });
        $this->messageHandler->offsetSet('help', $help);
        $this->messageHandler->offsetSet('commands', $help);

        $httphelp = new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command): PromiseInterface
        {
            return $this->reply($message, $this->httpHandler->generateHelp(), 'httphelp.txt', true);
        });
        $this->messageHandler->offsetSet('httphelp', $httphelp);
        /**
         * This method retrieves the CPU usage of the operating system where the bot is running.
         * If the operating system is Windows, it uses PowerShell to get the CPU usage percentage.
         * If the operating system is Linux, it uses the sys_getloadavg() function to get the CPU usage percentage.
         */
        $this->messageHandler->offsetSet('cpu', new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command): PromiseInterface
        {
            if (PHP_OS_FAMILY == "Windows") {
                $p = shell_exec('powershell -command "gwmi Win32_PerfFormattedData_PerfOS_Processor | select PercentProcessorTime"');
                $p = preg_replace('/\s+/', ' ', $p); // reduce spaces
                $p = str_replace('PercentProcessorTime', '', $p);
                $p = str_replace('--------------------', '', $p);
                $p = preg_replace('/\s+/', ' ', $p); // reduce spaces
                $load_array = explode(' ', $p);

                $x=0;
                $load = '';
                foreach ($load_array as $line) if (trim($line) && $x == 0) { $load = "CPU Usage: $line%" . PHP_EOL; break; }
                return $this->reply($message, $load);
            } else { // Linux
                $cpu_load = ($cpu_load_array = sys_getloadavg())
                    ? $cpu_load = array_sum($cpu_load_array) / count($cpu_load_array)
                    : '-1';
                return $this->reply($message, "CPU Usage: $cpu_load%");
            }
            return $this->reply($message, 'Unrecognized operating system!');
        }));
        /**
         * Adds a new command 'checkip' to the message handler that returns the IP address of the server.
         */
        $this->messageHandler->offsetSet('checkip', new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command): PromiseInterface
        {
            return $message->reply(file_get_contents('http://ipecho.net/plain'));
        }), ['Developer', 'Administrator']);
        /**
         * Adds softban command to the message handler.
         */
        $this->messageHandler->offsetSet('softban', new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command): PromiseInterface
        {
            $this->softban($id = $this->sanitizeInput(substr($message_filtered['message_content_lower'], strlen($command))));
            return $this->reply($message, "`$id` is no longer allowed to get verified.");
        }), ['Developer', 'Administrator']);

        /**
         * Adds unsoftban command to the message handler.
         */
        $this->messageHandler->offsetSet('unsoftban', new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command): PromiseInterface
        {
            $this->softban($id = $this->sanitizeInput(substr($message_filtered['message_content_lower'], strlen($command))), false);
            return $this->reply($message, "`$id` is allowed to get verified again.");
        }), ['Developer', 'Administrator']);
        /**
         * This function handles retrieving logs for the specified server. It takes a Message object and a string message_content as input.
         * It splits the message_content into tokens and checks if the server is valid. If the server is valid, it checks if the log directory exists.
         * If the log directory exists, it navigates to the specified file and returns the file as a reply. If the file does not exist, it returns a message with available options.
         * If the server is not valid, it returns a message with valid server options.
         */
        $log_handler = function (Message $message, string $message_content): PromiseInterface
        {
            $tokens = explode(';', $message_content);
            $keys = [];
            foreach ($this->server_settings as $key => $settings) {
                if (! isset($settings['enabled']) || ! $settings['enabled']) continue;
                $keys[] = $server = strtolower($key);
                if (! trim($tokens[0]) == $server) continue; // Check if server is valid
                if (! isset($this->files[$server.'_log_basedir']) || ! file_exists($this->files[$server.'_log_basedir'])) {
                    $this->logger->warning("`{$server}_log_basedir` is not defined or does not exist");
                    return $message->react("🔥");
                }
                unset($tokens[0]);
                $results = $this->FileNav($this->files[$server.'_log_basedir'], $tokens);
                if ($results[0]) return $message->reply(MessageBuilder::new()->addFile($results[1], 'log.txt'));
                if (count($results[1]) > 7) $results[1] = [array_pop($results[1]), array_pop($results[1]), array_pop($results[1]), array_pop($results[1]), array_pop($results[1]), array_pop($results[1]), array_pop($results[1])];
                if (! isset($results[2]) || ! $results[2]) return $this->reply($message, 'Available options: ' . PHP_EOL . '`' . implode('`' . PHP_EOL . '`', $results[1]) . '`');
                return $this->reply($message, "{$results[2]} is not an available option! Available options: " . PHP_EOL . '`' . implode('`' . PHP_EOL . '`', $results[1]) . '`');
            }
            return $this->reply($message, 'Please use the format `logs {server}`. Valid servers: `' . implode(', ', $keys) . '`');
        };
        /**
         * Sets a new message handler callback for retrieving logs on the filesystem.
         *
         * @return PromiseInterface
         */
        $this->messageHandler->offsetSet('logs', new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command) use ($log_handler): PromiseInterface
        {
            return $log_handler($message, trim(substr($message_filtered['message_content'], strlen($command))));
        }), ['Developer', 'Administrator']);
        /**
         * Adds a message handler for the "stop" command that reacts with a stop sign emoji and stops the bot.
         * @return null
         * NOTE: This function is currently not working due to a bug in the DiscordPHP library.
         */
        $this->messageHandler->offsetSet('stop', new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command)//: PromiseInterface
        {
            $promise = $message->react("🛑");
            $promise->done(function () { $this->stop(); });
            //return $promise; // Pending PromiseInterfaces v3
            return $promise;
        }), ['Developer', 'Administrator']);

        // httpHandler website endpoints
        $index = new httpHandlerCallback(function (ServerRequestInterface $request, array $data, bool $whitelisted, string $endpoint): HttpResponse
        {
            if ($whitelisted) {
                $method = $this->httpHandler->offsetGet('/botlog') ?? [];
                if ($method = array_shift($method)) return $method($request, $data, $whitelisted, $endpoint);
            }
            return new HttpResponse(HttpResponse::STATUS_FOUND, ['Location' => $this->auth_url]);
        });
        $this->httpHandler->offsetSet('/', $index);
        $this->httpHandler->offsetSet('/index.html', $index);
        $this->httpHandler->offsetSet('/index.php', $index);
        
        $this->httpHandler->offsetSet('/ping', new httpHandlerCallback(function (ServerRequestInterface $request, array $data, bool $whitelisted, string $endpoint): HttpResponse
        {
            return HttpResponse::plaintext('Hello wörld!');
        }));
        $this->httpHandler->offsetSet('/favicon.ico', new httpHandlerCallback(function (ServerRequestInterface $request, array $data, bool $whitelisted, string $endpoint): HttpResponse
        {
            if ($favicon = @file_get_contents('favicon.ico')) return new HttpResponse(HttpResponse::STATUS_OK, ['Content-Type' => 'image/x-icon'], $favicon);
            return new HttpResponse(HttpResponse::STATUS_NOT_FOUND, ['Content-Type' => 'text/plain'], "Unable to access `favicon.ico`");
        }));

        // httpHandler whitelisting with DiscordWebAuth
        if (include('dwa_secrets.php'))
        if ($dwa_client_id = getenv('dwa_client_id'))
        if ($dwa_client_secret = getenv('dwa_client_secret'))
        if (include('DiscordWebAuth.php')) {
            $this->httpHandler->offsetSet('/dwa', new httpHandlerCallback(function (ServerRequestInterface $request, array $data, bool $whitelisted, string $endpoint) use ($dwa_client_id, $dwa_client_secret): HttpResponse
            {
                $ip = $request->getServerParams()['REMOTE_ADDR'];
                if (! isset($this->dwa_sessions[$ip])) {
                    $this->dwa_sessions[$ip] = [];
                    $this->dwa_timers[$ip] = $this->discord->getLoop()->addTimer(30 * 60, function () use ($ip) { // Set a timer to unset the session after 30 minutes
                        unset($this->dwa_sessions[$ip]);
                    });
                }

                $DiscordWebAuth = new \DWA($this, $this->dwa_sessions, $dwa_client_id, $dwa_client_secret, $this->web_address, $this->http_port, $request);
                if (isset($params['code'], $params['state']))
                    return $DiscordWebAuth->getToken($params['state']);
                elseif (isset($params['login']))
                    return $DiscordWebAuth->login();
                elseif (isset($params['logout']))
                    return $DiscordWebAuth->logout();
                elseif ($DiscordWebAuth->isAuthed() && isset($params['remove']))
                    return $DiscordWebAuth->removeToken();
                
                $tech_ping = '';
                if (isset($this->technician_id)) $tech_ping = "<@{$this->technician_id}>, ";
                if (isset($DiscordWebAuth->user, $DiscordWebAuth->user->id)) {
                    $this->dwa_discord_ids[$ip] = $DiscordWebAuth->user->id;

                    // Comment out the following line to bypass whitelisting, or add your own whitelisting logic here
                    if (isset(reset($this->guilds)['channel_ids']['staff_bot']) && $channel = $this->discord->getChannel(reset($this->guilds)['channel_ids']['staff_bot'])) $this->sendMessage($channel, $tech_ping . "<@&$DiscordWebAuth->user->id> tried to log in with Discord but does not have permission to! Please check the logs.");
                    return new HttpResponse(HttpResponse::STATUS_UNAUTHORIZED); 

                    if ($this->httpHandler->whitelist($ip))
                        if (isset(reset($this->guilds)['channel_ids']['staff_bot']) && $channel = $this->discord->getChannel(reset($this->guilds)['channel_ids']['staff_bot']))
                            $this->sendMessage($channel, $tech_ping . "<@{$DiscordWebAuth->user->id}> has logged in with Discord.");
                    $method = $this->httpHandler->offsetGet('/botlog') ?? [];
                    if ($method = array_shift($method))
                        return new HttpResponse(HttpResponse::STATUS_FOUND, ['Location' => "http://{$this->httpHandler->external_ip}:{$this->http_port}/botlog"]);
                }

                return new HttpResponse(HttpResponse::STATUS_OK);
            }));
        }

        // httpHandler log endpoints
        $botlog_func = new httpHandlerCallback(function (ServerRequestInterface $request, array $data, bool $whitelisted, string $endpoint = '/botlog'): HttpResponse
        {
            $webpage_content = function (string $return) use ($endpoint) {
                return '<meta name="color-scheme" content="light dark"> 
                        <div class="button-container">
                            <button style="width:8%" onclick="sendGetRequest(\'pull\')">Pull</button>
                            <button style="width:8%" onclick="sendGetRequest(\'reset\')">Reset</button>
                            <button style="width:8%" onclick="sendGetRequest(\'update\')">Update</button>
                            <button style="width:8%" onclick="sendGetRequest(\'restart\')">Restart</button>
                            <button style="background-color: black; color:white; display:flex; justify-content:center; align-items:center; height:100%; width:68%; flex-grow: 1;" onclick="window.open(\''. $this->github . '\')">' . $this->discord->user->displayname . '</button>
                        </div>
                        <div class="alert-container"></div>
                        <div class="checkpoint">' . 
                            str_replace('[' . date("Y"), '</div><div> [' . date("Y"), 
                                str_replace([PHP_EOL, '[] []', ' [] '], '</div><div>', $return)
                            ) . 
                        "</div>
                        <div class='reload-container'>
                            <button onclick='location.reload()'>Reload</button>
                        </div>
                        <div class='loading-container'>
                            <div class='loading-bar'></div>
                        </div>
                        <script>
                            var mainScrollArea=document.getElementsByClassName('checkpoint')[0];
                            var scrollTimeout;
                            window.onload=function(){
                                if (window.location.href==localStorage.getItem('lastUrl')){
                                    mainScrollArea.scrollTop=localStorage.getItem('scrollTop');
                                } else {
                                    localStorage.setItem('lastUrl',window.location.href);
                                    localStorage.setItem('scrollTop',0);
                                }
                            };
                            mainScrollArea.addEventListener('scroll',function(){
                                clearTimeout(scrollTimeout);
                                scrollTimeout=setTimeout(function(){
                                    localStorage.setItem('scrollTop',mainScrollArea.scrollTop);
                                },100);
                            });
                            function sendGetRequest(endpoint) {
                                var xhr = new XMLHttpRequest();
                                xhr.open('GET', window.location.protocol + '//' + window.location.hostname + ':{$this->http_port}/' + endpoint, true);
                                xhr.onload = function () {
                                    var response = xhr.responseText.replace(/(<([^>]+)>)/gi, '');
                                    var alertContainer = document.querySelector('.alert-container');
                                    var alert = document.createElement('div');
                                    alert.innerHTML = response;
                                    alertContainer.appendChild(alert);
                                    setTimeout(function() {
                                        alert.remove();
                                    }, 15000);
                                    if (endpoint === 'restart') {
                                        var loadingBar = document.querySelector('.loading-bar');
                                        var loadingContainer = document.querySelector('.loading-container');
                                        loadingContainer.style.display = 'block';
                                        var width = 0;
                                        var interval = setInterval(function() {
                                            if (width >= 100) {
                                                clearInterval(interval);
                                                location.reload();
                                            } else {
                                                width += 2;
                                                loadingBar.style.width = width + '%';
                                            }
                                        }, 300);
                                        loadingBar.style.backgroundColor = 'white';
                                        loadingBar.style.height = '20px';
                                        loadingBar.style.position = 'fixed';
                                        loadingBar.style.top = '50%';
                                        loadingBar.style.left = '50%';
                                        loadingBar.style.transform = 'translate(-50%, -50%)';
                                        loadingBar.style.zIndex = '9999';
                                        loadingBar.style.borderRadius = '5px';
                                        loadingBar.style.boxShadow = '0 0 10px rgba(0, 0, 0, 0.5)';
                                        var backdrop = document.createElement('div');
                                        backdrop.style.position = 'fixed';
                                        backdrop.style.top = '0';
                                        backdrop.style.left = '0';
                                        backdrop.style.width = '100%';
                                        backdrop.style.height = '100%';
                                        backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
                                        backdrop.style.zIndex = '9998';
                                        document.body.appendChild(backdrop);
                                        setTimeout(function() {
                                            clearInterval(interval);
                                            if (!document.readyState || document.readyState === 'complete') {
                                                location.reload();
                                            } else {
                                                setTimeout(function() {
                                                    location.reload();
                                                }, 5000);
                                            }
                                        }, 5000);
                                    }
                                };
                                xhr.send();
                            }
                            </script>
                            <style>
                                .button-container {
                                    position: fixed;
                                    top: 0;
                                    left: 0;
                                    right: 0;
                                    background-color: #f1f1f1;
                                    overflow: hidden;
                                }
                                .button-container button {
                                    float: left;
                                    display: block;
                                    color: black;
                                    text-align: center;
                                    padding: 14px 16px;
                                    text-decoration: none;
                                    font-size: 17px;
                                    border: none;
                                    cursor: pointer;
                                    color: white;
                                    background-color: black;
                                }
                                .button-container button:hover {
                                    background-color: #ddd;
                                }
                                .checkpoint {
                                    margin-top: 100px;
                                }
                                .alert-container {
                                    position: fixed;
                                    top: 0;
                                    right: 0;
                                    width: 300px;
                                    height: 100%;
                                    overflow-y: scroll;
                                    padding: 20px;
                                    color: black;
                                    background-color: black;
                                }
                                .alert-container div {
                                    margin-bottom: 10px;
                                    padding: 10px;
                                    background-color: #fff;
                                    border: 1px solid #ddd;
                                }
                                .reload-container {
                                    position: fixed;
                                    bottom: 0;
                                    left: 50%;
                                    transform: translateX(-50%);
                                    margin-bottom: 20px;
                                }
                                .reload-container button {
                                    display: block;
                                    color: black;
                                    text-align: center;
                                    padding: 14px 16px;
                                    text-decoration: none;
                                    font-size: 17px;
                                    border: none;
                                    cursor: pointer;
                                }
                                .reload-container button:hover {
                                    background-color: #ddd;
                                }
                                .loading-container {
                                    position: fixed;
                                    top: 0;
                                    left: 0;
                                    right: 0;
                                    bottom: 0;
                                    background-color: rgba(0, 0, 0, 0.5);
                                    display: none;
                                }
                                .loading-bar {
                                    position: absolute;
                                    top: 50%;
                                    left: 50%;
                                    transform: translate(-50%, -50%);
                                    width: 0%;
                                    height: 20px;
                                    background-color: white;
                                }
                                .nav-container {
                                    position: fixed;
                                    bottom: 0;
                                    right: 0;
                                    margin-bottom: 20px;
                                }
                                .nav-container button {
                                    display: block;
                                    color: black;
                                    text-align: center;
                                    padding: 14px 16px;
                                    text-decoration: none;
                                    font-size: 17px;
                                    border: none;
                                    cursor: pointer;
                                    color: white;
                                    background-color: black;
                                    margin-right: 10px;
                                }
                                .nav-container button:hover {
                                    background-color: #ddd;
                                }
                                .checkbox-container {
                                    display: inline-block;
                                    margin-right: 10px;
                                }
                                .checkbox-container input[type=checkbox] {
                                    display: none;
                                }
                                .checkbox-container label {
                                    display: inline-block;
                                    background-color: #ddd;
                                    padding: 5px 10px;
                                    cursor: pointer;
                                }
                                .checkbox-container input[type=checkbox]:checked + label {
                                    background-color: #bbb;
                                }
                            </style>
                            <div class='nav-container'>"
                                . ($endpoint == '/botlog' ? "<button onclick=\"location.href='/botlog2'\">Botlog 2</button>" : "<button onclick=\"location.href='/botlog'\">Botlog 1</button>")
                            . "</div>
                            <div class='reload-container'>
                                <div class='checkbox-container'>
                                    <input type='checkbox' id='auto-reload-checkbox' " . (isset($_COOKIE['auto-reload']) && $_COOKIE['auto-reload'] == 'true' ? 'checked' : '') . ">
                                    <label for='auto-reload-checkbox'>Auto Reload</label>
                                </div>
                                <button id='reload-button'>Reload</button>
                            </div>
                            <script>
                                var reloadButton = document.getElementById('reload-button');
                                var autoReloadCheckbox = document.getElementById('auto-reload-checkbox');
                                var interval;
        
                                reloadButton.addEventListener('click', function () {
                                    clearInterval(interval);
                                    location.reload();
                                });
        
                                autoReloadCheckbox.addEventListener('change', function () {
                                    if (this.checked) {
                                        interval = setInterval(function() {
                                            location.reload();
                                        }, 15000);
                                        localStorage.setItem('auto-reload', 'true');
                                    } else {
                                        clearInterval(interval);
                                        localStorage.setItem('auto-reload', 'false');
                                    }
                                });
        
                                if (localStorage.getItem('auto-reload') == 'true') {
                                    autoReloadCheckbox.checked = true;
                                    interval = setInterval(function() {
                                        location.reload();
                                    }, 15000);
                                }
                            </script>";
            };
            if ($return = @file_get_contents('botlog.txt')) return HttpResponse::html($webpage_content($return));
            return HttpResponse::plaintext('Unable to access `botlog.txt`')->withStatus(HttpResponse::STATUS_INTERNAL_SERVER_ERROR);
        });
        $this->httpHandler->offsetSet('/botlog', $botlog_func, true);
        $this->httpHandler->offsetSet('/botlog2', $botlog_func, true);
        
        /*$this->messageHandler->offsetSet('role', new MessageHandlerCallback(function (Message $message, array $message_filtered, string $command) use ($log_handler): PromiseInterface
        {
            // Initialize default variables
            //$default_permissions = new \Discord\Parts\Permissions\RolePermission();
            $role_template = new Role($this->discord,
                [
                    'name' => 'name',
                    'color' => 0,
                    'hoist' => false,
                    'mentionable' => false,
                    'permissions' => 0
                ]
            );
            $message->guild->createRole($role_template->getUpdatableAttributes())->done(
                function ($role) use ($message) { //$key, $arr, $val
                    //
                },
                function ($error) {
                    $this->logger->warning("Error creating role! {$error->getMessage()}");
                }
            );
            
            // Determine what function to call and early break if the function is not found
            $method = '';
            $func = null;
            switch ($message_filtered['message_content_lower']) {
                case 'create':
                    $func = function (Message $message, array $message_filtered, string $command): PromiseInterface
                    {
                        return $this->reply($message, 'Placeholder text for adding a role.');
                    };
                    break;
                case 'delete':
                    $func = function (Message $message, array $message_filtered, string $command): PromiseInterface
                    {
                        return $this->reply($message, 'Placeholder text for deleting a role.');
                    };
                    break;
                default:
                    return $this->reply($message, 'Please use the format `role {create|delete}`.');
            }

            return $func($message, $message_filtered, $command);

        }), ['Developer', 'Administrator']);*/
    }

    /**
     * Sanitizes the input by removing unwanted characters and converting to lowercase.
     *
     * @param string $input The input string to sanitize.
     * @return string The sanitized input string.
     */
    public function sanitizeInput(string $input): string
    {
        return trim(str_replace(['<@!', '<@&', '<@', '>', '.', '_', '-', ' '], '', strtolower($input)));
    }
    /**
     * Filters the message content and returns an array with the filtered message content, 
     * the lowercased filtered message content, and a boolean indicating if this bot was called.
     *
     * @param mixed $message The message to filter.
     *
     * @return array An array with the filtered message content, the lowercased filtered message content, and a boolean indicating if the message was called.
     */
    public function filterMessage($message): array
    {
        if (! $message->guild || $message->guild->owner_id != $this->owner_id)  return ['message_content' => '', 'message_content_lower' => '', 'called' => false]; // Only process commands from a guild that Taislin owns
        $message_content = '';
        $prefix = $this->command_symbol;
        $called = false;
        if (str_starts_with($message->content, $call = $prefix . ' ')) { $message_content = trim(substr($message->content, strlen($call))); $called = true; }
        elseif (str_starts_with($message->content, $call = "<@!{$this->discord->id}>")) { $message_content = trim(substr($message->content, strlen($call))); $called = true; }
        elseif (str_starts_with($message->content, $call = "<@{$this->discord->id}>")) { $message_content = trim(substr($message->content, strlen($call))); $called = true; }
        return ['message_content' => $message_content, 'message_content_lower' => strtolower($message_content), 'called' => $called];
    }

    /**
     * Sends a message to a Discord channel.
     *
     * @param Channel $channel The channel to send the message to.
     * @param string $content The content of the message.
     * @param string $file_name The name of the file to attach to the message. Default is 'message.txt'.
     * @param bool $prevent_mentions Whether to prevent mentions in the message. Default is false.
     * @param bool $announce_shard Whether to announce the shard in the message. Default is true.
     *
     * @return PromiseInterface|null A promise that resolves to the sent message, or null if the message could not be sent.
     */
    public function sendMessage($channel, string $content, string $file_name = 'message.txt', $prevent_mentions = false, $announce_shard = true): ?PromiseInterface
    {
        // $this->logger->debug("Sending message to {$channel->name} ({$channel->id}): {$message}");
        if ($announce_shard && $this->sharding && $this->enabled_servers) {
            if (! $enabled_servers_string = implode(', ', $this->enabled_servers)) $enabled_servers_string = 'None';
            if ($this->shard) $content = '**SHARD FOR [' . $enabled_servers_string . ']**' . PHP_EOL . $content;
            else $content = '**MAIN PROCESS FOR [' . $enabled_servers_string . ']**' . PHP_EOL . $content;
        }
        $builder = MessageBuilder::new();
        if ($prevent_mentions) $builder->setAllowedMentions(['parse'=>[]]);
        if (strlen($content)<=2000) return $channel->sendMessage($builder->setContent($content));
        if (strlen($content)<=4096) {
            $embed = new Embed($this->discord);
            $embed->setDescription($content);
            $builder->addEmbed($embed);
            return $channel->sendMessage($builder);
        }
        return $channel->sendMessage($builder->addFileFromContent($file_name, $content));
    }
    /**
     * Sends a reply message to the channel where the original message was sent.
     *
     * @param Message $message The original message to reply to.
     * @param string $content The content of the reply message.
     * @param string $file_name The name of the file to attach to the reply message. Default is 'message.txt'.
     * @param bool $prevent_mentions Whether to prevent mentions in the reply message. Default is false.
     * @param bool $announce_shard Whether to announce the shard in the reply message. Default is true.
     * @return PromiseInterface|null A promise that resolves when the reply message is sent, or null if the message could not be sent.
     */
    public function reply(Message $message, string $content, string $file_name = 'message.txt', $prevent_mentions = false, $announce_shard = true): ?PromiseInterface
    {
        // $this->logger->debug("Sending message to {$channel->name} ({$channel->id}): {$message}");
        if ($announce_shard && $this->sharding && $this->enabled_servers) {
            if (! $enabled_servers_string = implode(', ', $this->enabled_servers)) $enabled_servers_string = 'None';
            if ($this->shard) $content = '**SHARD FOR [' . $enabled_servers_string . ']**' . PHP_EOL . $content;
            else $content = '**MAIN PROCESS FOR [' . $enabled_servers_string . ']**' . PHP_EOL . $content;
        }
        $builder = MessageBuilder::new();
        if ($prevent_mentions) $builder->setAllowedMentions(['parse'=>[]]);
        if (strlen($content)<=2000) return $message->reply($builder->setContent($content));
        if (strlen($content)<=4096) {
            $embed = new Embed($this->discord);
            $embed->setDescription($content);
            $builder->addEmbed($embed);
            return $message->reply($builder);
        }
        return $message->reply($builder->addFileFromContent($file_name, $content));
    }
    
    /**
     * Runs the Discord loop.
     *
     * @return void
     *
     * @throws \Discord\Exceptions\IntentException
     * @throws \Discord\Exceptions\SocketException
     */
    public function run(): void
    {
        $this->logger->info('Starting Discord loop');
        if (!(isset($this->discord))) $this->logger->warning('Discord not set!');
        else $this->discord->run();
    }

    /**
     * Stops the bot and logs the shutdown message.
     *
     * @return void
     */
    public function stop(): void
    {
        $this->logger->info('Shutting down');
        if ((isset($this->discord))) $this->discord->stop();
    }
    
    /**
     * These functions are used to save and load data to and from files.
     * Please maintain a consistent schema for directories and files
     *
     * The bot's $filecache_path should be a folder named json inside of either cwd() or __DIR__
     * getcwd() should be used if there are multiple instances of this bot operating from different source directories or on different shards but share the same bot files (NYI)
     * __DIR__ should be used if the json folder should be expected to always be in the same folder as this file, but only if this bot is not installed as a dependency (e.g. composer require valzargaming/modubot)
     *
     * The recommended schema is to follow DiscordPHP's Redis schema, but replace : with ;
     * dphp:cache:Channel:115233111977099271:1001123612587212820 would become dphp;cache;Channel;115233111977099271;1001123612587212820.json
     * In the above example the first set of numbers represents the guild_id and the second set of numbers represents the channel_id
     * Similarly, Messages might be cached like dphp;cache;Message;11523311197709927;234582138740146176;1014616396270932038.json where the third set of numbers represents the message_id
     * This schema is recommended because the expected max length of the file name will not usually exceed 80 characters, which is far below the NTFS character limit of 255,
     * and is still generic enough to easily automate saving and loading files using data served by Discord
     *
     * Windows users may need to enable long path in Windows depending on whether the length of the installation path would result in subdirectories exceeding 260 characters
     * Click Window key and type gpedit.msc, then press the Enter key. This launches the Local Group Policy Editor
     * Navigate to Local Computer Policy > Computer Configuration > Administrative Templates > System > Filesystem
     * Double click Enable NTFS long paths
     * Select Enabled, then click OK
     *
     * If using Windows 10/11 Home Edition, the following commands need to be used in an elevated command prompt before continuing with gpedit.msc
     * FOR %F IN ("%SystemRoot%\servicing\Packages\Microsoft-Windows-GroupPolicy-ClientTools-Package~*.mum") DO (DISM /Online /NoRestart /Add-Package:"%F")
     * FOR %F IN ("%SystemRoot%\servicing\Packages\Microsoft-Windows-GroupPolicy-ClientExtensions-Package~*.mum") DO (DISM /Online /NoRestart /Add-Package:"%F")
     */
    
     /**
     * Saves an associative array to a file in JSON format.
     *
     * @param string $filename The name of the file to save the data to.
     * @param array $assoc_array The associative array to be saved.
     * @return bool Returns true if the data was successfully saved, false otherwise.
     */
    public function VarSave(string $filename = '', array $assoc_array = []): bool
    {
        if ($filename === '') return false;
        if (file_put_contents($this->filecache_path . $filename, json_encode($assoc_array)) === false) return false;
        return true;
    }
    /**
     * Loads a variable from a file in the file cache.
     *
     * @param string $filename The name of the file to load.
     * @return array|null Returns an associative array of the loaded variable, or null if the file does not exist or could not be loaded.
     */
    public function VarLoad(string $filename = ''): ?array
    {
        if ($filename === '') return null;
        if (!file_exists($this->filecache_path . $filename)) return null;
        if (($string = @file_get_contents($this->filecache_path . $filename) ?? false) === false) return null;
        if (! $assoc_array = json_decode($string, TRUE)) return null;
        return $assoc_array;
    }

    /**
     * This function is used to navigate a file tree and find a file
     *
     * @param string $basedir The directory to start in
     * @param array $subdirs An array of subdirectories to navigate
     * @return array Returns an array with the first element being a boolean indicating if the file was found, and the second element being either an array of files in the directory or the path to the file if it was found
     */
    public function FileNav(string $basedir, array $subdirs): array
    {
        $scandir = scandir($basedir);
        unset($scandir[1], $scandir[0]);
        if (! $subdir = array_shift($subdirs)) return [false, $scandir];
        if (! in_array($subdir = trim($subdir), $scandir)) return [false, $scandir, $subdir];
        if (is_file("$basedir/$subdir")) return [true, "$basedir/$subdir"];
        return $this->FileNav("$basedir/$subdir", $subdirs);
    }

    /**
     * This function is used to set the default configuration for a guild if it does not already exist.
     *
     * @param Guild $guild The guild for which the configuration is being set.
     * @param array &$discord_config The Discord configuration array.
     *
     * @return void
     */
    public function SetConfigTemplate(Guild $guild, array &$discord_config): void
    {
        $discord_config[$guild->id] = [];
        if ($this->VarSave('discord_config.json', $discord_config)) $this->logger->info("Created new config for guild {$guild->name}");
        else $this->logger->warning("Failed top create new config for guild {$guild->name}");
    }
    
    /** (NYI)
     * Retrieves a Role object based on the input string.
     *
     * @param string $input The input string to search for a role.
     * @return Role|null Returns a Role object if found, otherwise null.
     */
    public function getRole(string $input): ?Role
    {
        reset($this->guilds);
        if (! $guild = $this->discord->guilds->get('id', key($this->guilds))) return null;
        if (! $input) return null;
        if (is_numeric($id = $this->sanitizeInput($input)))
            if ($role = $guild->roles->get('id', $id))
                return $role;
        if ($role = $guild->roles->get('name', $input)) return $role;
        $this->logger->warning("Could not find role with id or name `$input`");
        return null;
    }

    /**
     * Soft bans a user by adding their ckey or Discord ID to the softbanned array or removes them from it if $allow is false.
     * 
     * @param string $ckey The key of the user to be soft banned.
     * @param bool $allow Whether to add or remove the user from the softbanned array.
     * @return array The updated softbanned array.
     */
    public function softban($id, $allow = true): array
    {
        if ($allow) $this->softbanned[$id] = true;
        else unset($this->softbanned[$id]);
        $this->VarSave('softbanned.json', $this->softbanned);
        return $this->softbanned;
    }

    /*
     * This function is used to get the country code of an IP address using the ip-api API
     * The site will return a JSON object with the country code, region, and city of the IP address
     * The site will return a status of 429 if the request limit is exceeded (45 requests per minute)
     * Returns a string in the format of 'CC->REGION->CITY'
     *
     * @param string $ip The IP address to lookup.
     * @return string The country code, region and city of the given IP address in the format "countryCode->region->city".
     */
    function __IP2Country(string $ip): string
    {
        // TODO: Add caching and error handling for 429s
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, "http://ip-api.com/json/$ip"); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // The site is usually really fast, so we don't want to wait too long
        $response = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($response, true);
        if (! $json) return ''; // If the request timed out or if the service 429'd us
        if ($json['status'] == 'success') return $json['countryCode'] . '->' . $json['region'] . '->' . $json['city'];
    }
    /**
     * Returns the country code for a given IP address.
     * IMPORTANT: This data used for this function is not maintained and should not be used for anything other than a rough estimate of a user's location.
     *
     * @param string $ip The IP address to look up.
     * @return string The two-letter country code for the IP address, or 'unknown' if the country cannot be determined.
     */
    function IP2Country(string $ip): string
    {
        $numbers = explode('.', $ip);
        if (! include('ip_files/'.$numbers[0].'.php')) return 'unknown'; // $ranges is defined in the included file
        $code = ($numbers[0] * 16777216) + ($numbers[1] * 65536) + ($numbers[2] * 256) + ($numbers[3]);    
        $country = '';
        foreach (array_keys($ranges) as $key) if ($key<=$code) if ($ranges[$key][0]>=$code) {
            $country = $ranges[$key][1];
            break;
        }
        if ($country == '') $country = 'unknown';
        return $country;
    }

    /**
     * This function takes a member and checks if they have previously been verified
     * If they have, it will assign them the appropriate roles
     * If they have not, it will send them a message indicating that they need to verify if the 'welcome_message' is set
     *
     * @param Member $member The member to check and assign roles to
     * @return PromiseInterface|null Returns null if the member is softbanned, otherwise returns a PromiseInterface
     */
    public function joinRoles(Member $member): ?PromiseInterface
    {
        if (! array_key_exists($member->guild_id, $this->guilds)) return null;

        /* TODO: Reimplement this with a new verification system
        if (($item['ss13'] && isset($this->softbanned[$item['ss13']])) || isset($this->softbanned[$member->id])) return null;
        $banned = $this->bancheck($item['ss13'], true);
        $paroled = isset($this->paroled[$item['ss13']]);
        if ($banned && $paroled) return $member->setroles([$this->role_ids['infantry'], $this->role_ids['banished'], $this->role_ids['paroled']], "bancheck join {$item['ss13']}");
        if ($banned) return $member->setroles([$this->role_ids['infantry'], $this->role_ids['banished']], "bancheck join {$item['ss13']}");
        if ($paroled) return $member->setroles([$this->role_ids['infantry'], $this->role_ids['paroled']], "parole join {$item['ss13']}");
        return $member->setroles([$this->role_ids['infantry']], "verified join {$item['ss13']}");
        */

        reset($this->guilds);
        if (isset($this->welcome_message, $this->guilds[$member->guild_id]['channel_ids']['welcome']) && $this->welcome_message && $member->guild_id == key($this->guilds))
            if ($channel = $this->discord->getChannel($this->guilds[$member->guild_id]['channel_ids']['welcome']))
                return $this->sendMessage($channel, "<@{$member->id}>, " . $this->welcome_message);
        return null;
    }

    /**
     * This function is used to change the bot's status on Discord.
     *
     * @param Activity $activity The activity to set for the bot's status.
     * @param string $state The state to set for the bot's status. Default is 'online'.
     * @return void
     */
    public function statusChanger(Activity $activity, $state = 'online'): void
    {
        $this->discord->updatePresence($activity, false, $state);
    }

   /* TODO: Reimplement this without the external game and ckey system
    private function __ChatModerate(string $ckey, string $string, string $server = 'nomads'): string
    {
        foreach ($this->badwords as $badwords_array) switch ($badwords_array['method']) {
            case 'exact': // ban ckey if $string contains a blacklisted phrase exactly as it is defined
                if (preg_match('/\b' . $badwords_array['word'] . '\b/', $string)) $this->__relayViolation($server, $ckey, $badwords_array);
                break 2;
            case 'str_starts_with':
                if (str_starts_with(strtolower($string), $badwords_array['word'])) $this->__relayViolation($server, $ckey, $badwords_array);
                break 2;
            case 'str_ends_with':
                if (str_ends_with(strtolower($string), $badwords_array['word'])) $this->__relayViolation($server, $ckey, $badwords_array);
                break 2;
            case 'str_contains': // ban ckey if $string contains a blacklisted word
            default: // default to 'contains'
                if (str_contains(strtolower($string), $badwords_array['word'])) $this->__relayViolation($server, $ckey, $badwords_array);
                break 2;
        }
        return $string;
    }
    */
    /* TODO: Reimplement this without the external game and ckey system
    private function __relayViolation(string $server, string $ckey, array $badwords_array): string|bool // TODO: return type needs to be decided
    {
        $filtered = substr($badwords_array['word'], 0, 1) . str_repeat('%', strlen($badwords_array['word'])-2) . substr($badwords_array['word'], -1, 1);
        if (! $this->__relayWarningCounter($ckey, $badwords_array)) {
            $arr = ['ckey' => $ckey, 'duration' => $badwords_array['duration'], 'reason' => "Blacklisted phrase ($filtered). Review the rules at {$this->rules}. Appeal at {$this->banappeal}"];
            return $this->ban($arr);
        }
        $warning = "You are currently violating a server rule. Further violations will result in an automatic ban that will need to be appealed on our Discord. Review the rules at {$this->rules}. Reason: {$badwords_array['reason']} ({$badwords_array['category']} => $filtered)";
        if (isset($this->guilds[$member->guild_id]['staff_bot']) && $channel = $this->discord->getChannel($this->guilds[$member->guild_id]['staff_bot'])) $this->sendMessage($channel, "`$ckey` is" . substr($warning, 7));
        return $this->DirectMessage('AUTOMOD', $warning, $ckey, $server);
    }
    */
    /* TODO: Reimplement this without the external game and ckey system
    private function __relayWarningCounter(string $ckey, array $badwords_array): bool
    {
        if (!isset($this->badwords_warnings[$ckey][$badwords_array['category']])) $this->badwords_warnings[$ckey][$badwords_array['category']] = 1;
        else ++$this->badwords_warnings[$ckey][$badwords_array['category']];
        $this->VarSave('badwords_warnings.json', $this->badwords_warnings);
        if ($this->badwords_warnings[$ckey][$badwords_array['category']] > $badwords_array['warnings']) return false;
        return true;
    }
    */

    /**
     * Checks if the primary guild and the bot's config have all the required roles.
     *
     * @param array $required_roles An array of role IDs that are required for the bot to function.
     *
     * @return bool Returns true if all the required roles are present in the primary guild, false otherwise.
     */
    public function hasRequiredConfigRoles(array $required_roles = []): bool
    {
        reset($this->guilds);
        if (! $guild = $this->discord->guilds->get('id', $guild_key = key($this->guilds))) { $this->logger->error('Primary Guild `' . $guild_key . '` is missing!'); return false; }
        if ($diff = array_diff($required_roles, array_keys($this->guilds[$guild_key]['roles']))) { $this->logger->error('Required roles are missing from the `role_ids` config', $diff); return false; }
        foreach ($required_roles as $role) if (!isset($this->guilds[$guild_key]['roles'][$role]) || ! $guild->roles->get('id', $this->guilds[$guild_key]['roles'][$role])) { $this->logger->error("Role with ID `$role` is missing from the Primary Guild"); return false; }
        return true;
    }
    
    /**
     * Check that all required files are properly declared in the bot's config and exist on the filesystem.
     *
     * @param string $postfix The file postfix to search for
     * @param bool $defaults Whether to include default files or not
     * @param array $lists Additional file paths to search for
     * @return array|false An array of file paths or false if no files were found
     */
    public function getRequiredConfigFiles(string $postfix = '', bool $defaults = true, array $lists = []): array|false
    {
        $l = [];
        if ($defaults) {
            $defaultLists = [];
            foreach ($defaultLists as $file_path) if (isset($this->files[$file_path]) && ! in_array($file_path, $l)) array_unshift($l, $file_path);
            else $this->logger->warning("Default `$postfix` file `$file_path` was either missing from the `files` config or already included in the list");
            //if (empty($l)) $this->logger->debug("No default `$postfix` files were found in the `files` config");
        }
        if ($lists) foreach ($lists as $file_path) if (isset($this->files[$file_path]) && ! in_array($file_path, $l)) array_unshift($l, $file_path);
        if (empty($l)) {
            $this->logger->warning("No `$postfix` files were found");
            return false;
        }
        return $l;
    }

    /**
     * This function updates the contents of files based on the roles of verified members.
     *
     * @param callable $callback A function that determines what to write to the file.
     * @param array $file_paths An array of file paths to update.
     * @param array $required_roles An array of required roles for the members.
     * @return void
     */
    /*
    * This function is used to update the contents of files based on the roles of verified members
    * The callback function is used to determine what to write to the file
    */
    public function updateFilesFromMemberRoles(callable $callback, array $file_paths, array $required_roles): void
    {
        foreach ($file_paths as $file_path) {
            if (!file_exists($this->files[$file_path]) || ! $file = @fopen($this->files[$file_path], 'a')) continue;
            ftruncate($file, 0);
            $file_contents = '';
            /* TODO: Reimplement this without the verified ckey system
            foreach ($this->verified as $item) {
                if (! $member = $this->getVerifiedMember($item)) continue;
                $file_contents .= $callback($member, $item, $required_roles);
            }
            */
            fwrite($file, $file_contents);
            fclose($file);
        }
    }

    /**
     * Updates admin lists with required roles and permissions.
     *
     * @param array $lists An array of lists to update.
     * @param bool $defaults Whether to use default permissions if another role is not found first.
     * @param string $postfix The postfix to use for the file names.
     * @return bool Returns true if the update was successful, false otherwise.
     */
    public function adminlistUpdate(array $lists = [], bool $defaults = true, string $postfix = '_admins'): bool
    {
        $required_roles = []; // Priority is from top to bottom
        if (! $this->hasRequiredConfigRoles(array_keys($required_roles))) return false;
        if (! $file_paths = $this->getRequiredConfigFiles($postfix, $defaults, $lists)) return false;

        /* TODO: Reimplement this?
        $callback = function (Member $member, array $item, array $required_roles): string
        {
            $string = '';
            $checked_ids = [];
            foreach (array_keys($required_roles) as $role)
                if ($member->roles->has($this->role_ids[$role]))
                    if (! in_array($member->id, $checked_ids)) {
                        $string .= $item['ss13'] . ';' . $required_roles[$role][0] . ';' . $required_roles[$role][1] . '|||' . PHP_EOL;
                        $checked_ids[] = $member->id;
                    }
            return $string;
        };
        $this->updateFilesFromMemberRoles($callback, $file_paths, $required_roles);
        return true;
        */
        return false;
    }
}