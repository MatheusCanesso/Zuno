<?php
$serverName = "DESKTOP-REJT7MF\SQLEXPRESS";
$connectionOptions = array(
    "Database" => "Zuno",
    "Uid" => "sa",
    "PWD" => "12121515",
    "CharacterSet" => "UTF-8"
);

try {
    $conn = new PDO("sqlsrv:Server=$serverName;Database=Zuno", $connectionOptions['Uid'], $connectionOptions['PWD']);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>