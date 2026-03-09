<?php
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

$id = (int)($_GET['id'] ?? 0);
$u  = dbQueryOne("SELECT * FROM tbl_usuarios WHERE usuario_id = ? AND perfil_id = 2", [$id]);

if (!$u) {
    flash('Veterinário não encontrado.', 'erro');
    header('Location: /crmv/admin/usuarios/lista.php');
    exit;
}

// Matrículas do veterinário
$matriculas = dbQuery(
    "SELECT m.matricula_id, m.status, m.nota_final, m.certificado_gerado,
            m.certificado_codigo, m.certificado_emitido_em, m.matriculado_em,
            c.titulo, c.tipo, c.modalidade, c.data_inicio, c.carga_horaria,
            cat.nome AS cat_nome
     FROM tbl_matriculas m
     INNER JOIN tbl_cursos c ON m.curso_id = c.curso_id
     LEFT JOIN tbl_categorias cat ON c.categoria_id = cat.categoria_id
     WHERE m.usuario_id = ?
     ORDER BY m.matriculado_em DESC",
    [$id]
);

$pageTitulo  = 'Perfil do Veterinário';
$paginaAtiva = 'usuarios';
require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="pg-header">
    <div class="pg-header-row">
        <div style="display:flex;align-items:center;gap:16px">
            <div class="avatar-circulo" style="width:52px;height:52px;font-size:1.3rem">
                <?= primeiraLetra($u['nome_completo']) ?>
            </div>
            <div>
                <h1 class="pg-titulo" style="margin:0"><?= htmlspecialchars($u['nome_completo']) ?></h1>
                <p class="pg-subtitulo" style="margin:0">
                    <?php if ($u['crmv_numero']): ?>
                    <span style="font-family:monospace;font-weight:700;background:var(--azul-esc);color:#fff;padding:2px 8px;border-radius:4px;font-size:.8rem;margin-right:8px">
                        CRMV <?= htmlspecialchars($u['crmv_numero']) ?>-<?= htmlspecialchars($u['crmv_uf']) ?>
                    </span>
                    <?php endif; ?>
                    <?= $u['ativo'] ? '<span class="badge b-verde">Ativo</span>' : '<span class="badge b-verm">Inativo</span>' ?>
                </p>
            </div>
        </div>
        <div class="pg-acoes">
            <a href="/crmv/admin/usuarios/matriculas.php?id=<?= $id ?>" class="btn btn-primario">
                <i class="fa-solid fa-graduation-cap"></i> Matrículas
            </a>
            <a href="/crmv/admin/usuarios/form.php?id=<?= $id ?>" class="btn btn-secundario">
                <i class="fa-solid fa-pen"></i> Editar
            </a>
            <a href="/crmv/admin/usuarios/lista.php" class="btn btn-ghost">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
</div>

