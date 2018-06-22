<?php
/**
 *  A PHP API for SAMP servers. Will gather info provided by the server
 *  and make it accessable in a programmer friendly way. Uses the general
 *  API provided by SAMP. <https://wiki.sa-mp.com/wiki/Query_Mechanism>
 *
 *  Its more friendly than Westie's QueryAPI since it provides better
 *  error mangament, more functions, on-demand connection and more.
 *
 *  Latest version / Documentation / Issues and More on:
 *  <https://github.com/drvy/sampBetterAPI>
 *
 *  @package sampBetterAPI
 *  @version 0.2
 *  @author Dragomir Yordanov <drvy@drvy.net>
 *  @copyright 2018; MIT Licence <https://github.com/drvy/sampBetterAPI/blob/master/LICENSE>
 */

 #namespace App\Includes; // You should declare your namespace :)

class sampBetterAPI {

    protected $socket; // I store the socket here.
    protected $config; // I store my config here.
    protected $checkm = 'p1337'; // Server has to pong me this message.
    protected $server = array(); // All data retrieved is stored here.
    protected $context = array(); // All internal variables are stored here.

    public $online = false; // Server is Online?

    /* ----------------------------------------------------------------------
     * Construct / Destruct / Magic Methods
     * ------------------------------------------------------------------- */

    /**
     * Inicialize class, set settings and addr.
     *
     * @param      string   $server   Server's IP.
     * @param      string   $port     Server's Port.
     * @param      integer  $timeout  Connection timeout in seconds.
     * @param      boolean  $remote   Check server availability on web service.
     * @throws     Exception  In case fsockopen() doesn't exist.
     */
    public function __construct($server, $port, $timeout=2, $remote=true){

        if(!function_exists('fsockopen')){
            throw new Exception('00 FSOCKOPEN is not available!');
            return;
        }

        $this->config = array(
            'timeout' => $timeout,
            'server' => $server,
            'detect' => $remote,
            'port'   => $port,
        );

        unset($server, $port, $timeout, $remote);

        $this->server['addr'] = $this->config['server'].':'.$this->config['port'];
    }


    /**
     * End connection to server and delete server properties.
     */
    public function __destruct(){
        $this->closeSocket();
        unset($this->server);
    }


    /* ----------------------------------------------------------------------
     * Socket functions.
     * ------------------------------------------------------------------- */


    /**
     * Connects to remote server and checks if its a SAMP server. In case the
     * connection is successfull, sets $online to true, false otherwise.
     *
     * @throws     Exception  In case of error, throws exception.
     * @return     boolean    True if success, False otherwise.
     */
    protected function socket(){

        $this->socket = @fsockopen('udp://'.
            $this->config['server'],
            $this->config['port'],
            $error,
            $error,
            $this->config['timeout']
        );

        if($error || !is_resource($this->socket)){
            $this->closeSocket();
            throw new Exception('01 Cannot connect to server: '.$this->server['addr']);
            return false;
        }

        @socket_set_timeout($this->socket, $this->config['timeout']);

        if(!$this->isSamp()){
            $this->closeSocket();
            throw new Exception('02 This is not a SAMP server: '.$this->server['addr']);
            return false;
        }

        $this->online = true;
        return true;
    }


    /**
     * Closes the connection to the server.
     * @return     boolean  Always return true. (Always close server)
     */
    protected function closeSocket(){
        @fclose($this->socket);
        $this->online = false;
        return true;
    }


    /**
     * Opens a connection to the remote server after checking if its
     * available for connections.
     *
     * @throws     Exception  In case services say its offline.
     * @return     boolean    True if success, False otherwise.
     */
    protected function openSocket(){

        if(is_resource($this->socket)){
            return true;
        }

        if(!$this->checkRemoteOnline()){
            throw new Exception('03 Server seems down from 3rd party services.');
            return false;
        }

        return $this->socket();
    }


    /* ----------------------------------------------------------------------
     * Other functions.
     * ------------------------------------------------------------------- */

