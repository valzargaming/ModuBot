<?php

/*
 * This file is a part of the ModuBot project.
 *
 * Copyright (c) 2023-present Valithor Obsidion <valithor@valzargaming.com>
 */

use Discord\Parts\Embed\Embed;
use Discord\Builders\MessageBuilder;
use React\Socket\SocketServer;
use React\Http\HttpServer;
use React\Http\Message\Response;
use \Psr\Http\Message\ServerRequestInterface;

@include getcwd() . '/webapi_token_env.php'; // putenv("WEBAPI_TOKEN='YOUR_TOKEN_HERE'");
$webhook_key = getenv('WEBAPI_TOKEN') ?? 'CHANGEME'; // The token is used to verify that the sender is legitimate and not a malicious actor

$webapiFail = function (string $part, string $id) {
    // logInfo('[webapi] Failed', ['part' => $part, 'id' => $id]);
    return new Response(($id ? 404 : 400), ['Content-Type' => 'text/plain'], ($id ? 'Invalid' : 'Missing').' '.$part);
};

$webapiSnow = function (string $string) {
    return preg_match('/^[0-9]{16,20}$/', $string);
};

// $external_ip = file_get_contents('http://ipecho.net/plain');
// $modubot_ip = gethostbyname('www.modubot.com');
// $vzg_ip = gethostbyname('www.valzargaming.com');
$port = '55555';
$socket = new SocketServer(sprintf('%s:%s', '0.0.0.0', $port), [], $modubot->loop);

