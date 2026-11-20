<?php
declare(strict_types=1);
return new class implements ProviderAdapter {
 private function call(string $url,array $payload=[],bool $post=false):array{$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>120,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);if($post){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_HTTPHEADER,['Content-Type: application/json']);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE));}$raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);if($raw===false)throw new RuntimeException('Ollama connection failed: '.$err);$d=json_decode($raw,true)?:[];if($code<200||$code>=300)throw new RuntimeException('Ollama HTTP '.$code);return $d;}
 public function models(array $p):array{$d=$this->call('http://127.0.0.1:11434/api/tags');return array_map(fn($m)=>['id'=>$m['name'],'name'=>$m['name']],$d['models']??[]);}
 public function chat(array $p,string $model,array $messages):string{$d=$this->call('http://127.0.0.1:11434/api/chat',['model'=>$model,'messages'=>$messages,'stream'=>false],true);return (string)($d['message']['content']??'');}
};
