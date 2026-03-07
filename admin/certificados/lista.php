<?php
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

$busca    = trim($_GET['busca']    ?? '');
$curso_id = (int)($_GET['curso_id'] ?? 0);
$pag      = max(1, (int)($_GET['pag'] ?? 1));
$porPag   = 20;
$offset   = ($pag - 1) * $porPag;

$where  = "WHERE cert.valido = 1";
$params = [];
if ($busca) {
    $where  .= " AND (u.nome_completo LIKE ? OR cert.codigo LIKE ? OR u.crmv_numero LIKE ?)";
    $params  = ["%$busca%", "%$busca%", "%$busca%"];
}
if ($curso_id) {
    $where  .= " AND c.curso_id = ?";
    $params[] = $curso_id;
}

$total   = dbQueryOne("SELECT COUNT(*) AS n FROM tbl_certificados cert INNER JOIN tbl_matriculas m ON cert.matricula_id=m.matricula_id INNER JOIN tbl_usuarios u ON m.usuario_id=u.usuario_id INNER JOIN tbl_cursos c ON m.curso_id=c.curso_id $where", $params)['n'];
$paginas = (int)ceil($total / $porPag);

$certs = dbQuery(
    "SELECT cert.cert_id, cert.codigo, cert.emitido_em,
            u.nome_completo, u.crmv_numero, u.crmv_uf, u.email,
            c.titulo AS curso_titulo, c.tipo, c.carga_horaria, c.curso_id
     FROM tbl_certificados cert
     INNER JOIN tbl_matriculas m ON cert.matricula_id = m.matricula_id
     INNER JOIN tbl_usuarios   u ON m.usuario_id      = u.usuario_id
     INNER JOIN tbl_cursos     c ON m.curso_id         = c.curso_id
     $where
     ORDER BY cert.emitido_em DESC
     LIMIT $porPag OFFSET $offset",
    $params
);

$cursos_lista = dbQuery("SELECT curso_id, titulo FROM tbl_cursos WHERE ativo=1 ORDER BY titulo");

