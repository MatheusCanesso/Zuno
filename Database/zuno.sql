-- ==============================
-- BANCO DE DADOS: ZUNO
-- ==============================

CREATE DATABASE Zuno;
GO

USE Zuno;
GO

SELECT * FROM Usuarios;

-- ========== USUÁRIOS ==========

CREATE TABLE Usuarios (
    UsuarioID INT PRIMARY KEY IDENTITY(1,1),
    NomeUsuario NVARCHAR(50) NOT NULL UNIQUE,
    NomeExibicao NVARCHAR(100) NOT NULL,
    Email NVARCHAR(100) NOT NULL UNIQUE,
    SenhaHash NVARCHAR(255) NOT NULL,
    Biografia NVARCHAR(160),
    Localizacao NVARCHAR(100),
    SiteWeb NVARCHAR(100),
    DataNascimento DATE,
    DataCriacao DATETIME NOT NULL DEFAULT GETDATE(),
    FotoPerfilURL NVARCHAR(255),
    FotoCapaURL NVARCHAR(255),
    ContaVerificada BIT NOT NULL DEFAULT 0,
    Ativo BIT NOT NULL DEFAULT 1,
    UltimaAtividade DATETIME
);
GO

-- ========== ZUNS (POSTAGENS) ==========

CREATE TABLE Zuns (
    ZunID BIGINT PRIMARY KEY IDENTITY(1,1),
    UsuarioID INT NOT NULL,
    Conteudo NVARCHAR(280),
    DataCriacao DATETIME NOT NULL DEFAULT GETDATE(),
    DataAtualizacao DATETIME,
    ZunPaiID BIGINT,
    ZunOriginalID BIGINT,
    TipoZun NVARCHAR(20) CHECK (TipoZun IN ('zun', 'repost', 'resposta', 'citacao')),
    Visibilidade NVARCHAR(20) CHECK (Visibilidade IN ('publico', 'privado', 'apenas_seguidores')) DEFAULT 'publico',
    Localizacao GEOGRAPHY,
    Idioma NVARCHAR(10),
    FOREIGN KEY (UsuarioID) REFERENCES Usuarios(UsuarioID),
    FOREIGN KEY (ZunPaiID) REFERENCES Zuns(ZunID),
    FOREIGN KEY (ZunOriginalID) REFERENCES Zuns(ZunID)
);
GO

-- ========== MIDIAS ==========

CREATE TABLE Midias (
    MidiaID INT PRIMARY KEY IDENTITY(1,1),
    ZunID BIGINT NOT NULL,
    URL NVARCHAR(255) NOT NULL,
    TipoMidia NVARCHAR(20) CHECK (TipoMidia IN ('imagem', 'video', 'gif')),
    Ordem INT,
    AltText NVARCHAR(420),
    FOREIGN KEY (ZunID) REFERENCES Zuns(ZunID) ON DELETE CASCADE
);
GO

-- ========== ZUNLIKES (CURTIDAS) ==========

CREATE TABLE ZunLikes (
    ZunLikeID BIGINT PRIMARY KEY IDENTITY(1,1),
    UsuarioID INT NOT NULL,
    ZunID BIGINT NOT NULL,
    DataCurtida DATETIME NOT NULL DEFAULT GETDATE(),
    FOREIGN KEY (UsuarioID) REFERENCES Usuarios(UsuarioID),
    FOREIGN KEY (ZunID) REFERENCES Zuns(ZunID) ON DELETE CASCADE,
    UNIQUE (UsuarioID, ZunID)
);
GO

-- ========== REPOSTS (COMPARTILHAMENTOS) ==========

CREATE TABLE Reposts (
    RepostID BIGINT PRIMARY KEY IDENTITY(1,1),
    UsuarioID INT NOT NULL,
    ZunOriginalID BIGINT NOT NULL,
    DataRepost DATETIME NOT NULL DEFAULT GETDATE(),
    FOREIGN KEY (UsuarioID) REFERENCES Usuarios(UsuarioID),
    FOREIGN KEY (ZunOriginalID) REFERENCES Zuns(ZunID) ON DELETE CASCADE,
    UNIQUE (UsuarioID, ZunOriginalID)
);
GO

