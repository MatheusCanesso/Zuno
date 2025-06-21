<?php
session_start(); // Inicia a sessão para gerenciar o estado do usuário
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'Configs/config.php'; // Inclui o arquivo de configuração do banco de dados

$message = ''; // Variável para armazenar mensagens de sucesso ou erro

// --- Lógica de Cadastro ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $nomeCompleto = trim($_POST['reg_name']);
    $nomeUsuario = trim($_POST['reg_username']);
    $email = trim($_POST['reg_email']);
    $senha = $_POST['reg_password'];
    $confirmarSenha = $_POST['reg_confirm_password'];

    // Validações básicas
    if (empty($nomeCompleto) || empty($nomeUsuario) || empty($email) || empty($senha) || empty($confirmarSenha)) {
        $message = '<p style="color: red;">Por favor, preencha todos os campos.</p>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<p style="color: red;">Formato de e-mail inválido.</p>';
    } elseif ($senha !== $confirmarSenha) {
        $message = '<p style="color: red;">As senhas não coincidem.</p>';
    } elseif (strlen($senha) < 6) {
        $message = '<p style="color: red;">A senha deve ter no mínimo 6 caracteres.</p>';
    } else {
        // Criptografar a senha com SHA-256
        $senhaHash = hash('sha256', $senha); // Usando SHA-256 conforme solicitado

        try {
            // Verificar se o nome de usuário ou e-mail já existem
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM Usuarios WHERE NomeUsuario = :nomeUsuario OR Email = :email");
            $stmtCheck->bindParam(':nomeUsuario', $nomeUsuario);
            $stmtCheck->bindParam(':email', $email);
            $stmtCheck->execute();
            $count = $stmtCheck->fetchColumn();

            if ($count > 0) {
                $message = '<p style="color: red;">Nome de usuário ou e-mail já cadastrados.</p>';
            } else {
                // Inserir o novo usuário no banco de dados
                $stmt = $conn->prepare("INSERT INTO Usuarios (NomeUsuario, NomeExibicao, Email, SenhaHash, DataCriacao, Ativo) VALUES (:nomeUsuario, :nomeExibicao, :email, :senhaHash, GETDATE(), 1)");
                $stmt->bindParam(':nomeUsuario', $nomeUsuario);
                $stmt->bindParam(':nomeExibicao', $nomeCompleto); // Usando nome completo como nome de exibição inicial
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':senhaHash', $senhaHash);

                if ($stmt->execute()) {
                    // Método alternativo e mais confiável para SQL Server
                    $stmtGetId = $conn->prepare("SELECT UsuarioID FROM Usuarios WHERE Email = :email");
                    $stmtGetId->bindParam(':email', $email);
                    $stmtGetId->execute();
                    $newUser = $stmtGetId->fetch(PDO::FETCH_OBJ);

                    if ($newUser && isset($newUser->UsuarioID)) {
                        $_SESSION['user_id'] = $newUser->UsuarioID;
                        $_SESSION['username'] = $nomeUsuario;
                        $_SESSION['email'] = $email;
                        header("Location: Setup_Profile.php");
                        exit();
                    } else {
                        // Log do erro para diagnóstico
                        error_log("Falha ao obter ID do usuário cadastrado. Email: $email");
                        $message = '<p style="color: red;">Cadastro realizado, mas houve um problema ao redirecionar. Por favor, faça login.</p>';
                        header("Location: Login-Cadastro.php?status=registered");
                        exit();
                    }
                } else {
                    $message = '<p style="color: red;">Erro ao cadastrar usuário. Tente novamente.</p>';
                }

            }
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Erro no banco de dados: ' . $e->getMessage() . '</p>';
        }
    }
}

