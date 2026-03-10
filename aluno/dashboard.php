<?php
require_once __DIR__ . '/../includes/conexao.php';
exigeLogin();
if ((int)($_SESSION['usr_perfil'] ?? 0) === 1) {
    header('Location: /crmv/admin/dashboard.php'); exit;
}

$usr_id = (int)$_SESSION['usr_id'];

// Dados do usuário
$usuario = dbQueryOne(
    "SELECT nome_completo, crmv_numero, crmv_uf, email FROM tbl_usuarios WHERE usuario_id = ?",
    [$usr_id]
);

// Totais rápidos
$totais = dbQueryOne(
    "SELECT
        COUNT(*) AS total,
        SUM(m.status = 'ATIVA')    AS ativos,
        SUM(m.status = 'CONCLUIDA') AS concluidos,
        SUM(m.certificado_gerado = 1) AS certificados
     FROM tbl_matriculas m
     INNER JOIN tbl_cursos c ON m.curso_id = c.curso_id
     WHERE m.usuario_id = ? AND c.ativo = 1",
    [$usr_id]
);

// Matriculas — TODAS ou filtradas pela aba
$aba = $_GET['aba'] ?? 'todos';
$whereStatus = match($aba) {
    'ativos'     => " AND m.status = 'ATIVA'",
    'concluidos' => " AND m.status = 'CONCLUIDA'",
    'cancelados' => " AND m.status IN ('CANCELADA','REPROVADO')",
    default      => ''
};

$matriculas = dbQuery(
    "SELECT m.matricula_id, m.status, m.matriculado_em,
            m.certificado_gerado, m.certificado_codigo, m.progresso_ead,
            c.curso_id, c.titulo, c.tipo, c.modalidade,
            c.carga_horaria, c.data_inicio, c.data_fim,
            c.capa, c.youtube_id, c.link_ead,
            c.local_nome, c.local_cidade, c.local_uf,
            cat.nome AS cat_nome, cat.cor_hex,
            (SELECT COUNT(*) FROM tbl_materiais mat WHERE mat.curso_id = c.curso_id) AS total_materiais
     FROM tbl_matriculas m
     INNER JOIN tbl_cursos c ON m.curso_id = c.curso_id
     LEFT  JOIN tbl_categorias cat ON c.categoria_id = cat.categoria_id
     WHERE m.usuario_id = ? AND c.ativo = 1 $whereStatus
     ORDER BY m.matriculado_em DESC",
    [$usr_id]
);

$pageTitulo  = 'Início';
$paginaAtiva = 'dashboard';
require_once __DIR__ . '/../includes/layout_aluno.php';

// helpers de badge local
function badgeMatStatus(string $s): string {
    return match($s) {
        'ATIVA'     => '<span class="badge b-azul"><i class="fa-solid fa-play"></i> Em andamento</span>',
        'CONCLUIDA' => '<span class="badge b-verde"><i class="fa-solid fa-check"></i> Concluído</span>',
        'CANCELADA' => '<span class="badge b-verm"><i class="fa-solid fa-ban"></i> Cancelado</span>',
        'REPROVADO' => '<span class="badge b-verm"><i class="fa-solid fa-xmark"></i> Reprovado</span>',
        default     => '<span class="badge b-cinza">' . htmlspecialchars($s) . '</span>',
    };
}
?>

<!-- ── Boas-vindas ────────────────────────────────────────── -->
<div style="background:linear-gradient(120deg,var(--azul-esc) 0%,var(--azul-med) 100%);
            border-radius:var(--radius-lg);padding:26px 32px;color:#fff;
            margin-bottom:26px;position:relative;overflow:hidden">
    <div style="position:absolute;right:-20px;top:-20px;width:140px;height:140px;
                background:rgba(201,162,39,.10);border-radius:50%"></div>
    <div style="position:absolute;right:40px;bottom:-30px;width:100px;height:100px;
                background:rgba(255,255,255,.04);border-radius:50%"></div>
    <div style="position:relative">
        <h2 style="font-family:var(--font-titulo);font-size:1.4rem;margin:0 0 5px;font-weight:700">
            Olá, <?= ($usuario['nome_completo'] ?? '') ?>!
        </h2>
        <p style="font-size:.875rem;color:rgba(255,255,255,.65);margin:0">
            Bem-vindo ao seu portal de capacitações — CRMV-TO
        </p>
        <?php if ($usuario['crmv_numero']): ?>
        <div style="display:inline-flex;align-items:center;gap:6px;
                    background:rgba(201,162,39,.18);border:1px solid rgba(201,162,39,.35);
                    color:var(--ouro2);padding:4px 12px;border-radius:20px;
                    font-size:.72rem;font-weight:700;margin-top:12px">
            <i class="fa-solid fa-id-card"></i>
            CRMV <?= htmlspecialchars($usuario['crmv_numero']) ?>-<?= htmlspecialchars($usuario['crmv_uf']) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Stat cards ─────────────────────────────────────────── -->
