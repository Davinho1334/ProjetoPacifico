<?php
$host = "127.0.0.1";
$dbname = "escola_portal";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Não usar die(); deixe quem chamou decidir como responder.
    throw $e;
}
?>