-- ========== CONEXÕES (SEGUIDORES) ==========

CREATE TABLE Conexoes (
    SeguidorID INT NOT NULL,
    SeguidoID INT NOT NULL,
    DataConexao DATETIME NOT NULL DEFAULT GETDATE(),
    NotificacoesAtivadas BIT NOT NULL DEFAULT 1,
    PRIMARY KEY (SeguidorID, SeguidoID),
    FOREIGN KEY (SeguidorID) REFERENCES Usuarios(UsuarioID),
    FOREIGN KEY (SeguidoID) REFERENCES Usuarios(UsuarioID),
    CHECK (SeguidorID <> SeguidoID)
);
GO

-- ========== CONVERSAS ==========

CREATE TABLE Conversas (
    ConversaID INT PRIMARY KEY IDENTITY(1,1),
    DataCriacao DATETIME NOT NULL DEFAULT GETDATE(),
    UltimaMensagem DATETIME
);
GO

-- ========== PARTICIPANTES CONVERSAS ==========

CREATE TABLE ParticipantesConversa (
    ParticipanteID INT PRIMARY KEY IDENTITY(1,1),
    ConversaID INT NOT NULL,
    UsuarioID INT NOT NULL,
    DataEntrada DATETIME NOT NULL DEFAULT GETDATE(),
    DataSaida DATETIME,
    FOREIGN KEY (ConversaID) REFERENCES Conversas(ConversaID) ON DELETE CASCADE,
    FOREIGN KEY (UsuarioID) REFERENCES Usuarios(UsuarioID),
    UNIQUE (ConversaID, UsuarioID)
);
GO

-- ========== MENSAGENS ==========

CREATE TABLE MensagensDiretas (
    MensagemID BIGINT PRIMARY KEY IDENTITY(1,1),
    ConversaID INT NOT NULL,
    RemetenteID INT NOT NULL,
    Conteudo NVARCHAR(280) NOT NULL,
    DataEnvio DATETIME NOT NULL DEFAULT GETDATE(),
    DataLeitura DATETIME,
    FOREIGN KEY (ConversaID) REFERENCES Conversas(ConversaID) ON DELETE CASCADE,
    FOREIGN KEY (RemetenteID) REFERENCES Usuarios(UsuarioID)
);
GO

-- ========== MIDIAS MENSAGENS ==========

CREATE TABLE MidiasMensagens (
    MidiaMensagemID INT PRIMARY KEY IDENTITY(1,1),
    MensagemID BIGINT NOT NULL,
    URL NVARCHAR(255) NOT NULL,
    TipoMidia NVARCHAR(20) CHECK (TipoMidia IN ('imagem', 'video', 'gif')),
    FOREIGN KEY (MensagemID) REFERENCES MensagensDiretas(MensagemID) ON DELETE CASCADE
);
GO

-- ========== LISTAS ==========

CREATE TABLE Listas (
    ListaID INT PRIMARY KEY IDENTITY(1,1),
    DonoID INT NOT NULL,
    Nome NVARCHAR(100) NOT NULL,
    Descricao NVARCHAR(500),
    Privada BIT NOT NULL DEFAULT 0,
    DataCriacao DATETIME NOT NULL DEFAULT GETDATE(),
    FOREIGN KEY (DonoID) REFERENCES Usuarios(UsuarioID)
);
GO

-- ========== MEMBROS LISTA ==========

CREATE TABLE MembrosLista (
    MembroListaID INT PRIMARY KEY IDENTITY(1,1),
    ListaID INT NOT NULL,
    UsuarioID INT NOT NULL,
    DataAdicao DATETIME NOT NULL DEFAULT GETDATE(),
    FOREIGN KEY (ListaID) REFERENCES Listas(ListaID) ON DELETE CASCADE,
    FOREIGN KEY (UsuarioID) REFERENCES Usuarios(UsuarioID),
    UNIQUE (ListaID, UsuarioID)
);
GO

-- ========== INSCRIÇÕES DA LISTA ==========

