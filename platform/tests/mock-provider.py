from http.server import BaseHTTPRequestHandler,HTTPServer
import json
class Handler(BaseHTTPRequestHandler):
 def send(self,obj):
  b=json.dumps(obj).encode();self.send_response(200);self.send_header('Content-Type','application/json');self.send_header('Content-Length',str(len(b)));self.end_headers();self.wfile.write(b)
 def do_GET(self):
  if self.path.endswith('/models'):self.send({'data':[{'id':'mock-model'}]})
  else:self.send({'ok':True})
 def do_POST(self):
  n=int(self.headers.get('Content-Length',0));d=json.loads(self.rfile.read(n))
  self.send({'choices':[{'message':{'content':'MOCK_CHAT_OK:'+d['messages'][-1]['content']}}]})
 def log_message(self,*a):pass
HTTPServer(('127.0.0.1',18766),Handler).serve_forever()
