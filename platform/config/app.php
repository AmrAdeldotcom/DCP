<?php
declare(strict_types=1);
return [
 'name'=>'DCP',
 'version'=>'1.0.0-alpha.3',
 'bind'=>'127.0.0.1',
 'port'=>18765,
 'database'=>dirname(__DIR__).'/data/dcp.sqlite',
 'storage'=>dirname(__DIR__).'/storage',
 'key_file'=>dirname(__DIR__).'/storage/keys/master.key',
 'session_name'=>'DCPSESSID',
 'timezone'=>'Africa/Cairo',
 'default_locale'=>'en',
 'supported_locales'=>['en','ar','nl','es','pt','fr','de','zh','ja'],
 'ollama_url'=>'http://127.0.0.1:11434',
 'terminal_root'=>dirname(__DIR__,2),
 'max_terminal_seconds'=>8,
];