    /**
     * Check if the server exists and if its online from a remote service.
     * This may help reduce blocktime and lower resource usage on unstable
     * servers. Makes a doble check on two servers just in case the first
     * service is down.
     *
     * @return     boolean  True if server is online or remote check is disabled.
     *                      False if curl doesn't exist or server is offline.
     */
    protected function checkRemoteOnline(){
        if(!isset($this->config['detect']) || !$this->config['detect']){
            return true;
        }

        if(!function_exists('curl_init')){
            throw new Exception('04 CURL extension is not available.');
            return false;
        }

        $s = $this->config['server'];
        $p = $this->config['port'];

        $server = array(
            'http://www.game-state.com/iframe.php?ip='.$s.'&port='.$p,
            'http://status.homies.cz/samp/generate.php?ipport='.$s.'%3A'.$p.'&details0=on&output=tex'
        );

        unset($s,$p);

        $settings = array(
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0 Safari/537.36',
        );

        $ch = @curl_init();
        @curl_setopt_array($ch, $settings);


        @curl_setopt($ch, CURLOPT_URL, $server[0]);
        $data = @curl_exec($ch);

        if(!$data || !stristr($data, 'Online')){

            @curl_setopt($ch, CURLOPT_URL, $server[1]);
            $data = @curl_exec($ch);


            if(!$data || !stristr($data, 'd_status">Online')){
                return false;
            }
        }

        return true;
    }


    /**
     * Checks if a method has declared that a chain should start.
     * Used in $this->player() and a feature TODO $this->rcon().
     *
     * @return     boolean   True/False Whenever a method has declared a chain.
     */
    protected function chained($c='default'){
        return (isset($this->context['chain'][$c]));
    }


    /**
     * Unsets the chained property.
     *
     * @return     boolean    True. Always.
     */
    protected function unchain($c='default'){
        unset($this->context['chain'][$c]);
        return true;
    }


    /**
     * Sets the chain property. This will require all checking functions
     * to return false if they are not chained.
     *
     * @return     boolean    True. Always.
     */
    protected function chain($c='default'){
        $this->context['chain'][$c] = true;
        return true;
    }


    /* ----------------------------------------------------------------------
     * Packet send/receive functions.
     * ------------------------------------------------------------------- */

    /**
     * Sends a SAMP packet to the server. Has some pre-build packets
     * which are enabled by default, pass $build false to disable them.
     *
     * @param      string     $payload  The payload to send.
     * @param      boolean    $build    Check if payload is pre-build.
     * @throws     Exception  In case of error, throw exception.
     * @return     boolean    True if package sent, False otherwise.
     */
    protected function sendPacket($payload, $build=true, $rcon=false){
        $this->openSocket();

        if($build){
            switch($payload){
                case 'check': $payload = $this->checkm; break;
                case 'info': $payload = 'i'; break;
                case 'rules': $payload = 'r'; break;
                case 'list': $payload = 'c'; break;
                case 'players': $payload = 'd'; break;
                case 'rcon': $payload = 'x'; break;
                default: $payload = $payload; break;
            }
        }

        $_payload = 'SAMP';

        $ip = explode('.', $this->config['server']);
        foreach($ip as $seg){ $_payload .= chr($seg); }

        $_payload .= chr($this->config['port'] & 0xFF);
        $_payload .= chr($this->config['port'] >> 8 & 0xFF);
        $_payload .= $payload;

        unset($payload, $build);

        if (@fwrite($this->socket, $_payload)){ return true; }
        else { throw new Exception('05 Cannot send packet to server!'); }
    }


    /**
     * Requests X bytes from the server and reads them.
     * If $ord is true, will ord() the response.
     *
     * @param      integer  $bytes  The bytes to request.
     * @param      boolean  $ord    Wrap in ord() or not.
     * @return     string   Requested bytes.
     */
    protected function readPacket($bytes=2, $ord=false){
        $result = fread($this->socket, $bytes);
        return ($ord ? ord($result) : $result);
    }


    /**
     * Lose Request. Requests X bytes from server that will be ignored.
     *
     * @param      integer  $bytes  The bytes to ignore.
     * @return     boolean  Always return true.
     */
    protected function loseRead($bytes=2){
        @fread($this->socket, $bytes);
        return true;
    }


    /**
     * Gets the length of the next packet and retrieves it.
     *
     * @param      integer  $firstByte  First bytes that determinate packet size.
     * @param      string   $default    Default value for the packet.
     * @return     string   Packet contents.
     */
    protected function nextPacket($firstByte, $default=null){
        $nextLen = $this->readPacket($firstByte, true);
        if(!$nextLen){ return $default; }

        return $this->readPacket($nextLen, false);
    }


