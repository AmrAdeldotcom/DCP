<?php
declare(strict_types=1);
final class PluginManager {
 private array $plugins=[];
 public function __construct(private string $root){foreach(glob($root.'/*/*/plugin.json')?:[] as $file){$p=json_decode((string)file_get_contents($file),true);if(is_array($p)){$p['_manifest']=$file;$this->plugins[$p['id']??basename(dirname($file))]=$p;}}}
 public function all():array{return array_map(function($p){unset($p['_manifest']);return $p;},array_values($this->plugins));}
 public function byType(string $type):array{return array_values(array_filter($this->plugins,fn($p)=>($p['type']??'')===$type));}
 public function get(string $id):?array{return $this->plugins[$id]??null;}
 public function driver(array $plugin):string{return dirname($plugin['_manifest']).'/driver.php';}
}
