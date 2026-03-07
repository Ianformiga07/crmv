<?php
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

$id       = (int)($_GET['id']       ?? 0);
$curso_id = (int)($_GET['curso_id'] ?? 0);

if ($id > 0) {
    $mat = dbQueryOne("SELECT * FROM tbl_materiais WHERE material_id = ?", [$id]);
    if ($mat) {
        $arq = __DIR__ . '/../../uploads/materiais/' . $mat['nome_arquivo'];
        if (file_exists($arq)) @unlink($arq);
        dbExecute("DELETE FROM tbl_materiais WHERE material_id = ?", [$id]);
        flash('Material removido.', 'sucesso');
    }
}
header('Location: /crmv/admin/cursos/form.php?id=' . $curso_id);
exit;
