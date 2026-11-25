<?php
declare(strict_types=1);

interface CloudTerminalCloudClient {
    public function deviceIdentity():array;
    public function registerTerminalDeviceSigningKey(string $publicKey):array;
    public function dispatchRemoteTerminalRequest(string $requestId):array;
    public function verifyTerminalDispatch(array $envelope,string $signature):bool;
    public function submitRemoteTerminalResult(string $requestId,array $signedResult,string $signature):array;
}

final class CloudService implements CloudTerminalCloudClient {
    private const BASE_URL = 'https://dcp.amradel.com/api/v1';
    private string $baseUrl;
    public function __construct(private PDO $db, private Crypto $crypto, ?string $baseUrl = null) {
        $this->baseUrl = rtrim($baseUrl ?? self::BASE_URL, '/');
        $scheme = strtolower((string)parse_url($this->baseUrl, PHP_URL_SCHEME));
        $host = strtolower(trim((string)parse_url($this->baseUrl, PHP_URL_HOST), '[]'));
        $loopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !($scheme === 'http' && $loopback)) {
            throw new InvalidArgumentException('DCP Cloud must use HTTPS; plain HTTP is allowed only for loopback tests.');
        }
    }

    private function value(string $key): string {
        $q=$this->db->prepare('SELECT value FROM settings WHERE key=?');$q->execute([$key]);return (string)($q->fetchColumn()?:'');
    }
    private function save(string $key,string $value):void {
        $q=$this->db->prepare('INSERT INTO settings(key,value,updated_at) VALUES(?,?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=excluded.updated_at');
        $q->execute([$key,$value,date(DATE_ATOM)]);
    }
    private function secret(string $key):string {$v=$this->value($key);return $v===''?'':$this->crypto->decrypt($v);}
    private function saveSecret(string $key,string $value):void {$this->save($key,$value===''?'':$this->crypto->encrypt($value));}
    private function request(string $method,string $path,?array $body=null,string $token=''):array {
        $ch=curl_init($this->baseUrl.$path);if($ch===false)throw new RuntimeException('Could not initialize the DCP Cloud connection.');
        $headers=['Accept: application/json'];if($token!=='')$headers[]='Authorization: Bearer '.$token;if($body!==null)$headers[]='Content-Type: application/json';
        $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>18,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2];
        if($body!==null)$opts[CURLOPT_POSTFIELDS]=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
        if($raw===false)throw new RuntimeException('DCP Cloud is unreachable: '.($err?:'network error'));
        $data=json_decode((string)$raw,true);if(!is_array($data))throw new RuntimeException('DCP Cloud returned an invalid response (HTTP '.$status.').');
        if($status===401)throw new RuntimeException('DCP Cloud credentials expired or were revoked. Reconnect this device.');
        if($status<200||$status>=300)throw new RuntimeException('DCP Cloud: '.(string)($data['error']??$data['message']??('HTTP '.$status)));
        return $data;
    }
    private function ensureDevice():array {
        $id=$this->value('cloud.device_id');$token=$this->secret('cloud.device_token_enc');
        if($id!==''&&strlen($token)>=40)return ['device_id'=>$id,'device_token'=>$token];
        $result=$this->request('POST','/devices/anonymous/register',[
            'label'=>mb_substr(gethostname()?:'DCP Device',0,120),
            'platform'=>mb_substr(PHP_OS_FAMILY.' '.php_uname('m'),0,40)
        ]);
        $id=(string)($result['device_id']??'');$token=(string)($result['device_token']??'');
        if($id===''||strlen($token)<40)throw new RuntimeException('DCP Cloud did not issue valid device credentials.');
        $this->save('cloud.device_id',$id);$this->saveSecret('cloud.device_token_enc',$token);
        $accountToken=$this->secret('cloud.user_token_enc');if($accountToken!=='')$this->linkAccount($accountToken,$token);
        return ['device_id'=>$id,'device_token'=>$token];
    }
    private function linkAccount(string $userToken,string $deviceToken):void {
        if($userToken==='')return;
        $this->request('POST','/devices/link-account',['account_token'=>$userToken],$deviceToken);
    }
    private function attachAccount(string $email,string $userToken,string $deviceToken):void {
        $current=strtolower($this->value('cloud.account_email'));
        if($current!==''&&$current!==strtolower($email))$this->request('POST','/devices/unlink-account',[],$deviceToken);
        $this->linkAccount($userToken,$deviceToken);
        $this->save('cloud.account_email',$email);
        $this->saveSecret('cloud.user_token_enc',$userToken);
    }
    public function register(string $email,string $password,string $displayName):array {
        $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid DCP Cloud email address.');
        if(strlen($password)<12)throw new InvalidArgumentException('DCP Cloud passwords must contain at least 12 characters.');
        $result=$this->request('POST','/register',['email'=>$email,'password'=>$password,'display_name'=>trim($displayName)!==''?trim($displayName):'DCP User']);
        $token=(string)($result['access_token']??'');if(strlen($token)<40)throw new RuntimeException('DCP Cloud did not issue a valid account token.');
        $device=$this->ensureDevice();$this->attachAccount($email,$token,$device['device_token']);
        return $this->status();
    }
    public function login(string $email,string $password):array {
        $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$password==='')throw new InvalidArgumentException('Enter your DCP Cloud email and password.');
        $result=$this->request('POST','/login',['email'=>$email,'password'=>$password]);$token=(string)($result['access_token']??'');
        if(strlen($token)<40)throw new RuntimeException('DCP Cloud did not issue a valid account token.');
        $device=$this->ensureDevice();$this->attachAccount($email,$token,$device['device_token']);
        return $this->status();
    }
    public function status():array {
        try {
            $device=$this->ensureDevice();
            $this->request('POST','/devices/heartbeat',[],$device['device_token']);
            $paired=$this->request('GET','/paired-devices',null,$device['device_token']);
            $requests=$this->request('GET','/pairing-requests',null,$device['device_token']);
            $userToken=$this->secret('cloud.user_token_enc');
            return ['configured'=>true,'connected'=>true,'account_linked'=>$userToken!=='','account'=>$this->value('cloud.account_email'),'device_id'=>$device['device_id'],'devices'=>array_values($paired['devices']??[]),'pairing_requests'=>array_values($requests['requests']??[]),'message'=>'DCP Cloud device identity and heartbeat verified.'];
        } catch(Throwable $e) {
            return ['configured'=>$this->value('cloud.device_id')!=='','connected'=>false,'account_linked'=>$this->secret('cloud.user_token_enc')!=='','account'=>$this->value('cloud.account_email'),'device_id'=>$this->value('cloud.device_id'),'devices'=>[],'pairing_requests'=>[],'message'=>$e->getMessage()];
        }
    }
    public function logout():array {
        $deviceToken=$this->secret('cloud.device_token_enc');
        if($deviceToken!=='')$this->request('POST','/devices/unlink-account',[],$deviceToken);
        $this->save('cloud.account_email','');$this->saveSecret('cloud.user_token_enc','');
        return ['ok'=>true,'configured'=>true,'message'=>'DCP Cloud account signed out. This device identity and its approved pairings remain registered.'];
    }
    public function createPairingCode():array {
        $device=$this->ensureDevice();return $this->request('POST','/pairing-codes',[],$device['device_token']);
    }
    public function requestPairing(string $code):array {
        $device=$this->ensureDevice();$code=strtoupper(preg_replace('/[^A-Z0-9-]/','',trim($code))??'');
        if($code==='')throw new InvalidArgumentException('Enter the pairing code from the other DCP device.');
        return $this->request('POST','/pairing-requests',['code'=>$code],$device['device_token']);
    }
    public function decidePairing(string $requestId,bool $accept,array $scopes):array {
        $device=$this->ensureDevice();if(!preg_match('/^[a-f0-9-]{36}$/i',$requestId))throw new InvalidArgumentException('Invalid pairing request.');
        $allowed=['presence','signaling','terminal.request'];$scopes=array_values(array_unique(array_filter($scopes,fn($s)=>in_array($s,$allowed,true))));
        if($accept&&$scopes===[])throw new InvalidArgumentException('Select at least one permission before approving.');
        return $this->request('POST','/pairing-requests/'.$requestId.'/decision',['accept'=>$accept,'scopes'=>$scopes],$device['device_token']);
    }
    public function requestRemoteTerminal(string $targetDevice,string $command,string $cwd,string $shell,int $timeoutSeconds):array {
        $device=$this->ensureDevice();if(!preg_match('/^[a-f0-9-]{36}$/i',$targetDevice)||$targetDevice===$device['device_id'])throw new InvalidArgumentException('Choose a different paired target device.');
        if(trim($command)===''||strlen($command)>12000||strlen($cwd)>2048)throw new InvalidArgumentException('Command or working directory is outside the supported bounds.');
        if(!in_array($shell,['default','bash','zsh','sh','cmd','powershell'],true))throw new InvalidArgumentException('Unsupported target shell.');
        $timeoutSeconds=max(1,min(120,$timeoutSeconds));
        return $this->request('POST','/remote-terminal-requests',['target_device'=>$targetDevice,'command'=>$command,'cwd'=>$cwd,'shell'=>$shell,'timeout_seconds'=>$timeoutSeconds],$device['device_token']);
    }
    public function remoteTerminalInbox():array {
        $device=$this->ensureDevice();return $this->request('GET','/remote-terminal-requests/inbox',null,$device['device_token']);
    }
    public function decideRemoteTerminalRequest(string $requestId,bool $accept):array {
        $device=$this->ensureDevice();if(!preg_match('/^[a-f0-9-]{36}$/i',$requestId))throw new InvalidArgumentException('Invalid remote terminal request.');
        return $this->request('POST','/remote-terminal-requests/'.$requestId.'/decision',['accept'=>$accept],$device['device_token']);
    }
    public function deviceIdentity():array { return $this->ensureDevice(); }
    public function registerTerminalDeviceSigningKey(string $publicKey):array {
        $device=$this->ensureDevice();$raw=base64_decode($publicKey,true);if($raw===false||strlen($raw)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES)throw new InvalidArgumentException('Local terminal signing public key is invalid.');
        return $this->request('POST','/device/signing-key',['public_key'=>$publicKey],$device['device_token']);
    }
    public function terminalCloudSigningPublicKey():string {
        $remote=$this->request('GET','/signing-key');$public=base64_decode((string)($remote['public_key']??''),true);$keyId=(string)($remote['key_id']??'');
        if($public===false||strlen($public)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES||!hash_equals(hash('sha256',$public),$keyId))throw new RuntimeException('DCP Cloud terminal signing identity is invalid.');
        $pinnedId=$this->value('cloud.terminal_signing_key_id');$pinnedKey=$this->value('cloud.terminal_signing_public_key');
        if($pinnedId!==''&&(!hash_equals($pinnedId,$keyId)||!hash_equals($pinnedKey,(string)$remote['public_key'])))throw new RuntimeException('DCP Cloud signing key changed. Explicit trust rotation is required; remote execution is blocked.');
        if($pinnedId===''){$this->save('cloud.terminal_signing_key_id',$keyId);$this->save('cloud.terminal_signing_public_key',(string)$remote['public_key']);}
        return $public;
    }
    public function verifyTerminalDispatch(array $envelope,string $signature):bool {
        $required=['schema_version','envelope_id','request_id','source_device','issuer','issuer_key_id','target_device_id','action','command','working_directory','shell','timeout_seconds','command_sha256','approval_hash','issued_at','expires_at','nonce'];$keys=array_keys($envelope);sort($keys);sort($required);if($keys!==$required)return false;
        if(($envelope['schema_version']??null)!==1||($envelope['issuer']??'')!=='dcp-cloud'||($envelope['action']??'')!=='terminal.execute'||!is_string($envelope['command']??null)||trim($envelope['command'])===''||strlen($envelope['command'])>12000||!is_string($envelope['working_directory']??null)||strlen($envelope['working_directory'])>2048||!in_array($envelope['shell']??'', ['default','bash','zsh','sh','cmd','powershell'],true)||!is_int($envelope['timeout_seconds']??null)||$envelope['timeout_seconds']<1||$envelope['timeout_seconds']>120)return false;
        $device=$this->ensureDevice();if(!hash_equals($device['device_id'],(string)$envelope['target_device_id'])||!hash_equals((string)$envelope['command_sha256'],(string)$envelope['approval_hash']))return false;
        $hashInput=['request_id'=>(string)$envelope['request_id'],'source_device'=>(string)$envelope['source_device'],'target_device'=>(string)$envelope['target_device_id'],'command'=>(string)$envelope['command'],'cwd'=>(string)$envelope['working_directory'],'shell'=>(string)$envelope['shell'],'timeout_seconds'=>(int)$envelope['timeout_seconds']];$expected=hash('sha256',json_encode($hashInput,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));if(!hash_equals($expected,(string)$envelope['command_sha256']))return false;
        if(!preg_match('/^[a-f0-9-]{36}$/',(string)$envelope['request_id'])||!preg_match('/^[a-f0-9-]{36}$/',(string)$envelope['envelope_id'])||!preg_match('/^[a-f0-9]{48}$/',(string)$envelope['nonce']))return false;$issued=strtotime((string)$envelope['issued_at']);$expires=strtotime((string)$envelope['expires_at']);$now=time();if($issued===false||$expires===false||$issued>$now+60||$expires<=$now||$expires<=$issued||$expires-$issued>120)return false;
        $public=$this->terminalCloudSigningPublicKey();if(!hash_equals(hash('sha256',$public),(string)$envelope['issuer_key_id']))return false;$sig=base64_decode($signature,true);return $sig!==false&&strlen($sig)===SODIUM_CRYPTO_SIGN_BYTES&&sodium_crypto_sign_verify_detached($sig,$this->canonicalJson($envelope),$public);
    }
    private function canonicalJson(array $value):string {
        $normalize=static function($item)use(&$normalize){if(!is_array($item))return $item;if(array_is_list($item))return array_map($normalize,$item);ksort($item,SORT_STRING);foreach($item as $key=>$child)$item[$key]=$normalize($child);return $item;};return json_encode($normalize($value),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
    public function dispatchRemoteTerminalRequest(string $requestId):array {
        $device=$this->ensureDevice();if(!preg_match('/^[a-f0-9-]{36}$/i',$requestId))throw new InvalidArgumentException('Invalid remote terminal request.');return $this->request('POST','/remote-terminal-requests/'.$requestId.'/dispatch',[],$device['device_token']);
    }
    public function submitRemoteTerminalResult(string $requestId,array $signedResult,string $signature):array {
        $device=$this->ensureDevice();if(!preg_match('/^[a-f0-9-]{36}$/i',$requestId))throw new InvalidArgumentException('Invalid remote terminal request.');return $this->request('POST','/remote-terminal-requests/'.$requestId.'/result',['signed_result'=>$signedResult,'signature'=>$signature],$device['device_token']);
    }
    public function remoteTerminalRequestStatus(string $requestId):array {
        $device=$this->ensureDevice();if(!preg_match('/^[a-f0-9-]{36}$/i',$requestId))throw new InvalidArgumentException('Invalid remote terminal request.');return $this->request('GET','/remote-terminal-requests/'.$requestId,null,$device['device_token']);
    }
    public function revokePairing(string $deviceId):array {
        $device=$this->ensureDevice();if(!preg_match('/^[a-f0-9-]{36}$/i',$deviceId))throw new InvalidArgumentException('Invalid paired device.');
        return $this->request('POST','/paired-devices/'.$deviceId.'/revoke',[],$device['device_token']);
    }
    public function setPairingPresence(string $deviceId,bool $paused):array {
        $device=$this->ensureDevice();
        if(!preg_match('/^[a-f0-9-]{36}$/i',$deviceId))throw new InvalidArgumentException('Invalid paired device.');
        return $this->request('POST','/paired-devices/'.$deviceId.'/presence',['paused'=>$paused],$device['device_token']);
    }
    public function requestConnection(string $targetDevice):array {
        $device=$this->secret('cloud.device_token_enc');if($device==='')throw new RuntimeException('DCP Cloud device is not registered yet.');
        if($targetDevice===''||$targetDevice===$this->value('cloud.device_id'))throw new InvalidArgumentException('Choose another paired device.');
        return $this->request('POST','/connections/request',['target_device'=>$targetDevice],$device);
    }
    public function sendSignal(string $requestId,string $envelope):array {
        $device=$this->secret('cloud.device_token_enc');if($device==='')throw new RuntimeException('DCP Cloud device is not registered yet.');
        if(!preg_match('/^[a-f0-9-]{36}$/i',$requestId))throw new InvalidArgumentException('Invalid connection request ID.');
        if($envelope===''||strlen($envelope)>32768)throw new InvalidArgumentException('Invalid signaling envelope.');
        return $this->request('POST','/connections/'.$requestId.'/signal',['envelope'=>$envelope],$device);
    }
    public function signals(string $requestId,int $after=0):array {
        $device=$this->secret('cloud.device_token_enc');if($device==='')throw new RuntimeException('DCP Cloud device is not registered yet.');
        if(!preg_match('/^[a-f0-9-]{36}$/i',$requestId))throw new InvalidArgumentException('Invalid connection request ID.');
        return $this->request('GET','/connections/'.$requestId.'/signals?after='.max(0,$after),null,$device);
    }
}
