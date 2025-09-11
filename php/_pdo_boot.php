<?php
// php/_pdo_boot.php
declare(strict_types=1);

require_once __DIR__ . '/db.php'; // deve definir $pdo OU pelo menos $host,$dbname,$user,$pass

if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (isset($host, $dbname, $user, $pass)) {
    try {
      $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    } catch (Throwable $e) {
      throw new Exception('Falha ao criar PDO a partir de $host/$dbname/$user/$pass: ' . $e->getMessage());
    }
  } else {
    throw new Exception('db.php não definiu $pdo nem $host/$dbname/$user/$pass.');
  }
}
