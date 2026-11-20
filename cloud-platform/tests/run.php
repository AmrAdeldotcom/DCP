<?php
declare(strict_types=1);
$root=dirname(__DIR__);$files=['public/index.php','public/site.php','src/Http.php','src/Cloud.php','database/schema.mysql.sql'];$bad=[];foreach($files as $f)if(!is_file($root.'/'.$f))$bad[]='Missing '.$f;foreach(['src/Http.php','src/Cloud.php','public/index.php','public/site.php'] as $f){$out=[];$code=0;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$f).' 2>&1',$out,$code);if($code!==0)$bad[]=implode("\n",$out);}if($bad){fwrite(STDERR,implode("\n",$bad)."\n");exit(1);}echo "DCP Cloud scaffold checks passed.\n";
