<?php
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

// ── Ações POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($acao === 'publicar') {
            dbExecute("UPDATE tbl_cursos SET status = 'PUBLICADO' WHERE curso_id = ?", [$id]);
            flash('Curso publicado com sucesso!', 'sucesso');
        } elseif ($acao === 'encerrar') {
            dbExecute("UPDATE tbl_cursos SET status = 'ENCERRADO' WHERE curso_id = ?", [$id]);
            flash('Curso encerrado.', 'aviso');
        } elseif ($acao === 'excluir') {
            $temMat = dbQueryOne("SELECT COUNT(*) AS n FROM tbl_matriculas WHERE curso_id = ?", [$id])['n'];
            if ($temMat > 0) {
                flash('Não é possível excluir: curso possui matrículas vinculadas.', 'erro');
            } else {
                $curso = dbQueryOne("SELECT capa FROM tbl_cursos WHERE curso_id = ?", [$id]);
                if ($curso && $curso['capa'] && file_exists(__DIR__ . '/../../uploads/capas/' . $curso['capa'])) {
                    unlink(__DIR__ . '/../../uploads/capas/' . $curso['capa']);
                }
                dbExecute("DELETE FROM tbl_curso_instrutores WHERE curso_id = ?", [$id]);
                dbExecute("DELETE FROM tbl_curso_materiais   WHERE curso_id = ?", [$id]);
                dbExecute("DELETE FROM tbl_cursos            WHERE curso_id = ?", [$id]);
                flash('Curso excluído com sucesso.', 'sucesso');
            }
        }
    }
    header('Location: /crmv/admin/cursos/lista.php?' . http_build_query([
        'busca' => $_GET['busca'] ?? '', 'filtro' => $_GET['filtro'] ?? 'todos', 'pag' => $_GET['pag'] ?? 1
    ]));
    exit;
}

// ── Parâmetros ───────────────────────────────────────────────
$busca  = trim($_GET['busca']  ?? '');
$filtro = $_GET['filtro'] ?? 'todos';
$pag    = max(1, (int)($_GET['pag'] ?? 1));
$porPag = 12;
$offset = ($pag - 1) * $porPag;

$where  = "WHERE c.ativo = 1";
$params = [];
if ($busca !== '') {
    $where  .= " AND (c.titulo LIKE ? OR c.tipo LIKE ?)";
    $params  = ["%$busca%", "%$busca%"];
}
if ($filtro !== 'todos') {
    $where .= " AND c.status = ?";
    $params[] = strtoupper($filtro);
}

// Busca por instrutor é feita via subquery para não quebrar o COUNT
if ($busca !== '') {
    $where .= " OR c.curso_id IN (SELECT ci.curso_id FROM tbl_curso_instrutores ci WHERE ci.nome LIKE ?)";
    $params[] = "%$busca%";
}

$total   = dbQueryOne("SELECT COUNT(*) AS n FROM tbl_cursos c $where", $params)['n'];
$paginas = (int)ceil($total / $porPag);

$cursos = dbQuery(
    "SELECT c.curso_id, c.titulo, c.tipo, c.modalidade, c.status, c.carga_horaria,
            c.data_inicio, c.data_fim, c.vagas, c.capa,
            cat.nome AS cat_nome, cat.cor_hex,
            (SELECT GROUP_CONCAT(ci.nome ORDER BY ci.ordem SEPARATOR ', ')
             FROM tbl_curso_instrutores ci WHERE ci.curso_id = c.curso_id) AS instrutores,
            (SELECT COUNT(*) FROM tbl_matriculas m WHERE m.curso_id = c.curso_id) AS total_mat,
            (SELECT COUNT(*) FROM tbl_matriculas m WHERE m.curso_id = c.curso_id AND m.certificado_gerado = 1) AS total_cert
     FROM tbl_cursos c
     LEFT JOIN tbl_categorias cat ON c.categoria_id = cat.categoria_id
     $where
     ORDER BY c.criado_em DESC
     LIMIT $porPag OFFSET $offset",
    $params
);

