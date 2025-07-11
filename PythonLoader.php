<?php
/*
__PocketMine Plugin__
name=PythonLoader
description=Advanced Python plugin loader with full output support
version=0.1.0
author=babymusk
class=PythonLoader
apiversion=12,13
*/
class PythonLoader implements Plugin {
    
    private $api;
    private $pythonPath;
    private $scripts = [];
    private $config;
    private $messageQueue = [];
    private $tickTask;
    
    const PROTOCOL_VERSION = 1;
    const MAX_RESTARTS = 5;
    const RESTART_DELAY = 10;
    
    public function __construct(ServerAPI $api, $server = false) {
        $this->api = $api;
    }
    
    public function init() {
        $this->loadConfig();
        $this->verifyPython();
        $this->setupDirectory();
        $this->loadPythonScripts();
        
        // Register tick handler for processing I/O
        $this->tickTask = $this->api->schedule(5, [$this, "onTick"], [], true);
        
        // Register commands
        $this->api->console->register("pyloader", "<list|load|unload|restart> [script]", [$this, "commandHandler"]);
        
        console("[PythonLoader] Ready! Detected " . count($this->scripts) . " Python plugins");
    }
    
    private function loadConfig() {
        $defaultConfig = [
            "python-path" => "/usr/bin/python3",
            "script-directory" => "python-plugins",
            "enable-api" => true,
            "debug-mode" => false,
            "max-memory" => 128, // MB
            "autorestart" => true
        ];
        
        $this->config = new Config($this->api->plugin->configPath($this) . "config.yml", CONFIG_YAML, $defaultConfig);
    }
    
    private function verifyPython() {
        $this->pythonPath = $this->config->get("python-path");
        
        // Check Python exists and is correct version
        $output = [];
        $exitCode = null;
        exec("{$this->pythonPath} --version 2>&1", $output, $exitCode);
        
        if ($exitCode !== 0 || !preg_match('/Python 3\.[0-9]+/', implode("\n", $output))) {
            console("[PythonLoader] ERROR: Python 3 not found at '{$this->pythonPath}'");
            console("[PythonLoader] Output: " . implode("\n", $output));
            $this->api->plugin->disable($this);
            throw new Exception("Python 3 requirement not met");
        }
    }
    
    private function setupDirectory() {
        $scriptDir = $this->getScriptDirectory();
        
        if (!file_exists($scriptDir)) {
            if (!mkdir($scriptDir, 0755, true)) {
                console("[PythonLoader] ERROR: Failed to create script directory at '{$scriptDir}'");
                $this->api->plugin->disable($this);
                throw new Exception("Could not create script directory");
            }
            console("[PythonLoader] Created script directory at '{$scriptDir}'");
        }
    }
    
    private function getScriptDirectory() {
        return $this->api->plugin->configPath($this) . $this->config->get("script-directory");
    }
    
