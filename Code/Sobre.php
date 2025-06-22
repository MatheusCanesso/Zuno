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
            color: var(--text-dark);
        }

        p {
            font-size: 18px;
            margin-bottom: 30px;
            color: var(--text-dark);
            line-height: 1.2;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-logo">
            <a href="Index.php"><img src="../Design/Assets/logotipo_H.png" alt="Logo Zuno" class="logo-icon"></a>
        </div>
        <div class="nav-links">
            <a href="index.php">Início</a>
            <a id="active" href="sobre.php">Sobre</a>
            <a href="Login-Cadastro.php" class="cta-button">Cadastre-se</a>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <h1>Zuno: do pensamento ao pulso.</h1>
            <p>Uma rede social feita para dar ritmo aos seus pensamentos.</p>
            <p>Onde você Zuna o que pensa, e reZuna o que
                importa.</p>
            <button class="cta-button">Comece a Zunar</button>
        </div>
        <!-- <div class="hero-image">
            <img src="../Design/Assets/phone.png" alt="Interface do Zuno">
        </div> -->
    </section>

    <section class="mission">
        <h2>Nossa Missão</h2>
        <div class="mission-statement">
            <p>Enquanto outras plataformas te afogam em ruído, no Zuno você compartilha com fluidez, clareza e conteúdo.
                Sem filtros forçados. Sem algoritmos que te sufocam.</p>
            <p>No Zuno, a voz vem primeiro. O ritmo é seu.</p>
        </div>
        <button class="cta-button">Junte-se ao movimento</button>
    </section>

    <section class="team">
        <h2>Conheça o time</h2>
        <div class="team-grid">
            <div class="team-member">
                <div class="team-border-photo">
                    <img src="../Design/Assets/matheus_photo.jpg" alt="Membro do time" class="team-photo">
                </div>
                <h3>Canessodev</h3>
                <p>Fundador, Designer & Desenvolvedor</p>
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