// --- Lógica de Login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $emailUsername = trim($_POST['email_username']);
    $senhaDigitada = $_POST['password'];

    if (empty($emailUsername) || empty($senhaDigitada)) {
        $message = '<p style="color: red;">Por favor, preencha todos os campos.</p>';
    } else {
        $senhaHashDigitada = hash('sha256', $senhaDigitada); // Hashing da senha digitada

        try {
            // Buscar usuário por email ou nome de usuário
            $stmt = $conn->prepare("SELECT UsuarioID, NomeUsuario, Email, SenhaHash, Ativo FROM Usuarios WHERE (Email = :emailUsername OR NomeUsuario = :emailUsername) AND Ativo = 1");
            $stmt->bindParam(':emailUsername', $emailUsername);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_OBJ); // Usar FETCH_OBJ para acessar como objeto

            if ($user) {
                // Verificar a senha
                if ($user->SenhaHash === $senhaHashDigitada) {
                    // Login bem-sucedido
                    $_SESSION['user_id'] = $user->UsuarioID;
                    $_SESSION['username'] = $user->NomeUsuario;
                    $_SESSION['email'] = $user->Email;
                    // Redirecionar para a página principal da aplicação
                    header("Location: Profile.php"); // Altere para a página principal do seu app
                    exit();
                } else {
                    $message = '<p style="color: red;">Credenciais inválidas. Verifique seu e-mail/usuário e senha.</p>';
                }
            } else {
                $message = '<p style="color: red;">Credenciais inválidas. Verifique seu e-mail/usuário e senha.</p>';
            }
        } catch (PDOException $e) {
            $message = '<p style="color: red;">Erro no banco de dados: ' . $e->getMessage() . '</p>';
        }
    }
}

