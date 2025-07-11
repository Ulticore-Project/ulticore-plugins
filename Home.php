<?php

/*
__PocketMine Plugin__
name=HomeExtreme
description=HomeExtreme
version=1.0.1
author=ExtremeCraft
class=HomeExtreme
apiversion=12
*/

class HomeExtreme implements Plugin{
    private $api, $session, $path, $config;

    public function __construct(ServerAPI $api, $server =false){
        $this->api =$api;
        $this->session =array();
    }

    public function init(){
        $this->homeFile =new Config($this->api->plugin->configPath($this)."homes.yml", CONFIG_YAML, array());
        $this->api->console->register("home", " --> Teleport to your home", array($this, "command"));
        $this->api->console->register("sethome", " --> Add home", array($this, "command"));
        $this->api->console->register("delhome", " --> Removes your home", array($this, "command"));
        $this->api->ban->cmdWhitelist("sethome");
        $this->api->ban->cmdWhitelist("home");
    }
    public function __destruct(){
        $this->homeFile->save();
    }
    public function command($cmd, $params, $issuer, $alias){
        switch($cmd){
            case "home": if(!($issuer instanceof Player)){
            $output .= "[Home] Please run this command in-game.
            ";
            break;
        }
        else if(!($this->homeFile->exists($issuer->iusername))){
            $output .= "[Home] For teleport needs /sethome .\nHint : /sethome - add your home.";
        }
        else {
            $name =$issuer->iusername;
            $d =$this->homeFile->get($issuer->iusername);
            $x =$d["x"];
            $y =$d["y"] ;
            $z =$d["z"] ;
            if($this->api->player->tppos($name, $x, $y, $z)){
            }
            else{
                $output .= "[Home] Teleportation error.
            ";
            }
        }
        break;
        case "delhome": if(!isset($params[0])){
            $output .= "[Home] Use : /delhome <nickname>";
        }
    else if(!($this->homeFile->exists($params[0]))){
    $output .= "[Home] Your home not found.";
    }
    else {
    $this->homeFile->remove($params[0]);
    $output .= "[Home] $params[0]'s home was removed.";
    }
    break;
    case "sethome":if(!($issuer instanceof Player)){
    $output .= "[Home] Please run this command in-game.
    ";
    break;
    }
    else {
    $player =$issuer;
    $data =array( "x" => $issuer->entity->x, "y" => $issuer->entity->y, "z" => $issuer->entity->z, );
    $this->homeFile->set($player->iusername, $data);
    $this->homeFile->save();
    $output .="[Home] Your home has been successfully seted .\nHint : use /home to teleport to home.";
    }
    break;
    }
    return $output;
    }
    }

?>