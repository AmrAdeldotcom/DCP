#!/usr/bin/env python3
"""Exercise the authenticated DCP Chat API, background worker, checkpoint and Task Inbox end-to-end."""
import hashlib, http.cookiejar, json, os, re, shutil, socket, subprocess, tempfile, time, urllib.error, urllib.parse, urllib.request

ROOT=os.path.abspath(os.path.join(os.path.dirname(__file__),'..'))
TMP=tempfile.mkdtemp(prefix='dcp-chat-http-e2e-')

def free_port():
    sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();return port

app_port,provider_port=free_port(),free_port()
env=os.environ.copy();env.update({'DCP_USER_DATA_DIR':os.path.join(TMP,'user-data'),'DCP_MODE':'desktop','DCP_BASE_PATH':'','DCP_CLOUD_BASE_URL':'http://127.0.0.1:9'})
php=subprocess.run(['php','-r','echo PHP_BINARY;'],capture_output=True,text=True,check=True).stdout
provider=subprocess.Popen([php,'-S',f'127.0.0.1:{provider_port}',os.path.join(ROOT,'tests','mock-chat-delay-router.php')],cwd=ROOT,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
app=subprocess.Popen([php,'-S',f'127.0.0.1:{app_port}','-t',os.path.join(ROOT,'public'),os.path.join(ROOT,'public','router.php')],cwd=ROOT,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
base=f'http://127.0.0.1:{app_port}'
try:
    opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    html=''
    for _ in range(100):
        try: html=opener.open(base+'/',timeout=1).read().decode();break
        except Exception: time.sleep(.1)
    if not html: raise RuntimeError('DCP HTTP app did not become ready')
    match=re.search(r'data-csrf="([^"]+)"',html)
    if not match: raise RuntimeError('DCP page did not expose its CSRF session token')
    csrf=match.group(1)
    def request(path,method='GET',data=None):
        body=None if data is None else json.dumps(data).encode()
        headers={'Accept':'application/json'}
        if body is not None: headers.update({'Content-Type':'application/json','X-CSRF-Token':csrf})
        req=urllib.request.Request(base+path,data=body,headers=headers,method=method)
        try:
            with opener.open(req,timeout=8) as res:return res.status,json.loads(res.read())
        except urllib.error.HTTPError as exc:return exc.code,json.loads(exc.read())
    status,saved=request('/api/providers/save','POST',{'name':'Chat HTTP E2E mock','plugin_id':'openai-compatible','base_url':f'http://127.0.0.1:{provider_port}/v1','model':'mock-delay','api_key':'','enabled':True})
    assert status==200 and saved.get('provider',{}).get('id'),(status,saved)
    provider_id=saved['provider']['id']
    started_at=time.monotonic()
    status,started=request('/api/chat','POST',{'provider_id':provider_id,'model':'mock-delay','message':'long running chat request'})
    elapsed=time.monotonic()-started_at
    assert elapsed<8.0,('Chat API must return before the 10-second provider fixture completes; elapsed=%.2fs'%elapsed)
    assert status==200 and started.get('job_id') and started.get('async') is True,(status,started)
    print('PASS: /api/chat returned its durable job ID in %.2fs'%elapsed,flush=True)
    job_id=started['job_id'];session_id=started['session_id']
    running=None
    for _ in range(200):
        status,running=request('/api/chat/jobs?id='+urllib.parse.quote(job_id))
        if running.get('status')=='running':break
        if running.get('status') in ('failed','checkpoint_ready','completed'):raise RuntimeError('Worker did not enter its running state before the delayed provider call: '+repr(running))
        time.sleep(.05)
    assert running and running.get('status')=='running',running
    status,queued=request('/api/task-inbox','POST',{'message':'Please add this after the current answer','session_id':session_id})
    assert status==201 and queued['message']['status']=='queued',(status,queued)
    inbox_id=queued['message']['id']
    status,interrupted=request('/api/chat/jobs/interrupt','POST',{'id':job_id})
    assert status==200 and interrupted['job']['status'] in ('checkpoint_requested','checkpoint_ready'),(status,interrupted)
    final=None
    for _ in range(200):
        status,final=request('/api/chat/jobs?id='+urllib.parse.quote(job_id))
        if final.get('status') in ('checkpoint_ready','completed','failed'):break
        time.sleep(.1)
    assert final and final.get('status')=='checkpoint_ready',final
    assert final.get('reply')=='DELAYED_CHAT_OK:long running chat request',final
    status,checkpoint=request('/api/chat/jobs/checkpoint?id='+urllib.parse.quote(job_id))
    assert status==200 and checkpoint.get('checkpoint'),(status,checkpoint)
    snapshot=checkpoint['checkpoint']['snapshot']
    assert checkpoint['checkpoint']['context_hash']==hashlib.sha256(json.dumps(snapshot,ensure_ascii=False,separators=(',',':')).encode()).hexdigest(),checkpoint
    assert snapshot.get('human_review_required') is True and snapshot.get('automatic_execution') is False,snapshot
    assert any(item.get('id')==inbox_id for item in snapshot.get('pending_instructions',[])),snapshot
    status,messages=request('/api/chat/messages?id='+urllib.parse.quote(session_id))
    assert status==200 and any(m['role']=='assistant' and m['content']==final['reply'] for m in messages['messages']),(status,messages)
    status,queue=request('/api/task-inbox?session_id='+urllib.parse.quote(session_id))
    assert status==200 and any(m['id']==inbox_id and m['status']=='queued' for m in queue['messages']),(status,queue)
    status,active=request('/api/chat/jobs/active?session_id='+urllib.parse.quote(session_id))
    assert status==200 and active.get('job') is None,(status,active)
    print('PASS: authenticated chat request returned a durable background job ID')
    print('PASS: independent Task Inbox accepted a new instruction while the provider was still working')
    print('PASS: checkpoint request waited for the in-flight provider call to finish')
    print('PASS: response and conversation history were persisted exactly through the Chat API')
    print('PASS: durable checkpoint API returned conversation + pending queue snapshot with verifiable SHA-256 context hash')
    print('PASS: queued instruction remained queued and was not automatically executed')
    print('PASS: active-job recovery endpoint reports no active job after checkpoint completion')
    print('DCP_CHAT_JOB_HTTP_E2E_PASS')
finally:
    app.terminate();provider.terminate()
    for proc in (app,provider):
        try:proc.wait(timeout=5)
        except subprocess.TimeoutExpired:proc.kill();proc.wait(timeout=5)
    shutil.rmtree(TMP,ignore_errors=True)
