-- ============================================================
--  CRMV/TO — banco.sql
--  MySQL 5.7+ / MariaDB 10.3+
--  Execute: mysql -u root -p < banco.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS crmv_cursos
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE crmv_cursos;

-- ── PERFIS ──────────────────────────────────────────────────
CREATE TABLE tbl_perfis (
    perfil_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    perfil_nome     VARCHAR(50)  NOT NULL,
    perfil_descricao VARCHAR(200) NULL,
    ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tbl_perfis (perfil_nome, perfil_descricao) VALUES
('Administrador', 'Acesso total ao sistema'),
('Veterinário',   'Acesso à área do participante');

-- ── USUÁRIOS ────────────────────────────────────────────────
CREATE TABLE tbl_usuarios (
    usuario_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    perfil_id       INT UNSIGNED NOT NULL DEFAULT 2,
    nome_completo   VARCHAR(150) NOT NULL,
    cpf             VARCHAR(14)  NULL,
    rg              VARCHAR(20)  NULL,
    data_nascimento DATE         NULL,
    sexo            ENUM('M','F','O') NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    telefone        VARCHAR(20)  NULL,
    celular         VARCHAR(20)  NULL,
    crmv_numero     VARCHAR(20)  NULL,
    crmv_uf         CHAR(2)      NOT NULL DEFAULT 'TO',
    especialidade   VARCHAR(100) NULL,
    instituicao     VARCHAR(150) NULL,
    cep             VARCHAR(9)   NULL,
    logradouro      VARCHAR(150) NULL,
    numero          VARCHAR(10)  NULL,
    complemento     VARCHAR(80)  NULL,
    bairro          VARCHAR(80)  NULL,
    cidade          VARCHAR(80)  NULL,
    uf              CHAR(2)      NULL,
    senha_hash      VARCHAR(255) NOT NULL,
    senha_salt      VARCHAR(64)  NOT NULL,
    token_reset     VARCHAR(100) NULL,
    token_expira    DATETIME     NULL,
    ultimo_acesso   DATETIME     NULL,
    tentativas_login TINYINT     NOT NULL DEFAULT 0,
    bloqueado_ate   DATETIME     NULL,
    ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    foto_perfil     VARCHAR(200) NULL,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    criado_por      INT UNSIGNED NULL,
    FOREIGN KEY (perfil_id) REFERENCES tbl_perfis(perfil_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── CATEGORIAS ──────────────────────────────────────────────
CREATE TABLE tbl_categorias (
    categoria_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(100) NOT NULL,
    descricao       VARCHAR(500) NULL,
    cor_hex         VARCHAR(7)   NOT NULL DEFAULT '#1a6b3c',
    icone_fa        VARCHAR(60)  NULL,
    ordem           SMALLINT     NOT NULL DEFAULT 0,
    ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tbl_categorias (nome, descricao, cor_hex, icone_fa, ordem) VALUES
('Clínica Veterinária',    'Cursos de clínica geral',            '#1a6b3c', 'fa-stethoscope',    1),
('Cirurgia',               'Cursos e workshops de cirurgia',     '#15385c', 'fa-scalpel',        2),
('Diagnóstico por Imagem', 'Ultrassonografia, radiologia',       '#c9a227', 'fa-x-ray',          3),
('Medicina de Animais Silvestres','Fauna silvestre e exóticos',  '#2d6a4f', 'fa-paw',            4),
('Saúde Pública',          'Vigilância sanitária e zoonoses',   '#6d3b47', 'fa-shield-virus',   5),
('Administração e Ética',  'Gestão e deontologia veterinária',  '#374151', 'fa-balance-scale',  6),
('Bem-estar Animal',       'Etologia e bem-estar',              '#7c3aed', 'fa-heart',          7),
('Palestras Científicas',  'Palestras e conferências',          '#0d2137', 'fa-microphone',     8);

-- ── INSTRUTORES ─────────────────────────────────────────────
CREATE TABLE tbl_instrutores (
    instrutor_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(150) NOT NULL,
    titulo          VARCHAR(100) NULL,
    email           VARCHAR(150) NULL,
    telefone        VARCHAR(20)  NULL,
    curriculo       TEXT         NULL,
    foto            VARCHAR(200) NULL,
    ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── CURSOS ──────────────────────────────────────────────────
CREATE TABLE tbl_cursos (
    curso_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id    INT UNSIGNED NULL,
    titulo          VARCHAR(200) NOT NULL,
    descricao       TEXT         NULL,
    tipo            ENUM('CURSO','PALESTRA','WORKSHOP','CONGRESSO','WEBINAR') NOT NULL DEFAULT 'CURSO',
    modalidade      ENUM('PRESENCIAL','EAD','HIBRIDO') NOT NULL DEFAULT 'PRESENCIAL',
    carga_horaria   DECIMAL(5,1) NOT NULL DEFAULT 0,
    vagas           SMALLINT     NULL,
    data_inicio     DATE         NULL,
    data_fim        DATE         NULL,
    horario         VARCHAR(100) NULL,
    local_nome      VARCHAR(150) NULL,
    local_cidade    VARCHAR(80)  NULL,
    local_uf        CHAR(2)      NULL DEFAULT 'TO',
    local_endereco  VARCHAR(200) NULL,
    link_ead        VARCHAR(300) NULL,
    youtube_id      VARCHAR(30)  NULL,
    valor           DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    status          ENUM('RASCUNHO','PUBLICADO','ENCERRADO','CANCELADO') NOT NULL DEFAULT 'RASCUNHO',
    cert_modelo     TEXT         NULL,
    cert_validade   SMALLINT     NULL,
    ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    criado_por      INT UNSIGNED NULL,
    FOREIGN KEY (categoria_id) REFERENCES tbl_categorias(categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── MATRÍCULAS ──────────────────────────────────────────────
CREATE TABLE tbl_matriculas (
    matricula_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    curso_id            INT UNSIGNED NOT NULL,
    status              ENUM('ATIVA','CONCLUIDA','CANCELADA','REPROVADO') NOT NULL DEFAULT 'ATIVA',
    nota_final          DECIMAL(5,2) NULL,
    presenca_percent    DECIMAL(5,2) NULL,
    certificado_gerado  TINYINT(1)   NOT NULL DEFAULT 0,
    certificado_codigo  VARCHAR(20)  NULL UNIQUE,
    certificado_emitido_em DATETIME  NULL,
    progresso_ead       TINYINT      NOT NULL DEFAULT 0,
    matriculado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mat (usuario_id, curso_id),
    FOREIGN KEY (usuario_id) REFERENCES tbl_usuarios(usuario_id),
    FOREIGN KEY (curso_id)   REFERENCES tbl_cursos(curso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── AVALIAÇÕES ──────────────────────────────────────────────
CREATE TABLE tbl_avaliacoes (
    avaliacao_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id        INT UNSIGNED NOT NULL,
    titulo          VARCHAR(200) NOT NULL,
    descricao       TEXT         NULL,
    tipo            ENUM('PROVA','QUESTIONARIO','PESQUISA') NOT NULL DEFAULT 'PROVA',
    nota_minima     DECIMAL(5,2) NOT NULL DEFAULT 6.00,
    tempo_limite    SMALLINT     NULL COMMENT 'em minutos',
    tentativas_max  TINYINT      NOT NULL DEFAULT 1,
    randomizar      TINYINT(1)   NOT NULL DEFAULT 0,
    ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (curso_id) REFERENCES tbl_cursos(curso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tbl_questoes (
    questao_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    avaliacao_id    INT UNSIGNED NOT NULL,
    enunciado       TEXT         NOT NULL,
    tipo            ENUM('MULTIPLA','VF','DISSERTATIVA') NOT NULL DEFAULT 'MULTIPLA',
    pontos          DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    ordem           SMALLINT     NOT NULL DEFAULT 0,
    ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    FOREIGN KEY (avaliacao_id) REFERENCES tbl_avaliacoes(avaliacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tbl_alternativas (
    alternativa_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    questao_id      INT UNSIGNED NOT NULL,
    texto           TEXT         NOT NULL,
    correta         TINYINT(1)   NOT NULL DEFAULT 0,
    ordem           SMALLINT     NOT NULL DEFAULT 0,
    FOREIGN KEY (questao_id) REFERENCES tbl_questoes(questao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── CERTIFICADOS ────────────────────────────────────────────
CREATE TABLE tbl_certificados (
    cert_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricula_id    INT UNSIGNED NOT NULL UNIQUE,
    codigo          VARCHAR(20)  NOT NULL UNIQUE,
    emitido_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    qr_path         VARCHAR(200) NULL,
    pdf_path        VARCHAR(200) NULL,
    valido          TINYINT(1)   NOT NULL DEFAULT 1,
    FOREIGN KEY (matricula_id) REFERENCES tbl_matriculas(matricula_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── CONFIGURAÇÕES ───────────────────────────────────────────
CREATE TABLE tbl_configuracoes (
    config_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave           VARCHAR(80)  NOT NULL UNIQUE,
    valor           TEXT         NULL,
    descricao       VARCHAR(200) NULL,
    tipo            ENUM('texto','numero','booleano','json') NOT NULL DEFAULT 'texto',
    atualizado_em   DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    atualizado_por  INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tbl_configuracoes (chave, valor, descricao, tipo) VALUES
('site_nome',         'CRMV/TO — Educação Continuada', 'Nome do sistema',           'texto'),
('site_email',        'educacao@crmvto.gov.br',         'E-mail oficial',            'texto'),
('cert_validade_anos','5',                              'Validade dos certificados', 'numero'),
('sla_alerta_dias',   '30',                             'Dias para alerta de prazo', 'numero'),
('upload_max_mb',     '10',                             'Tamanho máximo de upload',  'numero'),
('cert_rodape',       'Conselho Regional de Medicina Veterinária do Estado do Tocantins', 'Texto do rodapé do certificado', 'texto');

-- ── LOG ─────────────────────────────────────────────────────
CREATE TABLE tbl_log_atividades (
    log_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NULL,
    acao            VARCHAR(50)  NOT NULL,
    descricao       VARCHAR(300) NULL,
    tabela_ref      VARCHAR(60)  NULL,
    registro_id     INT UNSIGNED NULL,
    ip_address      VARCHAR(45)  NULL,
    user_agent      VARCHAR(200) NULL,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_usuario (usuario_id),
    INDEX idx_log_criado  (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── VIEW DASHBOARD ──────────────────────────────────────────
CREATE OR REPLACE VIEW vw_dashboard_totais AS
SELECT
    (SELECT COUNT(*) FROM tbl_usuarios  WHERE ativo = 1 AND perfil_id = 2) AS total_veterinarios,
    (SELECT COUNT(*) FROM tbl_cursos    WHERE ativo = 1)                   AS total_cursos,
    (SELECT COUNT(*) FROM tbl_cursos    WHERE status = 'PUBLICADO' AND ativo = 1) AS cursos_publicados,
    (SELECT COUNT(*) FROM tbl_matriculas)                                  AS total_matriculas,
    (SELECT COUNT(*) FROM tbl_matriculas WHERE certificado_gerado = 1)     AS total_certificados,
    (SELECT COUNT(*) FROM tbl_usuarios  WHERE ativo = 1 AND perfil_id = 2
        AND MONTH(criado_em) = MONTH(NOW()) AND YEAR(criado_em) = YEAR(NOW())) AS novos_este_mes,
    (SELECT COUNT(*) FROM tbl_cursos    WHERE ativo = 1
        AND MONTH(criado_em) = MONTH(NOW()) AND YEAR(criado_em) = YEAR(NOW())) AS cursos_este_mes;

-- ── ADMIN PADRÃO (hash gerado pelo gerar_admin.php) ─────────
-- Rode gerar_admin.php no navegador para gerar o INSERT correto
-- e cole aqui, ou use diretamente o INSERT abaixo após rodar:
--
-- INSERT INTO tbl_usuarios (perfil_id, nome_completo, cpf, email, senha_hash, senha_salt, crmv_uf, ativo, tentativas_login)
-- VALUES (1, 'Administrador CRMV/TO', '000.000.000-00', 'admin@crmvto.gov.br', '<HASH>', '<SALT>', 'TO', 1, 0);