<!-- STATS RÁPIDOS -->
<?php
$totMat  = count($matriculas);
$totCert = array_sum(array_column($matriculas, 'certificado_gerado'));
$totConc = count(array_filter($matriculas, fn($m) => $m['status'] === 'CONCLUIDA'));
$totAtiv = count(array_filter($matriculas, fn($m) => $m['status'] === 'ATIVA'));
?>
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icone si-azul"><i class="fa-solid fa-list-check"></i></div>
        <div class="stat-info"><div class="stat-valor"><?= $totMat ?></div><div class="stat-rotulo">Matrículas</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icone si-verde"><i class="fa-solid fa-graduation-cap"></i></div>
        <div class="stat-info"><div class="stat-valor"><?= $totConc ?></div><div class="stat-rotulo">Concluídos</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icone si-ouro"><i class="fa-solid fa-certificate"></i></div>
        <div class="stat-info"><div class="stat-valor"><?= $totCert ?></div><div class="stat-rotulo">Certificados</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icone" style="background:var(--azul-bg,#eff6ff);color:var(--azul-clr)"><i class="fa-solid fa-spinner"></i></div>
        <div class="stat-info"><div class="stat-valor"><?= $totAtiv ?></div><div class="stat-rotulo">Em andamento</div></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start">

    <!-- COLUNA DADOS -->
    <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Dados pessoais -->
        <div class="card">
            <div class="card-header"><span class="card-titulo"><i class="fa-solid fa-user"></i> Dados Pessoais</span></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;font-size:.875rem">
                <?php
                $info = [
                    ['fa-id-card',        'CPF',              $u['cpf'] ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $u['cpf']) : '—'],
                    ['fa-id-badge',       'RG',               $u['rg'] ?: '—'],
                    ['fa-calendar',       'Nascimento',        $u['data_nascimento'] ? fmtData($u['data_nascimento']) : '—'],
                    ['fa-venus-mars',     'Sexo',             match($u['sexo']??'') {'M'=>'Masculino','F'=>'Feminino','O'=>'Outro',default=>'—'}],
                    ['fa-envelope',       'E-mail',           $u['email']],
                    ['fa-phone',          'Telefone',         $u['telefone'] ? preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $u['telefone']) : '—'],
                    ['fa-mobile-alt',     'Celular',          $u['celular'] ? preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $u['celular']) : '—'],
                ];
                foreach ($info as [$ico, $rot, $val]):
                ?>
                <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--c100)">
                    <span style="color:var(--c400)"><i class="fa-solid <?= $ico ?>" style="width:16px"></i> <?= $rot ?></span>
                    <span style="color:var(--c700);font-weight:500"><?= htmlspecialchars($val) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Dados profissionais -->
        <div class="card">
            <div class="card-header"><span class="card-titulo"><i class="fa-solid fa-briefcase" style="color:var(--ouro)"></i> Dados Profissionais</span></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;font-size:.875rem">
                <div style="padding:8px;background:var(--azul-esc);border-radius:8px;text-align:center">
                    <div style="font-size:.65rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.08em">CRMV</div>
                    <div style="font-family:monospace;font-size:1.3rem;font-weight:700;color:#fff;margin-top:2px">
                        <?= $u['crmv_numero'] ? htmlspecialchars($u['crmv_numero']) . '-' . htmlspecialchars($u['crmv_uf']) : '—' ?>
                    </div>
                </div>
                <?php if ($u['especialidade']): ?>
                <div style="padding:6px 0;border-bottom:1px solid var(--c100)">
                    <div style="font-size:.7rem;color:var(--c400);text-transform:uppercase">Especialidade</div>
                    <div style="margin-top:2px;font-weight:500"><?= htmlspecialchars($u['especialidade']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($u['instituicao']): ?>
                <div style="padding:6px 0">
                    <div style="font-size:.7rem;color:var(--c400);text-transform:uppercase">Instituição</div>
                    <div style="margin-top:2px;font-weight:500"><?= htmlspecialchars($u['instituicao']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Endereço -->
        <?php if ($u['logradouro'] || $u['cidade']): ?>
        <div class="card">
            <div class="card-header"><span class="card-titulo"><i class="fa-solid fa-location-dot"></i> Endereço</span></div>
            <div class="card-body" style="font-size:.875rem;color:var(--c600);line-height:1.6">
                <?php if ($u['logradouro']): ?>
                <?= htmlspecialchars($u['logradouro']) ?><?= $u['numero'] ? ', ' . $u['numero'] : '' ?>
                <?php if ($u['complemento']): ?> — <?= htmlspecialchars($u['complemento']) ?><?php endif; ?><br>
                <?php endif; ?>
                <?php if ($u['bairro']): ?><?= htmlspecialchars($u['bairro']) ?><br><?php endif; ?>
                <?php if ($u['cidade']): ?><?= htmlspecialchars($u['cidade']) ?>/<?= htmlspecialchars($u['uf']) ?><?php endif; ?>
                <?php if ($u['cep']): ?><br><span style="font-family:monospace">CEP: <?= preg_replace('/(\d{5})(\d{3})/', '$1-$2', $u['cep']) ?></span><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Info sistema -->
        <div class="card" style="border:1px solid var(--c200)">
            <div class="card-body" style="font-size:.78rem;color:var(--c400);display:flex;flex-direction:column;gap:6px">
                <div><i class="fa-solid fa-calendar-plus" style="width:16px"></i> Cadastrado em: <?= fmtDataHora($u['criado_em']) ?></div>
                <div><i class="fa-solid fa-clock" style="width:16px"></i> Último acesso: <?= $u['ultimo_acesso'] ? fmtDataHora($u['ultimo_acesso']) : 'Nunca acessou' ?></div>
            </div>
        </div>

    </div>

    <!-- COLUNA MATRÍCULAS -->
    <div class="card">
        <div class="card-header">
            <span class="card-titulo"><i class="fa-solid fa-graduation-cap"></i> Histórico de Cursos</span>
            <a href="/crmv/admin/usuarios/matriculas.php?id=<?= $id ?>" class="btn btn-primario btn-sm">
                <i class="fa-solid fa-user-plus"></i> Gerenciar Matrículas
            </a>
        </div>
        <?php if (empty($matriculas)): ?>
        <div class="vazio" style="padding:40px">
            <i class="fa-solid fa-graduation-cap"></i>
            <h3>Nenhuma matrícula</h3>
            <p>Este veterinário ainda não está inscrito em nenhum curso.</p>
        </div>
        <?php else: ?>
        <div class="tabela-wrapper">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Tipo</th>
                        <th>Modalidade</th>
                        <th>Carga</th>
                        <th>Nota</th>
                        <th>Status</th>
                        <th>Certificado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($matriculas as $m): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:.875rem"><?= htmlspecialchars(truncaTexto($m['titulo'], 46)) ?></div>
                        <div style="font-size:.72rem;color:var(--c400)"><?= fmtData($m['matriculado_em']) ?> · <?= htmlspecialchars($m['cat_nome'] ?? '') ?></div>
                    </td>
                    <td><span class="badge b-azul"><?= htmlspecialchars($m['tipo']) ?></span></td>
                    <td><?= badgeModalidade($m['modalidade']) ?></td>
                    <td style="font-size:.82rem;white-space:nowrap"><?= $m['carga_horaria'] ?>h</td>
                    <td style="font-weight:600;font-size:.875rem">
                        <?= $m['nota_final'] !== null ? number_format($m['nota_final'], 1) : '—' ?>
                    </td>
                    <td><?= badgeStatus($m['status']) ?></td>
                    <td>
                        <?php if ($m['certificado_gerado']): ?>
                        <div style="display:flex;align-items:center;gap:6px">
                            <span class="badge b-verde"><i class="fa-solid fa-certificate"></i> Emitido</span>
                            <a href="/crmv/admin/certificados/ver.php?codigo=<?= urlencode($m['certificado_codigo']) ?>"
                               class="btn btn-ghost btn-icone btn-sm" title="Ver certificado">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </div>
                        <?php else: ?>
                        <span style="font-size:.78rem;color:var(--c300)">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
