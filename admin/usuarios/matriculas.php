<?php
/**
 * matriculas.php — Gerenciar matrículas de um veterinário (admin)
 * Arquivo: /crmv/admin/usuarios/matriculas.php
 *
 * Permite ao admin:
 *  - Matricular o veterinário em um curso (status ATIVA ou CONCLUIDA)
 *  - Alterar status de uma matrícula existente
 *  - Cancelar / excluir matrícula
 */
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

$usuario_id = (int)($_GET['id'] ?? 0);
if (!$usuario_id) { header('Location: /crmv/admin/usuarios/lista.php'); exit; }

$usuario = dbQueryOne(
    "SELECT usuario_id, nome_completo, email, crmv_numero, crmv_uf, ativo
     FROM tbl_usuarios WHERE usuario_id = ? AND perfil_id = 2",
    [$usuario_id]
);
if (!$usuario) {
    flash('Veterinário não encontrado.', 'erro');
    header('Location: /crmv/admin/usuarios/lista.php'); exit;
}

// ── POST: ações ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // ── Matricular em novo curso ─────────────────────────────
    if ($acao === 'matricular') {
        $curso_id  = (int)($_POST['curso_id']  ?? 0);
        $status_m  = $_POST['status_matricula'] ?? 'ATIVA';
        $nota      = trim($_POST['nota_final'] ?? '') !== '' ? (float)str_replace(',','.', $_POST['nota_final']) : null;

        if (!in_array($status_m, ['ATIVA','CONCLUIDA','CANCELADA','REPROVADO'])) {
            $status_m = 'ATIVA';
        }

        // Verifica se já existe matrícula ativa
        $jaExiste = dbQueryOne(
            "SELECT matricula_id, status FROM tbl_matriculas WHERE usuario_id = ? AND curso_id = ?",
            [$usuario_id, $curso_id]
        );

        if ($jaExiste) {
            flash('Este veterinário já possui matrícula neste curso. Altere o status abaixo se necessário.', 'aviso');
        } else {
            dbExecute(
                "INSERT INTO tbl_matriculas (usuario_id, curso_id, status, nota_final, matriculado_em)
                 VALUES (?, ?, ?, ?, NOW())",
                [$usuario_id, $curso_id, $status_m, $nota]
            );
            $nova_mat_id = dbLastId();

            // Se já entrou como CONCLUIDA, verifica se deve gerar certificado
            if ($status_m === 'CONCLUIDA' && !empty($_POST['emitir_cert'])) {
                do { $codigo = geraCodigoCert(); }
                while (dbQueryOne("SELECT cert_id FROM tbl_certificados WHERE codigo = ?", [$codigo]));

                dbExecute(
                    "INSERT INTO tbl_certificados (matricula_id, codigo, emitido_em, valido) VALUES (?,?,NOW(),1)",
                    [$nova_mat_id, $codigo]
                );
                dbExecute(
                    "UPDATE tbl_matriculas SET certificado_gerado=1, certificado_codigo=?, certificado_emitido_em=NOW() WHERE matricula_id=?",
                    [$codigo, $nova_mat_id]
                );
                registraLog($_SESSION['usr_id'], 'CERT_EMITIDO', "Cert emitido ao matricular: $codigo para {$usuario['nome_completo']}", 'tbl_certificados', $nova_mat_id);
            }

            registraLog($_SESSION['usr_id'], 'MATRICULAR', "Matriculou {$usuario['nome_completo']} no curso #$curso_id (status: $status_m)", 'tbl_matriculas', $nova_mat_id);
            flash('Matrícula realizada com sucesso!', 'sucesso');
        }
    }

    // ── Alterar status de matrícula existente ────────────────
    elseif ($acao === 'alterar_status') {
        $matricula_id = (int)($_POST['matricula_id'] ?? 0);
        $novo_status  = $_POST['novo_status'] ?? '';
        $nota         = trim($_POST['nota_final'] ?? '') !== '' ? (float)str_replace(',','.', $_POST['nota_final']) : null;

        if (!in_array($novo_status, ['ATIVA','CONCLUIDA','CANCELADA','REPROVADO'])) {
            flash('Status inválido.', 'erro');
        } else {
            dbExecute(
                "UPDATE tbl_matriculas SET status = ?, nota_final = ?, atualizado_em = NOW() WHERE matricula_id = ? AND usuario_id = ?",
                [$novo_status, $nota, $matricula_id, $usuario_id]
            );
            registraLog($_SESSION['usr_id'], 'ALTERAR_MATRICULA', "Alterou matrícula #$matricula_id para $novo_status", 'tbl_matriculas', $matricula_id);
            flash('Status da matrícula atualizado.', 'sucesso');
        }
    }

    // ── Cancelar / excluir matrícula ─────────────────────────
    elseif ($acao === 'cancelar') {
        $matricula_id = (int)($_POST['matricula_id'] ?? 0);

        // Não exclui se tiver certificado emitido
        $mat = dbQueryOne("SELECT certificado_gerado FROM tbl_matriculas WHERE matricula_id = ? AND usuario_id = ?", [$matricula_id, $usuario_id]);
        if ($mat && $mat['certificado_gerado']) {
            flash('Não é possível cancelar: esta matrícula já possui certificado emitido.', 'erro');
        } elseif ($mat) {
            dbExecute("UPDATE tbl_matriculas SET status = 'CANCELADA', atualizado_em = NOW() WHERE matricula_id = ?", [$matricula_id]);
            registraLog($_SESSION['usr_id'], 'CANCELAR_MATRICULA', "Cancelou matrícula #$matricula_id de {$usuario['nome_completo']}", 'tbl_matriculas', $matricula_id);
            flash('Matrícula cancelada.', 'aviso');
        }
    }

    header('Location: /crmv/admin/usuarios/matriculas.php?id=' . $usuario_id);
    exit;
}

