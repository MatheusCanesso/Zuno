<?php
session_start();
// Define o fuso horário padrão para todas as operações de data/hora no PHP
date_default_timezone_set('America/Sao_Paulo');
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
$message = '';

// --- Lógica para exibir mensagem de sucesso após postagem ---
if (isset($_GET['status']) && $_GET['status'] === 'zun_posted') {
    $message = '<p style="color: green;">Zun postado com sucesso e feed atualizado!</p>';
}

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
    $message .= '<p style="color: red;">Erro ao carregar dados do perfil: ' . $e->getMessage() . '</p>';
    error_log("Erro ao carregar dados do perfil: " . $e->getMessage());
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
        'MMMMənd' // Adicionado 'yyyy' para incluir o ano
    );
    return ucfirst($formatter->format($d));
}

// Função auxiliar para formatar a data dos Zuns (AGORA COM "HÁ" e mais detalhes)
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


// --- Lógica para Postar um Novo Zun ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_zun') {
    $conteudo = trim($_POST['conteudo_zun']);
    $zunId = null; // Output parameter for stored procedure
    $postSuccessful = false;

    // Retrieve community ID from form
    $comunidadeId = isset($_POST['comunidade_id']) && $_POST['comunidade_id'] !== '' ? (int) $_POST['comunidade_id'] : null;

    // Default values for new zun (can be adapted for replies/reposts later)
    $zunPaiId = null;
    $zunOriginalId = null;
    $tipoZun = 'zun'; // 'zun', 'repost', 'resposta', 'citacao'
    $visibilidade = 'publico'; // 'publico', 'privado', 'apenas_seguidores'
    $localizacao = null; // GEOGRAPHY type in DB
    $idioma = null; // NVARCHAR(10)

    error_log("Tentativa de postar Zun. UserID: " . $userId . ", Conteúdo: '" . $conteudo . "', ComunidadeID: " . var_export($comunidadeId, true));

    // Check if there's content (text or at least one file)
    if (!empty($conteudo) || (isset($_FILES['midia_zun']) && !empty($_FILES['midia_zun']['name'][0]) && $_FILES['midia_zun']['error'][0] === UPLOAD_ERR_OK)) {
        try {
            error_log("Preparando chamada para PostarZun...");
            // Call PostarZun stored procedure
            // The PostarZun stored procedure now has 9 parameters, including @ComunidadeID
            $stmt = $conn->prepare("{CALL PostarZun(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}");

            $stmt->bindParam(1, $userId, PDO::PARAM_INT);
            $stmt->bindParam(2, $conteudo, PDO::PARAM_STR);
            $stmt->bindParam(3, $comunidadeId, PDO::PARAM_INT); // Bind ComunidadeID

            // Binding parameters that can be NULL
            $stmt->bindParam(4, $zunPaiId, PDO::PARAM_INT);
            if ($zunPaiId === null)
                $stmt->bindValue(4, null, PDO::PARAM_NULL);

            $stmt->bindParam(5, $zunOriginalId, PDO::PARAM_INT);
            if ($zunOriginalId === null)
                $stmt->bindValue(5, null, PDO::PARAM_NULL);

            $stmt->bindParam(6, $tipoZun, PDO::PARAM_STR);
            $stmt->bindParam(7, $visibilidade, PDO::PARAM_STR);

            $stmt->bindParam(8, $localizacao, PDO::PARAM_STR);
            if ($localizacao === null)
                $stmt->bindValue(8, null, PDO::PARAM_NULL);

            $stmt->bindParam(9, $idioma, PDO::PARAM_STR);
            if ($idioma === null)
                $stmt->bindValue(9, null, PDO::PARAM_NULL);

            // Binding output parameter for SQLSRV
            $stmt->bindParam(10, $zunId, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 8);

            error_log("Executando PostarZun...");
            $stmt->execute();
            error_log("PostarZun executado. ZunID retornado: " . var_export($zunId, true));

            // After execution, $zunId will contain the new ZunID
            if ($zunId > 0) {
                $postSuccessful = true;
                error_log("ZunID válido retornado: " . $zunId . ". Processando mídias...");
                // --- Handle Media Uploads ---
                if (isset($_FILES['midia_zun']) && is_array($_FILES['midia_zun']['name'])) {
                    $totalFiles = count($_FILES['midia_zun']['name']);
                    $filesProcessed = 0;
                    for ($i = 0; $i < $totalFiles && $filesProcessed < 4; $i++) { // Limit to 4 files as per design
                        if ($_FILES['midia_zun']['error'][$i] === UPLOAD_ERR_OK) {
                            $fileTmpPath = $_FILES['midia_zun']['tmp_name'][$i];
                            $fileType = $_FILES['midia_zun']['type'][$i]; // e.g., image/jpeg, image/gif, video/mp4

                            $mimeGroup = explode('/', $fileType)[0]; // 'image' or 'video'
                            $mediaType = 'imagem'; // Default

                            if ($mimeGroup === 'image') {
                                if (strpos($fileType, 'gif') !== false) {
                                    $mediaType = 'gif';
                                } else {
                                    $mediaType = 'imagem';
                                }
                            } elseif ($mimeGroup === 'video') {
                                $mediaType = 'video';
                            } else {
                                error_log("Tipo de mídia não suportado ou inválido: " . $fileType);
                                continue; // Skip unsupported file types
                            }

                            $mediaContent = file_get_contents($fileTmpPath);

                            error_log("Inserindo mídia para ZunID: " . $zunId . ", Tipo: " . $mediaType . ", Ordem: " . $filesProcessed);
                            $stmtMedia = $conn->prepare("INSERT INTO Midias (ZunID, URL, TipoMidia, Ordem, AltText) VALUES (:zunId, :url, :tipoMidia, :ordem, :altText)");
                            $stmtMedia->bindParam(':zunId', $zunId, PDO::PARAM_INT);
                            $stmtMedia->bindParam(':url', $mediaContent, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY); // Use PARAM_LOB for VARBINARY(MAX)
                            $stmtMedia->bindParam(':tipoMidia', $mediaType, PDO::PARAM_STR);
                            $stmtMedia->bindParam(':ordem', $filesProcessed, PDO::PARAM_INT); // Use $filesProcessed for sequential order
                            $altText = 'Mídia anexada ao Zun'; // Placeholder alt text
                            $stmtMedia->bindParam(':altText', $altText, PDO::PARAM_STR);
                            $stmtMedia->execute();
                            error_log("Mídia inserida com sucesso.");
                            $filesProcessed++;
                        } else {
                            error_log("Erro no upload do arquivo " . $i . ": " . $_FILES['midia_zun']['error'][$i]);
                        }
                    }
                }
                $_SESSION['toast_message'] = 'Zun postado com sucesso!';
                $_SESSION['toast_type'] = 'success'; // Novo tipo 'success' 
            } else {
                error_log("ZunID não foi retornado ou é inválido (<= 0). Possível falha na SP ou retorno.");
                $_SESSION['toast_message'] = 'Erro ao postar Zun. Tente novamente.';
                $_SESSION['toast_type'] = 'error'; // Novo tipo 'success' 
            }

        } catch (PDOException $e) {
            $_SESSION['toast_message'] = 'Erro no banco de dados ao postar Zun: ' . $e->getMessage();
            $_SESSION['toast_type'] = 'error'; // Novo tipo 'error'
            error_log("Erro PDO ao postar Zun: " . $e->getMessage());
        }
    } else {
        $_SESSION['toast_message'] = 'Zun não pode ser vazio (texto ou mídia).';
        $_SESSION['toast_type'] = 'error'; // Novo tipo 'error'
        error_log("Tentativa de postar Zun vazio (sem texto e sem mídia).");
    }
    // Redirecionar para a própria página para limpar o formulário e atualizar o feed
    header("Location: Radar.php?status=zun_posted");
    exit();
}

