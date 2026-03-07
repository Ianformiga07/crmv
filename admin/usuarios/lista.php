<?php
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

// ── Ações POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($acao === 'ativar') {
            dbExecute("UPDATE tbl_usuarios SET ativo = 1 WHERE usuario_id = ?", [$id]);
            flash('Veterinário ativado com sucesso.', 'sucesso');
        } elseif ($acao === 'inativar') {
            dbExecute("UPDATE tbl_usuarios SET ativo = 0 WHERE usuario_id = ?", [$id]);
            flash('Veterinário inativado.', 'aviso');
        } elseif ($acao === 'excluir') {
            $temMat = dbQueryOne("SELECT COUNT(*) AS n FROM tbl_matriculas WHERE usuario_id = ?", [$id])['n'];
            if ($temMat > 0) {
                flash('Não é possível excluir: veterinário possui matrículas vinculadas.', 'erro');
            } else {
                dbExecute("DELETE FROM tbl_usuarios WHERE usuario_id = ? AND perfil_id = 2", [$id]);
                flash('Veterinário excluído com sucesso.', 'sucesso');
            }
        }
    }
    header('Location: /crmv/admin/usuarios/lista.php?' . http_build_query([
        'busca' => $_GET['busca'] ?? '', 'filtro' => $_GET['filtro'] ?? 'todos', 'pag' => $_GET['pag'] ?? 1
    ]));
    exit;
}

// ── Parâmetros ───────────────────────────────────────────────
$busca   = trim($_GET['busca']  ?? '');
$filtro  = $_GET['filtro'] ?? 'todos';
$pag     = max(1, (int)($_GET['pag'] ?? 1));
$porPag  = 15;
$offset  = ($pag - 1) * $porPag;

$where  = "WHERE u.perfil_id = 2";
$params = [];
if ($busca !== '') {
    $where  .= " AND (u.nome_completo LIKE ? OR u.email LIKE ? OR u.crmv_numero LIKE ? OR u.cpf LIKE ?)";
    $params  = ["%$busca%", "%$busca%", "%$busca%", "%$busca%"];
}
if ($filtro === 'ativo')   $where .= " AND u.ativo = 1";
if ($filtro === 'inativo') $where .= " AND u.ativo = 0";

$total   = dbQueryOne("SELECT COUNT(*) AS n FROM tbl_usuarios u $where", $params)['n'];
$paginas = (int)ceil($total / $porPag);

$usuarios = dbQuery(
    "SELECT u.usuario_id, u.nome_completo, u.email, u.cpf, u.celular,
            u.crmv_numero, u.crmv_uf, u.especialidade, u.cidade, u.uf,
            u.ativo, u.criado_em, u.ultimo_acesso,
            (SELECT COUNT(*) FROM tbl_matriculas m WHERE m.usuario_id = u.usuario_id) AS total_mat,
            (SELECT COUNT(*) FROM tbl_matriculas m WHERE m.usuario_id = u.usuario_id AND m.certificado_gerado = 1) AS total_cert
     FROM tbl_usuarios u $where
     ORDER BY u.nome_completo ASC
     LIMIT $porPag OFFSET $offset",
    $params
);

