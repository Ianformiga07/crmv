<?php
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

$id       = (int)($_GET['id'] ?? 0);
$editando = $id > 0;
$c        = [];
$erros    = [];

// Listas auxiliares
$categorias  = dbQuery("SELECT categoria_id, nome, cor_hex FROM tbl_categorias WHERE ativo=1 ORDER BY ordem");
$instrutores = dbQuery("SELECT instrutor_id, nome, titulo FROM tbl_instrutores WHERE ativo=1 ORDER BY nome");
$materiais   = [];

if ($editando) {
    $c = dbQueryOne("SELECT * FROM tbl_cursos WHERE curso_id = ?", [$id]);
    if (!$c) { flash('Curso não encontrado.', 'erro'); header('Location: /crmv/admin/cursos/lista.php'); exit; }
    $materiais = dbQuery("SELECT * FROM tbl_materiais WHERE curso_id = ? AND (modulo_id IS NULL OR modulo_id = 0) ORDER BY criado_em", [$id]);
}

// ── POST: salvar ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $campos = [
        'categoria_id'   => (int)($_POST['categoria_id'] ?? 0) ?: null,
        'instrutor_id'   => (int)($_POST['instrutor_id'] ?? 0) ?: null,
        'titulo'         => trim($_POST['titulo']         ?? ''),
        'descricao'      => trim($_POST['descricao']      ?? ''),
        'tipo'           => $_POST['tipo']       ?? 'CURSO',
        'modalidade'     => $_POST['modalidade'] ?? 'PRESENCIAL',
        'carga_horaria'  => (float)str_replace(',', '.', $_POST['carga_horaria'] ?? '0'),
        'vagas'          => (int)($_POST['vagas'] ?? 0) ?: null,
        'data_inicio'    => trim($_POST['data_inicio']    ?? '') ?: null,
        'data_fim'       => trim($_POST['data_fim']       ?? '') ?: null,
        'horario'        => trim($_POST['horario']        ?? ''),
        'local_nome'     => trim($_POST['local_nome']     ?? ''),
        'local_cidade'   => trim($_POST['local_cidade']   ?? ''),
        'local_uf'       => strtoupper(trim($_POST['local_uf'] ?? 'TO')),
        'local_endereco' => trim($_POST['local_endereco'] ?? ''),
        'link_ead'       => trim($_POST['link_ead']       ?? ''),
        'youtube_id'     => trim($_POST['youtube_id']     ?? ''),
        'valor'          => (float)str_replace(',', '.', $_POST['valor'] ?? '0'),
        'status'         => $_POST['status'] ?? 'RASCUNHO',
        'observacoes'    => trim($_POST['observacoes']    ?? ''),
        // ── Campos do certificado ──────────────────────────
        'cert_conteudo_programatico' => $_POST['cert_conteudo_programatico'] ?? '',
        'cert_validade'              => (int)($_POST['cert_validade'] ?? 0),
        'cert_obs'                   => trim($_POST['cert_obs'] ?? ''),
    ];

    // Extrai ID do YouTube se for URL completa
    if ($campos['youtube_id'] && str_contains($campos['youtube_id'], 'youtube.com')) {
        preg_match('/(?:v=|\/embed\/|\/shorts\/)([a-zA-Z0-9_-]{11})/', $campos['youtube_id'], $m);
        $campos['youtube_id'] = $m[1] ?? $campos['youtube_id'];
    } elseif ($campos['youtube_id'] && str_contains($campos['youtube_id'], 'youtu.be')) {
        $campos['youtube_id'] = basename(parse_url($campos['youtube_id'], PHP_URL_PATH));
    }

    // Validações
    if ($campos['titulo'] === '') $erros[] = 'Título é obrigatório.';
    if ($campos['carga_horaria'] <= 0) $erros[] = 'Carga horária deve ser maior que zero.';

    // Upload de capa
    $nomeCapa = $c['capa'] ?? null;
    if (!empty($_FILES['capa']['name'])) {
        $ext  = strtolower(pathinfo($_FILES['capa']['name'], PATHINFO_EXTENSION));
        $perm = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $perm)) {
            $erros[] = 'Capa deve ser JPG, PNG ou WEBP.';
        } elseif ($_FILES['capa']['size'] > 5 * 1024 * 1024) {
            $erros[] = 'Capa deve ter no máximo 5MB.';
        } else {
            $nomeCapa = 'capa_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destino  = __DIR__ . '/../../uploads/capas/' . $nomeCapa;
            if (!move_uploaded_file($_FILES['capa']['tmp_name'], $destino)) {
                $erros[] = 'Falha ao salvar a capa. Verifique as permissões da pasta uploads/capas/.';
                $nomeCapa = $c['capa'] ?? null;
            } elseif (!empty($c['capa']) && $c['capa'] !== $nomeCapa) {
                @unlink(__DIR__ . '/../../uploads/capas/' . $c['capa']);
            }
        }
    }

    if (empty($erros)) {
        $campos['capa'] = $nomeCapa;

        if ($editando) {
            dbExecute(
                "UPDATE tbl_cursos SET
                    categoria_id=?, instrutor_id=?, titulo=?, descricao=?, tipo=?, modalidade=?,
                    carga_horaria=?, vagas=?, data_inicio=?, data_fim=?, horario=?,
                    local_nome=?, local_cidade=?, local_uf=?, local_endereco=?,
                    link_ead=?, youtube_id=?, valor=?, status=?, observacoes=?, capa=?,
                    cert_conteudo_programatico=?, cert_validade=?, cert_obs=?,
                    atualizado_em=NOW()
                 WHERE curso_id=?",
                [
                    $campos['categoria_id'], $campos['instrutor_id'], $campos['titulo'],
                    $campos['descricao'], $campos['tipo'], $campos['modalidade'],
                    $campos['carga_horaria'], $campos['vagas'], $campos['data_inicio'],
                    $campos['data_fim'], $campos['horario'], $campos['local_nome'],
                    $campos['local_cidade'], $campos['local_uf'], $campos['local_endereco'],
                    $campos['link_ead'], $campos['youtube_id'], $campos['valor'],
                    $campos['status'], $campos['observacoes'], $campos['capa'],
                    $campos['cert_conteudo_programatico'], $campos['cert_validade'], $campos['cert_obs'],
                    $id
                ]
            );
            $salvoId = $id;
            registraLog($_SESSION['usr_id'], 'EDITAR_CURSO', "Editou curso: {$campos['titulo']}", 'tbl_cursos', $id);
            flash('Curso atualizado com sucesso!', 'sucesso');
        } else {
            dbExecute(
                "INSERT INTO tbl_cursos
                    (categoria_id, instrutor_id, titulo, descricao, tipo, modalidade,
                     carga_horaria, vagas, data_inicio, data_fim, horario,
                     local_nome, local_cidade, local_uf, local_endereco,
                     link_ead, youtube_id, valor, status, observacoes, capa,
                     cert_conteudo_programatico, cert_validade, cert_obs,
                     ativo, criado_por)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)",
                [
                    $campos['categoria_id'], $campos['instrutor_id'], $campos['titulo'],
                    $campos['descricao'], $campos['tipo'], $campos['modalidade'],
                    $campos['carga_horaria'], $campos['vagas'], $campos['data_inicio'],
                    $campos['data_fim'], $campos['horario'], $campos['local_nome'],
                    $campos['local_cidade'], $campos['local_uf'], $campos['local_endereco'],
                    $campos['link_ead'], $campos['youtube_id'], $campos['valor'],
                    $campos['status'], $campos['observacoes'], $campos['capa'],
                    $campos['cert_conteudo_programatico'], $campos['cert_validade'], $campos['cert_obs'],
                    $_SESSION['usr_id']
                ]
            );
            $salvoId = dbLastId();
            registraLog($_SESSION['usr_id'], 'CRIAR_CURSO', "Criou curso: {$campos['titulo']}", 'tbl_cursos', $salvoId);
            flash('Curso cadastrado com sucesso!', 'sucesso');
        }

        // Upload de materiais gerais
        if (!empty($_FILES['materiais']['name'][0])) {
            foreach ($_FILES['materiais']['name'] as $k => $nomeOrig) {
                if (empty($nomeOrig)) continue;
                $ext2  = strtolower(pathinfo($nomeOrig, PATHINFO_EXTENSION));
                $perm2 = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'mp4'];
                if (!in_array($ext2, $perm2)) continue;
                if ($_FILES['materiais']['size'][$k] > 50 * 1024 * 1024) continue;
                $nomeArq = 'mat_' . $salvoId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext2;
                $dest2   = __DIR__ . '/../../uploads/materiais/' . $nomeArq;
                if (move_uploaded_file($_FILES['materiais']['tmp_name'][$k], $dest2)) {
                    dbExecute(
                        "INSERT INTO tbl_materiais (curso_id, modulo_id, nome_arquivo, nome_original, tamanho, tipo_mime, criado_por)
                         VALUES (?,NULL,?,?,?,?,?)",
                        [$salvoId, $nomeArq, $nomeOrig, $_FILES['materiais']['size'][$k],
                         $_FILES['materiais']['type'][$k], $_SESSION['usr_id']]
                    );
                }
            }
        }

        header('Location: /crmv/admin/cursos/lista.php');
        exit;
    }

    $c = array_merge($c, $campos);
}

