<?php
declare(strict_types=1);

$mode = (string)($argv[1] ?? '');
$port = (int)($argv[2] ?? 0);
$connections = (int)($argv[3] ?? ($mode === 'smtp' ? 2 : 1));
$server = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $errstr);
if (!$server) { fwrite(STDERR, $errstr . PHP_EOL); exit(1); }
echo "READY\n";
flush();

for ($n = 0; $n < $connections; $n++) {
    $client = stream_socket_accept($server, 20);
    if (!$client) { fwrite(STDERR, "accept timeout\n"); exit(2); }
    stream_set_timeout($client, 15);
    if ($mode === 'smtp') {
        fwrite($client, "220 mock.dcp.local ESMTP ready\r\n");
        $authStep = 0;
        $dataMode = false;
        while (($line = fgets($client, 8192)) !== false) {
            $command = rtrim($line, "\r\n");
            if ($dataMode) {
                if ($command === '.') {
                    fwrite($client, "250 2.0.0 queued\r\n");
                    $dataMode = false;
                }
                continue;
            }
            if (str_starts_with($command, 'EHLO ')) {
                fwrite($client, "250-mock.dcp.local\r\n250-AUTH LOGIN\r\n250 OK\r\n");
            } elseif ($command === 'AUTH LOGIN') {
                $authStep = 1;
                fwrite($client, "334 VXNlcm5hbWU6\r\n");
            } elseif ($authStep === 1) {
                $authStep = 2;
                fwrite($client, "334 UGFzc3dvcmQ6\r\n");
            } elseif ($authStep === 2) {
                $authStep = 0;
                fwrite($client, "235 2.7.0 authenticated\r\n");
            } elseif (str_starts_with($command, 'MAIL FROM:')) {
                fwrite($client, "250 2.1.0 sender ok\r\n");
            } elseif (str_starts_with($command, 'RCPT TO:')) {
                fwrite($client, "250 2.1.5 recipient ok\r\n");
            } elseif ($command === 'DATA') {
                $dataMode = true;
                fwrite($client, "354 end with dot\r\n");
            } elseif ($command === 'QUIT') {
                fwrite($client, "221 2.0.0 bye\r\n");
                break;
            } else {
                fwrite($client, "250 OK\r\n");
            }
        }
    } elseif ($mode === 'imap') {
        fwrite($client, "* OK mock.dcp.local IMAP4rev1 ready\r\n");
        while (($line = fgets($client, 8192)) !== false) {
            $command = rtrim($line, "\r\n");
            if (str_starts_with($command, 'A1 LOGIN ')) {
                fwrite($client, "A1 OK LOGIN completed\r\n");
            } elseif (str_starts_with($command, 'A2 LIST ')) {
                fwrite($client, "* LIST (\\HasNoChildren) \"/\" \"INBOX\"\r\nA2 OK LIST completed\r\n");
            } elseif ($command === 'A3 SELECT INBOX') {
                fwrite($client, "* 1 EXISTS\r\nA3 OK [READ-WRITE] SELECT completed\r\n");
            } elseif ($command === 'A4 UID SEARCH UNSEEN') {
                fwrite($client, "* SEARCH 42\r\nA4 OK SEARCH completed\r\n");
            } elseif (str_starts_with($command, 'B1 UID FETCH ')) {
                $headers = "From: inviter@example.test\r\nSubject: DCP device invitation\r\nDate: Tue, 24 Nov 2026 12:00:00 +0000\r\n";
                $body = "Please approve the DCP device pairing invitation.\r\n";
                fwrite($client, "* 1 FETCH (UID 42 BODY[HEADER.FIELDS (FROM SUBJECT DATE)] {" . strlen($headers) . "}\r\n" . $headers . " BODY[TEXT] {" . strlen($body) . "}\r\n" . $body . ")\r\nB1 OK FETCH completed\r\n");
            } elseif ($command === 'A5 LOGOUT') {
                fwrite($client, "* BYE logging out\r\nA5 OK LOGOUT completed\r\n");
                break;
            } else {
                fwrite($client, "A0 BAD unknown command\r\n");
            }
        }
    } else {
        fwrite(STDERR, "unknown mock server mode\n");
        exit(3);
    }
    fclose($client);
}
fclose($server);
