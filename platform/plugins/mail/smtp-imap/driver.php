<?php
declare(strict_types=1);
return new class implements MailAdapter {public function build(PDO $db,Crypto $crypto):MailService{return new MailService($db,$crypto);}};
