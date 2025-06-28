<?php
session_start();
require_once 'Configs/config.php';

// Redireciona se o usuário não estiver logado
if (!isset($_SESSION['user_id'])) {
    header("Location: Login-Cadastro.php?action=login");
    exit();
}

$userId = $_SESSION['user_id'];
$userData = null;
$message = '';

try {
    // Buscar informações do usuário
    $stmt = $conn->prepare("SELECT NomeExibicao, NomeUsuario, DataNascimento, DataCriacao, Biografia, FotoPerfil, FotoCapa FROM Usuarios WHERE UsuarioID = :userId");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$userData) {
        $message = '<p style="color: red;">Erro: Dados do usuário não encontrados.</p>';
    }
} catch (PDOException $e) {
    $message = '<p style="color: red;">Erro ao carregar dados do perfil: ' . $e->getMessage() . '</p>';
}

// Buscar comunidades do usuário
$userCommunities = [];
try {
    $stmt = $conn->prepare("SELECT c.ComunidadeID, c.NomeComunidade, c.Descricao, c.FotoComunidade, COUNT(mc.UsuarioID) as Membros 
                           FROM Comunidades c 
                           JOIN MembrosComunidade mc ON c.ComunidadeID = mc.ComunidadeID 
                           WHERE mc.UsuarioID = :userId 
                           GROUP BY c.ComunidadeID, c.NomeComunidade, c.Descricao, c.FotoComunidade");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userCommunities = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $message .= '<p style="color: red;">Erro ao carregar comunidades: ' . $e->getMessage() . '</p>';
}

// Buscar comunidades sugeridas
$suggestedCommunities = [];
try {
    $stmt = $conn->prepare("SELECT TOP 5 c.ComunidadeID, c.NomeComunidade, c.Descricao, c.FotoComunidade, COUNT(mc.UsuarioID) as Membros 
                           FROM Comunidades c 
                           JOIN MembrosComunidade mc ON c.ComunidadeID = mc.ComunidadeID 
                           WHERE c.ComunidadeID NOT IN (SELECT ComunidadeID FROM MembrosComunidade WHERE UsuarioID = :userId)
                           GROUP BY c.ComunidadeID, c.NomeComunidade, c.Descricao, c.FotoComunidade 
                           ORDER BY COUNT(mc.UsuarioID) DESC");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $suggestedCommunities = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Silenciar erro para não poluir a interface
}

// Buscar usuários para a seção "Quem seguir"
$suggestedUsers = [];
try {
    $stmt = $conn->prepare("SELECT TOP 5 UsuarioID, NomeExibicao, NomeUsuario, FotoPerfil 
                           FROM Usuarios 
                           WHERE UsuarioID != :currentUserId 
                           ORDER BY NEWID()");
    $stmt->bindParam(':currentUserId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $suggestedUsers = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Silenciar erro para não poluir a interface
}

// Funções auxiliares para formatação
function formatarData($data)
{
    if (empty($data)) {
        return '';
    }
    $d = new DateTime($data);
    $now = new DateTime();
    $interval = $now->diff($d);

    if ($interval->y > 0) {
        return $interval->y . 'a';
    } elseif ($interval->m > 0) {
        return $interval->m . 'm';
    } elseif ($interval->d > 0) {
        return $interval->d . 'd';
    } elseif ($interval->h > 0) {
        return $interval->h . 'h';
    } elseif ($interval->i > 0) {
        return $interval->i . 'min';
    } else {
        return 'agora';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunidades - Zuno</title>
    <script src="https://kit.fontawesome.com/17dd42404d.js" crossorigin="anonymous"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Urbanist', sans-serif;
            line-height: 1.6;
        }

        .logo-icon {
            max-height: 32px;
            width: auto;
        }

        .community-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="flex min-h-screen bg-gray-100 text-gray-900">
    <div class="bg-white rounded-lg shadow-md">
        <h2 class="text-xl font-bold p-4 border-b border-gray-200">Tendências para você</h2>
        <div class="p-4">
            <div class="flex flex-col space-y-4">
                <a href="#" class="group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs text-gray-500">Tendência em Brasil</p>
                            <p class="font-bold group-hover:text-[#16ac63]">#ZunoUpdate</p>
                            <p class="text-xs text-gray-500">1.2K Zuns</p>
                        </div>
                        <button class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                    </div>
                </a>
                <a href="#" class="group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs text-gray-500">Tecnologia · Tendência</p>
                            <p class="font-bold group-hover:text-[#16ac63]">#NovoRecurso</p>
                            <p class="text-xs text-gray-500">5.4K Zuns</p>
                        </div>
                        <button class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                    </div>
                </a>
                <a href="#" class="group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs text-gray-500">Tendência em Brasil</p>
                            <p class="font-bold group-hover:text-[#16ac63]">#ComunidadeZuno</p>
                            <p class="text-xs text-gray-500">3.1K Zuns</p>
                        </div>
                        <button class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                    </div>
                </a>
                <a href="#" class="group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs text-gray-500">Entretenimento · Tendência</p>
                            <p class="font-bold group-hover:text-[#16ac63]">#EventoGaming</p>
                            <p class="text-xs text-gray-500">8.7K Zuns</p>
                        </div>
                        <button class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                    </div>
                </a>
            </div>
            <a href="#" class="text-[#16ac63] text-sm mt-4 block hover:underline">Mostrar mais</a>
        </div>
    </div>