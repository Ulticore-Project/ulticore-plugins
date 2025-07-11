<?php

/*
  __PocketMine Plugin__
  name=TreeChopper
  description=TreeChopper
  version=1.1v1
  author=tschrock(modifed by gamehero)/tema1d.
  class=TreeChopper
  apiversion=12,12.1
 */

class TreeChopper implements Plugin {

    private $api, $config, $server;
    private $treeChopperEnabled = true;

    public function __construct(ServerAPI $api, $server = false) {
        $this->api = $api;
        $this->server = $server;
    }

    public function init() {

        $this->config = new Config($this->api->plugin->configPath($this) . "config.yml", CONFIG_YAML, array(
            "ChoppableBlocks" => array(
                17,
                18,
            ),
            "UseableTools" => array(
                258,
                271,
                275,
                279,
                286,
            ),
        ));

        $this->api->addhandler("player.block.break", array($this, "eventHandler"));
        $this->api->console->register("tc", "Switch on/off TreeChopper.", array($this, "commandHandler"));
        $this->api->ban->cmdWhitelist("tc");
        $configPath = $this->api->plugin->configPath($this) . "players_configs";
        if (!is_dir($configPath)) {
        @mkdir($configPath);
        }
    }
	private function destroyLeavesNearbyLog(Block $pos, $distance,$type, $target, $player, $item){
		$index = $pos->x.":". $pos->y.":". $pos->z;
		if($pos instanceof LeavesBlock){ //type doesn't matter
			$drops = $pos->getDrops($item, $player);
			foreach($drops as $drop){ //yeah, add even more useless loops(or useful), we dont have enough of them.
				$this->api->entity->drop(new Position($target->x + 0.5, $target->y, $target->z + 0.5, $target->level), BlockAPI::getItem($drop[0],$drop[1],$drop[2]));
			}
			$pos->level->setBlock($pos, new AirBlock(), false, false, true);
			if($type === 1) return true;
		}else{
			
		}

		if($distance <= 4){
			foreach(array(2,3,4,5) as $side){
				$this->destroyLeavesNearbyLog($pos->getSide($side), $distance + 1,$distance === 4 ? 1 : 0, $target, $player, $item); //recursion i guess?
			}
		}
	}
    public function eventHandler($data, $event) {
    $player = $data["player"];
    if (!$this->isTreeChopperEnabledForPlayer($player)) {
        return;
    }
        switch (strtolower($event)) {
            case "player.block.break":
                if (in_array($data["item"]->getID(), $this->config->get("UseableTools")) && in_array($data["target"]->getID(), $this->config->get("ChoppableBlocks"))) {
                    $block1 = $data["target"];
					if($block1 instanceof LeavesBlock){
						break;
					}
                    //console($block1->getName() . ">" . $block1->x . ":" . $block1->y . ":" . $block1->z . ":" . $block1->level->getName());

                    $block = $block1->getSide(1);

                    //console($block->getName() . ">" . $block->x . ":" . $block->y . ":" . $block->z . ":" . $block->level->getName());

                    if (in_array($block->getID(), $this->config->get("ChoppableBlocks"))) {

                        $player = $data["player"];
                        $target = $block;
                        $item = $data["item"];




                        if ($this->api->dhandle("player.block.touch", array("type" => "break", "player" => $player, "target" => $target, "item" => $item)) === false) {
                            if ($this->api->dhandle("player.block.break.bypass", array("player" => $player, "target" => $target, "item" => $item)) !== true) {
                                return $this->api->block->cancelAction($target, $player, false);
                            }
                        }

                        if ((!$target->isBreakable($item, $player) and $this->api->dhandle("player.block.break.invalid", array("player" => $player, "target" => $target, "item" => $item)) !== true) or ($player->gamemode & 0x02) === 0x02) {
                            if ($this->api->dhandle("player.block.break.bypass", array("player" => $player, "target" => $target, "item" => $item)) !== true) {
                                return $this->api->block->cancelAction($target, $player, false);
                            }
                        }
                        
                        if ($this->api->dhandle("player.block.break", array("player" => $player, "target" => $target, "item" => $item)) !== false) {
                            $drops = $target->getDrops($item, $player);
							$this->destroyLeavesNearbyLog($target, 0, 0, $target, $player, $item);
                            if ($target->onBreak($item, $player) === false) {
                                //return $this->api->block->cancelAction($target, $player, false);
                            }
                            if (($player->gamemode & 0x01) === 0 and $item->useOn($target) and $item->getMetadata() >= $item->getMaxDurability()) {
                                $player->setSlot($player->slot, new Item(AIR, 0, 0), true);
                            }
                        } else {
                            return $this->api->block->cancelAction($target, $player, false);
                        }


                        if (($player->gamemode & 0x01) === 0x00 and count($drops) > 0) {
                            foreach ($drops as $drop) {
                                $this->api->entity->drop(new Position($target->x + 0.5, $target->y, $target->z + 0.5, $target->level), BlockAPI::getItem($drop[0] & 0xFFFF, $drop[1] & 0xFFFF, $drop[2]));
                            }
                        }
                    }
					
                }
                break;
        }
    }
    
    private function isTreeChopperEnabledForPlayer(Player $player) {
    $playerName = $player->username;
    $configPath = $this->api->plugin->configPath($this) . "players_configs/" . strtolower($playerName) . ".yml";
    $playerConfig = new Config($configPath, CONFIG_YAML, array());
    
    if (!$playerConfig->exists("treeChopperEnabled")) {
        $playerConfig->set("treeChopperEnabled", true);
        $playerConfig->save();
    }

    return $playerConfig->get("treeChopperEnabled");
}

    public function commandHandler($cmd, $args, $issuer) {
        if (!isset($args[0])) {
            return "TreeChopper - fast cutting of trees.\n/tc on - enable TreeChopper.\n/tc off - disable TreeChopper.";
        }
        if ($args[0] === "on" || $args[0] === "off") {
            $playerName = $issuer->username;
            $configPath = $this->api->plugin->configPath($this) . "players_configs/" . strtolower($playerName) . ".yml";
            $playerConfig = new Config($configPath, CONFIG_YAML, array());
            $playerConfig->set("treeChopperEnabled", ($args[0] === "on"));
            $playerConfig->save();

            return "TreeChopper " . ($args[0] === "on" ? "enabled" : "disabled") . " for $playerName.";
        } else {
            return "Invalid argument. Usage:\n/tc on - enable TreeChopper.\n/tc off - disable TreeChopper.";
    }
}
    public function __destruct() {
        
    }

}

