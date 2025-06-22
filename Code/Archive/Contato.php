<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contato - Zuno</title>
    <script src="https://kit.fontawesome.com/17dd42404d.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap');

        :root {
            --primary: #21FA90;
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

        #active {
            color: var(--second);
            font-weight: bold;
        }

        .cta-button {
            background-color: var(--primary);
            color: white;
            /* Texto já está branco por padrão */
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

        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 0 20px;
        }

        h1 {
            color: var(--primary);
            font-size: 2.5rem;
            margin-bottom: 30px;
            text-align: center;
        }

        h2 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-top: 40px;
            margin-bottom: 20px;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .contact-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .contact-card:hover {
            transform: translateY(-5px);
        }

        .contact-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .contact-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-top: 40px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 0 15px 0;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Urbanist', sans-serif;
        }   

        .form-group textarea {
            min-height: 150px;
            resize: none;
        }

        .submit-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 14px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            display: block;
            margin: 30px auto 0;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(33, 250, 144, 0.4);
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }

        .social-link {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s;
            text-decoration: none;
        }

        .social-link:hover {
            transform: translateY(-3px);
            background-color: var(--second);
        }

        footer {
            background-color: var(--bg-dark);
            color: var(--text-light);
            padding: 30px 5%;
            text-align: center;
            margin-top: 50px;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
        }

        .footer-links a {
            color: var(--text-light);
            text-decoration: none;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .back-button {
            display: inline-block;
            margin-top: 30px;
            padding: 10px 20px;
            background-color: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .back-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(33, 250, 144, 0.4);
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-logo">
            <a href="index.php"><img src="../../Design/Assets/logotipo_H.png" alt="Logo Zuno" class="logo-icon"></a>
        </div>
        <div class="nav-links">
            <a href="../Index.php">Início</a>
            <a href="../Sobre.php">Sobre</a>
            <a href="../Login-Cadastro.php" class="cta-button">Cadastre-se</a>
        </div>
    </nav>

    <div class="container">
        <h1>📩 Fale Conosco</h1>
        <p style="text-align: center;">Tem dúvidas, sugestões ou precisa de ajuda? Entre em contato com a equipe Zuno!
        </p>

        <div class="contact-grid">
            <div class="contact-card">
                <div class="contact-icon">✉️</div>
                <h2>E-mail</h2>
                <p>Para suporte técnico ou dúvidas:</p>
                <p><strong>suporte@zuno.app</strong></p>
                <p>Para parcerias comerciais:</p>
                <p><strong>parcerias@zuno.app</strong></p>
            </div>

            <div class="contact-card">
                <div class="contact-icon">🌐</div>
                <h2>Redes Sociais</h2>
                <p>Siga-nos e envie mensagens:</p>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>

            <div class="contact-card">
                <div class="contact-icon">📱</div>
                <h2>Ajuda no App</h2>
                <p>No aplicativo Zuno:</p>
                <p>1. Acesse seu perfil</p>
                <p>2. Toque em "Configurações"</p>
                <p>3. Selecione "Ajuda & Suporte"</p>
            </div>
        </div>

        <div class="contact-form">
            <h2>📝 Formulário de Contato</h2>
            <form action="processa_contato.php" method="POST">
                <div class="form-group">
                    <label for="nome">Seu Nome</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="email">Seu E-mail</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="assunto">Assunto</label>
                    <input type="text" id="assunto" name="assunto" required>
                </div>
                <div class="form-group">
                    <label for="mensagem">Sua Mensagem</label>
                    <textarea id="mensagem" name="mensagem" required></textarea>
                </div>
                <button type="submit" class="submit-btn">Enviar Mensagem</button>
            </form>
        </div>

        <div style="text-align: center; margin-top: 50px;">
            <h2>⏰ Horário de Atendimento</h2>
            <p>Segunda a sexta: 9h às 18h</p>
            <p>Sábados: 10h às 14h</p>
            <p>Domingos e feriados: não há atendimento</p>
        </div>

        <a href="../Index.php" class="back-button">Voltar para o início</a>
    </div>

    <footer>
        <div class="footer-links">
            <a href="TermosDeServico.php">Termos de Serviço</a>
            <a href="PoliticaDePrivacidade.php">Política de Privacidade</a>
            <a href="Contato.php" id="active">Contato</a>
        </div>
        <p>2025 © Zuno Corporation. Todos os direitos reservados.</p>
        <p>#ZunoApp #PulsoDigital #ZuneAgora</p>
    </footer>
</body>

</html>