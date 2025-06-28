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
    // Buscar informações do usuário logado para a barra lateral
    $stmt = $conn->prepare("SELECT NomeExibicao, NomeUsuario, FotoPerfil FROM Usuarios WHERE UsuarioID = :userId");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$userData) {
        $message = '<p style="color: red;">Erro: Dados do usuário não encontrados.</p>';
    }

} catch (PDOException $e) {
    $message .= '<p style="color: red;">Erro ao carregar dados do usuário: ' . $e->getMessage() . '</p>';
}

// --- Lógica para buscar Tendências ---
$trendingTopics = [];
try {
    $stmt = $conn->prepare("SELECT TOP 10 Nome, Volume FROM Tendencias ORDER BY Volume DESC, DataRegistro DESC");
    $stmt->execute();
    $trendingTopics = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // error_log("Erro ao carregar tendências: " . $e->getMessage());
}

// --- Lógica para buscar Zuns Populares (da View ZunsPopulares) ---
$popularZuns = [];
try {
    $stmt = $conn->prepare("SELECT TOP 10 ZunID, Conteudo, DataCriacao, UsuarioID, NomeUsuario, NomeExibicao, ZunLikes, Reposts, Respostas FROM ZunsPopulares");
    $stmt->execute();
    $popularZuns = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // error_log("Erro ao carregar Zuns populares: " . $e->getMessage());
}

// --- Lógica para buscar Usuários Sugeridos (para a sidebar direita) ---
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

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explorar - Zuno</title>
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

        /* Estilo para o modal de imagem (se necessário) */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.75);
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

        /* Custom styles for icon hover effect */
        .icon-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            transition: background-color 0.2s ease-in-out;
        }

        .icon-button:hover {
            background-color: rgba(0, 153, 255, 0.1);
        }

        /* Card de Destaque para Tendência ou Zun Popular */
        .highlight-card {
            background: linear-gradient(135deg, rgba(33, 250, 144, 0.9), rgba(0, 209, 255, 0.9));
            color: white;
        }

        /* --- Estilos para Abas de Exploração --- */
        .explore-tab {
            color: #6b7280;
            /* text-gray-500 or text-gray-700 */
            font-weight: 600;
            /* font-semibold */
            position: relative;
            transition: color 0.2s ease, background-color 0.2s ease;
            cursor: pointer;
        }

        .explore-tab span {
            padding-bottom: 12px;
            /* Espaço para a linha de destaque */
            display: inline-block;
            /* Garante que o padding e a linha funcionem */
        }

        .explore-tab.active {
            color: #16ac63;
            /* Zuno green */
            font-weight: bold;
            /* font-bold */
        }

        .explore-tab.active span::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            /* Espessura da linha */
            background-color: #16ac63;
            /* Zuno green */
            border-radius: 9999px;
            /* rounded-full */
        }

        .explore-tab:hover:not(.active) {
            background-color: #f3f4f6;
            /* hover:bg-gray-100 */
            color: #1f2937;
            /* text-gray-900 on hover */
        }

        /* Conteúdo das abas */
        .tab-content {
            display: none;
            /* Esconde todos por padrão */
        }

        .tab-content.active {
            display: block;
            /* Mostra o conteúdo ativo */
        }
    </style>
</head>

