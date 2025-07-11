<?php
/*
__PocketMine Plugin__
 name=InfWorld
 description=Simple infinity world by loading a new world.
 version=24.05.14
 author=EkiFoX
 class=InfWorld
 apiversion=12,13
 */
class InfWorld implements Plugin {
	private $api;
	
	public $coord = array(
		"x" => 255,
		"z" => 255,
		"x1" => 0,
		"z1" => 0
	);
	
	public $maxworld = 200;
	
	public function __construct(ServerAPI $api, $server = false) {
		$this->api = $api;
	}

	public function init() {
		$this->api->addHandler("player.move", array($this, "move"));
	}

	public function __destruct() {}
  
	public function move($data){
		$plobj = $this->api->player->get($data->name);
		if(($data->x == $this->coord["x"]) OR ($data->z == $this->coord["z"])){
			$world = $plobj->level->getName();
			if($world < $this->maxworld){
				$newworld = $world+1;
				if($this->api->level->levelExists($newworld)){
					$lv = $this->server->api->level->get($newworld);
					$plobj->teleport($lv->getSafeSpawn());
					$plobj->sendChat("[InfWorld] You in new world: ".$newworld);
				}else{
					$plobj->sendChat("[InfWorld] Generating new world.");
					$this->api->level->generateLevel($newworld);
				}
			}else{
				$plobj->sendChat("[InfWorld] You in max coord.");
			}
		}
		if(($data->x == $this->coord["x1"]) OR ($data->z == $this->coord["z1"])){
			$world = $plobj->level->getName();
			if(1 < $world){
				$newworld = $world-1;
				if( $this->api->level->levelExists($newworld) ){
					$lv = $this->server->api->level->get($newworld);
					$plobj->teleport($lv->getSafeSpawn());
					$plobj->sendChat("[InfWorld] You in old world: ".$newworld);
				}else{
					$plobj->sendChat("[InfWorld] Generating new world.");
					$this->api->level->generateLevel($newworld);
				}
			}else{
				$plobj->sendChat("[InfWorld] You in max coord.");
			}
		}
		return true;
	}
}
