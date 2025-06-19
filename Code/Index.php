<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zuno - Sua voz em movimento</title>
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
        }

        span {
            color: var(--primary);
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 5%;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            font-weight: 700;
            font-size: 24px;
            color: var(--primary);
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

        .hero {
            display: flex;
            justify-content: center;
            text-align: center;
            align-items: center;
            padding: 80px 5%;
            height: 70vh;
            gap: 50px;
            background:
                linear-gradient(135deg, rgba(33, 250, 144, 0.9), rgba(0, 209, 255, 0.9)),
                url('../Design/Assets/background_cadastro-login.png') no-repeat center center;
            background-size: cover;
            color: white;
        }

        .hero-content {
            flex: 1;
            max-width: 60%;
        }

        .hero-image {
            flex: 1;
            max-width: 500px;
            text-align: center;
        }

        .hero-image img {
            max-width: 100%;
            border-radius: 20px;
            filter: drop-shadow(0 10px 30px rgba(33, 250, 144, 0.2));
        }

        h1 {
            font-size: 48px;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .highlight {
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        p {
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.9;
        }

        .features {
            padding: 80px 5%;
            background-color: white;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .feature-card {
            padding: 30px;
            border-radius: 15px;
            border: 1px solid var(--border);
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }

        .feature-icon {
            font-size: 40px;
            margin-bottom: 20px;
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

        .navbar-logo {
            display: flex;
            align-items: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--preto-zunno);
        }

        .navbar-logo .logo-icon {
            height: 30px;
            cursor: pointer;
            width: auto;
            margin-right: 10px;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-logo">
            <a href="Index.php"><img src="../Design/Assets/logotipo_H.png" alt="Logo Zuno" class="logo-icon"></a>
        </div>
        <div class="nav-links">
            <a id="active" href="index.php">Início</a>
            <a href="sobre.php">Sobre</a>
            <a href="cadastro.php" class="cta-button">Cadastre-se</a>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <h1>Zune livre, <span class="highlight">conecta rápido.</span></h1>
            <p>No Zuno, sua voz vem primeiro.<br>
                Compartilhe pensamentos, conecte-se com comunidades e sinta o pulso do que está acontecendo agora.</p>
            <button class="cta-button">Comece a Zunar</button>
        </div>
        <!-- <div class="hero-image">
            <img src="../Design/Assets/phone.png" alt="Interface do Zuno">
        </div> -->
    </section>
    <section class="features">
        <h2 style="text-align: center; font-size: 36px;">Por que escolher o <span>Zuno</span>?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3><span>Zune</span> rápido</h3>
                <p>Compartilhe seus pensamentos em segundos com nossa interface otimizada para velocidade.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3><span>Comunidades</span> vibrantes</h3>
                <p>Encontre seu Radar e conecte-se com pessoas que compartilham seus interesses.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🌙</div>
                <h3><span>Modo noturno</span> nativo</h3>
                <p>Desfrute de uma experiência visual confortável a qualquer hora do dia.</p>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-links">
            <a href="Archive/TermosDeServico.php">Termos de Serviço</a>
            <a href="Archive/PoliticaDePrivacidade.php">Política de Privacidade</a>
            <a href="Archive/Contato.php">Contato</a>
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