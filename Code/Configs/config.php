<?php
// config.php

// Detalhes de conexão com o banco de dados SQL Server
$serverName = "DESKTOP-REJT7MF\SQLEXPRESS"; // Ex: localhost, IP do servidor, ou nome da instância (ex: .\SQLEXPRESS)
$databaseName = "Zuno"; // Nome do seu banco de dados Zuno
$username = "sa";     // Usuário do SQL Server
$password = "12121515";     // Senha do SQL Server

try {
    // Configurações de PDO para SQL Server
    $conn = new PDO("sqlsrv:Server=$serverName;Database=$databaseName", $username, $password);
    // Define o modo de erro do PDO para lançar exceções
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Define o modo de busca padrão para objetos (ou PDO::FETCH_ASSOC para arrays associativos)
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    // echo "Conexão com o banco de dados estabelecida com sucesso!"; // Para testar a conexão
} catch (PDOException $e) {
    // Em caso de erro na conexão, exibe a mensagem de erro e interrompe a execução
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?>