$webapi = new HttpServer($loop, function (ServerRequestInterface $request) use ($modubot, $port, $socket, $vzg_ip, $external_ip, $webhook_key, $portknock, $portknock_ips, $max_attempts, $webapiFail, $webapiSnow)
{
    /*
    $path = explode('/', $request->getUri()->getPath());
    $sub = (isset($path[1]) ? (string) $path[1] : false);
    $id = (isset($path[2]) ? (string) $path[2] : false);
    $id2 = (isset($path[3]) ? (string) $path[3] : false);
    $ip = (isset($path[4]) ? (string) $path[4] : false);
    $idarray = array(); // get from post data (NYI)
    */
    
    $echo = 'API ';
    $sub = 'index.';
    $path = explode('/', $request->getUri()->getPath());
    $modubot->logger->debug('[webapi] ' . $request->getServerParams()['REMOTE_ADDR'] . ' ' . $request->getMethod() . ' ' . $request->getUri()->getPath());
    $repository = $sub = (isset($path[1]) ? (string) strtolower($path[1]) : false); if ($repository) $echo .= "$repository";
    $method = $id = (isset($path[2]) ? (string) strtolower($path[2]) : false); if ($method) $echo .= "/$method";
    $id2 = $repository2 = (isset($path[3]) ? (string) strtolower($path[3]) : false); if ($id2) $echo .= "/$id2";
    $ip = $partial = $method2 = (isset($path[4]) ? (string) strtolower($path[4]) : false); if ($partial) $echo .= "/$partial";
    $id3 = (isset($path[5]) ? (string) strtolower($path[5]) : false); if ($id3) $echo .= "/$id3";
    $id4 = (isset($path[6]) ? (string) strtolower($path[6]) : false); if ($id4) $echo .= "/$id4";
    $idarray = array(); // get from post data (NYI)
    // $modubot->logger->info($echo);
    
    if ($ip) $modubot->logger->info('API IP ' . $ip);
    $whitelist = [
        '127.0.0.1',
        $external_ip,
        //$modubot_ip,
        $vzg_ip,
    ];
    $whitelist = array_merge($whitelist);
    $substr_whitelist = ['10.0.0.', '192.168.']; 
    $whitelisted = false;
    foreach ($substr_whitelist as $substr) if (substr($request->getServerParams()['REMOTE_ADDR'], 0, strlen($substr)) == $substr) $whitelisted = true;
    if (in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist)) $whitelisted = true;
    
    if (! $whitelisted) $modubot->logger->info('API REMOTE_ADDR ' . $request->getServerParams()['REMOTE_ADDR']);

    $webpage_content = function (string $return) use ($modubot, $port, $sub) {
        return '<meta name="color-scheme" content="light dark"> 
                <div class="button-container">
                    <button style="width:8%" onclick="sendGetRequest(\'pull\')">Pull</button>
                    <button style="width:8%" onclick="sendGetRequest(\'reset\')">Reset</button>
                    <button style="width:8%" onclick="sendGetRequest(\'update\')">Update</button>
                    <button style="width:8%" onclick="sendGetRequest(\'restart\')">Restart</button>
                    <button style="background-color: black; color:white; display:flex; justify-content:center; align-items:center; height:100%; width:68%; flex-grow: 1;" onclick="window.open(\''. $modubot->github . '\')">' . $modubot->discord->user->displayname . '</button>
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
                        if(window.location.href==localStorage.getItem('lastUrl')){
                            mainScrollArea.scrollTop=localStorage.getItem('scrollTop');
                        }else{
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
                        xhr.open('GET', window.location.protocol + '//' + window.location.hostname + ':" . $port . "/' + endpoint, true);
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
                        . ($sub == 'botlog' ? "<button onclick=\"location.href='/botlog2'\">Botlog 2</button>" : "<button onclick=\"location.href='/botlog'\">Botlog 1</button>")
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

    switch ($sub) {
        case (str_starts_with($sub, 'index.')):
            $return = '<meta http-equiv="refresh" content="0 url=\'https://www.valzargaming.com/?login\'" />'; // Redirect to the website to log in
            return new Response(200, ['Content-Type' => 'text/html'], $return);
            break;
        case 'github':
            $return = '<meta http-equiv = \"refresh\" content = \"0; url = ' . $modubot->github . '\" />'; // Redirect to the website to log in
            return new Response(200, ['Content-Type' => 'text/html'], $return);
            break;
        case 'favicon.ico':
            if (! $whitelisted) {
                $modubot->logger->info('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if ($favicon = @file_get_contents('favicon.ico')) return new Response(200, ['Content-Type' => 'image/x-icon'], $favicon);
            else return new Response(501, ['Content-Type' => 'text/plain'], "Unable to access `favicon.ico`");
        
        case 'nohup.out':
            if (! $whitelisted) {
                $modubot->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if ($return = @file_get_contents('nohup.out')) return new Response(200, ['Content-Type' => 'text/plain'], $return);
            else return new Response(501, ['Content-Type' => 'text/plain'], "Unable to access `nohup.out`");
            break;
        
        case 'botlog':
            if (! $whitelisted) {
                $modubot->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if ($return = @file_get_contents('botlog.txt')) return new Response(200, ['Content-Type' => 'text/html'], $webpage_content($return));
            else return new Response(501, ['Content-Type' => 'text/plain'], "Unable to access `botlog.txt`");
            break;
        
        case 'channel':
            if (! $id || !$webapiSnow($id) || ! $return = $modubot->discord->getChannel($id)) return $webapiFail('channel_id', $id);
            break;

        case 'guild':
            if (! $id || !$webapiSnow($id) || ! $return = $modubot->discord->guilds->get('id', $id)) return $webapiFail('guild_id', $id);
            break;

        case 'bans':
            if (! $id || !$webapiSnow($id) || ! $return = $modubot->discord->guilds->get('id', $id)->bans) return $webapiFail('guild_id', $id);
            break;

        case 'channels':
            if (! $id || !$webapiSnow($id) || ! $return = $modubot->discord->guilds->get('id', $id)->channels) return $webapiFail('guild_id', $id);
            break;

        case 'members':
            if (! $id || !$webapiSnow($id) || ! $return = $modubot->discord->guilds->get('id', $id)->members) return $webapiFail('guild_id', $id);
            break;

        case 'emojis':
            if (! $id || !$webapiSnow($id) || ! $return = $modubot->discord->guilds->get('id', $id)->emojis) return $webapiFail('guild_id', $id);
            break;

        case 'invites':
            if (! $id || !$webapiSnow($id) || ! $return = $modubot->discord->guilds->get('id', $id)->invites) return $webapiFail('guild_id', $id);
            break;

        case 'roles':
            if (! $id || !$webapiSnow($id) || ! $return = $modubot->discord->guilds->get('id', $id)->roles) return $webapiFail('guild_id', $id);
            break;

        case 'guildMember':
            if (! $id || !$webapiSnow($id) || ! $guild = $modubot->discord->guilds->get('id', $id)) return $webapiFail('guild_id', $id);
            if (! $id2 || !$webapiSnow($id2) || ! $return = $guild->members->get('id', $id2)) return $webapiFail('user_id', $id2);
            break;

        case 'userId':
            if (! $id || !$webapiSnow($id) || ! $return = $modubot->discord->users->get('id', $id)) return $webapiFail('user_id', $id);
            break;

        case 'userName':
            if (! $id || ! $return = $modubot->discord->users->get('name', $id)) return $webapiFail('user_name', $id);
            break;
        
        case 'reset':
            if (! $whitelisted) {
                $modubot->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            execInBackground('git reset --hard origin/main');
            if (isset($modubot->channel_ids['staff_bot']) && $channel = $modubot->discord->getChannel($modubot->channel_ids['staff_bot'])) $modubot->sendMessage($channel, 'Forcefully moving the HEAD back to origin/main...');
            $return = 'fixing git';
            break;
        
        case 'pull':
            if (! $whitelisted) {
                $modubot->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            execInBackground('git pull');
            $modubot->logger->info('[GIT PULL]');
            if (isset($modubot->channel_ids['staff_bot']) && $channel = $modubot->discord->getChannel($modubot->channel_ids['staff_bot'])) $modubot->sendMessage($channel, 'Updating code from GitHub...');
            $return = 'updating code';
            break;
        
        case 'update':
            if (! $whitelisted) {
                $modubot->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            execInBackground('composer update');
            $modubot->logger->info('[COMPOSER UPDATE]');
            if (isset($modubot->channel_ids['staff_bot']) && $channel = $modubot->discord->getChannel($modubot->channel_ids['staff_bot'])) $modubot->sendMessage($channel, 'Updating dependencies...');
            $return = 'updating dependencies';
            break;
        
        case 'restart':
            if (! $whitelisted) {
                $modubot->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            $modubot->logger->info('[RESTART]');
            if (isset($modubot->channel_ids['staff_bot']) && $channel = $modubot->discord->getChannel($modubot->channel_ids['staff_bot'])) $modubot->sendMessage($channel, 'Restarting...');
            $return = 'restarting';
            $socket->close();
            if (! isset($modubot->timers['restart'])) $modubot->timers['restart'] = $modubot->discord->getLoop()->addTimer(5, function () use ($modubot) {
                \restart();
                $modubot->discord->close();
                die();
            });
            break;

        case 'lookup':
            if (! $whitelisted) {
                $modubot->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if (! $id || !$webapiSnow($id) || ! $return = $modubot->discord->users->get('id', $id)) return $webapiFail('user_id', $id);
            break;

        case 'owner':
            if (! $whitelisted) {
                $modubot->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if (! $id || !$webapiSnow($id)) return $webapiFail('user_id', $id); $return = false;
            if ($user = $modubot->discord->users->get('id', $id)) { // Search all guilds the bot is in and check if the user id exists as a guild owner
                foreach ($modubot->discord->guilds as $guild) {
                    if ($id == $guild->owner_id) {
                        $return = true;
                        break 1;
                    }
                }
            }
            break;

        case 'avatar':
            if (! $id || !$webapiSnow($id)) return $webapiFail('user_id', $id);
            if (! $user = $modubot->discord->users->get('id', $id)) $return = 'https://cdn.discordapp.com/embed/avatars/'.rand(0,4).'.png'; // Default avatar if user wasn't found
            else $return = $user->avatar;
            // if (! $return) return new Response(($id ? 404 : 400), ['Content-Type' => 'text/plain'], (''));
            break;

        case 'webhook':
            $server =& $method; // alias for readability
            if (! isset($modubot->channel_ids[$server]) || ! $channel_id = $modubot->channel_ids[$server]) return new Response(400, ['Content-Type' => 'text/plain'], 'Webhook Channel Not Defined');
            $params = $request->getQueryParams();
            // var_dump($params);
            if (! $whitelisted && (! isset($params['key']) || $params['key'] != $webhook_key)) return new Response(401, ['Content-Type' => 'text/plain'], 'Unauthorized');
            if (! isset($params['method']) || ! isset($params['data'])) return new Response(400, ['Content-Type' => 'text/plain'], 'Missing Parameters');
            $data = json_decode($params['data'], true);
            $time = '['.date('H:i:s', time()).']';
            $message = '';
            $id = '';
            if (isset($data['id'])) $id = $modubot->sanitizeInput($data['id']);
            switch ($params['method']) {
                case 'garbage':
                    if (isset($data['message'])) $message .= "**__{$time} GARBAGE__ <@$id>**: " . html_entity_decode(strip_tags($data['message']));
                    break;
                default:
                    $modubot->logger->alert("API UNKNOWN METHOD `{$params['method']}` FROM " . $request->getServerParams()['REMOTE_ADDR']);
                    return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid Parameter');
            }
            if ($message && $channel = $modubot->discord->getChannel($channel_id)) {
                if (! $id) $modubot->sendMessage($channel, $message);
                if (is_numeric($id) && $user = $modubot->discord->users->get('id', $id)) {
                    $embed = new Embed($modubot->discord);
                    $embed->setAuthor("{$user->displayname} ({$user->id})", $user->avatar);
                    $embed->setDescription($message);
                    $channel->sendEmbed($embed);
                } elseif (is_numeric($id)) {
                    $modubot->discord->users->fetch($id);
                    $modubot->sendMessage($channel, $message);
                } else $modubot->sendMessage($channel, $message);
            }
            return new Response(200, ['Content-Type' => 'text/html'], 'Done');
        default:
            return new Response(501, ['Content-Type' => 'text/plain'], 'Not implemented');
    }
    // Server-specific
    foreach ($modubot->server_settings as $key => $settings) {
        if (!isset($settings['enabled']) || !$settings['enabled']) continue;
        $server = strtolower($key);
        if ($sub == $key)
            switch ($id) {
                default:
                    return new Response(501, ['Content-Type' => 'text/plain'], 'Not implemented');
            }
            break;
    }
    return new Response(200, ['Content-Type' => 'text/json'], json_encode($return ?? ''));
});
//$webapi->listen($socket); // Moved to modubot.php
$webapi->on('error', function (Exception $e, ?\Psr\Http\Message\RequestInterface $request = null) use ($modubot, $socket) {
    $error = 'API ' . $e->getMessage() . ' [' . $e->getFile() . ':' . $e->getLine() . '] ' . str_replace('\n', PHP_EOL, $e->getTraceAsString());
    $modubot->logger->error('[webapi]' . $error);
    if ($request) $modubot->logger->error('[webapi] Request: ' . $request->getRequestTarget());
    if (str_starts_with($e->getMessage(), 'The response callback')) {
        $modubot->logger->info('[RESTART] WEBAPI ERROR');
        if (isset($modubot->channel_ids['staff_bot']) && $channel = $modubot->discord->getChannel($modubot->channel_ids['staff_bot'])) {
            $builder = MessageBuilder::new()
                ->setContent('Restarting due to error in HttpServer API...')
                ->addFileFromContent("httpserver_error.txt", $error);
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