$ufs   = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];
$tipos = ['CURSO', 'PALESTRA', 'WORKSHOP', 'CONGRESSO', 'WEBINAR'];

// Aba ativa (persiste após erro de validação)
$abaAtiva = $_GET['aba'] ?? ($_POST ? ($_POST['_aba_ativa'] ?? 'dados') : 'dados');

$pageTitulo  = $editando ? 'Editar Curso' : 'Novo Curso';
$paginaAtiva = 'cursos';
require_once __DIR__ . '/../../includes/layout.php';
?>

<!-- ── Cabeçalho ─────────────────────────────────────────── -->
<div class="pg-header">
    <div class="pg-header-row">
        <div>
            <h1 class="pg-titulo"><?= $editando ? 'Editar Curso' : 'Novo Curso / Palestra' ?></h1>
            <p class="pg-subtitulo"><?= $editando ? 'Atualize as informações do curso' : 'Preencha os dados do novo curso ou palestra' ?></p>
        </div>
        <div class="pg-acoes">
            <a href="/crmv/admin/cursos/lista.php" class="btn btn-ghost">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
</div>

<?php if (!empty($erros)): ?>
<div class="alerta alerta-erro" style="margin-bottom:20px">
    <i class="fa-solid fa-circle-xmark"></i>
    <div><strong>Corrija os erros:</strong>
        <ul style="margin:6px 0 0 16px;padding:0">
            <?php foreach ($erros as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- ── Navegação por abas ─────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:0 20px">
        <div class="tabs-barra" style="border-bottom:none;margin:0">
            <?php
            $abas = [
                'dados'        => ['fa-graduation-cap', 'Dados do Curso'],
                'conteudo_ead' => ['fa-play-circle',    'Conteúdo / EAD'],
                'materiais'    => ['fa-paperclip',      'Materiais'],
                'certificado'  => ['fa-certificate',    'Certificado'],
            ];
            foreach ($abas as $k => [$ic, $lbl]):
            ?>
            <a href="javascript:void(0)" onclick="trocarAba('<?= $k ?>')"
               class="tab-btn <?= $abaAtiva === $k ? 'ativo' : '' ?>"
               id="tab-<?= $k ?>">
                <i class="fa-solid <?= $ic ?>
                   <?= $k === 'certificado' ? ' ' : '' ?>"
                   <?= $k === 'certificado' ? 'style="color:var(--ouro)"' : '' ?>></i>
                <?= $lbl ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<form method="POST" enctype="multipart/form-data" data-guard id="fForm">
<!-- Campo oculto para aba ativa -->
<input type="hidden" name="_aba_ativa" id="inp_aba_ativa" value="<?= htmlspecialchars($abaAtiva) ?>">

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

    <!-- ══════════════════════════════════════════════════════
         COLUNA PRINCIPAL — Conteúdo das abas
    ══════════════════════════════════════════════════════════ -->
    <div style="display:flex;flex-direction:column;gap:20px">

        <!-- ─── ABA: DADOS DO CURSO ──────────────────────── -->
        <div id="aba-dados" class="aba-conteudo" style="display:<?= $abaAtiva === 'dados' ? 'flex' : 'none' ?>;flex-direction:column;gap:20px">

            <!-- Identificação -->
            <div class="card">
                <div class="card-header">
                    <span class="card-titulo"><i class="fa-solid fa-graduation-cap"></i> Identificação</span>
                </div>
                <div class="card-body">
                    <div class="form-grid">

                        <div class="c12 form-group">
                            <label class="req">Título do Curso / Palestra</label>
                            <input type="text" name="titulo" required data-max="200"
                                value="<?= htmlspecialchars($c['titulo'] ?? '') ?>"
                                placeholder="Ex: Workshop de Ultrassonografia em Pequenos Animais">
                        </div>

                        <div class="c4 form-group">
                            <label>Tipo</label>
                            <select name="tipo">
                                <?php foreach ($tipos as $t): ?>
                                <option value="<?= $t ?>" <?= ($c['tipo'] ?? 'CURSO') === $t ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="c4 form-group">
                            <label>Modalidade</label>
                            <select name="modalidade" onchange="toggleLocal(this.value)">
                                <option value="PRESENCIAL" <?= ($c['modalidade'] ?? '') === 'PRESENCIAL' ? 'selected' : '' ?>>Presencial</option>
                                <option value="EAD"        <?= ($c['modalidade'] ?? '') === 'EAD'        ? 'selected' : '' ?>>EAD (Online)</option>
                                <option value="HIBRIDO"    <?= ($c['modalidade'] ?? '') === 'HIBRIDO'    ? 'selected' : '' ?>>Híbrido</option>
                            </select>
                        </div>

                        <div class="c4 form-group">
                            <label>Categoria</label>
                            <select name="categoria_id">
                                <option value="">Sem categoria</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['categoria_id'] ?>" <?= ($c['categoria_id'] ?? '') == $cat['categoria_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nome']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="c12 form-group">
                            <label>Descrição</label>
                            <textarea name="descricao" rows="4" data-max="2000"
                                placeholder="Descreva o conteúdo, objetivos e público-alvo do curso..."><?= htmlspecialchars($c['descricao'] ?? '') ?></textarea>
                        </div>

                        <div class="c12 form-group">
                            <label>Observações / Notas</label>
                            <textarea name="observacoes" rows="2"
                                placeholder="Informações adicionais, pré-requisitos, avisos importantes..."><?= htmlspecialchars($c['observacoes'] ?? '') ?></textarea>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Datas, Carga e Vagas -->
            <div class="card">
                <div class="card-header">
                    <span class="card-titulo"><i class="fa-solid fa-calendar-days"></i> Datas, Carga Horária e Vagas</span>
                </div>
                <div class="card-body">
                    <div class="form-grid">

                        <div class="c3 form-group">
                            <label class="req">Carga Horária (h)</label>
                            <input type="number" name="carga_horaria" step="0.5" min="0.5" required
                                value="<?= htmlspecialchars($c['carga_horaria'] ?? '') ?>"
                                placeholder="Ex: 8">
                        </div>

                        <div class="c3 form-group">
                            <label>Vagas</label>
                            <input type="number" name="vagas" min="1"
                                value="<?= htmlspecialchars($c['vagas'] ?? '') ?>"
                                placeholder="Ilimitado se vazio">
                        </div>

                        <div class="c3 form-group">
                            <label>Valor (R$)</label>
                            <input type="number" name="valor" step="0.01" min="0"
                                value="<?= htmlspecialchars($c['valor'] ?? '0') ?>"
                                placeholder="0,00 para gratuito">
                        </div>

                        <div class="c3 form-group">
                            <label>Horário</label>
                            <input type="text" name="horario"
                                value="<?= htmlspecialchars($c['horario'] ?? '') ?>"
                                placeholder="Ex: 08h às 17h">
                        </div>

                        <div class="c4 form-group">
                            <label>Data de Início</label>
                            <input type="date" name="data_inicio" value="<?= htmlspecialchars($c['data_inicio'] ?? '') ?>">
                        </div>

                        <div class="c4 form-group">
                            <label>Data de Término</label>
                            <input type="date" name="data_fim" value="<?= htmlspecialchars($c['data_fim'] ?? '') ?>">
                        </div>

                        <div class="c4 form-group">
                            <label>Instrutor Responsável</label>
                            <select name="instrutor_id">
                                <option value="">Não definido</option>
                                <?php foreach ($instrutores as $ins): ?>
                                <option value="<?= $ins['instrutor_id'] ?>" <?= ($c['instrutor_id'] ?? '') == $ins['instrutor_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ins['nome']) ?><?= $ins['titulo'] ? ' — ' . $ins['titulo'] : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Local (Presencial/Híbrido) -->
            <div class="card" id="secaoLocal" style="<?= ($c['modalidade'] ?? 'PRESENCIAL') === 'EAD' ? 'display:none' : '' ?>">
                <div class="card-header">
                    <span class="card-titulo"><i class="fa-solid fa-location-dot"></i> Local de Realização</span>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="c6 form-group">
                            <label>Nome do Local</label>
                            <input type="text" name="local_nome"
                                value="<?= htmlspecialchars($c['local_nome'] ?? '') ?>"
                                placeholder="Ex: Centro de Eventos CRMV/TO">
                        </div>
                        <div class="c4 form-group">
                            <label>Cidade</label>
                            <input type="text" name="local_cidade"
                                value="<?= htmlspecialchars($c['local_cidade'] ?? '') ?>"
                                placeholder="Palmas">
                        </div>
                        <div class="c2 form-group">
                            <label>UF</label>
                            <select name="local_uf">
                                <?php foreach ($ufs as $uf): ?>
                                <option value="<?= $uf ?>" <?= ($c['local_uf'] ?? 'TO') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="c12 form-group">
                            <label>Endereço Completo</label>
                            <input type="text" name="local_endereco"
                                value="<?= htmlspecialchars($c['local_endereco'] ?? '') ?>"
                                placeholder="Rua, número, bairro">
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /aba-dados -->

        <!-- ─── ABA: CONTEÚDO / EAD ──────────────────────── -->
        <div id="aba-conteudo_ead" class="aba-conteudo" style="display:<?= $abaAtiva === 'conteudo_ead' ? 'flex' : 'none' ?>;flex-direction:column;gap:20px">

            <div class="card">
                <div class="card-header">
                    <span class="card-titulo"><i class="fa-brands fa-youtube" style="color:#ff0000"></i> Conteúdo EAD / Vídeo</span>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="c12 form-group">
                            <label>Link do Curso EAD</label>
                            <input type="url" name="link_ead"
                                value="<?= htmlspecialchars($c['link_ead'] ?? '') ?>"
                                placeholder="https://...">
                            <span class="dica">URL da plataforma EAD onde o aluno acessa o curso</span>
                        </div>
                        <div class="c12 form-group">
                            <label>YouTube — URL ou ID do vídeo</label>
                            <input type="text" name="youtube_id" id="inpYT"
                                value="<?= htmlspecialchars($c['youtube_id'] ?? '') ?>"
                                placeholder="https://youtube.com/watch?v=... ou apenas o ID"
                                oninput="prevYT(this.value)">
                            <span class="dica">Cole a URL completa ou apenas o ID (ex: dQw4w9WgXcQ)</span>
                        </div>
                        <div class="c12" id="prevYT" style="<?= empty($c['youtube_id']) ? 'display:none' : '' ?>">
                            <div style="border-radius:8px;overflow:hidden;max-width:480px">
                                <iframe id="ifrYT" width="100%" height="270"
                                    src="<?= $c['youtube_id'] ? 'https://www.youtube.com/embed/' . htmlspecialchars($c['youtube_id']) : '' ?>"
                                    frameborder="0" allowfullscreen></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alerta alerta-info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    Para cursos com <strong>múltiplos módulos e aulas</strong>, utilize o gerenciador de módulos
                    após salvar o curso. Cada módulo pode ter seus próprios vídeos, materiais e avaliação.
                </div>
            </div>

        </div><!-- /aba-conteudo_ead -->

        <!-- ─── ABA: MATERIAIS ───────────────────────────── -->
        <div id="aba-materiais" class="aba-conteudo" style="display:<?= $abaAtiva === 'materiais' ? 'flex' : 'none' ?>;flex-direction:column;gap:20px">

            <div class="card">
                <div class="card-header">
                    <span class="card-titulo"><i class="fa-solid fa-paperclip"></i> Materiais de Apoio Gerais</span>
                    <?php if (!empty($materiais)): ?>
                    <span style="font-size:.78rem;color:var(--c400)"><?= count($materiais) ?> arquivo<?= count($materiais) != 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:14px">

                    <?php if (!empty($materiais)): ?>
                    <div style="display:flex;flex-direction:column;gap:6px">
                        <?php foreach ($materiais as $mat): ?>
                        <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;
                                    background:var(--c50);border-radius:7px;border:1px solid var(--c200)">
                            <i class="fa-solid fa-file" style="color:var(--azul-clr);flex-shrink:0"></i>
                            <span style="flex:1;font-size:.85rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($mat['nome_original']) ?>
                            </span>
                            <span style="font-size:.72rem;color:var(--c400)"><?= round($mat['tamanho'] / 1024) ?> KB</span>
                            <a href="/crmv/admin/cursos/del_material.php?id=<?= $mat['material_id'] ?>&curso_id=<?= $id ?>"
                               class="btn btn-ghost btn-icone btn-sm" title="Remover"
                               data-confirma="Remover este material?">
                                <i class="fa-solid fa-trash" style="color:var(--verm)"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div style="border:2px dashed var(--c300);border-radius:8px;padding:20px;text-align:center;cursor:pointer"
                         onclick="document.getElementById('inpMat').click()"
                         ondragover="this.style.borderColor='var(--azul-clr)'"
                         ondragleave="this.style.borderColor='var(--c300)'">
                        <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.8rem;color:var(--c300);margin-bottom:8px"></i>
                        <p style="font-size:.85rem;color:var(--c500);margin:0">Clique ou arraste arquivos aqui</p>
                        <p style="font-size:.72rem;color:var(--c400);margin:4px 0 0">PDF, DOC, XLS, PPT, ZIP, MP4 — máx. 50MB por arquivo</p>
                        <input type="file" id="inpMat" name="materiais[]" multiple
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.mp4"
                            style="display:none" onchange="listarArquivos(this)">
                    </div>
                    <div id="listaArqs" style="display:none;font-size:.82rem;color:var(--c600)"></div>

                </div>
            </div>

        </div><!-- /aba-materiais -->

        <!-- ─── ABA: CERTIFICADO ─────────────────────────── -->
        <div id="aba-certificado" class="aba-conteudo" style="display:<?= $abaAtiva === 'certificado' ? 'flex' : 'none' ?>;flex-direction:column;gap:20px">

            <!-- Configurações gerais -->
            <div class="card">
                <div class="card-header">
                    <span class="card-titulo">
                        <i class="fa-solid fa-sliders" style="color:var(--ouro)"></i> Configurações do Certificado
                    </span>
                </div>
                <div class="card-body">
                    <div class="form-grid">

                        <div class="c4 form-group">
                            <label>Validade do Certificado</label>
                            <div style="display:flex;align-items:center;gap:8px">
                                <input type="number" name="cert_validade" min="0" max="120"
                                    value="<?= (int)($c['cert_validade'] ?? 0) ?>"
                                    placeholder="0"
                                    style="flex:1">
                                <span style="font-size:.85rem;color:var(--c500);white-space:nowrap">meses</span>
                            </div>
                            <span class="dica">Use 0 (zero) para certificado sem validade.</span>
                        </div>

                        <div class="c8 form-group">
                            <label>Observação Interna</label>
                            <input type="text" name="cert_obs"
                                value="<?= htmlspecialchars($c['cert_obs'] ?? '') ?>"
                                placeholder="Observações internas (não aparecem no certificado impresso)">
                            <span class="dica">Campo apenas para controle administrativo.</span>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Editor de conteúdo programático -->
            <div class="card">
                <div class="card-header">
                    <span class="card-titulo">
                        <i class="fa-solid fa-list-check" style="color:var(--verde)"></i>
                        Conteúdo Programático — Verso do Certificado
                    </span>
                    <span class="badge b-azul" style="font-size:.7rem">
                        Aparece no verso do certificado impresso
                    </span>
                </div>
                <div class="card-body" style="padding:0">

                    <!-- Barra de ferramentas -->
                    <div id="editor-toolbar"
                         style="display:flex;flex-wrap:wrap;gap:4px;padding:10px 16px;
                                background:var(--c50);border-bottom:1px solid var(--c200);
                                position:sticky;top:0;z-index:10">

                        <!-- Tamanho de fonte -->
                        <div style="display:flex;gap:4px;align-items:center;border-right:1px solid var(--c200);padding-right:8px;margin-right:4px">
                            <select onchange="execCmd('fontSize',this.value);this.value=''"
                                style="padding:4px 6px;border:1px solid var(--c300);border-radius:5px;font-size:.8rem;height:30px;cursor:pointer">
                                <option value="">Tamanho</option>
                                <option value="1">Muito pequeno</option>
                                <option value="2">Pequeno</option>
                                <option value="3">Normal</option>
                                <option value="4">Médio</option>
                                <option value="5">Grande</option>
                                <option value="6">Extra grande</option>
                                <option value="7">Enorme</option>
                            </select>
                        </div>

                        <!-- Formatação básica -->
                        <div style="display:flex;gap:2px;border-right:1px solid var(--c200);padding-right:8px;margin-right:4px">
                            <button type="button" onclick="execCmd('bold')"          title="Negrito"    class="tbtn"><i class="fa-solid fa-bold"></i></button>
                            <button type="button" onclick="execCmd('italic')"        title="Itálico"    class="tbtn"><i class="fa-solid fa-italic"></i></button>
                            <button type="button" onclick="execCmd('underline')"     title="Sublinhado" class="tbtn"><i class="fa-solid fa-underline"></i></button>
                            <button type="button" onclick="execCmd('strikeThrough')" title="Tachado"    class="tbtn"><i class="fa-solid fa-strikethrough"></i></button>
                        </div>

                        <!-- Alinhamento -->
                        <div style="display:flex;gap:2px;border-right:1px solid var(--c200);padding-right:8px;margin-right:4px">
                            <button type="button" onclick="execCmd('justifyLeft')"   title="Esquerda"    class="tbtn"><i class="fa-solid fa-align-left"></i></button>
                            <button type="button" onclick="execCmd('justifyCenter')" title="Centralizar" class="tbtn"><i class="fa-solid fa-align-center"></i></button>
                            <button type="button" onclick="execCmd('justifyRight')"  title="Direita"     class="tbtn"><i class="fa-solid fa-align-right"></i></button>
                            <button type="button" onclick="execCmd('justifyFull')"   title="Justificado" class="tbtn"><i class="fa-solid fa-align-justify"></i></button>
                        </div>

                        <!-- Listas -->
                        <div style="display:flex;gap:2px;border-right:1px solid var(--c200);padding-right:8px;margin-right:4px">
                            <button type="button" onclick="execCmd('insertUnorderedList')" title="Lista com marcadores" class="tbtn"><i class="fa-solid fa-list-ul"></i></button>
                            <button type="button" onclick="execCmd('insertOrderedList')"   title="Lista numerada"       class="tbtn"><i class="fa-solid fa-list-ol"></i></button>
                        </div>

                        <!-- Estilo de bloco -->
                        <div style="display:flex;gap:4px;border-right:1px solid var(--c200);padding-right:8px;margin-right:4px">
                            <select onchange="execBlock(this.value);this.value=''"
                                style="padding:4px 6px;border:1px solid var(--c300);border-radius:5px;font-size:.8rem;height:30px;cursor:pointer">
                                <option value="">Estilo</option>
                                <option value="p">Parágrafo</option>
                                <option value="h2">Título de módulo</option>
                                <option value="h3">Subtítulo</option>
                                <option value="h4">Sub-item</option>
                            </select>
                        </div>

                        <!-- Cor do texto -->
                        <div style="display:flex;gap:4px;border-right:1px solid var(--c200);padding-right:8px;margin-right:4px;align-items:center">
                            <label title="Cor do texto" style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:.75rem;color:var(--c600)">
                                <i class="fa-solid fa-font"></i>
                                <input type="color" value="#1a1a1a" onchange="execCmd('foreColor',this.value)"
                                    style="width:24px;height:24px;border:1px solid var(--c300);border-radius:4px;cursor:pointer;padding:1px">
                            </label>
                        </div>

                        <!-- Recuo -->
                        <div style="display:flex;gap:2px;border-right:1px solid var(--c200);padding-right:8px;margin-right:4px">
                            <button type="button" onclick="execCmd('outdent')" title="Diminuir recuo" class="tbtn"><i class="fa-solid fa-outdent"></i></button>
                            <button type="button" onclick="execCmd('indent')"  title="Aumentar recuo" class="tbtn"><i class="fa-solid fa-indent"></i></button>
                        </div>

                        <!-- Desfazer / Refazer -->
                        <div style="display:flex;gap:2px;border-right:1px solid var(--c200);padding-right:8px;margin-right:4px">
                            <button type="button" onclick="execCmd('undo')" title="Desfazer" class="tbtn"><i class="fa-solid fa-rotate-left"></i></button>
                            <button type="button" onclick="execCmd('redo')" title="Refazer"  class="tbtn"><i class="fa-solid fa-rotate-right"></i></button>
                        </div>

                        <!-- Limpar formatação -->
                        <button type="button" onclick="execCmd('removeFormat')" title="Limpar formatação"
                            class="tbtn" style="color:var(--verm)">
                            <i class="fa-solid fa-eraser"></i>
                        </button>

                        <!-- Inserir modelo de módulo -->
                        <div style="margin-left:auto">
                            <button type="button" onclick="inserirModulo()"
                                class="btn btn-ghost btn-sm" style="height:30px;font-size:.78rem">
                                <i class="fa-solid fa-puzzle-piece" style="color:var(--azul-clr)"></i>
                                Inserir Módulo
                            </button>
                        </div>
                    </div>

                    <!-- Área editável -->
                    <div id="editor-cert"
                         contenteditable="true"
                         spellcheck="true"
                         style="min-height:300px;padding:20px 24px;font-size:.9rem;
                                font-family:'Segoe UI',sans-serif;line-height:1.85;
                                color:#1a1a1a;outline:none;border-bottom:1px solid var(--c200)">
                        <?= $c['cert_conteudo_programatico'] ?? '' ?>
                    </div>

                    <!-- Campo hidden sincronizado -->
                    <input type="hidden" name="cert_conteudo_programatico" id="cert_prog_input">

                    <!-- Rodapé do editor -->
                    <div style="padding:8px 16px;background:var(--c50);display:flex;align-items:center;
                                justify-content:space-between;font-size:.72rem;color:var(--c400)">
                        <span id="cert-char-count">0 caracteres</span>
                        <span>
                            <i class="fa-solid fa-circle-info" style="color:var(--azul-clr)"></i>
                            Este conteúdo aparece no <strong>verso do certificado</strong> impresso.
                            Clique em <em>Inserir Módulo</em> para estruturar por módulo.
                        </span>
                    </div>
                </div>
            </div>

            <!-- Pré-visualização do verso -->
            <div class="card">
                <div class="card-header">
                    <span class="card-titulo"><i class="fa-solid fa-eye"></i> Pré-visualização do Verso</span>
                    <button type="button" onclick="atualizarPreview()" class="btn btn-ghost btn-sm">
                        <i class="fa-solid fa-arrows-rotate"></i> Atualizar
                    </button>
                </div>
                <div class="card-body" style="background:#f5f5f0;padding:20px">
                    <div style="max-width:100%;background:#fff;border:2px solid #c9a227;
                                border-radius:4px;padding:24px 30px;
                                box-shadow:0 4px 16px rgba(0,0,0,.1);min-height:160px">
                        <!-- Mini cabeçalho do verso -->
                        <div style="border-bottom:1.5px solid #c9a227;padding-bottom:10px;margin-bottom:14px;
                                    display:flex;align-items:center;gap:10px">
                            <div style="width:28px;height:28px;background:#0d2137;border-radius:50%;
                                        display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <i class="fa-solid fa-list-check" style="font-size:.65rem;color:#c9a227"></i>
                            </div>
                            <div>
                                <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#0d2137">
                                    Conteúdo Programático
                                </div>
                                <div style="font-size:.62rem;color:#888">Verso do Certificado · CRMV-TO</div>
                            </div>
                        </div>
                        <!-- Conteúdo -->
                        <div id="preview-cert-verso" style="font-size:.82rem;color:#1a1a1a;line-height:1.7">
                            <?php if (!empty($c['cert_conteudo_programatico'])): ?>
                            <?= $c['cert_conteudo_programatico'] ?>
                            <?php else: ?>
                            <div style="color:#bbb;font-style:italic;text-align:center;padding:20px">
                                O conteúdo digitado no editor aparecerá aqui.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /aba-certificado -->

    </div><!-- /col-principal -->

    <!-- ══════════════════════════════════════════════════════
         COLUNA LATERAL (sempre visível)
    ══════════════════════════════════════════════════════════ -->
    <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Status -->
        <div class="card">
            <div class="card-header"><span class="card-titulo"><i class="fa-solid fa-toggle-on"></i> Status</span></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
                <?php foreach ([
                    'RASCUNHO'  => ['b-cinza', 'Rascunho',   'Visível apenas para admins'],
                    'PUBLICADO' => ['b-verde',  'Publicado',  'Visível para inscrições'],
                    'ENCERRADO' => ['b-verm',   'Encerrado',  'Inscrições fechadas'],
                ] as $v => [$cls, $lbl, $desc]): ?>
                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px;border-radius:7px;
                              border:2px solid <?= ($c['status'] ?? 'RASCUNHO') === $v ? 'var(--azul-clr)' : 'var(--c200)' ?>"
                       id="statusCard_<?= $v ?>">
                    <input type="radio" name="status" value="<?= $v ?>"
                        <?= ($c['status'] ?? 'RASCUNHO') === $v ? 'checked' : '' ?>
                        onchange="highlightStatus()" style="margin-top:2px;accent-color:var(--azul-clr)">
                    <div>
                        <span class="badge <?= $cls ?>"><?= $lbl ?></span>
                        <div style="font-size:.72rem;color:var(--c400);margin-top:3px"><?= $desc ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Capa -->
        <div class="card">
            <div class="card-header"><span class="card-titulo"><i class="fa-solid fa-image"></i> Capa do Curso</span></div>
            <div class="card-body">
                <div id="prevCapa" style="<?= empty($c['capa']) ? 'display:none' : '' ?>;margin-bottom:12px;border-radius:8px;overflow:hidden;border:1px solid var(--c200)">
                    <img id="imgCapa"
                         src="<?= !empty($c['capa']) ? '/crmv/uploads/capas/' . htmlspecialchars($c['capa']) : '' ?>"
                         style="width:100%;max-height:160px;object-fit:cover">
                </div>
                <div style="border:2px dashed var(--c300);border-radius:8px;padding:16px;text-align:center;cursor:pointer"
                     onclick="document.getElementById('inpCapa').click()">
                    <i class="fa-solid fa-image" style="font-size:1.4rem;color:var(--c300);margin-bottom:6px"></i>
                    <p style="font-size:.8rem;color:var(--c500);margin:0">JPG, PNG ou WEBP — máx. 5MB</p>
                    <input type="file" id="inpCapa" name="capa" accept="image/jpeg,image/png,image/webp"
                        style="display:none" onchange="prevCapaFn(this)">
                </div>
                <?php if (!empty($c['capa'])): ?>
                <div style="margin-top:8px;text-align:center">
                    <a href="/crmv/admin/cursos/del_capa.php?id=<?= $id ?>"
                       class="btn btn-ghost btn-sm" data-confirma="Remover a capa?">
                        <i class="fa-solid fa-trash" style="color:var(--verm)"></i> Remover capa
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Atalho certificado (só edição) -->
        <?php if ($editando): ?>
        <div class="card" style="border:2px solid var(--ouro)">
            <div class="card-body" style="text-align:center;padding:18px">
                <i class="fa-solid fa-certificate" style="font-size:1.8rem;color:var(--ouro);margin-bottom:8px"></i>
                <p style="font-size:.85rem;color:var(--c600);margin:0 0 12px">Emitir certificados para este curso</p>
                <a href="/crmv/admin/certificados/emitir.php?curso_id=<?= $id ?>"
                   class="btn" style="width:100%;justify-content:center;background:var(--ouro);color:#fff;border-color:var(--ouro)">
                    <i class="fa-solid fa-certificate"></i> Emitir Certificados
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botões de salvar -->
        <div style="display:flex;flex-direction:column;gap:8px">
            <button type="submit" class="btn btn-primario" style="justify-content:center">
                <i class="fa-solid fa-floppy-disk"></i>
                <?= $editando ? 'Salvar Alterações' : 'Cadastrar Curso' ?>
            </button>
            <a href="/crmv/admin/cursos/lista.php" class="btn btn-ghost" style="justify-content:center">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </a>
        </div>

    </div><!-- /col-lateral -->
</div>
</form>

<!-- ══════════════════════════════════════════════════════════
     ESTILOS E SCRIPTS
══════════════════════════════════════════════════════════ -->
<style>
/* Botões da toolbar do editor */
.tbtn {
    padding:5px 9px;background:#fff;border:1px solid var(--c300);border-radius:5px;
    cursor:pointer;color:var(--c600);font-size:.78rem;height:30px;
    display:inline-flex;align-items:center;transition:all .15s;
}
.tbtn:hover  { background:var(--azul-esc);color:#fff;border-color:var(--azul-esc); }
.tbtn:active { transform:scale(.95); }

/* Estilos dentro do editor de conteúdo programático */
#editor-cert h2 {
    font-size:1rem;font-weight:700;color:#0d2137;margin:14px 0 6px;
    border-bottom:1px solid #e8d89a;padding-bottom:4px;
}
#editor-cert h3 { font-size:.9rem;font-weight:700;color:#0d2137;margin:10px 0 4px; }
#editor-cert h4 { font-size:.82rem;font-weight:700;color:#555;margin:8px 0 3px; }
#editor-cert ul,
#editor-cert ol  { margin-left:20px; }
#editor-cert li  { margin-bottom:3px; }
#editor-cert blockquote {
    border-left:3px solid #c9a227;padding-left:12px;
    margin-left:8px;color:#555;font-style:italic;
}

/* Estilos do preview */
#preview-cert-verso h2 {
    font-size:.95rem;font-weight:700;color:#0d2137;margin:12px 0 5px;
    border-bottom:1px solid #e8d89a;padding-bottom:3px;
}
#preview-cert-verso h3 { font-size:.85rem;font-weight:700;color:#0d2137;margin:8px 0 3px; }
#preview-cert-verso h4 { font-size:.78rem;font-weight:700;color:#555;margin:6px 0 2px; }
#preview-cert-verso ul,
#preview-cert-verso ol { margin-left:18px; }
#preview-cert-verso li { margin-bottom:2px; }
</style>

