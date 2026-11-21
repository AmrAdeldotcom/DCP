<?php
declare(strict_types=1);

final class CloudService {
    private const BASE_URL = 'https://dcp.amradel.com/api/v1';
    public function __construct(private PDO $db, private Crypto $crypto) {}

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
        $ch=curl_init(self::BASE_URL.$path);if($ch===false)throw new RuntimeException('Could not initialize the DCP Cloud connection.');
        $headers=['Accept: application/json'];if($token!=='')$headers[]='Authorization: Bearer '.$token;if($body!==null)$headers[]='Content-Type: application/json';
        $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>18,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_FOLLOWLOCATION=>false];
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
        $allowed=['presence','signaling'];$scopes=array_values(array_unique(array_filter($scopes,fn($s)=>in_array($s,$allowed,true))));
        if($accept&&$scopes===[])throw new InvalidArgumentException('Select at least one permission before approving.');
        return $this->request('POST','/pairing-requests/'.$requestId.'/decision',['accept'=>$accept,'scopes'=>$scopes],$device['device_token']);
    }
    public function revokePairing(string $deviceId):array {
        $device=$this->ensureDevice();if(!preg_match('/^[a-f0-9-]{36}$/i',$deviceId))throw new InvalidArgumentException('Invalid paired device.');
        return $this->request('POST','/paired-devices/'.$deviceId.'/revoke',[],$device['device_token']);
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
