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
    <link rel="stylesheet" href="src/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Urbanist', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <!-- <nav class="navbar">
        <div class="navbar-logo">
            <a href="Index.php"><img src="../Design/Assets/logotipo_H.png" alt="Logo Zuno" class="logo-icon"></a>
        </div>
        <div class="nav-links">
            <a href="index.php">Início</a>
            <a href="sobre.php">Sobre</a>
            <a href="logout.php" class="cta-button">Sair</a>
        </div>
    </nav> -->
    <nav class="navbar">
        <div class="navbar-logo">
            <a href="Index.php"><img src="../Design/Assets/logotipo_H.png" alt="Logo Zuno" class="logo-icon"></a>
        </div>
        <div class="nav-links">
            <a href="index.php">Início</a>
            <a href="sobre.php">Sobre</a>
            <a href="logout.php" class="cta-button">Sair</a>
        </div>
    </nav>

    <div class="profile-container">
        <?php if (!empty($message)): ?>
            <div class="message-box">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($userData): ?>
            <div class="cover-photo-container" style="background-image: url('<?php echo ($userData->FotoCapa ? 'data:image/jpeg;base64,' . base64_encode($userData->FotoCapa) : '../Design/Assets/default_cover.png'); ?>');">
                <a href="Editar-Perfil.php" class="edit-profile-button">
                    <i class="fas fa-edit"></i> Editar Perfil
                </a>
            </div>
            <div class="profile-header">
                <img src="<?php echo ($userData->FotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($userData->FotoPerfil) : '../Design/Assets/default_profile.png'); ?>" alt="Foto de Perfil" class="profile-picture">
                <div class="user-info">
                    <h1><?php echo htmlspecialchars($userData->NomeExibicao); ?></h1>
                    <p class="username">@<?php echo htmlspecialchars($userData->NomeUsuario); ?></p>
                    <p class="bio"><?php echo htmlspecialchars($userData->Biografia ?? 'Nenhuma biografia adicionada.'); ?></p>

                    <div class="profile-details">
                        <?php if (!empty($userData->Localizacao)): ?>
                            <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($userData->Localizacao); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($userData->SiteWeb)): ?>
                            <div><i class="fas fa-link"></i> <a href="<?php echo htmlspecialchars($userData->SiteWeb); ?>" target="_blank"><?php echo htmlspecialchars($userData->SiteWeb); ?></a></div>
                        <?php endif; ?>
                        <?php if (!empty($userData->DataNascimento)): ?>
                            <div><i class="fas fa-cake-candles"></i> Nascido(a) em <?php echo formatarDataNascimento($userData->DataNascimento); ?></div>
                        <?php endif; ?>
                        <div><i class="fas fa-calendar-alt"></i> Zunando desde <?php echo formatarDataCriacao($userData->DataCriacao); ?></div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <p style="text-align: center; padding: 20px;">Não foi possível carregar as informações do perfil.</p>
        <?php endif; ?>
    </div>  
</body>
</html>