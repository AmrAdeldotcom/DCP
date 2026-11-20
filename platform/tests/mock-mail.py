import socketserver,threading
class SMTP(socketserver.StreamRequestHandler):
 def handle(self):
  self.wfile.write(b'220 mock.smtp ESMTP\r\n')
  while True:
   line=self.rfile.readline()
   if not line:break
   cmd=line.decode(errors='ignore').strip().upper()
   if cmd.startswith('EHLO'):self.wfile.write(b'250-mock\r\n250 OK\r\n')
   elif cmd.startswith('MAIL FROM') or cmd.startswith('RCPT TO'):self.wfile.write(b'250 OK\r\n')
   elif cmd=='DATA':
    self.wfile.write(b'354 End data with <CR><LF>.<CR><LF>\r\n')
    while True:
     x=self.rfile.readline()
     if x in (b'.\r\n',b'.\n',b''):break
    self.wfile.write(b'250 queued\r\n')
   elif cmd=='QUIT':self.wfile.write(b'221 bye\r\n');break
   elif cmd.startswith('AUTH'):self.wfile.write(b'334 VXNlcm5hbWU6\r\n')
   else:self.wfile.write(b'250 OK\r\n')
class IMAP(socketserver.StreamRequestHandler):
 def handle(self):
  self.wfile.write(b'* OK mock imap ready\r\n')
  while True:
   line=self.rfile.readline()
   if not line:break
   try:tag,cmd=line.decode().strip().split(' ',1)
   except:continue
   c=cmd.upper()
   if c.startswith('LOGIN'):self.wfile.write((tag+' OK logged in\r\n').encode())
   elif c.startswith('LIST'):self.wfile.write(b'* LIST (\\HasNoChildren) "/" "INBOX"\r\n');self.wfile.write((tag+' OK list\r\n').encode())
   elif c.startswith('SELECT'):self.wfile.write(b'* 1 EXISTS\r\n');self.wfile.write((tag+' OK selected\r\n').encode())
   elif c.startswith('SEARCH') or c.startswith('UID SEARCH'):self.wfile.write(b'* SEARCH 1\r\n');self.wfile.write((tag+' OK search\r\n').encode())
   elif c.startswith('FETCH') or c.startswith('UID FETCH'):
    self.wfile.write(b'* 1 FETCH (BODY[HEADER.FIELDS (FROM SUBJECT DATE)] {95}\r\nSubject: DCP test message\r\nFrom: tester@example.test\r\nDate: Fri, 20 Nov 2026 12:00:00 +0000\r\n BODY[TEXT] {14}\r\nHello from IMAP\r\n)\r\n')
    self.wfile.write((tag+' OK fetched\r\n').encode())
   elif c.startswith('LOGOUT'):self.wfile.write(b'* BYE logout\r\n');self.wfile.write((tag+' OK logout\r\n').encode());break
   else:self.wfile.write((tag+' OK\r\n').encode())
class S(socketserver.ThreadingTCPServer):allow_reuse_address=True
threading.Thread(target=S(('127.0.0.1',18767),SMTP).serve_forever,daemon=True).start()
S(('127.0.0.1',18768),IMAP).serve_forever()