$pageTitulo  = 'Veterinários';
$paginaAtiva = 'usuarios';
require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="pg-header">
    <div class="pg-header-row">
        <div>
            <h1 class="pg-titulo">Veterinários</h1>
            <p class="pg-subtitulo"><?= number_format($total, 0, ',', '.') ?> cadastrado<?= $total != 1 ? 's' : '' ?></p>
        </div>
        <div class="pg-acoes">
            <a href="/crmv/admin/usuarios/form.php" class="btn btn-primario">
                <i class="fa-solid fa-user-plus"></i> Novo Veterinário
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
                    placeholder="Nome, e-mail, CRMV ou CPF..."
                    style="width:100%;padding:9px 12px 9px 36px;border:1.5px solid var(--c300);border-radius:8px;font-size:.875rem;font-family:inherit;outline:none;background:var(--c50,#f9fafb)"
                    onfocus="this.style.borderColor='var(--azul-clr)'"
                    onblur="this.style.borderColor='var(--c300)'">
            </div>
            <div style="display:flex;gap:4px">
                <?php foreach (['todos' => 'Todos', 'ativo' => 'Ativos', 'inativo' => 'Inativos'] as $v => $l): ?>
                <a href="?busca=<?= urlencode($busca) ?>&filtro=<?= $v ?>"
                   class="btn btn-sm <?= $filtro === $v ? 'btn-primario' : 'btn-ghost' ?>"><?= $l ?></a>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-secundario btn-sm"><i class="fa-solid fa-search"></i> Buscar</button>
            <?php if ($busca || $filtro !== 'todos'): ?>
            <a href="/crmv/admin/usuarios/lista.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-xmark"></i> Limpar</a>
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
                    <th>Veterinário</th>
                    <th>CRMV</th>
                    <th>Contato</th>
                    <th>Especialidade</th>
                    <th>Localização</th>
                    <th>Matrículas</th>
                    <th>Status</th>
                    <th style="width:110px">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($usuarios)): ?>
                <tr><td colspan="8">
                    <div class="vazio">
                        <i class="fa-solid fa-user-doctor"></i>
                        <h3>Nenhum veterinário encontrado</h3>
                        <p><?= $busca ? 'Tente outro termo de busca.' : 'Nenhum veterinário cadastrado ainda.' ?></p>
                        <?php if (!$busca): ?>
                        <a href="/crmv/admin/usuarios/form.php" class="btn btn-primario btn-sm" style="margin-top:12px">
                            <i class="fa-solid fa-user-plus"></i> Cadastrar agora
                        </a>
                        <?php endif; ?>
                    </div>
                </td></tr>
            <?php else: ?>
            <?php foreach ($usuarios as $u): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:11px">
                        <div class="avatar-circulo"><?= primeiraLetra($u['nome_completo']) ?></div>
                        <div>
                            <a href="/crmv/admin/usuarios/ver.php?id=<?= $u['usuario_id'] ?>"
                               style="font-weight:600;font-size:.875rem;color:var(--azul-esc);text-decoration:none"
                               onmouseover="this.style.textDecoration='underline'"
                               onmouseout="this.style.textDecoration='none'">
                                <?= htmlspecialchars($u['nome_completo']) ?>
                            </a>
                            <div style="font-size:.72rem;color:var(--c400);margin-top:1px"><?= htmlspecialchars($u['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <?php if ($u['crmv_numero']): ?>
                    <span style="font-family:monospace;font-weight:700;font-size:.85rem;background:var(--azul-esc);color:#fff;padding:3px 8px;border-radius:5px">
                        <?= htmlspecialchars($u['crmv_numero']) ?>-<?= htmlspecialchars($u['crmv_uf']) ?>
                    </span>
                    <?php else: ?>
                    <span style="color:var(--c300);font-size:.8rem">Não informado</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:.82rem">
                    <?php if ($u['celular']): ?>
                    <div><i class="fa-solid fa-mobile-alt" style="color:var(--c400);width:13px"></i> <?= htmlspecialchars($u['celular']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:.82rem;color:var(--c500)"><?= htmlspecialchars($u['especialidade'] ?: '—') ?></td>
                <td style="font-size:.82rem;color:var(--c500)">
                    <?= $u['cidade'] ? htmlspecialchars($u['cidade']) . '/' . htmlspecialchars($u['uf']) : '—' ?>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="font-weight:700;font-size:.9rem"><?= $u['total_mat'] ?></span>
                        <?php if ($u['total_cert'] > 0): ?>
                        <span style="font-size:.72rem;color:var(--verde);background:var(--verde-bg,#f0fdf4);padding:2px 6px;border-radius:10px">
                            <i class="fa-solid fa-certificate"></i> <?= $u['total_cert'] ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php if ($u['ativo']): ?>
                    <span class="badge b-verde"><i class="fa-solid fa-circle" style="font-size:.45rem;vertical-align:middle"></i> Ativo</span>
                    <?php else: ?>
                    <span class="badge b-verm">Inativo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="acoes">
                        <a href="/crmv/admin/usuarios/ver.php?id=<?= $u['usuario_id'] ?>"
                           class="btn btn-ghost btn-icone btn-sm" title="Visualizar">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                        <a href="/crmv/admin/usuarios/form.php?id=<?= $u['usuario_id'] ?>"
                           class="btn btn-ghost btn-icone btn-sm" title="Editar">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="id"   value="<?= $u['usuario_id'] ?>">
                            <input type="hidden" name="acao" value="<?= $u['ativo'] ? 'inativar' : 'ativar' ?>">
                            <button class="btn btn-ghost btn-icone btn-sm"
                                title="<?= $u['ativo'] ? 'Inativar' : 'Ativar' ?>"
                                data-confirma="<?= $u['ativo'] ? 'Inativar' : 'Ativar' ?> <?= htmlspecialchars(addslashes($u['nome_completo'])) ?>?">
                                <i class="fa-solid <?= $u['ativo'] ? 'fa-ban' : 'fa-circle-check' ?>"
                                   style="color:<?= $u['ativo'] ? 'var(--ouro)' : 'var(--verde)' ?>"></i>
                            </button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="id"   value="<?= $u['usuario_id'] ?>">
                            <input type="hidden" name="acao" value="excluir">
                            <button class="btn btn-ghost btn-icone btn-sm" title="Excluir"
                                data-confirma="Excluir permanentemente <?= htmlspecialchars(addslashes($u['nome_completo'])) ?>? Esta ação não pode ser desfeita.">
                                <i class="fa-solid fa-trash" style="color:var(--verm)"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($paginas > 1): ?>
    <div class="card-footer" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:14px 20px">
        <div class="paginacao">
            <a href="?<?= http_build_query(['busca'=>$busca,'filtro'=>$filtro,'pag'=>max(1,$pag-1)]) ?>"
               class="pag-link <?= $pag <= 1 ? 'dis' : '' ?>"><i class="fa-solid fa-chevron-left"></i></a>
            <?php for ($p = max(1,$pag-2); $p <= min($paginas,$pag+2); $p++): ?>
            <a href="?<?= http_build_query(['busca'=>$busca,'filtro'=>$filtro,'pag'=>$p]) ?>"
               class="pag-link <?= $p===$pag ? 'ativo' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="?<?= http_build_query(['busca'=>$busca,'filtro'=>$filtro,'pag'=>min($paginas,$pag+1)]) ?>"
               class="pag-link <?= $pag >= $paginas ? 'dis' : '' ?>"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
        <span style="font-size:.78rem;color:var(--c400)">Página <?= $pag ?> de <?= $paginas ?> — <?= number_format($total,0,',','.') ?> registros</span>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
