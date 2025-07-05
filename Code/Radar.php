<?php
session_start();
// Define o fuso horário padrão para todas as operações de data/hora no PHP
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

require_once 'Configs/config.php'; // Inclui as funções de criptografia/descriptografia

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
    $zunId = 0; // CORREÇÃO: Inicializado com 0 em vez de null para PDO::PARAM_INPUT_OUTPUT
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

    $selectedGifUrls = [];
    // Antes de processar os GIFs, verifique se é um array válido
    if (isset($_POST['selected_gif_urls'])) {
        $jsonString = '';
        if (is_array($_POST['selected_gif_urls'])) {
            // Se for um array, assume que a string JSON é o primeiro elemento
            // Isso acontece se o input HTML for name="selected_gif_urls[]"
            $jsonString = $_POST['selected_gif_urls'][0] ?? '';
        } else {
            // Se for uma string, usa-a diretamente
            // Isso acontece se o input HTML for name="selected_gif_urls"
            $jsonString = $_POST['selected_gif_urls'];
        }

        $decodedGifs = json_decode($jsonString, true);

        // Verifique explicitamente por erros de decodificação JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ERRO FATAL DE JSON: Falha ao decodificar selected_gif_urls. Motivo: " . json_last_error_msg() . " - JSON recebido: " . var_export($jsonString, true));
            $_SESSION['toast_message'] = 'Erro ao processar seleção de GIFs (formato inválido). Tente novamente.';
            $_SESSION['toast_type'] = 'error';
            header("Location: Radar.php?status=zun_posted");
            exit();
        }

        if (is_array($decodedGifs)) {
            $selectedGifUrls = $decodedGifs;
        } else {
            // Este caso deve ser raro se json_last_error() já pegou o erro, mas é um fallback
            error_log("ERRO LÓGICO: json_decode retornou algo que não é um array. Valor: " . var_export($decodedGifs, true));
            $_SESSION['toast_message'] = 'Erro interno ao processar GIFs. Tente novamente.';
            $_SESSION['toast_type'] = 'error';
            header("Location: Radar.php?status=zun_posted");
            exit();
        }
    }
    // Debugging: Check what GIF URLs are received
    error_log("DEBUG: GIF URLs recebidas no backend: " . var_export($selectedGifUrls, true));


    // Check if there's content (text or at least one file or at least one GIF)
    if (!empty($conteudo) || (isset($_FILES['midia_zun']) && !empty($_FILES['midia_zun']['name'][0]) && $_FILES['midia_zun']['error'][0] === UPLOAD_ERR_OK) || !empty($selectedGifUrls)) {
        try {
            error_log("Preparando chamada para PostarZun...");
            $sql = "DECLARE @output_zun_id BIGINT;
            EXEC PostarZun 
                @UsuarioID = ?, 
                @Conteudo = ?, 
                @ComunidadeID = ?, 
                @ZunPaiID = ?, 
                @ZunOriginalID = ?, 
                @TipoZun = ?, 
                @Visibilidade = ?, 
                @Localizacao = ?, 
                @Idioma = ?, 
                @ZunID = @output_zun_id OUTPUT;
            SELECT @output_zun_id AS zun_id;";

            $stmt = $conn->prepare($sql);

            // Use bindValue em vez de bindParam para todos os parâmetros de entrada
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $conteudo, PDO::PARAM_STR);
            $stmt->bindValue(3, $comunidadeId, $comunidadeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(4, $zunPaiId, $zunPaiId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(5, $zunOriginalId, $zunOriginalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(6, $tipoZun, PDO::PARAM_STR);
            $stmt->bindValue(7, $visibilidade, PDO::PARAM_STR);
            $stmt->bindValue(8, $localizacao, $localizacao === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(9, $idioma, $idioma === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            $stmt->execute();

            // Obtenha o ID do Zun criado
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            $zunId = $result->zun_id;

            error_log("PostarZun executado. ZunID retornado: " . var_export($zunId, true));

            if ($zunId > 0) {
                $postSuccessful = true;
                error_log("ZunID válido retornado: " . $zunId . ". Processando mídias...");

                $filesProcessed = 0; // Para controlar a ordem das mídias

                // --- Handle Local Media Uploads ---
                if (isset($_FILES['midia_zun']) && is_array($_FILES['midia_zun']['name'])) {
                    $totalFiles = count($_FILES['midia_zun']['name']);
                    for ($i = 0; $i < $totalFiles && $filesProcessed < 4; $i++) {
                        if ($_FILES['midia_zun']['error'][$i] === UPLOAD_ERR_OK) {
                            $fileTmpPath = $_FILES['midia_zun']['tmp_name'][$i];
                            $fileType = $_FILES['midia_zun']['type'][$i];

                            $mimeGroup = explode('/', $fileType)[0];
                            $mediaType = 'imagem';

                            if ($mimeGroup === 'image') {
                                if (strpos($fileType, 'gif') !== false) {
                                    $mediaType = 'gif';
                                } else {
                                    $mediaType = 'imagem';
                                }
                            } elseif ($mimeGroup === 'video') {
                                $mediaType = 'video';
                            } else {
                                error_log("Tipo de mídia local não suportado ou inválido: " . $fileType);
                                continue;
                            }

                            $rawMediaContent = file_get_contents($fileTmpPath);
                            $encryptedMediaContent = encryptData($rawMediaContent);

                            $stmtMedia = $conn->prepare("INSERT INTO Midias (ZunID, URL, TipoMidia, Ordem, AltText) VALUES (:zunId, :url, :tipoMidia, :ordem, :altText)");
                            $stmtMedia->bindParam(':zunId', $zunId, PDO::PARAM_INT);
                            $stmtMedia->bindParam(':url', $encryptedMediaContent, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
                            $stmtMedia->bindParam(':tipoMidia', $mediaType, PDO::PARAM_STR);
                            $stmtMedia->bindParam(':ordem', $filesProcessed, PDO::PARAM_INT);
                            $altText = 'Mídia anexada ao Zun';
                            $stmtMedia->bindParam(':altText', $altText, PDO::PARAM_STR);
                            $stmtMedia->execute();
                            $filesProcessed++;
                        } else {
                            error_log("Erro no upload do arquivo local " . $i . ": " . $_FILES['midia_zun']['error'][$i]);
                        }
                    }
                }

                // --- Handle GIF URLs from API ---
                foreach ($selectedGifUrls as $gifUrl) {
                    if ($filesProcessed >= 4) { // Respeitar o limite de 4 mídias
                        break;
                    }

                    $gifContent = false; // Inicializa para garantir que a variável exista
                    $encryptedGifContent = null; // Inicializa para garantir que a variável exista
                    $mediaType = 'gif'; // Define o tipo de mídia explicitamente para o GIF

                    // Tenta buscar o conteúdo binário do GIF a partir da URL
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $gifUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    // Apenas para DEV - Remova/configure em PROD
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                    $gifContent = curl_exec($ch);
                    $curl_error = curl_error($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($gifContent !== false && $http_code >= 200 && $http_code < 300) {
                        error_log("DEBUG: Conteúdo do GIF da URL " . $gifUrl . " buscado com sucesso via cURL. HTTP Code: " . $http_code);
                        $encryptedGifContent = encryptData($gifContent);

                        // Verifica se a criptografia foi bem-sucedida E se o conteúdo não está vazio
                        if ($encryptedGifContent !== false && $encryptedGifContent !== null && $encryptedGifContent !== '') {
                            $stmtGif = $conn->prepare("INSERT INTO Midias (ZunID, URL, TipoMidia, Ordem, AltText) VALUES (:zunId, :url, :tipoMidia, :ordem, :altText)");
                            $stmtGif->bindParam(':zunId', $zunId, PDO::PARAM_INT);
                            $stmtGif->bindParam(':url', $encryptedGifContent, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
                            $stmtGif->bindParam(':tipoMidia', $mediaType, PDO::PARAM_STR);
                            $stmtGif->bindParam(':ordem', $filesProcessed, PDO::PARAM_INT);
                            $altText = 'GIF anexado ao Zun';
                            $stmtGif->bindParam(':altText', $altText, PDO::PARAM_STR);

                            if ($stmtGif->execute()) {
                                error_log("DEBUG: GIF da URL " . $gifUrl . " inserido no DB com sucesso. Ordem: " . $filesProcessed);
                                $filesProcessed++;
                            } else {
                                error_log("ERRO SQL: Falha ao inserir GIF da URL " . $gifUrl . ": " . var_export($stmtGif->errorInfo(), true));
                                $_SESSION['toast_message'] = 'Erro ao salvar um GIF no banco de dados.';
                                $_SESSION['toast_type'] = 'error';
                            }
                        } else {
                            error_log("ERRO: Falha na criptografia ou conteúdo vazio do GIF da URL: " . $gifUrl);
                            $_SESSION['toast_message'] = 'Erro ao criptografar conteúdo do GIF. Tente outro GIF.';
                            $_SESSION['toast_type'] = 'error';
                        }
                    } else {
                        error_log("ERRO: Falha ao buscar conteúdo do GIF da URL: " . $gifUrl . " via cURL. Erro cURL: " . $curl_error . " HTTP Code: " . $http_code);
                        $_SESSION['toast_message'] = 'Não foi possível baixar um dos GIFs selecionados. Tente outro GIF ou um upload de imagem.';
                        $_SESSION['toast_type'] = 'error';
                    }
                }

                $_SESSION['toast_message'] = 'Zun postado com sucesso!';
                $_SESSION['toast_type'] = 'success';
            } else {
                error_log("ZunID não foi retornado ou é inválido (<= 0). Possível falha na SP ou retorno.");
                $_SESSION['toast_message'] = 'Erro ao postar Zun. Tente novamente.';
                $_SESSION['toast_type'] = 'error';
            }

        } catch (PDOException $e) {
            $_SESSION['toast_message'] = 'Erro no banco de dados ao postar Zun: ' . $e->getMessage();
            $_SESSION['toast_type'] = 'error';
            error_log("Erro PDO ao postar Zun: " . $e->getMessage());
        }
    } else {
        $_SESSION['toast_message'] = 'Zun não pode ser vazio (texto, imagem ou GIF).';
        $_SESSION['toast_type'] = 'error';
        error_log("Tentativa de postar Zun vazio (sem texto, sem mídia local e sem GIFs).");
    }
    header("Location: Radar.php?status=zun_posted");
    exit();
}

// Lógica para buscar Zuns para a timeline
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

    // Descriptografar as URLs das mídias após a busca
    foreach ($zuns as $zun) {
        for ($i = 0; $i < $zun->MidiaCount; $i++) {
            if (isset($zun->{"MidiaURL_" . $i}) && !empty($zun->{"MidiaURL_" . $i})) {
                $decryptedContent = decryptData($zun->{"MidiaURL_" . $i});
                if ($decryptedContent !== false) {
                    $zun->{"MidiaURL_" . $i} = $decryptedContent;
                } else {
                    error_log("Falha ao descriptografar mídia para ZunID: " . $zun->ZunID . ", Ordem: " . $i);
                    $zun->{"MidiaURL_" . $i} = null;
                }
            }
        }
    }

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
            /* Increased from 90% */
            max-height: 95%;
            /* Increased from 90% */
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
            /* Fundo transparente por padrão */
            color: white;
            border: none;
            cursor: pointer;
            z-index: 1001;
            font-size: 2.5rem;
            /* Ícone um pouco maior */
            width: 3.5rem;
            /* Área clicável um pouco maior */
            height: 3.5rem;
            /* Área clicável um pouco maior, para formar um quadrado */
            border-radius: 50%;
            /* Torna o botão redondo */
            display: flex;
            /* Para centralizar o ícone */
            justify-content: center;
            /* Para centralizar o ícone */
            align-items: center;
            /* Para centralizar o ícone */
            transition: background-color 0.2s ease, opacity 0.2s ease;
            opacity: 0.9;
            /* Pouco menos opaco por padrão, mas ainda visível */
        }

        .modal-arrow:hover {
            background: rgba(0, 0, 0, 0.5);
            /* Fundo aparece no hover */
            opacity: 1;
        }

        .modal-arrow.left-0 {
            left: -3rem;
            /* Move a seta 3rem para a esquerda, para fora da imagem */
        }

        .modal-arrow.right-0 {
            right: -3rem;
            /* Move a seta 3rem para a direita, para fora da imagem */
        }

        .modal-arrow:disabled {
            opacity: 0.1;
            /* Ainda menos visível quando desabilitado */
            cursor: not-allowed;
            background: none;
            /* Garante que não há fundo quando desabilitado */
        }

        /* Estilo para o Modal de GIF */
        .gif-modal-overlay {
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

        .gif-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .gif-modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 90%;
            overflow-y: auto;
            position: relative;
        }

        .gif-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }

        .gif-grid img {
            width: 100%;
            height: 100px;
            /* Altura fixa para os previews na grade */
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .gif-grid img:hover {
            transform: scale(1.05);
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

        /* Ajustes para todas as imagens de grid */
        .media-grid img,
        .media-grid video {
            width: 100%;
            height: auto;
            /* Permite que a altura se ajuste naturalmente */
            max-height: 400px;
            /* Altura máxima razoável para imagens no feed */
            object-fit: contain;
            /* Garante que a imagem inteira seja visível sem cortar */
            border-radius: 8px;
        }

        /* Ajustes para layouts de várias imagens para evitar corte e permitir maior tamanho */
        .grid-3-images>img:first-child,
        .grid-3-images>video:first-child {
            grid-column: span 2;
            height: auto;
            max-height: 350px;
            /* Aumenta a altura máxima para a imagem maior no grid de 3 */
            object-fit: contain;
        }

        .grid-3-images>img:nth-child(2),
        .grid-3-images>img:nth-child(3),
        .grid-3-images>video:nth-child(2),
        .grid-3-images>video:nth-child(3) {
            height: auto;
            max-height: 200px;
            /* Aumenta a altura máxima para as imagens menores no grid de 3 */
            object-fit: contain;
        }

        .grid-2-images img,
        .grid-2-images video,
        .grid-4-images img,
        .grid-4-images video {
            height: auto;
            /* Permite que a altura se ajuste para outros layouts de grid */
            max-height: 300px;
            /* Aumenta a altura máxima para itens de grid */
            object-fit: contain;
        }

        /* Adicionar bordas entre as mídias no grid */
        .media-grid.grid-cols-2>img:nth-child(odd):not(:last-child),
        .media-grid.grid-cols-2>video:nth-child(odd):not(:last-child) {
            border-right: 1px solid #e5e7eb;
        }

        .media-grid.grid-rows-2>img:nth-child(-n+2),
        .media-grid.grid-rows-2>video:nth-child(-n+2) {
            border-bottom: 1px solid #e5e7eb;
        }

        .media-grid.grid-cols-1>img,
        .media-grid.grid-cols-1>video {
            border: none;
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
                                <div id="community-icon-container"
                                    class="w-6 h-6 rounded-full overflow-hidden flex items-center justify-center bg-gray-200">
                                    <svg id="default-community-icon" class="select-icon w-4 h-4 text-gray-500"
                                        width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path d="M8 14C8 14 9.5 16 12 16C14.5 16 16 14 16 14" stroke="currentColor"
                                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M15 9H15.01" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M9 9H9.01" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" />
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
                            <input type="hidden" name="selected_gif_urls[]" id="selected_gif_urls_input">

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
                                    <button type="button" id="gif_button"
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
                        <div class="p-4 border-b border-gray-200 relative zun-container"
                            onclick="window.location.href='Status.php?id=<?php echo $zun->ZunID; ?>'" style="cursor: pointer;"
                            data-zun-id="<?php echo $zun->ZunID; ?>">
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

                                    <p class="text-gray-800 mb-5 max-w-2xl" style="word-break: break-all;">
                                        <?php echo htmlspecialchars($zun->Conteudo ?? ''); ?>
                                    </p>

                                    <?php if ($zun->MidiaCount > 0): ?>
                                        <?php
                                        $mediaUrls = [];
                                        $mediaTypes = [];
                                        for ($i = 0; $i < $zun->MidiaCount; $i++) {
                                            // Since MidiaURL_ is already decrypted in the PHP logic block above (lines 142-153),
                                            // we just need to use the already decrypted content.
                                            if (isset($zun->{"MidiaURL_" . $i}) && !empty($zun->{"MidiaURL_" . $i})) {
                                                // Determine MIME type based on the stored media type
                                                $mimeType = $zun->{"MidiaTipo_" . $i} === 'gif' ? 'image/gif' : 'image/jpeg';
                                                $mediaUrls[] = 'data:' . $mimeType . ';base64,' . base64_encode($zun->{"MidiaURL_" . $i});
                                                $mediaTypes[] = $zun->{"MidiaTipo_" . $i};
                                            }
                                        }
                                        $mediaCount = count($mediaUrls);
                                        ?>

                                        <div
                                            class="media-container mt-3 mb-4 mr-24 rounded-2xl overflow-hidden border border-gray-200">
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

                                    <div class="flex justify-between mr-1 items-center text-gray-500 text-sm">
                                        <button class="flex items-center space-x-1 hover:text-blue-500">
                                            <i class="far fa-comment"></i> <span><?php echo $zun->Respostas; ?></span>
                                        </button>
                                        <button class="repost-button flex items-center space-x-1 hover:text-lime-500"
                                            data-zun-id="<?php echo $zun->ZunID; ?>"
                                            data-author-name="<?php echo htmlspecialchars($zun->AutorNomeExibicao); ?>"
                                            data-author-username="<?php echo htmlspecialchars($zun->AutorNomeUsuario); ?>"
                                            data-content="<?php echo htmlspecialchars($zun->Conteudo); ?>"
                                            data-photo="<?php echo ($zun->AutorFotoPerfil ? 'data:image/jpeg;base64,' . base64_encode($zun->AutorFotoPerfil) : '../Design/Assets/default_profile.png'); ?>">
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
                                    class="like-button absolute right-16 text-gray-500 hover:text-red-500 flex flex-col items-center top-1/2 -translate-y-1/2"
                                    data-zun-id="<?php echo $zun->ZunID; ?>"
                                    data-liked="<?php echo $zun->ZunLikadoPorMim ? 'true' : 'false'; ?>">
                                    <i
                                        class="<?php echo ($zun->ZunLikadoPorMim ? 'fas fa-heart text-red-500' : 'far fa-heart'); ?> text-xl"></i>
                                    <span class="like-count text-sm"><?php echo $zun->ZunLikes; ?></span>
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
            <button id="prevArrow" class="modal-arrow left-0"><i class="fas fa-chevron-left"></i></button>
            <img src="" alt="Mídia Expandida" class="modal-image" id="expandedImage">
            <button id="nextArrow" class="modal-arrow right-0"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>

    <div id="gifModal" class="gif-modal-overlay">
        <div class="gif-modal-content">
            <h3 class="text-xl font-bold mb-4">Escolher GIF</h3>
            <div class="flex gap-2 mb-4">
                <input type="text" id="gif_search_input" placeholder="Buscar GIFs..."
                    class="flex-1 p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#21fa90]">
                <button id="gif_search_button"
                    class="bg-[#21fa90] text-white px-4 py-2 rounded-lg hover:bg-[#83ecb9]">Buscar</button>
            </div>
            <div id="gif_results_container" class="gif-grid">
                <p class="text-gray-500 text-center col-span-full">Carregando GIFs em alta...</p>
            </div>
            <button id="closeGifModal" class="modal-close-button"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <!-- Modal de Repost -->
    <div id="repostModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 opacity-0 invisible transition-opacity duration-300">
        <div class="bg-white rounded-xl p-6 w-full max-w-md transform scale-95 transition-all duration-300">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Repostar Zun</h3>
                <button id="closeRepostModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="flex items-start space-x-3 border p-3 rounded-lg bg-gray-50 mb-4">
                <img id="repostZunAuthorPhoto" src="" alt="Foto de Perfil" class="w-10 h-10 rounded-full object-cover">
                <div class="flex-1">
                    <div class="flex items-baseline space-x-1">
                        <span id="repostZunAuthorName" class="font-bold text-gray-900"></span>
                        <span id="repostZunAuthorUsername" class="text-gray-500 text-sm"></span>
                    </div>
                    <p id="repostZunContent" class="text-gray-800 text-sm mt-1"></p>
                </div>
            </div>

            <div class="flex flex-col space-y-3">
                <button id="repostOptionButton"
                    class="w-full text-black bg-gray-100 py-3 rounded-full font-semibold hover:bg-[#21fa90] transition-colors duration-200">
                    <i class="fas fa-sync-alt mr-2"></i> Repostar
                </button>
                <button id="quoteZunOptionButton"
                    class="w-full text-black bg-gray-100 py-3 rounded-full font-semibold hover:bg-[#21fa90] transition-colors duration-200">
                    <i class="fas fa-quote-left mr-2"></i> Citar Zun
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const textarea = document.getElementById('zunTextarea');
            const charCounter = document.getElementById('charCounter');
            const charCount = document.getElementById('charCount');
            const zunButton = document.getElementById('zunButton');
            const midiaInput = document.getElementById('midia_zun_input');
            const selectedGifUrlsInput = document.getElementById('selected_gif_urls_input');
            const imagePreviewContainer = document.getElementById('image-preview-container');
            const gifButton = document.getElementById('gif_button');
            const gifModal = document.getElementById('gifModal');
            const closeGifModalBtn = document.getElementById('closeGifModal');
            const gifSearchInput = document.getElementById('gif_search_input');
            const gifSearchButton = document.getElementById('gif_search_button');
            const gifResultsContainer = document.getElementById('gif_results_container');

            const maxLength = 280;
            const warningThreshold = 20;
            const MAX_MEDIA_COUNT = 4; // Limite total de imagens/GIFs

            // selectedFiles agora pode conter File objetos (para uploads) ou URLs de string (para GIFs)
            let selectedFiles = []; // Armazena File objetos ou {type: 'gif', url: '...'}

            function updateCharCounter() {
                const currentLength = textarea.value.length;
                charCount.textContent = currentLength;
                // Habilitar botão se houver texto ou mídias selecionadas
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
                imagePreviewContainer.innerHTML = ''; // Limpa previews existentes
                const currentGifUrls = []; // Para atualizar o hidden input de GIFs

                if (selectedFiles.length === 0) {
                    imagePreviewContainer.style.display = 'none';
                    selectedGifUrlsInput.value = ''; // Limpa o hidden input
                    return;
                }

                imagePreviewContainer.style.display = 'grid';
                imagePreviewContainer.className = 'mt-2 gap-2 rounded-lg overflow-hidden border border-gray-200';

                // Define classes de grade com base no número de mídias
                if (selectedFiles.length === 1) {
                    imagePreviewContainer.classList.add('grid-1-image');
                } else if (selectedFiles.length === 2) {
                    imagePreviewContainer.classList.add('grid-2-images');
                } else if (selectedFiles.length === 3) {
                    imagePreviewContainer.classList.add('grid-3-images');
                } else if (selectedFiles.length >= 4) {
                    imagePreviewContainer.classList.add('grid-4-images');
                }

                // Cria um novo FileList para o input de upload local, excluindo GIFs de URL
                const newFileList = new DataTransfer();

                selectedFiles.forEach((mediaItem, index) => {
                    const imgDiv = document.createElement('div');
                    imgDiv.className = 'relative group';

                    const closeButton = document.createElement('button');
                    closeButton.className = 'absolute top-1 right-1 bg-black bg-opacity-50 text-white rounded-full p-1 text-xs opacity-0 group-hover:opacity-100 transition-opacity';
                    closeButton.innerHTML = '<i class="fas fa-times"></i>';
                    closeButton.onclick = (event) => {
                        event.preventDefault();
                        selectedFiles = selectedFiles.filter((f, i) => i !== index);
                        updateImagePreviews();
                        updateCharCounter();
                    };
                    imgDiv.appendChild(closeButton);

                    let mediaElement;
                    if (mediaItem.type === 'gif') { // É um GIF de URL
                        mediaElement = document.createElement('img'); // Usar <img> para GIFs
                        mediaElement.src = mediaItem.url;
                        mediaElement.alt = 'GIF Preview';
                        currentGifUrls.push(mediaItem.url); // Adiciona a URL ao array de GIFs para o hidden input
                    } else { // É um File (imagem local)
                        mediaElement = document.createElement('img'); // Ou <video> se você tiver suporte a vídeo
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            mediaElement.src = e.target.result;
                        };
                        reader.readAsDataURL(mediaItem);
                        newFileList.items.add(mediaItem); // Adiciona o arquivo local ao novo FileList
                    }

                    mediaElement.className = 'w-full object-cover';

                    // Aplica alturas específicas com base no número total de mídias
                    if (selectedFiles.length === 1) {
                        mediaElement.classList.add('h-auto', 'max-h-96');
                        mediaElement.style.cursor = 'pointer'; // Adicionar cursor pointer para indicar que é clicável
                    } else if (selectedFiles.length === 3 && index === 0) {
                        mediaElement.classList.add('col-span-2', 'h-64');
                        mediaElement.style.cursor = 'pointer';
                    } else {
                        mediaElement.classList.add('h-40');
                        mediaElement.style.cursor = 'pointer';
                    }
                    mediaElement.onclick = () => openModal(mediaElement.src); // Adiciona o onclick para o modal de expandir

                    imgDiv.appendChild(mediaElement);
                    imagePreviewContainer.appendChild(imgDiv);
                });

                midiaInput.files = newFileList.files; // Atualiza o input de arquivos locais
                selectedGifUrlsInput.value = JSON.stringify(currentGifUrls); // Atualiza o hidden input de GIFs
            }

            textarea.addEventListener('input', updateCharCounter);

            // Listener para upload de arquivos locais
            midiaInput.addEventListener('change', function () {
                const newLocalFiles = Array.from(midiaInput.files);

                // Filtra para garantir que não excedemos o limite com novos arquivos locais
                const remainingSlots = MAX_MEDIA_COUNT - selectedFiles.length;
                const filesToAdd = newLocalFiles.slice(0, remainingSlots);

                // Adiciona os novos arquivos locais ao array
                selectedFiles.push(...filesToAdd);

                // Limpa o input de arquivos para permitir novas seleções
                midiaInput.value = '';

                updateImagePreviews();
                updateCharCounter();
            });

            // --- Lógica do Modal de GIF ---
            gifButton.addEventListener('click', function () {
                if (selectedFiles.length < MAX_MEDIA_COUNT) {
                    gifModal.classList.add('active');
                    if (gifResultsContainer.children.length <= 1) { // Só carrega trending se não houver resultados
                        searchGifs(''); // Carrega GIFs em alta na abertura
                    }
                } else {
                    Toastify({
                        text: "Você pode adicionar no máximo " + MAX_MEDIA_COUNT + " mídias (imagens/GIFs) por Zun.",
                        duration: 3000,
                        newWindow: true,
                        close: true,
                        gravity: "bottom",
                        position: "center",
                        stopOnFocus: true,
                        style: {
                            background: "#ef4444",
                            borderRadius: "8px",
                            fontFamily: "'Urbanist', sans-serif"
                        },
                    }).showToast();
                }
            });

            closeGifModalBtn.addEventListener('click', function () {
                gifModal.classList.remove('active');
                gifSearchInput.value = ''; // Limpa a busca ao fechar
                gifResultsContainer.innerHTML = '<p class="text-gray-500 text-center col-span-full">Carregando GIFs em alta...</p>'; // Reseta a mensagem
            });

            gifSearchButton.addEventListener('click', function () {
                searchGifs(gifSearchInput.value);
            });

            gifSearchInput.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    searchGifs(gifSearchInput.value);
                }
            });

            function searchGifs(query) {
                gifResultsContainer.innerHTML = '<p class="text-gray-500 text-center col-span-full">Buscando GIFs...</p>';
                fetch(`fetch_gifs.php?q=${encodeURIComponent(query)}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro ao buscar GIFs: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        gifResultsContainer.innerHTML = ''; // Limpa resultados anteriores
                        if (data.data && data.data.length > 0) {
                            data.data.forEach(gif => {
                                const img = document.createElement('img');
                                img.src = gif.url;
                                img.alt = 'GIF';
                                img.title = gif.source; // Mostra a fonte (Giphy/Tenor) no tooltip
                                img.classList.add('gif-item');
                                img.addEventListener('click', () => selectGif(gif.url));
                                gifResultsContainer.appendChild(img);
                            });
                        } else {
                            gifResultsContainer.innerHTML = '<p class="text-gray-500 text-center col-span-full">Nenhum GIF encontrado.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar GIFs:', error);
                        gifResultsContainer.innerHTML = `<p class="text-red-500 text-center col-span-full">Erro ao carregar GIFs: ${error.message}</p>`;
                    });
            }

            function selectGif(gifUrl) {
                if (selectedFiles.length < MAX_MEDIA_COUNT) {
                    selectedFiles.push({ type: 'gif', url: gifUrl });
                    updateImagePreviews();
                    updateCharCounter();
                    gifModal.classList.remove('active'); // Fecha o modal após selecionar
                    gifSearchInput.value = ''; // Limpa a busca
                    gifResultsContainer.innerHTML = '<p class="text-gray-500 text-center col-span-full">Carregando GIFs em alta...</p>'; // Reseta a mensagem
                } else {
                    Toastify({
                        text: "Você pode adicionar no máximo " + MAX_MEDIA_COUNT + " mídias (imagens/GIFs) por Zun.",
                        duration: 3000,
                        newWindow: true,
                        close: true,
                        gravity: "bottom",
                        position: "center",
                        stopOnFocus: true,
                        style: {
                            background: "#ef4444",
                            borderRadius: "8px",
                            fontFamily: "'Urbanist', sans-serif"
                        },
                    }).showToast();
                }
            }

            // Initial setup
            updateCharCounter();
            // Mostrar toasts (mensagens de sucesso/erro)
            const toastMessage = "<?php echo isset($_SESSION['toast_message']) ? htmlspecialchars($_SESSION['toast_message']) : ''; ?>";
            const toastType = "<?php echo isset($_SESSION['toast_type']) ? htmlspecialchars($_SESSION['toast_type']) : ''; ?>";

            if (toastMessage) {
                let backgroundColor = 'rgb(26, 189, 110)';
                if (toastType === 'error') {
                    backgroundColor = '#ef4444';
                }
                Toastify({
                    text: toastMessage,
                    duration: 3000,
                    newWindow: true,
                    close: true,
                    gravity: "bottom",
                    position: "center",
                    stopOnFocus: true,
                    style: {
                        background: backgroundColor,
                        borderRadius: "8px",
                        fontFamily: "'Urbanist', sans-serif"
                    },
                    onClick: function () { }
                }).showToast();
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
            const imageModal = document.getElementById('imageModal');
            const expandedImage = document.getElementById('expandedImage');
            const closeModalBtn = document.getElementById('closeModal');
            const prevArrow = document.getElementById('prevArrow');
            const nextArrow = document.getElementById('nextArrow');

            // Variáveis para armazenar as URLs e o índice atual das mídias no modal
            let currentModalMediaUrls = [];
            let currentModalMediaIndex = 0;

            // Armazena as fotos das comunidades em um objeto para acesso rápido
            const communityPhotos = {};
            <?php foreach ($userCommunities as $community): ?>
                communityPhotos['<?php echo $community->ComunidadeID; ?>'] = '<?php echo ($community->FotoComunidade ? 'data:image/jpeg;base64,' . base64_encode($community->FotoComunidade) : '../Design/Assets/default_community.png'); ?>';
            <?php endforeach; ?>

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
            // Agora recebe o array de URLs e o índice da imagem clicada
            window.openModal = function (mediaUrlsArray, initialIndex) {
                currentModalMediaUrls = mediaUrlsArray;
                showMediaInModal(initialIndex);
                imageModal.classList.add('active');
            };

            // Função para fechar o modal
            closeModalBtn.addEventListener('click', function () {
                imageModal.classList.remove('active');
                expandedImage.src = ''; // Limpa a imagem para otimizar memória
                currentModalMediaUrls = []; // Limpa as URLs armazenadas
                currentModalMediaIndex = 0; // Reseta o índice
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

            // Fechar modal clicando fora da imagem (mas não nas setas)
            imageModal.addEventListener('click', function (event) {
                // Certifica-se de que o clique foi no overlay e não na imagem ou nas setas
                if (event.target === imageModal) {
                    closeModalBtn.click(); // Simula o clique no botão de fechar
                }
            });


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

            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.zun-container').forEach(container => {
                    container.addEventListener('click', function (e) {
                        // Verifica se o clique foi em um elemento interativo
                        // Se for uma imagem ou botão dentro do .zun-container,
                        // o openModal ou a ação do botão será tratada individualmente
                        // e não deve disparar o redirecionamento do container.
                        // O event.stopPropagation() nas imagens já cuida disso.
                        if (e.target.closest('.interactive')) {
                            return;
                        }

                        // Se o clique não foi em uma mídia, redireciona para a página de status do Zun.
                        // Este listener é um fallback para cliques no texto ou outras áreas.
                        if (!e.target.closest('img')) { // Não redireciona se o clique foi em uma imagem
                            window.location.href = 'Status.php?id=' + this.getAttribute('data-zun-id');
                        }
                    });
                });
            });

            // Lógica para Curtir/Descurtir Zun
            document.querySelectorAll('.like-button').forEach(button => {
                button.addEventListener('click', function (event) {
                    event.stopPropagation();

                    const zunId = this.dataset.zunId;
                    const isLiked = this.dataset.liked === 'true';
                    const icon = this.querySelector('i');
                    const likeCountSpan = this.querySelector('.like-count');
                    let currentLikes = parseInt(likeCountSpan.textContent);

                    // Otimização de UI - atualiza visualmente primeiro
                    if (isLiked) {
                        icon.classList.remove('fas', 'text-red-500');
                        icon.classList.add('far');
                        currentLikes--;
                    } else {
                        icon.classList.remove('far');
                        icon.classList.add('fas', 'text-red-500');
                        currentLikes++;
                    }
                    likeCountSpan.textContent = currentLikes;

                    // Envia a requisição AJAX
                    fetch('Ponto-Like.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `zun_id=${zunId}&action=${isLiked ? 'unlike' : 'like'}`
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Erro na resposta do servidor');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Atualiza com os valores do servidor
                                likeCountSpan.textContent = data.newLikes;
                                this.dataset.liked = data.liked;
                            } else {
                                // Reverte a UI se falhou
                                revertLikeUI();
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            // Reverte a UI em caso de erro
                            revertLikeUI();
                            showErrorToast("Erro de conexão. Verifique sua internet.");
                        });

                    function revertLikeUI() {
                        if (isLiked) {
                            icon.classList.remove('far');
                            icon.classList.add('fas', 'text-red-500');
                            likeCountSpan.textContent = currentLikes + 1;
                        } else {
                            icon.classList.remove('fas', 'text-red-500');
                            icon.classList.add('far');
                            likeCountSpan.textContent = currentLikes - 1;
                        }
                    }

                    function showErrorToast(message) {
                        Toastify({
                            text: message,
                            duration: 3000,
                            gravity: "bottom",
                            position: "center",
                            style: {
                                background: "#ef4444",
                                borderRadius: "8px",
                                fontFamily: "'Urbanist', sans-serif"
                            },
                        }).showToast();
                    }
                });
            });
        });
        // Variáveis do modal de repost
        const repostModal = document.getElementById('repostModal');
        const closeRepostModalButton = document.getElementById('closeRepostModal');
        const repostOptionButton = document.getElementById('repostOptionButton');
        const quoteZunOptionButton = document.getElementById('quoteZunOptionButton');
        const repostZunAuthorPhoto = document.getElementById('repostZunAuthorPhoto');
        const repostZunAuthorName = document.getElementById('repostZunAuthorName');
        const repostZunAuthorUsername = document.getElementById('repostZunAuthorUsername');
        const repostZunContent = document.getElementById('repostZunContent');

        let currentRepostZunId = null; // Para armazenar o ZunID do Zun que será repostado/citado

        // Função para abrir o modal de repost
        function openRepostModal(zunId, authorName, authorUsername, content, photo) {
            currentRepostZunId = zunId;

            // Preencher os dados do modal
            repostZunAuthorPhoto.src = photo;
            repostZunAuthorName.textContent = authorName;
            repostZunAuthorUsername.textContent = `@${authorUsername}`;
            repostZunContent.textContent = content;

            // Mostrar o modal com animação
            repostModal.classList.remove('invisible', 'opacity-0');
            repostModal.classList.add('opacity-100');
            document.querySelector('#repostModal > div').classList.remove('scale-95');
            document.querySelector('#repostModal > div').classList.add('scale-100');

            // Bloquear scroll da página
            document.body.style.overflow = 'hidden';
        }

        // Função para fechar o modal de repost
        function closeRepostModal() {
            repostModal.classList.remove('opacity-100');
            repostModal.classList.add('opacity-0');
            document.querySelector('#repostModal > div').classList.remove('scale-100');
            document.querySelector('#repostModal > div').classList.add('scale-95');

            setTimeout(() => {
                repostModal.classList.add('invisible');
                document.body.style.overflow = '';
            }, 300);

            currentRepostZunId = null;
        }

        // Event listeners para o modal de repost
        closeRepostModalButton.addEventListener('click', closeRepostModal);

        repostModal.addEventListener('click', function (event) {
            if (event.target === repostModal) {
                closeRepostModal();
            }
        });

        // Lógica para o botão "Repostar"
        repostOptionButton.addEventListener('click', function () {
            if (currentRepostZunId) {
                // Aqui você faria uma requisição AJAX para registrar o repost
                fetch('Rezun-Repost.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `zun_id=${currentRepostZunId}&action=repost`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Toastify({
                                text: "Zun repostado com sucesso!",
                                duration: 3000,
                                newWindow: true,
                                close: true,
                                gravity: "bottom",
                                position: "center",
                                stopOnFocus: true,
                                style: {
                                    background: "rgb(26, 189, 110)",
                                    borderRadius: "8px",
                                    fontFamily: "'Urbanist', sans-serif"
                                },
                            }).showToast();

                            // Atualizar contador de reposts no botão
                            const repostButton = document.querySelector(`.repost-button[data-zun-id="${currentRepostZunId}"]`);
                            if (repostButton) {
                                const countSpan = repostButton.querySelector('span');
                                if (countSpan) {
                                    const currentCount = parseInt(countSpan.textContent);
                                    countSpan.textContent = currentCount + 1;
                                }
                            }
                        } else {
                            Toastify({
                                text: data.message || "Erro ao repostar o Zun",
                                duration: 3000,
                                newWindow: true,
                                close: true,
                                gravity: "bottom",
                                position: "center",
                                stopOnFocus: true,
                                style: {
                                    background: "#ef4444",
                                    borderRadius: "8px",
                                    fontFamily: "'Urbanist', sans-serif"
                                },
                            }).showToast();
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Toastify({
                            text: "Erro de conexão ao repostar",
                            duration: 3000,
                            newWindow: true,
                            close: true,
                            gravity: "bottom",
                            position: "center",
                            stopOnFocus: true,
                            style: {
                                background: "#ef4444",
                                borderRadius: "8px",
                                fontFamily: "'Urbanist', sans-serif"
                            },
                        }).showToast();
                    });

                closeRepostModal();
            }
        });

        // Lógica para o botão "Citar Zun"
        quoteZunOptionButton.addEventListener('click', function () {
            if (currentRepostZunId) {
                // Redirecionar para a página de postagem com o Zun citado
                window.location.href = `Postagem.php?quote_zun_id=${currentRepostZunId}`;
            }
        });

        // Modificar os botões de repost existentes para abrir o modal
        document.querySelectorAll('.repost-button').forEach(button => {
            button.addEventListener('click', function (event) {
                event.stopPropagation();

                const zunId = this.dataset.zunId;
                const authorName = this.dataset.authorName;
                const authorUsername = this.dataset.authorUsername;
                const content = this.dataset.content;
                const photo = this.dataset.photo;

                openRepostModal(zunId, authorName, authorUsername, content, photo);
            });
        });
    </script>
</body>

</html>