CREATE TABLE InscricoesLista (
    InscricaoID INT PRIMARY KEY IDENTITY(1,1),
    ListaID INT NOT NULL,
    UsuarioID INT NOT NULL,
    DataInscricao DATETIME NOT NULL DEFAULT GETDATE(),
    FOREIGN KEY (ListaID) REFERENCES Listas(ListaID) ON DELETE CASCADE,
    FOREIGN KEY (UsuarioID) REFERENCES Usuarios(UsuarioID),
    UNIQUE (ListaID, UsuarioID)
);
GO

-- ========== FAVORITOS ==========

CREATE TABLE Favoritos (
    FavoritoID BIGINT PRIMARY KEY IDENTITY(1,1),
    UsuarioID INT NOT NULL,
    ZunID BIGINT NOT NULL,
    DataCriacao DATETIME NOT NULL DEFAULT GETDATE(),
    FOREIGN KEY (UsuarioID) REFERENCES Usuarios(UsuarioID),
    FOREIGN KEY (ZunID) REFERENCES Zuns(ZunID) ON DELETE CASCADE,
    UNIQUE (UsuarioID, ZunID)
);
GO

-- ========== TAGS ==========

CREATE TABLE Tags (
    TagID INT PRIMARY KEY IDENTITY(1,1),
    Tag NVARCHAR(140) NOT NULL UNIQUE,
    DataPrimeiroUso DATETIME NOT NULL DEFAULT GETDATE()
);
GO

-- ========== ZUNTAGS ==========

CREATE TABLE ZunTags (
    ZunTagID BIGINT PRIMARY KEY IDENTITY(1,1),
    ZunID BIGINT NOT NULL,
    TagID INT NOT NULL,
    FOREIGN KEY (ZunID) REFERENCES Zuns(ZunID) ON DELETE CASCADE,
    FOREIGN KEY (TagID) REFERENCES Tags(TagID),
    UNIQUE (ZunID, TagID)
);
GO

-- ========== MENÇÕES ==========

CREATE TABLE Mencoes (
    MençãoID BIGINT PRIMARY KEY IDENTITY(1,1),
    ZunID BIGINT NOT NULL,
    UsuarioMencionadoID INT NOT NULL,
    PosicaoInicio INT NOT NULL,
    PosicaoFim INT NOT NULL,
    FOREIGN KEY (ZunID) REFERENCES Zuns(ZunID) ON DELETE CASCADE,
    FOREIGN KEY (UsuarioMencionadoID) REFERENCES Usuarios(UsuarioID)
);
GO

-- ========== NOTIFICAÇÕES ==========

CREATE TABLE Notificacoes (
    NotificacaoID BIGINT PRIMARY KEY IDENTITY(1,1),
    UsuarioAlvoID INT NOT NULL,
    UsuarioOrigemID INT NOT NULL,
    TipoNotificacao NVARCHAR(50) NOT NULL CHECK (TipoNotificacao IN (
        'zunlike', 'repost', 'resposta', 'citacao', 'menção', 
        'nova_conexao', 'mensagem', 'convite_lista', 'adicao_lista'
    )),
    ZunID BIGINT,
    ConversaID INT,
    ListaID INT,
    DataNotificacao DATETIME NOT NULL DEFAULT GETDATE(),
    Lida BIT NOT NULL DEFAULT 0,
    FOREIGN KEY (UsuarioAlvoID) REFERENCES Usuarios(UsuarioID),
    FOREIGN KEY (UsuarioOrigemID) REFERENCES Usuarios(UsuarioID),
    FOREIGN KEY (ZunID) REFERENCES Zuns(ZunID) ON DELETE SET NULL,
    FOREIGN KEY (ConversaID) REFERENCES Conversas(ConversaID) ON DELETE SET NULL,
    FOREIGN KEY (ListaID) REFERENCES Listas(ListaID) ON DELETE SET NULL
);
GO

-- ========== BLOQUEIOS ==========

