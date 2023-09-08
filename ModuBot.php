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
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\Http\Browser;
use React\Http\HttpServer;
use React\Promise\PromiseInterface;
use React\Socket\SocketServer;
//use React\EventLoop\TimerInterface;
use React\Filesystem\Factory as FilesystemFactory;

class ModuBot
{
    public bool $sharding = false;
    public bool $shard = false;
    public string $welcome_message = '';
    
    public MessageHandler $messageHandler;

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
    public string $primary_guild_id = '468979034571931648'; // Guild ID for the ModuBot server

    public string $github = 'https://github.com/valzargaming/modubot'; // Link to the bot's github page
    public string $banappeal = ''; // Users can appeal their bans here
    public string $rules = ''; // Link to the server rules
    public bool $webserver_online = false;
    
    public array $folders = [];
    public array $files = [];
    public array $ips = [];
    public array $ports = [];
    public array $channel_ids = [];
    public array $role_ids = [];
    
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
        $this->filesystem = $options['filesystem'];
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
        if (isset($options['primary_guild_id'])) $this->primary_guild_id = $options['primary_guild_id'];
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
        
        if (isset($options['files'])) foreach ($options['files'] as $key => $path) $this->files[$key] = $path;
        else $this->logger->warning('No files passed in options!');
        if (isset($options['channel_ids'])) foreach ($options['channel_ids'] as $key => $id) $this->channel_ids[$key] = $id;
        else $this->logger->warning('No channel_ids passed in options!');
        if (isset($options['role_ids'])) foreach ($options['role_ids'] as $key => $id) $this->role_ids[$key] = $id;
        else $this->logger->warning('No role_ids passed in options!');

