<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only.\n");exit(2);}
$id=trim((string)($argv[1]??''));if(!preg_match('/^mcp_[a-f0-9]{20}$/',$id)){fwrite(STDERR,"Usage: php platform/bin/mcp-token-revoke.php <mcp-token-id>\n");exit(2);}
$root=dirname(__DIR__);$config=require $root.'/config/app.php';require $root.'/app/Core/Database.php';$db=Database::connect($config);Database::migrate($db);$q=$db->prepare('UPDATE mcp_tokens SET revoked_at=? WHERE id=? AND revoked_at IS NULL');$q->execute([date(DATE_ATOM),$id]);if($q->rowCount()!==1){fwrite(STDERR,"MCP token not found or already revoked.\n");exit(1);}echo "MCP token revoked: {$id}\n";