<div class="stat-grid" style="margin-bottom:26px">
    <div class="stat-card">
        <div class="stat-icone si-azul"><i class="fa-solid fa-graduation-cap"></i></div>
        <div class="stat-info">
            <div class="stat-valor"><?= (int)($totais['total'] ?? 0) ?></div>
            <div class="stat-rotulo">Total de inscrições</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icone si-ouro"><i class="fa-solid fa-spinner"></i></div>
        <div class="stat-info">
            <div class="stat-valor"><?= (int)($totais['ativos'] ?? 0) ?></div>
            <div class="stat-rotulo">Em andamento</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icone si-verde"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-info">
            <div class="stat-valor"><?= (int)($totais['concluidos'] ?? 0) ?></div>
            <div class="stat-rotulo">Concluídos</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icone" style="background:var(--ouro-bg);color:var(--ouro-txt)">
            <i class="fa-solid fa-certificate"></i>
        </div>
        <div class="stat-info">
            <div class="stat-valor"><?= (int)($totais['certificados'] ?? 0) ?></div>
            <div class="stat-rotulo">Certificados emitidos</div>
        </div>
    </div>
</div>

<!-- ── Filtros de aba ─────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:12px 20px">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
            <div class="tabs-barra" style="border:none;margin:0;gap:4px">
                <?php foreach ([
                    'todos'     => ['label'=>'Todos',       'icon'=>'fa-list'],
                    'ativos'    => ['label'=>'Em andamento', 'icon'=>'fa-spinner'],
                    'concluidos'=> ['label'=>'Concluídos',  'icon'=>'fa-circle-check'],
                    'cancelados'=> ['label'=>'Cancelados',  'icon'=>'fa-ban'],
                ] as $k => $v): ?>
                <a href="?aba=<?= $k ?>"
                   class="btn btn-sm <?= $aba === $k ? 'btn-secundario' : 'btn-ghost' ?>">
                    <i class="fa-solid <?= $v['icon'] ?>"></i> <?= $v['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>
            <span style="font-size:.78rem;color:var(--c400)">
                <?= count($matriculas) ?> curso<?= count($matriculas) !== 1 ? 's' : '' ?>
            </span>
        </div>
    </div>
</div>

<!-- ── Grid de cursos ─────────────────────────────────────── -->
<?php if (empty($matriculas)): ?>
<div class="card">
    <div class="vazio" style="padding:64px 24px">
        <i class="fa-solid fa-graduation-cap"></i>
        <h3>Nenhum curso encontrado</h3>
        <p><?= $aba !== 'todos' ? 'Não há cursos com este filtro.' : 'Você ainda não está matriculado em nenhum curso.' ?></p>
    </div>
</div>

<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:18px">
<?php foreach ($matriculas as $m): ?>

<div class="curso-card-aluno">

    <!-- Capa -->
    <div class="curso-capa-aluno">
        <?php if ($m['capa']): ?>
        <img src="/crmv/uploads/capas/<?= htmlspecialchars($m['capa']) ?>" alt="">
        <div class="curso-capa-overlay"></div>
        <?php else: ?>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
                    background:linear-gradient(135deg,<?= htmlspecialchars($m['cor_hex'] ?? '#0d2137') ?>,<?= htmlspecialchars($m['cor_hex'] ?? '#15385c') ?>99)">
            <i class="fa-solid fa-graduation-cap" style="font-size:3rem;color:rgba(255,255,255,.18)"></i>
        </div>
        <?php endif; ?>

        <!-- Badges sobre a capa -->
        <div style="position:absolute;top:8px;left:8px;display:flex;gap:5px;flex-wrap:wrap">
            <?= badgeModalidade($m['modalidade']) ?>
        </div>
        <div style="position:absolute;top:8px;right:8px">
            <?= badgeMatStatus($m['status']) ?>
        </div>

        <?php if ($m['capa']): ?>
        <div style="position:absolute;bottom:8px;left:12px;right:12px">
            <div style="font-weight:700;font-size:.87rem;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.7);
                        overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= htmlspecialchars($m['titulo']) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Barra de progresso EAD -->
    <?php if ($m['modalidade'] === 'EAD' && $m['progresso_ead'] > 0): ?>
    <div class="progresso-bar">
        <div class="progresso-fill"
             style="width:<?= min(100,(int)$m['progresso_ead']) ?>%;
                    background:<?= $m['status']==='CONCLUIDA' ? 'var(--verde)' : 'var(--azul-clr)' ?>">
        </div>
    </div>
    <?php endif; ?>

    <!-- Corpo do card -->
    <div style="padding:16px;flex:1;display:flex;flex-direction:column;gap:10px">

        <?php if (!$m['capa']): ?>
        <div>
            <div style="font-weight:700;font-size:.9rem;color:var(--azul-esc);line-height:1.4">
                <?= htmlspecialchars(truncaTexto($m['titulo'], 65)) ?>
            </div>
            <?php if ($m['cat_nome']): ?>
            <div style="font-size:.72rem;color:var(--c400);margin-top:3px">
                <?= htmlspecialchars($m['cat_nome']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="font-weight:700;font-size:.88rem;color:var(--azul-esc);line-height:1.35">
            <?= htmlspecialchars(truncaTexto($m['titulo'], 65)) ?>
        </div>
        <?php endif; ?>

        <!-- Meta informações -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;font-size:.76rem;color:var(--c500)">
            <div style="display:flex;align-items:center;gap:5px">
                <i class="fa-solid fa-tag" style="color:var(--c300);width:12px"></i>
                <?= htmlspecialchars($m['tipo']) ?>
            </div>
            <div style="display:flex;align-items:center;gap:5px">
                <i class="fa-solid fa-clock" style="color:var(--c300);width:12px"></i>
                <?= $m['carga_horaria'] ?>h
            </div>
            <?php if ($m['data_inicio']): ?>
            <div style="display:flex;align-items:center;gap:5px">
                <i class="fa-solid fa-calendar" style="color:var(--c300);width:12px"></i>
                <?= fmtData($m['data_inicio']) ?>
            </div>
            <?php endif; ?>
            <?php if ($m['local_cidade']): ?>
            <div style="display:flex;align-items:center;gap:5px">
                <i class="fa-solid fa-location-dot" style="color:var(--c300);width:12px"></i>
                <?= htmlspecialchars($m['local_cidade']) ?>
            </div>
            <?php endif; ?>
            <?php if ($m['total_materiais'] > 0): ?>
            <div style="display:flex;align-items:center;gap:5px">
                <i class="fa-solid fa-file" style="color:var(--c300);width:12px"></i>
                <?= $m['total_materiais'] ?> material<?= $m['total_materiais'] != 1 ? 'is' : '' ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Aviso de certificado disponível -->
        <?php if ($m['status'] === 'CONCLUIDA'): ?>
        <div style="background:var(--verde-bg);border:1px solid #86efac;border-radius:var(--radius);
                    padding:6px 10px;display:flex;align-items:center;gap:7px;font-size:.76rem;
                    color:var(--verde-txt);font-weight:600">
            <i class="fa-solid fa-certificate" style="color:var(--verde)"></i>
            <?= $m['certificado_gerado'] ? 'Certificado emitido' : 'Certificado disponível!' ?>
        </div>
        <?php endif; ?>

        <!-- Botões de ação -->
        <div style="display:flex;gap:8px;margin-top:auto;padding-top:4px">
            <a href="/crmv/aluno/curso.php?id=<?= $m['matricula_id'] ?>"
               class="btn btn-secundario btn-sm"
               style="flex:1;justify-content:center">
                <i class="fa-solid fa-play"></i> Acessar
            </a>

            <?php if ($m['status'] === 'CONCLUIDA'): ?>
            <a href="/crmv/aluno/<?= $m['certificado_gerado'] ? 'certificado_ver.php' : 'emitir_certificado.php' ?>?id=<?= $m['matricula_id'] ?>"
               class="btn btn-sm"
               style="flex:1;justify-content:center;background:var(--ouro-bg);color:var(--ouro-txt);border:1.5px solid #fde68a">
                <i class="fa-solid fa-certificate"></i>
                <?= $m['certificado_gerado'] ? 'Ver cert.' : 'Emitir' ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout_aluno_footer.php'; ?>