$pageTitulo  = 'Cursos & Palestras';
$paginaAtiva = 'cursos';
require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="pg-header">
    <div class="pg-header-row">
        <div>
            <h1 class="pg-titulo">Cursos & Palestras</h1>
            <p class="pg-subtitulo"><?= number_format($total, 0, ',', '.') ?> cadastrado<?= $total != 1 ? 's' : '' ?></p>
        </div>
        <div class="pg-acoes">
            <a href="/crmv/admin/cursos/form.php" class="btn btn-primario">
                <i class="fa-solid fa-plus"></i> Novo Curso
            </a>
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:14px 20px">
        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <div style="flex:1;min-width:260px;position:relative">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--c400);font-size:.8rem;pointer-events:none"></i>
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                    placeholder="Buscar por título, tipo ou instrutor..."
                    style="width:100%;padding:9px 12px 9px 36px;border:1.5px solid var(--c300);border-radius:8px;font-size:.875rem;font-family:inherit;outline:none"
                    onfocus="this.style.borderColor='var(--azul-clr)'" onblur="this.style.borderColor='var(--c300)'">
            </div>
            <div style="display:flex;gap:4px">
                <?php foreach (['todos'=>'Todos','RASCUNHO'=>'Rascunho','PUBLICADO'=>'Publicados','ENCERRADO'=>'Encerrados'] as $v=>$l): ?>
                <a href="?busca=<?= urlencode($busca) ?>&filtro=<?= $v ?>"
                   class="btn btn-sm <?= $filtro===$v ? 'btn-primario' : 'btn-ghost' ?>"><?= $l ?></a>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-secundario btn-sm"><i class="fa-solid fa-search"></i> Buscar</button>
            <?php if ($busca || $filtro !== 'todos'): ?>
            <a href="/crmv/admin/cursos/lista.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-xmark"></i> Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- GRID DE CARDS -->
<?php if (empty($cursos)): ?>
<div class="card">
    <div class="vazio" style="padding:60px">
        <i class="fa-solid fa-graduation-cap"></i>
        <h3>Nenhum curso encontrado</h3>
        <p><?= $busca ? 'Tente outro termo de busca.' : 'Cadastre o primeiro curso ou palestra.' ?></p>
        <?php if (!$busca): ?>
        <a href="/crmv/admin/cursos/form.php" class="btn btn-primario btn-sm" style="margin-top:12px">
            <i class="fa-solid fa-plus"></i> Criar agora
        </a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px">
