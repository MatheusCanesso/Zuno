<?php
session_start();
date_default_timezone_set('America/Sao_Paulo'); // Define o fuso horário para consistência
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

require_once 'Configs/config.php';

// Redireciona se o usuário não estiver logado
if (!isset($_SESSION['user_id'])) {
    header("Location: Login-Cadastro.php?action=login");
    exit();
}

$userId = $_SESSION['user_id'];
$userData = null;
$community = null;
$communityOwner = null;
$communityMembers = [];
$isMember = false;
$message = ''; // Para mensagens de erro/sucesso na página

// Obtém o CommunityID da URL
$communityId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($communityId <= 0) {
    // Redireciona ou exibe erro se o ID da comunidade não for válido
    header("Location: Comunidades.php?status=invalid_id");
    exit();
}

try {
    // Buscar informações do usuário logado para a barra lateral
    $stmt = $conn->prepare("SELECT NomeExibicao, NomeUsuario, FotoPerfil FROM Usuarios WHERE UsuarioID = :userId");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_OBJ);

    // Buscar informações da comunidade
    $stmt = $conn->prepare("SELECT ComunidadeID, DonoID, Nome, Descricao, SiteWeb, FotoPerfil, FotoCapa, ComunidadeVerificada, Privada, DataCriacao FROM Comunidades WHERE ComunidadeID = :communityId");
    $stmt->bindParam(':communityId', $communityId, PDO::PARAM_INT);
    $stmt->execute();
    $community = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$community) {
        // Redireciona se a comunidade não for encontrada
        header("Location: Comunidades.php?status=not_found");
        exit();
    }

    // Buscar informações do dono da comunidade
    $stmt = $conn->prepare("SELECT NomeExibicao, NomeUsuario, FotoPerfil FROM Usuarios WHERE UsuarioID = :ownerId");
    $stmt->bindParam(':ownerId', $community->DonoID, PDO::PARAM_INT);
    $stmt->execute();
    $communityOwner = $stmt->fetch(PDO::FETCH_OBJ);

    // Buscar membros da comunidade e verificar se o usuário logado é membro
    $stmt = $conn->prepare("SELECT mc.UsuarioID, u.NomeExibicao, u.NomeUsuario, u.FotoPerfil 
                            FROM MembrosComunidade mc 
                            JOIN Usuarios u ON mc.UsuarioID = u.UsuarioID 
                            WHERE mc.ComunidadeID = :communityId");
    $stmt->bindParam(':communityId', $communityId, PDO::PARAM_INT);
    $stmt->execute();
    $communityMembers = $stmt->fetchAll(PDO::FETCH_OBJ);

    foreach ($communityMembers as $member) {
        if ($member->UsuarioID === $userId) {
            $isMember = true;
            break;
        }
    }

    // --- Lógica para buscar Zuns da Comunidade ---
    // ATENÇÃO: A tabela Zuns no seu esquema atual NÃO TEM ComunidadeID.
    // Para exibir Zuns feitos *dentro* desta comunidade, você precisaria adicionar uma coluna ComunidadeID à tabela Zuns.
    // Por enquanto, esta query buscará Zuns postados pelo DONO da comunidade.
    $stmt = $conn->prepare("SELECT z.ZunID, z.Conteudo, z.DataCriacao, u.UsuarioID AS AutorID, u.NomeUsuario AS AutorNomeUsuario, u.NomeExibicao AS AutorNomeExibicao, 
                            CASE WHEN u.FotoPerfil IS NOT NULL THEN CAST(u.FotoPerfil AS VARBINARY(MAX)) ELSE NULL END AS AutorFotoPerfil,
                            (SELECT COUNT(*) FROM ZunLikes WHERE ZunID = z.ZunID) AS ZunLikes,
                            (SELECT COUNT(*) FROM Reposts WHERE ZunOriginalID = z.ZunID) AS Reposts,
                            (SELECT COUNT(*) FROM Zuns WHERE ZunPaiID = z.ZunID) AS Respostas,
                            (SELECT COUNT(*) FROM Midias WHERE ZunID = z.ZunID) AS MidiaCount,
                            (SELECT TOP 1 CAST(URL AS VARBINARY(MAX)) FROM Midias WHERE ZunID = z.ZunID AND Ordem = 0) AS MidiaURL_0,
                            (SELECT TOP 1 TipoMidia FROM Midias WHERE ZunID = z.ZunID AND Ordem = 0) AS MidiaTipo_0,
                            (SELECT TOP 1 CAST(URL AS VARBINARY(MAX)) FROM Midias WHERE ZunID = z.ZunID AND Ordem = 1) AS MidiaURL_1,
                            (SELECT TOP 1 TipoMidia FROM Midias WHERE ZunID = z.ZunID AND Ordem = 1) AS MidiaTipo_1,
                            (SELECT TOP 1 CAST(URL AS VARBINARY(MAX)) FROM Midias WHERE ZunID = z.ZunID AND Ordem = 2) AS MidiaURL_2,
                            (SELECT TOP 1 TipoMidia FROM Midias WHERE ZunID = z.ZunID AND Ordem = 2) AS MidiaTipo_2,
                            (SELECT TOP 1 CAST(URL AS VARBINARY(MAX)) FROM Midias WHERE ZunID = z.ZunID AND Ordem = 3) AS MidiaURL_3,
                            (SELECT TOP 1 TipoMidia FROM Midias WHERE ZunID = z.ZunID AND Ordem = 3) AS MidiaTipo_3
                            FROM Zuns z JOIN Usuarios u ON z.UsuarioID = u.UsuarioID 
                            WHERE z.UsuarioID = :ownerId -- Exibindo Zuns do Dono da Comunidade
                            ORDER BY z.DataCriacao DESC OFFSET 0 ROWS FETCH NEXT 20 ROWS ONLY");
    $stmt->bindParam(':ownerId', $community->DonoID, PDO::PARAM_INT);
    $stmt->execute();
    $communityZuns = $stmt->fetchAll(PDO::FETCH_OBJ);

} catch (PDOException $e) {
    $message .= '<p style="color: red;">Erro ao carregar dados da comunidade: ' . $e->getMessage() . '</p>';
    error_log("Erro ao carregar dados da ComunidadePage: " . $e->getMessage());
}

// Funções auxiliares (reaproveitadas do Radar.php)
function formatarDataZun($data)
{
    if (empty($data)) {
        return '';
    }
    $d = new DateTime($data);
    $now = new DateTime();
    $interval = $now->diff($d);
    if ($interval->y > 0) {
        return 'há ' . $interval->y . 'a';
    } elseif ($interval->m > 0) {
        return 'há ' . $interval->m . 'm';
    } elseif ($interval->d > 0) {
        return 'há ' . $interval->d . 'd';
    } elseif ($interval->h > 0) {
        return 'há ' . $interval->h . 'h';
    } elseif ($interval->i > 0) {
        return 'há ' . $interval->i . 'min';
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
    <title><?php echo htmlspecialchars($community->Nome ?? 'Comunidade'); ?> - Zuno</title>
    <script src="https://kit.fontawesome.com/17dd42404d.js" crossorigin="anonymous"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <style>
        body {
            font-family: 'Urbanist', sans-serif;
            line-height: 1.6;
        }

        .logo-icon {
            max-height: 32px;
            width: auto;
        }

        /* Classes para layout de imagens no Zun */
        .media-grid {
            display: grid;
            gap: 8px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .grid-1-image {
            grid-template-columns: 1fr;
        }

        .grid-2-images {
            grid-template-columns: 1fr 1fr;
        }

        .grid-3-images {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: auto auto;
        }

        .grid-3-images>img:first-child {
            grid-column: span 2;
            height: 250px;
        }

        .grid-3-images>img:nth-child(2),
        .grid-3-images>img:nth-child(3) {
            height: 150px;
        }

        .grid-4-images {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
        }

        .media-grid img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }

        .media-grid.grid-cols-2>img:nth-child(odd):not(:last-child) {
            border-right: 1px solid #e5e7eb;
        }

        .media-grid.grid-rows-2>img:nth-child(-n+2) {
            border-bottom: 1px solid #e5e7eb;
        }

        .media-grid.grid-cols-1>img {
            border: none;
        }

        /* Custom icon for verified community */
        .verified-icon {
            display: inline-block;
            width: 20px;
            height: 20px;
            background-color: #21FA90;
            /* Zuno Green */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            margin-left: 8px;
            flex-shrink: 0;
        }

        .verified-icon i {
            font-size: 10px;
            /* Smaller checkmark */
        }

        /* Estilo para o modal de imagem (reaproveitado do Radar.php) */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.75);
            /* Fundo preto transparente */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .modal-close-button {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            z-index: 1001;
            transition: color 0.2s ease;
        }

        .modal-close-button:hover {
            color: #ccc;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-900 flex min-h-screen">
    <!-- Barra de Navegação Lateral -->
    <nav class="w-64 fixed h-full bg-white border-r border-gray-200 p-4 flex flex-col justify-between">
        <div>
            <div class="mb-8 pl-2">
                <a href="Index.php">
                    <img src="../Design/Assets/logotipo_H.png" alt="Logo Zuno" class="logo-icon">
                </a>
            </div>
            <div class="flex flex-col space-y-2">
                <a href="Radar.php"
                    class="flex items-center p-2 text-lg font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                    <span class="icon mr-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" />
                            <path d="M12 12L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1"
                                stroke-dasharray="4 2" />
                            <circle cx="12" cy="12" r="2" fill="currentColor" />
                        </svg>
                    </span> Radar
                </a>
                <a href="Explorar.php"
                    class="flex items-center p-2 text-lg font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                    <span class="icon mr-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path
                                d="M14 11C14 12.6569 12.6569 14 11 14C9.34315 14 8 12.6569 8 11C8 9.34315 9.34315 8 11 8C12.6569 8 14 9.34315 14 11Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </span> Explorar
                </a>
                <a href="#"
                    class="flex items-center p-2 text-lg font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                    <span class="icon mr-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path
                                d="M13.73 21C13.5542 21.3031 13.3019 21.5545 12.9982 21.7295C12.6946 21.9045 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9045 11.0018 21.7295C10.6981 21.5545 10.4458 21.3031 10.27 21"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </span> Notificações
                </a>
                <a href="#"
                    class="flex items-center p-2 text-lg font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                    <span class="icon mr-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M21 11.5C21.0034 12.8199 20.6951 14.1219 20.1 15.3C19.3944 16.7117 18.3098 17.8992 16.9674 18.7293C15.6251 19.5594 14.0782 19.9994 12.5 20C11.1801 20.0034 9.87812 19.6951 8.7 19.1L3 21L4.9 15.3C4.30493 14.1219 3.99656 12.8199 4 11.5C4.00061 9.92176 4.44061 8.37485 5.27072 7.03255C6.10083 5.69026 7.28825 4.60557 8.7 3.9C9.87812 3.30493 11.1801 2.99656 12.5 3H13C15.0843 3.11499 17.053 3.99476 18.5291 5.47086C20.0052 6.94695 20.885 8.91565 21 11V11.5Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path d="M8 10H16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M8 14H12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                    </span> Mensagens
                </a>
                <a href="#"
                    class="flex items-center p-2 text-lg font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                    <span class="icon mr-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M19 21L12 16L5 21V5C5 4.46957 5.21071 3.96086 5.58579 3.58579C5.96086 3.21071 6.46957 3 7  3H17C17.5304 3 18.0391 3.21071 18.4142 3.58579C18.7893 3.96086 19 4.46957 19 5V21Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path d="M12 7V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M9 10H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                    </span> Itens salvos
                </a>
                <a href="Comunidades.php"
                    class="flex items-center p-2 text-lg font-semibold text-[#16ac63] rounded-lg hover:bg-gray-200 transition-colors duration-200">
                    <i class="fas fa-list mr-3"></i> Comunidades
                </a>
                <a href="#"
                    class="flex items-center p-2 text-lg font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                    <i class="fas fa-ellipsis-h mr-3"></i> Mais
                </a>
            </div>
            <button
                class="w-full mt-4 py-3 px-4 bg-[#21fa90] text-white font-bold rounded-lg hover:bg-[#83ecb9] transition-colors duration-200 flex items-center justify-center group">
                <span class="mr-2">Zunear</span>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                    class="group-hover:animate-pulse">
                    <circle cx="12" cy="12" r="11" fill="url(#zuneGradient)" stroke="currentColor" stroke-width="1.5" />
                    <path d="M12 7V17M17 12H7" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" />
                    <circle cx="12" cy="12" r="6" stroke="currentColor" stroke-width="1" stroke-opacity="0.5"
                        stroke-dasharray="2,2" />
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="0.8" stroke-opacity="0.3"
                        stroke-dasharray="1,2" />
                    <defs>
                        <linearGradient id="zuneGradient" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="currentColor" stop-opacity="0.1" />
                            <stop offset="100%" stop-color="currentColor" stop-opacity="0.3" />
                        </linearGradient>
                    </defs>
                </svg>
            </button>
            <div class="bg-white rounded-lg shadow-md mt-4">
                <h2 class="text-xl font-bold flex items-center p-4 border-b border-gray-200 gap-x-2">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16 19C16 16.7909 14.2091 15 12 15C9.79086 15 8 16.7909 8 19" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" />
                        <circle cx="12" cy="9" r="3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        <path d="M5 17C5 14.7909 6.79086 13 9 13" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" />
                        <circle cx="9" cy="7" r="2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        <path d="M19 17C19 14.7909 17.2091 13 15 13" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" />
                        <circle cx="15" cy="7" r="2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg> Suas Comunidades
                </h2>
                <div class="p-4">
                    <?php if (!empty($userCommunities)): ?>
                        <ul>
                            <?php foreach ($userCommunities as $community): ?>
                                <li class="mb-2 last:mb-0">
                                    <a href="ComunidadePage.php?id=<?php echo $community->ComunidadeID; ?>"
                                        class="flex items-center p-2 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                                        <img src="<?php echo ($community->FotoComunidade ? 'data:image/jpeg;base64,' . base64_encode($community->FotoComunidade) : '../Design/Assets/default_community.png'); ?>"
                                            alt="Foto da Comunidade" class="w-8 h-8 rounded-full object-cover mr-3">
                                        <span
                                            class="text-gray-700"><?php echo htmlspecialchars($community->NomeComunidade); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">Você ainda não faz parte de nenhuma comunidade.</p>
                    <?php endif; ?>
                </div>
            </div>
            </a>
    </nav>

    <!-- Conteúdo Principal da Página da Comunidade -->
    <div class="flex flex-1 ml-64">
        <div class="flex-1 mx-auto">
            <main class="flex-1 mx-auto border-x border-gray-200">
                <!-- Cabeçalho da Comunidade -->
                <div class="relative bg-white border-b border-gray-200 pb-4">
                    <!-- Adicionado pb-4 para espaço abaixo do conteúdo -->
                    <!-- Foto de Capa da Comunidade -->
                    <div class="w-full h-48 bg-gray-300 overflow-hidden relative">
                        <img src="<?php echo ($community->FotoCapa ? 'data:image/jpeg;base64,' . base64_encode($community->FotoCapa) : '../Design/Assets/default_cover.png'); ?>"
                            alt="Capa da Comunidade" class="w-full h-full object-cover">
                    </div>
                    <!-- Foto de Perfil da Comunidade -->

                    <div class="p-4 pt-4 pb-2"> <!-- Padding top ajustado para a foto de perfil -->
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center">
                                <div class="flex">
                                    <img id="communityProfilePic"
                                        src="<?php echo ($community->FotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($community->FotoPerfil) : '../Design/Assets/default_community.png'); ?>"
                                        alt="Foto da Comunidade"
                                        class="w-32 h-32 rounded-full border-4 border-white object-cover shadow-lg cursor-pointer">
                                </div>
                                <div class="flex-col items-left ml-4">
                                    <div class="flex items-center mb-2">
                                        <h1 class="text-3xl font-bold text-gray-900 mr-2">
                                            <?php echo htmlspecialchars($community->Nome ?? 'Comunidade'); ?></h1>
                                        <?php if ($community->ComunidadeVerificada): ?>
                                            <span class="verified-icon" title="Comunidade Verificada">
                                                <i class="fas fa-check"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-gray-600 text-md mb-2">
                                        <?php echo htmlspecialchars($community->Descricao ?? 'Nenhuma descrição.'); ?>
                                    </p>
                                    <?php if (!empty($community->SiteWeb)): ?>
                                        <p class="text-blue-500 text-sm mb-2"><i class="fas fa-link mr-1"></i> <a
                                                href="<?php echo htmlspecialchars($community->SiteWeb); ?>"
                                                target="_blank"><?php echo htmlspecialchars($community->SiteWeb); ?></a></p>
                                    <?php endif; ?>
                                    <div class="flex items-center text-gray-500 text-sm">
                                        <i class="fas fa-users mr-1"></i> <span><?php echo count($communityMembers); ?>
                                            membros</span>
                                        <span class="mx-2">&middot;</span>
                                        <span>Criada em
                                            <?php echo (new DateTime($community->DataCriacao))->format('d/m/Y'); ?></span>
                                        <?php if ($communityOwner): ?>
                                            <span class="mx-2">&middot;</span>
                                            <span>Por <span
                                                    class="font-semibold"><?php echo htmlspecialchars($communityOwner->NomeExibicao ?? 'Desconhecido'); ?></span></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($isMember): ?>
                                <button
                                    class="bg-red-500 text-white font-semibold py-2 px-4 rounded-full hover:bg-red-600 transition-colors duration-200">
                                    Sair da Comunidade
                                </button>
                            <?php else: ?>
                                <button
                                    class="bg-[#21fa90] text-white font-semibold py-2 px-4 rounded-full hover:bg-[#83ecb9] transition-colors duration-200">
                                    Entrar na Comunidade
                                </button>
                            <?php endif; ?>
                        </div>
                        <!-- <p class="text-gray-600 text-md mb-2"><?php echo htmlspecialchars($community->Descricao ?? 'Nenhuma descrição.'); ?></p>
                        <?php if (!empty($community->SiteWeb)): ?>
                            <p class="text-blue-500 text-sm mb-2"><i class="fas fa-link mr-1"></i> <a href="<?php echo htmlspecialchars($community->SiteWeb); ?>" target="_blank"><?php echo htmlspecialchars($community->SiteWeb); ?></a></p>
                        <?php endif; ?>
                        <div class="flex items-center text-gray-500 text-sm">
                            <i class="fas fa-users mr-1"></i> <span><?php echo count($communityMembers); ?> membros</span>
                            <span class="mx-2">&middot;</span>
                            <span>Criada em <?php echo (new DateTime($community->DataCriacao))->format('d/m/Y'); ?></span>
                            <?php if ($communityOwner): ?>
                                <span class="mx-2">&middot;</span>
                                <span>Por <span class="font-semibold"><?php echo htmlspecialchars($communityOwner->NomeExibicao ?? 'Desconhecido'); ?></span></span>
                            <?php endif; ?>
                        </div> -->
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="message-box p-4 bg-red-100 text-red-700 rounded-lg mb-4 mx-4 mt-4">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Seção de Zuns da Comunidade -->
                <div class="bg-white mt-4 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold p-4 border-b border-gray-200">Zuns da Comunidade</h2>
                    <?php if (!empty($communityZuns)): ?>
                        <?php foreach ($communityZuns as $zun): ?>
                            <div class="p-4 border-b border-gray-200 relative">
                                <div class="flex items-start space-x-3">
                                    <img src="<?php echo ($zun->AutorFotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($zun->AutorFotoPerfil) : '../Design/Assets/default_profile.png'); ?>"
                                        alt="Foto de Perfil" class="w-10 h-10 rounded-full object-cover mt-1">

                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-baseline space-x-1">
                                                <span
                                                    class="font-bold text-gray-900"><?php echo htmlspecialchars($zun->AutorNomeExibicao ?? 'Usuário Desconhecido'); ?></span>
                                                <span
                                                    class="text-gray-500 text-sm">@<?php echo htmlspecialchars($zun->AutorNomeUsuario ?? 'desconhecido'); ?>
                                                    &middot; <?php echo formatarDataZun($zun->DataCriacao); ?></span>
                                            </div>
                                            <button class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                        </div>

                                        <p class="text-gray-800 mt-2 mb-4" style="word-break: break-all;">
                                            <?php echo htmlspecialchars($zun->Conteudo ?? ''); ?></p>

                                        <?php if ($zun->MidiaCount > 0):
                                            $mediaUrls = [];
                                            for ($i = 0; $i < $zun->MidiaCount; $i++) {
                                                if (isset($zun->{"MidiaURL_" . $i}) && !empty($zun->{"MidiaURL_" . $i})) {
                                                    $mediaUrls[] = 'data:image/jpeg;base64,' . base64_encode($zun->{"MidiaURL_" . $i});
                                                }
                                            }
                                            $mediaCount = count($mediaUrls);
                                            ?>
                                            <div class="media-grid mt-2 mb-4 rounded-lg overflow-hidden border border-gray-200
                                                <?php if ($mediaCount === 1)
                                                    echo 'grid-1-image';
                                                else if ($mediaCount === 2)
                                                    echo 'grid-2-images';
                                                else if ($mediaCount === 3)
                                                    echo 'grid-3-images';
                                                else if ($mediaCount >= 4)
                                                    echo 'grid-4-images'; ?>">
                                                <?php foreach ($mediaUrls as $index => $url): ?>
                                                    <img src="<?php echo htmlspecialchars($url); ?>" alt="Mídia do Zun" class="<?php
                                                       if ($mediaCount === 1)
                                                           echo 'w-full h-auto max-h-96';
                                                       else if ($mediaCount === 3 && $index === 0)
                                                           echo 'col-span-2 h-64';
                                                       else
                                                           echo 'w-full h-40';
                                                       ?> object-cover <?php
                                                        if ($mediaCount > 1 && $index % 2 === 0 && $index < $mediaCount - 1 && ($mediaCount !== 3 || $index !== 0))
                                                            echo 'border-r border-gray-200';
                                                        if ($mediaCount > 2 && $index < $mediaCount - 2)
                                                            echo 'border-b border-gray-200';
                                                        ?>">
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="flex justify-between items-center text-gray-500 text-sm mt-4 px-4">
                                            <div class="flex items-center space-x-12">
                                                <button class="flex items-center space-x-1 hover:text-blue-500">
                                                    <i class="fas fa-reply"></i> <span><?php echo $zun->Respostas; ?></span>
                                                </button>
                                                <button class="flex items-center space-x-1 hover:text-lime-500">
                                                    <i class="fas fa-sync-alt"></i> <span><?php echo $zun->Reposts; ?></span>
                                                </button>
                                            </div>

                                            <div class="flex items-center space-x-12">
                                                <button class="flex items-center space-x-1 hover:text-gray-700">
                                                    <i class="fas fa-chart-bar"></i> <span><?php echo '0'; ?></span>
                                                </button>

                                                <button class="hover:text-gray-700 transition-colors duration-200">
                                                    <i class="far fa-bookmark"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        class="absolute right-4 text-gray-500 hover:text-red-500 flex flex-col items-center top-1/2 -translate-y-1/2">
                                        <i
                                            class="<?php echo ($zun->ZunLikadoPorMim ? 'fas fa-heart text-red-500' : 'far fa-heart'); ?> text-xl"></i>
                                        <span class="text-sm"><?php echo $zun->ZunLikes; ?></span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="bg-white mt-4 rounded-lg shadow-md p-8 text-center text-gray-600">
                            <div class="mx-auto w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                                <i class="fas fa-comment-slash text-gray-400 text-3xl"></i>
                            </div>
                            <p class="text-gray-500">Nenhum Zun encontrado nesta comunidade.</p>
                            <?php if ($isMember): ?>
                                <p class="text-gray-500 text-sm mt-2">Seja o primeiro a Zunar aqui!</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </main>
        </div>

        <!-- Sidebar Direita (Quem seguir e Membros da Comunidade) -->
        <aside class="w-80 p-6 space-y-6 border border-gray-200 rounded-lg">
            <!-- Card de Membros da Comunidade -->
            <div class="bg-white rounded-lg shadow-md">
                <h2 class="text-xl font-bold flex items-center p-4 border-b border-gray-200 gap-x-2">
                    <i class="fas fa-users text-gray-700"></i> Membros da Comunidade
                </h2>
                <div class="p-4">
                    <?php if (!empty($communityMembers)): ?>
                        <ul>
                            <?php foreach ($communityMembers as $member): ?>
                                <li class="flex items-center justify-between mb-3 last:mb-0">
                                    <div class="flex items-center space-x-3">
                                        <img src="<?php echo ($member->FotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($member->FotoPerfil) : '../Design/Assets/default_profile.png'); ?>"
                                            alt="Foto de Perfil" class="w-10 h-10 rounded-full object-cover">
                                        <div class="flex flex-col">
                                            <span
                                                class="font-bold text-gray-900"><?php echo htmlspecialchars($member->NomeExibicao ?? 'Usuário Desconhecido'); ?></span>
                                            <span
                                                class="text-gray-500 text-sm">@<?php echo htmlspecialchars($member->NomeUsuario ?? 'desconhecido'); ?></span>
                                        </div>
                                    </div>
                                    <?php if ($member->UsuarioID !== $userId): // Não mostrar botão para o próprio usuário ?>
                                        <button
                                            class="bg-gray-900 text-white text-sm font-semibold py-1.5 px-4 rounded-full hover:bg-gray-700 transition-colors duration-200">
                                            Seguir
                                        </button>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">Esta comunidade ainda não tem membros.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card Quem Seguir (mantido do Radar.php) -->
            <div class="bg-white rounded-lg shadow-md">
                <h2 class="text-xl font-bold flex items-center p-4 border-b border-gray-200 gap-x-2">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M12 11C13.6569 11 15 9.65685 15 8C15 6.34315 13.6569 5 12 5C10.3431 5 9  6.34315 9 8C9 9.65685 10.3431 11 12 11Z"
                            stroke="currentColor" stroke-width="1.8" />
                        <path d="M6 19C6 16.7909 7.79086 15 10 15H14C16.2091 15 18 16.7909 18 19" stroke="currentColor"
                            stroke-width="1.8" />
                        <path d="M18 8H22M20 6V10" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg> Quem seguir
                </h2>
                <div class="p-4">
                    <?php
                    // Lógica para buscar usuários sugeridos (mantida do Feed.php)
                    $suggestedUsers = [];
                    try {
                        $stmt = $conn->prepare("SELECT UsuarioID, NomeExibicao, NomeUsuario, FotoPerfil FROM Usuarios WHERE UsuarioID != :currentUserId ORDER BY NEWID()");
                        $stmt->bindParam(':currentUserId', $userId, PDO::PARAM_INT);
                        $stmt->execute();
                        $suggestedUsers = $stmt->fetchAll(PDO::FETCH_OBJ);
                    } catch (PDOException $e) {
                        // Logar erro em produção
                    }
                    ?>
                    <?php if (!empty($suggestedUsers)): ?>
                        <?php foreach ($suggestedUsers as $sUser): ?>
                            <div class="flex items-center justify-between mb-4 last:mb-0">
                                <div class="flex items-center space-x-3">
                                    <img src="<?php echo ($sUser->FotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($sUser->FotoPerfil) : '../Design/Assets/default_profile.png'); ?>"
                                        alt="Foto de Perfil" class="w-10 h-10 rounded-full object-cover">
                                    <div class="flex flex-col">
                                        <span
                                            class="font-bold text-gray-900"><?php echo htmlspecialchars($sUser->NomeExibicao); ?></span>
                                        <span
                                            class="text-gray-500 text-sm">@<?php echo htmlspecialchars($sUser->NomeUsuario); ?></span>
                                    </div>
                                </div>
                                <button
                                    class="bg-gray-900 text-white text-sm font-semibold py-1.5 px-4 rounded-full hover:bg-gray-700 transition-colors duration-200">
                                    Seguir
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">Nenhuma sugestão de usuário no momento.</p>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>

    <!-- Modal de Imagem (reaproveitado do Radar.php) -->
    <div id="imageModal" class="modal-overlay">
        <div class="modal-content">
            <button id="closeModal" class="modal-close-button"><i class="fas fa-times"></i></button>
            <img src="" alt="Foto de Perfil Expandida" class="modal-image" id="expandedProfilePic">
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const communityProfilePic = document.getElementById('communityProfilePic');
            const imageModal = document.getElementById('imageModal');
            const expandedProfilePic = document.getElementById('expandedProfilePic');
            const closeModalButton = document.getElementById('closeModal');

            if (communityProfilePic) {
                communityProfilePic.addEventListener('click', function () {
                    expandedProfilePic.src = this.src;
                    imageModal.classList.add('active');
                });
            }

            if (closeModalButton) {
                closeModalButton.addEventListener('click', function () {
                    imageModal.classList.remove('active');
                });
            }

            // Fechar modal clicando fora da imagem
            if (imageModal) {
                imageModal.addEventListener('click', function (e) {
                    if (e.target === imageModal) {
                        imageModal.classList.remove('active');
                    }
                });
            }
        });
    </script>
</body>

</html>