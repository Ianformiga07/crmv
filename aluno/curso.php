<?php
require_once __DIR__ . '/../includes/conexao.php';
exigeLogin();
if ((int)($_SESSION['usr_perfil'] ?? 0) === 1) {
    header('Location: /crmv/admin/dashboard.php'); exit;
}

$usr_id       = (int)$_SESSION['usr_id'];
$matricula_id = (int)($_GET['id'] ?? 0);
if (!$matricula_id) { header('Location: /crmv/aluno/dashboard.php'); exit; }

// Matrícula + Curso
$mat = dbQueryOne(
    "SELECT m.matricula_id, m.status, m.nota_final, m.presenca_percent,
            m.certificado_gerado, m.certificado_codigo, m.progresso_ead,
            m.matriculado_em,
            c.curso_id, c.titulo, c.descricao, c.tipo, c.modalidade,
            c.carga_horaria, c.data_inicio, c.data_fim, c.horario,
            c.local_nome, c.local_cidade, c.local_uf, c.local_endereco,
            c.capa, c.youtube_id, c.link_ead, c.observacoes,
            c.cert_modelo,
            cat.nome AS cat_nome, cat.cor_hex
     FROM tbl_matriculas m
     INNER JOIN tbl_cursos c ON m.curso_id = c.curso_id
     LEFT  JOIN tbl_categorias cat ON c.categoria_id = cat.categoria_id
     WHERE m.matricula_id = ? AND m.usuario_id = ? AND c.ativo = 1",
    [$matricula_id, $usr_id]
);
if (!$mat) { flash('Matrícula não encontrada.', 'erro'); header('Location: /crmv/aluno/dashboard.php'); exit; }

// ── Módulos do curso ─────────────────────────────────────────
// Tenta buscar módulos cadastrados
$modulos = dbQuery(
    "SELECT modulo_id, titulo, descricao, ordem
     FROM tbl_modulos
     WHERE curso_id = ?
     ORDER BY ordem ASC",
    [$mat['curso_id']]
);

// Para cada módulo, busca seus itens (aulas, materiais, avaliação)
foreach ($modulos as &$mod) {
    // Aulas / vídeos do módulo
    $mod['aulas'] = dbQuery(
        "SELECT aula_id, titulo, youtube_id, link_externo, descricao, ordem, duracao_min
         FROM tbl_aulas
         WHERE modulo_id = ? AND ativo = 1
         ORDER BY ordem ASC",
        [$mod['modulo_id']]
    );
    // Materiais do módulo
    $mod['materiais'] = dbQuery(
        "SELECT material_id, nome_original, nome_arquivo, tamanho, tipo_mime
         FROM tbl_materiais
         WHERE modulo_id = ?
         ORDER BY criado_em ASC",
        [$mod['modulo_id']]
    );
    // Avaliação do módulo
    $mod['avaliacao'] = dbQueryOne(
        "SELECT avaliacao_id, titulo, tipo, nota_minima, tempo_limite, tentativas_max
         FROM tbl_avaliacoes
         WHERE modulo_id = ? AND ativo = 1
         LIMIT 1",
        [$mod['modulo_id']]
    );
}
unset($mod);

// Materiais SEM módulo (associados diretamente ao curso)
$materiais_gerais = dbQuery(
    "SELECT material_id, nome_original, nome_arquivo, tamanho, tipo_mime, criado_em
     FROM tbl_materiais
     WHERE curso_id = ? AND (modulo_id IS NULL OR modulo_id = 0)
     ORDER BY criado_em ASC",
    [$mat['curso_id']]
);

// Avaliação geral do curso (sem módulo)
$avaliacao_geral = dbQueryOne(
    "SELECT avaliacao_id, titulo, descricao, tipo, nota_minima, tempo_limite, tentativas_max
     FROM tbl_avaliacoes
     WHERE curso_id = ? AND (modulo_id IS NULL OR modulo_id = 0) AND ativo = 1
     LIMIT 1",
    [$mat['curso_id']]
);

// Instrutores
$instrutores = dbQuery(
    "SELECT nome, titulo_profis, instituicao, crmv, bio, foto, ordem
     FROM tbl_curso_instrutores
     WHERE curso_id = ?
     ORDER BY ordem ASC",
    [$mat['curso_id']]
);

$aba = $_GET['aba'] ?? 'conteudo';
$pageTitulo  = truncaTexto($mat['titulo'], 40);
$paginaAtiva = 'meus-cursos';
require_once __DIR__ . '/../includes/layout_aluno.php';

