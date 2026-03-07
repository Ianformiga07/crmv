<?php
require_once __DIR__ . '/../includes/conexao.php';
exigeAdmin();

// ── TOTAIS GERAIS ────────────────────────────────────────────
$totais = dbQueryOne("SELECT * FROM vw_dashboard_totais");

// ── CURSOS RECENTES ──────────────────────────────────────────
$cursos = dbQuery(
    "SELECT c.curso_id, c.titulo, c.tipo, c.modalidade, c.status,
            c.data_inicio, c.carga_horaria,
            cat.nome AS cat_nome, cat.cor_hex,
            (SELECT COUNT(*) FROM tbl_matriculas m WHERE m.curso_id = c.curso_id) AS total_mat,
            (SELECT COUNT(*) FROM tbl_matriculas m WHERE m.curso_id = c.curso_id AND m.certificado_gerado = 1) AS total_cert
     FROM tbl_cursos c
     LEFT JOIN tbl_categorias cat ON c.categoria_id = cat.categoria_id
     WHERE c.ativo = 1
     ORDER BY c.criado_em DESC
     LIMIT 8"
);

// ── ÚLTIMOS CERTIFICADOS ─────────────────────────────────────
$certs = dbQuery(
    "SELECT m.certificado_codigo, m.certificado_emitido_em,
            u.nome_completo, u.crmv_numero, u.crmv_uf,
            c.titulo AS curso_titulo
     FROM tbl_matriculas m
     INNER JOIN tbl_usuarios u ON m.usuario_id = u.usuario_id
     INNER JOIN tbl_cursos   c ON m.curso_id   = c.curso_id
     WHERE m.certificado_gerado = 1
     ORDER BY m.certificado_emitido_em DESC
     LIMIT 6"
);

// ── PRÓXIMOS EVENTOS ─────────────────────────────────────────
$eventos = dbQuery(
    "SELECT curso_id, titulo, tipo, modalidade, data_inicio, local_cidade,
            (SELECT COUNT(*) FROM tbl_matriculas m WHERE m.curso_id = c.curso_id) AS inscritos,
            vagas
     FROM tbl_cursos c
     WHERE status = 'PUBLICADO' AND ativo = 1 AND data_inicio >= CURDATE()
     ORDER BY data_inicio ASC
     LIMIT 4"
);

// ── ATIVIDADE RECENTE ────────────────────────────────────────
$logs = dbQuery(
    "SELECT l.acao, l.descricao, l.criado_em, u.nome_completo
     FROM tbl_log_atividades l
     LEFT JOIN tbl_usuarios u ON l.usuario_id = u.usuario_id
     ORDER BY l.criado_em DESC
     LIMIT 8"
);

$pageTitulo  = 'Dashboard';
$paginaAtiva = 'dashboard';
require_once __DIR__ . '/../includes/layout.php';
?>

<!-- PAGE HEADER -->
<div class="pg-header">
    <div class="pg-header-row">
        <div>
            <h1 class="pg-titulo">Bom dia, <?= htmlspecialchars(truncaTexto($_SESSION['usr_nome'], 20)) ?> 👋</h1>
            <p class="pg-subtitulo">Resumo geral do sistema — <?= fmtData(date('Y-m-d')) ?></p>
        </div>
        <div class="pg-acoes">
            <a href="/crmv/admin/usuarios/form.php" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-user-plus"></i> Novo Veterinário
            </a>
            <a href="/crmv/admin/cursos/form.php" class="btn btn-primario btn-sm">
                <i class="fa-solid fa-plus"></i> Novo Curso
            </a>
        </div>
    </div>
</div>

<!-- STAT CARDS -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icone si-azul"><i class="fa-solid fa-user-doctor"></i></div>
        <div class="stat-info">
            <div class="stat-valor"><?= $totais['total_veterinarios'] ?? 0 ?></div>
            <div class="stat-rotulo">Veterinários cadastrados</div>
            <?php if (($totais['novos_este_mes'] ?? 0) > 0): ?>
            <div class="stat-delta delta-pos"><i class="fa-solid fa-arrow-up"></i> +<?= $totais['novos_este_mes'] ?> este mês</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icone si-verde"><i class="fa-solid fa-graduation-cap"></i></div>
        <div class="stat-info">
            <div class="stat-valor"><?= $totais['total_cursos'] ?? 0 ?></div>
            <div class="stat-rotulo">Cursos & Palestras</div>
            <div class="stat-delta delta-pos"><i class="fa-solid fa-wifi"></i> <?= $totais['cursos_publicados'] ?? 0 ?> publicados</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icone si-ouro"><i class="fa-solid fa-list-check"></i></div>
        <div class="stat-info">
            <div class="stat-valor"><?= $totais['total_matriculas'] ?? 0 ?></div>
            <div class="stat-rotulo">Total de matrículas</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icone si-verde"><i class="fa-solid fa-certificate"></i></div>
        <div class="stat-info">
            <div class="stat-valor"><?= $totais['total_certificados'] ?? 0 ?></div>
            <div class="stat-rotulo">Certificados emitidos</div>
        </div>
    </div>
