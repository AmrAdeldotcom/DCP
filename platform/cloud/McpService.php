<?php
declare(strict_types=1);

final class DcpCloudMcpService {
    public function __construct(private PDO $db) {}

    private const TOOL_SCOPES = [
        'dcp_cloud_list_devices' => 'cloud.devices.read',
        'dcp_cloud_pairing_overview' => 'cloud.devices.read',
        'dcp_cloud_resource_discover' => 'cloud.resources.read',
        'dcp_cloud_list_teams' => 'cloud.teams.read',
        'dcp_cloud_list_resource_grants' => 'cloud.grants.read',
    ];

    public static function hasScope(array $scopes, string $required): bool {
        return in_array('*', $scopes, true) || in_array($required, $scopes, true);
    }

    public function tools(array $scopes): array {
        $definitions = [
            ['name'=>'dcp_cloud_list_devices','description'=>'List active devices belonging to the authenticated DCP Cloud account.','inputSchema'=>['type'=>'object','properties'=>(object)[],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'dcp_cloud_pairing_overview','description'=>'List pending pairing requests and approved device pairings for this account.','inputSchema'=>['type'=>'object','properties'=>(object)[],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'dcp_cloud_resource_discover','description'=>'Discover resources owned by this account or explicitly granted to its users, teams or devices. Owner-local secrets are never returned.','inputSchema'=>['type'=>'object','properties'=>['kind'=>['type'=>'string','maxLength'=>40],'provider'=>['type'=>'string','maxLength'=>100],'model'=>['type'=>'string','maxLength'=>160],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>50]],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'dcp_cloud_list_teams','description'=>'List teams and membership roles for the authenticated account.','inputSchema'=>['type'=>'object','properties'=>(object)[],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'dcp_cloud_list_resource_grants','description'=>'List active and revoked resource grants owned by this account.','inputSchema'=>['type'=>'object','properties'=>['limit'=>['type'=>'integer','minimum'=>1,'maximum'=>100]],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
        ];
        return array_values(array_filter($definitions, static fn(array $tool): bool => self::hasScope($scopes, self::TOOL_SCOPES[$tool['name']])));
    }

    public function requiredScope(string $tool): ?string { return self::TOOL_SCOPES[$tool] ?? null; }

    public function call(string $tool, array $args, int $userId): array {
        $scope = $this->requiredScope($tool);
        if ($scope === null) throw new InvalidArgumentException('unknown_tool');
        if ($userId < 1) throw new InvalidArgumentException('invalid_account');
        return match ($tool) {
            'dcp_cloud_list_devices' => $this->listDevices($userId),
            'dcp_cloud_pairing_overview' => $this->pairingOverview($userId),
            'dcp_cloud_resource_discover' => $this->discoverResources($userId, $args),
            'dcp_cloud_list_teams' => $this->listTeams($userId),
            'dcp_cloud_list_resource_grants' => $this->listResourceGrants($userId, $args),
            default => throw new InvalidArgumentException('unknown_tool'),
        };
    }

    private function listDevices(int $userId): array {
        $q=$this->db->prepare('SELECT id,label,platform,last_seen_at FROM devices WHERE user_id=? AND revoked_at IS NULL ORDER BY label LIMIT 100');$q->execute([$userId]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row){$seen=strtotime((string)($row['last_seen_at']??''));$row['online']=$seen!==false&&$seen>=time()-90;}unset($row);
        return ['devices'=>$rows];
    }

    private function pairingOverview(int $userId): array {
        $q=$this->db->prepare("SELECT r.id,r.status,r.created_at,r.expires_at,owner.label owner_label,requester.label requester_label FROM pairing_requests r JOIN devices owner ON owner.id=r.owner_device AND owner.revoked_at IS NULL JOIN devices requester ON requester.id=r.requester_device AND requester.revoked_at IS NULL WHERE owner.user_id=? AND r.status='pending' AND r.expires_at>CURRENT_TIMESTAMP ORDER BY r.created_at LIMIT 100");$q->execute([$userId]);$requests=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$this->db->prepare('SELECT x.id,x.label,x.platform,x.last_seen_at,p.scopes,p.presence_paused_at FROM device_pairings p JOIN devices a ON a.id=p.owner_device AND a.revoked_at IS NULL JOIN devices b ON b.id=p.peer_device AND b.revoked_at IS NULL JOIN devices x ON x.id=CASE WHEN a.user_id=? THEN b.id ELSE a.id END WHERE (a.user_id=? OR b.user_id=?) AND p.revoked_at IS NULL AND x.revoked_at IS NULL ORDER BY x.label LIMIT 100');$q->execute([$userId,$userId,$userId]);$pairings=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach($pairings as &$pair){$scopes=json_decode((string)$pair['scopes'],true)?:[];$pair['scopes']=$scopes;$pair['presence_granted']=in_array('presence',$scopes,true);$pair['presence_paused']=$pair['presence_granted']&&$pair['presence_paused_at']!==null;$pair['presence_enabled']=$pair['presence_granted']&&!$pair['presence_paused'];if(!$pair['presence_enabled'])$pair['last_seen_at']=null;unset($pair['presence_paused_at']);}unset($pair);
        return ['pending_requests'=>$requests,'pairings'=>$pairings];
    }

    private function discoverResources(int $userId, array $args): array {
        foreach(['kind'=>40,'provider'=>100,'model'=>160] as $key=>$max){if(isset($args[$key])&&(!is_string($args[$key])||strlen($args[$key])>$max))throw new InvalidArgumentException('invalid_'.$key);}
        $limit=max(1,min(50,(int)($args['limit']??25)));
        $sql="SELECT r.id,r.kind,r.name,r.provider,r.model,r.endpoint_class,r.operations_json,r.metadata_json,r.capacity_json,r.data_classification,r.locality,r.health_status,r.health_at,r.published FROM cloud_resources r WHERE r.revoked_at IS NULL AND (r.owner_user_id=? OR (r.published=1 AND EXISTS (SELECT 1 FROM cloud_resource_grants g WHERE g.resource_id=r.id AND g.status='active' AND (g.expires_at IS NULL OR g.expires_at>CURRENT_TIMESTAMP) AND ((g.grantee_type='user' AND g.grantee_id=?) OR (g.grantee_type='team' AND g.grantee_id IN (SELECT CAST(team_id AS CHAR) FROM cloud_team_members WHERE user_id=?)) OR (g.grantee_type='device' AND g.grantee_id IN (SELECT id FROM devices WHERE user_id=? AND revoked_at IS NULL))))))";
        $params=[$userId,(string)$userId,$userId,$userId];
        foreach(['kind'=>'kind','provider'=>'provider','model'=>'model'] as $arg=>$column){if(isset($args[$arg])&&$args[$arg]!==''){$sql.=" AND r.$column=?";$params[]=$args[$arg];}}
        $sql.=' ORDER BY r.name LIMIT '.(int)$limit;$q=$this->db->prepare($sql);$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row){foreach(['operations_json'=>'operations','metadata_json'=>'metadata','capacity_json'=>'capacity'] as $column=>$key){$decoded=json_decode((string)$row[$column],true);$row[$key]=is_array($decoded)?$decoded:[];unset($row[$column]);}}unset($row);
        return ['resources'=>$rows,'count'=>count($rows)];
    }

    private function listTeams(int $userId): array {
        $q=$this->db->prepare('SELECT t.id,t.name,tm.role,tm.created_at FROM cloud_team_members tm JOIN cloud_teams t ON t.id=tm.team_id WHERE tm.user_id=? ORDER BY t.name LIMIT 100');$q->execute([$userId]);return ['teams'=>$q->fetchAll(PDO::FETCH_ASSOC)];
    }

    private function listResourceGrants(int $userId, array $args): array {
        $limit=max(1,min(100,(int)($args['limit']??50)));
        $q=$this->db->prepare('SELECT g.id,g.resource_id,r.name resource_name,g.grantee_type,g.grantee_id,g.operations_json,g.quota_limit,g.concurrency_limit,g.expires_at,g.status,g.generation,g.created_at,g.revoked_at FROM cloud_resource_grants g JOIN cloud_resources r ON r.id=g.resource_id WHERE r.owner_user_id=? ORDER BY g.created_at DESC LIMIT '.(int)$limit);$q->execute([$userId]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row){$row['operations']=json_decode((string)$row['operations_json'],true)?:[];unset($row['operations_json']);}unset($row);
        return ['grants'=>$rows];
    }
}
