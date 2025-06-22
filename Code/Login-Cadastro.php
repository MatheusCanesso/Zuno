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
            // --- DEBUG LOG: Tentando verificar usuário/email existente (Cadastro) ---
            error_log(date('[Y-m-d H:i:s]') . " Attempting to check for existing user during registration...");

            // Verificar se o nome de usuário ou e-mail já existem
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM Usuarios WHERE NomeUsuario = :nomeUsuario OR Email = :email");
            $stmtCheck->bindParam(':nomeUsuario', $nomeUsuario);
            $stmtCheck->bindParam(':email', $email);
            
            // --- DEBUG LOG: Executando query de verificação de cadastro ---
            error_log(date('[Y-m-d H:i:s]') . " Executing registration check query: SELECT COUNT(*) FROM Usuarios WHERE NomeUsuario = '$nomeUsuario' OR Email = '$email'"); // Apenas para debug, cuidado em produção

            $stmtCheck->execute();
            $count = $stmtCheck->fetchColumn();
            
            // --- DEBUG LOG: Query de verificação de cadastro executada com sucesso ---
            error_log(date('[Y-m-d H:i:s]') . " Registration check query executed successfully. Count: " . $count);

            if ($count > 0) {
                $message = '<p style="color: red;">Nome de usuário ou e-mail já cadastrados.</p>';
            } else {
                // --- DEBUG LOG: Tentando inserir novo usuário ---
                error_log(date('[Y-m-d H:i:s]') . " Attempting to insert new user: " . $nomeUsuario);
                
                // Inserir o novo usuário no banco de dados
                $stmt = $conn->prepare("INSERT INTO Usuarios (NomeUsuario, NomeExibicao, Email, SenhaHash, DataCriacao, Ativo) VALUES (:nomeUsuario, :nomeExibicao, :email, :senhaHash, GETDATE(), 1)");
                $stmt->bindParam(':nomeUsuario', $nomeUsuario);
                $stmt->bindParam(':nomeExibicao', $nomeCompleto); // Usando nome completo como nome de exibição inicial
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':senhaHash', $senhaHash);
                
                // --- DEBUG LOG: Executando query de inserção de cadastro ---
                error_log(date('[Y-m-d H:i:s]') . " Executing registration insert query.");

                if ($stmt->execute()) {
                    // --- DEBUG LOG: Inserção de usuário bem-sucedida, buscando ID ---
                    error_log(date('[Y-m-d H:i:s]') . " User inserted successfully, fetching ID.");

                    // Método alternativo e mais confiável para SQL Server
                    $stmtGetId = $conn->prepare("SELECT UsuarioID FROM Usuarios WHERE Email = :email");
                    $stmtGetId->bindParam(':email', $email);
                    $stmtGetId->execute();
                    $newUser = $stmtGetId->fetch(PDO::FETCH_OBJ);

                    if ($newUser && isset($newUser->UsuarioID)) {
                        $_SESSION['user_id'] = $newUser->UsuarioID;
                        $_SESSION['username'] = $nomeUsuario;
                        $_SESSION['email'] = $email;
                        // --- DEBUG LOG: Redirecionando para Setup_Profile.php ---
                        error_log(date('[Y-m-d H:i:s]') . " Redirecting to Setup_Profile.php");
                        header("Location: Setup_Profile.php");
                        exit();
                    } else {
                        // --- DEBUG LOG: Falha ao obter ID do usuário cadastrado ---
                        error_log(date('[Y-m-d H:i:s]') . " Failed to retrieve UserID after registration for email: " . $email);
                        $message = '<p style="color: red;">Cadastro realizado, mas houve um problema ao redirecionar. Por favor, faça login.</p>';
                        header("Location: Login-Cadastro.php?status=registered");
                        exit();
                    }
                } else {
                    // --- DEBUG LOG: Erro ao cadastrar usuário (stmt->execute() falhou) ---
                    error_log(date('[Y-m-d H:i:s]') . " Error inserting new user.");
                    $message = '<p style="color: red;">Erro ao cadastrar usuário. Tente novamente.</p>';
                }

            }
        } catch (PDOException $e) {
            // --- DEBUG LOG: Erro PDO na lógica de cadastro ---
            error_log(date('[Y-m-d H:i:s]') . " PDO Error during registration logic: " . $e->getMessage());
            $message = '<p style="color: red;">Erro no banco de dados (cadastro): ' . $e->getMessage() . '</p>';
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
            // --- DEBUG LOG: Tentando logar usuário ---
            error_log(date('[Y-m-d H:i:s]') . " Attempting to log in user: " . $emailUsername);

            $user = null; // Inicializa a variável do usuário

            // --- TENTATIVA 1: Buscar por Email (sem OR) ---
            error_log(date('[Y-m-d H:i:s]') . " Attempting login by Email...");
            $stmtEmail = $conn->prepare("SELECT UsuarioID, NomeUsuario, Email, SenhaHash, Ativo FROM Usuarios WHERE Email = :email AND Ativo = 1");
            $stmtEmail->bindParam(':email', $emailUsername);
            $stmtEmail->execute();
            $user = $stmtEmail->fetch(PDO::FETCH_OBJ);

            // Se não encontrou por email, tenta por nome de usuário
            if (!$user) {
                // --- DEBUG LOG: Usuário NÃO encontrado por Email. Tentando por Nome de Usuário... ---
                error_log(date('[Y-m-d H:i:s]') . " User NOT found by Email. Trying by Username...");
                $stmtUsername = $conn->prepare("SELECT UsuarioID, NomeUsuario, Email, SenhaHash, Ativo FROM Usuarios WHERE NomeUsuario = :username AND Ativo = 1");
                $stmtUsername->bindParam(':username', $emailUsername);
                $stmtUsername->execute();
                $user = $stmtUsername->fetch(PDO::FETCH_OBJ);

                // --- DEBUG LOG: Resultado da busca por Nome de Usuário ---
                if ($user) {
                    error_log(date('[Y-m-d H:i:s]') . " User found by Username: " . $user->NomeUsuario);
                } else {
                    error_log(date('[Y-m-d H:i:s]') . " User NOT found by Username.");
                }
            } else {
                // --- DEBUG LOG: Usuário encontrado por Email ---
                error_log(date('[Y-m-d H:i:s]') . " User found by Email: " . $user->NomeUsuario);
            }
            
            // Agora, com o $user (se encontrado por qualquer método), faça a verificação da senha
            if ($user) {
                // --- DEBUG LOG: Usuário encontrado, verificando senha ---
                error_log(date('[Y-m-d H:i:s]') . " User found (" . $user->NomeUsuario . "), verifying password.");

                if ($user->SenhaHash === $senhaHashDigitada) {
                    // Login bem-sucedido
                    $_SESSION['user_id'] = $user->UsuarioID;
                    $_SESSION['username'] = $user->NomeUsuario;
                    $_SESSION['email'] = $user->Email;
                    
                    // --- DEBUG LOG: Login bem-sucedido, redirecionando para Profile.php ---
                    error_log(date('[Y-m-d H:i:s]') . " Login successful. Redirecting to Profile.php");
                    header("Location: Profile.php");
                    exit();
                } else {
                    // --- DEBUG LOG: Senha incorreta ---
                    error_log(date('[Y-m-d H:i:s]') . " Invalid password for user: " . $user->NomeUsuario);
                    $message = '<p style="color: red;">Credenciais inválidas. Verifique seu e-mail/usuário e senha.</p>';
                }
            } else {
                // --- DEBUG LOG: Usuário não encontrado (após ambas as tentativas) ---
                error_log(date('[Y-m-d H:i:s]') . " User not found after both email and username attempts.");
                $message = '<p style="color: red;">Credenciais inválidas. Verifique seu e-mail/usuário e senha.</p>';
            }
        } catch (PDOException $e) {
            // --- DEBUG LOG: Erro PDO na lógica de login (geral) ---
            error_log(date('[Y-m-d H:i:s]') . " PDO Error during login logic: " . $e->getMessage());
            $message = '<p style="color: red;">Erro no banco de dados (login): ' . $e->getMessage() . '</p>';
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="src/output.css">
    <title>Entrar no Zuno</title>
    <style>
        ::-webkit-scrollbar {
            display: none;
        }

        html {
            -ms-overflow-style: none;
            scrollbar-width: none;
            overflow: hidden;
            scroll-behavior: smooth;
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