CREATE TABLE Bloqueios (
    BloqueioID INT PRIMARY KEY IDENTITY(1,1),
    BloqueadorID INT NOT NULL,
    BloqueadoID INT NOT NULL,
    DataBloqueio DATETIME NOT NULL DEFAULT GETDATE(),
    FOREIGN KEY (BloqueadorID) REFERENCES Usuarios(UsuarioID),
    FOREIGN KEY (BloqueadoID) REFERENCES Usuarios(UsuarioID),
    UNIQUE (BloqueadorID, BloqueadoID),
    CHECK (BloqueadorID <> BloqueadoID)
);
GO

-- ========== SILENCIAMENTOS ==========

CREATE TABLE Silenciamentos (
    SilenciamentoID INT PRIMARY KEY IDENTITY(1,1),
    SilenciadorID INT NOT NULL,
    SilenciadoID INT NOT NULL,
    DataSilenciamento DATETIME NOT NULL DEFAULT GETDATE(),
    FOREIGN KEY (SilenciadorID) REFERENCES Usuarios(UsuarioID),
    FOREIGN KEY (SilenciadoID) REFERENCES Usuarios(UsuarioID),
    UNIQUE (SilenciadorID, SilenciadoID),
    CHECK (SilenciadorID <> SilenciadoID)
);
GO

-- ========== TENDÊNCIAS ==========

CREATE TABLE Tendencias (
    TendenciaID INT PRIMARY KEY IDENTITY(1,1),
    Nome NVARCHAR(140) NOT NULL,
    Localizacao NVARCHAR(100),
    Volume INT,
    DataRegistro DATETIME NOT NULL DEFAULT GETDATE()
);
GO

-- ========== ENQUETES ==========

CREATE TABLE Enquetes (
    EnqueteID INT PRIMARY KEY IDENTITY(1,1),
    ZunID BIGINT NOT NULL,
    DataEncerramento DATETIME,
    DuracaoMinutos INT,
    FOREIGN KEY (ZunID) REFERENCES Zuns(ZunID) ON DELETE CASCADE
);
GO

-- ========== OPÇÕES ENQUETE ==========

CREATE TABLE OpcoesEnquete (
    OpcaoID INT PRIMARY KEY IDENTITY(1,1),
    EnqueteID INT NOT NULL,
    TextoOpcao NVARCHAR(100) NOT NULL,
    Ordem INT NOT NULL,
    FOREIGN KEY (EnqueteID) REFERENCES Enquetes(EnqueteID) ON DELETE CASCADE
);
GO

-- ========== VOTOS ENQUETE ==========

CREATE TABLE VotosEnquete (
    VotoID INT PRIMARY KEY IDENTITY(1,1),
    OpcaoID INT NOT NULL,
    UsuarioID INT NOT NULL,
    DataVoto DATETIME NOT NULL DEFAULT GETDATE(),
    FOREIGN KEY (OpcaoID) REFERENCES OpcoesEnquete(OpcaoID) ON DELETE CASCADE,
    FOREIGN KEY (UsuarioID) REFERENCES Usuarios(UsuarioID),
    UNIQUE (OpcaoID, UsuarioID)
);
GO

-- Índices para Usuarios
CREATE INDEX IX_Usuarios_NomeUsuario ON Usuarios(NomeUsuario);
CREATE INDEX IX_Usuarios_Email ON Usuarios(Email);
GO

-- Índices para Zuns
CREATE INDEX IX_Zuns_UsuarioID ON Zuns(UsuarioID);
CREATE INDEX IX_Zuns_ZunPaiID ON Zuns(ZunPaiID);
CREATE INDEX IX_Zuns_ZunOriginalID ON Zuns(ZunOriginalID);
CREATE INDEX IX_Zuns_DataCriacao ON Zuns(DataCriacao);
GO

-- Índices para ZunLikes
CREATE INDEX IX_ZunLikes_ZunID ON ZunLikes(ZunID);
CREATE INDEX IX_ZunLikes_UsuarioID ON ZunLikes(UsuarioID);
GO

-- Índices para Reposts
CREATE INDEX IX_Reposts_ZunOriginalID ON Reposts(ZunOriginalID);
CREATE INDEX IX_Reposts_UsuarioID ON Reposts(UsuarioID);
GO