function fmtTamanho(int $bytes): string {
    if ($bytes < 1024)    return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
function iconeArquivo(string $mime): string {
    if (str_contains($mime, 'pdf'))   return 'fa-file-pdf';
    if (str_contains($mime, 'word') || str_contains($mime, 'doc')) return 'fa-file-word';
    if (str_contains($mime, 'sheet') || str_contains($mime, 'excel') || str_contains($mime, 'xls')) return 'fa-file-excel';
    if (str_contains($mime, 'image')) return 'fa-file-image';
    if (str_contains($mime, 'video')) return 'fa-file-video';
    if (str_contains($mime, 'zip')  || str_contains($mime, 'rar'))  return 'fa-file-zipper';
    return 'fa-file';
}
?>

<!-- ── Cabeçalho ─────────────────────────────────────────── -->
<div class="pg-header">
    <div class="pg-header-row">
        <div>
            <h1 class="pg-titulo"><?= htmlspecialchars($mat['titulo']) ?></h1>
            <p class="pg-subtitulo" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <?= badgeModalidade($mat['modalidade']) ?>
                <span><?= htmlspecialchars($mat['tipo']) ?></span>
                <span style="color:var(--c300)">·</span>
                <span><?= $mat['carga_horaria'] ?>h de carga horária</span>
                <?php if ($mat['cat_nome']): ?>
                <span style="color:var(--c300)">·</span>
                <span><?= htmlspecialchars($mat['cat_nome']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="pg-acoes">
            <?php if ($mat['status'] === 'CONCLUIDA'): ?>
            <a href="/crmv/aluno/<?= $mat['certificado_gerado'] ? 'certificado_ver.php' : 'emitir_certificado.php' ?>?id=<?= $matricula_id ?>"
               class="btn btn-primario">
                <i class="fa-solid fa-certificate"></i>
                <?= $mat['certificado_gerado'] ? 'Ver Certificado' : 'Emitir Certificado' ?>
            </a>
            <?php endif; ?>
            <a href="/crmv/aluno/dashboard.php" class="btn btn-ghost">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
</div>

<!-- ── Barra de status ──────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:14px 20px">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
            <?php
            $statusLabel = match($mat['status']) {
                'ATIVA'     => ['b-azul',  'fa-play',   'Em Andamento'],
                'CONCLUIDA' => ['b-verde', 'fa-check',  'Concluído'],
                'CANCELADA' => ['b-verm',  'fa-ban',    'Cancelado'],
                'REPROVADO' => ['b-verm',  'fa-xmark',  'Reprovado'],
                default     => ['b-cinza', 'fa-question', $mat['status']],
            };
            ?>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:.75rem;font-weight:600;color:var(--c500);text-transform:uppercase;letter-spacing:.05em">Status</span>
                <span class="badge <?= $statusLabel[0] ?>">
                    <i class="fa-solid <?= $statusLabel[1] ?>"></i> <?= $statusLabel[2] ?>
                </span>
            </div>
            <?php if ($mat['nota_final']): ?>
            <div style="display:flex;align-items:center;gap:6px">
                <span style="font-size:.75rem;color:var(--c500)">Nota:</span>
                <strong style="color:var(--azul-esc)"><?= number_format($mat['nota_final'], 1) ?></strong>
            </div>
            <?php endif; ?>
            <?php if ($mat['data_inicio']): ?>
            <div style="display:flex;align-items:center;gap:6px">
                <i class="fa-solid fa-calendar" style="color:var(--c400);font-size:.8rem"></i>
                <span style="font-size:.82rem;color:var(--c600)">
                    <?= fmtData($mat['data_inicio']) ?>
                    <?= $mat['data_fim'] && $mat['data_fim'] !== $mat['data_inicio'] ? ' — ' . fmtData($mat['data_fim']) : '' ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if ($mat['local_cidade']): ?>
            <div style="display:flex;align-items:center;gap:6px">
                <i class="fa-solid fa-location-dot" style="color:var(--c400);font-size:.8rem"></i>
                <span style="font-size:.82rem;color:var(--c600)">
                    <?= htmlspecialchars($mat['local_cidade']) ?>/<?= htmlspecialchars($mat['local_uf']) ?>
                </span>
            </div>
            <?php endif; ?>
            <div style="margin-left:auto;font-size:.75rem;color:var(--c400)">
                Inscrito em <?= fmtData($mat['matriculado_em']) ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Tabs ─────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header" style="padding:0 20px">
        <div class="tabs-barra" style="border-bottom:none;margin:0;width:100%">
            <?php
            $totalMateriais = count($materiais_gerais);
            foreach ($modulos as $mod) $totalMateriais += count($mod['materiais']);
            $tabs = [
                'conteudo'  => ['fa-play-circle',      'Conteúdo'],
                'info'      => ['fa-circle-info',       'Informações'],
            ];
            if ($totalMateriais > 0) $tabs['materiais'] = ['fa-folder-open', 'Materiais (' . $totalMateriais . ')'];
            if ($avaliacao_geral)    $tabs['avaliacao'] = ['fa-clipboard-question', 'Avaliação'];
            ?>
            <?php foreach ($tabs as $k => [$ic, $lbl]): ?>
            <a href="?id=<?= $matricula_id ?>&aba=<?= $k ?>"
               class="tab-btn <?= $aba === $k ? 'ativo' : '' ?>">
                <i class="fa-solid <?= $ic ?>"></i> <?= $lbl ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card-body">

    <!-- ─── ABA CONTEÚDO (Módulos) ──────────────────────── -->
    <?php if ($aba === 'conteudo'): ?>

        <?php if (!empty($modulos)): ?>
        <!-- Sistema de módulos estruturados -->
        <div style="display:flex;flex-direction:column;gap:20px">

        <?php foreach ($modulos as $midx => $mod): ?>
        <div style="border:1.5px solid var(--c200);border-radius:var(--radius-lg);overflow:hidden">

            <!-- Cabeçalho do módulo -->
            <div style="background:var(--azul-esc);color:#fff;padding:14px 20px;
                        display:flex;align-items:center;gap:12px">
                <div style="width:32px;height:32px;border-radius:50%;background:rgba(201,162,39,.25);
                            border:1.5px solid #c9a227;display:flex;align-items:center;
                            justify-content:center;flex-shrink:0;font-family:var(--font-titulo);
                            font-weight:700;font-size:.85rem;color:#c9a227">
                    <?= $midx + 1 ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:.95rem"><?= htmlspecialchars($mod['titulo']) ?></div>
                    <?php if ($mod['descricao']): ?>
                    <div style="font-size:.72rem;color:rgba(255,255,255,.55);margin-top:2px">
                        <?= htmlspecialchars(truncaTexto($mod['descricao'], 100)) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Contadores do módulo -->
                <div style="margin-left:auto;display:flex;gap:10px;font-size:.72rem;color:rgba(255,255,255,.55)">
                    <?php if (!empty($mod['aulas'])): ?>
                    <span><i class="fa-solid fa-play-circle"></i> <?= count($mod['aulas']) ?> aula<?= count($mod['aulas']) != 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                    <?php if (!empty($mod['materiais'])): ?>
                    <span><i class="fa-solid fa-file"></i> <?= count($mod['materiais']) ?> material<?= count($mod['materiais']) != 1 ? 'is' : '' ?></span>
                    <?php endif; ?>
                    <?php if ($mod['avaliacao']): ?>
                    <span><i class="fa-solid fa-clipboard-question"></i> avaliação</span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="padding:16px;display:flex;flex-direction:column;gap:10px">

                <!-- Aulas do módulo -->
                <?php if (!empty($mod['aulas'])): ?>
                <div>
                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;
                                letter-spacing:.08em;color:var(--c400);margin-bottom:8px">
                        <i class="fa-solid fa-play-circle" style="color:var(--azul-clr)"></i> Video Aulas
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px">
                    <?php foreach ($mod['aulas'] as $aidx => $aula): ?>
                    <div style="border:1px solid var(--c200);border-radius:var(--radius);overflow:hidden;background:#fff">
                        <?php if ($aula['youtube_id']): ?>
                        <!-- Player embed -->
                        <details>
                            <summary style="padding:12px 16px;cursor:pointer;display:flex;align-items:center;
                                            gap:10px;font-size:.875rem;font-weight:600;color:var(--azul-esc);
                                            list-style:none;user-select:none;background:var(--c50)">
                                <div style="width:28px;height:28px;background:var(--azul-esc);border-radius:50%;
                                            display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <i class="fa-brands fa-youtube" style="color:#ff0000;font-size:.8rem"></i>
                                </div>
                                <span><?= htmlspecialchars($aula['titulo']) ?></span>
                                <?php if ($aula['duracao_min']): ?>
                                <span style="margin-left:auto;font-size:.72rem;color:var(--c400);font-weight:400">
                                    <i class="fa-solid fa-clock"></i> <?= $aula['duracao_min'] ?>min
                                </span>
                                <?php endif; ?>
                                <i class="fa-solid fa-chevron-down" style="color:var(--c400);font-size:.7rem;margin-left:8px"></i>
                            </summary>
                            <div style="padding-bottom:0">
                                <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;background:#000">
                                    <iframe
                                        src="https://www.youtube.com/embed/<?= htmlspecialchars($aula['youtube_id']) ?>"
                                        frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen
                                        loading="lazy"
                                        style="position:absolute;top:0;left:0;width:100%;height:100%">
                                    </iframe>
                                </div>
                                <?php if ($aula['descricao']): ?>
                                <div style="padding:12px 16px;font-size:.82rem;color:var(--c600);line-height:1.6;
                                            border-top:1px solid var(--c100)">
                                    <?= nl2br(htmlspecialchars($aula['descricao'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </details>
                        <?php elseif ($aula['link_externo']): ?>
                        <!-- Link externo -->
                        <div style="padding:12px 16px;display:flex;align-items:center;gap:12px;background:var(--c50)">
                            <div style="width:28px;height:28px;background:var(--azul-esc);border-radius:50%;
                                        display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <i class="fa-solid fa-arrow-up-right-from-square" style="color:#c9a227;font-size:.7rem"></i>
                            </div>
                            <span style="font-size:.875rem;font-weight:600;color:var(--azul-esc);flex:1">
                                <?= htmlspecialchars($aula['titulo']) ?>
                            </span>
                            <a href="<?= htmlspecialchars($aula['link_externo']) ?>" target="_blank"
                               class="btn btn-primario btn-sm">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Acessar
                            </a>
                        </div>
                        <?php else: ?>
                        <div style="padding:12px 16px;display:flex;align-items:center;gap:10px;background:var(--c50)">
                            <div style="width:28px;height:28px;background:var(--c200);border-radius:50%;
                                        display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <i class="fa-solid fa-play" style="color:var(--c400);font-size:.7rem"></i>
                            </div>
                            <span style="font-size:.875rem;font-weight:600;color:var(--azul-esc)">
                                <?= htmlspecialchars($aula['titulo']) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Materiais do módulo -->
                <?php if (!empty($mod['materiais'])): ?>
                <div>
                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;
                                letter-spacing:.08em;color:var(--c400);margin-bottom:8px">
                        <i class="fa-solid fa-folder-open" style="color:var(--ouro)"></i> Materiais de Apoio
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px">
                    <?php foreach ($mod['materiais'] as $mitem): ?>
                    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;
                                background:var(--c50);border:1px solid var(--c200);
                                border-radius:var(--radius)">
                        <i class="fa-solid <?= iconeArquivo($mitem['tipo_mime'] ?? '') ?>"
                           style="color:var(--azul-txt);font-size:1.1rem;width:20px;text-align:center"></i>
                        <div style="flex:1;min-width:0">
                            <div style="font-size:.82rem;font-weight:600;color:var(--c900);
                                        overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($mitem['nome_original']) ?>
                            </div>
                            <div style="font-size:.68rem;color:var(--c400)">
                                <?= fmtTamanho((int)$mitem['tamanho']) ?>
                            </div>
                        </div>
                        <a href="/crmv/uploads/materiais/<?= htmlspecialchars($mitem['nome_arquivo']) ?>"
                           target="_blank" class="btn btn-ghost btn-sm" download>
                            <i class="fa-solid fa-download"></i> Baixar
                        </a>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Avaliação do módulo -->
                <?php if ($mod['avaliacao']): ?>
                <div style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1.5px solid #93c5fd;
                            border-radius:var(--radius);padding:14px 16px;
                            display:flex;align-items:center;justify-content:space-between;gap:12px">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:36px;height:36px;background:var(--azul-esc);border-radius:50%;
                                    display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-clipboard-question" style="color:#c9a227;font-size:.9rem"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.875rem;color:var(--azul-esc)">
                                <?= htmlspecialchars($mod['avaliacao']['titulo']) ?>
                            </div>
                            <div style="font-size:.72rem;color:var(--c500);margin-top:2px">
                                <?php if ($mod['avaliacao']['nota_minima']): ?>
                                Nota mínima: <?= $mod['avaliacao']['nota_minima'] ?> pts ·
                                <?php endif; ?>
                                <?php if ($mod['avaliacao']['tempo_limite']): ?>
                                <?= $mod['avaliacao']['tempo_limite'] ?> min
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($mat['status'] !== 'CONCLUIDA'): ?>
                    <a href="/crmv/aluno/avaliacao.php?matricula=<?= $matricula_id ?>&avaliacao=<?= $mod['avaliacao']['avaliacao_id'] ?>"
                       class="btn btn-primario btn-sm">
                        <i class="fa-solid fa-play"></i> Fazer Avaliação
                    </a>
                    <?php else: ?>
                    <span class="badge b-verde"><i class="fa-solid fa-check"></i> Concluído</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Nenhum conteúdo no módulo -->
                <?php if (empty($mod['aulas']) && empty($mod['materiais']) && !$mod['avaliacao']): ?>
                <div style="padding:14px;text-align:center;color:var(--c400);font-size:.82rem">
                    <i class="fa-solid fa-hourglass-half"></i> Conteúdo em breve
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <!-- Materiais e avaliação gerais (sem módulo) após a lista de módulos -->
        <?php if (!empty($materiais_gerais)): ?>
        <div style="margin-top:20px">
            <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;
                        letter-spacing:.08em;color:var(--c500);margin-bottom:10px">
                <i class="fa-solid fa-folder-open"></i> Materiais Gerais do Curso
            </div>
            <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach ($materiais_gerais as $mitem): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;
                        background:var(--c50);border:1px solid var(--c200);border-radius:var(--radius)">
                <i class="fa-solid <?= iconeArquivo($mitem['tipo_mime'] ?? '') ?>"
                   style="color:var(--azul-txt);font-size:1.1rem;width:20px;text-align:center"></i>
                <div style="flex:1;min-width:0">
                    <div style="font-size:.875rem;font-weight:600;color:var(--c900);
                                overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= htmlspecialchars($mitem['nome_original']) ?>
                    </div>
                    <div style="font-size:.72rem;color:var(--c400)">
                        <?= fmtTamanho((int)$mitem['tamanho']) ?>
                    </div>
                </div>
                <a href="/crmv/uploads/materiais/<?= htmlspecialchars($mitem['nome_arquivo']) ?>"
                   target="_blank" class="btn btn-ghost btn-sm" download>
                    <i class="fa-solid fa-download"></i> Baixar
                </a>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Sem módulos — fallback com vídeo/link único do curso -->
        <?php if ($mat['youtube_id']): ?>
        <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;
                    border-radius:var(--radius);margin-bottom:20px;background:#000">
            <iframe
                src="https://www.youtube.com/embed/<?= htmlspecialchars($mat['youtube_id']) ?>"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
                style="position:absolute;top:0;left:0;width:100%;height:100%;border-radius:var(--radius)">
            </iframe>
        </div>
        <?php elseif ($mat['link_ead']): ?>
        <div class="alerta alerta-info" style="margin-bottom:20px">
            <i class="fa-solid fa-laptop-code"></i>
            <div>
                <strong>Curso EAD</strong> — acesse a plataforma de ensino pelo botão abaixo.
            </div>
        </div>
        <a href="<?= htmlspecialchars($mat['link_ead']) ?>" target="_blank"
           class="btn btn-primario btn-lg" style="margin-bottom:20px">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Acessar Plataforma EAD
        </a>
        <?php else: ?>
        <div class="alerta alerta-aviso">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Nenhuma aula online disponível. Verifique os materiais ou entre em contato com o CRMV-TO.
        </div>
        <?php endif; ?>

        <?php if ($mat['descricao']): ?>
        <div style="margin-top:16px">
            <h3 style="font-family:var(--font-titulo);font-size:1rem;color:var(--azul-esc);margin:0 0 10px">
                <i class="fa-solid fa-book-open" style="color:var(--verde);margin-right:6px"></i> Sobre este curso
            </h3>
            <div style="font-size:.9rem;color:var(--c600);line-height:1.75">
                <?= nl2br(htmlspecialchars($mat['descricao'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Materiais gerais (sem módulos) -->
        <?php if (!empty($materiais_gerais)): ?>
        <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--c200)">
            <h3 style="font-family:var(--font-titulo);font-size:1rem;color:var(--azul-esc);margin:0 0 12px">
                <i class="fa-solid fa-folder-open" style="color:var(--ouro);margin-right:6px"></i> Materiais
            </h3>
            <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach ($materiais_gerais as $mitem): ?>
            <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;
                        background:var(--c50);border:1px solid var(--c200);border-radius:var(--radius)">
                <i class="fa-solid <?= iconeArquivo($mitem['tipo_mime'] ?? '') ?>"
                   style="color:var(--azul-txt);font-size:1.1rem;width:20px;text-align:center"></i>
                <div style="flex:1;min-width:0">
                    <div style="font-weight:600;font-size:.875rem;color:var(--c900);
                                overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= htmlspecialchars($mitem['nome_original']) ?>
                    </div>
                    <div style="font-size:.72rem;color:var(--c400)"><?= fmtTamanho((int)$mitem['tamanho']) ?></div>
                </div>
                <a href="/crmv/uploads/materiais/<?= htmlspecialchars($mitem['nome_arquivo']) ?>"
                   target="_blank" class="btn btn-ghost btn-sm" download>
                    <i class="fa-solid fa-download"></i> Baixar
                </a>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Avaliação geral -->
        <?php if ($avaliacao_geral): ?>
        <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--c200)">
            <h3 style="font-family:var(--font-titulo);font-size:1rem;color:var(--azul-esc);margin:0 0 12px">
                <i class="fa-solid fa-clipboard-question" style="color:var(--azul-clr);margin-right:6px"></i> Avaliação do Curso
            </h3>
            <?php if ($mat['status'] === 'CONCLUIDA'): ?>
            <div class="alerta alerta-sucesso">
                <i class="fa-solid fa-circle-check"></i>
                Curso concluído! Você já pode emitir seu certificado.
            </div>
            <?php else: ?>
            <a href="/crmv/aluno/avaliacao.php?id=<?= $matricula_id ?>" class="btn btn-primario btn-lg">
                <i class="fa-solid fa-clipboard-question"></i> Iniciar Avaliação
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Instrutores -->
        <?php if (!empty($instrutores)): ?>
        <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--c200)">
            <h3 style="font-family:var(--font-titulo);font-size:1rem;color:var(--azul-esc);margin:0 0 16px">
                <i class="fa-solid fa-chalkboard-teacher" style="color:var(--verde);margin-right:6px"></i>
                Instrutor<?= count($instrutores) > 1 ? 'es' : '' ?>
            </h3>
            <div style="display:flex;flex-direction:column;gap:12px">
            <?php foreach ($instrutores as $inst): ?>
            <div style="display:flex;align-items:flex-start;gap:14px;padding:14px;
                        background:var(--c50);border-radius:var(--radius);border:1px solid var(--c200)">
                <div style="width:44px;height:44px;border-radius:50%;background:var(--azul-esc);
                            display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <?php if ($inst['foto']): ?>
                    <img src="/crmv/uploads/fotos/<?= htmlspecialchars($inst['foto']) ?>"
                         style="width:44px;height:44px;border-radius:50%;object-fit:cover">
                    <?php else: ?>
                    <i class="fa-solid fa-user" style="color:rgba(255,255,255,.6);font-size:.9rem"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:.9rem;color:var(--azul-esc)">
                        <?= htmlspecialchars($inst['nome']) ?>
                    </div>
                    <?php if ($inst['titulo_profis']): ?>
                    <div style="font-size:.78rem;color:var(--c500);margin-top:2px">
                        <?= htmlspecialchars($inst['titulo_profis']) ?>
                        <?= $inst['instituicao'] ? ' — ' . htmlspecialchars($inst['instituicao']) : '' ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($inst['bio']): ?>
                    <div style="font-size:.82rem;color:var(--c600);margin-top:6px;line-height:1.6">
                        <?= htmlspecialchars(truncaTexto($inst['bio'], 200)) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($mat['observacoes']): ?>
        <div class="alerta alerta-info" style="margin-top:16px">
            <i class="fa-solid fa-circle-info"></i>
            <div><strong>Observações:</strong><br><?= nl2br(htmlspecialchars($mat['observacoes'])) ?></div>
        </div>
        <?php endif; ?>
        <?php endif; // fim if modulos ?>

    <!-- ─── ABA MATERIAIS (todos agrupados) ─────────────── -->
    <?php elseif ($aba === 'materiais'): ?>

        <?php if (!empty($modulos)): ?>
        <!-- Materiais agrupados por módulo -->
        <?php foreach ($modulos as $midx => $mod):
            if (empty($mod['materiais'])) continue; ?>
        <div style="margin-bottom:20px">
            <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;
                        letter-spacing:.08em;color:var(--azul-esc);margin-bottom:8px;
                        display:flex;align-items:center;gap:8px">
                <span style="background:var(--azul-esc);color:#fff;padding:2px 8px;border-radius:10px;font-size:.7rem">
                    Módulo <?= $midx + 1 ?>
                </span>
                <?= htmlspecialchars($mod['titulo']) ?>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px">
            <?php foreach ($mod['materiais'] as $mitem): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;
                        background:var(--c50);border:1px solid var(--c200);border-radius:var(--radius)">
                <i class="fa-solid <?= iconeArquivo($mitem['tipo_mime'] ?? '') ?>"
                   style="color:var(--azul-txt);font-size:1.1rem;width:20px;text-align:center"></i>
                <div style="flex:1;min-width:0">
                    <div style="font-size:.875rem;font-weight:600;color:var(--c900);
                                overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= htmlspecialchars($mitem['nome_original']) ?>
                    </div>
                    <div style="font-size:.72rem;color:var(--c400)">
                        <?= fmtTamanho((int)$mitem['tamanho']) ?>
                    </div>
                </div>
                <a href="/crmv/uploads/materiais/<?= htmlspecialchars($mitem['nome_arquivo']) ?>"
                   target="_blank" class="btn btn-ghost btn-sm" download>
                    <i class="fa-solid fa-download"></i> Baixar
                </a>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($materiais_gerais)): ?>
        <?php if (!empty($modulos)): ?>
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;
                    letter-spacing:.08em;color:var(--c500);margin-bottom:8px;margin-top:8px">
            <i class="fa-solid fa-folder-open"></i> Materiais Gerais
        </div>
        <?php endif; ?>
        <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($materiais_gerais as $mitem): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;
                    background:var(--c50);border:1px solid var(--c200);border-radius:var(--radius)">
            <i class="fa-solid <?= iconeArquivo($mitem['tipo_mime'] ?? '') ?>"
               style="color:var(--azul-txt);font-size:1.1rem;width:20px;text-align:center"></i>
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:.875rem;color:var(--c900);
                            overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= htmlspecialchars($mitem['nome_original']) ?>
                </div>
                <div style="font-size:.72rem;color:var(--c400)">
                    <?= fmtTamanho((int)$mitem['tamanho']) ?>
                    · <?= fmtDataHora($mitem['criado_em']) ?>
                </div>
            </div>
            <a href="/crmv/uploads/materiais/<?= htmlspecialchars($mitem['nome_arquivo']) ?>"
               target="_blank" class="btn btn-ghost btn-sm" download>
                <i class="fa-solid fa-download"></i> Baixar
            </a>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($totalMateriais === 0): ?>
        <div class="vazio">
            <i class="fa-solid fa-folder-open"></i>
            <h3>Nenhum material disponível</h3>
            <p>Os materiais deste curso ainda não foram publicados.</p>
        </div>
        <?php endif; ?>

    <!-- ─── ABA INFORMAÇÕES ──────────────────────────────── -->
    <?php elseif ($aba === 'info'): ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
            <div>
                <h4 style="font-family:var(--font-titulo);font-size:.9rem;color:var(--azul-esc);
                            margin:0 0 12px;display:flex;align-items:center;gap:7px">
                    <i class="fa-solid fa-circle-info" style="color:var(--verde)"></i> Detalhes do Curso
                </h4>
                <?php foreach ([
                    ['Tipo',          $mat['tipo']],
                    ['Modalidade',    $mat['modalidade']],
                    ['Carga Horária', $mat['carga_horaria'] . 'h'],
                    ['Início',        $mat['data_inicio'] ? fmtData($mat['data_inicio']) : null],
                    ['Término',       $mat['data_fim']    ? fmtData($mat['data_fim'])    : null],
                    ['Horário',       $mat['horario']],
                    ['Categoria',     $mat['cat_nome']],
                ] as [$rot, $val]):
                    if (!$val) continue; ?>
                <div style="display:flex;justify-content:space-between;align-items:center;
                            padding:7px 0;border-bottom:1px solid var(--c100);font-size:.86rem">
                    <span style="color:var(--c500)"><?= $rot ?></span>
                    <strong style="color:var(--c800)"><?= htmlspecialchars($val) ?></strong>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($mat['local_cidade']): ?>
            <div>
                <h4 style="font-family:var(--font-titulo);font-size:.9rem;color:var(--azul-esc);
                            margin:0 0 12px;display:flex;align-items:center;gap:7px">
                    <i class="fa-solid fa-location-dot" style="color:var(--verde)"></i> Local
                </h4>
                <?php foreach ([
                    ['Nome',      $mat['local_nome']],
                    ['Cidade/UF', $mat['local_cidade'] . '/' . $mat['local_uf']],
                    ['Endereço',  $mat['local_endereco']],
                ] as [$rot, $val]):
                    if (!$val) continue; ?>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;
                            padding:7px 0;border-bottom:1px solid var(--c100);font-size:.86rem;gap:12px">
                    <span style="color:var(--c500);flex-shrink:0"><?= $rot ?></span>
                    <span style="color:var(--c700);text-align:right"><?= htmlspecialchars($val) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div>
                <h4 style="font-family:var(--font-titulo);font-size:.9rem;color:var(--azul-esc);
                            margin:0 0 12px;display:flex;align-items:center;gap:7px">
                    <i class="fa-solid fa-id-card" style="color:var(--verde)"></i> Minha Matrícula
                </h4>
                <?php foreach ([
                    ['Nº Matrícula',    '#' . $mat['matricula_id']],
                    ['Data inscrição',  fmtData($mat['matriculado_em'])],
                    ['Nota final',      $mat['nota_final'] ? number_format($mat['nota_final'], 1) : null],
                    ['Presença',        $mat['presenca_percent'] ? (int)$mat['presenca_percent'] . '%' : null],
                    ['Módulos',         !empty($modulos) ? count($modulos) . ' módulo' . (count($modulos) != 1 ? 's' : '') : null],
                    ['Certificado',     $mat['certificado_gerado'] ? 'Emitido' : ($mat['status'] === 'CONCLUIDA' ? 'Disponível' : 'Não disponível')],
                ] as [$rot, $val]):
                    if ($val === null) continue; ?>
                <div style="display:flex;justify-content:space-between;align-items:center;
                            padding:7px 0;border-bottom:1px solid var(--c100);font-size:.86rem">
                    <span style="color:var(--c500)"><?= $rot ?></span>
                    <strong style="color:var(--c800)"><?= htmlspecialchars((string)$val) ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Instrutores na aba info -->
        <?php if (!empty($instrutores)): ?>
        <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--c200)">
            <h4 style="font-family:var(--font-titulo);font-size:.9rem;color:var(--azul-esc);margin:0 0 12px;
                        display:flex;align-items:center;gap:7px">
                <i class="fa-solid fa-chalkboard-teacher" style="color:var(--verde)"></i>
                Instrutor<?= count($instrutores) > 1 ? 'es' : '' ?>
            </h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px">
            <?php foreach ($instrutores as $inst): ?>
            <div style="display:flex;align-items:flex-start;gap:10px;padding:12px;
                        background:var(--c50);border-radius:var(--radius);border:1px solid var(--c200)">
                <div style="width:38px;height:38px;border-radius:50%;background:var(--azul-esc);
                            display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <?php if ($inst['foto']): ?>
                    <img src="/crmv/uploads/fotos/<?= htmlspecialchars($inst['foto']) ?>"
                         style="width:38px;height:38px;border-radius:50%;object-fit:cover">
                    <?php else: ?>
                    <i class="fa-solid fa-user" style="color:rgba(255,255,255,.6);font-size:.8rem"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:.85rem;color:var(--azul-esc)">
                        <?= htmlspecialchars($inst['nome']) ?>
                    </div>
                    <?php if ($inst['titulo_profis']): ?>
                    <div style="font-size:.72rem;color:var(--c500);margin-top:1px">
                        <?= htmlspecialchars($inst['titulo_profis']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <!-- ─── ABA AVALIAÇÃO ────────────────────────────────── -->
    <?php elseif ($aba === 'avaliacao' && $avaliacao_geral): ?>
        <div style="max-width:560px">
            <h3 style="font-family:var(--font-titulo);font-size:1.05rem;color:var(--azul-esc);margin:0 0 6px">
                <?= htmlspecialchars($avaliacao_geral['titulo']) ?>
            </h3>
            <?php if ($avaliacao_geral['descricao']): ?>
            <p style="font-size:.875rem;color:var(--c500);margin:0 0 20px;line-height:1.65">
                <?= nl2br(htmlspecialchars($avaliacao_geral['descricao'])) ?>
            </p>
            <?php endif; ?>

            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:24px">
                <?php foreach ([
                    ['fa-star',         'Nota mínima',  $avaliacao_geral['nota_minima']   ? $avaliacao_geral['nota_minima']   . ' pontos' : 'Não definida'],
                    ['fa-clock',        'Tempo limite', $avaliacao_geral['tempo_limite']   ? $avaliacao_geral['tempo_limite']  . ' min'    : 'Sem limite'],
                    ['fa-rotate-right', 'Tentativas',   $avaliacao_geral['tentativas_max'] ? 'Máx. ' . $avaliacao_geral['tentativas_max'] : 'Ilimitadas'],
                    ['fa-list-ol',      'Tipo',         ucfirst(strtolower($avaliacao_geral['tipo']))],
                ] as [$ico, $rot, $val]): ?>
                <div style="display:flex;align-items:center;gap:10px;font-size:.86rem;
                            padding:9px 14px;background:var(--c50);border-radius:var(--radius);
                            border:1px solid var(--c200)">
                    <i class="fa-solid <?= $ico ?>" style="color:var(--azul-clr);width:16px;text-align:center"></i>
                    <span style="color:var(--c500)"><?= $rot ?>:</span>
                    <strong style="color:var(--c800)"><?= htmlspecialchars($val) ?></strong>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($mat['status'] === 'CONCLUIDA'): ?>
            <div class="alerta alerta-sucesso">
                <i class="fa-solid fa-circle-check"></i>
                Curso concluído! Você já pode emitir seu certificado.
            </div>
            <?php else: ?>
            <a href="/crmv/aluno/avaliacao.php?id=<?= $matricula_id ?>" class="btn btn-primario btn-lg">
                <i class="fa-solid fa-clipboard-question"></i> Iniciar Avaliação
            </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    </div><!-- /card-body -->
</div><!-- /card -->

<?php require_once __DIR__ . '/../includes/layout_aluno_footer.php'; ?>