        if (isset($options['server_settings']) && is_array($options['server_settings'])) $this->server_settings = $options['server_settings'];
        else $this->logger->warning('No server_settings passed in options!');

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
        $this->messageHandler = new MessageHandler($this);
        $this->generateServerFunctions();
        $this->generateGlobalFunctions();
        $this->logger->debug('[COMMAND LIST] ' . $this->messageHandler->generateHelp());
        if (isset($this->discord)) {
            $this->discord->once('ready', function () use ($options) {
                $this->ready = true;
                $this->logger->info("logged in as {$this->discord->user->displayname} ({$this->discord->id})");
                $this->logger->info('------');
                if (isset($options['webapi'], $options['socket'])) {
                    $this->logger->info('setting up HttpServer API');
                    $this->webapi = $options['webapi'];
                    $this->socket = $options['socket'];
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
        if (isset($options['channel_ids'])) foreach ($options['channel_ids'] as $key => $value) if (! is_numeric($value)) {
            $this->logger->warning("`$value` is not a valid channel id!");
            unset($options['channel_ids'][$key]);
        }
        if (isset($options['role_ids'])) foreach ($options['role_ids'] as $key => $value) if (! is_numeric($value)) {
            $this->logger->warning("`$value` is not a valid role id!");
            unset($options['role_ids'][$key]);
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
        $options['filesystem'] = $options['filesystem'] ?? FileSystemFactory::create($options['loop']);
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
            return $this->reply($message, $this->messageHandler->generateHelp($message->member->roles), 'help.txt', true);
        });
        $this->messageHandler->offsetSet('help', $help);
        $this->messageHandler->offsetSet('commands', $help);
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
            if ($this->shard) $content .= '**SHARD FOR [' . $enabled_servers_string . ']**' . PHP_EOL;
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
            if ($this->shard) $content .= '**SHARD FOR [' . $enabled_servers_string . ']**' . PHP_EOL;
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
        if (! $guild = $this->discord->guilds->get('id', $this->primary_guild_id)) return null;
        if (! $input) {
            $this->logger->warning("An invalid string was passed to getRole()");
            return null;
        }
        if (is_numeric($id = $this->sanitizeInput($input)))
            if ($role = $guild->roles->get('id', $id))
                return $role;
        if ($role = $guild->roles->get('name', $input)) return $role;
        $this->logger->warning("Could not find role with id or name `$input`");
        return null;
    }

    /* TODO: Reimplement this without the external game system
    public function bancheck(string $ckey, bool $bypass = false): bool
    {
        if (! $ckey = $this->sanitizeInput($ckey)) return false;
        $banned = ($this->legacy ? $this->legacyBancheck($ckey) : $this->sqlBancheck($ckey));
        if (! $this->shard)
            if (! $bypass && $member = $this->getVerifiedMember($ckey))
                if ($banned && ! $member->roles->has($this->role_ids['banished'])) $member->addRole($this->role_ids['banished'], "bancheck ($ckey)");
                elseif (! $banned && $member->roles->has($this->role_ids['banished'])) $member->removeRole($this->role_ids['banished'], "bancheck ($ckey)");
        return $banned;
    }
    */
    public function legacyBancheck(string $ckey): bool
    {
        /* TODO: Reimplement this without the external game system
        foreach ($this->server_settings as $key => $settings) {
            if (! isset($settings['enabled']) || ! $settings['enabled']) continue;
            $server = strtolower($key);
            if (file_exists($this->files[$server.'_bans']) && $file = @fopen($this->files[$server.'_bans'], 'r')) {
                while (($fp = fgets($file, 4096)) !== false) {
                    // str_replace(PHP_EOL, '', $fp); // Is this necessary?
                    $linesplit = explode(';', trim(str_replace('|||', '', $fp))); // $split_ckey[0] is the ckey
                    if ((count($linesplit)>=8) && ($linesplit[8] == $ckey)) {
                        fclose($file);
                        return true;
                    }
                }
                fclose($file);
            } else $this->logger->debug("unable to open `{$this->files[$server.'_bans']}`");
        }
        */
        return false;
    }
    public function sqlBancheck(string $ckey): bool
    {
        // TODO
        return false;
    }

    /**
     * Placeholder method for SQL unban functionality.
     *
     * @param array $array An array of parameters.
     * @param mixed|null $admin An optional parameter indicating the admin.
     * @param string|null $key An optional parameter indicating the key.
     *
     * @return string A string indicating that SQL methods are not yet implemented.
     */
    public function sqlUnban(array $array, ?string $admin = null, ?string $key = ''): string
    {
        return "SQL methods are not yet implemented!" . PHP_EOL;
    }
    /* TODO: Reimplement this without the external game system
    public function legacyUnban(string $ckey, ?string $admin = null, ?string $key = ''): void
    {
        $admin = $admin ?? $this->discord->user->username;
        $legacyUnban = function (string $ckey, string $admin, string $key)
        {
            $server = strtolower($key);
            if (file_exists($this->files[$server.'_discord2unban']) && $file = @fopen($this->files[$server.'_discord2unban'], 'a')) {
                fwrite($file, $admin . ":::$ckey");
                fclose($file);
            } else $this->logger->warning("unable to open {$this->files[$server.'_discord2unban']}");
        };
        if ($key) $legacyUnban($ckey, $admin, $key);
        else foreach ($this->server_settings as $key => $settings) {
            if (! isset($settings['enabled']) || ! $settings['enabled']) continue;
            $legacyUnban($ckey, $admin, $key);
        }
    }
    */
    /* TODO: Reimplement this without the external game system
    public function legacyBan(array $array, $admin = null, ?string $key = ''): string
    {
        $admin = $admin ?? $this->discord->user->username;
        $legacyBan = function (array $array, string $admin, string $key): string
        {
            $server = strtolower($key);
            if (str_starts_with(strtolower($array['duration']), 'perm')) $array['duration'] = '999 years';
            if (file_exists($this->files[$server.'_discord2ban']) && $file = @fopen($this->files[$server.'_discord2ban'], 'a')) {
                fwrite($file, "$admin:::{$array['ckey']}:::{$array['duration']}:::{$array['reason']}" . PHP_EOL);
                fclose($file);
                return "**$admin** banned **{$array['ckey']}** from **{$key}** for **{$array['duration']}** with the reason **{$array['reason']}**" . PHP_EOL;
            } else {
                $this->logger->warning("unable to open {$this->files[$server.'_discord2ban']}");
                return "unable to open `{$this->files[$server.'_discord2ban']}`" . PHP_EOL;
            }
        };
        if ($key) return $legacyBan($array, $admin, $key);
        $result = '';
        foreach ($this->server_settings as $key => $settings) {
            if (! isset($settings['enabled']) || ! $settings['enabled']) continue;
            $result .= $legacyBan($array, $admin, $key);
        }
        return $result;
    }
    */
    /**
     * Placeholder method for SQL ban functionality.
     *
     * @param array $array An array of ban parameters.
     * @param mixed $admin The admin who issued the ban.
     * @param string|null $key The key to use for the ban.
     * @return string A message indicating that SQL methods are not yet implemented.
     */
    public function sqlBan(array $array, ?string $admin = null, ?string $key = ''): string
    {
        return "SQL methods are not yet implemented!" . PHP_EOL;
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
    * These functions determine which of the above methods should be used to process a ban or unban
    * Ban functions will return a string containing the results of the ban
    * Unban functions will return nothing, but may contain error-handling messages that can be passed to $logger->warning()
    */
    /* TODO: Reimplement this without the external game system
    public function ban(array &$array, ?string $admin = null, ?string $key = '', $permanent = false): string
    {
        if (! isset($array['ckey'])) return "You must specify a ckey to ban.";
        if (! is_numeric($array['ckey']) && ! is_string($array['ckey'])) return "The ckey must be a Byond username or Discord ID.";
        if (! isset($array['duration'])) return "You must specify a duration to ban for.";
        if ($array['duration'] == '999 years') $permanent = true;
        if (! isset($array['reason'])) return "You must specify a reason for the ban.";
        $array['ckey'] = $this->sanitizeInput($array['ckey']);
        if (is_numeric($array['ckey'])) {
            if (! $item = $this->verified->get('discord', $array['ckey'])) return "Unable to find a ckey for <@{$array['ckey']}>. Please use the ckey instead of the Discord ID.";
            $array['ckey'] = $item['ss13'];
        }
        if (! $this->shard)
            if ($member = $this->getVerifiedMember($array['ckey']))
                if (! $member->roles->has($this->role_ids['banished'])) {
                    if (! $permanent) $member->addRole($this->role_ids['banished'], "Banned for {$array['duration']} with the reason {$array['reason']}");
                    else $member->setRoles([$this->role_ids['banished'], $this->role_ids['permabanished']], "Banned for {$array['duration']} with the reason {$array['reason']}");
                }
        if ($this->legacy) return $this->legacyBan($array, $admin, $key, $permanent);
        return $this->sqlBan($array, $admin, $key, $permanent);
    }
    */
    /* TODO: Reimplement this without the external game system
    public function unban(string $ckey, ?string $admin = null, ?string $key = ''): void
    {
        $admin ??= $this->discord->user->displayname;
        if ($this->legacy) $this->legacyUnban($ckey, $admin, $key);
        else $this->sqlUnban($ckey, $admin, $key);
        if (! $this->shard)
            if ($member = $this->getVerifiedMember($ckey))
                if ($member->roles->has($this->role_ids['banished']))
                    $member->removeRole($this->role_ids['banished'], "Unbanned by $admin");
    }
    */
    
    /* TODO: Reimplement this without the external game system
    public function DirectMessage(string $recipient, string $message, string $sender, ?string $server = ''): bool
    {
        $directmessage = function (string $recipient, string $message, string $sender, string $server): bool
        {
            $server = strtolower($server);
            if (file_exists($this->files[$server.'_discord2dm']) && $file = @fopen($this->files[$server.'_discord2dm'], 'a')) {
                fwrite($file, "$sender:::$recipient:::$message" . PHP_EOL);
                fclose($file);
                return true;
            } else {
                $this->logger->debug("unable to open `{$this->files[$server.'_discord2dm']}`");
                return false;
            }
        };
        
        $sent = false;
        if ($server) $sent = $directmessage($recipient, $message, $sender, $server);
        else foreach ($this->server_settings as $key => $settings) {
            $server = strtolower($key);
            if (! isset($settings['enabled']) || ! $settings['enabled']) continue;
            if ($directmessage($recipient, $message, $sender, $key)) $sent = true;
        }
        return $sent;
    }
    */
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
        if ($member->guild_id !== $this->primary_guild_id) return null;

        /* TODO: Reimplement this without the verification system
        if (($item['ss13'] && isset($this->softbanned[$item['ss13']])) || isset($this->softbanned[$member->id])) return null;
        $banned = $this->bancheck($item['ss13'], true);
        $paroled = isset($this->paroled[$item['ss13']]);
        if ($banned && $paroled) return $member->setroles([$this->role_ids['infantry'], $this->role_ids['banished'], $this->role_ids['paroled']], "bancheck join {$item['ss13']}");
        if ($banned) return $member->setroles([$this->role_ids['infantry'], $this->role_ids['banished']], "bancheck join {$item['ss13']}");
        if ($paroled) return $member->setroles([$this->role_ids['infantry'], $this->role_ids['paroled']], "parole join {$item['ss13']}");
        return $member->setroles([$this->role_ids['infantry']], "verified join {$item['ss13']}");
        */

        if (isset($this->welcome_message, $this->channel_ids['welcome']) && $this->welcome_message && $member->guild_id == $this->primary_guild_id)
            if ($channel = $this->discord->getChannel($this->channel_ids['welcome']))
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
        if (isset($this->channel_ids['staff_bot']) && $channel = $this->discord->getChannel($this->channel_ids['staff_bot'])) $this->sendMessage($channel, "`$ckey` is" . substr($warning, 7));
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
        if (! $guild = $this->discord->guilds->get('id', $this->primary_guild_id)) { $this->logger->error("Primary Guild `{$this->primary_guild_id}` is missing!"); return false; }
        if ($diff = array_diff($required_roles, array_keys($this->role_ids))) { $this->logger->error('Required roles are missing from the `role_ids` config', $diff); return false; }
        foreach ($required_roles as $role) if (!isset($this->role_ids[$role]) || ! $guild->roles->get('id', $this->role_ids[$role])) { $this->logger->error("Role with ID `$role` is missing from the Primary Guild"); return false; }
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
                if (!$member = $this->getVerifiedMember($item)) continue;
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