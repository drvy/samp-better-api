# sampBetterAPI
A more friendly and more intuitive SAMP API for PHP. This is an attempt to make a more developer friendly API for SAMP servers. Instead of pulling all the information into an array and having to process it manually, this API gives you everything you need to work on from the QUERY API from SAMP.

Original documentation on the SAMP Query API can be found [here](https://wiki.sa-mp.com/wiki/Query_Mechanism).

## ~ Features
  - Lazy on-demand connect to the server.
  - 3rd party services online check to reduce PHP ignored timeout on ocasions.
  - Friendly getter ``info()`` and chainable one-player ``$this->player('Name')->id()`` method.
  - Request only necessary information reducing execution time.
  - Better error handling with exceptions and better server connection detect.
  - Better PHP doc documentation without @ignore.
 

## ~ Example Usage
Detailed examples can be seen in the ``/examples`` directory of this repository.
```php
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
    
    $info['players'] = $server->info('detailedPlayers');
    
    echo '<pre>';
    print_r($info);

} catch(exception $error) {

    die('Error has occured: '.$error->getMessage());

}
```

## ~ Westie's original API vs sampBetterAPI
Westie's original API can be found [here](https://github.com/Westie/samp-php). This is a simple comparasion of features.

| Fature                     | sampBetterAPI                  | Westie's Samp Query API       |
| ---------------------------|:-------------------------------|:------------------------------|
| Connect on demand          | Yes                            | No, connects on instance.     |
| Server Online Check        | Yes, local and 3rd party.      | Yes, local but somehow buggy. |
| Players List               | Both (simple, detailed).       | Both (simple, detailed).      |
| Basic Info                 | Yes, ``info()`` detailed.      | Yes, one array for all.       |
| Player is Online           | Yes, ``isOnline()``.           | No.                           |
| Player getters (id,ping..) | Yes, ``player()`` chain.       | No.                           |
| Instant cache              | Yes, request only when needed. | No, request everytime.        |
| Update info                | Yes, ``updateInfo()``.         | No need, requests everytime   |
| Error Management           | Yes via ``Exceptions``         | No, PHP triggered errors.     |
| RCON commands              | No. (feature update)           | No, separate API.             |


## ~ Documentation
Soon. Meanwhile class is well documented via ``PHPDoc``.