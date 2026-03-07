<?php
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $c = dbQueryOne("SELECT capa FROM tbl_cursos WHERE curso_id = ?", [$id]);
    if ($c && $c['capa']) {
        $arq = __DIR__ . '/../../uploads/capas/' . $c['capa'];
        if (file_exists($arq)) @unlink($arq);
        dbExecute("UPDATE tbl_cursos SET capa = NULL WHERE curso_id = ?", [$id]);
        flash('Capa removida.', 'sucesso');
    }
}
header('Location: /crmv/admin/cursos/form.php?id=' . $id);
exit;