</div>

<!-- GRID PRINCIPAL -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">

    <!-- COLUNA ESQUERDA -->
    <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Cursos recentes -->
        <div class="card">
            <div class="card-header">
                <span class="card-titulo"><i class="fa-solid fa-graduation-cap"></i> Cursos Recentes</span>
                <a href="/crmv/admin/cursos/lista.php" class="btn btn-ghost btn-sm">Ver todos <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="tabela-wrapper">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th>Curso / Palestra</th><th>Tipo</th><th>Modalidade</th>
                            <th>Início</th><th>Matrículas</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($cursos)): ?>
                        <tr><td colspan="7">
                            <div class="vazio" style="padding:28px">
                                <i class="fa-solid fa-graduation-cap"></i>
                                <p>Nenhum curso cadastrado ainda.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                    <?php foreach ($cursos as $c): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:9px">
                                <span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:<?= htmlspecialchars($c['cor_hex'] ?? '#94a3b8') ?>"></span>
                                <div>
                                    <div style="font-weight:600;font-size:.875rem;color:var(--c900)"><?= htmlspecialchars(truncaTexto($c['titulo'], 48)) ?></div>
                                    <?php if ($c['cat_nome']): ?>
                                    <div style="font-size:.7rem;color:var(--c400);margin-top:1px"><?= htmlspecialchars($c['cat_nome']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge b-azul"><?= htmlspecialchars($c['tipo']) ?></span></td>
                        <td><?= badgeModalidade($c['modalidade']) ?></td>
                        <td style="font-size:.82rem;color:var(--c500);white-space:nowrap"><?= fmtData($c['data_inicio']) ?></td>
                        <td>
                            <strong style="font-size:.9rem"><?= $c['total_mat'] ?></strong>
                            <?php if ($c['total_cert'] > 0): ?>
                            <span style="font-size:.7rem;color:var(--verde-txt);margin-left:4px"><i class="fa-solid fa-certificate"></i> <?= $c['total_cert'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= badgeStatus($c['status']) ?></td>
                        <td>
                            <div class="acoes">
                                <a href="/crmv/admin/cursos/form.php?id=<?= $c['curso_id'] ?>" class="btn btn-ghost btn-icone btn-sm" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                <a href="/crmv/admin/cursos/matriculas.php?id=<?= $c['curso_id'] ?>" class="btn btn-ghost btn-icone btn-sm" title="Matrículas"><i class="fa-solid fa-users"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Próximos eventos -->
        <?php if (!empty($eventos)): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-titulo"><i class="fa-solid fa-calendar-days"></i> Próximos Eventos</span>
            </div>
            <?php
            $meses = ['','JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
            foreach ($eventos as $ev):
                $dt = $ev['data_inicio'] ? new DateTime($ev['data_inicio']) : null;
            ?>
            <div style="display:flex;align-items:center;gap:14px;padding:13px 20px;border-bottom:1px solid var(--c100)">
                <div style="width:44px;height:44px;border-radius:10px;background:var(--azul-med);color:white;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;line-height:1">
                    <span style="font-size:.62rem;text-transform:uppercase;opacity:.7"><?= $dt ? $meses[(int)$dt->format('n')] : '' ?></span>
                    <span style="font-weight:700;font-size:1rem"><?= $dt ? $dt->format('d') : '—' ?></span>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-weight:600;font-size:.875rem;color:var(--c900)"><?= htmlspecialchars(truncaTexto($ev['titulo'], 46)) ?></div>
                    <div style="font-size:.75rem;color:var(--c400);display:flex;gap:10px;margin-top:3px">
                        <span><i class="fa-solid fa-tag"></i> <?= $ev['tipo'] ?></span>
                        <?php if ($ev['local_cidade']): ?><span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($ev['local_cidade']) ?></span><?php endif; ?>
                        <span><i class="fa-solid fa-users"></i> <?= $ev['inscritos'] ?> inscritos</span>
                    </div>
                </div>
                <a href="/crmv/admin/cursos/matriculas.php?id=<?= $ev['curso_id'] ?>" class="btn btn-ghost btn-sm">Ver</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div><!-- /col-esquerda -->

    <!-- COLUNA DIREITA -->
    <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Últimos certificados -->
        <div class="card">
            <div class="card-header">
                <span class="card-titulo"><i class="fa-solid fa-award" style="color:var(--ouro)"></i> Últimos Certificados</span>
                <a href="/crmv/admin/certificados/lista.php" class="btn btn-ghost btn-sm">Ver todos</a>
            </div>
            <?php if (empty($certs)): ?>
            <div class="vazio" style="padding:28px"><i class="fa-solid fa-certificate"></i><p>Nenhum certificado ainda.</p></div>
            <?php else: ?>
            <?php foreach ($certs as $ct): ?>
            <div style="display:flex;gap:11px;align-items:flex-start;padding:12px 18px;border-bottom:1px solid var(--c100)">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--verde-bg);color:var(--verde-txt);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0">
                    <i class="fa-solid fa-certificate"></i>
                </div>
                <div style="min-width:0;flex:1">
                    <div style="font-size:.83rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($ct['nome_completo']) ?></div>
                    <div style="font-size:.73rem;color:var(--c400);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:1px"><?= htmlspecialchars(truncaTexto($ct['curso_titulo'], 36)) ?></div>
                    <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
                        <code style="font-size:.67rem;background:var(--c100);padding:2px 6px;border-radius:4px;color:var(--c600)"><?= htmlspecialchars($ct['certificado_codigo']) ?></code>
                        <span style="font-size:.68rem;color:var(--c400)"><?= fmtData($ct['certificado_emitido_em']) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Atividade recente -->
        <div class="card">
            <div class="card-header">
                <span class="card-titulo"><i class="fa-solid fa-clock-rotate-left"></i> Atividade Recente</span>
                <a href="/crmv/admin/logs.php" class="btn btn-ghost btn-sm">Ver log</a>
            </div>
            <?php if (empty($logs)): ?>
            <div class="vazio" style="padding:24px"><i class="fa-solid fa-history"></i><p>Sem atividades registradas.</p></div>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <div style="display:flex;gap:10px;align-items:flex-start;padding:10px 18px;border-bottom:1px solid var(--c100)">
                <div style="width:28px;height:28px;border-radius:50%;background:var(--c100);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="fa-solid fa-circle-dot" style="font-size:.7rem;color:var(--c400)"></i>
                </div>
                <div style="min-width:0;flex:1">
                    <div style="font-size:.8rem;color:var(--c700);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars(truncaTexto($log['descricao'] ?: $log['acao'], 40)) ?></div>
                    <div style="font-size:.7rem;color:var(--c400);margin-top:2px"><?= htmlspecialchars($log['nome_completo'] ?? 'Sistema') ?> &bull; <?= fmtDataHora($log['criado_em']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Atalhos -->
        <div class="card">
            <div class="card-header"><span class="card-titulo"><i class="fa-solid fa-bolt"></i> Atalhos Rápidos</span></div>
            <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <a href="/crmv/admin/usuarios/form.php" class="btn btn-ghost" style="flex-direction:column;gap:6px;padding:14px;height:auto;text-align:center;justify-content:center">
                    <i class="fa-solid fa-user-plus" style="font-size:1.1rem;color:var(--azul-clr)"></i>
                    <span style="font-size:.75rem">Novo<br>Veterinário</span>
                </a>
                <a href="/crmv/admin/cursos/form.php" class="btn btn-ghost" style="flex-direction:column;gap:6px;padding:14px;height:auto;text-align:center;justify-content:center">
                    <i class="fa-solid fa-graduation-cap" style="font-size:1.1rem;color:var(--verde)"></i>
                    <span style="font-size:.75rem">Novo<br>Curso</span>
                </a>
                <a href="/crmv/admin/certificados/lista.php" class="btn btn-ghost" style="flex-direction:column;gap:6px;padding:14px;height:auto;text-align:center;justify-content:center">
                    <i class="fa-solid fa-certificate" style="font-size:1.1rem;color:var(--ouro)"></i>
                    <span style="font-size:.75rem">Emitir<br>Certificado</span>
                </a>
                <a href="/crmv/admin/relatorios/matriculas.php" class="btn btn-ghost" style="flex-direction:column;gap:6px;padding:14px;height:auto;text-align:center;justify-content:center">
                    <i class="fa-solid fa-chart-bar" style="font-size:1.1rem;color:var(--c500)"></i>
                    <span style="font-size:.75rem">Relatório<br>Matrículas</span>
                </a>
            </div>
        </div>

    </div><!-- /col-direita -->
</div>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
