<?php
session_start();
require_once 'Configs/config.php';

// Redireciona se o usuário não estiver logado
if (!isset($_SESSION['user_id'])) {
    header("Location: Login-Cadastro.php?action=login");
    exit();
}

// Verifica se o ID do Zun foi passado na URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: Radar.php");
    exit();
}

$zunId = $_GET['id'];
$userId = $_SESSION['user_id'];
$userData = null;
$zunData = null;
$comments = [];
$message = '';

// Buscar informações do usuário logado
try {
    $stmt = $conn->prepare("SELECT NomeExibicao, NomeUsuario, FotoPerfil FROM Usuarios WHERE UsuarioID = :userId");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$userData) {
        $message = '<p style="color: red;">Erro: Dados do usuário não encontrados.</p>';
    }
} catch (PDOException $e) {
    $message = '<p style="color: red;">Erro ao carregar dados do perfil: ' . $e->getMessage() . '</p>';
}

// Buscar informações completas do Zun
try {
    $stmt = $conn->prepare("
        SELECT 
            z.ZunID,
            z.Conteudo,
            z.DataCriacao,
            u.UsuarioID AS AutorID,
            u.NomeExibicao AS AutorNomeExibicao,
            u.NomeUsuario AS AutorNomeUsuario,
            u.FotoPerfil AS AutorFotoPerfil,
            (SELECT COUNT(*) FROM Zuns WHERE ZunPaiID = z.ZunID) AS Respostas,
            (SELECT COUNT(*) FROM Zuns WHERE ZunOriginalID = z.ZunID AND TipoZun = 'repost') AS Reposts,
            (SELECT COUNT(*) FROM ZunLikes WHERE ZunID = z.ZunID) AS ZunLikes,
            (SELECT COUNT(*) FROM ZunLikes WHERE ZunID = z.ZunID AND UsuarioID = :userId) AS ZunLikadoPorMim,
            (SELECT COUNT(*) FROM Midias WHERE ZunID = z.ZunID) AS MidiaCount
        FROM Zuns z
        JOIN Usuarios u ON z.UsuarioID = u.UsuarioID
        WHERE z.ZunID = :zunId
    ");

    $stmt->bindParam(':zunId', $zunId, PDO::PARAM_INT);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $zunData = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$zunData) {
        $message = '<p style="color: red;">Zun não encontrado ou você não tem permissão para visualizá-lo.</p>';
    } else {
        // Buscar mídias separadamente
        $stmtMedia = $conn->prepare("
            SELECT MidiaID, URL, TipoMidia, Ordem 
            FROM Midias 
            WHERE ZunID = :zunId 
            ORDER BY Ordem
        ");
        $stmtMedia->bindParam(':zunId', $zunId, PDO::PARAM_INT);
        $stmtMedia->execute();
        $medias = $stmtMedia->fetchAll(PDO::FETCH_OBJ);

        // Adicionar as mídias ao objeto $zunData
        foreach ($medias as $i => $media) {
            $zunData->{"MidiaURL_" . $i} = $media->URL;
            $zunData->{"MidiaTipo_" . $i} = $media->TipoMidia;
        }
    }
} catch (PDOException $e) {
    $message = '<p style="color: red;">Erro ao carregar o Zun: ' . $e->getMessage() . '</p>';
}

// Buscar comentários do Zun
try {
    $stmt = $conn->prepare("
        SELECT 
            c.ZunID AS ComentarioID,
            c.Conteudo,
            c.DataCriacao,
            u.UsuarioID AS AutorID,
            u.NomeExibicao AS AutorNomeExibicao,
            u.NomeUsuario AS AutorNomeUsuario,
            u.FotoPerfil AS AutorFotoPerfil,
            (SELECT COUNT(*) FROM ZunLikes WHERE ZunID = c.ZunID) AS ZunLikes,
            (SELECT COUNT(*) FROM ZunLikes WHERE ZunID = c.ZunID AND UsuarioID = :userId) AS ZunLikadoPorMim,
            (SELECT COUNT(*) FROM Midias WHERE ZunID = c.ZunID) AS MidiaCount
        FROM Zuns c
        JOIN Usuarios u ON c.UsuarioID = u.UsuarioID
        WHERE c.ZunPaiID = :zunId
        ORDER BY c.DataCriacao DESC
    ");

    $stmt->bindParam(':zunId', $zunId, PDO::PARAM_INT);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $comments = $stmt->fetchAll(PDO::FETCH_OBJ);

    // Buscar mídias para cada comentário
    foreach ($comments as $comment) {
        $stmtMedia = $conn->prepare("
            SELECT MidiaID, URL, TipoMidia, Ordem 
            FROM Midias 
            WHERE ZunID = :comentarioId 
            ORDER BY Ordem
        ");
        $stmtMedia->bindParam(':comentarioId', $comment->ComentarioID, PDO::PARAM_INT);
        $stmtMedia->execute();
        $medias = $stmtMedia->fetchAll(PDO::FETCH_OBJ);

        // Adicionar as mídias ao objeto $comment
        foreach ($medias as $i => $media) {
            $comment->{"MidiaURL_" . $i} = $media->URL;
            $comment->{"MidiaTipo_" . $i} = $media->TipoMidia;
        }
    }
} catch (PDOException $e) {
    $message .= '<p style="color: red;">Erro ao carregar comentários: ' . $e->getMessage() . '</p>';
}

// Função para formatar a data
function formatarDataZun($data)
{
    if (empty($data))
        return '';
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

// Processar novo comentário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_comment') {
    $conteudo = trim($_POST['conteudo_comment']);

    if (!empty($conteudo) || (isset($_FILES['midia_comment']) && !empty($_FILES['midia_comment']['name'][0]))) {
        try {
            $sql = "EXEC ComentarZun @UsuarioID = ?, @ZunID = ?, @Conteudo = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $zunId, PDO::PARAM_INT);
            $stmt->bindValue(3, $conteudo, PDO::PARAM_STR);
            $stmt->execute();

            $commentId = $conn->lastInsertId();

            // Processar mídias do comentário
            if (isset($_FILES['midia_comment']) && is_array($_FILES['midia_comment']['name'])) {
                $totalFiles = count($_FILES['midia_comment']['name']);
                for ($i = 0; $i < $totalFiles && $i < 4; $i++) {
                    if ($_FILES['midia_comment']['error'][$i] === UPLOAD_ERR_OK) {
                        $fileTmpPath = $_FILES['midia_comment']['tmp_name'][$i];
                        $fileType = $_FILES['midia_comment']['type'][$i];
                        $mimeGroup = explode('/', $fileType)[0];

                        $mediaType = ($mimeGroup === 'image') ? 'imagem' :
                            (($mimeGroup === 'video') ? 'video' : 'outro');

                        $rawMediaContent = file_get_contents($fileTmpPath);
                        $encryptedMediaContent = encryptData($rawMediaContent);

                        $stmtMedia = $conn->prepare("INSERT INTO Midias (ZunID, URL, TipoMidia, Ordem, AltText) VALUES (:zunId, :url, :tipoMidia, :ordem, :altText)");
                        $stmtMedia->bindParam(':zunId', $commentId, PDO::PARAM_INT);
                        $stmtMedia->bindParam(':url', $encryptedMediaContent, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
                        $stmtMedia->bindParam(':tipoMidia', $mediaType, PDO::PARAM_STR);
                        $stmtMedia->bindParam(':ordem', $i, PDO::PARAM_INT);
                        $altText = 'Mídia anexada ao comentário';
                        $stmtMedia->bindParam(':altText', $altText, PDO::PARAM_STR);
                        $stmtMedia->execute();
                    }
                }
            }

            $_SESSION['toast_message'] = 'Comentário postado com sucesso!';
            $_SESSION['toast_type'] = 'success';
            header("Location: Status.php?id=" . $zunId);
            exit();
        } catch (PDOException $e) {
            $_SESSION['toast_message'] = 'Erro ao postar comentário: ' . $e->getMessage();
            $_SESSION['toast_type'] = 'error';
        }
    } else {
        $_SESSION['toast_message'] = 'O comentário não pode estar vazio.';
        $_SESSION['toast_type'] = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zun - Zuno</title>
    <script src="https://kit.fontawesome.com/17dd42404d.js" crossorigin="anonymous"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
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
            max-width: 95%;
            max-height: 95%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 10px;
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

        .modal-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            color: white;
            border: none;
            cursor: pointer;
            z-index: 1001;
            font-size: 2.5rem;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background-color 0.2s ease, opacity 0.2s ease;
            opacity: 0.9;
        }

        .modal-arrow:hover {
            background: rgba(0, 0, 0, 0.5);
            opacity: 1;
        }

        .modal-arrow.left-0 {
            left: -3rem;
        }

        .modal-arrow.right-0 {
            right: -3rem;
        }

        .modal-arrow:disabled {
            opacity: 0.1;
            cursor: not-allowed;
            background: none;
        }

        /* Classes para layout de imagens no Zun */
        .media-grid {
            display: grid;
            gap: 4px;
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

        .media-grid img,
        .media-grid video {
            width: 100%;
            height: auto;
            max-height: 400px;
            object-fit: contain;
            border-radius: 8px;
            cursor: pointer;
        }

        .grid-3-images>img:first-child,
        .grid-3-images>video:first-child {
            grid-column: span 2;
            height: auto;
            max-height: 350px;
            object-fit: contain;
        }

        .grid-3-images>img:nth-child(2),
        .grid-3-images>img:nth-child(3),
        .grid-3-images>video:nth-child(2),
        .grid-3-images>video:nth-child(3) {
            height: auto;
            max-height: 200px;
            object-fit: contain;
        }

        .grid-2-images img,
        .grid-2-images video,
        .grid-4-images img,
        .grid-4-images video {
            height: auto;
            max-height: 300px;
            object-fit: contain;
        }

        .zun-container {
            position: relative;
        }

        .zun-container .interactive {
            pointer-events: auto;
            cursor: default;
        }

        .zun-container>* {
            pointer-events: none;
        }

        .zun-container .interactive * {
            pointer-events: auto;
        }
    </style>
</head>

<body class="bg-white flex min-h-screen ml-96 mr-96 bg-gray-100 text-gray-900">
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
                    </span>
                    Mensagens
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
                        stroke-dasharray="2 2" />
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="0.8" stroke-opacity="0.3"
                        stroke-dasharray="1 2" />
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
                                    <a href="ComunidadePage.php?id=<?php echo htmlspecialchars($community->ComunidadeID); ?>"
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
                <div class="sticky top-0 bg-white bg-opacity-90 backdrop-blur-sm z-10 p-4 border-b border-gray-200">
                    <div class="flex items-center">
                        <a href="Radar.php" class="mr-6 text-gray-700 hover:text-gray-900">
                            <i class="fas fa-arrow-left text-xl"></i>
                        </a>
                        <h1 class="text-xl font-bold">Zun</h1>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="p-4 text-center">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($zunData): ?>
                    <div class="p-4 border-b border-gray-200">
                        <div class="flex items-start space-x-3">
                            <a href="Profile.php?id=<?php echo htmlspecialchars($zunData->AutorID); ?>">
                                <img src="<?php echo ($zunData->AutorFotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($zunData->AutorFotoPerfil) : '../Design/Assets/default_profile.png'); ?>"
                                    alt="Foto de Perfil" class="w-12 h-12 rounded-full object-cover">
                            </a>

                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-baseline space-x-1">
                                        <a href="Profile.php?id=<?php echo htmlspecialchars($zunData->AutorID); ?>"
                                            class="font-bold text-gray-900 hover:underline">
                                            <?php echo htmlspecialchars($zunData->AutorNomeExibicao); ?>
                                        </a>
                                        <span class="text-gray-500 text-sm">
                                            @<?php echo htmlspecialchars($zunData->AutorNomeUsuario); ?>
                                        </span>
                                        <span class="text-gray-500 text-sm">·</span>
                                        <span class="text-gray-500 text-sm">
                                            <?php echo formatarDataZun($zunData->DataCriacao); ?>
                                        </span>
                                    </div>
                                    <button class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                </div>

                                <p class="text-gray-800 mt-1 mb-3">
                                    <?php echo htmlspecialchars($zunData->Conteudo); ?>
                                </p>

                                <?php if ($zunData->MidiaCount > 0): ?>
                                    <?php
                                    $mediaUrls = [];
                                    $mediaTypes = [];
                                    for ($i = 0; $i < $zunData->MidiaCount; $i++) {
                                        if (isset($zunData->{"MidiaURL_" . $i})) {
                                            $decryptedMedia = decryptData($zunData->{"MidiaURL_" . $i});
                                            $mimeType = $zunData->{"MidiaTipo_" . $i} === 'gif' ? 'image/gif' : 'image/jpeg';
                                            $mediaUrls[] = 'data:' . $mimeType . ';base64,' . base64_encode($decryptedMedia);
                                            $mediaTypes[] = $zunData->{"MidiaTipo_" . $i};
                                        }
                                    }
                                    $mediaCount = count($mediaUrls);
                                    ?>

                                    <div class="media-container mt-3 mb-4 rounded-2xl overflow-hidden border border-gray-200">
                                        <?php if ($mediaCount === 1): ?>
                                            <div class="w-full">
                                                <?php if ($mediaTypes[0] === 'gif'): ?>
                                                    <div class="relative">
                                                        <img src="<?php echo htmlspecialchars($mediaUrls[0]); ?>" alt="GIF do Zun"
                                                            class="w-full max-h-[80vh] object-contain" loading="lazy"
                                                            onclick="event.stopPropagation(); openModal(<?php echo htmlspecialchars(json_encode($mediaUrls)); ?>, 0)">
                                                        <div
                                                            class="absolute bottom-3 right-3 bg-black/70 text-white text-xs px-2 py-1 rounded">
                                                            GIF
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <img src="<?php echo htmlspecialchars($mediaUrls[0]); ?>" alt="Mídia do Zun"
                                                        class="w-full max-h-[80vh] object-contain" loading="lazy"
                                                        onclick="event.stopPropagation(); openModal(<?php echo htmlspecialchars(json_encode($mediaUrls)); ?>, 0)">
                                                <?php endif; ?>
                                            </div>

                                        <?php elseif ($mediaCount === 2): ?>
                                            <div class="grid grid-cols-2 gap-1">
                                                <?php foreach ($mediaUrls as $index => $url): ?>
                                                    <div class="relative aspect-square">
                                                        <img src="<?php echo htmlspecialchars($url); ?>" alt="Mídia do Zun"
                                                            class="w-full h-full object-cover" loading="lazy"
                                                            onclick="event.stopPropagation(); openModal(<?php echo htmlspecialchars(json_encode($mediaUrls)); ?>, <?php echo $index; ?>)">
                                                        <?php if ($mediaTypes[$index] === 'gif'): ?>
                                                            <div
                                                                class="absolute bottom-2 right-2 bg-black/70 text-white text-xs px-2 py-1 rounded">
                                                                GIF
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                        <?php elseif ($mediaCount === 3): ?>
                                            <div class="grid grid-cols-2 gap-1">
                                                <div class="row-span-2">
                                                    <img src="<?php echo htmlspecialchars($mediaUrls[0]); ?>" alt="Mídia do Zun"
                                                        class="w-full h-full object-cover" loading="lazy"
                                                        onclick="event.stopPropagation(); openModal(<?php echo htmlspecialchars(json_encode($mediaUrls)); ?>, 0)">
                                                    <?php if ($mediaTypes[0] === 'gif'): ?>
                                                        <div
                                                            class="absolute bottom-2 right-2 bg-black/70 text-white text-xs px-2 py-1 rounded">
                                                            GIF
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <img src="<?php echo htmlspecialchars($mediaUrls[1]); ?>" alt="Mídia do Zun"
                                                        class="w-full h-full object-cover" loading="lazy"
                                                        onclick="event.stopPropagation(); openModal(<?php echo htmlspecialchars(json_encode($mediaUrls)); ?>, 1)">
                                                    <?php if ($mediaTypes[1] === 'gif'): ?>
                                                        <div
                                                            class="absolute bottom-2 right-2 bg-black/70 text-white text-xs px-2 py-1 rounded">
                                                            GIF
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <img src="<?php echo htmlspecialchars($mediaUrls[2]); ?>" alt="Mídia do Zun"
                                                        class="w-full h-full object-cover" loading="lazy"
                                                        onclick="event.stopPropagation(); openModal(<?php echo htmlspecialchars(json_encode($mediaUrls)); ?>, 2)">
                                                    <?php if ($mediaTypes[2] === 'gif'): ?>
                                                        <div
                                                            class="absolute bottom-2 right-2 bg-black/70 text-white text-xs px-2 py-1 rounded">
                                                            GIF
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                        <?php elseif ($mediaCount >= 4): ?>
                                            <div class="grid grid-cols-2 gap-1">
                                                <?php for ($i = 0; $i < min($mediaCount, 4); $i++): ?>
                                                    <div class="relative aspect-square">
                                                        <img src="<?php echo htmlspecialchars($mediaUrls[$i]); ?>" alt="Mídia do Zun"
                                                            class="w-full h-full object-cover" loading="lazy"
                                                            onclick="event.stopPropagation(); openModal(<?php echo htmlspecialchars(json_encode($mediaUrls)); ?>, <?php echo $i; ?>)">
                                                        <?php if ($mediaTypes[$i] === 'gif'): ?>
                                                            <div
                                                                class="absolute bottom-2 right-2 bg-black/70 text-white text-xs px-2 py-1 rounded">
                                                                GIF
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($i === 3 && $mediaCount > 4): ?>
                                                            <div
                                                                class="absolute inset-0 bg-black/50 flex items-center justify-center text-white font-bold text-xl">
                                                                +<?php echo ($mediaCount - 4); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="flex justify-between text-gray-500 text-sm">
                                    <button class="flex items-center space-x-1 hover:text-blue-500">
                                        <i class="far fa-comment"></i>
                                        <span><?php echo $zunData->Respostas; ?></span>
                                    </button>
                                    <button class="flex items-center space-x-1 hover:text-lime-500">
                                        <i class="fas fa-sync-alt"></i>
                                        <span><?php echo $zunData->Reposts; ?></span>
                                    </button>
                                    <button class="flex items-center space-x-1 hover:text-pink-500">
                                        <i
                                            class="<?php echo ($zunData->ZunLikadoPorMim ? 'fas fa-heart text-pink-500' : 'far fa-heart'); ?>"></i>
                                        <span><?php echo $zunData->ZunLikes; ?></span>
                                    </button>
                                    <button class="hover:text-gray-700 transition-colors duration-200">
                                        <i class="far fa-bookmark"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 border-b border-gray-200">
                        <form action="Status.php?id=<?php echo $zunId; ?>" method="POST" enctype="multipart/form-data"
                            class="comment-input-container">
                            <input type="hidden" name="action" value="post_comment">
                            <div class="flex items-start space-x-3">
                                <img src="<?php echo ($userData->FotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($userData->FotoPerfil) : '../Design/Assets/default_profile.png'); ?>"
                                    alt="Sua Foto de Perfil" class="w-10 h-10 rounded-full object-cover">

                                <div class="flex-1 relative">
                                    <textarea name="conteudo_comment" rows="2" placeholder="Zunear sua resposta"
                                        class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#21fa90] resize-none"></textarea>

                                    <div class="flex justify-between items-center mt-2">
                                        <div class="flex items-center space-x-2">
                                            <label
                                                class="p-2 rounded-full cursor-pointer hover:bg-blue-500/10 text-blue-500">
                                                <i class="fas fa-image"></i>
                                                <input type="file" name="midia_comment[]" accept="image/*,video/*"
                                                    class="hidden" multiple>
                                            </label>

                                            <label
                                                class="p-2 rounded-full cursor-pointer hover:bg-blue-500/10 text-blue-500">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                        stroke-linejoin="round" />
                                                    <path
                                                        d="M8 14C8 15.1046 8.89543 16 10 16H14C15.1046 16 16 15.1046 16 14V10C16 8.89543 15.1046 8 14 8H10C8.89543 8 8 8.89543 8 10V14Z"
                                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                        stroke-linejoin="round" />
                                                    <path d="M8 12H16" stroke="currentColor" stroke-width="1.5"
                                                        stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                                <input type="file" name="gif_comment" accept="image/gif" class="hidden">
                                            </label>

                                            <button type="button"
                                                class="p-2 rounded-full cursor-pointer hover:bg-blue-500/10 text-blue-500 emoji-trigger">
                                                <i class="far fa-smile"></i>
                                            </button>
                                        </div>

                                        <button type="submit"
                                            class="bg-[#21fa90] text-white font-bold py-1.5 px-4 rounded-full hover:bg-[#83ecb9] transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                            Responder
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div>
                        <?php if (!empty($comments)): ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="p-4 border-b border-gray-200">
                                    <div class="flex items-start space-x-3">
                                        <a href="Profile.php?id=<?php echo htmlspecialchars($comment->AutorID); ?>">
                                            <img src="<?php echo ($comment->AutorFotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($comment->AutorFotoPerfil) : '../Design/Assets/default_profile.png'); ?>"
                                                alt="Foto de Perfil" class="w-10 h-10 rounded-full object-cover">
                                        </a>

                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-baseline space-x-1">
                                                    <a href="Profile.php?id=<?php echo htmlspecialchars($comment->AutorID); ?>"
                                                        class="font-bold text-gray-900 hover:underline">
                                                        <?php echo htmlspecialchars($comment->AutorNomeExibicao); ?>
                                                    </a>
                                                    <span class="text-gray-500 text-sm">
                                                        @<?php echo htmlspecialchars($comment->AutorNomeUsuario); ?>
                                                    </span>
                                                    <span class="text-gray-500 text-sm">·</span>
                                                    <span class="text-gray-500 text-sm">
                                                        <?php echo formatarDataZun($comment->DataCriacao); ?>
                                                    </span>
                                                </div>
                                                <button class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                            </div>

                                            <p class="text-gray-800 mt-1 mb-3">
                                                <?php echo htmlspecialchars($comment->Conteudo); ?>
                                            </p>

                                            <?php if ($comment->MidiaCount > 0): ?>
                                                <?php
                                                $commentMediaUrls = [];
                                                $commentMediaTypes = [];
                                                for ($i = 0; $i < $comment->MidiaCount; $i++) {
                                                    if (isset($comment->{"MidiaURL_" . $i})) {
                                                        $commentMediaUrls[] = 'data:image/jpeg;base64,' . base64_encode($comment->{"MidiaURL_" . $i});
                                                        $commentMediaTypes[] = $comment->{"MidiaTipo_" . $i};
                                                    }
                                                }
                                                $commentMediaCount = count($commentMediaUrls);
                                                ?>

                                                <div class="media-grid mt-2 mb-4 
                                                    <?php if ($commentMediaCount === 1)
                                                        echo 'grid-1-image';
                                                    elseif ($commentMediaCount === 2)
                                                        echo 'grid-2-images';
                                                    elseif ($commentMediaCount === 3)
                                                        echo 'grid-3-images';
                                                    elseif ($commentMediaCount >= 4)
                                                        echo 'grid-4-images'; ?>">
                                                    <?php foreach ($commentMediaUrls as $index => $url): ?>
                                                        <?php if ($commentMediaTypes[$index] === 'gif'): ?>
                                                            <img src="<?php echo htmlspecialchars($url); ?>" alt="GIF do Comentário"
                                                                class="<?php echo ($commentMediaCount === 3 && $index === 0) ? 'col-span-2 h-64' : 'w-full h-40'; ?> object-cover"
                                                                onclick="event.stopPropagation(); openModal(<?php echo htmlspecialchars(json_encode($commentMediaUrls)); ?>, <?php echo $index; ?>)">
                                                        <?php else: ?>
                                                            <img src="<?php echo htmlspecialchars($url); ?>" alt="Mídia do Comentário"
                                                                class="<?php echo ($commentMediaCount === 3 && $index === 0) ? 'col-span-2 h-64' : 'w-full h-40'; ?> object-cover"
                                                                onclick="event.stopPropagation(); openModal(<?php echo htmlspecialchars(json_encode($commentMediaUrls)); ?>, <?php echo $index; ?>)">
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="flex justify-between text-gray-500 text-sm">
                                                <button class="flex items-center space-x-1 hover:text-blue-500">
                                                    <i class="far fa-comment"></i>
                                                    <span><?php echo $comment->Respostas; ?></span>
                                                </button>
                                                <button class="flex items-center space-x-1 hover:text-lime-500">
                                                    <i class="fas fa-sync-alt"></i>
                                                    <span><?php echo $comment->Reposts; ?></span>
                                                </button>
                                                <button class="flex items-center space-x-1 hover-text-pink-500">
                                                    <i
                                                        class="<?php echo ($comment->ZunLikadoPorMim ? 'fas fa-heart text-pink-500' : 'far fa-heart'); ?>"></i>
                                                    <span><?php echo $comment->ZunLikes; ?></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-8 text-center text-gray-500">
                                <p>Nenhum comentário ainda. Seja o primeiro a responder!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <aside class="w-80 p-6 hidden lg:block">
        <div class="bg-white rounded-lg shadow-md p-4">
            <h2 class="text-xl font-bold mb-4">Quem seguir</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <img src="../Design/Assets/default_profile.png" alt="Foto de Perfil"
                            class="w-10 h-10 rounded-full">
                        <div>
                            <p class="font-bold">Nome do Usuário</p>
                            <p class="text-gray-500 text-sm">@username</p>
                        </div>
                    </div>
                    <button
                        class="bg-black text-white text-sm font-semibold py-1 px-4 rounded-full hover:bg-gray-800 transition-colors duration-200">
                        Seguir
                    </button>
                </div>
                </div>
            <a href="#" class="block text-blue-500 mt-4 text-sm hover:underline">Mostrar mais</a>
        </div>
    </aside>

    <div id="imageModal" class="modal-overlay">
        <div class="modal-content">
            <button id="closeModal" class="modal-close-button"><i class="fas fa-times"></i></button>
            <button id="prevArrow" class="modal-arrow left-0"><i class="fas fa-chevron-left"></i></button>
            <img src="" alt="Mídia Expandida" class="modal-image" id="expandedImage">
            <button id="nextArrow" class="modal-arrow right-0"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Variáveis globais para o modal de imagem
            const imageModal = document.getElementById('imageModal');
            const expandedImage = document.getElementById('expandedImage');
            const closeModalBtn = document.getElementById('closeModal');
            const prevArrow = document.getElementById('prevArrow');
            const nextArrow = document.getElementById('nextArrow');

            let currentModalMediaUrls = [];
            let currentModalMediaIndex = 0;

            // Função para exibir uma mídia específica no modal
            function showMediaInModal(index) {
                if (currentModalMediaUrls.length === 0) {
                    expandedImage.src = '';
                    prevArrow.style.display = 'none';
                    nextArrow.style.display = 'none';
                    return;
                }

                expandedImage.src = currentModalMediaUrls[index];
                currentModalMediaIndex = index;

                // Mostra/esconde as setas de acordo com o índice atual
                prevArrow.style.display = (index > 0) ? 'flex' : 'none';
                nextArrow.style.display = (index < currentModalMediaUrls.length - 1) ? 'flex' : 'none';

                // Desabilita os botões se estiver nos limites
                prevArrow.disabled = (index === 0);
                nextArrow.disabled = (index === currentModalMediaUrls.length - 1);
            }

            // Função para abrir o modal com a imagem expandida
            window.openModal = function (mediaUrlsArray, initialIndex) {
                currentModalMediaUrls = mediaUrlsArray;
                showMediaInModal(initialIndex);
                imageModal.classList.add('active');
                document.body.style.overflow = 'hidden'; // Bloqueia o scroll do body
            };

            // Função para fechar o modal
            closeModalBtn.addEventListener('click', function () {
                imageModal.classList.remove('active');
                expandedImage.src = ''; // Limpa a imagem para otimizar memória
                currentModalMediaUrls = []; // Limpa as URLs armazenadas
                currentModalMediaIndex = 0; // Reseta o índice
                document.body.style.overflow = 'auto'; // Restaura o scroll do body
            });

            // Lógica de navegação: Anterior
            prevArrow.addEventListener('click', function (event) {
                event.stopPropagation(); // Evita que o clique feche o modal (devido ao event listener do overlay)
                if (currentModalMediaIndex > 0) {
                    showMediaInModal(currentModalMediaIndex - 1);
                }
            });

            // Lógica de navegação: Próximo
            nextArrow.addEventListener('click', function (event) {
                event.stopPropagation(); // Evita que o clique feche o modal
                if (currentModalMediaIndex < currentModalMediaUrls.length - 1) {
                    showMediaInModal(currentModalMediaIndex + 1);
                }
            });

            // Fechar modal clicando fora da imagem (mas não nas setas ou no botão de fechar)
            imageModal.addEventListener('click', function (e) {
                if (e.target === this) {
                    closeModalBtn.click(); // Simula o clique no botão de fechar
                }
            });

            // Listener para o container principal do Zun para evitar redirecionamento ao clicar na imagem
            document.querySelectorAll('.zun-container').forEach(container => {
                container.addEventListener('click', function (e) {
                    if (e.target.closest('.interactive') || e.target.closest('.modal-arrow') || e.target.closest('.modal-close-button')) {
                        return;
                    }
                    if (!e.target.closest('img') && !e.target.closest('button')) {
                         window.location.href = 'Status.php?id=' + this.getAttribute('data-zun-id');
                    }
                });
            });

            // Mostrar toasts (mensagens de sucesso/erro)
            <?php if (isset($_SESSION['toast_message'])): ?>
                Toastify({
                    text: "<?php echo htmlspecialchars($_SESSION['toast_message']); ?>",
                    duration: 3000,
                    newWindow: true,
                    close: true,
                    gravity: "bottom",
                    position: "center",
                    stopOnFocus: true,
                    style: {
                        background: "<?php echo ($_SESSION['toast_type'] === 'success' ? '#16ac63' : '#ef4444'); ?>",
                        borderRadius: "8px",
                        fontFamily: "'Urbanist', sans-serif"
                    },
                }).showToast();
                <?php
                unset($_SESSION['toast_message']);
                unset($_SESSION['toast_type']);
                ?>
            <?php endif; ?>
        });
    </script>
</body>

</html>