<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zuno - Sua voz em movimento</title>
    <script src="https://kit.fontawesome.com/17dd42404d.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="src/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <style>
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

        h1 {
            font-size: 48px;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        p {
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.9;
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
            <a href="Login-Cadastro.php" class="cta-button">Cadastre-se</a>
        </div>
    </nav>

    <section class="hero-index-index">
        <div class="hero-content-index">
            <h1>Zune livre, <span class="highlight-index">conecta rápido.</span></h1>
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