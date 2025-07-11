<?php
/*
__PocketMine Plugin__
name=IpUpdater
description=Updates ip on freedns.afraid.org
version=1.0
author=babymusk
class=Ipupdate
apiversion=12,13
*/

class Ipupdate implements Plugin {  
    private $api;  

    public function __construct(ServerAPI $api, $server = false) {  
        $this->api = $api;  
    }  

    public function init() {  
        $this->api->console->register("updateip", "Updateip", [$this, "ipupdate"]);
    }  

    public function ipupdate(){
        $dir = shell_exec("./updateip.sh");
        return $dir;
    }

    public function __destruct() {}  
}  