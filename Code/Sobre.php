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
            align-items: center;
            justify-content: center;
            padding: 80px 5%;
            height: 70vh;
            color: white;
            text-align: center;
            gap: 50px;
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
            color: var(--text-dark);
        }

        .highlight {
            color: var(--primary);
        }

        p {
            font-size: 18px;
            margin-bottom: 30px;
            color: var(--text-dark);
            line-height: 1.2;
        }

        .mission {
            background: linear-gradient(135deg, var(--primary) 0%, #00D1FF 100%);
            color: white;
            padding: 80px 5%;
            text-align: center;
        }

        .mission h2 {
            font-size: 36px;
            margin-bottom: 40px;
        }

        .mission-statement {
            max-width: 800px;
            margin: 0 auto;
            font-size: 18px;
            line-height: 1.8;
            margin-bottom: 50px;
        }

        .team {
            padding: 80px 5%;
            background-color: white;
        }

        .team h2 {
            text-align: center;
            font-size: 36px;
            margin-bottom: 50px;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .team-member {
            padding: 30px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-radius: 15px;
            transition: all 0.3s;
            border: 1px solid var(--border);
        }

        .team-member:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }

        .team-border-photo {
            width: 170px;
            height: 170px;
            background: conic-gradient(#00D1FF, #21FA90, #00D1FF);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .team-photo {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
        }

        footer {
            background-color: var(--bg-dark);
            color: var(--text-light);
            padding: 50px 5%;
            text-align: center;
        }

        footer p{
            color: var(--text-light);
            margin: 10px 0;
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