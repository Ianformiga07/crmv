<?php
/**
 * emitir_certificado.php — rota do ALUNO para emitir seu próprio certificado
 * Arquivo: /crmv/aluno/emitir_certificado.php
 *
 * Admin NÃO acessa esta rota — é redirecionado para /crmv/admin/certificados/emitir.php
 */
require_once __DIR__ . '/../includes/conexao.php';
exigeLogin();

// Admin usa rota própria
if ((int)($_SESSION['usr_perfil'] ?? 0) === 1) {
    header('Location: /crmv/admin/certificados/emitir.php?' . $_SERVER['QUERY_STRING']); exit;
}

$usr_id       = (int)$_SESSION['usr_id'];
$matricula_id = (int)($_GET['id'] ?? 0);
if (!$matricula_id) { header('Location: /crmv/aluno/dashboard.php'); exit; }

/*
 * Verifica se a matrícula pertence ao aluno logado e está concluída.
 * Colunas de tbl_matriculas: matricula_id, usuario_id, curso_id, status,
 *   certificado_gerado, certificado_codigo, certificado_emitido_em
 */
$matricula = dbQueryOne(
    "SELECT m.matricula_id,
            m.usuario_id,
            m.curso_id,
            m.status,
            m.certificado_gerado,
            m.certificado_codigo,
            m.certificado_emitido_em,
            c.titulo AS curso_titulo
     FROM   tbl_matriculas m
     INNER  JOIN tbl_cursos c ON c.curso_id = m.curso_id
     WHERE  m.matricula_id = ?
       AND  m.usuario_id   = ?",
    [$matricula_id, $usr_id]
);

if (!$matricula) {
    flash('Matrícula não encontrada.', 'erro');
    header('Location: /crmv/aluno/dashboard.php'); exit;
}

if ($matricula['status'] !== 'CONCLUIDA') {
    flash('Certificado disponível apenas para cursos concluídos.', 'erro');
    header('Location: /crmv/aluno/dashboard.php'); exit;
}

// Já emitido: redireciona para visualização
if ($matricula['certificado_gerado'] && $matricula['certificado_codigo']) {
    header('Location: /crmv/aluno/certificado_ver.php?id=' . $matricula_id); exit;
}

// ── Gera novo certificado ────────────────────────────────────
// Garante código único
do {
    $codigo = strtoupper(
        substr(bin2hex(random_bytes(2)), 0, 4) . '-' .
        substr(bin2hex(random_bytes(2)), 0, 4) . '-' .
        substr(bin2hex(random_bytes(2)), 0, 4)
    );
    $existe = dbQueryOne(
        "SELECT cert_id FROM tbl_certificados WHERE codigo = ? LIMIT 1",
        [$codigo]
    );
} while ($existe);

// Insere em tbl_certificados
// Colunas: cert_id, matricula_id, codigo, emitido_em, qr_path, pdf_path, valido
dbExecute(
    "INSERT INTO tbl_certificados (matricula_id, codigo, emitido_em, valido)
     VALUES (?, ?, NOW(), 1)",
    [$matricula_id, $codigo]
);

// Atualiza tbl_matriculas
dbExecute(
    "UPDATE tbl_matriculas
     SET certificado_gerado    = 1,
         certificado_codigo    = ?,
         certificado_emitido_em = NOW()
     WHERE matricula_id = ?",
    [$codigo, $matricula_id]
);

registraLog(
    $usr_id,
    'CERT_EMITIDO',
    "Certificado emitido: {$matricula['curso_titulo']} — código {$codigo}",
    'tbl_certificados',
    dbLastId()
);

flash('Certificado emitido com sucesso!', 'sucesso');
header('Location: /crmv/aluno/certificado_ver.php?id=' . $matricula_id);
exit;
