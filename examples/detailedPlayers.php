<?php
/**
 * Displays server's online players (including id and ping) usign the sampBetterAPI.
 */

require __DIR__.'/../api/sampBetterAPI.php';

try {

    // Parameters:
    //  127.0.0.1 -> Server IP
    //  7777      -> Server Port
    //  2         -> Timeout
    //  true      -> Check if server is online via 3rd party services.
    $server = new sampBetterAPI('127.0.0.1', '7777', 2, true);

    $players = $server->info('detailedPlayers');

    echo '<pre>';
    print_r($players);

} catch(exception $error) {

    die('Error has occured: '.$error->getMessage());

}