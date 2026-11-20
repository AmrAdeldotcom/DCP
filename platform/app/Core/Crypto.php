<?php
declare(strict_types=1);
final class Crypto {
 private string $key;
 public function __construct(string $file){
  $dir=dirname($file);if(!is_dir($dir))mkdir($dir,0700,true);
  if(!is_file($file)){ $key=random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);file_put_contents($file,base64_encode($key),LOCK_EX);chmod($file,0600);}
  $raw=base64_decode(trim((string)file_get_contents($file)),true);
  if($raw===false||strlen($raw)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)throw new RuntimeException('DCP encryption key is invalid.');
  $this->key=$raw;
 }
 public function encrypt(string $value):string {if($value==='')return ''; $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);return base64_encode($nonce.sodium_crypto_secretbox($value,$nonce,$this->key));}
 public function decrypt(string $value):string {if($value==='')return ''; $raw=base64_decode($value,true);if($raw===false||strlen($raw)<=SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)throw new RuntimeException('Encrypted setting cannot be decrypted.');$nonce=substr($raw,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);$plain=sodium_crypto_secretbox_open(substr($raw,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$nonce,$this->key);if($plain===false)throw new RuntimeException('Encrypted setting authentication failed.');return $plain;}
 public function mask(string $value):string {if($value==='')return ''; $n=strlen($value);return $n<10?str_repeat('•',max(3,$n-2)).substr($value,-2):substr($value,0,5).str_repeat('•',min(18,$n-9)).substr($value,-4);}
}
