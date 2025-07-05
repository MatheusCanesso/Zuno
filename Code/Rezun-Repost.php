<?php
session_start();
require_once 'Configs/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit();
}

$userId = $_SESSION['user_id'];
$zunId = isset($_POST['zun_id']) ? (int)$_POST['zun_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($zunId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Zun inválido']);
    exit();
}

try {
    if ($action === 'repost') {
        // Verificar se já não repostou
        $stmt = $conn->prepare("SELECT 1 FROM Reposts WHERE UsuarioID = ? AND ZunOriginalID = ?");
        $stmt->execute([$userId, $zunId]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Você já repostou este Zun']);
            exit();
        }
        
        // Inserir o repost
        $stmt = $conn->prepare("INSERT INTO Reposts (UsuarioID, ZunOriginalID) VALUES (?, ?)");
        $stmt->execute([$userId, $zunId]);
        
        // Notificar o autor original
        $stmt = $conn->prepare("SELECT UsuarioID FROM Zuns WHERE ZunID = ?");
        $stmt->execute([$zunId]);
        $originalAuthorId = $stmt->fetchColumn();
        
        if ($originalAuthorId && $originalAuthorId != $userId) {
            $stmt = $conn->prepare("INSERT INTO Notificacoes (UsuarioAlvoID, UsuarioOrigemID, TipoNotificacao, ZunID) VALUES (?, ?, 'repost', ?)");
            $stmt->execute([$originalAuthorId, $userId, $zunId]);
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
} catch (PDOException $e) {
    error_log("Erro ao processar repost: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor']);
}