<?php
declare(strict_types=1);
interface MailAdapter {public function build(PDO $db,Crypto $crypto):MailService;}
