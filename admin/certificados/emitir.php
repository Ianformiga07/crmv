<?php
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

$curso_id = (int)($_GET['curso_id'] ?? 0);
$curso    = $curso_id ? dbQueryOne("SELECT * FROM tbl_cursos WHERE curso_id = ?", [$curso_id]) : null;

// ── POST: emitir certificado(s) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao        = $_POST['acao'] ?? '';
    $carga_uso   = (float)str_replace(',', '.', $_POST['carga_horaria_cert'] ?? '0');
    $texto_extra = trim($_POST['texto_extra'] ?? '');

    if ($acao === 'emitir_um') {
        // Emitir para UM veterinário específico (mesmo sem matrícula)
        $vet_id   = (int)($_POST['vet_id'] ?? 0);
        $c_id     = (int)($_POST['curso_id'] ?? 0);

        $vet   = dbQueryOne("SELECT * FROM tbl_usuarios WHERE usuario_id = ? AND perfil_id = 2", [$vet_id]);
        $curso = dbQueryOne("SELECT * FROM tbl_cursos   WHERE curso_id = ?", [$c_id]);

        if (!$vet || !$curso) { flash('Veterinário ou curso não encontrado.', 'erro'); header('Location: /crmv/admin/certificados/emitir.php?curso_id=' . $c_id); exit; }

        // Verifica se já tem matrícula, senão cria
        $mat = dbQueryOne("SELECT * FROM tbl_matriculas WHERE usuario_id = ? AND curso_id = ?", [$vet_id, $c_id]);
        if (!$mat) {
            dbExecute("INSERT INTO tbl_matriculas (usuario_id, curso_id, status) VALUES (?,?,'CONCLUIDA')", [$vet_id, $c_id]);
            $mat_id = dbLastId();
        } else {
            $mat_id = $mat['matricula_id'];
        }

        // Gera ou reutiliza código
        $codigo = dbQueryOne("SELECT codigo FROM tbl_certificados WHERE matricula_id = ?", [$mat_id])['codigo'] ?? null;
        if (!$codigo) {
            do { $codigo = geraCodigoCert(); } while (dbQueryOne("SELECT cert_id FROM tbl_certificados WHERE codigo = ?", [$codigo]));
            dbExecute(
                "INSERT INTO tbl_certificados (matricula_id, codigo) VALUES (?,?)",
                [$mat_id, $codigo]
            );
            dbExecute(
                "UPDATE tbl_matriculas SET certificado_gerado=1, certificado_codigo=?, certificado_emitido_em=NOW(), status='CONCLUIDA' WHERE matricula_id=?",
                [$codigo, $mat_id]
            );
            registraLog($_SESSION['usr_id'], 'EMITIR_CERT', "Certificado emitido: $codigo para {$vet['nome_completo']}", 'tbl_certificados', $mat_id);
        }

        flash("Certificado emitido com sucesso! Código: $codigo", 'sucesso');
        header("Location: /crmv/admin/certificados/ver.php?codigo=$codigo");
        exit;

    } elseif ($acao === 'emitir_todos') {
        // Emitir para todos os matriculados no curso
        $c_id    = (int)($_POST['curso_id'] ?? 0);
        $mats    = dbQuery("SELECT * FROM tbl_matriculas WHERE curso_id = ? AND certificado_gerado = 0", [$c_id]);
        $emitidos = 0;
        foreach ($mats as $mat) {
            do { $codigo = geraCodigoCert(); } while (dbQueryOne("SELECT cert_id FROM tbl_certificados WHERE codigo = ?", [$codigo]));
            dbExecute("INSERT INTO tbl_certificados (matricula_id, codigo) VALUES (?,?)", [$mat['matricula_id'], $codigo]);
            dbExecute(
                "UPDATE tbl_matriculas SET certificado_gerado=1, certificado_codigo=?, certificado_emitido_em=NOW() WHERE matricula_id=?",
                [$codigo, $mat['matricula_id']]
            );
            $emitidos++;
        }
        registraLog($_SESSION['usr_id'], 'EMITIR_CERT_LOTE', "Emitidos $emitidos certificados para curso #$c_id", 'tbl_cursos', $c_id);
        flash("$emitidos certificado(s) emitido(s) com sucesso!", 'sucesso');
        header('Location: /crmv/admin/certificados/lista.php?curso_id=' . $c_id);
        exit;
    }
}