    /**
     * Determines if the server is SAMP. Sends a ping/pong package to the
     * server and expects the same response. Also resposible for determinating
     * the server's ping.
     *
     * @return     boolean  True if SAMP, False otherwise.
     */
    protected function isSamp(){
        $this->sendPacket('check');
        $a = microtime(true);

        var_dump($this->readPacket(15, false));
        die();

        $isSamp = (substr($this->readPacket(15, false),-5) === $this->checkm);
        $b = microtime(true);

        // Server ping represented in ms.
        $this->server['ping'] = abs(round((($a - $b) * 1000), 0));
        unset($a,$b);

        return $isSamp;
    }


    /* ----------------------------------------------------------------------
     * General server related functions.
     * ------------------------------------------------------------------- */


    /**
     * Gets the server provided information. If $long, will also request
     * server rules. This function does not return the response.
     *
     * @param      boolean  $long   Request server rules.
     * @return     boolean  True. Always.
     */
    protected function getServerInfo($long=true){
        $this->sendPacket('info');
        $this->loseRead(11);

        $this->server['currentPlayers'] = (int) $this->readPacket(2, true);
        $this->server['maxPlayers'] = (int) $this->readPacket(2, true);
        $this->server['password'] = (int) $this->readPacket(1, true);
        $this->server['hostname'] = (string) $this->nextPacket(4, null);
        $this->server['gamemode'] = (string) $this->nextPacket(4, null);
        $this->server['language'] = (string) $this->nextPacket(4, null);

        if(!$long){ return true; }

        $this->sendPacket('rules');
        $this->loseRead(11);

        $rulesLenght = (int) $this->readPacket(2, true);

        if($rulesLenght){
            for($a = 0; $a < $rulesLenght; ++$a){
                $rule = (string) $this->nextPacket(1);
                if($rule){ $this->server[$rule] = (string) $this->nextPacket(1); }
                unset($rule);
                continue;
            }
        }

        $this->context['infoType'] = ($long ? 'long' : 'short');
        return true;
    }


    /**
     * Gets the server players that are currently online. If $detail,
     * will also request players ping and id. This function does not
     * return the response.
     *
     * Notice: As of SAMP < 0.3.x if the server has more than 100 players
     * online at the request moment, this will return an empty array.
     * This is a bug of the SAMP server API.
     *
     * @param      boolean  $detail  Request ping and id.
     * @return     boolean  True. Always.
     */
    protected function getServerPlayers($detail=true){

        switch($detail){
            case true: default: $this->sendPacket('players'); break;
            case false: $this->sendPacket('list'); break;
        }

        $this->loseRead(11);

        $players = (int) $this->readPacket(2, true);
        $this->server['players'] = array();

        // No players and version < 0.3.x +100 players bug.
        if($players < 1){ return true; }

        for($a=0; $a < $players; ++$a){

            if($detail){ $id = (int) $this->readPacket(1, true); }

            $nickname = (string) $this->nextPacket(1);
            $score = (int) $this->readPacket(4, true);

            if($detail){ $ping = (int) $this->readPacket(4, true); }

            $this->server['players'][strtolower($nickname)] = array(
                'id' => (isset($id) ? $id : null),
                'ping' => (isset($ping) ? $ping : null),
                'nickname' => $nickname,
                'score' => $score
            );

            unset($score, $ping, $id, $nickname);
            continue;
        }

        $this->context['playerType'] = ($detail ? 'detail' : 'list');
        return true;
    }


    /**
     * Check if a player was defined using $this->player(). If not, throws
     * exception and returns false.
     *
     * @throws     Exception  Exception as player was not defined.
     * @return     boolean    True if defined, False otherwise.
     */
    protected function checkPlayer(){
        if(!isset($this->context['player'])){
            throw new Exception('06 Player must be set previously.');
            return false;
        }

        return true;
    }


    /* ----------------------------------------------------------------------
     * Property getters, setters and checkers.
     * ------------------------------------------------------------------- */


    /**
     * Determines if a player is online.
     *
     * @param      string   $nickname  The nickname
     * @return     boolean  True if player online, False otherwise.
     */
    public function isOnline($nickname){
        if(!is_string($nickname)){ return false; }
        if(!isset($this->server['players'])){ $this->getServerPlayers(false); }

        $nickname = strtolower($nickname);
        return (isset($this->server['players'][$nickname]));
    }