-- Índices para Conexoes
CREATE INDEX IX_Conexoes_SeguidoID ON Conexoes(SeguidoID);
CREATE INDEX IX_Conexoes_SeguidorID ON Conexoes(SeguidorID);
GO

-- Índices para Mensagens Diretas
CREATE INDEX IX_MensagensDiretas_ConversaID ON MensagensDiretas(ConversaID);
CREATE INDEX IX_MensagensDiretas_RemetenteID ON MensagensDiretas(RemetenteID);
CREATE INDEX IX_MensagensDiretas_DataEnvio ON MensagensDiretas(DataEnvio);
GO

-- Índices para Notificações
CREATE INDEX IX_Notificacoes_UsuarioAlvoID ON Notificacoes(UsuarioAlvoID);
CREATE INDEX IX_Notificacoes_UsuarioOrigemID ON Notificacoes(UsuarioOrigemID);
CREATE INDEX IX_Notificacoes_TipoNotificacao ON Notificacoes(TipoNotificacao);
CREATE INDEX IX_Notificacoes_Lida ON Notificacoes(Lida);
GO

-- View para linha do tempo de um usuário (zuns + reposts)
-- Removido ORDER BY da definição da VIEW
CREATE VIEW TimelineUsuario AS
SELECT 
    u.UsuarioID,
    z.ZunID,
    z.Conteudo,
    z.DataCriacao,
    u2.UsuarioID AS AutorID,
    u2.NomeUsuario AS AutorNomeUsuario,
    u2.NomeExibicao AS AutorNomeExibicao,
    CASE WHEN r.ZunOriginalID IS NOT NULL THEN 1 ELSE 0 END AS EhRepost,
    r.UsuarioID AS RepostPorID,
    ur.NomeUsuario AS RepostPorNomeUsuario,
    ur.NomeExibicao AS RepostPorNomeExibicao
FROM 
    Usuarios u
JOIN 
    Conexoes c ON u.UsuarioID = c.SeguidoID
JOIN 
    Zuns z ON c.SeguidorID = z.UsuarioID
JOIN 
    Usuarios u2 ON z.UsuarioID = u2.UsuarioID
LEFT JOIN 
    Reposts r ON z.ZunID = r.ZunOriginalID AND r.UsuarioID = u.UsuarioID
LEFT JOIN 
    Usuarios ur ON r.UsuarioID = ur.UsuarioID
WHERE 
    z.ZunPaiID IS NULL OR z.TipoZun = 'repost';
GO

-- View para estatísticas de usuários
CREATE VIEW EstatisticasUsuario AS
SELECT 
    u.UsuarioID,
    u.NomeUsuario,
    u.NomeExibicao,
    (SELECT COUNT(*) FROM Conexoes WHERE SeguidoID = u.UsuarioID) AS Conexoes,
    (SELECT COUNT(*) FROM Conexoes WHERE SeguidorID = u.UsuarioID) AS Seguindo,
    (SELECT COUNT(*) FROM Zuns WHERE UsuarioID = u.UsuarioID AND TipoZun = 'zun') AS Zuns,
    (SELECT COUNT(*) FROM ZunLikes WHERE UsuarioID = u.UsuarioID) AS ZunLikes,
    (SELECT COUNT(*) FROM Reposts WHERE UsuarioID = u.UsuarioID) AS Reposts
FROM 
    Usuarios u;
GO

-- View para zuns populares
-- Removido ORDER BY da definição da VIEW
CREATE VIEW ZunsPopulares AS
SELECT 
    z.ZunID,
    z.Conteudo,
    z.DataCriacao,
    u.UsuarioID,
    u.NomeUsuario,
    u.NomeExibicao,
    (SELECT COUNT(*) FROM ZunLikes WHERE ZunID = z.ZunID) AS ZunLikes,
    (SELECT COUNT(*) FROM Reposts WHERE ZunOriginalID = z.ZunID) AS Reposts,
    (SELECT COUNT(*) FROM Zuns WHERE ZunPaiID = z.ZunID) AS Respostas
FROM 
    Zuns z
JOIN 
    Usuarios u ON z.UsuarioID = u.UsuarioID