    private function loadPythonScripts() {
        $scriptDir = $this->getScriptDirectory();
        $files = scandir($scriptDir);
        
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === "py") {
                $scriptPath = $scriptDir . DIRECTORY_SEPARATOR . $file;
                $scriptName = pathinfo($file, PATHINFO_FILENAME);
                
                $this->scripts[$scriptName] = [
                    "path" => $scriptPath,
                    "process" => null,
                    "pipes" => null,
                    "restarts" => 0,
                    "last_restart" => 0,
                    "buffer" => "",
                    "status" => "stopped",
                    "meta" => $this->readScriptMeta($scriptPath)
                ];
                
                $this->startPythonScript($scriptName);
            }
        }
    }
    
    private function readScriptMeta($path) {
        $meta = [
            "name" => basename($path),
            "version" => "1.0",
            "description" => "",
            "author" => ""
        ];
        
        $content = file_get_contents($path);
        if (preg_match('/^#\s*name:\s*(.+)$/mi', $content, $matches)) {
            $meta["name"] = trim($matches[1]);
        }
        if (preg_match('/^#\s*version:\s*(.+)$/mi', $content, $matches)) {
            $meta["version"] = trim($matches[1]);
        }
        if (preg_match('/^"""([\s\S]*?)"""/m', $content, $matches)) {
            $meta["description"] = trim($matches[1]);
        }
        
        return $meta;
    }
    
    private function startPythonScript($scriptName) {
        if (!isset($this->scripts[$scriptName])) {
            return false;
        }
        
        $script = &$this->scripts[$scriptName];
        
        // Check restart limits
        if ($script["restarts"] >= self::MAX_RESTARTS) {
            console("[PythonLoader] Script '{$scriptName}' exceeded maximum restart attempts");
            $script["status"] = "crashed";
            return false;
        }
        
        // Check restart delay
        if (time() - $script["last_restart"] < self::RESTART_DELAY) {
            return false;
        }
        
        $descriptorSpec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];
        
        $cmd = escapeshellcmd($this->pythonPath) . " " . escapeshellarg($script["path"]);
        
        $process = proc_open($cmd, $descriptorSpec, $pipes, null, null, [
            "bypass_shell" => true
        ]);
        
        if (is_resource($process)) {
            $script["process"] = $process;
            $script["pipes"] = $pipes;
            $script["last_restart"] = time();
            $script["restarts"]++;
            $script["status"] = "running";
            
            // Set non-blocking mode
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            
            console("[PythonLoader] Started script '{$scriptName}' (v{$script["meta"]["version"]})");
            return true;
        }
        
        console("[PythonLoader] Failed to start script '{$scriptName}'");
        $script["status"] = "failed";
        return false;
    }
    
    public function onTick() {
        foreach ($this->scripts as $scriptName => &$script) {
            if (!is_resource($script["process"])) {
                if ($script["status"] === "running" && $this->config->get("autorestart")) {
                    $this->startPythonScript($scriptName);
                }
                continue;
            }
            
            // Check process status
            $status = proc_get_status($script["process"]);
            if (!$status["running"]) {
                $exitCode = proc_close($script["process"]);
                $script["status"] = ($exitCode === 0) ? "stopped" : "crashed";
                console("[PythonLoader] Script '{$scriptName}' exited with code {$exitCode}");
                
                if ($this->config->get("autorestart")) {
                    $this->startPythonScript($scriptName);
                }
                continue;
            }
            
            // Process output
            $this->processScriptOutput($scriptName);
            
            // Process input queue
            $this->processInputQueue($scriptName);
        }
    }
    
    private function processScriptOutput($scriptName) {
        $script = &$this->scripts[$scriptName];
        
        // Read stdout
        $stdout = stream_get_contents($script["pipes"][1]);
        if ($stdout !== false && $stdout !== "") {
            $lines = explode("\n", $script["buffer"] . $stdout);
            $script["buffer"] = array_pop($lines); // Save incomplete line
            
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== "") {
                    $this->handleScriptOutput($scriptName, $line, "stdout");
                }
            }
        }
        
        // Read stderr
        $stderr = stream_get_contents($script["pipes"][2]);
        if ($stderr !== false && $stderr !== "") {
            foreach (explode("\n", trim($stderr)) as $line) {
                if ($line !== "") {
                    $this->handleScriptOutput($scriptName, $line, "stderr");
                }
            }
        }
    }
    
    private function handleScriptOutput($scriptName, $line, $type) {
        if ($this->config->get("debug-mode")) {
            console("[Python:{$scriptName}:{$type}] {$line}");
        }
        
        try {
            // Attempt to parse JSON output
            $data = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->handleProtocolMessage($scriptName, $data);
                return;
            }
        } catch (Exception $e) {
            // Not JSON, treat as raw output
        }
        
        // Default output handling
        switch ($type) {
            case "stdout":
                console("[{$scriptName}] {$line}");
                break;
            case "stderr":
                console("[{$scriptName}-ERROR] {$line}");
                break;
        }
    }
    
    private function handleProtocolMessage($scriptName, $data) {
        if (!isset($data["type"])) {
            return;
        }
        
        $script = $this->scripts[$scriptName];
        
        switch ($data["type"]) {
            case "log":
                $level = strtoupper($data["level"] ?? "INFO");
                console("[{$scriptName}-{$level}] {$data["message"]}");
                break;
                
            case "command":
                $this->handleScriptCommand($scriptName, $data);
                break;
                
            case "event":
                $this->handleScriptEvent($scriptName, $data);
                break;
                
            case "response":
                if (isset($this->messageQueue[$data["id"]])) {
                    $callback = $this->messageQueue[$data["id"]];
                    unset($this->messageQueue[$data["id"]]);
                    $callback($data["response"]);
                }
                break;
        }
    }
    
    private function handleScriptCommand($scriptName, $data) {
        $command = $data["command"] ?? "";
        $args = $data["args"] ?? [];
        
        switch ($command) {
            case "broadcast":
                $this->api->chat->broadcast($args[0] ?? "Hello from Python!");
                break;
                
            case "get_players":
                $players = array_map(function($p) { return $p->username; }, $this->api->player->getAll());
                $this->sendToScript($scriptName, [
                    "type" => "response",
                    "id" => $data["id"],
                    "response" => $players
                ]);
                break;
        }
    }
    
    private function handleScriptEvent($scriptName, $data) {
        // Handle custom events from Python
    }
    
    public function sendToScript($scriptName, $data, $callback = null) {
        if (!isset($this->scripts[$scriptName])) {
            return false;
        }
        
        $script = $this->scripts[$scriptName];
        
        if (!is_resource($script["process"])) {
            return false;
        }
        
        $jsonData = json_encode($data) . "\n";
        
        if (isset($data["id"]) && $callback !== null) {
            $this->messageQueue[$data["id"]] = $callback;
        }
        
        return fwrite($script["pipes"][0], $jsonData) !== false;
    }
    
    private function processInputQueue($scriptName) {
        // Process any queued messages for this script
    }
    
    public function commandHandler($cmd, $args, $issuer, $alias) {
        if (count($args) < 1) {
            return "Usage: /pyloader <list|load|unload|restart|info> [script]";
        }
        
        $subCmd = strtolower(array_shift($args));
        
        switch ($subCmd) {
            case "list":
                $list = [];
                foreach ($this->scripts as $name => $script) {
                    $list[] = "{$name} ({$script["meta"]["version"]}) - {$script["status"]}";
                }
                return "Loaded scripts:\n" . implode("\n", $list);
                
            case "load":
                if (count($args) < 1) {
                    return "Usage: /pyloader load <script.py>";
                }
                return $this->loadScript($args[0]);
                
            case "unload":
                if (count($args) < 1) {
                    return "Usage: /pyloader unload <script>";
                }
                return $this->unloadScript($args[0]);
                
            case "restart":
                if (count($args) < 1) {
                    return "Usage: /pyloader restart <script>";
                }
                return $this->restartScript($args[0]);
                
            case "info":
                if (count($args) < 1) {
                    return "Usage: /pyloader info <script>";
                }
                return $this->scriptInfo($args[0]);
                
            default:
                return "Unknown subcommand. Use list, load, unload, restart, or info";
        }
    }
    
    private function loadScript($filename) {
        $scriptPath = $this->getScriptDirectory() . DIRECTORY_SEPARATOR . $filename;
        
        if (!file_exists($scriptPath)) {
            return "Script file '{$filename}' not found";
        }
        
        $scriptName = pathinfo($filename, PATHINFO_FILENAME);
        
        if (isset($this->scripts[$scriptName])) {
            return "Script '{$scriptName}' is already loaded";
        }
        
        $this->scripts[$scriptName] = [
            "path" => $scriptPath,
            "process" => null,
            "pipes" => null,
            "restarts" => 0,
            "last_restart" => 0,
            "buffer" => "",
            "status" => "stopped",
            "meta" => $this->readScriptMeta($scriptPath)
        ];
        
        if ($this->startPythonScript($scriptName)) {
            return "Successfully loaded script '{$scriptName}'";
        }
        
        unset($this->scripts[$scriptName]);
        return "Failed to load script '{$scriptName}'";
    }
    
    private function unloadScript($scriptName) {
        if (!isset($this->scripts[$scriptName])) {
            return "Script '{$scriptName}' is not loaded";
        }
        
        $script = $this->scripts[$scriptName];
        
        if (is_resource($script["process"])) {
            // Send shutdown command
            $this->sendToScript($scriptName, ["type" => "command", "command" => "shutdown"]);
            
            // Wait a bit for clean shutdown
            usleep(100000); // 100ms
            
            // Force close if still running
            if (proc_get_status($script["process"])["running"]) {
                proc_terminate($script["process"]);
            }
            
            proc_close($script["process"]);
        }
        
        unset($this->scripts[$scriptName]);
        return "Unloaded script '{$scriptName}'";
    }
    
    private function restartScript($scriptName) {
        if (!isset($this->scripts[$scriptName])) {
            return "Script '{$scriptName}' is not loaded";
        }
        
        $this->unloadScript($scriptName);
        $this->loadScript($this->scripts[$scriptName]["path"]);
        return "Restarted script '{$scriptName}'";
    }
    
    private function scriptInfo($scriptName) {
        if (!isset($this->scripts[$scriptName])) {
            return "Script '{$scriptName}' is not loaded";
        }
        
        $script = $this->scripts[$scriptName];
        $info = [
            "Name: " . $script["meta"]["name"],
            "Version: " . $script["meta"]["version"],
            "Status: " . $script["status"],
            "Path: " . $script["path"],
            "Restarts: " . $script["restarts"],
            "Last restart: " . date("Y-m-d H:i:s", $script["last_restart"])
        ];
        
        if (!empty($script["meta"]["description"])) {
            $info[] = "Description: " . $script["meta"]["description"];
        }
        
        if (!empty($script["meta"]["author"])) {
            $info[] = "Author: " . $script["meta"]["author"];
        }
        
        return implode("\n", $info);
    }
    
    public function __destruct() {
        // Cleanup all processes
        foreach ($this->scripts as $scriptName => $script) {
            if (is_resource($script["process"])) {
                $this->sendToScript($scriptName, ["type" => "command", "command" => "shutdown"]);
                usleep(50000); // 50ms grace period
                proc_terminate($script["process"]);
                proc_close($script["process"]);
            }
        }
        
        // // Cancel tick task
        // if ($this->tickTask !== null) {
        //     $this->api->cancelSchedule($this->tickTask);
        // }
    }
}