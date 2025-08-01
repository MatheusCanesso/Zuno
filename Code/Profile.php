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
    // Buscar todas as informações do usuário, incluindo as imagens binárias
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

// Funções auxiliares para formatação
function formatarDataNascimento($data)
{
    if (empty($data)) {
        return 'Não informada';
    }
    $d = new DateTime($data);
    return $d->format('d/m/Y');
}

function formatarDataCriacao($data)
{
    if (empty($data)) {
        return 'Não informada';
    }
    $d = new DateTime($data);
    $formatter = new IntlDateFormatter(
        'pt_BR',
        IntlDateFormatter::LONG,
        IntlDateFormatter::NONE,
        'America/Sao_Paulo', // Considerando o fuso horário do projeto
        IntlDateFormatter::GREGORIAN,
        'MMMM yyyy' // Adicionado 'yyyy' para incluir o ano
    );
    return ucfirst($formatter->format($d));
}

// Buscar usuários para a seção "Quem seguir"
$suggestedUsers = [];
try {
    $stmt = $conn->prepare("SELECT UsuarioID, NomeExibicao, NomeUsuario, FotoPerfil FROM Usuarios WHERE UsuarioID != :currentUserId ORDER BY RANDOM() LIMIT 5"); // ORDER BY RANDOM() para sugestões variadas
    $stmt->bindParam(':currentUserId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $suggestedUsers = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Em um ambiente de produção, você pode logar o erro em vez de exibi-lo ao usuário.
    // $message .= '<p style="color: red;">Erro ao carregar sugestões de usuários: ' . $e->getMessage() . '</p>';
}

// Buscar comunidades que o usuário faz parte
$userCommunities = [];
try {
    $stmt = $conn->prepare("SELECT c.NomeComunidade FROM Comunidades c JOIN MembrosComunidade mc ON c.ComunidadeID = mc.ComunidadeID WHERE mc.UsuarioID = :userId");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userCommunities = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Em um ambiente de produção, você pode logar o erro em vez de exibi-lo ao usuário.
    // $message .= '<p style="color: red;">Erro ao carregar comunidades: ' . $e->getMessage() . '</p>';
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Zuno</title>
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

        /* Estilo para o modal de imagem */
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
                    <!-- <i class="fas fa-home mr-3"></i> Radar -->
                    <span class="icon mr-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Círculo externo -->
                            <circle cx="12" cy="12" r="10" stroke="oklch(37.3% 0.034 259.733)" stroke-width="2" />

                            <!-- Linha girando -->
                            <path d="M12 12L20 7" stroke="oklch(37.3% 0.034 259.733)" stroke-width="2"
                                stroke-linecap="round" />

                            <!-- Pulsos concêntricos -->
                            <circle cx="12" cy="12" r="4" stroke="oklch(37.3% 0.034 259.733)" stroke-width="1"
                                stroke-dasharray="4 2" />
                            <circle cx="12" cy="12" r="2" fill="oklch(37.3% 0.034 259.733)" />
                        </svg>
                    </span> Radar
                </a>

                <a href="#"
                    class="flex items-center p-2 text-lg font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                    <!-- <i class="fas fa-search mr-3"></i> -->
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
                        <!-- <i class="fas fa-bell mr-3"></i> -->
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
                    <!-- <i class="fas fa-envelope mr-3"></i>  -->
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
                    <!-- <i class="fas fa-bookmark mr-3"></i>  -->
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

                <a href="#"
                    class="flex items-center p-2 text-lg font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                    <i class="fas fa-list mr-3"></i> Listas
                </a>

                <!-- <a href="Profile.php"
                    class="flex items-center p-2 text-lg font-semibold text-[#21fa90] rounded-lg hover:bg-blue-100 transition-colors duration-200">
                    <i class="fas fa-user mr-3"></i> Perfil
                </a> -->

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
                    <!-- Círculo de fundo com gradiente sutil -->
                    <circle cx="12" cy="12" r="11" fill="url(#zuneGradient)" stroke="currentColor" stroke-width="1.5" />

                    <!-- Símbolo "+" estilizado -->
                    <path d="M12 7V17M17 12H7" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" />

                    <!-- Efeito de ondas circulares -->
                    <circle cx="12" cy="12" r="6" stroke="currentColor" stroke-width="1" stroke-opacity="0.5"
                        stroke-dasharray="2,2" />
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="0.8" stroke-opacity="0.3"
                        stroke-dasharray="1,2" />

                    <!-- Gradiente -->
                    <defs>
                        <linearGradient id="zuneGradient" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="currentColor" stop-opacity="0.1" />
                            <stop offset="100%" stop-color="currentColor" stop-opacity="0.3" />
                        </linearGradient>
                    </defs>
                </svg>
            </button>

            <div class="bg-white rounded-lg shadow-md">
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
                                        <i class="fas fa-users mr-3 text-gray-700"></i>
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

        <div
            class="mt-auto flex items-center bg-gray-200 justify-between p-3 rounded-lg hover:bg-gray-300 transition-colors duration-200 cursor-pointer">
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
        </div>
    </nav>

    <div class="flex flex-1 pl-64">
        <main class="flex-1 mx-auto border-x border-gray-200">
            <div class="relative p-4 border-b border-gray-200"> <input type="text" placeholder="Pesquisar no Zuno"
                    class="w-full pl-10 pr-4 py-2 rounded-full bg-gray-200 text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#21fa90] focus:bg-white">
                <div class="absolute inset-y-0 left-0 pl-7 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-500"></i>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message-box p-4 bg-red-100 text-red-700 rounded-lg mb-4">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($userData): ?>
                <div class="relative">
                    <div class="cover-photo-container h-48 w-full bg-gray-200 relative bg-cover bg-center"
                        style="background-image: url('<?php echo ($userData->FotoCapa ? 'data:image/jpeg;base64,' . base64_encode($userData->FotoCapa) : '../Design/Assets/default_cover.png'); ?>');">
                        <a href="Editar-Perfil.php"
                            class="absolute top-4 right-4 bg-black bg-opacity-50 text-white px-4 py-2 rounded-full border border-gray-600 hover:bg-opacity-70 flex items-center space-x-2 text-sm">
                            <i class="fas fa-edit"></i> <span>Editar Perfil</span>
                        </a>
                    </div>

                    <div class="profile-header px-4 pb-4">
                        <div class="absolute top-28 left-4 border-4 border-white rounded-full shadow-md cursor-pointer z-10"
                            id="profilePicContainer">
                            <img src="<?php echo ($userData->FotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($userData->FotoPerfil) : '../Design/Assets/default_profile.png'); ?>"
                                alt="Foto de Perfil" class="w-32 h-32 rounded-full object-cover" id="profilePic">
                        </div>

                        <div class="pt-20">
                            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($userData->NomeExibicao); ?></h1>
                            <p class="text-gray-500">@<?php echo htmlspecialchars($userData->NomeUsuario); ?></p>
                            <p class="my-3 text-gray-700">
                                <?php echo htmlspecialchars($userData->Biografia ?? 'Nenhuma biografia adicionada.'); ?>
                            </p>

                            <div class="profile-details text-gray-600 text-sm space-y-1">
                                <?php if (!empty($userData->Localizacao)): ?>
                                    <div class="flex items-center space-x-2"><i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($userData->Localizacao); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($userData->SiteWeb)): ?>
                                    <div class="flex items-center space-x-2"><i class="fas fa-link"></i> <a
                                            href="<?php echo htmlspecialchars($userData->SiteWeb); ?>" target="_blank"
                                            class="text-blue-500 hover:underline"><?php echo htmlspecialchars($userData->SiteWeb); ?></a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($userData->DataNascimento)): ?>
                                    <div class="flex items-center space-x-2"><i class="fas fa-cake-candles"></i>
                                        <span>Nascido(a) em
                                            <?php echo formatarDataNascimento($userData->DataNascimento); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="flex items-center space-x-2"><i class="fas fa-calendar-alt"></i> <span>Zunando
                                        desde <?php echo formatarDataCriacao($userData->DataCriacao); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-center p-5 text-gray-600">Não foi possível carregar as informações do perfil.</p>
            <?php endif; ?>
        </main>

        <aside class="w-80 p-6 space-y-6 border border-gray-200 rounded-lg">
            <div class="bg-white rounded-lg shadow-md">
                <h2 class="text-xl font-bold p-4 border-b border-gray-200">Quem seguir</h2>
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

            <div class="bg-white rounded-lg shadow-md">
                <h2 class="text-xl font-bold p-4 border-b border-gray-200">Suas Comunidades</h2>
                <div class="p-4">
                    <?php if (!empty($userCommunities)): ?>
                        <ul>
                            <?php foreach ($userCommunities as $community): ?>
                                <li class="mb-2 last:mb-0">
                                    <a href="#"
                                        class="flex items-center p-2 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                                        <i class="fas fa-users mr-3 text-gray-700"></i>
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
            const profilePicContainer = document.getElementById('profilePicContainer');
            const profilePic = document.getElementById('profilePic');
            const imageModal = document.getElementById('imageModal');
            const expandedProfilePic = document.getElementById('expandedProfilePic');
            const closeModalButton = document.getElementById('closeModal');

            if (profilePicContainer && profilePic && imageModal && expandedProfilePic && closeModalButton) {
                profilePicContainer.addEventListener('click', function () {
                    expandedProfilePic.src = profilePic.src; // Define a imagem do popup com a src da foto de perfil
                    imageModal.classList.add('active'); // Ativa a visibilidade do modal
                });

                closeModalButton.addEventListener('click', function () {
                    imageModal.classList.remove('active'); // Desativa a visibilidade do modal
                });

                // Fechar o modal ao clicar no overlay (fora da imagem)
                imageModal.addEventListener('click', function (event) {
                    if (event.target === imageModal) { // Verifica se o clique foi diretamente no overlay
                        imageModal.classList.remove('active');
                    }
                });
            }
        });
    </script>
</body>

</html>