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
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap');

        :root {
            --primary: #21FA90;
            --blue-primary:rgb(0, 153, 255);
            --second: rgb(26, 189, 110);
            --bg-light: #F9FAFB;
            --bg-dark: #121212;
            --border: #E5E7EB;
            --text-dark: #000000;
            --text-light: #FFFFFF;
        }

        body {
            font-family: 'Urbanist', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 5%;
            border-bottom: 1px solid var(--border);
        }

        .navbar-logo {
            display: flex;
            align-items: center;
        }

        .navbar-logo .logo-icon {
            height: 30px;
            width: auto;
            margin-right: 10px;
        }

        .nav-links a {
            margin-left: 30px;
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .cta-button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(33, 250, 144, 0.4);
            color: white !important;
        }

        .profile-container {
            max-width: 900px;
            margin: 30px auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .cover-photo-container {
            width: 100%;
            height: 200px;
            background-color: #ccc; /* Placeholder */
            position: relative;
            background-size: cover;
            background-position: center;
        }
        .profile-header {
            padding: 20px 30px;
            display: flex;
            align-items: flex-end;
            position: relative;
            margin-top: -100px; /* Sobrepõe a foto de capa */
        }

        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 0 0 2px var(--primary); /* Borda gradiente simulada */
            object-fit: cover;
            background-color: #eee; /* Placeholder */
        }

        .user-info {
            margin-left: 20px;
            flex-grow: 1;
        }

        .user-info h1 {
            margin: 0;
            font-size: 2.2rem;
            color: var(--text-dark);
            line-height: 1.2;
        }

        .user-info .username {
            color: #555;
            font-size: 1.1rem;
            margin-top: 5px;
        }

        .user-info .bio {
            margin-top: 15px;
            font-size: 1rem;
            color: #333;
        }

        .profile-details {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
            color: #666;
            font-size: 0.95rem;
        }

        .profile-details div {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-details i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .message-box {
            text-align: center;
            margin: 20px;
            padding: 15px;
            background-color: #e6ffe6;
            border: 1px solid #a3e6a3;
            border-radius: 8px;
            color: #2e8b57;
            font-weight: 500;
        }
        
        .edit-profile-button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            position: absolute; /* Ajustado para melhor posicionamento no header */
            right: 30px;
            top: 20px;
        }

        .edit-profile-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33, 250, 144, 0.4);
        }

        footer {
            background-color: var(--bg-dark);
            color: var(--text-light);
            padding: 50px 5%;
            text-align: center;
            margin-top: 30px;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 30px;
        }

        .footer-links a {
            color: var(--text-light);
            text-decoration: none;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .social-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .social-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
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
                <a href="Setup_Profile.php" class="edit-profile-button">
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