<body class="flex min-h-screen ml-96 mr-96 bg-gray-100 text-gray-900">
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
                    class="flex items-center p-2 text-lg font-semibold text-[#16ac63] rounded-lg hover:bg-gray-200 transition-colors duration-200">
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
                    </span>
                    Mensagens
                </a>

                <a href="#"
                    class="flex items-center p-2 text-lg font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                    <span class="icon mr-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M19 21L12 16L5 21V5C5 4.46957 5.21071 3.96086 5.58579 3.58579C5.96086 3.21071 6.46957 3 7 3H17C17.5304 3 18.0391 3.21071 18.4142 3.58579C18.7893 3.96086 19 4.46957 19 5V21Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path d="M12 7V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M9 10H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>

                    </span> Itens salvos
                </a>

                <a href="Comunidades.php"
                    class="flex items-center p-2 text-lg font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
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
                    <?php
                    // Lógica para buscar comunidades do usuário (mantida do Comunidades.php)
                    $userCommunities = [];
                    try {
                        // Requer as tabelas 'Comunidades' e 'MembrosComunidade'
                        $stmt = $conn->prepare("SELECT c.ComunidadeID, c.NomeComunidade, c.Descricao, c.FotoComunidade, COUNT(mc.UsuarioID) AS Membros
                                                FROM Comunidades c
                                                JOIN MembrosComunidade mc ON c.ComunidadeID = mc.ComunidadeID
                                                WHERE mc.UsuarioID = :userId
                                                GROUP BY c.ComunidadeID, c.NomeComunidade, c.Descricao, c.FotoComunidade");
                        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                        $stmt->execute();
                        $userCommunities = $stmt->fetchAll(PDO::FETCH_OBJ);
                    } catch (PDOException $e) {
                        // Logar erro em produção
                    }
                    ?>
                    <?php if (!empty($userCommunities)): ?>
                        <ul>
                            <?php foreach ($userCommunities as $community): ?>
                                <li class="mb-2 last:mb-0">
                                    <a href="#"
                                        class="flex items-center p-2 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                                        <img src="<?php echo htmlspecialchars($community->FotoComunidade); ?>"
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
        </div>

        <a href="Profile.php"
            class="mt-auto flex items-center justify-between p-3 rounded-lg hover:bg-gray-300 transition-colors duration-200 cursor-pointer">
            <div class="flex items-center space-x-2">
                <?php if ($userData): ?>
                    <img src="<?php echo ($userData->FotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($userData->FotoPerfil) : '../Design/Assets/default_profile.png'); ?>"
                        alt="Foto de Perfil" class="w-10 h-10 rounded-full object-cover">
                    <div class="flex flex-col">
                        <span
                            class="font-bold text-gray-900"><?php echo htmlspecialchars($userData->NomeExibicao); ?></span>
                        <span class="text-gray-500 text-sm">@<?php echo htmlspecialchars($userData->NomeUsuario); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <i class="fas fa-ellipsis-h text-gray-500"></i>
        </a>
    </nav>

    <div class="flex flex-1 pl-64">
        <main class="flex-1 mx-auto border-x border-gray-200">
            <div class="sticky top-0 bg-white z-10 p-4 border-b border-gray-200 flex items-center justify-between">
                <h1 class="text-2xl font-bold">Explorar</h1>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message-box p-4 bg-red-100 text-red-700 rounded-lg mb-4">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="p-4 border-b border-gray-200 bg-white">
                <div class="relative">
                    <input type="text" placeholder="Pesquisar no Zuno..."
                        class="w-full pl-10 pr-4 py-2 rounded-full bg-gray-200 text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="icon text-gray-500">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z"
                                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <path
                                    d="M14 11C14 12.6569 12.6569 14 11 14C9.34315 14 8 12.6569 8 11C8 9.34315 9.34315 8 11 8C12.6569 8 14 9.34315 14 11Z"
                                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                </div>
            </div>

            <div class="sticky top-14 bg-white z-10 border-b border-gray-200">
                <div class="flex">
                    <a href="#para-voce" class="flex-1 py-4 text-center explore-tab active"
                        data-tab-content="para-voce-content">
                        <span class="relative inline-block px-4">Para você</span>
                    </a>
                    <a href="#tendencias" class="flex-1 py-4 text-center explore-tab"
                        data-tab-content="tendencias-content">
                        <span class="relative inline-block px-4">Tendências</span>
                    </a>
                    <a href="#noticias" class="flex-1 py-4 text-center explore-tab" data-tab-content="noticias-content">
                        <span class="relative inline-block px-4">Notícias</span>
                    </a>
                    <a href="#esportes" class="flex-1 py-4 text-center explore-tab" data-tab-content="esportes-content">
                        <span class="relative inline-block px-4">Esportes</span>
                    </a>
                    <a href="#entretenimento" class="flex-1 py-4 text-center explore-tab"
                        data-tab-content="entretenimento-content">
                        <span class="relative inline-block px-4">Entretenimento</span>
                    </a>
                </div>
            </div>

            <div id="para-voce-content" class="tab-content active">
                <div class="bg-white mt-4 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold p-4 border-b border-gray-200">Tendências para você</h2>
                    <?php if (!empty($trendingTopics)): ?>
                        <?php foreach ($trendingTopics as $index => $topic): ?>
                            <a href="#"
                                class="block p-4 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition-colors duration-200">
                                <div class="flex items-center">
                                    <span class="text-lg font-bold text-gray-400 mr-3"><?php echo $index + 1; ?></span>
                                    <div>
                                        <h3 class="font-bold text-lg text-gray-900">
                                            #<?php echo htmlspecialchars($topic->Nome); ?></h3>
                                        <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($topic->Volume); ?> Zuns
                                        </p>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-600">
                            <div class="mx-auto w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                                <i class="fas fa-hashtag text-gray-400 text-3xl"></i>
                            </div>
                            <p>Nenhuma tendência disponível no momento.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-white mt-4 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold p-4 border-b border-gray-200">Zuns Populares</h2>
                    <?php if (!empty($popularZuns)): ?>
                        <?php foreach ($popularZuns as $zun): ?>
                            <div
                                class="border-b border-gray-100 p-4 last:border-b-0 hover:bg-gray-50 transition-colors duration-200">
                                <div class="flex items-start space-x-3">
                                    <img src="<?php echo ($zun->FotoPerfilURL ? htmlspecialchars($zun->FotoPerfilURL) : '../Design/Assets/default_profile.png'); ?>"
                                        alt="Foto de Perfil" class="w-10 h-10 rounded-full object-cover mt-1">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-1">
                                            <span class="font-bold"><?php echo htmlspecialchars($zun->NomeExibicao); ?></span>
                                            <span
                                                class="text-gray-500 text-sm">@<?php echo htmlspecialchars($zun->NomeUsuario); ?></span>
                                            <span class="text-gray-500 text-sm">&middot;
                                                <?php echo (new DateTime($zun->DataCriacao))->format('d/m H:i'); ?></span>
                                        </div>
                                        <p class="text-gray-800 mt-1"><?php echo htmlspecialchars($zun->Conteudo); ?></p>
                                        <div class="flex justify-around items-center mt-3 text-gray-500 text-sm">
                                            <button class="flex items-center space-x-1 hover:text-blue-500">
                                                <i class="far fa-comment"></i> <span><?php echo $zun->Respostas; ?></span>
                                            </button>
                                            <button class="flex items-center space-x-1 hover:text-lime-500">
                                                <i class="fas fa-sync-alt"></i> <span><?php echo $zun->Reposts; ?></span>
                                            </button>
                                            <button class="flex items-center space-x-1 hover:text-red-500">
                                                <i class="far fa-heart"></i> <span><?php echo $zun->ZunLikes; ?></span>
                                            </button>
                                            <button class="flex items-center space-x-1 hover:text-blue-500">
                                                <i class="fas fa-share-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-600">
                            <div class="mx-auto w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                                <i class="fas fa-fire text-gray-400 text-3xl"></i>
                            </div>
                            <p>Nenhum Zun popular no momento. Comece a interagir!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="tendencias-content" class="tab-content hidden">
                <div class="bg-white mt-4 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold p-4 border-b border-gray-200">Tendências em Destaque</h2>
                    <?php if (!empty($trendingTopics)): ?>
                        <?php foreach ($trendingTopics as $index => $topic): ?>
                            <a href="#"
                                class="block p-4 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition-colors duration-200">
                                <div class="flex items-center">
                                    <span class="text-lg font-bold text-gray-400 mr-3"><?php echo $index + 1; ?></span>
                                    <div>
                                        <h3 class="font-bold text-lg text-gray-900">
                                            #<?php echo htmlspecialchars($topic->Nome); ?></h3>
                                        <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($topic->Volume); ?> Zuns
                                        </p>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-600">
                            <div class="mx-auto w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                                <i class="fas fa-hashtag text-gray-400 text-3xl"></i>
                            </div>
                            <p>Nenhuma tendência disponível no momento.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="noticias-content" class="tab-content hidden">
                <div class="bg-white mt-4 rounded-lg shadow-md p-8 text-center text-gray-600">
                    <div class="mx-auto w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                        <i class="fas fa-newspaper text-gray-400 text-3xl"></i>
                    </div>
                    <p>Nenhuma notícia disponível no momento.</p>
                </div>
            </div>

            <div id="esportes-content" class="tab-content hidden">
                <div class="bg-white mt-4 rounded-lg shadow-md p-8 text-center text-gray-600">
                    <div class="mx-auto w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                        <i class="fas fa-futbol text-gray-400 text-3xl"></i>
                    </div>
                    <p>Nenhum conteúdo de esportes disponível no momento.</p>
                </div>
            </div>

            <div id="entretenimento-content" class="tab-content hidden">
                <div class="bg-white mt-4 rounded-lg shadow-md p-8 text-center text-gray-600">
                    <div class="mx-auto w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                        <i class="fas fa-film text-gray-400 text-3xl"></i>
                    </div>
                    <p>Nenhum conteúdo de entretenimento disponível no momento.</p>
                </div>
            </div>
        </main>


        <aside class="w-80 p-6 space-y-6 border border-gray-200 rounded-lg">
            <div class="bg-white rounded-lg shadow-md">
                <h2 class="text-xl font-bold flex items-center p-4 border-b border-gray-200 gap-x-2">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M12 11C13.6569 11 15 9.65685 15 8C15 6.34315 13.6569 5 12 5C10.3431 5 9 6.34315 9 8C9 9.65685 10.3431 11 12 11Z"
                            stroke="currentColor" stroke-width="1.8" />
                        <path d="M6 19C6 16.7909 7.79086 15 10 15H14C16.2091 15 18 16.7909 18 19" stroke="currentColor"
                            stroke-width="1.8" />
                        <path d="M18 8H22M20 6V10" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg> Quem seguir
                </h2>
                <div class="p-4">
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

    <div id="imageModal" class="modal-overlay">
        <div class="modal-content">
            <button id="closeModal" class="modal-close-button"><i class="fas fa-times"></i></button>
            <img src="" alt="Foto de Perfil Expandida" class="modal-image" id="expandedProfilePic">
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('.explore-tab');
            const tabContents = document.querySelectorAll('.tab-content');

            function showTabContent(tabId) {
                // Esconde todo o conteúdo das abas
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });

                // Remove a classe 'active' de todas as abas
                tabs.forEach(tab => {
                    tab.classList.remove('active');
                });

                // Adiciona a classe 'active' à aba clicada
                const clickedTab = document.querySelector(`.explore-tab[data-tab-content="${tabId}"]`);
                if (clickedTab) {
                    clickedTab.classList.add('active');
                }

                // Mostra o conteúdo da aba correspondente
                const targetContent = document.getElementById(tabId);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            }

            // Adiciona evento de clique a cada aba
            tabs.forEach(tab => {
                tab.addEventListener('click', function (e) {
                    e.preventDefault();
                    const tabId = this.dataset.tabContent;
                    showTabContent(tabId);
                });
            });

            // Ativa a primeira aba por padrão ao carregar a página
            // Ou ativa a aba baseada na URL hash se presente
            const initialTabId = window.location.hash ? window.location.hash.substring(1) + '-content' : 'para-voce-content';
            showTabContent(initialTabId);
        });
    </script>

</body>

</html>