// ── Listagem de veterinários para emissão ────────────────────
$busca_vet = trim($_GET['busca_vet'] ?? '');
$params    = [];
$wh        = "WHERE u.perfil_id = 2 AND u.ativo = 1";
if ($busca_vet) {
    $wh .= " AND (u.nome_completo LIKE ? OR u.crmv_numero LIKE ? OR u.email LIKE ?)";
    $params = ["%$busca_vet%", "%$busca_vet%", "%$busca_vet%"];
}

$veterinarios = dbQuery(
    "SELECT u.usuario_id, u.nome_completo, u.email, u.crmv_numero, u.crmv_uf,
            m.matricula_id, m.status AS mat_status, m.certificado_gerado, m.certificado_codigo
     FROM tbl_usuarios u
     LEFT JOIN tbl_matriculas m ON m.usuario_id = u.usuario_id AND m.curso_id = ?
     $wh
     ORDER BY u.nome_completo
     LIMIT 50",
    array_merge([$curso_id ?: 0], $params)
);

// Todos os cursos para o seletor
$cursos_lista = dbQuery("SELECT curso_id, titulo, tipo, carga_horaria FROM tbl_cursos WHERE ativo=1 ORDER BY titulo");

$pageTitulo  = 'Emitir Certificado';
$paginaAtiva = 'certificados';
require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="pg-header">
    <div class="pg-header-row">
        <div>
            <h1 class="pg-titulo"><i class="fa-solid fa-certificate" style="color:var(--ouro)"></i> Emitir Certificado</h1>
            <p class="pg-subtitulo">O admin pode emitir certificados a qualquer momento, independente de matrícula</p>
        </div>
        <div class="pg-acoes">
            <a href="/crmv/admin/certificados/lista.php" class="btn btn-ghost"><i class="fa-solid fa-list"></i> Ver todos</a>
        </div>
    </div>
</div>

<!-- SELETOR DE CURSO -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><span class="card-titulo"><i class="fa-solid fa-graduation-cap"></i> Selecione o Curso</span></div>
    <div class="card-body">
        <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group" style="flex:1;min-width:280px;margin:0">
                <label>Curso / Palestra</label>
                <select name="curso_id" onchange="this.form.submit()">
                    <option value="">— Selecione um curso —</option>
                    <?php foreach ($cursos_lista as $cl): ?>
                    <option value="<?= $cl['curso_id'] ?>" <?= $curso_id===$cl['curso_id']?'selected':'' ?>>
                        [<?= $cl['tipo'] ?>] <?= htmlspecialchars($cl['titulo']) ?> (<?= $cl['carga_horaria'] ?>h)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($curso): ?>
            <div style="padding:8px 14px;background:var(--azul-esc);color:#fff;border-radius:8px;font-size:.85rem">
                <i class="fa-solid fa-clock"></i> <?= $curso['carga_horaria'] ?>h
                &nbsp;|&nbsp;
                <i class="fa-solid fa-tag"></i> <?= $curso['tipo'] ?>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($curso): ?>

