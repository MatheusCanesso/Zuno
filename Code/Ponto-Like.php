<?php
session_start();
header('Content-Type: application/json');

date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

require_once 'Configs/config.php';

$response = ['success' => false, 'message' => 'Erro desconhecido.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Usuário não autenticado.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $zunId = isset($_POST['zun_id']) ? (int)$_POST['zun_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($zunId <= 0) {
        $response['message'] = 'ID do Zun inválido.';
        echo json_encode($response);
        exit();
    }

    try {
        // Corrigido o nome da stored procedure
        $sql = "DECLARE @NewLikesCount INT;
                DECLARE @LikedStatus BIT;
                
                EXEC GerenciarZunPontoLike 
                    @UsuarioID = ?,
                    @ZunID = ?,
                    @NewLikesCount = @NewLikesCount OUTPUT,
                    @LikedStatus = @LikedStatus OUTPUT;
                
                SELECT @NewLikesCount AS NewLikesCount, @LikedStatus AS LikedStatus;";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(1, $userId, PDO::PARAM_INT);
        $stmt->bindParam(2, $zunId, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Obter os resultados
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $response['success'] = true;
        $response['newLikes'] = $result['NewLikesCount'];
        $response['liked'] = (bool)$result['LikedStatus'];
        $response['message'] = $response['liked'] ? 'Zun curtido!' : 'Zun descurtido.';

    } catch (PDOException $e) {
        $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
        error_log("Erro ao gerenciar like do Zun: " . $e->getMessage());
    }
} else {
    $response['message'] = 'Método de requisição inválido.';
}

echo json_encode($response);
?>