<?php
declare(strict_types=1);
interface DcpResourceChatClient {
 public function send(string $providerId,string $model,string $message,array $history=[]):string;
}