<?php foreach ($cursos as $c): ?>
<div class="card" style="display:flex;flex-direction:column">

    <!-- Capa -->
    <div style="height:140px;background:linear-gradient(135deg,<?= htmlspecialchars($c['cor_hex'] ?? '#0d2137') ?>,<?= htmlspecialchars($c['cor_hex'] ?? '#15385c') ?>dd);position:relative;overflow:hidden;flex-shrink:0">
        <?php if ($c['capa']): ?>
        <img src="/crmv/uploads/capas/<?= htmlspecialchars($c['capa']) ?>"
             style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0">
        <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.6),transparent)"></div>
        <?php else: ?>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;opacity:.15">
            <i class="fa-solid fa-graduation-cap" style="font-size:4rem;color:#fff"></i>
        </div>
        <?php endif; ?>
        <div style="position:absolute;top:10px;left:10px;display:flex;gap:6px">
            <?= badgeModalidade($c['modalidade']) ?>
        </div>
        <div style="position:absolute;top:10px;right:10px">
            <?= badgeStatus($c['status']) ?>
        </div>
        <?php if ($c['capa']): ?>
        <div style="position:absolute;bottom:8px;left:12px;right:12px">
            <div style="font-weight:700;font-size:.9rem;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.6);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= htmlspecialchars($c['titulo']) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div style="padding:16px;display:flex;flex-direction:column;flex:1;gap:12px">
        <?php if (!$c['capa']): ?>
        <div>
            <div style="font-weight:700;font-size:.925rem;color:var(--azul-esc);line-height:1.3">
                <?= htmlspecialchars(truncaTexto($c['titulo'], 60)) ?>
            </div>
            <?php if ($c['cat_nome']): ?>
            <div style="font-size:.72rem;color:var(--c400);margin-top:3px"><?= htmlspecialchars($c['cat_nome']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Meta info -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:.78rem;color:var(--c500)">
            <div><i class="fa-solid fa-tag" style="width:14px;color:var(--c300)"></i> <?= htmlspecialchars($c['tipo']) ?></div>
            <div><i class="fa-solid fa-clock" style="width:14px;color:var(--c300)"></i> <?= $c['carga_horaria'] ?>h</div>
            <?php if ($c['data_inicio']): ?>
            <div><i class="fa-solid fa-calendar" style="width:14px;color:var(--c300)"></i> <?= fmtData($c['data_inicio']) ?></div>
            <?php endif; ?>
            <?php if ($c['instrutores']): ?>
            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($c['instrutores']) ?>">
                <i class="fa-solid fa-chalkboard-teacher" style="width:14px;color:var(--c300)"></i>
                <?= htmlspecialchars(truncaTexto($c['instrutores'], 18)) ?>
            </div>
            <?php endif; ?>
            <?php if ($c['vagas']): ?>
            <div>
                <i class="fa-solid fa-users" style="width:14px;color:var(--c300)"></i>
                <?= $c['total_mat'] ?>/<?= $c['vagas'] ?> vagas
            </div>
            <?php else: ?>
            <div><i class="fa-solid fa-users" style="width:14px;color:var(--c300)"></i> <?= $c['total_mat'] ?> inscritos</div>
            <?php endif; ?>
        </div>

        <?php if ($c['total_cert'] > 0): ?>
        <div style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--verde);background:#f0fdf4;padding:5px 10px;border-radius:6px">
            <i class="fa-solid fa-certificate"></i> <?= $c['total_cert'] ?> certificado<?= $c['total_cert']!=1?'s':'' ?> emitido<?= $c['total_cert']!=1?'s':'' ?>
        </div>
        <?php endif; ?>

        <!-- Ações -->
        <div style="display:flex;gap:6px;margin-top:auto;padding-top:4px;border-top:1px solid var(--c100)">
            <a href="/crmv/admin/cursos/form.php?id=<?= $c['curso_id'] ?>" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center">
                <i class="fa-solid fa-pen"></i> Editar
            </a>
            <a href="/crmv/admin/certificados/emitir.php?curso_id=<?= $c['curso_id'] ?>" class="btn btn-sm" style="flex:1;justify-content:center;background:#fefce8;color:#713f12;border-color:#fde68a">
                <i class="fa-solid fa-certificate"></i> Certificado
            </a>
            <div style="position:relative">
                <button onclick="toggleMenu(this)" class="btn btn-ghost btn-icone btn-sm">
                    <i class="fa-solid fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu" style="display:none;position:absolute;right:0;top:calc(100% + 4px);background:#fff;border:1px solid var(--c200);border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);min-width:160px;z-index:100;overflow:hidden">
                    <?php if ($c['status'] === 'RASCUNHO'): ?>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $c['curso_id'] ?>">
                        <input type="hidden" name="acao" value="publicar">
                        <button class="dropdown-item"><i class="fa-solid fa-globe" style="color:var(--verde)"></i> Publicar</button>
                    </form>
                    <?php elseif ($c['status'] === 'PUBLICADO'): ?>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $c['curso_id'] ?>">
                        <input type="hidden" name="acao" value="encerrar">
                        <button class="dropdown-item" data-confirma="Encerrar este curso?"><i class="fa-solid fa-stop-circle" style="color:var(--ouro)"></i> Encerrar</button>
                    </form>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $c['curso_id'] ?>">
                        <input type="hidden" name="acao" value="excluir">
                        <button class="dropdown-item" data-confirma="Excluir '<?= htmlspecialchars(addslashes($c['titulo'])) ?>'? Esta ação não pode ser desfeita.">
                            <i class="fa-solid fa-trash" style="color:var(--verm)"></i> Excluir
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Paginação -->
<?php if ($paginas > 1): ?>
<div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:24px">
    <div class="paginacao">
        <a href="?<?= http_build_query(['busca'=>$busca,'filtro'=>$filtro,'pag'=>max(1,$pag-1)]) ?>" class="pag-link <?= $pag<=1?'dis':'' ?>"><i class="fa-solid fa-chevron-left"></i></a>
        <?php for ($p=max(1,$pag-2);$p<=min($paginas,$pag+2);$p++): ?>
        <a href="?<?= http_build_query(['busca'=>$busca,'filtro'=>$filtro,'pag'=>$p]) ?>" class="pag-link <?= $p===$pag?'ativo':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a href="?<?= http_build_query(['busca'=>$busca,'filtro'=>$filtro,'pag'=>min($paginas,$pag+1)]) ?>" class="pag-link <?= $pag>=$paginas?'dis':'' ?>"><i class="fa-solid fa-chevron-right"></i></a>
    </div>
    <span style="font-size:.78rem;color:var(--c400)">Página <?= $pag ?> de <?= $paginas ?></span>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
.dropdown-item { display:flex;align-items:center;gap:9px;width:100%;padding:9px 14px;background:none;border:none;font-family:inherit;font-size:.85rem;color:var(--c700);cursor:pointer;text-align:left; }
.dropdown-item:hover { background:var(--c50); }
</style>
<script>
function toggleMenu(btn) {
    var menu = btn.nextElementSibling;
    document.querySelectorAll('.dropdown-menu').forEach(function(m){ if(m!==menu) m.style.display='none'; });
    menu.style.display = menu.style.display==='none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('[onclick="toggleMenu(this)"]')) {
        document.querySelectorAll('.dropdown-menu').forEach(function(m){ m.style.display='none'; });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
