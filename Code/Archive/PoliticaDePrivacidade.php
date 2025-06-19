<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidade - Zuno</title>
    <script src="https://kit.fontawesome.com/17dd42404d.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
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
        }

        h2 {
            color: var(--primary);
            font-size: 1.8rem;
            margin-top: 40px;
            margin-bottom: 15px;
        }

        p, li {
            font-size: 1.1rem;
            margin-bottom: 15px;
        }

        ul {
            padding-left: 20px;
        }

        li {
            margin-bottom: 10px;
            position: relative;
            padding-left: 30px;
        }

        li:before {
            content: "";
            position: absolute;
            left: 0;
            top: 10px;
            width: 8px;
            height: 8px;
            background-color: var(--primary);
            border-radius: 50%;
        }

        .last-updated {
            font-style: italic;
            color: #666;
            margin-bottom: 30px;
        }

        .highlight {
            font-weight: bold;
            color: var(--blue-primary);
        }

        .highlight-green {
            font-weight: bold;
            color: var(--primary);
        }

        .data-category {
            background-color: rgba(33, 250, 144, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .data-category-title {
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        footer {
            background-color: var(--bg-dark);
            color: var(--text-light);
            padding: 50px 5%;
            text-align: center;
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

        #active {
            color: var(--second);
            font-weight: bold;
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
        <h1>🔒 Política de Privacidade — ZUNO</h1>
        <p class="last-updated">Última atualização: 14 de junho de 2025</p>
        
        <p>Na Zuno, respeitar sua privacidade é prioridade. Esta Política explica como coletamos, usamos e protegemos seus dados ao utilizar nosso aplicativo e site.</p>

        <h2>1. Quais dados coletamos?</h2>
        <p>Coletamos apenas os dados essenciais para o funcionamento da plataforma:</p>

        <div class="data-category">
            <div class="data-category-title">📋 Dados fornecidos por você:</div>
            <ul>
                <li>Nome de usuário e e-mail</li>
                <li>Senha criptografada</li>
                <li>Biografia, foto de perfil, e preferências de comunidade</li>
            </ul>
        </div>

        <div class="data-category">
            <div class="data-category-title">📱 Dados coletados automaticamente:</div>
            <ul>
                <li>IP, localização aproximada, tipo de dispositivo e navegador</li>
                <li>Ações realizadas na plataforma (ex: curtidas, postagens, interações)</li>
                <li>Cookies para melhorar sua experiência</li>
            </ul>
        </div>

        <h2>2. Como usamos seus dados?</h2>
        <p>Usamos seus dados para:</p>
        <ul>
            <li>Criar e gerenciar sua conta;</li>
            <li>Personalizar o conteúdo exibido;</li>
            <li>Melhorar a plataforma com base no uso;</li>
            <li>Enviar comunicações importantes (ex: atualizações, notificações, suporte).</li>
        </ul>
        <p><span class="highlight">Jamais vendemos ou repassamos seus dados para terceiros sem seu consentimento.</span></p>

        <h2>3. Compartilhamento de dados</h2>
        <p>Podemos compartilhar seus dados apenas quando:</p>
        <ul>
            <li>Necessário para funcionamento técnico (ex: servidores, analytics);</li>
            <li>Requerido por lei ou ordem judicial;</li>
            <li>Autorizado expressamente por você.</li>
        </ul>
        <p>Todos os parceiros seguem padrões rígidos de proteção de dados.</p>

        <h2>4. Seus direitos</h2>
        <p>Você pode, a qualquer momento:</p>
        <ul>
            <li>Solicitar acesso aos seus dados;</li>
            <li>Corrigir informações incorretas;</li>
            <li>Solicitar a exclusão da sua conta;</li>
            <li>Retirar o consentimento para uso dos dados.</li>
        </ul>
        <p>Entre em contato pelo e-mail <span class="highlight">privacidade@zuno.app</span> para exercer esses direitos.</p>

        <h2>5. Armazenamento e segurança</h2>
        <p>Seus dados são armazenados em servidores seguros e protegidos por criptografia e práticas de segurança modernas. Apenas pessoas autorizadas têm acesso a eles.</p>

        <h2>6. Cookies</h2>
        <p>Utilizamos cookies e tecnologias similares para:</p>
        <ul>
            <li>Salvar preferências;</li>
            <li>Analisar o uso da plataforma;</li>
            <li>Oferecer uma experiência personalizada.</li>
        </ul>
        <p>Você pode desativar os cookies nas configurações do seu navegador, mas isso pode afetar o funcionamento da Zuno.</p>

        <h2>7. Menores de idade</h2>
        <p>A Zuno é destinada a pessoas com 13 anos ou mais. Se descobrirmos que um menor de idade usou a plataforma sem consentimento dos responsáveis, os dados serão removidos.</p>

        <h2>8. Alterações nesta Política</h2>
        <p>Podemos atualizar esta Política de tempos em tempos. Notificaremos os usuários em caso de mudanças significativas.</p>

        <h2>9. Fale com a gente</h2>
        <p>Tem dúvidas ou quer saber mais sobre como cuidamos da sua privacidade?</p>
        <p>📩 <span class="highlight">privacidade@zuno.app</span></p>

        <p class="highlight-green">Zuno — Dê voz ao seu mundo. Com segurança e respeito.</p>

        <a href="index.php" class="back-button">Voltar para o início</a>
    </div>

    <footer>
        <div class="footer-links">
            <a href="TermosDeServico.php">Termos de Serviço</a>
            <a href="PoliticaDePrivacidade.php" id="active">Política de Privacidade</a>
            <a href="Contato.php">Contato</a>
        </div>
        <div class="social-icons">
            <div class="social-icon">🐦</div>
            <div class="social-icon">📸</div>
            <div class="social-icon">📱</div>
        </div>
        <p>2025 © Zuno Corporation. Todos os direitos reservados.</p>
        <p>#ZunoApp #PulsoDigital #ZuneAgora</p>
    </footer>
</body>

</html>