// Lógica para buscar Zuns para a timeline
// Esta seção é executada no carregamento normal da página ou após o redirecionamento.
// Ela buscará os Zuns mais recentes, incluindo os recém-postados pelo usuário.
$zuns = [];
try {
    $pagina = 1;
    $zunsPorPagina = 20;

    $stmt = $conn->prepare("{CALL ObterTimeline(?, ?, ?)}");
    $stmt->bindParam(1, $userId, PDO::PARAM_INT);
    $stmt->bindParam(2, $pagina, PDO::PARAM_INT);
    $stmt->bindParam(3, $zunsPorPagina, PDO::PARAM_INT);

    $stmt->execute();
    $zuns = $stmt->fetchAll(PDO::FETCH_OBJ);

} catch (PDOException $e) {
    $message .= '<p style="color: red;">Erro ao carregar Zuns: ' . $e->getMessage() . '</p>';
    error_log("Erro ao carregar Zuns da timeline: " . $e->getMessage());
}

// Buscar usuários para a seção "Quem seguir"
$suggestedUsers = [];
try {
    $stmt = $conn->prepare("SELECT UsuarioID, NomeExibicao, NomeUsuario, FotoPerfil FROM Usuarios WHERE UsuarioID != :currentUserId ORDER BY NEWID()");
    $stmt->bindParam(':currentUserId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $suggestedUsers = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    error_log("Erro ao carregar sugestões de usuários: " . $e->getMessage());
}

// Buscar comunidades que o usuário faz parte (para o menu lateral e para a seleção de postagem)
$userCommunities = [];
try {
    $stmt = $conn->prepare("SELECT c.ComunidadeID, c.Nome AS NomeComunidade, c.FotoPerfil AS FotoComunidade FROM Comunidades c JOIN MembrosComunidade mc ON c.ComunidadeID = mc.ComunidadeID WHERE mc.UsuarioID = :userId ORDER BY c.Nome");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userCommunities = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    error_log("Erro ao carregar comunidades do usuário: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Radar - Zuno</title>
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

        /* Classes para layout de imagens no Zun */
        .media-grid {
            display: grid;
            gap: 8px;
            /* gap-2 */
            border-radius: 8px;
            /* rounded-lg */
            overflow: hidden;
            border: 1px solid #e5e7eb;
            /* border border-gray-200 */
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

        /* Ajustes para todas as imagens de grid */
        .media-grid img {
            width: 100%;
            height: 200px;
            /* Default height, can be overridden */
            object-fit: cover;
            border-radius: 8px;
        }

        /* Adicionar bordas entre as imagens no grid */
        .media-grid.grid-cols-2>img:nth-child(odd):not(:last-child) {
            border-right: 1px solid #e5e7eb;
        }

        .media-grid.grid-rows-2>img:nth-child(-n+2) {
            /* For top row in 3/4 images */
            border-bottom: 1px solid #e5e7eb;
        }

        .media-grid.grid-cols-1>img {
            border: none;
            /* No internal borders for single image */
        }

        .custom-select {
            position: relative;
            display: inline-block;
            min-width: 200px;
        }

        .custom-select select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 100%;
            padding: 8px 32px 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            background-color: white;
            color: #374151;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .custom-select select:focus {
            outline: none;
            border-color: #21fa90;
            box-shadow: 0 0 0 2px rgba(33, 250, 144, 0.2);
        }

        .custom-select::after {
            content: "▼";
            font-size: 10px;
            color: #6b7280;
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .custom-select:hover::after {
            color: #21fa90;
        }

        /* Estilo para as opções do select */
        .custom-select option {
            padding: 8px;
        }

        /* Container do select com ícone */
        .select-with-icon {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .select-icon {
            width: 20px;
            height: 20px;
            color: #21fa90;
        }
    </style>
</head>

<body class=" bg-white flex min-h-screen ml-96 mr-96 bg-gray-100 text-gray-900">
    <nav class="w-64 fixed h-full bg-white border-r border-gray-200 p-4 flex flex-col justify-between">
        <div>
            <div class="mb-8 pl-2">
                <a href="Index.php">
                    <img src="../Design/Assets/logotipo_H.png" alt="Logo Zuno" class="logo-icon">
                </a>
            </div>

            <div class="flex flex-col space-y-2">
                <a href="Radar.php"
                    class="flex items-center p-2 text-lg font-semibold text-[#16ac63] rounded-lg hover:bg-gray-200 transition-colors duration-200">
                    <span class="icon mr-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" stroke="#16ac63" stroke-width="2" />

                            <path d="M12 12L20 7" stroke="#16ac63" stroke-width="2" stroke-linecap="round" />

                            <circle cx="12" cy="12" r="4" stroke="#16ac63" stroke-width="1" stroke-dasharray="4 2" />
                            <circle cx="12" cy="12" r="2" fill="#16ac63" />
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
                <div class="sticky top-0 bg-white z-10 p-4 border-b border-gray-200">
                    <input type="text" placeholder="Pesquisar no Zuno"
                        class="w-full pl-10 pr-4 py-2 rounded-full bg-gray-200 text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#21fa90] focus:bg-white">
                    <div class="absolute inset-y-0 left-0 pl-7 flex items-center pointer-events-none">
                        <span class="icon mr-2">
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

                <div class="bg-white p-4 pt-0 border-b border-gray-200">
                    <div class="flex justify-center">
                        <div class="mt-3 flex items-center">
                            <div class="select-with-icon">
                                <!-- Container para a foto da comunidade (inicialmente vazio) -->
                                <div id="community-icon-container"
                                    class="w-6 h-6 rounded-full overflow-hidden flex items-center justify-center bg-gray-200">
                                    <!-- Ícone padrão (mostrado quando nenhuma comunidade está selecionada) -->
                                    <svg id="default-community-icon" class="select-icon w-4 h-4 text-gray-500" width="24"
                                        height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path d="M8 14C8 14 9.5 16 12 16C14.5 16 16 14 16 14" stroke="currentColor"
                                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M15 9H15.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path d="M9 9H9.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                </div>
    
                                <div class="custom-select">
                                    <select name="comunidade_id" id="comunidade_id">
                                        <option value="">Meu Feed (Público)</option>
                                        <?php foreach ($userCommunities as $community): ?>
                                            <option value="<?php echo htmlspecialchars($community->ComunidadeID); ?>"
                                                data-photo="<?php echo ($community->FotoComunidade ? 'data:image/jpeg;base64,' . base64_encode($community->FotoComunidade) : '../Design/Assets/default_community.png'); ?>">
                                                <?php echo htmlspecialchars($community->NomeComunidade); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <img src="<?php echo ($userData->FotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($userData->FotoPerfil) : '../Design/Assets/default_profile.png'); ?>"
                            alt="Sua Foto de Perfil" class="w-12 h-12 rounded-full object-cover">
                        <form action="Radar.php" method="POST" enctype="multipart/form-data" class="flex-1">
                            <input type="hidden" name="action" value="post_zun">
                            <textarea name="conteudo_zun" id="zunTextarea" rows="3"
                                placeholder="O que está acontecendo, <?php echo htmlspecialchars($userData->NomeExibicao ?? ''); ?>?"
                                class="w-full text-lg p-2 border-none focus:ring-0 focus:outline-none resize-none"
                                maxlength="280"></textarea>
                            <div id="image-preview-container"
                                class="mt-2 rounded-lg overflow-hidden border border-gray-200" style="display: none;">
                            </div>
                            <input type="file" name="midia_zun[]" id="midia_zun_input"
                                accept="image/jpeg,image/png,image/gif" multiple class="hidden">

                            <div class="flex justify-between items-center mt-2">
                                <div class="flex items-center text-blue-500 text-xl gap-x-2">
                                    <label for="midia_zun_input"
                                        class="p-2 rounded-full cursor-pointer transition-colors duration-200 hover:bg-blue-500/10 flex items-center justify-center">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <rect x="4" y="4" width="16" height="16" rx="2" stroke="currentColor"
                                                stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M4 16L8 12L12 16L16 10L20 14" stroke="currentColor"
                                                stroke-width="1.5" stroke-linecap="round" />
                                            <circle cx="16" cy="9" r="1.5" fill="currentColor" />
                                        </svg>
                                    </label>
                                    <button type="button"
                                        class="p-2 rounded-full cursor-pointer transition-colors duration-200 hover:bg-blue-500/10 flex items-center justify-center">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <rect x="3" y="3" width="18" height="18" rx="3" stroke="currentColor"
                                                stroke-width="1.5" />
                                            <text x="12" y="16" font-family="Arial, sans-serif" font-size="10"
                                                font-weight="bold" text-anchor="middle" fill="currentColor">GIF</text>
                                        </svg>
                                    </button>
                                    <button type="button"
                                        class="p-2 rounded-full cursor-pointer transition-colors duration-200 hover:bg-blue-500/10 flex items-center justify-center">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <rect x="3" y="3" width="18" height="18" rx="3" stroke="currentColor"
                                                stroke-width="1.5" />
                                            <rect x="7" y="7" width="10" height="2" rx="1" fill="currentColor" />
                                            <rect x="7" y="15" width="10" height="2" rx="1" fill="currentColor" />
                                            <circle cx="9" cy="8" r="1.5" fill="currentColor" />
                                            <circle cx="9" cy="16" r="1.5" fill="currentColor" />
                                        </svg>
                                    </button>
                                    <button type="button"
                                        class="p-2 rounded-full cursor-pointer transition-colors duration-200 hover:bg-blue-500/10 flex items-center justify-center">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                                            <circle cx="9" cy="10" r="1" fill="currentColor" />
                                            <circle cx="15" cy="10" r="1" fill="currentColor" />
                                            <path d="M8 15C8 15 9.5 17 12 17C14.5 17 16 15 16 15" stroke="currentColor"
                                                stroke-width="1.5" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="flex items-center gap-2">
                                    <div id="charCounter" class="text-sm text-gray-400 transition-all duration-200">
                                        <span id="charCount">0</span>/280
                                    </div>

                                    <button type="submit" id="zunButton"
                                        class="bg-[#21fa90] text-white font-bold py-2 px-5 rounded-full hover:bg-[#83ecb9] transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                        disabled>
                                        Zunear
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="h-3 bg-gray-100 border-b border-t border-gray-200"></div>

                <?php if (!empty($zuns)): ?>
                    <?php foreach ($zuns as $zun): ?>
                        <div class="p-4 border-b border-gray-200 relative">
                            <?php if ($zun->EhRepost): // Se for um repost, mostrar quem repostou ?>
                                <div class="flex items-center text-gray-500 text-sm mb-1 ml-9">
                                    <i class="fas fa-retweet mr-2"></i>
                                    <span><?php echo htmlspecialchars($zun->RepostPorNomeExibicao ?? 'Usuário Desconhecido'); ?>
                                        Zunou novamente</span>
                                </div>
                            <?php endif; ?>
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

                                    <p class="text-gray-800 mt-5 mb-5 max-w-xl" style="word-break: break-all;">
                                        <?php echo htmlspecialchars($zun->Conteudo ?? ''); ?>
                                    </p>

                                    <?php if ($zun->MidiaCount > 0):
                                        $mediaUrls = [];
                                        for ($i = 0; $i < $zun->MidiaCount; $i++) {
                                            if (isset($zun->{"MidiaURL_" . $i}) && !empty($zun->{"MidiaURL_" . $i})) {
                                                $mediaUrls[] = 'data:image/jpeg;base64,' . base64_encode($zun->{"MidiaURL_" . $i});
                                            }
                                        }
                                        $mediaCount = count($mediaUrls);
                                        ?>
                                        <div class="media-grid mt-2 rounded-lg overflow-hidden border border-gray-200
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

                                    <div class="flex justify-between mr-1 items-center text-gray-500 text-sm">
                                        <button class="flex items-center space-x-1 hover:text-blue-500">
                                            <i class="far fa-comment"></i> <span><?php echo $zun->Respostas; ?></span>
                                        </button>
                                        <button class="flex items-center space-x-1 hover:text-lime-500">
                                            <i class="fas fa-sync-alt"></i> <span><?php echo $zun->Reposts; ?></span>
                                        </button>
                                        <button class="flex items-center space-x-1 hover:text-gray-700">
                                            <i class="fas fa-chart-bar"></i> <span><?php echo '0'; ?></span> </button>

                                        <button class="hover:text-gray-700 transition-colors duration-200">
                                            <i class="far fa-bookmark"></i>
                                        </button>
                                    </div>
                                </div>
                                <button
                                    class="absolute right-16 text-gray-500 hover:text-red-500 flex flex-col items-center top-1/2 -translate-y-1/2">
                                    <i
                                        class="<?php echo ($zun->ZunLikadoPorMim ? 'fas fa-heart text-red-500' : 'far fa-heart'); ?> text-xl"></i>
                                    <span class="text-sm"><?php echo $zun->ZunLikes; ?></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center p-8 text-gray-600">Nenhum Zun para exibir. Siga algumas pessoas ou faça sua
                        primeira publicação!</p>
                <?php endif; ?>
            </main>
        </div>

        <aside class="w-80 p-6 space-y-6 border border-gray-200 rounded-lg">
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
            const textarea = document.getElementById('zunTextarea');
            const charCounter = document.getElementById('charCounter');
            const charCount = document.getElementById('charCount');
            const zunButton = document.getElementById('zunButton');
            const midiaInput = document.getElementById('midia_zun_input');
            const imagePreviewContainer = document.getElementById('image-preview-container');
            const maxLength = 280; // Changed to 280 to match Zuns table
            const warningThreshold = 20;

            let selectedFiles = [];

            function updateCharCounter() {
                const currentLength = textarea.value.length;
                charCount.textContent = currentLength;
                // Enable button if there's text or selected files
                zunButton.disabled = currentLength === 0 && selectedFiles.length === 0;

                if (currentLength > maxLength - warningThreshold) {
                    charCounter.classList.remove('text-gray-400');
                    charCounter.classList.add('text-red-500');
                    if (currentLength > maxLength - 5) {
                        charCounter.classList.add('animate-pulse');
                    } else {
                        charCounter.classList.remove('animate-pulse');
                    }
                } else {
                    charCounter.classList.remove('text-red-500', 'animate-pulse');
                    charCounter.classList.add('text-gray-400');
                }
                if (currentLength > maxLength) {
                    textarea.value = textarea.value.substring(0, maxLength);
                    charCount.textContent = maxLength;
                }
            }

            function updateImagePreviews() {
                imagePreviewContainer.innerHTML = ''; // Clear existing previews
                if (selectedFiles.length === 0) {
                    imagePreviewContainer.style.display = 'none';
                    return;
                }

                imagePreviewContainer.style.display = 'grid';
                imagePreviewContainer.className = 'mt-2 gap-2 rounded-lg overflow-hidden border border-gray-200';

                // Set grid classes based on number of images
                if (selectedFiles.length === 1) {
                    imagePreviewContainer.classList.add('grid-1-image');
                } else if (selectedFiles.length === 2) {
                    imagePreviewContainer.classList.add('grid-2-images');
                } else if (selectedFiles.length === 3) {
                    imagePreviewContainer.classList.add('grid-3-images'); // 2 cols, 2 rows for 3 images
                } else if (selectedFiles.length >= 4) {
                    imagePreviewContainer.classList.add('grid-4-images');
                }

                selectedFiles.forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const imgDiv = document.createElement('div');
                        imgDiv.className = 'relative group';

                        // Close button for image preview
                        const closeButton = document.createElement('button');
                        closeButton.className = 'absolute top-1 right-1 bg-black bg-opacity-50 text-white rounded-full p-1 text-xs opacity-0 group-hover:opacity-100 transition-opacity';
                        closeButton.innerHTML = '<i class="fas fa-times"></i>';
                        closeButton.onclick = (event) => {
                            event.preventDefault(); // Prevent form submission
                            // Remove the file from the selectedFiles array
                            selectedFiles = selectedFiles.filter((f, i) => i !== index);

                            // Create a new FileList to update the input element
                            const dataTransfer = new DataTransfer();
                            selectedFiles.forEach(file => dataTransfer.items.add(file));
                            midiaInput.files = dataTransfer.files;

                            updateImagePreviews(); // Re-render previews
                            updateCharCounter(); // Re-evaluate button state
                        };
                        imgDiv.appendChild(closeButton);

                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Preview';
                        img.className = 'w-full object-cover';

                        // Apply specific heights/widths for 3-image layout
                        if (selectedFiles.length === 3) {
                            if (index === 0) {
                                img.classList.add('col-span-2', 'h-64'); // First image takes full width, taller
                            } else {
                                img.classList.add('h-40'); // Remaining two are shorter
                            }
                        } else {
                            img.classList.add('h-40'); // Default height for 1, 2, 4 images
                            if (selectedFiles.length === 1) {
                                img.classList.add('h-auto', 'max-h-96'); // Adjust single image height
                            }
                        }

                        imgDiv.appendChild(img);
                        imagePreviewContainer.appendChild(imgDiv);
                    };
                    reader.readAsDataURL(file);
                });
            }

            textarea.addEventListener('input', updateCharCounter);
            midiaInput.addEventListener('change', function () {
                // Limit to 4 files
                selectedFiles = Array.from(midiaInput.files).slice(0, 4);
                updateImagePreviews();
                updateCharCounter(); // Update button state based on files
            });

            // Initial setup
            updateCharCounter();
            const toastMessage = "<?php echo isset($_SESSION['toast_message']) ? htmlspecialchars($_SESSION['toast_message']) : ''; ?>";
            const toastType = "<?php echo isset($_SESSION['toast_type']) ? htmlspecialchars($_SESSION['toast_type']) : ''; ?>";

            if (toastMessage) {
                let backgroundColor = 'rgb(26, 189, 110)'; // Cor padrão Zuno Green para sucesso
                if (toastType === 'error') {
                    backgroundColor = '#ef4444'; // Vermelho do Tailwind para erro (red-500)
                }

                // Localize este trecho no seu Radar.php dentro do <script>
                Toastify({
                    text: toastMessage,
                    duration: 3000,
                    newWindow: true,
                    close: true,
                    gravity: "bottom", // Alterado de "top" para "bottom"
                    position: "center", // Alterado de "right" para "center"
                    stopOnFocus: true,
                    style: {
                        background: backgroundColor,
                        borderRadius: "8px",
                        fontFamily: "'Urbanist', sans-serif"
                    },
                    onClick: function () { }
                }).showToast();

                // Limpa as variáveis de sessão para que o toast não apareça novamente em futuros recarregamentos
                <?php
                unset($_SESSION['toast_message']);
                unset($_SESSION['toast_type']);
                ?>
            }
        });
        document.addEventListener('DOMContentLoaded', function () {
            const communitySelect = document.getElementById('comunidade_id');
            const communityIconContainer = document.getElementById('community-icon-container');
            const defaultCommunityIcon = document.getElementById('default-community-icon');

            // Armazena as fotos das comunidades em um objeto para acesso rápido
            const communityPhotos = {};
            <?php foreach ($userCommunities as $community): ?>
                communityPhotos['<?php echo $community->ComunidadeID; ?>'] = '<?php echo ($community->FotoComunidade ? 'data:image/jpeg;base64,' . base64_encode($community->FotoComunidade) : '../Design/Assets/default_community.png'); ?>';
            <?php endforeach; ?>

            // Função para atualizar o ícone da comunidade
            function updateCommunityIcon() {
                const selectedCommunityId = communitySelect.value;

                // Limpa o container
                communityIconContainer.innerHTML = '';

                if (selectedCommunityId && communityPhotos[selectedCommunityId]) {
                    // Mostra a foto da comunidade selecionada
                    const img = document.createElement('img');
                    img.src = communityPhotos[selectedCommunityId];
                    img.alt = 'Foto da Comunidade';
                    img.className = 'w-full h-full object-cover';
                    communityIconContainer.appendChild(img);

                    // Aumenta o tamanho do container para a foto
                    communityIconContainer.classList.add('w-6', 'h-6');
                    communityIconContainer.classList.remove('w-4', 'h-4');
                } else {
                    // Mostra o ícone padrão
                    communityIconContainer.appendChild(defaultCommunityIcon.cloneNode(true));

                    // Reduz o tamanho do container para o ícone
                    communityIconContainer.classList.add('w-4', 'h-4');
                    communityIconContainer.classList.remove('w-6', 'h-6');
                }
            }

            // Atualiza o ícone quando a seleção muda
            communitySelect.addEventListener('change', updateCommunityIcon);

            // Atualiza o ícone no carregamento inicial
            updateCommunityIcon();
        });
    </script>
</body>

</html>