WHERE 
    z.DataCriacao >= DATEADD(DAY, -7, GETDATE());
GO

-- Procedure para postar um novo zun
CREATE PROCEDURE PostarZun
    @UsuarioID INT,
    @Conteudo NVARCHAR(280),
    @ZunPaiID BIGINT = NULL,
    @ZunOriginalID BIGINT = NULL,
    @TipoZun NVARCHAR(20) = 'zun',
    @Visibilidade NVARCHAR(20) = 'publico',
    @Localizacao GEOGRAPHY = NULL,
    @Idioma NVARCHAR(10) = NULL,
    @ZunID BIGINT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    
    INSERT INTO Zuns (
        UsuarioID, 
        Conteudo, 
        ZunPaiID, 
        ZunOriginalID, 
        TipoZun, 
        Visibilidade, 
        Localizacao, 
        Idioma
    )
    VALUES (
        @UsuarioID, 
        @Conteudo, 
        @ZunPaiID, 
        @ZunOriginalID, 
        @TipoZun, 
        @Visibilidade, 
        @Localizacao, 
        @Idioma
    );
    
    SET @ZunID = SCOPE_IDENTITY();
    
    -- Se for uma resposta, notificar o autor do zun pai
    IF @ZunPaiID IS NOT NULL AND @TipoZun = 'resposta'
    BEGIN
        DECLARE @AutorPaiID INT;
        SELECT @AutorPaiID = UsuarioID FROM Zuns WHERE ZunID = @ZunPaiID;
        
        IF @AutorPaiID <> @UsuarioID
        BEGIN
            INSERT INTO Notificacoes (
                UsuarioAlvoID,
                UsuarioOrigemID,
                TipoNotificacao,
                ZunID,
                DataNotificacao
            )
            VALUES (
                @AutorPaiID,
                @UsuarioID,
                'resposta',
                @ZunID,
                GETDATE()
            );
        END
    END
    
    -- Se for uma menção, notificar os usuários mencionados
    -- (Implementação de extração de menções seria feita na aplicação)
END;
GO

-- Procedure para conectar-se a um usuário
CREATE PROCEDURE ConectarUsuario
    @SeguidorID INT,
    @SeguidoID INT
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Verificar se o usuário não está bloqueado
    IF EXISTS (SELECT 1 FROM Bloqueios WHERE (BloqueadorID = @SeguidoID AND BloqueadoID = @SeguidorID))
    BEGIN
        RAISERROR('Você foi bloqueado por este usuário e não pode conectá-lo.', 16, 1);
        RETURN;
    END
    
    -- Verificar se já não está conectado
    IF EXISTS (SELECT 1 FROM Conexoes WHERE SeguidorID = @SeguidorID AND SeguidoID = @SeguidoID)
    BEGIN
        RAISERROR('Você já está conectado a este usuário.', 16, 1);
        RETURN;
    END
    
    -- Verificar se não está tentando conectar-se a si mesmo
    IF @SeguidorID = @SeguidoID
    BEGIN
        RAISERROR('Você não pode conectar-se a si mesmo.', 16, 1);
        RETURN;
    END
    
    -- Inserir a conexão
    INSERT INTO Conexoes (SeguidorID, SeguidoID)
    VALUES (@SeguidorID, @SeguidoID);
    
    -- Notificar o usuário seguido
    INSERT INTO Notificacoes (
        UsuarioAlvoID,
        UsuarioOrigemID,
        TipoNotificacao,
        DataNotificacao
    )
    VALUES (
        @SeguidoID,
        @SeguidorID,
        'nova_conexao',
        GETDATE()
    );
END;
GO

