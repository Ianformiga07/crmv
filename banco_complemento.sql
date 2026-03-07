-- ================================================================
-- CRMV/TO — banco_complemento.sql
-- Execute APÓS o banco.sql original
-- Adiciona tabelas e colunas necessárias para Cursos + Certificados
-- ================================================================

-- ── Novas colunas em tbl_cursos ──────────────────────────────
ALTER TABLE tbl_cursos
    ADD COLUMN IF NOT EXISTS capa         VARCHAR(120) NULL AFTER descricao,
    ADD COLUMN IF NOT EXISTS youtube_id   VARCHAR(20)  NULL AFTER link_ead,
    ADD COLUMN IF NOT EXISTS horario      VARCHAR(60)  NULL AFTER data_fim,
    ADD COLUMN IF NOT EXISTS local_nome   VARCHAR(120) NULL AFTER horario,
    ADD COLUMN IF NOT EXISTS local_cidade VARCHAR(80)  NULL AFTER local_nome,
    ADD COLUMN IF NOT EXISTS local_uf     CHAR(2)      NULL DEFAULT 'TO' AFTER local_cidade,
    ADD COLUMN IF NOT EXISTS local_endereco VARCHAR(200) NULL AFTER local_uf,
    ADD COLUMN IF NOT EXISTS observacoes  TEXT         NULL AFTER descricao,
    ADD COLUMN IF NOT EXISTS instituicao  VARCHAR(160) NULL AFTER especialidade,
    ADD COLUMN IF NOT EXISTS criado_por   INT          NULL AFTER ativo,
    ADD COLUMN IF NOT EXISTS atualizado_em DATETIME    NULL AFTER criado_em;

-- ── Tabela de materiais de apoio ─────────────────────────────
CREATE TABLE IF NOT EXISTS tbl_materiais (
    material_id   INT AUTO_INCREMENT PRIMARY KEY,
    curso_id      INT          NOT NULL,
    nome_arquivo  VARCHAR(160) NOT NULL,
    nome_original VARCHAR(220) NOT NULL,
    tamanho       INT          NOT NULL DEFAULT 0,
    tipo_mime     VARCHAR(80)  NULL,
    criado_em     DATETIME     NOT NULL DEFAULT NOW(),
    criado_por    INT          NULL,
    CONSTRAINT fk_mat_curso FOREIGN KEY (curso_id) REFERENCES tbl_cursos(curso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabela de instrutores (se não existir) ────────────────────
CREATE TABLE IF NOT EXISTS tbl_instrutores (
    instrutor_id   INT AUTO_INCREMENT PRIMARY KEY,
    nome           VARCHAR(120) NOT NULL,
    titulo         VARCHAR(100) NULL,
    email          VARCHAR(120) NULL,
    bio            TEXT         NULL,
    assinatura_img VARCHAR(120) NULL,
    ativo          TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em      DATETIME     NOT NULL DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Novas colunas em tbl_matriculas ──────────────────────────
ALTER TABLE tbl_matriculas
    ADD COLUMN IF NOT EXISTS certificado_gerado     TINYINT(1) NOT NULL DEFAULT 0 AFTER nota_final,
    ADD COLUMN IF NOT EXISTS certificado_codigo     VARCHAR(30) NULL AFTER certificado_gerado,
    ADD COLUMN IF NOT EXISTS certificado_emitido_em DATETIME    NULL AFTER certificado_codigo;

-- ── Tabela de certificados ────────────────────────────────────
CREATE TABLE IF NOT EXISTS tbl_certificados (
    cert_id      INT AUTO_INCREMENT PRIMARY KEY,
    matricula_id INT          NOT NULL,
    codigo       VARCHAR(30)  NOT NULL UNIQUE,
    valido       TINYINT(1)   NOT NULL DEFAULT 1,
    emitido_em   DATETIME     NOT NULL DEFAULT NOW(),
    emitido_por  INT          NULL,
    observacao   TEXT         NULL,
    CONSTRAINT fk_cert_mat FOREIGN KEY (matricula_id) REFERENCES tbl_matriculas(matricula_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Novas colunas em tbl_usuarios ────────────────────────────
ALTER TABLE tbl_usuarios
    ADD COLUMN IF NOT EXISTS rg               VARCHAR(20)  NULL AFTER cpf,
    ADD COLUMN IF NOT EXISTS data_nascimento  DATE         NULL AFTER rg,
    ADD COLUMN IF NOT EXISTS sexo             CHAR(1)      NULL AFTER data_nascimento,
    ADD COLUMN IF NOT EXISTS telefone         VARCHAR(15)  NULL AFTER celular,
    ADD COLUMN IF NOT EXISTS especialidade    VARCHAR(120) NULL AFTER crmv_uf,
    ADD COLUMN IF NOT EXISTS instituicao      VARCHAR(160) NULL AFTER especialidade,
    ADD COLUMN IF NOT EXISTS cep              VARCHAR(8)   NULL AFTER instituicao,
    ADD COLUMN IF NOT EXISTS logradouro       VARCHAR(160) NULL AFTER cep,
    ADD COLUMN IF NOT EXISTS numero           VARCHAR(10)  NULL AFTER logradouro,
    ADD COLUMN IF NOT EXISTS complemento      VARCHAR(80)  NULL AFTER numero,
    ADD COLUMN IF NOT EXISTS bairro           VARCHAR(80)  NULL AFTER complemento,
    ADD COLUMN IF NOT EXISTS cidade           VARCHAR(80)  NULL AFTER bairro,
    ADD COLUMN IF NOT EXISTS uf               CHAR(2)      NULL AFTER cidade,
    ADD COLUMN IF NOT EXISTS ultimo_acesso    DATETIME     NULL AFTER senha_hash,
    ADD COLUMN IF NOT EXISTS criado_por       INT          NULL AFTER ativo,
    ADD COLUMN IF NOT EXISTS atualizado_em    DATETIME     NULL AFTER criado_em;

-- ── Configurações úteis ───────────────────────────────────────
INSERT IGNORE INTO tbl_configuracoes (chave, valor, descricao) VALUES
    ('presidente_nome', 'Presidente do CRMV-TO',   'Nome do presidente para o certificado'),
    ('presidente_titulo','Médico(a) Veterinário(a)','Título do presidente'),
    ('cfmv_numero',     '0000',                    'Número de inscrição no CFMV');

-- ── Índices de performance ────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_cert_codigo      ON tbl_certificados(codigo);
CREATE INDEX IF NOT EXISTS idx_mat_curso        ON tbl_matriculas(curso_id);
CREATE INDEX IF NOT EXISTS idx_mat_usuario      ON tbl_matriculas(usuario_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_perfil  ON tbl_usuarios(perfil_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_email   ON tbl_usuarios(email);
CREATE INDEX IF NOT EXISTS idx_cursos_status    ON tbl_cursos(status, ativo);
