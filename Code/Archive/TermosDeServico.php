<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termos de Serviço - Zuno</title>
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

        .highlight-green{
            font-weight: bold;
            color: var(--primary);
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
        <h1>📜 Termos de Serviço — ZUNO</h1>
        <p class="last-updated">Última atualização: 14 de junho de 2025</p>
        
        <p>Bem-vindo ao Zuno! Ao acessar ou usar nosso aplicativo ou site, você concorda com estes Termos de Serviço. Por favor, leia com atenção.</p>

        <h2>1. Sobre a Zuno</h2>
        <p>Zuno é uma rede social voltada para texto e comunidades, permitindo aos usuários expressarem suas ideias através de "Zunes", interações e conexões com outras pessoas ao redor do mundo.</p>

        <h2>2. Aceitação dos Termos</h2>
        <p>Ao criar uma conta ou utilizar qualquer funcionalidade da Zuno, você declara que:</p>
        <ul>
            <li>Tem pelo menos 13 anos de idade;</li>
            <li>Concorda com todos os termos aqui descritos;</li>
            <li>Leu e entendeu nossa <span class="highlight">Política de Privacidade</span>.</li>
        </ul>

        <h2>3. Uso da Plataforma</h2>
        <p>Você se compromete a:</p>
        <ul>
            <li>Utilizar a Zuno de forma ética, respeitando os demais usuários;</li>
            <li>Não publicar conteúdo ilegal, ofensivo, discriminatório ou que viole os direitos de terceiros;</li>
            <li>Não usar a Zuno para spam, golpes ou qualquer atividade maliciosa.</li>
        </ul>
        <p>Lembre-se: você é responsável por tudo que publica. O que você "zuna", ecoa.</p>

        <h2>4. Conteúdo do Usuário</h2>
        <ul>
            <li>Todo conteúdo publicado por você (textos, comentários, imagens etc.) continua sendo seu.</li>
            <li>Ao postar, você nos concede uma licença limitada para exibir, distribuir e promover esse conteúdo dentro da plataforma.</li>
            <li>Podemos remover qualquer conteúdo que viole nossas diretrizes, sem aviso prévio.</li>
        </ul>

        <h2>5. Conta e Segurança</h2>
        <ul>
            <li>É proibido criar contas falsas, se passar por outra pessoa ou usar nomes ofensivos.</li>
            <li>Mantenha suas informações de login em segurança. Não compartilhe sua senha.</li>
            <li>Podemos suspender ou encerrar sua conta em caso de violação destes Termos.</li>
        </ul>

        <h2>6. Modificações na Plataforma</h2>
        <p>A Zuno está sempre evoluindo. Podemos:</p>
        <ul>
            <li>Atualizar funcionalidades;</li>
            <li>Modificar ou descontinuar recursos;</li>
            <li>Alterar os Termos de Serviço — com aviso prévio quando necessário.</li>
        </ul>

        <h2>7. Limitação de Responsabilidade</h2>
        <p>A Zuno não se responsabiliza por:</p>
        <ul>
            <li>Conteúdo publicado por usuários;</li>
            <li>Danos causados por mau uso da plataforma;</li>
            <li>Interrupções temporárias no serviço.</li>
        </ul>

        <h2>8. Encerramento de Conta</h2>
        <p>Você pode excluir sua conta a qualquer momento nas configurações. Nós também podemos encerrar ou suspender seu acesso em caso de violação dos termos.</p>

        <h2>9. Legislação Aplicável</h2>
        <p>Estes termos são regidos pelas leis do Brasil. Qualquer disputa será resolvida em foro competente.</p>

        <h2>10. Contato</h2>
        <p>Dúvidas ou sugestões? Entre em contato com a gente em:<br>
        📩 <span class="highlight">suporte@zuno.app</span></p>

        <p class="highlight-green">Zuno. Dê voz ao seu mundo.</p>

        <a href="index.php" class="back-button">Voltar para o início</a>
    </div>

    <footer>
        <div class="footer-links">
            <a href="TermosDeServico.php" id="active">Termos de Serviço</a>
            <a href="PoliticaDePrivacidade.php">Política de Privacidade</a>
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