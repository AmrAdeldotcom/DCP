<?php
$required=['curl','intl','mbstring','openssl','pdo_sqlite','sodium','fileinfo'];
$missing=array_values(array_filter($required,fn($e)=>!extension_loaded($e)));
if(PHP_VERSION_ID<80200){fwrite(STDERR,"DCP requires PHP 8.2+.\n");exit(2);}
if($missing){fwrite(STDERR,"Missing PHP extensions: ".implode(', ',$missing)."\n");exit(3);}
foreach([dirname(__DIR__).'/data',dirname(__DIR__).'/storage'] as $dir){
 if(!is_dir($dir)||!is_writable($dir)){fwrite(STDERR,"DCP folder is not writable: $dir\n");exit(4);}
}
echo "PHP ".PHP_VERSION." and required extensions are available.\n";
