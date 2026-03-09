<?php
require_once __DIR__ . '/../includes/conexao.php';
exigeLogin();
if ((int)($_SESSION['usr_perfil'] ?? 0) === 1) {
    header('Location: /crmv/admin/dashboard.php'); exit;
}

$usr_id = (int)$_SESSION['usr_id'];

// Matrículas concluídas com info de certificado
$cursos_cert = dbQuery(
    "SELECT m.matricula_id, m.status, m.certificado_gerado, m.certificado_codigo,
            m.nota_final, m.matriculado_em,
            c.titulo, c.tipo, c.carga_horaria, c.data_inicio, c.data_fim,
            cert.cert_id, cert.codigo, cert.emitido_em
     FROM tbl_matriculas m
     INNER JOIN tbl_cursos c ON m.curso_id = c.curso_id
     LEFT  JOIN tbl_certificados cert ON cert.matricula_id = m.matricula_id AND cert.valido = 1
     WHERE m.usuario_id = ? AND m.status = 'CONCLUIDA' AND c.ativo = 1
     ORDER BY m.matriculado_em DESC",
    [$usr_id]
);

$pageTitulo  = 'Certificados';
$paginaAtiva = 'certificados';
require_once __DIR__ . '/../includes/layout_aluno.php';
?>

<div class="pg-header">
    <div class="pg-header-row">
        <div>
            <h1 class="pg-titulo">Meus Certificados</h1>
            <p class="pg-subtitulo"><?= count($cursos_cert) ?> curso<?= count($cursos_cert) !== 1 ? 's' : '' ?> concluído<?= count($cursos_cert) !== 1 ? 's' : '' ?></p>
        </div>
    </div>
</div>

<?php if (empty($cursos_cert)): ?>
<div class="card">
    <div class="vazio" style="padding:64px">
        <i class="fa-solid fa-certificate"></i>
        <h3>Nenhum curso concluído ainda</h3>
        <p>Conclua um curso para emitir seu certificado.</p>
        <a href="/crmv/aluno/dashboard.php" class="btn btn-secundario btn-sm" style="margin-top:14px">
            <i class="fa-solid fa-arrow-left"></i> Ver meus cursos
        </a>
    </div>
</div>

<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px">
<?php foreach ($cursos_cert as $item): ?>

<div class="card" style="overflow:hidden">
    <div style="display:flex;align-items:center;gap:0;flex-wrap:wrap">

        <!-- Faixa lateral colorida -->
        <div style="width:5px;align-self:stretch;background:<?= $item['cert_id'] ? 'var(--verde)' : 'var(--ouro)' ?>;flex-shrink:0"></div>

        <!-- Conteúdo principal -->
        <div style="flex:1;padding:16px 20px;min-width:0">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <div style="flex:1;min-width:0">
                    <div style="font-family:var(--font-titulo);font-size:.95rem;font-weight:700;
                                color:var(--azul-esc);margin-bottom:4px;line-height:1.3">
                        <?= htmlspecialchars($item['titulo']) ?>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;font-size:.78rem;color:var(--c500)">
                        <span><i class="fa-solid fa-tag" style="width:12px;color:var(--c300)"></i> <?= htmlspecialchars($item['tipo']) ?></span>
                        <span><i class="fa-solid fa-clock" style="width:12px;color:var(--c300)"></i> <?= $item['carga_horaria'] ?>h</span>
                        <?php if ($item['data_inicio']): ?>
                        <span><i class="fa-solid fa-calendar" style="width:12px;color:var(--c300)"></i> <?= fmtData($item['data_inicio']) ?></span>
                        <?php endif; ?>
                        <?php if ($item['nota_final']): ?>
                        <span><i class="fa-solid fa-star" style="width:12px;color:var(--ouro)"></i> Nota <?= number_format($item['nota_final'],1) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status / Código -->
                <div style="text-align:right;flex-shrink:0">
                    <?php if ($item['cert_id']): ?>
                    <span class="badge b-verde" style="margin-bottom:5px;display:inline-flex">
                        <i class="fa-solid fa-check"></i> Emitido
                    </span>
                    <div style="font-size:.72rem;font-family:monospace;color:var(--c500);margin-top:2px">
                        <?= htmlspecialchars($item['codigo']) ?>
                    </div>
                    <?php else: ?>
                    <span class="badge b-ouro" style="display:inline-flex">
                        <i class="fa-solid fa-hourglass-half"></i> Disponível
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div style="padding:16px;border-left:1px solid var(--c200);display:flex;flex-direction:column;gap:7px;flex-shrink:0">
            <?php if ($item['cert_id']): ?>
            <a href="/crmv/aluno/certificado_ver.php?id=<?= $item['matricula_id'] ?>"
               class="btn btn-secundario btn-sm">
                <i class="fa-solid fa-eye"></i> Ver
            </a>
            <a href="/crmv/admin/certificados/imprimir.php?codigo=<?= urlencode($item['codigo']) ?>"
               target="_blank" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-print"></i> PDF
            </a>
            <?php else: ?>
            <a href="/crmv/aluno/emitir_certificado.php?id=<?= $item['matricula_id'] ?>"
               class="btn btn-primario btn-sm">
                <i class="fa-solid fa-certificate"></i> Emitir
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout_aluno_footer.php'; ?>
