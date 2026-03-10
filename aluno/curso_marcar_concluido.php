<?php
/**
 * curso_marcar_concluido.php
 * /crmv/aluno/curso_marcar_concluido.php
 *
 * Endpoint AJAX chamado quando o aluno clica "Marcar como concluído"
 * em cursos que NÃO possuem módulos/aulas cadastradas.
 *
 * Atualiza progresso_ead = 100 na matrícula, permitindo acesso
 * à avaliação e ao certificado.
 */
require_once __DIR__ . '/../includes/conexao.php';
exigeLogin();

header('Content-Type: application/json');

$usr_id       = (int)$_SESSION['usr_id'];
$matricula_id = (int)($_POST['matricula_id'] ?? 0);

if (!$matricula_id) {
    echo json_encode(['ok' => false, 'erro' => 'Matrícula inválida']);
    exit;
}

// Verifica que a matrícula pertence ao aluno logado
$mat = dbQueryOne(
    "SELECT matricula_id, curso_id, status, progresso_ead
     FROM tbl_matriculas
     WHERE matricula_id = ? AND usuario_id = ?",
    [$matricula_id, $usr_id]
);

if (!$mat) {
    echo json_encode(['ok' => false, 'erro' => 'Matrícula não encontrada']);
    exit;
}

// Só atualiza se não estiver cancelada/reprovada
if (in_array($mat['status'], ['CANCELADA', 'REPROVADO'])) {
    echo json_encode(['ok' => false, 'erro' => 'Status inválido']);
    exit;
}

// Atualiza progresso para 100%
dbExecute(
    "UPDATE tbl_matriculas SET progresso_ead = 100 WHERE matricula_id = ?",
    [$matricula_id]
);

echo json_encode(['ok' => true]);
exit;