    /**
     * Sets a workable nickname for other getters to work with. Before setting
     * anything, checks if the player is online.
     *
     * @param      string     $nickname  The nickname
     * @throws     Exception  Throws exception in case the player is not online.
     * @return     Object     Returns $this object to make it chainable.
     *                        Will return False if player is not online.
     */
    public function player($nickname=''){

        if(!$this->isOnline($nickname)){
            throw new Exception('07 This player is not online');
            return false;
        }

        $this->chain('p');
        $this->context['player'] = strtolower($nickname);

        return $this;
    }


    /**
     * Gets the player's score. Player must be previously set
     * via $this->player().
     *
     * @return     string  Player's score or false if player() not set.
     */
    public function score(){
        if(!$this->checkPlayer() || !$this->chained('p')){ return false; }

        $this->unchain('p');
        return $this->server['players'][$this->context['player']]['score'];
    }


    /**
     * Gets the player's id. Player must be previously set
     * via $this->player(). If the list is not the detailed one,
     * will request it.
     *
     * @return     string  Player's id or false if player() not set.
     */
    public function id(){
        if(!$this->checkPlayer() || !$this->chained('p')){ return false; }

        if($this->context['playerType'] === 'list'){
            $this->getServerPlayers(true);
        }

        $this->unchain('p');
        return $this->server['players'][$this->context['player']]['id'];
    }


    /**
     * Gets the player's ping. Player must be previously set
     * via $this->player(). If the list is not the detailed one,
     * will request it.
     *
     * @return     string  Player's ping or false if player() not set.
     */
    public function ping(){
        if(!$this->checkPlayer() || !$this->chained('p')){ return false; }

        if($this->context['playerType'] === 'list'){
            $this->getServerPlayers(true);
        }

        $this->unchain('p');
        return $this->server['players'][$this->context['player']]['ping'];
    }


    /**
     * Gets the player's nickname. Player must be previously set
     * via $this->player().
     *
     * @return     string  Player's nickname or false if player() not set.
     */
    public function nickname(){
        if(!$this->checkPlayer() || !$this->chained('p')){ return false; }

        $this->unchain('p');
        return $this->server['players'][$this->context['player']]['nickname'];
    }


    /**
     * Forces a request on server information. Usefull if running in constant
     * CLI mode and you need to update some of the information.
     *
     * Allowed: basic, detailed, players, detailedPlayers and ping.
     *
     * @param      string  $request  The type of update
     * @return     boolean Returns either true or false.
     */
    public function updateInfo($request='basic'){
        switch($request){

            case 'basic': default:
                return $this->getServerInfo(false);

            case 'detailed':
                return $this->getServerInfo(true);

            case 'players':
                return $this->getServerPlayers(false);

            case 'detailedPlayers':
                return $this->getServerPlayers(true);

            case 'ping':
                return $this->isSamp();
        }

        return null;
    }


    /**
     * General getter for information about server, and players list.
     * It detects what kind of information it needs to retrieve, and if it
     * doesn't exist, will request only the necessary one.
     *
     * $request may be: hostname, currentPlayers, maxPlayers, gamemode,
     * language, mapname, lagcomp, version, weather, weburl, worldtime,
     * players, detailedPlayers and ping.
     *
     * @param      string  $request  The requested param
     * @return     string||array     Returns either a string or array depending
     *                               on the information to retrieve.
     */
    public function info($request='hostname'){

        switch($request){

            case 'hostname':
            case 'currentPlayers':
            case 'maxPlayers':
            case 'gamemode':
            case 'language':
                if(!isset($this->context['infoType'])){
                    $this->getServerInfo(false);
                }
                break;

            case 'lagcomp':
            case 'mapname':
            case 'version':
            case 'weather':
            case 'weburl':
            case 'worldtime':
                if(!isset($this->context['infoType'])
                   || $this->context['infoType'] === 'short'){
                    $this->getServerInfo(true);
                }
                break;

            case 'players':
                if(!isset($this->context['playerType'])){
                    $this->getServerPlayers(false);
                }
                break;

            case 'detailedPlayers':
                if(!isset($this->context['playerType'])
                   || $this->context['playerType'] === 'list'){
                    $this->getServerPlayers(true);
                }

                $request = 'players';
                break;

            case 'ping':
                if(!isset($this->server['ping'])){ $this->isSamp(); }
                break;

            default: break;
        }

        return (isset($this->server[$request]) ? $this->server[$request] : null);
    }

}