$pageTitulo  = 'Certificados Emitidos';
$paginaAtiva = 'certificados';
require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="pg-header">
    <div class="pg-header-row">
        <div>
            <h1 class="pg-titulo">Certificados Emitidos</h1>
            <p class="pg-subtitulo"><?= number_format($total, 0, ',', '.') ?> certificado<?= $total!=1?'s':'' ?> no total</p>
        </div>
        <div class="pg-acoes">
            <a href="/crmv/admin/certificados/emitir.php" class="btn btn-primario">
                <i class="fa-solid fa-certificate"></i> Emitir Certificado
            </a>
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:14px 20px">
        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <div style="flex:1;min-width:220px;position:relative">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--c400);font-size:.8rem;pointer-events:none"></i>
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                    placeholder="Nome, código ou CRMV..."
                    style="width:100%;padding:9px 12px 9px 36px;border:1.5px solid var(--c300);border-radius:8px;font-size:.875rem;font-family:inherit;outline:none"
                    onfocus="this.style.borderColor='var(--azul-clr)'" onblur="this.style.borderColor='var(--c300)'">
            </div>
            <div class="form-group" style="margin:0;min-width:220px">
                <select name="curso_id" style="padding:9px 12px;border:1.5px solid var(--c300);border-radius:8px;font-size:.875rem;font-family:inherit;outline:none;background:#fff"
                    onfocus="this.style.borderColor='var(--azul-clr)'" onblur="this.style.borderColor='var(--c300)'">
                    <option value="">Todos os cursos</option>
                    <?php foreach ($cursos_lista as $cl): ?>
                    <option value="<?= $cl['curso_id'] ?>" <?= $curso_id===$cl['curso_id']?'selected':'' ?>>
                        <?= htmlspecialchars(truncaTexto($cl['titulo'], 45)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secundario btn-sm"><i class="fa-solid fa-search"></i> Filtrar</button>
            <?php if ($busca || $curso_id): ?>
            <a href="/crmv/admin/certificados/lista.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-xmark"></i> Limpar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- TABELA -->
<div class="card">
    <div class="tabela-wrapper">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Veterinário</th>
                    <th>CRMV</th>
                    <th>Curso</th>
                    <th>Carga</th>
                    <th>Emitido em</th>
                    <th style="width:130px">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($certs)): ?>
            <tr><td colspan="7">
                <div class="vazio" style="padding:50px">
                    <i class="fa-solid fa-certificate"></i>
                    <h3>Nenhum certificado encontrado</h3>
                    <p><?= $busca ? 'Tente outro termo.' : 'Nenhum certificado emitido ainda.' ?></p>
                    <a href="/crmv/admin/certificados/emitir.php" class="btn btn-primario btn-sm" style="margin-top:12px">
                        <i class="fa-solid fa-certificate"></i> Emitir agora
                    </a>
                </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($certs as $cert): ?>
            <tr>
                <td>
                    <code style="font-family:monospace;font-size:.85rem;font-weight:700;background:var(--azul-esc);color:#e6bb45;padding:3px 9px;border-radius:5px;letter-spacing:.05em">
                        <?= htmlspecialchars($cert['codigo']) ?>
                    </code>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="avatar-circulo" style="width:32px;height:32px;font-size:.8rem;flex-shrink:0"><?= primeiraLetra($cert['nome_completo']) ?></div>
                        <div>
                            <div style="font-weight:600;font-size:.875rem"><?= htmlspecialchars($cert['nome_completo']) ?></div>
                            <div style="font-size:.72rem;color:var(--c400)"><?= htmlspecialchars($cert['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <?php if ($cert['crmv_numero']): ?>
                    <span style="font-family:monospace;font-weight:700;font-size:.82rem"><?= htmlspecialchars($cert['crmv_numero']) ?>-<?= htmlspecialchars($cert['crmv_uf']) ?></span>
                    <?php else: ?>
                    <span style="color:var(--c300)">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-size:.85rem;font-weight:500"><?= htmlspecialchars(truncaTexto($cert['curso_titulo'], 42)) ?></div>
                    <div style="font-size:.72rem;color:var(--c400)"><?= htmlspecialchars($cert['tipo']) ?></div>
                </td>
                <td style="font-weight:700;font-size:.875rem"><?= $cert['carga_horaria'] ?>h</td>
                <td style="font-size:.82rem;color:var(--c500);white-space:nowrap"><?= fmtDataHora($cert['emitido_em']) ?></td>
                <td>
                    <div class="acoes">
                        <a href="/crmv/admin/certificados/ver.php?codigo=<?= urlencode($cert['codigo']) ?>"
                           class="btn btn-ghost btn-icone btn-sm" title="Visualizar">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                        <a href="/crmv/admin/certificados/imprimir.php?codigo=<?= urlencode($cert['codigo']) ?>"
                           class="btn btn-ghost btn-icone btn-sm" title="Imprimir / PDF" target="_blank">
                            <i class="fa-solid fa-print"></i>
                        </a>
                        <a href="/crmv/validar.php?codigo=<?= urlencode($cert['codigo']) ?>"
                           class="btn btn-ghost btn-icone btn-sm" title="Página pública de validação" target="_blank">
                            <i class="fa-solid fa-qrcode"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($paginas > 1): ?>
    <div class="card-footer" style="display:flex;align-items:center;justify-content:center;gap:8px">
        <div class="paginacao">
            <a href="?<?= http_build_query(['busca'=>$busca,'curso_id'=>$curso_id,'pag'=>max(1,$pag-1)]) ?>" class="pag-link <?= $pag<=1?'dis':'' ?>"><i class="fa-solid fa-chevron-left"></i></a>
            <?php for ($p=max(1,$pag-2);$p<=min($paginas,$pag+2);$p++): ?>
            <a href="?<?= http_build_query(['busca'=>$busca,'curso_id'=>$curso_id,'pag'=>$p]) ?>" class="pag-link <?= $p===$pag?'ativo':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="?<?= http_build_query(['busca'=>$busca,'curso_id'=>$curso_id,'pag'=>min($paginas,$pag+1)]) ?>" class="pag-link <?= $pag>=$paginas?'dis':'' ?>"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
        <span style="font-size:.78rem;color:var(--c400)">Página <?= $pag ?> de <?= $paginas ?></span>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