// ── Dados para exibição ───────────────────────────────────────
$matriculas = dbQuery(
    "SELECT m.matricula_id, m.status, m.nota_final, m.progresso_ead,
            m.certificado_gerado, m.certificado_codigo, m.certificado_emitido_em,
            m.matriculado_em, m.atualizado_em,
            c.curso_id, c.titulo, c.tipo, c.modalidade, c.carga_horaria,
            c.data_inicio, c.data_fim, c.status AS curso_status
     FROM tbl_matriculas m
     INNER JOIN tbl_cursos c ON m.curso_id = c.curso_id
     WHERE m.usuario_id = ?
     ORDER BY m.matriculado_em DESC",
    [$usuario_id]
);

// Cursos disponíveis para nova matrícula (exclui os que já tem)
$ids_matriculados = array_column($matriculas, 'curso_id');
$placeholders     = $ids_matriculados ? implode(',', array_fill(0, count($ids_matriculados), '?')) : '0';

$cursos_disponiveis = dbQuery(
    "SELECT curso_id, titulo, tipo, modalidade, carga_horaria, status
     FROM tbl_cursos
     WHERE ativo = 1 AND curso_id NOT IN ($placeholders)
     ORDER BY titulo",
    $ids_matriculados ?: []
);

$pageTitulo  = 'Matrículas — ' . $usuario['nome_completo'];
$paginaAtiva = 'usuarios';
require_once __DIR__ . '/../../includes/layout.php';
?>

