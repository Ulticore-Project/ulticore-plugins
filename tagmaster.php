<?php

/*
___PocketMine Plugin___
name=tagmaster
description=add tags to game
version=0.3
author=Erick_2012
class=TagMaster
apiversion=10,11,12,12.1
*/

class TagMaster implements Plugin {
    private $api;
    private $path;
    private $tags = array();

    public function __construct(ServerAPI $api, $server = false) {
        $this->api = $api;
    }

    public function init() {
        $this->path = $this->api->plugin->configPath($this);
        $this->loadTags();

        $this->api->addHandler("player.chat", array($this, "whenspeaking"));
                $this->api->console->register("tag", "add the [admin] tag to a player", array($this, "commandTag"), false);
        $this->api->console->register("especial", "Secret command only for those with the [vip] tag", array($this, "Specialcommand"), false);
        
    $this->api->ban->cmdwhitelist("especial");

 }

    public function loadTags() {
        if(!file_exists($this->path . "tags.yml")) {
            file_put_contents($this->path . "tags.yml", "");
        }
        $this->tags = explode("\n", trim(file_get_contents($this->path . "tags.yml")));
    }

    public function saveTags() {
        file_put_contents($this->path . "tags.yml", implode("\n", $this->tags));
    }

    public function commandtag($cmd, $args, $issuer) {
        if(count($args) < 1) return "Usage: /tag vip <player>";

        $alvo = strtolower($args[0]);
        if(!in_array($alvo, $this->tags)) {
            $this->tags[] = $alvo;
            $this->saveTags();
            return "The player $alvo has been tagged [admin].";
        } else {
            return "$alvo already has the tag.";
        }
    }

    public function Specialcommand($cmd, $args, $issuer) {
        if($issuer === "console") return "Players only!";
        $name = strtolower($issuer->username);
        if(in_array($name, $this->tags)) {
            $issuer->sendChat("You executed the secret command!");
        } else {
            $issuer->sendChat("You don't have permission for that!");
        }
    }

    public function whenspeaking($data, $event) {
        $player = $data["player"];
        $msg = $data["message"];
        $name = strtolower($player->username);

        if(in_array($name, $this->tags)) {
            $this->api->chat->broadcast("§c[admin]§f" . $player->username . ": §f" . $msg);
            return false;
        }
  }

    public function __destruct() {}
}
?>