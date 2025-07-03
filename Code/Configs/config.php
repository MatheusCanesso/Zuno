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

// --- Configurações de Criptografia ---
// ATENÇÃO: Em um ambiente de produção, esta chave DEVE ser gerada de forma segura
// e carregada de um local seguro (e.g., variável de ambiente, sistema de gerenciamento de segredos).
// NÃO a mantenha codificada diretamente em um arquivo público.
// --- Configurações de Criptografia ---
define('ENCRYPTION_KEY', 'sua_chave_secreta_de_32_bytes_aqui_123'); // Chave de 32 bytes para AES-256
define('CIPHER_METHOD', 'aes-256-cbc'); // Método de cifra

// Função para criptografar dados
function encryptData($data) {
    $key = ENCRYPTION_KEY;
    $cipher = CIPHER_METHOD;
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
    return $iv . $encrypted;
}

// Função para descriptografar dados
function decryptData($encryptedDataWithIv) {
    $key = ENCRYPTION_KEY;
    $cipher = CIPHER_METHOD;
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = substr($encryptedDataWithIv, 0, $ivlen);
    $encryptedData = substr($encryptedDataWithIv, $ivlen);
    return openssl_decrypt($encryptedData, $cipher, $key, 0, $iv);
}

// --- Configurações de API de GIFs ---
// Obtenha suas chaves de API em Giphy Developers (https://developers.giphy.com/)
// e Tenor Developers (https://developers.tenor.com/dashboard/)
define('GIPHY_API_KEY', 'bN4Sepd2jEzO4MJ8XxSp7fGwhbn3QCvW');
define('TENOR_API_KEY', 'AIzaSyBinPiZ0nNdUgbReFZlCCLGnpQOaGQd9_Y');
?>