// Verifica se há uma mensagem de status do redirecionamento (após cadastro)
if (isset($_GET['status']) && $_GET['status'] === 'registered') {
    $message = '<p style="color: green;">Cadastro realizado com sucesso! Por favor, faça login.</p>';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar no Zuno</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* === SOLUÇÃO PARA BARRAS DE ROLAGEM === */
        ::-webkit-scrollbar {
            display: none;
        }

        html {
            -ms-overflow-style: none;
            scrollbar-width: none;
            overflow: hidden;
            scroll-behavior: smooth;
        }

        /* === SEU CSS ORIGINAL === */
        :root {
            --primary: #21FA90;
            --bg-light: #F9FAFB;
            --bg-dark: #121212;
            --border: #E5E7EB;
            --text-dark: #000000;
            --text-light: #FFFFFF;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            display: flex;
            overflow-y: auto;
            height: 100vh;
            margin: 0;
            padding: 0;
        }

        :root {
            --primary: #21FA90;
            --bg-light: #F9FAFB;
            --bg-dark: #121212;
            --border: #E5E7EB;
            --text-dark: #000000;
            --text-light: #FFFFFF;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-light);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }

        .auth-container {
            display: flex;
            width: 100%;
        }

        .auth-left {
            flex: 1;
            background:
                linear-gradient(135deg, rgba(33, 250, 144, 0.9), rgba(0, 209, 255, 0.9)),
                url('../Design/Assets/background_cadastro-login.png') no-repeat center center;
            background-size: cover;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 80px;
            width: 50%;
            position: relative;
        }

        .auth-image {
            width: 100%;
            height: auto;
            max-height: 300px;
            object-fit: cover;
            margin-top: 40px;
            border-radius: 12px;
            align-self: flex-end;
            opacity: 0.5;
        }

        .auth-left h1 {
            font-size: 42px;
            margin-bottom: 20px;
        }

        .auth-left p {
            font-size: 18px;
            line-height: 1.6;
        }

        .auth-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-top: 170px;
            padding: 0 80px;
        }

        .auth-form {
            max-width: 400px;
            max-height: 600px;
            width: 100%;
        }

        .auth-logo {
            font-weight: 700;
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 40px;
        }

        .auth-tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border);
        }

        .auth-tab {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 500;
        }

        .auth-tab.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(33, 250, 144, 0.1);
        }

        .auth-button {
            width: 100%;
            background-color: var(--primary);
            color: white;
            /* Texto já está branco por padrão */
            border: none;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .auth-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33, 250, 144, 0.3);
        }

        .auth-divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: #777;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid var(--border);
        }

        .auth-divider::before {
            margin-right: 10px;
        }

        .auth-divider::after {
            margin-left: 10px;
        }

        .social-login {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .social-button {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .social-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .auth-footer {
            text-align: center;
            margin-top: 30px;
            color: #777;
        }

        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
        }

        /* Formulário de cadastro - inicialmente escondido */
        #register-form {
            display: none;
        }

        @media (max-width: 768px) {
            .auth-container {
                flex-direction: column;
            }

            .auth-left {
                padding: 40px 20px;
                text-align: center;
            }

            .auth-right {
                padding: 40px 20px;
            }
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <div class="auth-left">
            <h1>Zune livre, conecta rápido.</h1>
            <p>No Zuno, sua voz vem primeiro.<br>
                Compartilhe pensamentos, conecte-se com comunidades e sinta o pulso <br>do que está acontecendo agora.
            </p>
        </div>
        <div class="auth-right">
            <div class="auth-form">
                <div class="auth-logo">Zuno</div>

                <div class="auth-tabs">
                    <div class="auth-tab <?php echo (!isset($_POST['action']) || $_POST['action'] === 'login' || (isset($_GET['status']) && $_GET['status'] === 'registered')) ? 'active' : ''; ?>"
                        onclick="switchTab('login')">Entrar</div>
                    <div class="auth-tab <?php echo (isset($_POST['action']) && $_POST['action'] === 'register') ? 'active' : ''; ?>"
                        onclick="switchTab('register')">Cadastrar</div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="message">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form id="login-form" method="POST" action="Login-Cadastro.php">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="email">E-mail ou nome de usuário</label>
                        <input type="text" id="email" name="email_username" placeholder="exemplo@email.com" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Senha</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="auth-button">Entrar</button>

                    <div class="auth-footer">
                        <a href="#">Esqueceu sua senha?</a>
                    </div>

                    <div class="auth-divider">ou</div>

                    <div class="social-login">
                        <div class="social-button">G</div>
                        <div class="social-button">f</div>
                        <div class="social-button">A</div>
                    </div>

                    <div class="auth-footer">
                        Não tem uma conta? <a href="#" onclick="switchTab('register'); return false;">Cadastre-se</a>
                    </div>
                </form>

                <form id="register-form" method="POST" action="Login-Cadastro.php">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label for="reg-name">Nome completo</label>
                        <input type="text" id="reg-name" name="reg_name" placeholder="Seu nome" required>
                    </div>

                    <div class="form-group">
                        <label for="reg-username">Nome de usuário</label>
                        <input type="text" id="reg-username" name="reg_username" placeholder="@seudousuario" required>
                    </div>

                    <div class="form-group">
                        <label for="reg-email">E-mail</label>
                        <input type="email" id="reg-email" name="reg_email" placeholder="exemplo@email.com" required>
                    </div>

                    <div class="form-group">
                        <label for="reg-password">Senha</label>
                        <input type="password" id="reg-password" name="reg_password" placeholder="••••••••" required>
                    </div>

                    <div class="form-group">
                        <label for="reg-confirm-password">Confirmar senha</label>
                        <input type="password" id="reg-confirm-password" name="reg_confirm_password"
                            placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="auth-button">Criar conta</button>

                    <div class="auth-divider">ou</div>

                    <div class="social-login">
                        <div class="social-button">G</div>
                        <div class="social-button">f</div>
                        <div class="social-button">A</div>
                    </div>

                    <div class="auth-footer">
                        Já tem uma conta? <a href="#" onclick="switchTab('login'); return false;">Entrar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const loginTab = document.querySelector('.auth-tabs div:nth-child(1)');
            const registerTab = document.querySelector('.auth-tabs div:nth-child(2)');
            const loginForm = document.getElementById('login-form');
            const registerForm = document.getElementById('register-form');

            if (tab === 'login') {
                loginTab.classList.add('active');
                registerTab.classList.remove('active');
                loginForm.style.display = 'block';
                registerForm.style.display = 'none';
            } else {
                loginTab.classList.remove('active');
                registerTab.classList.add('active');
                loginForm.style.display = 'none';
                registerForm.style.display = 'block';
            }
        }
        // Lógica para ativar a aba correta ao carregar a página (via URL param ou POST)
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const actionParam = urlParams.get('action'); // Para linkar de fora

            // Verifica se houve uma submissão POST de registro
            const isPostRegister = '<?php echo (isset($_POST["action"]) && $_POST["action"] === "register") ? "true" : "false"; ?>';

            if (isPostRegister === 'true') {
                switchTab('register');
            } else if (status === 'registered' || actionParam === 'login') {
                switchTab('login');
            } else if (actionParam === 'register') {
                switchTab('register');
            } else {
                // Padrão para login se nenhum parâmetro ou POST de registro for detectado
                switchTab('login');
            }
        });
    </script>
</body>

</html>