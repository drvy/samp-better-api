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
    $server = new sampBetterAPI('127.0.0.1','7777', 2, true);

    echo '<pre>';

    // Update players list.
    $server->updateInfo('detailedPlayers');

    // Get random player
    $player = array_rand($server->info('players'), 1);

    // Show his/her stats
    echo 'Player Name: ',  $server->player($player)->nickname(), PHP_EOL;
    echo 'Player Score: ', $server->player($player)->score(), PHP_EOL;
    echo 'Player Ping: ',  $server->player($player)->ping(), PHP_EOL;
    echo 'Player ID: ',    $server->player($player)->id(), PHP_EOL;

    echo 'Player is Online: ', var_dump($server->isOnline($player));


} catch(exception $error) {

    die('Error has occured: '.$error->getMessage());

}