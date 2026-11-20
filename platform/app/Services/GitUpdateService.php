<?php
declare(strict_types=1);

final class GitUpdateService {
 private string $repo;
 public function __construct(){ $this->repo=dirname(__DIR__,3); }
 private function git(string $args):array{
  $cmd='git -C '.escapeshellarg($this->repo).' '.$args.' 2>&1';$out=[];$code=0;exec($cmd,$out,$code);return ['code'=>$code,'output'=>trim(implode("\n",$out))];
 }
 public function syncOnStartup():array{
  if(!is_dir($this->repo.'/.git'))return ['ok'=>true,'supported'=>false,'updated'=>false,'message'=>'Automatic updates are unavailable in this installed package.'];
  $branch=$this->git('branch --show-current');if($branch['code']!==0||$branch['output']!=='main')return ['ok'=>true,'updated'=>false,'message'=>'Updates skipped: current branch is not main.'];
  $remote=$this->git('remote get-url origin');if($remote['code']!==0)return ['ok'=>false,'updated'=>false,'message'=>'Updates skipped: GitHub origin is not configured.'];
  $fetch=$this->git('fetch --quiet origin main');if($fetch['code']!==0)return ['ok'=>false,'updated'=>false,'message'=>'GitHub update check failed: '.$fetch['output']];
  $dirty=$this->git('status --porcelain');if($dirty['output']!=='')return ['ok'=>true,'updated'=>false,'message'=>'Update available check completed, but local changes were preserved; automatic update skipped.'];
  $behind=$this->git('rev-list --count HEAD..origin/main');if($behind['code']!==0)return ['ok'=>false,'updated'=>false,'message'=>'Could not compare local and GitHub versions.'];
  $count=(int)$behind['output'];if($count===0)return ['ok'=>true,'updated'=>false,'message'=>'DCP is up to date.'];
  $pull=$this->git('merge --ff-only origin/main');if($pull['code']!==0)return ['ok'=>false,'updated'=>false,'message'=>'Update found but could not be safely applied: '.$pull['output']];
  return ['ok'=>true,'updated'=>true,'commits'=>$count,'message'=>'DCP updated from GitHub successfully. Restart DCP to reload native components.'];
 }
}
