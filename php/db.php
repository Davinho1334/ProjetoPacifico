<?php
$host = "127.0.0.1";   // ou "localhost"
$dbname = "escola_portal"; // <-- troque pelo nome exato do seu banco
$user = "root";        // usuário do MySQL
$pass = "";            // senha do MySQL (no XAMPP normalmente é vazio)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}
?>