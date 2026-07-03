<?php
namespace PHPMailer\PHPMailer;

class PHPMailer {
    public function __construct(bool $exceptions = null) {}
    public function isSMTP() {}
    public function Host(string $host) {}
    public function SMTPAuth(bool $auth) {}
    public function Username(string $username) {}
    public function Password(string $password) {}
    public function SMTPSecure(string $encryption) {}
    public function Port(int $port) {}
    public function setFrom(string $address, string $name = '') {}
    public function addAddress(string $address, string $name = '') {}
    public function isHTML(bool $isHtml) {}
    public function Subject(string $subject) {}
    public function Body(string $body) {}
    public function AltBody(string $altBody) {}
    public function send() : bool { return true; } // Simulate success
    public function getErrorInfo() : string { return ''; }
}
?>