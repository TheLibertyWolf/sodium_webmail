<?php
declare(strict_types=1);

return static function(PDO $pdo): void {
    $pdo->exec("INSERT IGNORE INTO sodium_user_aptitudes(user_id,label) VALUES(1,'sodium_full_access')");
};
