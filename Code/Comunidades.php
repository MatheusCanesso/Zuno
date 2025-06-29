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

// --- Lógica para buscar Comunidades do Banco de Dados ---
$userCommunities = [];
try {
    // Esta consulta busca as comunidades às quais o usuário logado pertence.
    $stmt = $conn->prepare("SELECT c.ComunidadeID, c.NomeComunidade, c.Descricao, c.FotoComunidade, COUNT(mc.UsuarioID) AS Membros
                            FROM Comunidades c
                            LEFT JOIN MembrosComunidade mc ON c.ComunidadeID = mc.ComunidadeID
                            WHERE mc.UsuarioID = :userId
                            GROUP BY c.ComunidadeID, c.NomeComunidade, c.Descricao, c.FotoComunidade"); // Removido ComunidadeVerificada, DonoID, SiteWeb para GROUP BY
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userCommunities = $stmt->fetchAll(PDO::FETCH_OBJ);
    error_log("Comunidades do usuário carregadas: " . count($userCommunities)); // Log para depuração

} catch (PDOException $e) {
    $message .= '<p style="color: red;">Erro ao carregar minhas comunidades: ' . $e->getMessage() . '</p>'; // Mensagem visível
    error_log("Erro ao carregar minhas comunidades: " . $e->getMessage()); // Log para depuração
}

$allCommunities = []; // Variável para TODAS as comunidades
try {
    // CONSULTA ATUALIZADA: Busca TODAS as comunidades da plataforma, ordenadas por nome.
    // ATENÇÃO: FotoPerfil (VARBINARY(MAX)) NÃO PODE ser usada no GROUP BY/ORDER BY diretamente.
    // Se a coluna FotoPerfil da tabela Comunidades for VARBINARY(MAX),
    // ela NÃO DEVE estar no SELECT ou GROUP BY se não for agregada.
    // Vou assumir que FotoComunidade é NVARCHAR para URL ou que você ajustou o DB para isso.
    $stmt = $conn->prepare("SELECT c.ComunidadeID, c.DonoID, c.Nome AS NomeComunidade, c.Descricao, c.SiteWeb, c.ComunidadeVerificada, 
                            COUNT(mc.UsuarioID) AS Membros,
                            -- Se FotoPerfil da comunidade for VARBINARY(MAX) no DB, você precisa lidar com ela separadamente ou buscar por URL
                            -- Por simplicidade e para evitar erros no GROUP BY, vou selecionar FotoPerfil e FotoCapa apenas se você tem URLs ou sabe como exibir o binário sem erro na query principal
                            -- Assumindo FotoComunidade aqui é um path/URL se for exibida diretamente. Se for VARBINARY, precisaria de base64_encode no PHP
                            c.FotoPerfil AS FotoComunidade, -- Assumindo alias para FotoComunidade para corresponder ao HTML
                            c.FotoCapa AS FotoCapaComunidade -- Assumindo alias
                            FROM Comunidades c
                            LEFT JOIN MembrosComunidade mc ON c.ComunidadeID = mc.ComunidadeID
                            GROUP BY c.ComunidadeID, c.DonoID, c.Nome, c.Descricao, c.SiteWeb, c.ComunidadeVerificada, c.FotoPerfil, c.FotoCapa -- Inclua FotoPerfil e FotoCapa se elas forem string/URL
                            ORDER BY c.Nome ASC"); 
    $stmt->execute();
    $allCommunities = $stmt->fetchAll(PDO::FETCH_OBJ);
    error_log("Todas as comunidades carregadas: " . count($allCommunities)); // Log para depuração

} catch (PDOException $e) {
    $message .= '<p style="color: red;">Erro ao carregar comunidades da plataforma: ' . $e->getMessage() . '</p>'; // Mensagem visível
    error_log("Erro ao carregar todas as comunidades: " . $e->getMessage()); // Log para depuração
}
// --- Fim da Lógica de Comunidades ---


// Buscar usuários para a seção "Quem seguir" na barra lateral direita
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

        /* Custom styles for icon hover effect (mantido do Radar.php) */
        .icon-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px; /* Adjust size as needed */
            height: 40px; /* Adjust size as needed */
            border-radius: 50%;
            transition: background-color 0.2s ease-in-out;
        }

        .icon-button:hover {
            background-color: rgba(0, 153, 255, 0.1); /* Azul transparente */
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
                            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1" stroke-dasharray="4 2" />
                            <circle cx="12" cy="12" r="2" fill="currentColor" />
                        </svg>
                    </span> Radar
                </a>

                <a href="#"
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
                                    <a href="#"
                                        class="flex items-center p-2 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                                        <img src="<?php echo htmlspecialchars($community->FotoComunidade); ?>" alt="Foto da Comunidade" class="w-8 h-8 rounded-full object-cover mr-3">
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
        <div class="flex-1 mx-auto">
            <main class="flex-1 mx-auto border-x border-gray-200">
                <div class="sticky top-0 bg-white z-10 p-4 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">Comunidades</h1>
                        <p class="text-gray-500 text-sm mt-1">Conecte-se com pessoas que compartilham seus interesses</p>
                    </div>
                    <button class="bg-[#21fa90] text-white font-semibold py-2 px-4 rounded-full hover:bg-[#83ecb9] transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i> Criar Comunidade
                    </button>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="message-box p-4 bg-red-100 text-red-700 rounded-lg mb-4">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="p-4 border-b border-gray-200 bg-white">
                    <div class="relative">
                        <input type="text" placeholder="Buscar comunidades..."
                            class="w-full pl-10 pr-4 py-2 rounded-full bg-gray-200 text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#21fa90] focus:bg-white">
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

                <?php if (!empty($userCommunities)): ?>
                <div class="bg-white mt-4 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold p-4 border-b border-gray-200">Minhas Comunidades</h2>
                    <?php foreach ($userCommunities as $community): ?>
                        <div class="flex items-center p-4 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition-colors duration-200">
                            <img src="<?php echo htmlspecialchars($community->FotoComunidade); ?>" alt="Foto da Comunidade" class="w-12 h-12 rounded-full object-cover mr-4">
                            <div class="flex-1">
                                <h3 class="font-bold text-lg"><?php echo htmlspecialchars($community->NomeComunidade); ?></h3>
                                <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($community->Descricao); ?></p>
                                <p class="text-gray-500 text-xs mt-1"><?php echo htmlspecialchars($community->Membros); ?> membros</p>
                            </div>
                            <a href="ComunidadePage.php?id=<?php echo $community->ComunidadeID; ?>" class="bg-blue-500 text-white text-sm font-semibold py-1.5 px-4 rounded-full hover:bg-blue-600 transition-colors duration-200">
                                Ver
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="bg-white mt-4 rounded-lg shadow-md p-8">
                        <div class="text-center py-8">
                            <div class="mx-auto w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                                <i class="fas fa-users text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-lg font-bold mb-2">Você ainda não faz parte de nenhuma comunidade</h3>
                            <p class="text-gray-500 mb-4">Junte-se a uma comunidade para começar a interagir</p>
                            <button class="bg-[#21fa90] text-white font-bold py-2 px-6 rounded-full hover:bg-[#83ecb9] transition-colors duration-200">
                                Explorar comunidades
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bg-white mt-4 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold p-4 border-b border-gray-200">Comunidades para Você</h2>
                    <?php if (!empty($allCommunities)): // Variável renomeada para allCommunities ?>
                        <?php foreach ($allCommunities as $community): ?>
                            <div class="flex items-center p-4 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition-colors duration-200">
                                <img src="<?php echo ($community->FotoComunidade ? 'data:image/jpeg;base64,' . base64_encode($community->FotoComunidade) : '../Design/Assets/default_community.png'); ?>" alt="Foto da Comunidade" class="w-12 h-12 rounded-full object-cover mr-4">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <h3 class="font-bold text-lg"><?php echo htmlspecialchars($community->NomeComunidade); ?></h3>
                                        <?php if ($community->ComunidadeVerificada): ?>
                                            <span class="verified-icon ml-2" title="Comunidade Verificada">
                                                <i class="fas fa-check"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($community->Descricao); ?></p>
                                    <p class="text-gray-500 text-xs mt-1"><?php echo htmlspecialchars($community->Membros); ?> membros</p>
                                </div>
                                <a href="ComunidadePage.php?id=<?php echo $community->ComunidadeID; ?>" class="bg-gray-900 text-white text-sm font-semibold py-1.5 px-4 rounded-full hover:bg-gray-700 transition-colors duration-200">
                                    Ver
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="bg-white mt-4 rounded-lg shadow-md p-8 text-center text-gray-600">
                            <div class="mx-auto w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                                <i class="fas fa-compass text-gray-400 text-3xl"></i>
                            </div>
                            <p class="text-gray-500">Nenhuma comunidade encontrada na plataforma.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </main>
        </div>

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

    <div id="imageModal" class="modal-overlay">
        <div class="modal-content">
            <button id="closeModal" class="modal-close-button"><i class="fas fa-times"></i></button>
            <img src="" alt="Foto de Perfil Expandida" class="modal-image" id="expandedProfilePic">
        </div>
    </div>

</body>

</html>