<script>
// ── Navegação por abas ──────────────────────────────────────
function trocarAba(qual) {
    // Esconde todas
    document.querySelectorAll('.aba-conteudo').forEach(function(el) {
        el.style.display = 'none';
    });
    // Remove ativo de todas as tabs
    document.querySelectorAll('.tab-btn').forEach(function(el) {
        el.classList.remove('ativo');
    });
    // Mostra a ativa
    var aba = document.getElementById('aba-' + qual);
    if (aba) aba.style.display = 'flex';
    // Marca tab como ativa
    var tab = document.getElementById('tab-' + qual);
    if (tab) tab.classList.add('ativo');
    // Persiste no campo hidden
    document.getElementById('inp_aba_ativa').value = qual;
}

// ── Editor do certificado ───────────────────────────────────
var editorCert  = document.getElementById('editor-cert');
var hiddenCert  = document.getElementById('cert_prog_input');
var charCount   = document.getElementById('cert-char-count');

function syncEditor() {
    if (hiddenCert && editorCert) {
        hiddenCert.value = editorCert.innerHTML;
        if (charCount) {
            charCount.textContent = editorCert.innerText.replace(/\n/g, '').length + ' caracteres';
        }
    }
}
if (editorCert) {
    editorCert.addEventListener('input', syncEditor);
    syncEditor(); // sincroniza na carga inicial
}

