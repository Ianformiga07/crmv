<?php
/**
 * curso.php — Página do curso para o aluno
 * /crmv/aluno/curso.php
 *
 * Fluxo:
 *  1. Aulas aparecem como LINKS (não embed). Clicar → abre em nova aba + marca "concluída".
 *     O aluno também pode marcar manualmente via botão "Marcar como concluída".
 *  2. Materiais são opcionais — baixar NÃO é obrigatório para concluir.
 *  3. Quando todas as AULAS estão concluídas, a aba "Avaliação" se desbloqueia.
 *  4. Avaliação de 5 questões (múltipla escolha). Aprovando → aba "Certificado" aparece.
 *  5. Na aba Certificado o aluno pode visualizar ou imprimir o certificado.
 */
require_once __DIR__ . '/../includes/conexao.php';
exigeLogin();
if ((int)($_SESSION['usr_perfil'] ?? 0) === 1) {
    header('Location: /crmv/admin/dashboard.php'); exit;
}

$usr_id       = (int)$_SESSION['usr_id'];
$matricula_id = (int)($_GET['id'] ?? 0);
if (!$matricula_id) { header('Location: /crmv/aluno/dashboard.php'); exit; }

/* ══════════════════════════════════════════════════════════════
   POST — AJAX: marcar aula concluída, baixar material,
          responder avaliação
   ══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    /* ── Marcar aula como concluída (AJAX) ─────────────────── */
    if ($acao === 'concluir_aula') {
        $aula_id = (int)($_POST['aula_id'] ?? 0);
        if ($aula_id) {
            // Verifica que a aula pertence a um módulo deste curso
            $ok = dbQueryOne(
                "SELECT a.aula_id FROM tbl_aulas a
                 INNER JOIN tbl_modulos mo ON a.modulo_id = mo.modulo_id
                 INNER JOIN tbl_matriculas m  ON m.curso_id  = mo.curso_id
                 WHERE a.aula_id = ? AND m.matricula_id = ? AND a.ativo = 1",
                [$aula_id, $matricula_id]
            );
            if ($ok) {
                dbExecute(
                    "INSERT IGNORE INTO tbl_progresso_aulas (matricula_id, aula_id)
                     VALUES (?, ?)",
                    [$matricula_id, $aula_id]
                );
                _recalcProgresso($matricula_id);
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    /* ── Registrar download de material (AJAX, opcional) ──── */
    if ($acao === 'baixar_material') {
        $material_id = (int)($_POST['material_id'] ?? 0);
        if ($material_id) {
            dbExecute(
                "INSERT IGNORE INTO tbl_progresso_materiais (matricula_id, material_id)
                 VALUES (?, ?)",
                [$matricula_id, $material_id]
            );
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    /* ── Responder avaliação (POST normal) ───────────────────  */
    if ($acao === 'responder_avaliacao') {
        $avaliacao_id = (int)($_POST['avaliacao_id'] ?? 0);
        $respostas    = $_POST['resp'] ?? [];

        $av = dbQueryOne(
            "SELECT avaliacao_id, nota_minima, tentativas_max
             FROM tbl_avaliacoes WHERE avaliacao_id = ? AND ativo = 1",
            [$avaliacao_id]
        );
        if (!$av) { header('Location: /crmv/aluno/curso.php?id='.$matricula_id.'&aba=avaliacao'); exit; }

        // Limite de tentativas
        $feitas = (int)dbQueryOne(
            "SELECT COUNT(*) AS n FROM tbl_tentativas_avaliacao
             WHERE matricula_id = ? AND avaliacao_id = ?",
            [$matricula_id, $avaliacao_id]
        )['n'];
        if ($av['tentativas_max'] > 0 && $feitas >= $av['tentativas_max']) {
            flash('Número máximo de tentativas atingido.', 'erro');
            header('Location: /crmv/aluno/curso.php?id='.$matricula_id.'&aba=avaliacao'); exit;
        }

        // Cria tentativa
        dbExecute(
            "INSERT INTO tbl_tentativas_avaliacao (matricula_id, avaliacao_id, iniciado_em)
             VALUES (?, ?, NOW())",
            [$matricula_id, $avaliacao_id]
        );
        $tid = dbLastId();

        // Busca questões e calcula nota
        $questoes = dbQuery(
            "SELECT q.questao_id, q.pontos, a.alternativa_id, a.correta
             FROM tbl_questoes q
             INNER JOIN tbl_alternativas a ON a.questao_id = q.questao_id
             WHERE q.avaliacao_id = ? AND q.ativo = 1",
            [$avaliacao_id]
        );
        $mapa = [];
        foreach ($questoes as $row) {
            if (!isset($mapa[$row['questao_id']])) {
                $mapa[$row['questao_id']] = ['pontos' => $row['pontos'], 'alts' => []];
            }
            $mapa[$row['questao_id']]['alts'][$row['alternativa_id']] = (bool)$row['correta'];
        }

        $total = $obtidos = 0;
        foreach ($mapa as $qid => $qd) {
            $alt     = (int)($respostas[$qid] ?? 0);
            $correta = $alt && ($qd['alts'][$alt] ?? false);
            $total  += $qd['pontos'];
            if ($correta) $obtidos += $qd['pontos'];
            dbExecute(
                "INSERT INTO tbl_respostas_avaliacao (tentativa_id, questao_id, alternativa_id, correta)
                 VALUES (?, ?, ?, ?)",
                [$tid, $qid, $alt ?: null, $correta ? 1 : 0]
            );
        }

        $nota     = $total > 0 ? round($obtidos / $total * 10, 2) : 0;
        $aprovado = $nota >= (float)($av['nota_minima'] ?? 6);
        dbExecute(
            "UPDATE tbl_tentativas_avaliacao SET nota=?, aprovado=?, concluido_em=NOW() WHERE tentativa_id=?",
            [$nota, $aprovado ? 1 : 0, $tid]
        );

        _recalcProgresso($matricula_id);

        if ($aprovado) {
            flash("✓ Parabéns! Você foi aprovado com nota {$nota}. Seu certificado está disponível!", 'sucesso');
            header('Location: /crmv/aluno/curso.php?id='.$matricula_id.'&aba=certificado'); exit;
        } else {
            $notaMin = $av['nota_minima'] ?? 6;
            flash("Nota {$nota} — mínimo exigido: {$notaMin}. Tente novamente.", 'aviso');
            header('Location: /crmv/aluno/curso.php?id='.$matricula_id.'&aba=avaliacao'); exit;
        }
    }
}

/* ── Recalcula progresso_ead (apenas aulas, materiais opcionais) ── */
function _recalcProgresso(int $mat_id): void {
    $m = dbQueryOne("SELECT curso_id FROM tbl_matriculas WHERE matricula_id=?", [$mat_id]);
    if (!$m) return;

    $totalAulas = (int)dbQueryOne(
        "SELECT COUNT(*) AS n FROM tbl_aulas a
         INNER JOIN tbl_modulos mo ON a.modulo_id = mo.modulo_id
         WHERE mo.curso_id = ? AND a.ativo = 1",
        [$m['curso_id']])['n'];

    $feitosAulas = (int)dbQueryOne(
        "SELECT COUNT(*) AS n FROM tbl_progresso_aulas WHERE matricula_id=?", [$mat_id])['n'];

    $porc = $totalAulas > 0 ? min(100, (int)round($feitosAulas / $totalAulas * 100)) : 0;
    dbExecute("UPDATE tbl_matriculas SET progresso_ead=? WHERE matricula_id=?", [$porc, $mat_id]);
}

/* ══════════════════════════════════════════════════════════════
   DADOS DA MATRÍCULA E CURSO
   ══════════════════════════════════════════════════════════════ */
$mat = dbQueryOne(
    "SELECT m.matricula_id, m.status, m.nota_final, m.presenca_percent,
            m.certificado_gerado, m.certificado_codigo, m.progresso_ead,
            m.matriculado_em,
            c.curso_id, c.titulo, c.descricao, c.tipo, c.modalidade,
            c.carga_horaria, c.data_inicio, c.data_fim, c.horario,
            c.local_nome, c.local_cidade, c.local_uf, c.local_endereco,
            c.capa, c.youtube_id, c.link_ead, c.observacoes,
            cat.nome AS cat_nome
     FROM tbl_matriculas m
     INNER JOIN tbl_cursos c ON m.curso_id = c.curso_id
     LEFT  JOIN tbl_categorias cat ON c.categoria_id = cat.categoria_id
     WHERE m.matricula_id = ? AND m.usuario_id = ? AND c.ativo = 1",
    [$matricula_id, $usr_id]
);
if (!$mat) {
    flash('Matrícula não encontrada.', 'erro');
    header('Location: /crmv/aluno/dashboard.php'); exit;
}

/* ── Módulos ─────────────────────────────────────────────────── */
$modulos = dbQuery(
    "SELECT modulo_id, titulo, descricao, ordem
     FROM tbl_modulos WHERE curso_id=? ORDER BY ordem ASC",
    [$mat['curso_id']]
);

/* ── Progresso já registrado ─────────────────────────────────── */
$idsAssistidos = array_column(
    dbQuery("SELECT aula_id FROM tbl_progresso_aulas WHERE matricula_id=?", [$matricula_id]),
    'aula_id'
);
$idsBaixados = array_column(
    dbQuery("SELECT material_id FROM tbl_progresso_materiais WHERE matricula_id=?", [$matricula_id]),
    'material_id'
);

/* ── Monta módulos ───────────────────────────────────────────── */
$totalAulas = $aulasConcluidas = 0;

foreach ($modulos as &$mod) {
    $mod['aulas'] = dbQuery(
        "SELECT aula_id, titulo, descricao, youtube_id, link_externo, duracao_min, ordem
         FROM tbl_aulas WHERE modulo_id=? AND ativo=1 ORDER BY ordem ASC",
        [$mod['modulo_id']]
    );
    $mod['materiais'] = dbQuery(
        "SELECT material_id, nome_original, nome_arquivo, tamanho, tipo_mime
         FROM tbl_materiais WHERE modulo_id=? ORDER BY criado_em ASC",
        [$mod['modulo_id']]
    );

    // Contagem de progresso
    $mod['concluidas'] = 0;
    foreach ($mod['aulas'] as $a) {
        $totalAulas++;
        if (in_array($a['aula_id'], $idsAssistidos)) { $mod['concluidas']++; $aulasConcluidas++; }
    }
    $mod['todas_concluidas'] = !empty($mod['aulas']) && $mod['concluidas'] >= count($mod['aulas']);
}
unset($mod);

/* ── Materiais gerais (sem módulo) ───────────────────────────── */
$matsGerais = dbQuery(
    "SELECT material_id, nome_original, nome_arquivo, tamanho, tipo_mime
     FROM tbl_materiais WHERE curso_id=? AND (modulo_id IS NULL OR modulo_id=0)
     ORDER BY criado_em ASC",
    [$mat['curso_id']]
);

/* ── Todas as aulas concluídas?
   Se não há módulos/aulas cadastrados, considera concluído automaticamente
   (o curso pode ser apenas presencial, ou conteúdo externo sem módulos).
   Também considera concluído se o admin já marcou status = CONCLUIDA,
   ou se o progresso_ead já foi marcado como 100% manualmente.              */
$semModulos = empty($modulos) || $totalAulas === 0;
$todasAulasConcluidas = $semModulos && (
        $mat['status'] === 'CONCLUIDA'
        || (int)$mat['progresso_ead'] >= 100
    )
    || (!$semModulos && $totalAulas > 0 && $aulasConcluidas >= $totalAulas)
    || $mat['status'] === 'CONCLUIDA';

/* ── Avaliação do curso (única, geral) ───────────────────────── */
$avaliacao = dbQueryOne(
    "SELECT avaliacao_id, titulo, descricao, nota_minima, tentativas_max, tempo_limite
     FROM tbl_avaliacoes
     WHERE curso_id=? AND (modulo_id IS NULL OR modulo_id=0) AND ativo=1
     ORDER BY avaliacao_id ASC LIMIT 1",
    [$mat['curso_id']]
);

$melhorTentativa = null;
$avaliacaoAprovada = false;
$tentativasFeitas = 0;

if ($avaliacao) {
    $melhorTentativa = dbQueryOne(
        "SELECT nota, aprovado, concluido_em,
                (SELECT COUNT(*) FROM tbl_tentativas_avaliacao
                 WHERE matricula_id=? AND avaliacao_id=?) AS total_tent
         FROM tbl_tentativas_avaliacao
         WHERE matricula_id=? AND avaliacao_id=? AND concluido_em IS NOT NULL
         ORDER BY nota DESC LIMIT 1",
        [$matricula_id, $avaliacao['avaliacao_id'],
         $matricula_id, $avaliacao['avaliacao_id']]
    );
    $avaliacaoAprovada = $melhorTentativa && (bool)$melhorTentativa['aprovado'];
    $tentativasFeitas  = (int)($melhorTentativa['total_tent'] ?? 0);

    // Busca questões para o formulário
    $rows = dbQuery(
        "SELECT q.questao_id, q.enunciado, q.pontos,
                a.alternativa_id, a.texto AS txt
         FROM tbl_questoes q
         INNER JOIN tbl_alternativas a ON a.questao_id = q.questao_id
         WHERE q.avaliacao_id=? AND q.ativo=1
         ORDER BY q.ordem ASC, a.ordem ASC",
        [$avaliacao['avaliacao_id']]
    );
    $qs = [];
    foreach ($rows as $r) {
        if (!isset($qs[$r['questao_id']])) {
            $qs[$r['questao_id']] = [
                'questao_id' => $r['questao_id'],
                'enunciado'  => $r['enunciado'],
                'pontos'     => $r['pontos'],
                'alts'       => [],
            ];
        }
        $qs[$r['questao_id']]['alts'][] = [
            'alternativa_id' => $r['alternativa_id'],
            'txt'            => $r['txt'],
        ];
    }
    $avaliacao['questoes'] = array_values($qs);
}

/* ── Certificado pode ser emitido? ─────────────────────────────
   Regras:
   • Se status = CONCLUIDA (admin marcou) → sempre pode
   • Se há avaliação → precisa: aulas concluídas + avaliação aprovada
   • Se NÃO há avaliação → precisa apenas: aulas concluídas (ou sem aulas)  */
$semAvaliacao   = !$avaliacao;
$podeEmitirCert = $mat['status'] === 'CONCLUIDA'
    || ($todasAulasConcluidas && ($semAvaliacao || $avaliacaoAprovada));

/* ── Progresso geral (%) ─────────────────────────────────────── */
$porcAulas = $totalAulas > 0 ? min(100, (int)round($aulasConcluidas / $totalAulas * 100)) : 0;
$porcGeral = ($mat['status'] === 'CONCLUIDA' || ($avaliacaoAprovada))
    ? 100
    : ($semModulos ? 0 : $porcAulas);

/* ── Instrutores ─────────────────────────────────────────────── */
$instrutores = dbQuery(
    "SELECT nome, titulo_profis, instituicao, bio, foto
     FROM tbl_curso_instrutores WHERE curso_id=? ORDER BY ordem ASC",
    [$mat['curso_id']]
);

/* ── Aba ativa ───────────────────────────────────────────────── */
$aba = $_GET['aba'] ?? 'conteudo';
// Validação de acesso às abas restritas
if ($aba === 'avaliacao' && !$todasAulasConcluidas && !$avaliacaoAprovada) {
    $aba = 'conteudo';
}
if ($aba === 'certificado' && !$podeEmitirCert && $mat['status'] !== 'CONCLUIDA') {
    $aba = 'conteudo';
}

$pageTitulo  = truncaTexto($mat['titulo'], 40);
$paginaAtiva = 'meus-cursos';
require_once __DIR__ . '/../includes/layout_aluno.php';

/* ── Helpers ─────────────────────────────────────────────────── */
function fmtTam(int $b): string {
    if ($b < 1024) return $b.' B';
    if ($b < 1048576) return round($b/1024,1).' KB';
    return round($b/1048576,1).' MB';
}
function icoArq(string $m): string {
    if (str_contains($m,'pdf'))   return 'fa-file-pdf';
    if (str_contains($m,'word')||str_contains($m,'doc')) return 'fa-file-word';
    if (str_contains($m,'sheet')||str_contains($m,'excel')||str_contains($m,'xls')) return 'fa-file-excel';
    if (str_contains($m,'image')) return 'fa-file-image';
    if (str_contains($m,'video')) return 'fa-file-video';
    if (str_contains($m,'zip')||str_contains($m,'rar')) return 'fa-file-zipper';
    return 'fa-file';
}
?>

<!-- ═══════════════════════════════════════════════════════════
     CABEÇALHO
     ═══════════════════════════════════════════════════════════ -->
<div class="pg-header">
    <div class="pg-header-row">
        <div>
            <h1 class="pg-titulo"><?= htmlspecialchars($mat['titulo']) ?></h1>
            <p class="pg-subtitulo" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <?= badgeModalidade($mat['modalidade']) ?>
                <span><?= htmlspecialchars($mat['tipo']) ?></span>
                <span style="color:var(--c300)">·</span>
                <span><?= $mat['carga_horaria'] ?>h</span>
                <?php if ($mat['cat_nome']): ?>
                <span style="color:var(--c300)">·</span>
                <span><?= htmlspecialchars($mat['cat_nome']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="pg-acoes">
            <?php if ($mat['status'] === 'CONCLUIDA' && $mat['certificado_gerado']): ?>
            <a href="/crmv/aluno/certificado_ver.php?id=<?= $matricula_id ?>"
               class="btn btn-primario">
                <i class="fa-solid fa-certificate"></i> Ver Certificado
            </a>
            <?php endif; ?>
            <a href="/crmv/aluno/dashboard.php" class="btn btn-ghost">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     BARRA DE PROGRESSO
     ═══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px 20px">
        <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap">

            <?php $sl = match($mat['status']) {
                'ATIVA'     => ['b-azul',  'fa-play',  'Em Andamento'],
                'CONCLUIDA' => ['b-verde', 'fa-check', 'Concluído'],
                'CANCELADA' => ['b-verm',  'fa-ban',   'Cancelado'],
                'REPROVADO' => ['b-verm',  'fa-xmark', 'Reprovado'],
                default     => ['b-cinza', 'fa-circle', $mat['status']],
            }; ?>
            <span class="badge <?= $sl[0] ?>">
                <i class="fa-solid <?= $sl[1] ?>"></i> <?= $sl[2] ?>
            </span>

            <!-- Barra visual -->
            <div style="flex:1;min-width:160px">
                <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--c500);margin-bottom:4px">
                    <span>Progresso global</span>
                    <strong style="color:<?= $porcGeral>=100?'var(--verde)':'var(--azul-clr)' ?>">
                        <?= $porcGeral ?>%
                    </strong>
                </div>
                <div style="height:10px;background:var(--c200);border-radius:5px;overflow:hidden">
                    <div style="height:100%;width:<?= $porcGeral ?>%;border-radius:5px;transition:width .5s;
                                background:<?= $porcGeral>=100?'var(--verde)':'var(--azul-clr)' ?>"></div>
                </div>
            </div>

            <!-- Contador de aulas -->
            <?php if (!$semModulos): ?>
            <div style="font-size:.78rem;color:var(--c500);display:flex;align-items:center;gap:6px">
                <i class="fa-solid fa-circle-check" style="color:<?= $todasAulasConcluidas?'var(--verde)':'var(--c300)' ?>"></i>
                <span><?= $aulasConcluidas ?>/<?= $totalAulas ?> aulas concluídas</span>
            </div>
            <?php elseif ($mat['status'] === 'CONCLUIDA'): ?>
            <div style="font-size:.78rem;color:var(--verde);display:flex;align-items:center;gap:6px;font-weight:600">
                <i class="fa-solid fa-circle-check"></i>
                <span>Curso concluído</span>
            </div>
            <?php endif; ?>

            <?php if ($avaliacaoAprovada): ?>
            <span class="badge b-verde">
                <i class="fa-solid fa-trophy"></i> Avaliação aprovada
            </span>
            <?php endif; ?>
        </div>

        <!-- Aviso quando todas as aulas concluídas mas falta avaliação -->
        <?php if ($todasAulasConcluidas && $avaliacao && !$avaliacaoAprovada && $mat['status'] !== 'CONCLUIDA'): ?>
        <div style="margin-top:12px;padding:10px 14px;background:#eff6ff;border:1px solid #93c5fd;
                    border-radius:var(--radius);font-size:.83rem;color:var(--azul-esc)">
            <i class="fa-solid fa-circle-info" style="margin-right:6px"></i>
            <strong>Ótimo!</strong> Você concluiu o conteúdo do curso.
            <a href="?id=<?= $matricula_id ?>&aba=avaliacao" style="color:var(--azul-clr);font-weight:700;margin-left:4px">
                Responda a avaliação para liberar o certificado →
            </a>
        </div>
        <?php endif; ?>

        <!-- Aviso quando pode emitir certificado -->
        <?php if ($podeEmitirCert && $mat['status'] !== 'CONCLUIDA'): ?>
        <div style="margin-top:12px;padding:10px 14px;background:#f0fdf4;border:1px solid #86efac;
                    border-radius:var(--radius);font-size:.83rem;color:#15803d;font-weight:600">
            <i class="fa-solid fa-award" style="margin-right:6px"></i>
            Parabéns! Você completou o curso.
            <a href="?id=<?= $matricula_id ?>&aba=certificado" style="color:var(--verde);margin-left:4px">
                Clique aqui para emitir seu certificado →
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ABAS
     ═══════════════════════════════════════════════════════════ -->
<div class="card">
    <div class="card-header" style="padding:0 16px">
        <div class="tabs-barra" style="border-bottom:none;margin:0;width:100%;flex-wrap:wrap">

            <!-- Conteúdo -->
            <a href="?id=<?= $matricula_id ?>&aba=conteudo"
               class="tab-btn <?= $aba==='conteudo'?'ativo':'' ?>">
                <i class="fa-solid fa-book-open"></i> Conteúdo
            </a>

            <!-- Materiais (sempre visível se houver) -->
            <?php $totalMats = count($matsGerais); foreach ($modulos as $m2) $totalMats += count($m2['materiais']); ?>
            <?php if ($totalMats > 0): ?>
            <a href="?id=<?= $matricula_id ?>&aba=materiais"
               class="tab-btn <?= $aba==='materiais'?'ativo':'' ?>">
                <i class="fa-solid fa-folder-open"></i> Materiais
                <span style="margin-left:4px;font-size:.68rem;padding:1px 6px;border-radius:8px;
                             background:var(--c200);color:var(--c600)"><?= $totalMats ?></span>
            </a>
            <?php endif; ?>

            <!-- Avaliação (desbloqueia quando todas as aulas concluídas) -->
            <?php if ($avaliacao): ?>
            <?php if ($todasAulasConcluidas || $avaliacaoAprovada): ?>
            <a href="?id=<?= $matricula_id ?>&aba=avaliacao"
               class="tab-btn <?= $aba==='avaliacao'?'ativo':'' ?>"
               style="<?= $avaliacaoAprovada?'color:var(--verde)':'' ?>">
                <i class="fa-solid fa-clipboard-question"></i> Avaliação
                <?php if ($avaliacaoAprovada): ?>
                <i class="fa-solid fa-check" style="color:var(--verde);font-size:.7rem;margin-left:3px"></i>
                <?php endif; ?>
            </a>
            <?php else: ?>
            <span class="tab-btn" style="opacity:.4;cursor:not-allowed;pointer-events:none"
                  title="Conclua todas as aulas para desbloquear">
                <i class="fa-solid fa-lock"></i> Avaliação
            </span>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Certificado (só aparece após avaliação aprovada ou status CONCLUIDA) -->
            <?php if ($podeEmitirCert || $mat['status'] === 'CONCLUIDA'): ?>
            <a href="?id=<?= $matricula_id ?>&aba=certificado"
               class="tab-btn <?= $aba==='certificado'?'ativo':'' ?>"
               style="<?= $aba!=='certificado'?'color:var(--verde)':'' ?>">
                <i class="fa-solid fa-award"></i> Certificado
                <span style="margin-left:4px;font-size:.65rem;background:var(--verde);color:#fff;
                             padding:1px 6px;border-radius:8px">Disponível</span>
            </a>
            <?php endif; ?>

            <!-- Informações -->
            <a href="?id=<?= $matricula_id ?>&aba=info"
               class="tab-btn <?= $aba==='info'?'ativo':'' ?>">
                <i class="fa-solid fa-circle-info"></i> Informações
            </a>
        </div>
    </div>

    <div class="card-body" style="padding:20px">

    <?php /* ═══════════════ ABA CONTEÚDO ═══════════════ */ ?>
    <?php if ($aba === 'conteudo'): ?>

        <?php if (empty($modulos)): ?>
        <!-- ══ Sem módulos: conteúdo direto do curso (youtube_id ou link_ead) ══ -->
        <?php
        // Verifica se há alguma "aula virtual" baseada no youtube_id/link_ead do curso
        $linkCursoDireto = '';
        $tipoCursoDireto = '';
        if ($mat['youtube_id']) {
            $linkCursoDireto = 'https://www.youtube.com/watch?v=' . $mat['youtube_id'];
            $tipoCursoDireto = 'youtube';
        } elseif ($mat['link_ead']) {
            $linkCursoDireto = $mat['link_ead'];
            $tipoCursoDireto = 'externo';
        }

        // Chave especial de progresso para curso sem módulos: usa "aula virtual" id=0
        // Verifica se o aluno já marcou este curso como assistido/concluído
        $aulaVirtualConcluida = $mat['status'] === 'CONCLUIDA' || $mat['progresso_ead'] >= 100;

        // Se não há nenhum conteúdo de vídeo/link mas há status CONCLUIDA
        if (!$linkCursoDireto && $mat['status'] === 'CONCLUIDA'):
        ?>
        <div style="text-align:center;padding:30px 20px">
            <div style="width:56px;height:56px;border-radius:50%;background:#dcfce7;
                        display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
                <i class="fa-solid fa-check" style="font-size:1.5rem;color:var(--verde)"></i>
            </div>
            <h3 style="color:var(--azul-esc);margin:0 0 8px">Curso concluído</h3>
            <p style="color:var(--c500);font-size:.88rem;margin:0 0 20px">
                Este curso foi concluído. Acesse a aba Certificado para emitir seu certificado.
            </p>
            <a href="?id=<?= $matricula_id ?>&aba=certificado" class="btn btn-primario">
                <i class="fa-solid fa-award"></i> Ir para o Certificado
            </a>
        </div>

        <?php elseif ($linkCursoDireto): ?>
        <!-- Link do curso (youtube ou externo) como item clicável -->
        <div style="display:flex;flex-direction:column;gap:6px">
            <div id="aula-item-0"
                 style="display:flex;align-items:center;gap:12px;padding:14px 18px;
                        border:1.5px solid <?= $aulaVirtualConcluida?'#86efac':'var(--c200)' ?>;
                        border-radius:var(--radius-lg);
                        background:<?= $aulaVirtualConcluida?'#f0fdf4':'#fff' ?>;transition:background .3s">

                <!-- Ícone de status -->
                <div class="status-ico"
                     style="width:28px;height:28px;border-radius:50%;flex-shrink:0;
                            display:flex;align-items:center;justify-content:center;
                            border:2px solid <?= $aulaVirtualConcluida?'var(--verde)':'var(--c300)' ?>;
                            background:<?= $aulaVirtualConcluida?'var(--verde)':'transparent' ?>">
                    <?php if ($aulaVirtualConcluida): ?>
                    <i class="fa-solid fa-check" style="color:#fff;font-size:.65rem"></i>
                    <?php else: ?>
                    <i class="fa-solid fa-play" style="color:var(--c400);font-size:.6rem"></i>
                    <?php endif; ?>
                </div>

                <!-- Nome e link -->
                <div style="flex:1;min-width:0">
                    <a href="<?= htmlspecialchars($linkCursoDireto) ?>" target="_blank"
                       id="link-aula-0"
                       onclick="marcarCursoAssistido(<?= $matricula_id ?>)"
                       style="font-size:.95rem;font-weight:700;color:var(--azul-esc);
                              text-decoration:none;display:inline-flex;align-items:center;gap:6px">
                        <?= htmlspecialchars($mat['titulo']) ?>
                        <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:.7rem;color:var(--c400)"></i>
                    </a>
                    <div style="font-size:.75rem;color:var(--c400);margin-top:3px">
                        <?= $tipoCursoDireto === 'youtube' ? 'Vídeo no YouTube' : 'Acesso externo' ?>
                        · <?= $mat['carga_horaria'] ?>h
                        <?php if ($aulaVirtualConcluida): ?>
                        · <span style="color:var(--verde);font-weight:600">
                            <i class="fa-solid fa-check-circle"></i> Concluído
                          </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Botões -->
                <div class="aula-acoes" style="flex-shrink:0;display:flex;gap:8px;align-items:center">
                    <?php if ($aulaVirtualConcluida): ?>
                    <span style="font-size:.75rem;color:var(--verde);font-weight:700;white-space:nowrap">
                        <i class="fa-solid fa-circle-check"></i> Concluído
                    </span>
                    <?php else: ?>
                    <a href="<?= htmlspecialchars($linkCursoDireto) ?>" target="_blank"
                       onclick="marcarCursoAssistido(<?= $matricula_id ?>)"
                       class="btn btn-primario btn-sm">
                        <i class="fa-solid <?= $tipoCursoDireto==='youtube'?'fa-brands fa-youtube':'fa-play' ?>"></i>
                        Assistir
                    </a>
                    <button type="button" class="btn btn-ghost btn-sm"
                            onclick="marcarCursoAssistido(<?= $matricula_id ?>)">
                        <i class="fa-solid fa-check"></i> Marcar como concluído
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Materiais gerais logo abaixo do vídeo, se houver -->
        <?php if (!empty($matsGerais)): ?>
        <div style="margin-top:14px;border:1px solid var(--c200);border-radius:var(--radius-lg);overflow:hidden">
            <div style="padding:10px 16px;background:var(--c100);font-size:.78rem;font-weight:700;
                        text-transform:uppercase;letter-spacing:.06em;color:var(--c600)">
                <i class="fa-solid fa-folder-open" style="color:var(--ouro)"></i>
                Materiais de Apoio
                <span style="font-weight:400;color:var(--c400)">(opcionais)</span>
            </div>
            <div style="padding:10px 14px;display:flex;flex-wrap:wrap;gap:6px">
            <?php foreach ($matsGerais as $mi):
                $baixou = in_array($mi['material_id'], $idsBaixados); ?>
            <a href="/crmv/uploads/materiais/<?= htmlspecialchars($mi['nome_arquivo']) ?>"
               target="_blank" download
               onclick="registrarDownload(<?= $mi['material_id'] ?>, <?= $matricula_id ?>)"
               style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:.78rem;
                      text-decoration:none;border-radius:var(--radius);
                      border:1px solid <?= $baixou?'#86efac':'var(--c200)' ?>;
                      background:<?= $baixou?'#f0fdf4':'var(--c50)' ?>;
                      color:<?= $baixou?'#15803d':'var(--c700)' ?>">
                <i class="fa-solid <?= icoArq($mi['tipo_mime']??'') ?>" style="font-size:.82rem"></i>
                <?= htmlspecialchars(truncaTexto($mi['nome_original'],38)) ?>
                <span style="font-size:.65rem;color:var(--c400)"><?= fmtTam((int)$mi['tamanho']) ?></span>
                <i class="fa-solid <?= $baixou?'fa-check':'fa-download' ?>" style="font-size:.65rem;opacity:.7"></i>
            </a>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Sem conteúdo nem link -->
        <div class="alerta alerta-aviso">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Nenhum conteúdo disponível ainda. Entre em contato com o CRMV-TO.
        </div>
        <?php endif; ?>


        <?php else: ?>
        <!-- Com módulos -->
        <div style="display:flex;flex-direction:column;gap:6px">

        <?php foreach ($modulos as $midx => $mod):
            $totMod  = count($mod['aulas']);
            $concMod = $mod['concluidas'];
            $pMod    = $totMod > 0 ? min(100,(int)round($concMod/$totMod*100)) : 0;
            $mostrouAlgo = false;
        ?>

        <!-- MÓDULO -->
        <div style="border:1.5px solid <?= $mod['todas_concluidas']?'#86efac':'var(--c200)' ?>;
                    border-radius:var(--radius-lg);overflow:hidden;
                    background:<?= $mod['todas_concluidas']?'#f0fdf4':'#fff' ?>">

            <!-- Cabeçalho do módulo -->
            <div style="padding:12px 18px;display:flex;align-items:center;gap:12px;
                        background:<?= $mod['todas_concluidas']?'#dcfce7':'var(--azul-esc)' ?>;
                        cursor:pointer;user-select:none"
                 onclick="toggleModulo(<?= $midx ?>)">
                <div style="width:32px;height:32px;border-radius:50%;flex-shrink:0;
                            display:flex;align-items:center;justify-content:center;font-weight:700;
                            font-size:.85rem;
                            background:<?= $mod['todas_concluidas']?'var(--verde)':'rgba(201,162,39,.25)' ?>;
                            border:2px solid <?= $mod['todas_concluidas']?'var(--verde)':'#c9a227' ?>;
                            color:<?= $mod['todas_concluidas']?'#fff':'#c9a227' ?>">
                    <?= $mod['todas_concluidas']
                        ? '<i class="fa-solid fa-check"></i>'
                        : ($midx+1) ?>
                </div>
                <div style="flex:1">
                    <div style="font-weight:700;font-size:.93rem;
                                color:<?= $mod['todas_concluidas']?'#15803d':'#fff' ?>">
                        <?= htmlspecialchars($mod['titulo']) ?>
                    </div>
                    <div style="font-size:.72rem;margin-top:2px;
                                color:<?= $mod['todas_concluidas']?'#16a34a':'rgba(255,255,255,.6)' ?>">
                        <?= $concMod ?>/<?= $totMod ?> atividade<?= $totMod!=1?'s':'' ?> concluída<?= $totMod!=1?'s':'' ?>
                    </div>
                </div>
                <!-- mini barra -->
                <div style="min-width:80px;text-align:right">
                    <div style="height:5px;background:<?= $mod['todas_concluidas']?'#bbf7d0':'rgba(255,255,255,.2)' ?>;
                                border-radius:3px;overflow:hidden">
                        <div style="height:100%;width:<?= $pMod ?>%;border-radius:3px;
                                    background:<?= $pMod>=100?'var(--verde)':'#c9a227' ?>;transition:width .5s"></div>
                    </div>
                    <div style="font-size:.65rem;color:<?= $mod['todas_concluidas']?'#16a34a':'rgba(255,255,255,.5)' ?>;margin-top:3px">
                        <?= $pMod ?>%
                    </div>
                </div>
                <i class="fa-solid fa-chevron-down" id="chevron-<?= $midx ?>"
                   style="color:<?= $mod['todas_concluidas']?'#16a34a':'rgba(255,255,255,.6)' ?>;
                          font-size:.75rem;transition:transform .3s;flex-shrink:0"></i>
            </div>

            <!-- Conteúdo do módulo (expansível) -->
            <div id="mod-body-<?= $midx ?>" style="display:block">
            <div style="padding:4px 0">

                <?php /* ── Aulas como LINKS ── */ ?>
                <?php foreach ($mod['aulas'] as $ai => $aula):
                    $concluida = in_array($aula['aula_id'], $idsAssistidos);
                    $mostrouAlgo = true;
                ?>
                <div id="aula-item-<?= $aula['aula_id'] ?>"
                     style="display:flex;align-items:center;gap:12px;padding:11px 18px;
                            border-bottom:1px solid var(--c100);
                            background:<?= $concluida?'#f0fdf4':'#fff' ?>;
                            transition:background .3s">

                    <!-- Ícone de status -->
                    <div style="width:24px;height:24px;border-radius:50%;flex-shrink:0;
                                display:flex;align-items:center;justify-content:center;
                                border:2px solid <?= $concluida?'var(--verde)':'var(--c300)' ?>;
                                background:<?= $concluida?'var(--verde)':'transparent' ?>">
                        <?php if ($concluida): ?>
                        <i class="fa-solid fa-check" style="color:#fff;font-size:.6rem"></i>
                        <?php else: ?>
                        <i class="fa-solid fa-play" style="color:var(--c400);font-size:.55rem"></i>
                        <?php endif; ?>
                    </div>

                    <!-- Link da aula -->
                    <div style="flex:1;min-width:0">
                        <?php
                        // Determina o URL da aula
                        $linkAula = '';
                        $tipoLink = '';
                        if ($aula['youtube_id']) {
                            $linkAula = 'https://www.youtube.com/watch?v=' . $aula['youtube_id'];
                            $tipoLink = 'youtube';
                        } elseif ($aula['link_externo']) {
                            $linkAula = $aula['link_externo'];
                            $tipoLink = 'externo';
                        }
                        ?>
                        <?php if ($linkAula): ?>
                        <a href="<?= htmlspecialchars($linkAula) ?>" target="_blank"
                           onclick="marcarConcluida(<?= $aula['aula_id'] ?>, <?= $matricula_id ?>)"
                           style="font-size:.88rem;font-weight:<?= $concluida?'500':'600' ?>;
                                  color:<?= $concluida?'var(--c500)':'var(--azul-esc)' ?>;
                                  text-decoration:none;display:block">
                            <?= htmlspecialchars($aula['titulo']) ?>
                            <i class="fa-solid fa-arrow-up-right-from-square"
                               style="font-size:.65rem;margin-left:4px;color:var(--c400)"></i>
                        </a>
                        <?php else: ?>
                        <span style="font-size:.88rem;font-weight:600;color:var(--c500)">
                            <?= htmlspecialchars($aula['titulo']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($aula['descricao']): ?>
                        <div style="font-size:.72rem;color:var(--c400);margin-top:2px">
                            <?= htmlspecialchars(truncaTexto($aula['descricao'],80)) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Duração -->
                    <?php if ($aula['duracao_min']): ?>
                    <span style="font-size:.7rem;color:var(--c400);flex-shrink:0;white-space:nowrap">
                        <i class="fa-solid fa-clock"></i> <?= $aula['duracao_min'] ?>min
                    </span>
                    <?php endif; ?>

                    <!-- Ações -->
                    <div style="flex-shrink:0;display:flex;gap:6px;align-items:center">
                        <?php if ($concluida): ?>
                        <span style="font-size:.72rem;color:var(--verde);font-weight:700;white-space:nowrap">
                            <i class="fa-solid fa-circle-check"></i> Concluída
                        </span>
                        <?php else: ?>
                        <?php if ($linkAula): ?>
                        <a href="<?= htmlspecialchars($linkAula) ?>" target="_blank"
                           onclick="marcarConcluida(<?= $aula['aula_id'] ?>, <?= $matricula_id ?>)"
                           class="btn btn-primario btn-sm">
                            <i class="fa-solid <?= $tipoLink==='youtube'?'fa-brands fa-youtube':'fa-play' ?>"></i>
                            Assistir
                        </a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-ghost btn-sm"
                                onclick="marcarConcluida(<?= $aula['aula_id'] ?>, <?= $matricula_id ?>)"
                                title="Marcar como concluída sem abrir o link">
                            <i class="fa-solid fa-check"></i> Marcar concluída
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php /* ── Materiais do módulo (opcionais, compactos) ── */ ?>
                <?php if (!empty($mod['materiais'])): ?>
                <div style="padding:10px 18px 6px;border-bottom:1px solid var(--c100)">
                    <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;
                                letter-spacing:.08em;color:var(--c400);margin-bottom:6px">
                        <i class="fa-solid fa-folder-open" style="color:var(--ouro)"></i>
                        Materiais de Apoio
                        <span style="font-weight:400;color:var(--c300)">(opcional)</span>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:6px">
                    <?php foreach ($mod['materiais'] as $mi):
                        $baixou = in_array($mi['material_id'], $idsBaixados);
                    ?>
                    <a href="/crmv/uploads/materiais/<?= htmlspecialchars($mi['nome_arquivo']) ?>"
                       target="_blank" download
                       onclick="registrarDownload(<?= $mi['material_id'] ?>, <?= $matricula_id ?>)"
                       style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px;
                              font-size:.75rem;text-decoration:none;border-radius:var(--radius);
                              border:1px solid <?= $baixou?'#86efac':'var(--c200)' ?>;
                              background:<?= $baixou?'#f0fdf4':'#fff' ?>;
                              color:<?= $baixou?'#15803d':'var(--c700)' ?>">
                        <i class="fa-solid <?= icoArq($mi['tipo_mime']??'') ?>" style="font-size:.8rem"></i>
                        <?= htmlspecialchars(truncaTexto($mi['nome_original'],35)) ?>
                        <i class="fa-solid <?= $baixou?'fa-check':'fa-download' ?>"
                           style="font-size:.65rem;opacity:.7"></i>
                    </a>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$mostrouAlgo && empty($mod['materiais'])): ?>
                <div style="padding:14px 18px;font-size:.82rem;color:var(--c400);text-align:center">
                    <i class="fa-solid fa-hourglass-half"></i> Conteúdo em breve
                </div>
                <?php endif; ?>

            </div>
            </div><!-- /mod-body -->
        </div><!-- /módulo -->
        <?php endforeach; ?>
        </div><!-- /flex módulos -->

        <!-- Materiais gerais (se houver) -->
        <?php if (!empty($matsGerais)): ?>
        <div style="margin-top:16px;border:1.5px solid var(--c200);border-radius:var(--radius-lg);overflow:hidden">
            <div style="padding:12px 18px;background:var(--c700);color:#fff;
                        font-size:.85rem;font-weight:700;display:flex;align-items:center;gap:8px">
                <i class="fa-solid fa-folder-open" style="color:var(--ouro)"></i>
                Materiais Gerais
                <span style="font-size:.68rem;font-weight:400;opacity:.7">(opcionais)</span>
            </div>
            <div style="padding:10px 16px;display:flex;flex-wrap:wrap;gap:6px">
            <?php foreach ($matsGerais as $mi):
                $baixou = in_array($mi['material_id'], $idsBaixados);
            ?>
            <a href="/crmv/uploads/materiais/<?= htmlspecialchars($mi['nome_arquivo']) ?>"
               target="_blank" download
               onclick="registrarDownload(<?= $mi['material_id'] ?>, <?= $matricula_id ?>)"
               style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:.78rem;
                      text-decoration:none;border-radius:var(--radius);
                      border:1px solid <?= $baixou?'#86efac':'var(--c200)' ?>;
                      background:<?= $baixou?'#f0fdf4':'var(--c50)' ?>;
                      color:<?= $baixou?'#15803d':'var(--c700)' ?>">
                <i class="fa-solid <?= icoArq($mi['tipo_mime']??'') ?>" style="font-size:.82rem"></i>
                <?= htmlspecialchars(truncaTexto($mi['nome_original'],40)) ?>
                <span style="font-size:.65rem;color:var(--c400)"><?= fmtTam((int)$mi['tamanho']) ?></span>
                <i class="fa-solid <?= $baixou?'fa-check':'fa-download' ?>" style="font-size:.65rem;opacity:.7"></i>
            </a>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; // if modulos ?>

    <?php /* ═══════════════ ABA MATERIAIS ═══════════════ */ ?>
    <?php elseif ($aba === 'materiais'): ?>

        <?php if ($totalMats === 0): ?>
        <div class="vazio">
            <i class="fa-solid fa-folder-open"></i>
            <h3>Nenhum material disponível</h3>
            <p>Os materiais deste curso ainda não foram publicados.</p>
        </div>
        <?php else: ?>

        <p style="font-size:.83rem;color:var(--c500);margin:0 0 16px">
            <i class="fa-solid fa-circle-info" style="color:var(--azul-clr)"></i>
            Os materiais são opcionais e não afetam a conclusão do curso.
        </p>

        <?php foreach ($modulos as $midx => $mod):
            if (empty($mod['materiais'])) continue; ?>
        <div style="margin-bottom:18px">
            <div style="font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
                        color:var(--azul-esc);margin-bottom:8px;display:flex;align-items:center;gap:8px">
                <span style="background:var(--azul-esc);color:#fff;padding:2px 8px;border-radius:10px;font-size:.68rem">
                    Módulo <?= $midx+1 ?>
                </span>
                <?= htmlspecialchars($mod['titulo']) ?>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px">
            <?php foreach ($mod['materiais'] as $mi):
                $baixou = in_array($mi['material_id'], $idsBaixados);
            ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;
                        border:1px solid <?= $baixou?'#86efac':'var(--c200)' ?>;
                        background:<?= $baixou?'#f0fdf4':'var(--c50)' ?>;border-radius:var(--radius)">
                <i class="fa-solid <?= icoArq($mi['tipo_mime']??'') ?>"
                   style="color:var(--azul-txt);font-size:1rem;width:18px;flex-shrink:0"></i>
                <div style="flex:1;min-width:0">
                    <div style="font-size:.85rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= htmlspecialchars($mi['nome_original']) ?>
                    </div>
                    <div style="font-size:.68rem;color:var(--c400)">
                        <?= fmtTam((int)$mi['tamanho']) ?>
                        <?php if ($baixou): ?>
                        · <span style="color:var(--verde)"><i class="fa-solid fa-check"></i> Baixado</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="/crmv/uploads/materiais/<?= htmlspecialchars($mi['nome_arquivo']) ?>"
                   target="_blank" download
                   onclick="registrarDownload(<?= $mi['material_id'] ?>, <?= $matricula_id ?>)"
                   class="btn btn-sm <?= $baixou?'btn-ghost':'btn-secundario' ?>">
                    <i class="fa-solid <?= $baixou?'fa-check':'fa-download' ?>"></i>
                    <?= $baixou ? 'Baixar novamente' : 'Baixar' ?>
                </a>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($matsGerais)): ?>
        <?php if (array_filter($modulos, fn($m) => !empty($m['materiais']))): ?>
        <div style="font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
                    color:var(--c500);margin-bottom:8px;margin-top:8px">
            <i class="fa-solid fa-folder-open"></i> Materiais Gerais
        </div>
        <?php endif; ?>
        <div style="display:flex;flex-direction:column;gap:6px">
        <?php foreach ($matsGerais as $mi):
            $baixou = in_array($mi['material_id'], $idsBaixados);
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;
                    border:1px solid <?= $baixou?'#86efac':'var(--c200)' ?>;
                    background:<?= $baixou?'#f0fdf4':'var(--c50)' ?>;border-radius:var(--radius)">
            <i class="fa-solid <?= icoArq($mi['tipo_mime']??'') ?>"
               style="color:var(--azul-txt);font-size:1rem;width:18px;flex-shrink:0"></i>
            <div style="flex:1;min-width:0">
                <div style="font-size:.85rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= htmlspecialchars($mi['nome_original']) ?>
                </div>
                <div style="font-size:.68rem;color:var(--c400)">
                    <?= fmtTam((int)$mi['tamanho']) ?>
                    <?php if ($baixou): ?>
                    · <span style="color:var(--verde)"><i class="fa-solid fa-check"></i> Baixado</span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="/crmv/uploads/materiais/<?= htmlspecialchars($mi['nome_arquivo']) ?>"
               target="_blank" download
               onclick="registrarDownload(<?= $mi['material_id'] ?>, <?= $matricula_id ?>)"
               class="btn btn-sm <?= $baixou?'btn-ghost':'btn-secundario' ?>">
                <i class="fa-solid <?= $baixou?'fa-check':'fa-download' ?>"></i>
                <?= $baixou ? 'Baixar novamente' : 'Baixar' ?>
            </a>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; // totalMats ?>

    <?php /* ═══════════════ ABA AVALIAÇÃO ═══════════════ */ ?>
    <?php elseif ($aba === 'avaliacao'): ?>

        <?php if (!$avaliacao): ?>
        <div class="alerta alerta-info">
            <i class="fa-solid fa-circle-info"></i>
            Nenhuma avaliação cadastrada para este curso.
        </div>

        <?php elseif (!$todasAulasConcluidas && !$avaliacaoAprovada): ?>
        <!-- Bloqueado -->
        <div style="text-align:center;padding:40px 20px">
            <div style="width:64px;height:64px;border-radius:50%;background:var(--c100);
                        display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <i class="fa-solid fa-lock" style="font-size:1.6rem;color:var(--c400)"></i>
            </div>
            <h3 style="font-family:var(--font-titulo);color:var(--azul-esc);margin:0 0 8px">
                Avaliação bloqueada
            </h3>
            <p style="color:var(--c500);font-size:.88rem;max-width:400px;margin:0 auto 20px">
                Conclua todas as aulas do curso para desbloquear a avaliação.
            </p>
            <div style="font-size:.83rem;color:var(--c500)">
                <i class="fa-solid fa-circle-check" style="color:var(--azul-clr)"></i>
                <?= $aulasConcluidas ?>/<?= $totalAulas ?> aulas concluídas
            </div>
            <a href="?id=<?= $matricula_id ?>&aba=conteudo" class="btn btn-ghost" style="margin-top:16px">
                <i class="fa-solid fa-arrow-left"></i> Voltar ao conteúdo
            </a>
        </div>

        <?php elseif ($avaliacaoAprovada): ?>
        <!-- Já aprovado -->
        <div style="text-align:center;padding:40px 20px">
            <div style="width:72px;height:72px;border-radius:50%;background:#dcfce7;
                        display:flex;align-items:center;justify-content:margin;margin:0 auto 16px">
                <i class="fa-solid fa-trophy" style="font-size:2rem;color:var(--verde);margin:auto"></i>
            </div>
            <h3 style="font-family:var(--font-titulo);color:var(--verde);margin:16px 0 8px">
                Parabéns! Avaliação concluída com sucesso.
            </h3>
            <p style="color:var(--c500);font-size:.9rem;margin:0 0 8px">
                Sua melhor nota foi
                <strong style="font-size:1.4rem;color:var(--verde)"><?= $melhorTentativa['nota'] ?></strong>
                de 10.
            </p>
            <p style="color:var(--c400);font-size:.78rem;margin:0 0 24px">
                Você realizou <?= $tentativasFeitas ?> tentativa<?= $tentativasFeitas!=1?'s':'' ?>.
            </p>
            <a href="?id=<?= $matricula_id ?>&aba=certificado" class="btn btn-primario btn-lg">
                <i class="fa-solid fa-award"></i> Ir para o Certificado
            </a>
        </div>

        <?php else: ?>
        <!-- Formulário de avaliação -->
        <div style="max-width:700px">

            <div style="margin-bottom:20px">
                <h3 style="font-family:var(--font-titulo);font-size:1rem;color:var(--azul-esc);margin:0 0 6px">
                    <i class="fa-solid fa-clipboard-question" style="color:var(--azul-clr);margin-right:6px"></i>
                    <?= htmlspecialchars($avaliacao['titulo']) ?>
                </h3>
                <?php if ($avaliacao['descricao']): ?>
                <p style="font-size:.875rem;color:var(--c500);margin:0;line-height:1.65">
                    <?= nl2br(htmlspecialchars($avaliacao['descricao'])) ?>
                </p>
                <?php endif; ?>
            </div>

            <!-- Regras da avaliação -->
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px">
                <?php foreach ([
                    ['fa-list-ol',      'Questões',     count($avaliacao['questoes']).' questões'],
                    ['fa-star',         'Nota mínima',  ($avaliacao['nota_minima'] ?? 6).' pontos'],
                    ['fa-clock',        'Tempo limite',  $avaliacao['tempo_limite']?$avaliacao['tempo_limite'].' min':'Sem limite'],
                    ['fa-rotate-right', 'Tentativas',    $avaliacao['tentativas_max']?'Máx. '.$avaliacao['tentativas_max']:'Ilimitadas'],
                ] as [$ic,$rot,$val]): ?>
                <div style="display:flex;align-items:center;gap:7px;font-size:.82rem;
                            padding:6px 12px;background:var(--c50);border:1px solid var(--c200);
                            border-radius:var(--radius)">
                    <i class="fa-solid <?= $ic ?>" style="color:var(--azul-clr)"></i>
                    <span style="color:var(--c500)"><?= $rot ?>:</span>
                    <strong><?= htmlspecialchars($val) ?></strong>
                </div>
                <?php endforeach; ?>
                <?php if ($tentativasFeitas > 0): ?>
                <div style="display:flex;align-items:center;gap:7px;font-size:.82rem;
                            padding:6px 12px;background:#fff7ed;border:1px solid #fde68a;
                            border-radius:var(--radius)">
                    <i class="fa-solid fa-rotate" style="color:var(--ouro)"></i>
                    <span style="color:var(--c500)">Tentativas feitas:</span>
                    <strong><?= $tentativasFeitas ?></strong>
                    <?php if ($melhorTentativa): ?>
                    <span style="color:var(--c400)">· Melhor nota: <strong style="color:var(--verm)"><?= $melhorTentativa['nota'] ?></strong></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (empty($avaliacao['questoes'])): ?>
            <div class="alerta alerta-aviso">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Esta avaliação ainda não tem questões cadastradas. Aguarde.
            </div>

            <?php else: ?>
            <form method="POST" id="form-avaliacao" onsubmit="return confirmarEnvio(this)">
                <input type="hidden" name="acao"          value="responder_avaliacao">
                <input type="hidden" name="avaliacao_id"  value="<?= $avaliacao['avaliacao_id'] ?>">

                <div style="display:flex;flex-direction:column;gap:16px;margin-bottom:24px">
                <?php foreach ($avaliacao['questoes'] as $qi => $q): ?>
                <div style="border:1.5px solid var(--c200);border-radius:var(--radius);overflow:hidden"
                     id="q-wrap-<?= $q['questao_id'] ?>">
                    <!-- Enunciado -->
                    <div style="padding:12px 16px;background:var(--azul-esc);color:#fff;
                                font-size:.875rem;font-weight:700;display:flex;align-items:flex-start;gap:10px">
                        <span style="min-width:22px;height:22px;border-radius:50%;background:#c9a227;
                                     color:var(--azul-esc);display:flex;align-items:center;justify-content:center;
                                     font-size:.75rem;font-weight:800;flex-shrink:0">
                            <?= $qi+1 ?>
                        </span>
                        <span><?= htmlspecialchars($q['enunciado']) ?></span>
                    </div>
                    <!-- Alternativas -->
                    <div style="padding:8px 16px;display:flex;flex-direction:column;gap:2px;background:#fff">
                    <?php foreach ($q['alts'] as $alt): ?>
                    <label style="display:flex;align-items:flex-start;gap:10px;padding:8px 10px;
                                  cursor:pointer;border-radius:6px;font-size:.86rem;color:var(--c700);
                                  transition:background .15s"
                           onmouseover="this.style.background='#f8fafc'"
                           onmouseout="this.style.background='transparent'"
                           onclick="this.closest('.alt-group')?.querySelectorAll('label').forEach(l=>l.style.background='transparent');this.style.background='#eff6ff'">
                        <input type="radio"
                               name="resp[<?= $q['questao_id'] ?>]"
                               value="<?= $alt['alternativa_id'] ?>"
                               required
                               style="margin-top:3px;accent-color:var(--azul-clr);flex-shrink:0">
                        <?= htmlspecialchars($alt['txt']) ?>
                    </label>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>

                <!-- Rodapé do formulário -->
                <div style="padding:16px;background:var(--c50);border:1px solid var(--c200);
                            border-radius:var(--radius);display:flex;align-items:center;
                            justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <div style="font-size:.82rem;color:var(--c500)">
                        <i class="fa-solid fa-circle-info"></i>
                        Responda todas as <?= count($avaliacao['questoes']) ?> questões antes de enviar.
                        A aprovação libera o certificado.
                    </div>
                    <button type="submit" class="btn btn-primario btn-lg">
                        <i class="fa-solid fa-paper-plane"></i> Enviar Avaliação
                    </button>
                </div>
            </form>
            <?php endif; ?>

        </div>
        <?php endif; // estados da avaliação ?>

    <?php /* ═══════════════ ABA CERTIFICADO ═══════════════ */ ?>
    <?php elseif ($aba === 'certificado'): ?>

        <?php if (!$podeEmitirCert && $mat['status'] !== 'CONCLUIDA'): ?>
        <!-- Sem acesso -->
        <div style="text-align:center;padding:40px 20px">
            <i class="fa-solid fa-lock" style="font-size:2.5rem;color:var(--c300);margin-bottom:16px;display:block"></i>
            <h3 style="color:var(--azul-esc);margin:0 0 8px">Certificado ainda não disponível</h3>
            <p style="color:var(--c500);font-size:.88rem">
                Conclua todas as aulas e seja aprovado na avaliação para liberar seu certificado.
            </p>
            <a href="?id=<?= $matricula_id ?>&aba=conteudo" class="btn btn-ghost" style="margin-top:16px">
                <i class="fa-solid fa-arrow-left"></i> Voltar ao conteúdo
            </a>
        </div>

        <?php else: ?>
        <!-- Certificado disponível -->
        <div style="max-width:600px;margin:0 auto;text-align:center;padding:10px 0">

            <!-- Medalha / ícone -->
            <div style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,#c9a227,#f5d060);
                        display:flex;align-items:center;justify-content:center;
                        margin:0 auto 20px;box-shadow:0 4px 20px rgba(201,162,39,.35)">
                <i class="fa-solid fa-award" style="font-size:2.4rem;color:#fff"></i>
            </div>

            <h2 style="font-family:var(--font-titulo);color:var(--azul-esc);margin:0 0 8px;font-size:1.3rem">
                Certificado de Conclusão
            </h2>
            <p style="font-size:.95rem;color:var(--c500);margin:0 0 6px">
                <?= htmlspecialchars($mat['titulo']) ?>
            </p>
            <p style="font-size:.83rem;color:var(--c400);margin:0 0 30px">
                Carga horária: <?= $mat['carga_horaria'] ?>h
                <?php if ($melhorTentativa): ?>
                · Nota: <strong style="color:var(--verde)"><?= $melhorTentativa['nota'] ?></strong>
                <?php endif; ?>
            </p>

            <!-- Código do certificado (se já emitido) -->
            <?php if ($mat['certificado_gerado'] && $mat['certificado_codigo']): ?>
            <div style="margin-bottom:24px;padding:12px 20px;background:var(--c50);
                        border:1px solid var(--c200);border-radius:var(--radius);display:inline-block">
                <div style="font-size:.7rem;color:var(--c400);margin-bottom:4px;text-transform:uppercase;letter-spacing:.08em">
                    Código do Certificado
                </div>
                <div style="font-size:1rem;font-weight:800;color:var(--azul-esc);letter-spacing:.1em;font-family:monospace">
                    <?= htmlspecialchars($mat['certificado_codigo']) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Botões de ação -->
            <div style="display:flex;flex-direction:column;align-items:center;gap:12px">

                <?php if ($mat['certificado_gerado']): ?>
                <!-- Certificado já emitido: visualizar e imprimir -->
                <a href="/crmv/aluno/certificado_ver.php?id=<?= $matricula_id ?>"
                   class="btn btn-primario btn-lg" style="min-width:280px">
                    <i class="fa-solid fa-eye"></i> Visualizar Certificado
                </a>
                <a href="/crmv/aluno/imprimir_cert.php?codigo=<?= htmlspecialchars($mat['certificado_codigo']) ?>"
                   target="_blank"
                   class="btn btn-secundario btn-lg" style="min-width:280px">
                    <i class="fa-solid fa-print"></i> Imprimir / Salvar PDF
                </a>

                <?php elseif ($mat['status'] === 'CONCLUIDA'): ?>
                <!-- Status CONCLUIDA mas não emitiu ainda -->
                <a href="/crmv/aluno/emitir_certificado.php?id=<?= $matricula_id ?>"
                   class="btn btn-primario btn-lg" style="min-width:280px">
                    <i class="fa-solid fa-certificate"></i> Emitir Certificado
                </a>
                <p style="font-size:.78rem;color:var(--c400)">
                    Ao emitir, o certificado fica disponível para visualização e impressão a qualquer momento.
                </p>

                <?php elseif ($podeEmitirCert): ?>
                <!-- Pode emitir (avaliação aprovada, mas admin ainda não marcou CONCLUIDA) -->
                <div class="alerta alerta-aviso" style="text-align:left;max-width:480px">
                    <i class="fa-solid fa-clock"></i>
                    <div>
                        <strong>Aguardando confirmação</strong><br>
                        <span style="font-size:.82rem">
                            Você concluiu todas as etapas! O administrador irá confirmar sua conclusão
                            e liberar o certificado em breve. Caso demore, entre em contato com o CRMV-TO.
                        </span>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>

    <?php /* ═══════════════ ABA INFORMAÇÕES ═══════════════ */ ?>
    <?php elseif ($aba === 'info'): ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px">
            <div>
                <h4 style="font-family:var(--font-titulo);font-size:.88rem;color:var(--azul-esc);margin:0 0 10px">
                    <i class="fa-solid fa-circle-info" style="color:var(--verde)"></i> Detalhes do Curso
                </h4>
                <?php foreach ([
                    ['Tipo',         $mat['tipo']],
                    ['Modalidade',   $mat['modalidade']],
                    ['Carga Horária',$mat['carga_horaria'].'h'],
                    ['Início',       $mat['data_inicio']?fmtData($mat['data_inicio']):null],
                    ['Término',      $mat['data_fim']?fmtData($mat['data_fim']):null],
                    ['Horário',      $mat['horario']],
                ] as [$r,$v]): if (!$v) continue; ?>
                <div style="display:flex;justify-content:space-between;padding:6px 0;
                            border-bottom:1px solid var(--c100);font-size:.84rem">
                    <span style="color:var(--c500)"><?= $r ?></span>
                    <strong><?= htmlspecialchars($v) ?></strong>
                </div>
                <?php endforeach; ?>
            </div>

            <div>
                <h4 style="font-family:var(--font-titulo);font-size:.88rem;color:var(--azul-esc);margin:0 0 10px">
                    <i class="fa-solid fa-id-card" style="color:var(--verde)"></i> Meu Progresso
                </h4>
                <?php foreach ([
                    ['Matrícula',    '#'.$mat['matricula_id']],
                    ['Inscrito em',  fmtData($mat['matriculado_em'])],
                    ['Aulas',        $aulasConcluidas.'/'.$totalAulas.' concluídas'],
                    ['Avaliação',    $avaliacaoAprovada ? 'Aprovado (nota '.$melhorTentativa['nota'].')' : ($tentativasFeitas>0?'Reprovado ('.$tentativasFeitas.' tentativas)':'Pendente')],
                    ['Certificado',  $mat['certificado_gerado']?'Emitido':($podeEmitirCert?'Disponível':'Não disponível')],
                    ['Nota final',   $mat['nota_final']?number_format($mat['nota_final'],1):null],
                ] as [$r,$v]): if ($v===null) continue; ?>
                <div style="display:flex;justify-content:space-between;padding:6px 0;
                            border-bottom:1px solid var(--c100);font-size:.84rem">
                    <span style="color:var(--c500)"><?= $r ?></span>
                    <strong style="text-align:right;max-width:200px"><?= htmlspecialchars((string)$v) ?></strong>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($mat['local_cidade']): ?>
            <div>
                <h4 style="font-family:var(--font-titulo);font-size:.88rem;color:var(--azul-esc);margin:0 0 10px">
                    <i class="fa-solid fa-location-dot" style="color:var(--verde)"></i> Local
                </h4>
                <?php foreach ([
                    ['Nome',     $mat['local_nome']],
                    ['Cidade/UF',$mat['local_cidade'].'/'.$mat['local_uf']],
                    ['Endereço', $mat['local_endereco']],
                ] as [$r,$v]): if (!$v) continue; ?>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;
                            padding:6px 0;border-bottom:1px solid var(--c100);font-size:.84rem;gap:10px">
                    <span style="color:var(--c500);flex-shrink:0"><?= $r ?></span>
                    <span style="text-align:right;color:var(--c700)"><?= htmlspecialchars($v) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Descrição do curso -->
        <?php if ($mat['descricao']): ?>
        <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--c200)">
            <h4 style="font-family:var(--font-titulo);font-size:.88rem;color:var(--azul-esc);margin:0 0 10px">
                <i class="fa-solid fa-book-open" style="color:var(--verde)"></i> Sobre este curso
            </h4>
            <div style="font-size:.88rem;color:var(--c600);line-height:1.75">
                <?= nl2br(htmlspecialchars($mat['descricao'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Instrutores -->
        <?php if (!empty($instrutores)): ?>
        <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--c200)">
            <h4 style="font-family:var(--font-titulo);font-size:.88rem;color:var(--azul-esc);margin:0 0 12px">
                <i class="fa-solid fa-chalkboard-teacher" style="color:var(--verde)"></i>
                Instrutor<?= count($instrutores)>1?'es':'' ?>
            </h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px">
            <?php foreach ($instrutores as $inst): ?>
            <div style="display:flex;align-items:flex-start;gap:10px;padding:12px;
                        background:var(--c50);border:1px solid var(--c200);border-radius:var(--radius)">
                <div style="width:38px;height:38px;border-radius:50%;background:var(--azul-esc);
                            overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <?php if ($inst['foto']): ?>
                    <img src="/crmv/uploads/fotos/<?= htmlspecialchars($inst['foto']) ?>"
                         style="width:38px;height:38px;object-fit:cover">
                    <?php else: ?>
                    <i class="fa-solid fa-user" style="color:rgba(255,255,255,.6);font-size:.8rem"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:.85rem;color:var(--azul-esc)"><?= htmlspecialchars($inst['nome']) ?></div>
                    <?php if ($inst['titulo_profis']): ?>
                    <div style="font-size:.72rem;color:var(--c500);margin-top:1px"><?= htmlspecialchars($inst['titulo_profis']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; // abas ?>

    </div><!-- /card-body -->
</div><!-- /card -->

<script>
/* ═══════════════════════════════════════════════════════════
   JavaScript: rastreamento e UX
   ═══════════════════════════════════════════════════════════ */

/* ── Curso sem módulos: marcar como assistido/concluído ─────── */
function marcarCursoAssistido(matId) {
    // Para cursos sem módulos, usamos uma "aula virtual" com id especial via progresso_ead direto
    fetch('/crmv/aluno/curso_marcar_concluido.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'matricula_id=' + matId
    })
    .then(function() {
        // Recarrega para mostrar botão de certificado / avaliação
        setTimeout(function() { location.reload(); }, 400);
    })
    .catch(function() {
        // Mesmo em erro, recarrega (fallback)
        setTimeout(function() { location.reload(); }, 500);
    });
}

/* ── Marcar aula como concluída (AJAX) ──────────────────── */
function marcarConcluida(aulaId, matId) {
    var item = document.getElementById('aula-item-' + aulaId);
    if (item && item.dataset.concluida === '1') return; // já registrado

    fetch(location.pathname + '?id=' + matId, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'acao=concluir_aula&aula_id=' + aulaId
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.ok || !item) return;
        item.dataset.concluida = '1';
        item.style.background = '#f0fdf4';

        // Atualiza o ícone de status
        var icone = item.querySelector('.status-ico');
        if (icone) {
            icone.style.background = 'var(--verde)';
            icone.style.borderColor = 'var(--verde)';
            icone.innerHTML = '<i class="fa-solid fa-check" style="color:#fff;font-size:.6rem"></i>';
        }

        // Substitui os botões por "Concluída"
        var acoes = item.querySelector('.aula-acoes');
        if (acoes) {
            acoes.innerHTML = '<span style="font-size:.72rem;color:var(--verde);font-weight:700;white-space:nowrap">' +
                '<i class="fa-solid fa-circle-check"></i> Concluída</span>';
        }

        // Verifica se pode recarregar para mostrar aviso/aba desbloqueada
        setTimeout(function() { location.reload(); }, 600);
    })
    .catch(function() {});
}

/* ── Registrar download de material (AJAX, silencioso) ──── */
function registrarDownload(matId, matriculaId) {
    fetch(location.pathname + '?id=' + matriculaId, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'acao=baixar_material&material_id=' + matId
    }).catch(function() {});
    // Não bloqueia o download
    return true;
}

/* ── Toggle colapsar/expandir módulo ───────────────────── */
function toggleModulo(idx) {
    var body    = document.getElementById('mod-body-' + idx);
    var chevron = document.getElementById('chevron-' + idx);
    if (!body) return;
    var aberto = body.style.display !== 'none';
    body.style.display    = aberto ? 'none' : 'block';
    chevron.style.transform = aberto ? 'rotate(-90deg)' : 'rotate(0deg)';
}

/* ── Confirmar envio da avaliação ───────────────────────── */
function confirmarEnvio(form) {
    // Verifica se todas as questões foram respondidas
    var names = {};
    form.querySelectorAll('input[type=radio]').forEach(function(r) { names[r.name] = true; });
    var total = Object.keys(names).length;
    var resp  = 0;
    Object.keys(names).forEach(function(n) {
        if (form.querySelector('input[name="' + n + '"]:checked')) resp++;
    });
    if (resp < total) {
        alert('Responda todas as ' + total + ' questões antes de enviar.');
        return false;
    }
    return confirm('Confirmar envio da avaliação?\n\nApós enviar, suas respostas não poderão ser alteradas.');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_aluno_footer.php'; ?>
