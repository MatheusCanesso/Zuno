<?php
session_start();
require_once 'Configs/config.php';

// Configuração de codificação alternativa para SQL Server
$conn->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_UTF8);
$conn->exec("SET LANGUAGE 'Portuguese'");
$conn->exec("SET DATEFORMAT ymd");

// Redireciona se não estiver logado
if (!isset($_SESSION['user_id'])) {
    header("Location: Login_Cadastro.php?action=login");
    exit();
}

$userId = $_SESSION['user_id'];
$message = '';
$userData = null;

// Busca dados do usuário
try {
    $stmt = $conn->prepare("SELECT Biografia, Localizacao, SiteWeb, DataNascimento, FotoPerfil, FotoCapa FROM Usuarios WHERE UsuarioID = :userId");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $message = '<p style="color: red;">Erro ao carregar dados: ' . $e->getMessage() . '</p>';
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Processar campos textuais com normalização UTF-8
    $biografia = mb_convert_encoding(trim($_POST['biografia'] ?? ''), 'UTF-8');
    $localizacao = mb_convert_encoding(trim($_POST['localizacao'] ?? ''), 'UTF-8');
    $siteWeb = mb_convert_encoding(trim($_POST['site_web'] ?? ''), 'UTF-8');
    $dataNascimento = $_POST['data_nascimento'] ?? null;

    // Processar imagens como binários
    $processarUpload = function($fileKey) {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES[$fileKey]['tmp_name'];
            return file_exists($fileTmpPath) ? file_get_contents($fileTmpPath) : null;
        }
        return null;
    };

    $fotoPerfilBin = $processarUpload('foto_perfil');
    $fotoCapaBin = $processarUpload('foto_capa');

    try {
        $sql = "UPDATE Usuarios SET 
                Biografia = :biografia,
                Localizacao = :localizacao,
                SiteWeb = :siteWeb,
                DataNascimento = :dataNascimento";
        
        $params = [
            ':biografia' => $biografia,
            ':localizacao' => $localizacao,
            ':siteWeb' => $siteWeb,
            ':dataNascimento' => $dataNascimento,
            ':userId' => $userId
        ];

        if ($fotoPerfilBin !== null) {
            $sql .= ", FotoPerfil = :fotoPerfil";
            $params[':fotoPerfil'] = $fotoPerfilBin;
        }
        if ($fotoCapaBin !== null) {
            $sql .= ", FotoCapa = :fotoCapa";
            $params[':fotoCapa'] = $fotoCapaBin;
        }

        $sql .= " WHERE UsuarioID = :userId";

        $stmt = $conn->prepare($sql);
        
        foreach ($params as $key => &$val) {
            if (strpos($key, 'foto') !== false) {
                $stmt->bindParam($key, $val, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
            } else {
                $stmt->bindParam($key, $val, PDO::PARAM_STR);
            }
        }

        if ($stmt->execute()) {
            $message = '<p style="color: green;">Perfil atualizado com sucesso!</p>';
            header("Refresh: 2; url=Home.php");
        }
    } catch (PDOException $e) {
        $message = '<p style="color: red;">Erro no banco de dados: ' . $e->getMessage() . '</p>';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completar Perfil - Zuno</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS da página de login/cadastro, reutilizado para consistência */
        :root {
            --primary: #21FA90;
            --bg-light: #F9FAFB;
            --bg-dark: #121212;
            --border: #E5E7EB;
            --text-dark: #000000;
            --text-light: #FFFFFF;
            --branco-zunno: #FFFFFF; /* Adicionado */
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }

        .profile-setup-container {
            background-color: var(--branco-zunno);
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            text-align: center;
        }

        .profile-setup-container h2 {
            font-size: 2rem;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
        }

        .profile-setup-container p {
            margin-bottom: 2rem;
            color: #555;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="url"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            color: var(--text-dark);
            background-color: var(--bg-light);
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(33, 250, 144, 0.1);
        }

        .file-upload-preview {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 10px;
        }

        .file-upload-preview img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
        }

        .file-upload-preview .cover-preview {
            width: 100%;
            height: 120px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid var(--border);
        }
        
        .btn-submit {
            width: 100%;
            background-color: var(--primary);
            color: var(--text-light);
            border: none;
            padding: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33, 250, 144, 0.3);
        }

        .message {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 8px;
            font-weight: 500;
        }

        .message p {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="profile-setup-container">
        <h2>Complete seu Perfil Zuno!</h2>
        <p>Adicione algumas informações para que outros usuários possam te conhecer melhor.</p>

        <?php if (!empty($message)): ?>
            <div class="message">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="Setup_Profile.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="biografia">Biografia (máx. 160 caracteres)</label>
                <textarea id="biografia" name="biografia" maxlength="160"><?php echo htmlspecialchars($userData->Biografia ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="localizacao">Localização</label>
                <input type="text" id="localizacao" name="localizacao" value="<?php echo htmlspecialchars($userData->Localizacao ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="site_web">Site/Portfólio</label>
                <input type="url" id="site_web" name="site_web" placeholder="https://seusite.com" value="<?php echo htmlspecialchars($userData->SiteWeb ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="data_nascimento">Data de Nascimento</label>
                <input type="date" id="data_nascimento" name="data_nascimento" value="<?php echo htmlspecialchars($userData->DataNascimento ? date('Y-m-d', strtotime($userData->DataNascimento)) : ''); ?>">
            </div>

            <div class="form-group">
                <label for="foto_perfil">Foto de Perfil</label>
                <input type="file" id="foto_perfil" name="foto_perfil" accept="image/jpeg, image/png, image/gif">
                <?php if (isset($userData->FotoPerfil) && !empty($userData->FotoPerfil)): ?>
                    <div class="file-upload-preview">
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($userData->FotoPerfil); ?>" alt="Foto de Perfil Atual">
                        <span>Foto atual</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="foto_capa">Foto de Capa</label>
                <input type="file" id="foto_capa" name="foto_capa" accept="image/jpeg, image/png, image/gif">
                <?php if (isset($userData->FotoCapa) && !empty($userData->FotoCapa)): ?>
                    <div class="file-upload-preview">
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($userData->FotoCapa); ?>" alt="Foto de Capa Atual" class="cover-preview">
                        <span>Capa atual</span>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-submit">Salvar Perfil</button>
        </form>
    </div>
</body>
</html>