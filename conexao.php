<?php
// conexao.php

$host = 'localhost'; // Normalmente é localhost
$db   = 'habbo'; // Substitua pelo nome da database do emulador
$user = 'root'; // Seu usuário do MySQL
$pass = ''; // Sua senha do MySQL

// Configuração do PDO para maior segurança
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, $options);
} catch (\PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?>
