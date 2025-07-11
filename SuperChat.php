<?php

/*
__PocketMine Plugin__
name=ChatExtreme
description=ExtremeCraft
version=1.0
author=ExtremeCraft
class=ChatExtreme
apiversion=12
*/


class ChatExtreme implements Plugin{
	private $api, $prefix, $path, $user;
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		$this->api->addHandler("player.join", array($this, "handler"), 5);
		$this->api->addHandler("player.chat", array($this, "handler"), 5);
		$this->api->addHandler("player.death", array($this, "handler"), 5);
		$this->api->addHandler("player.quit", array($this, "handler"), 5);		
		$this->readConfig();
		$this->api->console->register("setprefix", "  --> Set Players Rank.", array($this, "Pref"));
		$this->api->console->register("defprefix", "  --> Set a default Rank.", array($this, "Pref"));
		$this->api->console->register("delprefix", "  --> Delete a Rank.", array($this, "Pref"));
		$this->api->console->register("setnick", "  --> Change player nick.", array($this, "Pref"));
		$this->api->console->register("delnick", "  --> Delete a nick.", array($this, "Pref"));
		$this->api->console->register("mute", "  --> Mute a player.", array($this, "Pref"));
		$this->api->console->register("unmute", "  --> Unmute a player", array($this, "Pref"));
		$this->api->console->register("uce", "  --> Enable Chat on server .", array($this, "Pref"));
		$this->api->console->register("ucd", "  --> Disable chat on server .", array($this, "Pref"));
		//console(FORMAT_GREEN."[✔] Загрузка ChatExtreme прошла успешно!");
		
	}
	
	public function __destruct(){
	}
	
	public function readConfig(){
		$this->path = $this->api->plugin->createConfig($this, array(
			"chat-format" => "➦ {DISPLAYNAME}《{prefix}》: {MESSAGE}",
			"default" => "Player",
			"save-kills" => false,
			"chat" => "enable",
		));
		$this->config = $this->api->plugin->readYAML($this->path."config.yml");
		if(!file_exists($this->path."kills.yml")){
        $c = new Config($this->path."kills.yml", CONFIG_YAML, array());}
        $this->killconfig = $this->api->plugin->readYAML($this->path."kills.yml");
	}

	
	public function Pref($cmd, $args){
	switch($cmd){
	    case "setprefix":
	      $player = $args[0];
	      $pref = $args[1];
	      
	      $this->config['player'][$player]['pref'] =$pref;
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	      
	      $output .= "[✔] Title".$player." successfully changed to : ".$pref." !";
	      $this->api->chat->sendTo(false, "[✔] Your new rank on the server : ".$pref." !", $player);
      break;
	    case "defprefix":
	      $def = $args[0];
	       
	      $this->config['default']=$def;
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	       
	      $output .= "[✔] Standard rank successfully changed to : ".$def." .";
	    break;
	    case "delprefix":
	      $player = $args[0];
	       
	      unset($this->config['player'][$player]['pref']);
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	       
	      $output .= "[✔] Removing rank from ".$player." sucessful !";
	      $this->api->chat->sendTo(false, "[✔] Your rank has been changed to standard .", $player);
	    break;
	    case "setnick":
	      $player = $args[0];
	      $nick = $args[1];
	      
	      $this->config['player'][$player]['nick'] = "~".$nick;
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	      
	      $output .= "[✔] У ".$player." new chat room nickname : ".$nick." !";
	      $this->api->chat->sendTo(false, "[✔] Your new chat nickname : ".$nick." !", $player);
      break;
      case "delnick":
	      $player = $args[0];
	      
	      unset($this->config['player'][$player]['nick']);
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	      
	      $output .= "[✔] Your nickname ".$player."' changed to real !";
	      $this->api->chat->sendTo(false, "[✔] Your chat nickname is back to standard!", $player);
      break;
      case "mute":
	      $player = $args[0];
	      
	      $this->config['player'][$player]['mute'] = true;
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	      
	      $output .= "[✔] Muting ".$player."!";
	      $this->api->chat->sendTo(false, "[✔] You have been muted! ", $player);
      break;
      case "unmute":
	      $player = $args[0];
	      
	      unset($this->config['player'][$player]['mute']);
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	      
	      $output .= "[✔] Unmuted ".$player." Sucessful!";
	      $this->api->chat->sendTo(false, "[✔] You have been unmuted !", $player);
      break;
	  case "uce":
	      $this->config['chat']="enable";
		  $output .= "[✔] Chat Enabled !";
	  break;
	  case "ucd":
	      $this->config['chat']="disable";
		  $output .= "[✘] Chat disabled !";
	  break;
	  }
	  return $output;
	  }
	  
	public function handler(&$data, $event){
		switch($event){
		    case "player.death":
                $player = $this->api->entity->get($data["cause"]);
			    if($player instanceof Entity){
			    $player = $player->name; }
				If(!isset($this->killconfig['player'][$player]['kills'])){
				$this->killconfig['player'][$player]['kills']=1;
				}
				else {
				$this->killconfig['player'][$player]['kills']=$this->killconfig['player'][$player]['kills']+1;
				}
				break;
			case "player.quit":
			If($this->config['save-kills']==true){
			    $this->api->plugin->writeYAML($this->path."kills.yml", $this->killconfig);
				console(FORMAT_GREEN."[UChat] Saved kills!"); }
			    break;
			case "player.chat":
          $player = $data["player"]->username;
		  If(!isset($this->config['player'][$player]['mute']) && $this->config['chat']=="enable")
		  {
		     If(!isset($this->config['player'][$player]['pref'])){
		     $prefix=$this->config['default'];
		     }
		     else{
		     $prefix= $this->config['player'][$player]['pref'];
		     }
		     If(!isset($this->config['player'][$player]['nick'])){
		     $nickname=$player;
		     }
		     else{
		     $nickname=$this->config['player'][$player]['nick'];
		     }
			 If(!isset($this->killconfig['player'][$player]['kills'])){
			 $kills=0;
			 }
			 else {
			 $kills=$this->killconfig['player'][$player]['kills'];
			 }
		    
          $data = array("player" => $data["player"], "message" => str_replace(array("{DISPLAYNAME}", "{MESSAGE}", "{WORLDNAME}", "{prefix}", "{kills}"), array($nickname, $data["message"], $data["player"]->level->getName(), $prefix, $kills), $this->config["chat-format"]));
          if($this->api->handle("UChat.".$event, $data) !== false){
					  $this->api->chat->broadcast($data["message"]);
				 }
				 return false;
		  }
		   elseif(isset($this->config['player'][$player]['mute']))
		   {
		   $this->api->chat->sendTo(false, "[✘] You can't message here!", $player);
		   return false;
		   }
		   else
		   {
		   $this->api->chat->sendTo(false, "[✘] Chat has been disabled in this server!", $player);
		   return false;
		   }
			break;
		}
	}	
}