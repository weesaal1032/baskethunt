<?php
function send_mail(string $to, string $subject, string $body): bool {
    $config = require __DIR__.'/../config.php';
    $smtp = $config['smtp'] ?? [];
    $headers = "From: {$smtp['from']}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers);
}
