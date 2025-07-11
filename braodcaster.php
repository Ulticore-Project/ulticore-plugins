<?php
/*
name=AutoBroadcaster
version=1.0
author=babymusk
class=AutoBroadcaster
apiversion=12,13
*/

class AutoBroadcaster implements Plugin {
    private $api;
    private $server;
    private $enabled = false;
    private $timer;
    const message = ", Join the Discord! https://discord.gg/cRhH2Vpzrq";
    
    public function __construct(ServerAPI $api, $server = false) {
        $this->api = $api;
        $this->server = ServerAPI::request();
    }
    
    public function init() {
        $this->enabled = true;
        $this->api->console->register("broadcast", "Send message to all players", [$this, "commandHandler"]);
        $this->startMessageTimer();
    }
    
    public function commandHandler($cmd, $params, $issuer, $alias) {
        $this->sendToAllPlayers();
        return "Sent message to all online players";
    }
    
    private function startMessageTimer() {
        if(!$this->enabled) return;
        
        $this->sendToAllPlayers();
        
        // Schedule next message in five minutes (12,000 ticks)
        $this->timer = $this->api->schedule(300*20, function() {$this->startMessageTimer();}, []);
    }
    
    private function sendToAllPlayers() {
        $players = $this->api->player->getAll(); // Get all online players
        
        
        foreach($players as $player) {
            if($player->connected) { // Check if player is still connected
                $message = "[Server] Hey ".$player. self::message;
                $player->sendChat($message);
            }
        }
    }
    
    public function __destruct() {

    }
}