// Sincroniza antes do submit
var fForm = document.getElementById('fForm');
if (fForm) fForm.addEventListener('submit', syncEditor);

function execCmd(cmd, val) {
    if (editorCert) editorCert.focus();
    document.execCommand(cmd, false, val || null);
}
function execBlock(tag) {
    if (!tag) return;
    if (editorCert) editorCert.focus();
    document.execCommand('formatBlock', false, tag);
}
function atualizarPreview() {
    var prev = document.getElementById('preview-cert-verso');
    if (prev && editorCert) prev.innerHTML = editorCert.innerHTML;
}
function inserirModulo() {
    if (!editorCert) return;
    editorCert.focus();
    var n = editorCert.querySelectorAll('h2').length + 1;
    document.execCommand('insertHTML', false,
        '<h2>Módulo ' + n + ' — Título do Módulo</h2>' +
        '<ul><li>Tópico 1</li><li>Tópico 2</li><li>Tópico 3</li></ul>'
    );
}

// ── Demais funções do form ──────────────────────────────────
function toggleLocal(v) {
    var sec = document.getElementById('secaoLocal');
    var ead = document.getElementById('aba-conteudo_ead'); // mostra dica
    if (sec) sec.style.display = (v === 'EAD') ? 'none' : '';
}
function prevCapaFn(inp) {
    if (!inp.files[0]) return;
    var r = new FileReader();
    r.onload = function(e) {
        document.getElementById('imgCapa').src = e.target.result;
        document.getElementById('prevCapa').style.display = '';
    };
    r.readAsDataURL(inp.files[0]);
}
function listarArquivos(inp) {
    var d = document.getElementById('listaArqs');
    if (!inp.files.length) { d.style.display = 'none'; return; }
    d.style.display = '';
    d.innerHTML = Array.from(inp.files).map(function(f) {
        return '<div style="padding:4px 0;border-bottom:1px solid var(--c100)">' +
               '<i class="fa-solid fa-file" style="color:var(--azul-clr)"></i> ' +
               f.name + ' <span style="color:var(--c400)">(' + Math.round(f.size / 1024) + ' KB)</span></div>';
    }).join('');
}
function prevYT(v) {
    var id = v;
    var m  = v.match(/(?:v=|\/embed\/|youtu\.be\/|\/shorts\/)([a-zA-Z0-9_-]{11})/);
    if (m) id = m[1];
    var prev = document.getElementById('prevYT');
    var ifr  = document.getElementById('ifrYT');
    if (id.length === 11) {
        if (ifr)  ifr.src = 'https://www.youtube.com/embed/' + id;
        if (prev) prev.style.display = '';
    } else {
        if (prev) prev.style.display = 'none';
        if (ifr)  ifr.src = '';
    }
}
function highlightStatus() {
    document.querySelectorAll('[id^="statusCard_"]').forEach(function(el) {
        var radio = el.querySelector('input[type=radio]');
        el.style.borderColor = radio.checked ? 'var(--azul-clr)' : 'var(--c200)';
    });
}
document.querySelectorAll('input[name="status"]').forEach(function(r) {
    r.addEventListener('change', highlightStatus);
});
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
