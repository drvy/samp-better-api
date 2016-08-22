<?php
/**
 * Displays server's basic info usign the sampBetterAPI.
 */

require __DIR__.'/../api/sampBetterAPI.php';

try {

    // Parameters:
    //  127.0.0.1 -> Server IP
    //  7777      -> Server Port
    //  2         -> Timeout
    //  true      -> Check if server is online via 3rd party services.
    $server = new sampBetterAPI('127.0.0.1', '7777', 2, true);

    $info = array(
        'hostname'       => $server->info('hostname'),
        'currentPlayers' => $server->info('currentPlayers'),
        'maxPlayers'     => $server->info('maxPlayers'),
        'gamemode'       => $server->info('gamemode'),
        'language'       => $server->info('language'),
        'ping'           => $server->info('ping')
    );

} catch(exception $error) {

    die('Error has occured: '.$error->getMessage());

}

echo '<pre>';
print_r($info);