<!-- ── Cabeçalho ─────────────────────────────────────────────── -->
<div class="pg-header">
    <div class="pg-header-row">
        <div style="display:flex;align-items:center;gap:14px">
            <div class="avatar-circulo" style="width:48px;height:48px;font-size:1.2rem;flex-shrink:0">
                <?= primeiraLetra($usuario['nome_completo']) ?>
            </div>
            <div>
                <h1 class="pg-titulo" style="margin:0">Matrículas</h1>
                <p class="pg-subtitulo" style="margin:0">
                    <?= htmlspecialchars($usuario['nome_completo']) ?>
                    <?php if ($usuario['crmv_numero']): ?>
                    <span style="font-family:monospace;font-weight:700;background:var(--azul-esc);color:#fff;padding:1px 7px;border-radius:4px;font-size:.75rem;margin-left:6px">
                        CRMV <?= htmlspecialchars($usuario['crmv_numero']) ?>-<?= htmlspecialchars($usuario['crmv_uf']) ?>
                    </span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="pg-acoes">
            <a href="/crmv/admin/usuarios/ver.php?id=<?= $usuario_id ?>" class="btn btn-ghost">
                <i class="fa-solid fa-arrow-left"></i> Voltar ao perfil
            </a>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

    <!-- ── COLUNA PRINCIPAL: lista de matrículas ─────────────── -->
    <div style="display:flex;flex-direction:column;gap:16px">

        <?php if (empty($matriculas)): ?>
        <div class="card">
            <div class="vazio" style="padding:60px">
                <i class="fa-solid fa-graduation-cap"></i>
                <h3>Nenhuma matrícula</h3>
                <p>Este veterinário ainda não está inscrito em nenhum curso.<br>
                   Use o formulário ao lado para matriculá-lo.</p>
            </div>
        </div>
        <?php else: ?>

        <?php foreach ($matriculas as $mat): ?>
        <div class="card" style="overflow:hidden">
            <div style="display:flex;align-items:stretch;gap:0">

                <!-- Faixa de status -->
                <div style="width:5px;flex-shrink:0;background:<?= match($mat['status']) {
                    'ATIVA'     => 'var(--azul-clr)',
                    'CONCLUIDA' => 'var(--verde)',
                    'CANCELADA','REPROVADO' => 'var(--verm)',
                    default     => 'var(--c300)'
                } ?>"></div>

                <div style="flex:1;padding:16px 18px;min-width:0">
                    <!-- Título + badges -->
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:700;font-size:.9rem;color:var(--azul-esc);line-height:1.3;margin-bottom:5px">
                                <?= htmlspecialchars($mat['titulo']) ?>
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;font-size:.76rem;color:var(--c500)">
                                <span><i class="fa-solid fa-tag" style="color:var(--c300)"></i> <?= $mat['tipo'] ?></span>
                                <span><i class="fa-solid fa-clock" style="color:var(--c300)"></i> <?= $mat['carga_horaria'] ?>h</span>
                                <?= badgeModalidade($mat['modalidade']) ?>
                                <span><i class="fa-solid fa-calendar" style="color:var(--c300)"></i> Matriculado em <?= fmtData($mat['matriculado_em']) ?></span>
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
                            <?= badgeStatus($mat['status']) ?>
                            <?php if ($mat['certificado_gerado']): ?>
                            <span class="badge b-ouro" style="font-size:.66rem">
                                <i class="fa-solid fa-certificate"></i> Cert. emitido
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Linha de ações -->
                    <div style="display:flex;align-items:center;gap:10px;margin-top:12px;
                                padding-top:10px;border-top:1px solid var(--c100);flex-wrap:wrap">

                        <?php if ($mat['certificado_gerado']): ?>
                        <!-- Tem certificado: mostrar link -->
                        <a href="/crmv/admin/certificados/ver.php?codigo=<?= urlencode($mat['certificado_codigo']) ?>"
                           class="btn btn-ghost btn-sm">
                            <i class="fa-solid fa-certificate" style="color:var(--ouro)"></i> Ver certificado
                        </a>
                        <a href="/crmv/admin/certificados/imprimir.php?codigo=<?= urlencode($mat['certificado_codigo']) ?>"
                           target="_blank" class="btn btn-ghost btn-sm">
                            <i class="fa-solid fa-print"></i> PDF
                        </a>
                        <?php elseif ($mat['status'] === 'CONCLUIDA'): ?>
                        <!-- Concluído sem cert: botão de emitir -->
                        <a href="/crmv/admin/certificados/emitir.php?curso_id=<?= $mat['curso_id'] ?>"
                           class="btn btn-sm" style="background:var(--ouro);color:#fff;border-color:var(--ouro)">
                            <i class="fa-solid fa-certificate"></i> Emitir certificado
                        </a>
                        <?php endif; ?>

                        <!-- Alterar status (form inline) -->
                        <?php if (!$mat['certificado_gerado']): ?>
                        <form method="POST" style="display:flex;align-items:center;gap:6px;margin-left:auto">
                            <input type="hidden" name="acao"         value="alterar_status">
                            <input type="hidden" name="matricula_id" value="<?= $mat['matricula_id'] ?>">
                            <select name="novo_status"
                                style="padding:5px 8px;border:1.5px solid var(--c300);border-radius:6px;
                                       font-size:.78rem;font-family:inherit;height:30px;outline:none;cursor:pointer">
                                <?php foreach (['ATIVA'=>'Ativa','CONCLUIDA'=>'Concluída','CANCELADA'=>'Cancelada','REPROVADO'=>'Reprovado'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $mat['status']===$v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="nota_final" step="0.1" min="0" max="10"
                                value="<?= $mat['nota_final'] ?? '' ?>"
                                placeholder="Nota"
                                style="width:70px;padding:5px 7px;border:1.5px solid var(--c300);
                                       border-radius:6px;font-size:.78rem;font-family:inherit;height:30px;outline:none">
                            <button type="submit" class="btn btn-secundario btn-sm" style="height:30px">
                                <i class="fa-solid fa-floppy-disk"></i> Salvar
                            </button>
                        </form>
                        <?php else: ?>
                        <!-- Tem cert: status não pode ser alterado, só mostra info -->
                        <div style="margin-left:auto;font-size:.74rem;color:var(--c400)">
                            <i class="fa-solid fa-lock"></i>
                            Status bloqueado (certificado emitido)
                        </div>
                        <?php endif; ?>

                        <!-- Cancelar -->
                        <?php if (!$mat['certificado_gerado'] && $mat['status'] !== 'CANCELADA'): ?>
                        <form method="POST">
                            <input type="hidden" name="acao"         value="cancelar">
                            <input type="hidden" name="matricula_id" value="<?= $mat['matricula_id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm"
                                data-confirma="Cancelar a matrícula de <?= htmlspecialchars(addslashes($usuario['nome_completo'])) ?> em '<?= htmlspecialchars(addslashes($mat['titulo'])) ?>'?">
                                <i class="fa-solid fa-ban" style="color:var(--verm)"></i> Cancelar
                            </button>
                        </form>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <!-- ── COLUNA LATERAL: nova matrícula ────────────────────── -->
    <div style="display:flex;flex-direction:column;gap:16px">

        <div class="card" style="border:2px solid var(--azul-clr)">
            <div class="card-header">
                <span class="card-titulo">
                    <i class="fa-solid fa-user-plus" style="color:var(--azul-clr)"></i>
                    Matricular em Curso
                </span>
            </div>
            <div class="card-body">

                <?php if (empty($cursos_disponiveis)): ?>
                <div style="text-align:center;padding:20px;color:var(--c400);font-size:.85rem">
                    <i class="fa-solid fa-check-circle" style="font-size:2rem;color:var(--verde);margin-bottom:8px;display:block"></i>
                    Este veterinário já está matriculado em todos os cursos disponíveis.
                </div>
                <?php else: ?>
                <form method="POST" style="display:flex;flex-direction:column;gap:14px">
                    <input type="hidden" name="acao" value="matricular">

                    <div class="form-group" style="margin:0">
                        <label class="req" style="font-size:.8rem">Curso</label>
                        <select name="curso_id" required
                            style="width:100%;padding:9px 10px;border:1.5px solid var(--c300);
                                   border-radius:7px;font-family:inherit;font-size:.85rem;
                                   background:#fff;outline:none;cursor:pointer"
                            onfocus="this.style.borderColor='var(--azul-clr)'"
                            onblur="this.style.borderColor='var(--c300)'">
                            <option value="">— Selecione o curso —</option>
                            <?php foreach ($cursos_disponiveis as $cur): ?>
                            <option value="<?= $cur['curso_id'] ?>">
                                [<?= $cur['tipo'] ?>] <?= htmlspecialchars(truncaTexto($cur['titulo'], 38)) ?>
                                (<?= $cur['carga_horaria'] ?>h)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin:0">
                        <label style="font-size:.8rem">Status da Matrícula</label>
                        <select name="status_matricula"
                            style="width:100%;padding:9px 10px;border:1.5px solid var(--c300);
                                   border-radius:7px;font-family:inherit;font-size:.85rem;
                                   background:#fff;outline:none;cursor:pointer"
                            onchange="toggleCamposConclusao(this.value)"
                            onfocus="this.style.borderColor='var(--azul-clr)'"
                            onblur="this.style.borderColor='var(--c300)'">
                            <option value="ATIVA">Ativa (em andamento)</option>
                            <option value="CONCLUIDA">Concluída</option>
                            <option value="CANCELADA">Cancelada</option>
                        </select>
                        <span class="dica" style="font-size:.7rem;color:var(--c400)">
                            "Ativa" aparece em "Meus Cursos" do aluno. "Concluída" libera emissão de certificado.
                        </span>
                    </div>

                    <!-- Campos extras se CONCLUIDA -->
                    <div id="campo-conclusao" style="display:none;flex-direction:column;gap:10px;
                                padding:12px;background:var(--c50);border-radius:8px;border:1px solid var(--c200)">
                        <div class="form-group" style="margin:0">
                            <label style="font-size:.8rem">Nota Final (opcional)</label>
                            <input type="number" name="nota_final" step="0.1" min="0" max="10"
                                placeholder="Ex: 8.5"
                                style="width:100%;padding:8px 10px;border:1.5px solid var(--c300);
                                       border-radius:6px;font-family:inherit;font-size:.85rem;outline:none">
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.83rem;color:var(--c600)">
                            <input type="checkbox" name="emitir_cert" value="1"
                                style="width:15px;height:15px;accent-color:var(--azul-clr);cursor:pointer">
                            Emitir certificado automaticamente
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primario" style="justify-content:center;width:100%">
                        <i class="fa-solid fa-user-plus"></i> Matricular
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Resumo -->
        <?php
        $totalMat  = count($matriculas);
        $concluidos = count(array_filter($matriculas, fn($m) => $m['status'] === 'CONCLUIDA'));
        $ativos     = count(array_filter($matriculas, fn($m) => $m['status'] === 'ATIVA'));
        $certs      = count(array_filter($matriculas, fn($m) => $m['certificado_gerado']));
        ?>
        <div class="card">
            <div class="card-header">
                <span class="card-titulo"><i class="fa-solid fa-chart-pie"></i> Resumo</span>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px;font-size:.85rem">
                <?php foreach ([
                    ['Total de matrículas',   $totalMat,  'var(--azul-clr)', 'fa-list'],
                    ['Em andamento',           $ativos,    'var(--azul-clr)', 'fa-spinner'],
                    ['Concluídos',             $concluidos,'var(--verde)',    'fa-check'],
                    ['Certificados emitidos',  $certs,     'var(--ouro)',     'fa-certificate'],
                ] as [$rot, $val, $cor, $ico]): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;
                            padding:6px 0;border-bottom:1px solid var(--c100)">
                    <span style="color:var(--c500)">
                        <i class="fa-solid <?= $ico ?>" style="color:<?= $cor ?>;width:14px"></i>
                        <?= $rot ?>
                    </span>
                    <strong style="color:var(--c700)"><?= $val ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<script>
function toggleCamposConclusao(val) {
    var el = document.getElementById('campo-conclusao');
    if (val === 'CONCLUIDA') {
        el.style.display = 'flex';
    } else {
        el.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
