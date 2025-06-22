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
function formatarDataNascimento($data) {
    if (empty($data)) {
        return 'Não informada';
    }
    $d = new DateTime($data);
    return $d->format('d/m/Y');
}

function formatarDataCriacao($data) {
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
        'MMMM yyyy'
    );
    return ucfirst($formatter->format($d));
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
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
    </style>
</head>
<body class="bg-white text-black flex">
    <!-- Barra de navegação vertical -->
    <nav class="w-64 h-screen fixed flex flex-col border-r border-gray-800 p-4">
        <!-- Logo -->
        <div class="flex items-center justify-center rounded-full p-2 mb-4">
            <a href="Index.php">
                <img src="../Design/Assets/logotipo_H.png" alt="Logo Zuno">
            </a>
        </div>
        
        <!-- Itens do menu -->
        <div class="flex flex-col space-y-2 flex-grow">
            <a href="index.php" class="flex items-center space-x-4 p-3 rounded-lg hover:bg-gray-100">
                <i class="fas fa-home w-6 h-6 text-gray-700"></i>
                <span class="text-xl text-gray-700">Início</span>
            </a>
            
            <a href="#" class="flex items-center space-x-4 p-3 rounded-lg hover:bg-gray-100">
                <i class="fas fa-search w-6 h-6 text-gray-700"></i>
                <span class="text-xl text-gray-700">Explorar</span>
            </a>
            
            <a href="#" class="flex items-center space-x-4 p-3 rounded-lg hover:bg-gray-100">
                <i class="fas fa-bell w-6 h-6 text-gray-700"></i>
                <span class="text-xl text-gray-700">Notificações</span>
            </a>
            
            <a href="#" class="flex items-center space-x-4 p-3 rounded-lg hover:bg-gray-100">
                <i class="fas fa-envelope w-6 h-6 text-gray-700"></i>
                <span class="text-xl text-gray-700">Mensagens</span>
            </a>
            
            <a href="#" class="flex items-center space-x-4 p-3 rounded-lg hover:bg-gray-100">
                <i class="fas fa-bookmark w-6 h-6 text-gray-700"></i>
                <span class="text-xl text-gray-700">Itens salvos</span>
            </a>
            
            <a href="#" class="flex items-center space-x-4 p-3 rounded-lg hover:bg-gray-100">
                <i class="fas fa-list w-6 h-6 text-gray-700"></i>
                <span class="text-xl text-gray-700">Listas</span>
            </a>
            
            <a href="Profile.php" class="flex items-center space-x-4 p-3 rounded-lg hover:bg-gray-100 font-bold">
                <i class="fas fa-user w-6 h-6 text-gray-700"></i>
                <span class="text-xl text-gray-700">Perfil</span>
            </a>
            
            <a href="#" class="flex items-center space-x-4 p-3 rounded-lg hover:bg-gray-100">
                <i class="fas fa-ellipsis-h w-6 h-6 text-gray-700"></i>
                <span class="text-xl text-gray-700">Mais</span>
            </a>
        </div>
        
        <!-- Botão de tweetar -->
        <button class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-4 rounded-lg mb-4">
            Zunear
        </button>
        
        <!-- Perfil do usuário -->
        <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-100">
            <div class="flex items-center space-x-2">
                <?php if ($userData): ?>
                    <img src="<?php echo ($userData->FotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($userData->FotoPerfil) : '../Design/Assets/default_profile.png'); ?>" alt="Foto de Perfil" class="w-10 h-10 rounded-full">
                    <div class="flex flex-col">
                        <span class="font-bold"><?php echo htmlspecialchars($userData->NomeExibicao); ?></span>
                        <span class="text-gray-500">@<?php echo htmlspecialchars($userData->NomeUsuario); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <i class="fas fa-ellipsis-h text-gray-500"></i>
        </div>
    </nav>

    <!-- Conteúdo principal -->
    <main class="ml-64 flex-1">
        <div class="profile-container max-w-2xl mx-auto">
            <?php if (!empty($message)): ?>
                <div class="message-box">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($userData): ?>
                <div class="cover-photo-container h-48 w-full bg-gray-800 relative" style="background-image: url('<?php echo ($userData->FotoCapa ? 'data:image/jpeg;base64,' . base64_encode($userData->FotoCapa) : '../Design/Assets/default_cover.png'); ?>'); background-size: cover; background-position: center;">
                    <a href="Editar-Perfil.php" class="absolute top-4 right-4 bg-black bg-opacity-50 text-white px-4 py-2 rounded-full border border-gray-600 hover:bg-opacity-70">
                        <i class="fas fa-edit"></i> Editar Perfil
                    </a>
                </div>
                
                <div class="profile-header px-4 relative">
                    <div class="absolute -top-16 left-4 border-4 border-black rounded-full">
                        <img src="<?php echo ($userData->FotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($userData->FotoPerfil) : '../Design/Assets/default_profile.png'); ?>" alt="Foto de Perfil" class="w-32 h-32 rounded-full object-cover">
                    </div>
                    
                    <div class="pt-20 pb-4">
                        <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($userData->NomeExibicao); ?></h1>
                        <p class="text-gray-500">@<?php echo htmlspecialchars($userData->NomeUsuario); ?></p>
                        <p class="my-3"><?php echo htmlspecialchars($userData->Biografia ?? 'Nenhuma biografia adicionada.'); ?></p>

                        <div class="profile-details text-gray-500 space-y-1">
                            <?php if (!empty($userData->Localizacao)): ?>
                                <div class="flex items-center space-x-2"><i class="fas fa-map-marker-alt"></i> <span><?php echo htmlspecialchars($userData->Localizacao); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($userData->SiteWeb)): ?>
                                <div class="flex items-center space-x-2"><i class="fas fa-link"></i> <a href="<?php echo htmlspecialchars($userData->SiteWeb); ?>" target="_blank" class="text-blue-500 hover:underline"><?php echo htmlspecialchars($userData->SiteWeb); ?></a></div>
                            <?php endif; ?>
                            <?php if (!empty($userData->DataNascimento)): ?>
                                <div class="flex items-center space-x-2"><i class="fas fa-cake-candles"></i> <span>Nascido(a) em <?php echo formatarDataNascimento($userData->DataNascimento); ?></span></div>
                            <?php endif; ?>
                            <div class="flex items-center space-x-2"><i class="fas fa-calendar-alt"></i> <span>Zunando desde <?php echo formatarDataCriacao($userData->DataCriacao); ?></span></div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-center p-5">Não foi possível carregar as informações do perfil.</p>
            <?php endif; ?>
        </div>  
    </main>
</body>
</html>