<!-- EMITIR EM LOTE -->
<?php
$pendentes = dbQueryOne("SELECT COUNT(*) AS n FROM tbl_matriculas WHERE curso_id = ? AND certificado_gerado = 0", [$curso_id])['n'];
$jaEmitidos = dbQueryOne("SELECT COUNT(*) AS n FROM tbl_matriculas WHERE curso_id = ? AND certificado_gerado = 1", [$curso_id])['n'];
?>
<?php if ($pendentes > 0): ?>
<div class="card" style="margin-bottom:20px;border:2px solid var(--verde)">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap">
        <div>
            <div style="font-weight:700;font-size:1rem;color:var(--azul-esc)">
                <i class="fa-solid fa-users" style="color:var(--verde)"></i>
                <?= $pendentes ?> matriculado<?= $pendentes!=1?'s':'' ?> ainda sem certificado
            </div>
            <div style="font-size:.82rem;color:var(--c500);margin-top:3px">Emita para todos de uma vez</div>
        </div>
        <form method="POST">
            <input type="hidden" name="acao"     value="emitir_todos">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
            <button type="submit" class="btn btn-primario" data-confirma="Emitir certificados para todos os <?= $pendentes ?> matriculados?">
                <i class="fa-solid fa-certificate"></i> Emitir para todos
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- LISTA DE VETERINÁRIOS -->
<div class="card">
    <div class="card-header">
        <span class="card-titulo"><i class="fa-solid fa-user-doctor"></i> Veterinários</span>
        <form method="GET" style="display:flex;gap:6px">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
            <div style="position:relative">
                <i class="fa-solid fa-search" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--c400);font-size:.75rem;pointer-events:none"></i>
                <input type="text" name="busca_vet" value="<?= htmlspecialchars($busca_vet) ?>"
                    placeholder="Buscar veterinário..."
                    style="padding:7px 10px 7px 30px;border:1.5px solid var(--c300);border-radius:7px;font-size:.82rem;font-family:inherit;outline:none;width:220px"
                    onfocus="this.style.borderColor='var(--azul-clr)'" onblur="this.style.borderColor='var(--c300)'">
            </div>
            <button type="submit" class="btn btn-ghost btn-sm">Buscar</button>
        </form>
    </div>
    <div class="tabela-wrapper">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Veterinário</th>
                    <th>CRMV</th>
                    <th>Matrícula</th>
                    <th>Carga Horária Cert.</th>
                    <th>Certificado</th>
                    <th style="width:180px">Ação</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($veterinarios)): ?>
            <tr><td colspan="6"><div class="vazio" style="padding:30px"><i class="fa-solid fa-user-doctor"></i><p>Nenhum veterinário encontrado.</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($veterinarios as $v): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="avatar-circulo" style="width:32px;height:32px;font-size:.8rem;flex-shrink:0"><?= primeiraLetra($v['nome_completo']) ?></div>
                        <div>
                            <div style="font-weight:600;font-size:.875rem"><?= htmlspecialchars($v['nome_completo']) ?></div>
                            <div style="font-size:.72rem;color:var(--c400)"><?= htmlspecialchars($v['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <?php if ($v['crmv_numero']): ?>
                    <span style="font-family:monospace;font-weight:700;font-size:.82rem;background:var(--azul-esc);color:#fff;padding:2px 7px;border-radius:4px">
                        <?= htmlspecialchars($v['crmv_numero']) ?>-<?= htmlspecialchars($v['crmv_uf']) ?>
                    </span>
                    <?php else: ?>
                    <span style="color:var(--c300);font-size:.8rem">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($v['matricula_id']): ?>
                    <?= badgeStatus($v['mat_status']) ?>
                    <?php else: ?>
                    <span style="font-size:.78rem;color:var(--c400)">Sem matrícula</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span style="font-size:.85rem;font-weight:600"><?= $curso['carga_horaria'] ?>h</span>
                    <span style="font-size:.72rem;color:var(--c400)">(padrão do curso)</span>
                </td>
                <td>
                    <?php if ($v['certificado_gerado']): ?>
                    <div style="display:flex;align-items:center;gap:6px">
                        <span class="badge b-verde"><i class="fa-solid fa-check"></i> Emitido</span>
                        <a href="/crmv/admin/certificados/ver.php?codigo=<?= urlencode($v['certificado_codigo']) ?>"
                           class="btn btn-ghost btn-icone btn-sm" title="Visualizar">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </div>
                    <?php else: ?>
                    <span class="badge b-cinza">Não emitido</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($v['certificado_gerado']): ?>
                    <div style="display:flex;gap:5px">
                        <a href="/crmv/admin/certificados/ver.php?codigo=<?= urlencode($v['certificado_codigo']) ?>"
                           class="btn btn-ghost btn-sm"><i class="fa-solid fa-eye"></i> Ver</a>
                        <a href="/crmv/admin/certificados/imprimir.php?codigo=<?= urlencode($v['certificado_codigo']) ?>"
                           class="btn btn-sm" style="background:#fefce8;color:#713f12;border-color:#fde68a" target="_blank">
                           <i class="fa-solid fa-print"></i> PDF
                        </a>
                    </div>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="acao"     value="emitir_um">
                        <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                        <input type="hidden" name="vet_id"   value="<?= $v['usuario_id'] ?>">
                        <button type="submit" class="btn btn-sm" style="background:var(--ouro);color:#fff;border-color:var(--ouro)">
                            <i class="fa-solid fa-certificate"></i> Emitir
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($jaEmitidos > 0): ?>
    <div class="card-footer">
        <span style="font-size:.8rem;color:var(--verde)"><i class="fa-solid fa-certificate"></i> <?= $jaEmitidos ?> certificado<?= $jaEmitidos!=1?'s':'' ?> já emitido<?= $jaEmitidos!=1?'s':'' ?> para este curso</span>
        <a href="/crmv/admin/certificados/lista.php?curso_id=<?= $curso_id ?>" class="btn btn-ghost btn-sm" style="margin-left:auto">
            Ver todos <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="card">
    <div class="vazio" style="padding:60px">
        <i class="fa-solid fa-certificate"></i>
        <h3>Selecione um curso acima</h3>
        <p>Escolha o curso para ver os veterinários e emitir os certificados.</p>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