-- Procedure para obter a timeline de um usuário
CREATE PROCEDURE ObterTimeline
    @UsuarioID INT,
    @Pagina INT = 1,
    @ZunsPorPagina INT = 20
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @Offset INT = (@Pagina - 1) * @ZunsPorPagina;
    
    -- Zuns dos usuários que o usuário atual segue
    SELECT 
        z.ZunID,
        z.Conteudo,
        z.DataCriacao,
        u.UsuarioID AS AutorID,
        u.NomeUsuario AS AutorNomeUsuario,
        u.NomeExibicao AS AutorNomeExibicao,
        u.FotoPerfilURL AS AutorFotoPerfil,
        0 AS EhRepost,
        NULL AS RepostPorID,
        NULL AS RepostPorNomeUsuario,
        NULL AS RepostPorNomeExibicao,
        (SELECT COUNT(*) FROM ZunLikes WHERE ZunID = z.ZunID) AS ZunLikes,
        (SELECT COUNT(*) FROM Reposts WHERE ZunOriginalID = z.ZunID) AS Reposts,
        (SELECT COUNT(*) FROM Zuns WHERE ZunPaiID = z.ZunID) AS Respostas,
        CASE WHEN EXISTS (SELECT 1 FROM ZunLikes WHERE UsuarioID = @UsuarioID AND ZunID = z.ZunID) THEN 1 ELSE 0 END AS ZunLikadoPorMim,
        CASE WHEN EXISTS (SELECT 1 FROM Reposts WHERE UsuarioID = @UsuarioID AND ZunOriginalID = z.ZunID) THEN 1 ELSE 0 END AS RepostadoPorMim,
        CASE WHEN EXISTS (SELECT 1 FROM Favoritos WHERE UsuarioID = @UsuarioID AND ZunID = z.ZunID) THEN 1 ELSE 0 END AS FavoritadoPorMim
    FROM 
        Zuns z
    JOIN 
        Conexoes c ON z.UsuarioID = c.SeguidorID
    JOIN 
        Usuarios u ON z.UsuarioID = u.UsuarioID
    WHERE 
        c.SeguidoID = @UsuarioID
        AND (z.ZunPaiID IS NULL OR z.TipoZun = 'repost')
    
    UNION ALL
    
    -- Reposts feitos pelos usuários que o usuário atual segue
    SELECT 
        z.ZunID,
        z.Conteudo,
        r.DataRepost AS DataCriacao,
        u.UsuarioID AS AutorID,
        u.NomeUsuario AS AutorNomeUsuario,
        u.NomeExibicao AS AutorNomeExibicao,
        u.FotoPerfilURL AS AutorFotoPerfil,
        1 AS EhRepost,
        ur.UsuarioID AS RepostPorID,
        ur.NomeUsuario AS RepostPorNomeUsuario,
        ur.NomeExibicao AS RepostPorNomeExibicao,
        (SELECT COUNT(*) FROM ZunLikes WHERE ZunID = z.ZunID) AS ZunLikes,
        (SELECT COUNT(*) FROM Reposts WHERE ZunOriginalID = z.ZunID) AS Reposts,
        (SELECT COUNT(*) FROM Zuns WHERE ZunPaiID = z.ZunID) AS Respostas,
        CASE WHEN EXISTS (SELECT 1 FROM ZunLikes WHERE UsuarioID = @UsuarioID AND ZunID = z.ZunID) THEN 1 ELSE 0 END AS ZunLikadoPorMim,
        CASE WHEN EXISTS (SELECT 1 FROM Reposts WHERE UsuarioID = @UsuarioID AND ZunOriginalID = z.ZunID) THEN 1 ELSE 0 END AS RepostadoPorMim,
        CASE WHEN EXISTS (SELECT 1 FROM Favoritos WHERE UsuarioID = @UsuarioID AND ZunID = z.ZunID) THEN 1 ELSE 0 END AS FavoritadoPorMim
    FROM 
        Reposts r
    JOIN 
        Zuns z ON r.ZunOriginalID = z.ZunID
    JOIN 
        Usuarios u ON z.UsuarioID = u.UsuarioID
    JOIN 
        Usuarios ur ON r.UsuarioID = ur.UsuarioID
    JOIN 
        Conexoes c ON r.UsuarioID = c.SeguidorID
    WHERE 
        c.SeguidoID = @UsuarioID
    
    ORDER BY 
        DataCriacao DESC
    OFFSET @Offset ROWS
    FETCH NEXT @ZunsPorPagina ROWS ONLY;
END;
GO
