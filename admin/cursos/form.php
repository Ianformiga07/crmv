<?php
/**
 * form.php — Cadastro / Edição de Curso
 * /crmv/admin/cursos/form.php
 */
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

$id       = (int)($_GET['id'] ?? 0);
$editando = $id > 0;
$c        = [];
$erros    = [];

$categorias  = dbQuery("SELECT categoria_id, nome, cor_hex FROM tbl_categorias WHERE ativo=1 ORDER BY ordem");
$instrutores = dbQuery("SELECT instrutor_id, nome, titulo FROM tbl_instrutores WHERE ativo=1 ORDER BY nome");
$materiais   = [];
$modulos     = [];
$avaliacao   = null;
$questoes    = [];

if ($editando) {
    $c = dbQueryOne("SELECT * FROM tbl_cursos WHERE curso_id = ?", [$id]);
    if (!$c) { flash('Curso não encontrado.', 'erro'); header('Location: /crmv/admin/cursos/lista.php'); exit; }

    $materiais = dbQuery(
        "SELECT * FROM tbl_materiais WHERE curso_id=? AND (modulo_id IS NULL OR modulo_id=0) ORDER BY criado_em",
        [$id]
    );

    $rawMods = dbQuery("SELECT * FROM tbl_modulos WHERE curso_id=? ORDER BY ordem", [$id]);
    foreach ($rawMods as $mod) {
        $aulas = dbQuery("SELECT * FROM tbl_aulas WHERE modulo_id=? AND ativo=1 ORDER BY ordem", [$mod['modulo_id']]);
        $modulos[] = array_merge($mod, ['aulas' => $aulas]);
    }

    $avaliacao = dbQueryOne(
        "SELECT * FROM tbl_avaliacoes WHERE curso_id=? AND (modulo_id IS NULL OR modulo_id=0) AND ativo=1 ORDER BY avaliacao_id LIMIT 1",
        [$id]
    );
    if ($avaliacao) {
        $rows = dbQuery(
            "SELECT q.*, a.alternativa_id, a.texto AS alt_txt, a.correta, a.ordem AS alt_ord
             FROM tbl_questoes q
             INNER JOIN tbl_alternativas a ON a.questao_id=q.questao_id
             WHERE q.avaliacao_id=? AND q.ativo=1
             ORDER BY q.ordem, a.ordem",
            [$avaliacao['avaliacao_id']]
        );
        foreach ($rows as $r) {
            $qid = $r['questao_id'];
            if (!isset($questoes[$qid])) {
                $questoes[$qid] = ['questao_id'=>$qid,'enunciado'=>$r['enunciado'],'pontos'=>$r['pontos'],'alts'=>[],'correta'=>null];
            }
            $questoes[$qid]['alts'][] = ['alternativa_id'=>$r['alternativa_id'],'texto'=>$r['alt_txt'],'correta'=>(bool)$r['correta']];
            if ($r['correta']) $questoes[$qid]['correta'] = count($questoes[$qid]['alts']) - 1;
        }
        $questoes = array_values($questoes);
    }
}

/* ════════════════════════════════ POST ════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['_acao'] ?? 'salvar_curso';

    /* ─── Salvar dados gerais ─────────────────────────────── */
    if ($acao === 'salvar_curso') {

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
            'requer_avaliacao'   => isset($_POST['requer_avaliacao'])   ? 1 : 0,
            'avaliacao_com_nota' => isset($_POST['avaliacao_com_nota']) ? 1 : 0,
            'nota_minima'        => (float)str_replace(',','.', $_POST['nota_minima'] ?? '70'),
            'tentativas_maximas' => (int)($_POST['tentativas_maximas'] ?? 3),
            'cert_conteudo_programatico' => $_POST['cert_conteudo_programatico'] ?? '',
            'cert_validade'              => ($v = (int)($_POST['cert_validade'] ?? 0)) > 0 ? $v : null,
            'cert_obs'                   => trim($_POST['cert_obs'] ?? ''),
        ];

        // Normaliza YouTube ID
        $yt = $campos['youtube_id'];
        if ($yt && str_contains($yt, 'youtu')) {
            preg_match('/(?:v=|\/embed\/|\/shorts\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $yt, $m);
            $campos['youtube_id'] = $m[1] ?? substr($yt, 0, 11);
        }

        if ($campos['titulo'] === '')        $erros[] = 'Título é obrigatório.';
        if ($campos['carga_horaria'] <= 0)   $erros[] = 'Carga horária deve ser maior que zero.';

        // Processa upload da capa
        $nomeCapa = $c['capa'] ?? null;
        if (!empty($_FILES['capa']['name'])) {
            $ext = strtolower(pathinfo($_FILES['capa']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                $erros[] = 'Capa deve ser JPG, PNG ou WEBP.';
            } elseif ($_FILES['capa']['size'] > 5*1024*1024) {
                $erros[] = 'Capa deve ter no máximo 5MB.';
            } else {
                $nomeCapa = 'capa_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
                $destCapa = __DIR__.'/../../uploads/capas/'.$nomeCapa;
                if (!move_uploaded_file($_FILES['capa']['tmp_name'], $destCapa)) {
                    $erros[] = 'Falha ao salvar a capa.';
                    $nomeCapa = $c['capa'] ?? null;
                } elseif (!empty($c['capa']) && $c['capa'] !== $nomeCapa) {
                    @unlink(__DIR__.'/../../uploads/capas/'.$c['capa']);
                }
            }
        }

        if (empty($erros)) {
            $campos['capa'] = $nomeCapa;

            if ($editando) {
                /* ── UPDATE ── */
                dbExecute(
                    "UPDATE tbl_cursos SET
                        categoria_id=?, instrutor_id=?, titulo=?, descricao=?,
                        tipo=?, modalidade=?, carga_horaria=?, vagas=?,
                        data_inicio=?, data_fim=?, horario=?,
                        local_nome=?, local_cidade=?, local_uf=?, local_endereco=?,
                        link_ead=?, youtube_id=?, valor=?, status=?,
                        observacoes=?, capa=?,
                        requer_avaliacao=?, avaliacao_com_nota=?, nota_minima=?, tentativas_maximas=?,
                        cert_conteudo_programatico=?, cert_validade=?, cert_obs=?,
                        atualizado_em=NOW()
                     WHERE curso_id=?",
                    [
                        $campos['categoria_id'], $campos['instrutor_id'],
                        $campos['titulo'],        $campos['descricao'],
                        $campos['tipo'],          $campos['modalidade'],
                        $campos['carga_horaria'], $campos['vagas'],
                        $campos['data_inicio'],   $campos['data_fim'],
                        $campos['horario'],
                        $campos['local_nome'],    $campos['local_cidade'],
                        $campos['local_uf'],      $campos['local_endereco'],
                        $campos['link_ead'],      $campos['youtube_id'],
                        $campos['valor'],         $campos['status'],
                        $campos['observacoes'],   $campos['capa'],
                        $campos['requer_avaliacao'], $campos['avaliacao_com_nota'],
                        $campos['nota_minima'],   $campos['tentativas_maximas'],
                        $campos['cert_conteudo_programatico'],
                        $campos['cert_validade'], $campos['cert_obs'],
                        $_SESSION['usr_id']
                    ]
                );
                $salvoId = $id;
                registraLog($_SESSION['usr_id'], 'EDITAR_CURSO', "Editou curso: {$campos['titulo']}", 'tbl_cursos', $id);
                flash('Curso atualizado com sucesso!', 'sucesso');

            } else {
                /* ── INSERT ── */
                dbExecute(
                    "INSERT INTO tbl_cursos
                        (categoria_id, instrutor_id, titulo, descricao,
                         tipo, modalidade, carga_horaria, vagas,
                         data_inicio, data_fim, horario,
                         local_nome, local_cidade, local_uf, local_endereco,
                         link_ead, youtube_id, valor, status,
                         observacoes, capa,
                         requer_avaliacao, avaliacao_com_nota, nota_minima, tentativas_maximas,
                         cert_conteudo_programatico, cert_validade, cert_obs,
                         ativo, criado_por)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)",
                    [
                        $campos['categoria_id'], $campos['instrutor_id'],
                        $campos['titulo'],        $campos['descricao'],
                        $campos['tipo'],          $campos['modalidade'],
                        $campos['carga_horaria'], $campos['vagas'],
                        $campos['data_inicio'],   $campos['data_fim'],
                        $campos['horario'],
                        $campos['local_nome'],    $campos['local_cidade'],
                        $campos['local_uf'],      $campos['local_endereco'],
                        $campos['link_ead'],      $campos['youtube_id'],
                        $campos['valor'],         $campos['status'],
                        $campos['observacoes'],   $campos['capa'],
                        $campos['requer_avaliacao'], $campos['avaliacao_com_nota'],
                        $campos['nota_minima'],   $campos['tentativas_maximas'],
                        $campos['cert_conteudo_programatico'],
                        $campos['cert_validade'], $campos['cert_obs'],
                        $_SESSION['usr_id']
                    ]
                );
                $salvoId = dbLastId();
                $id = $salvoId; $editando = true;
                registraLog($_SESSION['usr_id'], 'CRIAR_CURSO', "Criou curso: {$campos['titulo']}", 'tbl_cursos', $salvoId);
                flash('Curso cadastrado! Complete as informações nas abas acima.', 'sucesso');
            }

            // Materiais gerais anexados no mesmo submit
            if (!empty($_FILES['materiais']['name'][0])) {
                foreach ($_FILES['materiais']['name'] as $k => $nOrig) {
                    if (empty($nOrig)) continue;
                    $ext2 = strtolower(pathinfo($nOrig, PATHINFO_EXTENSION));
                    if (!in_array($ext2, ['pdf','doc','docx','xls','xlsx','ppt','pptx','zip','mp4'])) continue;
                    if ($_FILES['materiais']['size'][$k] > 50*1024*1024) continue;
                    $nArq = 'mat_'.$salvoId.'_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext2;
                    if (move_uploaded_file($_FILES['materiais']['tmp_name'][$k], __DIR__.'/../../uploads/materiais/'.$nArq)) {
                        dbExecute(
                            "INSERT INTO tbl_materiais (curso_id,modulo_id,nome_arquivo,nome_original,tamanho,tipo_mime,criado_por) VALUES (?,NULL,?,?,?,?,?)",
                            [$salvoId, $nArq, $nOrig, $_FILES['materiais']['size'][$k], $_FILES['materiais']['type'][$k], $_SESSION['usr_id']]
                        );
                    }
                }
            }

            $abaRedir = $_POST['_aba_ativa'] ?? 'dados';
            header("Location: /crmv/admin/cursos/form.php?id={$salvoId}&aba={$abaRedir}");
            exit;
        }
        $c = array_merge($c, $campos);
    }

/* ─── Salvar aulas ────────────────────────────────────── */
elseif ($acao === 'salvar_aulas') {

    if (!$id) {
        flash('Salve o curso antes de cadastrar as aulas.', 'erro');
        header("Location: /crmv/admin/cursos/form.php");
        exit;
    }

    $modPadrao = dbQueryOne("SELECT modulo_id FROM tbl_modulos WHERE curso_id=? ORDER BY ordem LIMIT 1", [$id]);
    $moduloId  = null;

    if ($modPadrao) {
        $moduloId = $modPadrao['modulo_id'];

        foreach (dbQuery("SELECT * FROM tbl_aulas WHERE modulo_id=?", [$moduloId]) as $aOld) {
            if (!empty($aOld['arquivo_video'])) {
                @unlink(__DIR__.'/../../uploads/videos/'.$aOld['arquivo_video']);
            }
        }

        dbExecute("DELETE FROM tbl_aulas WHERE modulo_id=?", [$moduloId]);

    } else {

        dbExecute(
            "INSERT INTO tbl_modulos (curso_id,titulo,ordem) VALUES (?,'Aulas do Curso',1)",
            [$id]
        );

        $moduloId = dbLastId();
    }

    $titulos = $_POST['aula_titulo'] ?? [];
    $yts     = $_POST['aula_yt'] ?? [];
    $links   = $_POST['aula_link'] ?? [];
    $durs    = $_POST['aula_duracao'] ?? [];

    foreach ($titulos as $i => $titulo) {

        $titulo = trim($titulo);

        if ($titulo === '') {
            $titulo = 'Aula '.($i+1);
        }

        $ytId = '';
        $linkExt = trim($links[$i] ?? '');
        $ytRaw = trim($yts[$i] ?? '');
        $dur   = (int)($durs[$i] ?? 0) ?: null;

        $arqV = null;

        if ($ytRaw) {
            preg_match('/(?:v=|\/embed\/|\/shorts\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $ytRaw, $m);
            $ytId = $m[1] ?? (strlen($ytRaw) <= 11 ? $ytRaw : '');
        }

        if (isset($_FILES['aula_video']['error'][$i]) && $_FILES['aula_video']['error'][$i] === UPLOAD_ERR_OK) {

            $tmp  = $_FILES['aula_video']['tmp_name'][$i];
            $nome = $_FILES['aula_video']['name'][$i];

            $vExt = strtolower(pathinfo($nome, PATHINFO_EXTENSION));

            if (in_array($vExt, ['mp4','mov','avi','mkv','webm'])) {

                $dir = __DIR__ . '/../../uploads/videos/';

                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                $vNome = 'video_'.$id.'_'.time().'_'.bin2hex(random_bytes(3)).'.'.$vExt;

                if (move_uploaded_file($tmp, $dir.$vNome)) {
                    $arqV = $vNome;
                }
            }
        }

        dbExecute(
            "INSERT INTO tbl_aulas 
            (modulo_id,titulo,youtube_id,link_externo,arquivo_video,duracao_min,ordem,ativo) 
            VALUES (?,?,?,?,?,?,?,1)",
            [$moduloId, $titulo, $ytId ?: null, $linkExt ?: null, $arqV, $dur, $i+1]
        );
    }

    flash('Aulas salvas!', 'sucesso');
    header("Location: /crmv/admin/cursos/form.php?id={$id}&aba=aulas");
    exit;
}

    /* ─── Salvar avaliação ────────────────────────────────── */
    elseif ($acao === 'salvar_avaliacao' && $editando) {
        $avTit   = trim($_POST['av_titulo'] ?? 'Avaliação Final');
        $avDesc  = trim($_POST['av_descricao'] ?? '');
        $avNota  = (float)str_replace(',', '.', $_POST['av_nota_minima'] ?? '70');
        $avTempo = (int)($_POST['av_tempo_limite'] ?? 0) ?: null;
        $avTent  = (int)($_POST['av_tentativas_max'] ?? 3);
        $avRand  = isset($_POST['av_randomizar']) ? 1 : 0;

        $enuns = $_POST['q_enunciado'] ?? [];
        $ponts = $_POST['q_pontos']    ?? [];
        $corts = $_POST['q_correta']   ?? [];
        $alts  = [];
        for ($a = 0; $a < 4; $a++) $alts[$a] = $_POST["q_alt{$a}"] ?? [];

        $avOld = dbQueryOne("SELECT avaliacao_id FROM tbl_avaliacoes WHERE curso_id=? AND (modulo_id IS NULL OR modulo_id=0)", [$id]);
        if ($avOld) {
            foreach (dbQuery("SELECT questao_id FROM tbl_questoes WHERE avaliacao_id=?", [$avOld['avaliacao_id']]) as $qo) {
                dbExecute("DELETE FROM tbl_alternativas WHERE questao_id=?", [$qo['questao_id']]);
            }
            dbExecute("DELETE FROM tbl_questoes  WHERE avaliacao_id=?", [$avOld['avaliacao_id']]);
            dbExecute("DELETE FROM tbl_avaliacoes WHERE avaliacao_id=?", [$avOld['avaliacao_id']]);
        }

        dbExecute(
            "INSERT INTO tbl_avaliacoes (curso_id,modulo_id,titulo,descricao,tipo,nota_minima,tempo_limite,tentativas_max,randomizar,ativo)
             VALUES (?,NULL,?,?,'PROVA',?,?,?,?,1)",
            [$id, $avTit, $avDesc, $avNota, $avTempo, $avTent, $avRand]
        );
        $avId = dbLastId();

        foreach ($enuns as $qi => $enun) {
            $enun = trim($enun); if ($enun === '') continue;
            $pts  = max(0.5, (float)str_replace(',', '.', $ponts[$qi] ?? '1'));
            $cort = (int)($corts[$qi] ?? 0);
            dbExecute("INSERT INTO tbl_questoes (avaliacao_id,enunciado,tipo,pontos,ordem,ativo) VALUES (?,?,'MULTIPLA',?,?,1)", [$avId, $enun, $pts, $qi+1]);
            $qId = dbLastId();
            for ($a = 0; $a < 4; $a++) {
                $txt = trim($alts[$a][$qi] ?? '');
                if ($txt === '') continue;
                dbExecute("INSERT INTO tbl_alternativas (questao_id,texto,correta,ordem) VALUES (?,?,?,?)", [$qId, $txt, ($a === $cort ? 1 : 0), $a+1]);
            }
        }

        dbExecute("UPDATE tbl_cursos SET requer_avaliacao=1,avaliacao_com_nota=1,nota_minima=?,tentativas_maximas=? WHERE curso_id=?", [$avNota, $avTent, $id]);
        registraLog($_SESSION['usr_id'], 'EDITAR_AVALIACAO', "Salvou avaliação curso {$id}", 'tbl_cursos', $id);
        flash('Avaliação salva!', 'sucesso');
        header("Location: /crmv/admin/cursos/form.php?id={$id}&aba=avaliacao"); exit;
    }

    /* ─── Apagar avaliação ────────────────────────────────── */
    elseif ($acao === 'apagar_avaliacao' && $editando) {
        $avOld = dbQueryOne("SELECT avaliacao_id FROM tbl_avaliacoes WHERE curso_id=? AND (modulo_id IS NULL OR modulo_id=0)", [$id]);
        if ($avOld) {
            foreach (dbQuery("SELECT questao_id FROM tbl_questoes WHERE avaliacao_id=?", [$avOld['avaliacao_id']]) as $qo) {
                dbExecute("DELETE FROM tbl_alternativas WHERE questao_id=?", [$qo['questao_id']]);
            }
            dbExecute("DELETE FROM tbl_questoes  WHERE avaliacao_id=?", [$avOld['avaliacao_id']]);
            dbExecute("DELETE FROM tbl_avaliacoes WHERE avaliacao_id=?", [$avOld['avaliacao_id']]);
            dbExecute("UPDATE tbl_cursos SET requer_avaliacao=0 WHERE curso_id=?", [$id]);
        }
        flash('Avaliação removida.', 'aviso');
        header("Location: /crmv/admin/cursos/form.php?id={$id}&aba=avaliacao"); exit;
    }
}

/* ── Helpers ── */
$ufs   = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];
$tipos = ['CURSO','PALESTRA','WORKSHOP','CONGRESSO','WEBINAR'];
$abaAtiva = $_GET['aba'] ?? 'dados';

/* ── Helper renderizar questão ── */
function renderQuestaoHTML(int $qi, array $q): string {
    $alts = $q['alts'];
    while (count($alts) < 4) $alts[] = ['texto'=>'','correta'=>false];
    $letras  = ['A','B','C','D'];
    $corrIdx = $q['correta'] ?? 0;
    $h  = '<div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--azul-esc);color:#fff">';
    $h .= '<span class="q-num" style="width:22px;height:22px;border-radius:50%;background:var(--ouro);color:var(--azul-esc);font-weight:800;font-size:.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">'.($qi+1).'</span>';
    $h .= '<span style="font-size:.75rem;color:rgba(255,255,255,.7)">Questão '.($qi+1).'</span>';
    $h .= '<div style="display:flex;align-items:center;gap:6px;margin-left:auto">';
    $h .= '<span style="font-size:.72rem;opacity:.7">Pontos:</span>';
    $h .= '<input type="number" name="q_pontos[]" min="0.5" max="100" step="0.5" value="'.htmlspecialchars($q['pontos']??1).'" style="width:55px;background:transparent;border:1px solid rgba(255,255,255,.4);border-radius:4px;color:#fff;font-size:.78rem;padding:2px 6px;text-align:center">';
    $h .= '<button type="button" onclick="removerQuestao(this)" style="background:transparent;border:none;color:rgba(255,255,255,.6);cursor:pointer;padding:4px;border-radius:4px"><i class="fa-solid fa-xmark"></i></button>';
    $h .= '</div></div>';
    $h .= '<div style="padding:12px 14px;background:var(--c50);border-bottom:1px solid var(--c200)">';
    $h .= '<textarea name="q_enunciado[]" rows="2" placeholder="Enunciado da questão..." required style="width:100%;padding:8px;border:1px solid var(--c300);border-radius:6px;font-size:.88rem;resize:vertical">'.htmlspecialchars($q['enunciado']??'').'</textarea></div>';
    $h .= '<div style="padding:12px 14px;display:flex;flex-direction:column;gap:6px">';
    $h .= '<div style="font-size:.72rem;color:var(--c400);margin-bottom:2px"><i class="fa-solid fa-circle-check" style="color:var(--verde)"></i> Marque a alternativa correta</div>';
    for ($a = 0; $a < 4; $a++) {
        $isC = ($a === $corrIdx);
        $h .= '<label style="display:flex;align-items:center;gap:8px;flex:1;padding:7px 10px;border-radius:6px;cursor:pointer;border:1.5px solid '.($isC?'var(--verde)':'var(--c200)').';background:'.($isC?'#f0fdf4':'#fff').'">';
        $h .= '<input type="radio" name="q_correta['.$qi.']" value="'.$a.'" '.($isC?'checked':'').' onchange="destacarCorreta(this)" style="accent-color:var(--verde)">';
        $h .= '<span style="width:18px;height:18px;border-radius:50%;background:var(--azul-esc);color:var(--ouro);font-weight:700;font-size:.7rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">'.$letras[$a].'</span>';
        $h .= '<input type="text" name="q_alt'.$a.'[]" value="'.htmlspecialchars($alts[$a]['texto']??'').'" placeholder="Alternativa '.$letras[$a].'" style="flex:1;border:none;outline:none;background:transparent;font-size:.85rem;color:var(--c700)">';
        $h .= '</label>';
    }
    $h .= '</div>';
    return $h;
}

/* ── Helper campos ocultos do curso para forms secundários ── */
function camposOcultosCurso(array $c): string {
    $campos = [
        'titulo'         => $c['titulo']         ?? '',
        'carga_horaria'  => $c['carga_horaria']  ?? '0',
        'status'         => $c['status']         ?? 'RASCUNHO',
        'tipo'           => $c['tipo']           ?? 'CURSO',
        'modalidade'     => $c['modalidade']     ?? 'PRESENCIAL',
        'categoria_id'   => $c['categoria_id']   ?? '',
        'instrutor_id'   => $c['instrutor_id']   ?? '',
        'descricao'      => $c['descricao']      ?? '',
        'observacoes'    => $c['observacoes']    ?? '',
        'vagas'          => $c['vagas']          ?? '',
        'valor'          => $c['valor']          ?? '0',
        'horario'        => $c['horario']        ?? '',
        'data_inicio'    => $c['data_inicio']    ?? '',
        'data_fim'       => $c['data_fim']       ?? '',
        'local_nome'     => $c['local_nome']     ?? '',
        'local_cidade'   => $c['local_cidade']   ?? '',
        'local_uf'       => $c['local_uf']       ?? 'TO',
        'local_endereco' => $c['local_endereco'] ?? '',
        'link_ead'       => $c['link_ead']       ?? '',
        'youtube_id'     => $c['youtube_id']     ?? '',
        'nota_minima'    => $c['nota_minima']    ?? '70',
        'tentativas_maximas' => $c['tentativas_maximas'] ?? '3',
        'cert_validade'  => $c['cert_validade']  ?? '0',
        'cert_obs'       => $c['cert_obs']       ?? '',
        'cert_conteudo_programatico' => $c['cert_conteudo_programatico'] ?? '',
    ];
    $html = '';
    foreach ($campos as $name => $val) {
        $html .= '<input type="hidden" name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($val).'">'."\n";
    }
    return $html;
}

$pageTitulo  = $editando ? 'Editar Curso' : 'Novo Curso';
$paginaAtiva = 'cursos';
require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="pg-header">
    <div class="pg-header-row">
        <div>
            <h1 class="pg-titulo"><?= $editando ? 'Editar Curso' : 'Novo Curso / Palestra' ?></h1>
            <p class="pg-subtitulo"><?= $editando ? 'Atualize as informações do curso' : 'Preencha os dados do novo curso' ?></p>
        </div>
        <div class="pg-acoes">
            <a href="/crmv/admin/cursos/lista.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
        </div>
    </div>
</div>

<?php if (!empty($erros)): ?>
<div class="alerta alerta-erro" style="margin-bottom:20px">
    <i class="fa-solid fa-circle-xmark"></i>
    <div><strong>Corrija os erros:</strong><ul style="margin:6px 0 0 16px;padding:0">
        <?php foreach ($erros as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul></div>
</div>
<?php endif; ?>

<!-- Aviso certificado automático -->
<div class="alerta alerta-info" style="margin-bottom:16px">
    <i class="fa-solid fa-certificate" style="color:var(--ouro)"></i>
    <div>
        <strong>Certificado automático ativado</strong> — O aluno emite o próprio certificado assim que concluir todas as aulas<?= ($c['requer_avaliacao'] ?? 0) ? ' <strong>e for aprovado na avaliação</strong>' : '' ?>.
    </div>
</div>

<!-- ── Abas ── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:0 20px">
        <div class="tabs-barra" style="border-bottom:none;margin:0">
        <?php
        $totalAulasRes = 0; foreach ($modulos as $m_) $totalAulasRes += count($m_['aulas']);
        $abaDefs = [
            'dados'       => ['fa-graduation-cap',    'Dados',       ''],
            'aulas'       => ['fa-play-circle',        'Aulas',       $totalAulasRes>0 ? '<span style="font-size:.7rem;padding:1px 6px;border-radius:8px;background:var(--c200);color:var(--c600);margin-left:4px">'.$totalAulasRes.'</span>' : ''],
            'materiais'   => ['fa-paperclip',          'Materiais',   count($materiais)>0 ? '<span style="font-size:.7rem;padding:1px 6px;border-radius:8px;background:var(--c200);color:var(--c600);margin-left:4px">'.count($materiais).'</span>' : ''],
            'avaliacao'   => ['fa-clipboard-question', 'Avaliação',   $avaliacao ? '<i class="fa-solid fa-check" style="color:var(--verde);font-size:.7rem;margin-left:4px"></i>' : ''],
            'certificado' => ['fa-certificate',        'Certificado', ''],
        ];
        foreach ($abaDefs as $k => [$ic,$lbl,$extra]):
        ?>
        <button type="button" onclick="trocarAba('<?= $k ?>')"
           class="tab-btn <?= $abaAtiva===$k?'ativo':'' ?>" id="tab-<?= $k ?>"
           style="background:none;border:none;cursor:pointer;font-family:inherit">
            <i class="fa-solid <?= $ic ?>" <?= $k==='certificado'?'style="color:var(--ouro)"':'' ?>></i>
            <?= $lbl ?><?= $extra ?>
        </button>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

<!-- ══ COLUNA PRINCIPAL ══ -->
<div style="display:flex;flex-direction:column;gap:20px">

<!-- ══════════════ ABA DADOS ══════════════ -->
<div id="aba-dados" class="aba-conteudo" style="display:<?= $abaAtiva==='dados'?'flex':'none' ?>;flex-direction:column;gap:20px">
<form method="POST" enctype="multipart/form-data" id="fFormDados">
<input type="hidden" name="_acao"       value="salvar_curso">
<input type="hidden" name="_aba_ativa"  id="inp_aba_ativa" value="<?= htmlspecialchars($abaAtiva) ?>">
<input type="hidden" name="status"      id="inp_status_dados" value="<?= htmlspecialchars($c['status']??'RASCUNHO') ?>">
<input type="file"   name="capa"        id="inpCapaDados" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="prevCapaFn(this)">

<!-- Identificação -->
<div class="card"><div class="card-header"><span class="card-titulo"><i class="fa-solid fa-graduation-cap"></i> Identificação</span></div>
<div class="card-body"><div class="form-grid">
<div class="c12 form-group">
    <label class="req">Título do Curso / Palestra</label>
    <input type="text" name="titulo" required data-max="200" value="<?= htmlspecialchars($c['titulo']??'') ?>" placeholder="Ex: Workshop de Ultrassonografia">
</div>
<div class="c4 form-group"><label>Tipo</label><select name="tipo">
    <?php foreach ($tipos as $t): ?><option value="<?= $t ?>" <?= ($c['tipo']??'CURSO')===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
</select></div>
<div class="c4 form-group"><label>Modalidade</label><select name="modalidade" onchange="toggleLocal(this.value)">
    <option value="PRESENCIAL" <?= ($c['modalidade']??'')==='PRESENCIAL'?'selected':'' ?>>Presencial</option>
    <option value="EAD"        <?= ($c['modalidade']??'')==='EAD'?'selected':'' ?>>EAD (Online)</option>
    <option value="HIBRIDO"    <?= ($c['modalidade']??'')==='HIBRIDO'?'selected':'' ?>>Híbrido</option>
</select></div>
<div class="c4 form-group"><label>Categoria</label><select name="categoria_id">
    <option value="">Sem categoria</option>
    <?php foreach ($categorias as $cat): ?>
    <option value="<?= $cat['categoria_id'] ?>" <?= ($c['categoria_id']??'')==$cat['categoria_id']?'selected':'' ?>><?= htmlspecialchars($cat['nome']) ?></option>
    <?php endforeach; ?>
</select></div>
<div class="c12 form-group"><label>Descrição</label>
    <textarea name="descricao" rows="4" data-max="2000" placeholder="Descreva o conteúdo, objetivos e público-alvo..."><?= htmlspecialchars($c['descricao']??'') ?></textarea></div>
<div class="c12 form-group"><label>Observações</label>
    <textarea name="observacoes" rows="2" placeholder="Pré-requisitos, avisos importantes..."><?= htmlspecialchars($c['observacoes']??'') ?></textarea></div>
</div></div></div>

<!-- Datas, Carga e Vagas -->
<div class="card"><div class="card-header"><span class="card-titulo"><i class="fa-solid fa-calendar-days"></i> Datas, Carga e Vagas</span></div>
<div class="card-body"><div class="form-grid">
<div class="c3 form-group"><label class="req">Carga Horária (h)</label>
    <input type="number" name="carga_horaria" step="0.5" min="0.5" required value="<?= htmlspecialchars($c['carga_horaria']??'') ?>" placeholder="Ex: 8"></div>
<div class="c3 form-group"><label>Vagas</label>
    <input type="number" name="vagas" min="1" value="<?= htmlspecialchars($c['vagas']??'') ?>" placeholder="Ilimitado se vazio"></div>
<div class="c3 form-group"><label>Valor (R$)</label>
    <input type="number" name="valor" step="0.01" min="0" value="<?= htmlspecialchars($c['valor']??'0') ?>" placeholder="0 para gratuito"></div>
<div class="c3 form-group"><label>Horário</label>
    <input type="text" name="horario" value="<?= htmlspecialchars($c['horario']??'') ?>" placeholder="Ex: 08h às 17h"></div>
<div class="c4 form-group"><label>Início</label>
    <input type="date" name="data_inicio" value="<?= htmlspecialchars($c['data_inicio']??'') ?>"></div>
<div class="c4 form-group"><label>Término</label>
    <input type="date" name="data_fim" value="<?= htmlspecialchars($c['data_fim']??'') ?>"></div>
<div class="c4 form-group"><label>Instrutor</label><select name="instrutor_id">
    <option value="">Não definido</option>
    <?php foreach ($instrutores as $ins): ?>
    <option value="<?= $ins['instrutor_id'] ?>" <?= ($c['instrutor_id']??'')==$ins['instrutor_id']?'selected':'' ?>><?= htmlspecialchars($ins['nome']) ?><?= $ins['titulo']?' — '.$ins['titulo']:'' ?></option>
    <?php endforeach; ?>
</select></div>
<div class="c12 form-group"><label>Link Plataforma EAD (opcional)</label>
    <input type="url" name="link_ead" value="<?= htmlspecialchars($c['link_ead']??'') ?>" placeholder="https://... (se usar plataforma externa)">
    <span class="dica">Use quando o curso redireciona para Moodle ou similar.</span></div>
</div></div></div>

<!-- Local (oculto para EAD) -->
<div class="card" id="secaoLocal" style="<?= ($c['modalidade']??'PRESENCIAL')==='EAD'?'display:none':'' ?>">
    <div class="card-header"><span class="card-titulo"><i class="fa-solid fa-location-dot"></i> Local de Realização</span></div>
    <div class="card-body"><div class="form-grid">
        <div class="c6 form-group"><label>Nome do Local</label><input type="text" name="local_nome" value="<?= htmlspecialchars($c['local_nome']??'') ?>" placeholder="Ex: Centro de Eventos CRMV/TO"></div>
        <div class="c4 form-group"><label>Cidade</label><input type="text" name="local_cidade" value="<?= htmlspecialchars($c['local_cidade']??'') ?>" placeholder="Palmas"></div>
        <div class="c2 form-group"><label>UF</label><select name="local_uf">
            <?php foreach ($ufs as $uf): ?><option value="<?= $uf ?>" <?= ($c['local_uf']??'TO')===$uf?'selected':'' ?>><?= $uf ?></option><?php endforeach; ?>
        </select></div>
        <div class="c12 form-group"><label>Endereço</label><input type="text" name="local_endereco" value="<?= htmlspecialchars($c['local_endereco']??'') ?>" placeholder="Rua, número, bairro"></div>
    </div></div>
</div>

<div style="display:flex;gap:8px">
    <button type="submit" class="btn btn-primario"><i class="fa-solid fa-floppy-disk"></i> <?= $editando?'Salvar Alterações':'Cadastrar Curso' ?></button>
    <a href="/crmv/admin/cursos/lista.php" class="btn btn-ghost"><i class="fa-solid fa-xmark"></i> Cancelar</a>
</div>
</form>
</div><!-- /aba-dados -->

<!-- ══════════════ ABA AULAS ══════════════ -->
<div id="aba-aulas" class="aba-conteudo" style="display:<?= $abaAtiva==='aulas'?'flex':'none' ?>;flex-direction:column;gap:20px">
<?php if (!$editando): ?>
<div class="alerta alerta-aviso"><i class="fa-solid fa-triangle-exclamation"></i> Salve os dados do curso primeiro para adicionar aulas.</div>
<?php else: ?>
<form method="POST" enctype="multipart/form-data" id="fFormAulas">
<input type="hidden" name="_acao" value="salvar_aulas">
<div class="card">
    <div class="card-header">
        <span class="card-titulo"><i class="fa-solid fa-play-circle" style="color:var(--azul-clr)"></i> Aulas do Curso</span>
        <span style="font-size:.75rem;color:var(--c400)">YouTube · Link externo · Upload local</span>
    </div>
    <div class="card-body" style="padding:14px">
        <div id="lista-aulas" style="display:flex;flex-direction:column;gap:10px;margin-bottom:14px">
        <?php
        $aulasFlat = [];
        foreach ($modulos as $m_) foreach ($m_['aulas'] as $a_) $aulasFlat[] = $a_;
        if (empty($aulasFlat)) $aulasFlat[] = [];
        foreach ($aulasFlat as $ai => $aula):
            $tipoAtivo = 'yt';
            if (!empty($aula['arquivo_video'])) $tipoAtivo = 'upload';
            elseif (!empty($aula['link_externo'])) $tipoAtivo = 'link';
        ?>
        <div class="aula-bloco" data-idx="<?= $ai ?>" style="border:1.5px solid var(--c200);border-radius:var(--radius-lg);overflow:hidden">
            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--azul-esc);color:#fff">
                <span class="aula-num" style="width:22px;height:22px;border-radius:50%;background:var(--ouro);color:var(--azul-esc);font-weight:800;font-size:.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= $ai+1 ?></span>
                <input type="text" name="aula_titulo[]" value="<?= htmlspecialchars($aula['titulo']??'') ?>" placeholder="Título da aula" style="flex:1;background:transparent;border:none;border-bottom:1px solid rgba(255,255,255,.3);color:#fff;font-weight:600;font-size:.9rem;outline:none;padding:2px 0">
                <input type="number" name="aula_duracao[]" min="1" max="600" value="<?= htmlspecialchars($aula['duracao_min']??'') ?>" placeholder="min" title="Duração em minutos" style="width:62px;background:transparent;border:1px solid rgba(255,255,255,.3);border-radius:5px;color:#fff;font-size:.78rem;padding:3px 6px;text-align:center">
                <button type="button" onclick="removerAula(this)" style="background:transparent;border:none;color:rgba(255,255,255,.6);cursor:pointer;font-size:.9rem;padding:4px;border-radius:4px"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div style="display:flex;border-bottom:1px solid var(--c200)">
                <button type="button" class="tipo-btn <?= $tipoAtivo==='yt'?'tipo-ativo':'' ?>" onclick="mudarTipoAula(this,'yt')" style="flex:1;padding:8px;border:none;cursor:pointer;font-size:.78rem;background:<?= $tipoAtivo==='yt'?'var(--azul-esc)':'var(--c50)' ?>;color:<?= $tipoAtivo==='yt'?'#fff':'var(--c600)' ?>;border-right:1px solid var(--c200)"><i class="fa-brands fa-youtube" style="color:<?= $tipoAtivo==='yt'?'#ff6b6b':'#ff0000' ?>"></i> YouTube</button>
                <button type="button" class="tipo-btn <?= $tipoAtivo==='link'?'tipo-ativo':'' ?>" onclick="mudarTipoAula(this,'link')" style="flex:1;padding:8px;border:none;cursor:pointer;font-size:.78rem;background:<?= $tipoAtivo==='link'?'var(--azul-esc)':'var(--c50)' ?>;color:<?= $tipoAtivo==='link'?'#fff':'var(--c600)' ?>;border-right:1px solid var(--c200)"><i class="fa-solid fa-link"></i> Link Externo</button>
                <button type="button" class="tipo-btn <?= $tipoAtivo==='upload'?'tipo-ativo':'' ?>" onclick="mudarTipoAula(this,'upload')" style="flex:1;padding:8px;border:none;cursor:pointer;font-size:.78rem;background:<?= $tipoAtivo==='upload'?'var(--azul-esc)':'var(--c50)' ?>;color:<?= $tipoAtivo==='upload'?'#fff':'var(--c600)' ?>"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Local</button>
            </div>
            <div class="tipo-painel tipo-yt" style="display:<?= $tipoAtivo==='yt'?'block':'none' ?>;padding:12px 14px">
                <input type="text" name="aula_yt[]" value="<?= htmlspecialchars($aula['youtube_id']??'') ?>" placeholder="URL ou ID do YouTube" style="width:100%;padding:8px 10px;border:1px solid var(--c300);border-radius:6px;font-size:.85rem" oninput="prevAulaYT(this)">
                <div class="yt-prev-wrap" style="<?= empty($aula['youtube_id'])?'display:none':'' ?>;margin-top:8px">
                    <?php if (!empty($aula['youtube_id'])): ?><iframe width="100%" height="180" src="https://www.youtube.com/embed/<?= htmlspecialchars($aula['youtube_id']) ?>" frameborder="0" allowfullscreen style="border-radius:6px;max-width:320px"></iframe><?php endif; ?>
                </div>
            </div>
            <div class="tipo-painel tipo-link" style="display:<?= $tipoAtivo==='link'?'block':'none' ?>;padding:12px 14px">
                <input type="url" name="aula_link[]" value="<?= htmlspecialchars($aula['link_externo']??'') ?>" placeholder="https://..." style="width:100%;padding:8px 10px;border:1px solid var(--c300);border-radius:6px;font-size:.85rem">
            </div>
            <div class="tipo-painel tipo-upload" style="display:<?= $tipoAtivo==='upload'?'block':'none' ?>;padding:12px 14px">
                <?php if (!empty($aula['arquivo_video'])): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:8px;background:var(--c50);border-radius:6px;border:1px solid var(--c200);margin-bottom:8px">
                    <i class="fa-solid fa-film" style="color:var(--azul-clr)"></i>
                    <span style="font-size:.82rem;flex:1"><?= htmlspecialchars($aula['arquivo_video']) ?></span>
                    <span style="font-size:.7rem;color:var(--verde)"><i class="fa-solid fa-check"></i> Já enviado</span>
                </div>
                <?php endif; ?>
                <div style="border:2px dashed var(--c300);border-radius:6px;padding:14px;text-align:center;cursor:pointer" onclick="this.querySelector('input').click()">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;color:var(--c400);margin-bottom:6px"></i>
                    <p style="font-size:.8rem;color:var(--c500);margin:0"><?= !empty($aula['arquivo_video'])?'Substituir vídeo':'Clique para selecionar vídeo' ?></p>
                    <input type="file" name="aula_video[]" accept=".mp4,.mov,.avi,.mkv,.webm" style="display:none" onchange="nomeVideoSelecionado(this)">
                </div>
                <div class="nome-video" style="font-size:.78rem;color:var(--verde);margin-top:4px"></div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <button type="button" onclick="adicionarAula()" class="btn btn-ghost" style="width:100%;justify-content:center;border-style:dashed">
            <i class="fa-solid fa-plus" style="color:var(--azul-clr)"></i> Adicionar Aula
        </button>
    </div>
</div>
<div style="display:flex;gap:10px;align-items:center">
    <button type="submit" class="btn btn-primario"><i class="fa-solid fa-floppy-disk"></i> Salvar Todas as Aulas</button>
    <span style="font-size:.78rem;color:var(--c400)"><i class="fa-solid fa-triangle-exclamation" style="color:var(--ouro)"></i> Ao salvar, a lista de aulas é substituída.</span>
</div>
</form>
<?php endif; ?>
</div><!-- /aba-aulas -->

<!-- ══════════════ ABA MATERIAIS ══════════════ -->
<div id="aba-materiais" class="aba-conteudo" style="display:<?= $abaAtiva==='materiais'?'flex':'none' ?>;flex-direction:column;gap:20px">
<form method="POST" enctype="multipart/form-data" id="fFormMat">
<input type="hidden" name="_acao"      value="salvar_curso">
<input type="hidden" name="_aba_ativa" value="materiais">
<?= camposOcultosCurso($c) ?>

<div class="card"><div class="card-header">
    <span class="card-titulo"><i class="fa-solid fa-paperclip"></i> Materiais de Apoio</span>
    <?php if (!empty($materiais)): ?><span style="font-size:.78rem;color:var(--c400)"><?= count($materiais) ?> arquivo<?= count($materiais)!=1?'s':'' ?></span><?php endif; ?>
</div><div class="card-body" style="display:flex;flex-direction:column;gap:14px">
    <?php if (!empty($materiais)): ?>
    <div style="display:flex;flex-direction:column;gap:6px">
    <?php foreach ($materiais as $mat_): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:var(--c50);border-radius:7px;border:1px solid var(--c200)">
        <i class="fa-solid fa-file" style="color:var(--azul-clr);flex-shrink:0"></i>
        <span style="flex:1;font-size:.85rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($mat_['nome_original']) ?></span>
        <span style="font-size:.72rem;color:var(--c400)"><?= round($mat_['tamanho']/1024) ?> KB</span>
        <a href="/crmv/admin/cursos/del_material.php?id=<?= $mat_['material_id'] ?>&curso_id=<?= $id ?>" class="btn btn-ghost btn-icone btn-sm" title="Remover" data-confirma="Remover este material?">
            <i class="fa-solid fa-trash" style="color:var(--verm)"></i>
        </a>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="border:2px dashed var(--c300);border-radius:8px;padding:20px;text-align:center;cursor:pointer" onclick="document.getElementById('inpMat').click()">
        <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.8rem;color:var(--c300);margin-bottom:8px"></i>
        <p style="font-size:.85rem;color:var(--c500);margin:0">Clique ou arraste arquivos aqui</p>
        <p style="font-size:.72rem;color:var(--c400);margin:4px 0 0">PDF, DOC, XLS, PPT, ZIP, MP4 — máx. 50MB</p>
        <input type="file" id="inpMat" name="materiais[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.mp4" style="display:none" onchange="listarArquivos(this)">
    </div>
    <div id="listaArqs" style="display:none;font-size:.82rem;color:var(--c600)"></div>
    <?php if ($editando): ?>
    <button type="submit" class="btn btn-primario"><i class="fa-solid fa-cloud-arrow-up"></i> Enviar Materiais</button>
    <?php endif; ?>
</div></div>
</form>
</div><!-- /aba-materiais -->

<!-- ══════════════ ABA AVALIAÇÃO ══════════════ -->
<div id="aba-avaliacao" class="aba-conteudo" style="display:<?= $abaAtiva==='avaliacao'?'flex':'none' ?>;flex-direction:column;gap:20px">
<?php if (!$editando): ?>
<div class="alerta alerta-aviso"><i class="fa-solid fa-triangle-exclamation"></i> Salve os dados do curso primeiro para criar a avaliação.</div>
<?php else: ?>
<?php if ($avaliacao): ?>
<div class="alerta alerta-info" style="align-items:center">
    <i class="fa-solid fa-clipboard-check" style="color:var(--verde);font-size:1.2rem"></i>
    <div style="flex:1">
        <strong><?= htmlspecialchars($avaliacao['titulo']) ?></strong>
        <span style="margin-left:8px;color:var(--c500);font-size:.82rem"><?= count($questoes) ?> questões · Nota mín: <?= $avaliacao['nota_minima'] ?> · Tentativas: <?= $avaliacao['tentativas_max'] ?></span>
    </div>
    <form method="POST" style="margin:0">
        <input type="hidden" name="_acao" value="apagar_avaliacao">
        <button type="submit" class="btn btn-ghost btn-sm" data-confirma="Apagar toda a avaliação e recriar?">
            <i class="fa-solid fa-trash" style="color:var(--verm)"></i> Apagar e recriar
        </button>
    </form>
</div>
<?php endif; ?>

<form method="POST" id="fFormAvaliacao">
<input type="hidden" name="_acao" value="salvar_avaliacao">
<div class="card"><div class="card-header"><span class="card-titulo"><i class="fa-solid fa-clipboard-question" style="color:var(--azul-clr)"></i> Configurações da Avaliação</span></div>
<div class="card-body"><div class="form-grid">
<div class="c8 form-group"><label>Título da Avaliação</label>
    <input type="text" name="av_titulo" value="<?= htmlspecialchars($avaliacao['titulo']??'Avaliação Final') ?>" placeholder="Ex: Avaliação de Aprendizagem"></div>
<div class="c4 form-group"><label>Nota Mínima para Aprovação</label>
    <div style="display:flex;align-items:center;gap:6px">
        <input type="number" name="av_nota_minima" min="0" max="100" step="0.5" value="<?= htmlspecialchars($avaliacao['nota_minima']??($c['nota_minima']??'70')) ?>" style="flex:1">
        <span style="font-size:.82rem;color:var(--c500)">pts</span>
    </div></div>
<div class="c6 form-group"><label>Instruções para o Aluno</label>
    <textarea name="av_descricao" rows="2" placeholder="Instruções antes de iniciar..."><?= htmlspecialchars($avaliacao['descricao']??'') ?></textarea></div>
<div class="c3 form-group"><label>Tentativas Máximas</label>
    <input type="number" name="av_tentativas_max" min="1" max="99" value="<?= htmlspecialchars($avaliacao['tentativas_max']??($c['tentativas_maximas']??'3')) ?>"></div>
<div class="c3 form-group"><label>Tempo Limite (min)</label>
    <input type="number" name="av_tempo_limite" min="0" value="<?= htmlspecialchars($avaliacao['tempo_limite']??'') ?>" placeholder="Sem limite"></div>
<div class="c12">
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.875rem">
        <input type="checkbox" name="av_randomizar" <?= ($avaliacao['randomizar']??0)?'checked':'' ?> style="accent-color:var(--azul-clr)">
        Embaralhar ordem das questões para cada aluno
    </label>
</div>
</div></div></div>

<div class="card"><div class="card-header">
    <span class="card-titulo"><i class="fa-solid fa-list-ol"></i> Questões de Múltipla Escolha
        <span id="cont-questoes" style="font-size:.75rem;color:var(--c400);margin-left:6px;font-weight:400"><?= count($questoes)?:1 ?> questão(ões)</span>
    </span>
    <button type="button" onclick="adicionarQuestao()" class="btn btn-ghost btn-sm">
        <i class="fa-solid fa-plus" style="color:var(--azul-clr)"></i> Nova Questão
    </button>
</div><div class="card-body" style="padding:14px">
    <div id="lista-questoes" style="display:flex;flex-direction:column;gap:14px">
    <?php if (empty($questoes)): ?>
    <div class="questao-bloco" data-qi="0" style="border:1.5px solid var(--c200);border-radius:var(--radius-lg);overflow:hidden">
        <?= renderQuestaoHTML(0, ['enunciado'=>'','pontos'=>1,'alts'=>[['texto'=>'','correta'=>true],['texto'=>'','correta'=>false],['texto'=>'','correta'=>false],['texto'=>'','correta'=>false]],'correta'=>0]) ?>
    </div>
    <?php else: ?>
    <?php foreach ($questoes as $qi => $q): ?>
    <div class="questao-bloco" data-qi="<?= $qi ?>" style="border:1.5px solid var(--c200);border-radius:var(--radius-lg);overflow:hidden">
        <?= renderQuestaoHTML($qi, $q) ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    </div>
    <button type="button" onclick="adicionarQuestao()" class="btn btn-ghost" style="width:100%;justify-content:center;border-style:dashed;margin-top:12px">
        <i class="fa-solid fa-plus" style="color:var(--azul-clr)"></i> Adicionar Questão
    </button>
</div></div>
<div style="display:flex;gap:10px;align-items:center">
    <button type="submit" class="btn btn-primario"><i class="fa-solid fa-floppy-disk"></i> Salvar Avaliação</button>
    <span style="font-size:.78rem;color:var(--c400)"><i class="fa-solid fa-circle-info"></i> Aparece para o aluno após concluir todas as aulas.</span>
</div>
</form>
<?php endif; ?>
</div><!-- /aba-avaliacao -->

<!-- ══════════════ ABA CERTIFICADO ══════════════ -->
<div id="aba-certificado" class="aba-conteudo" style="display:<?= $abaAtiva==='certificado'?'flex':'none' ?>;flex-direction:column;gap:20px">
<form method="POST" id="fFormCert">
<input type="hidden" name="_acao"      value="salvar_curso">
<input type="hidden" name="_aba_ativa" value="certificado">
<?= camposOcultosCurso($c) ?>

<div class="alerta alerta-info">
    <i class="fa-solid fa-robot" style="color:var(--azul-clr)"></i>
    <div><strong>Emissão automática pelo aluno</strong><br>
    <span style="font-size:.82rem">O certificado fica disponível quando o aluno concluir todas as aulas<?= ($c['requer_avaliacao']??0)?' e for aprovado na avaliação':'' ?>.</span></div>
</div>

<div class="card"><div class="card-header"><span class="card-titulo"><i class="fa-solid fa-sliders" style="color:var(--ouro)"></i> Configurações</span></div>
<div class="card-body"><div class="form-grid">
<div class="c4 form-group"><label>Validade</label>
    <div style="display:flex;align-items:center;gap:8px">
        <input type="number" name="cert_validade" min="0" max="120" value="<?= (int)($c['cert_validade']??0) ?>" placeholder="0" style="flex:1">
        <span style="font-size:.85rem;color:var(--c500);white-space:nowrap">meses</span>
    </div><span class="dica">Use 0 para certificado sem validade.</span></div>
<div class="c8 form-group"><label>Observação Interna</label>
    <input type="text" name="cert_obs" value="<?= htmlspecialchars($c['cert_obs']??'') ?>" placeholder="Não aparece no certificado"></div>
</div></div></div>

<div class="card"><div class="card-header">
    <span class="card-titulo"><i class="fa-solid fa-list-check" style="color:var(--verde)"></i> Conteúdo Programático — Verso</span>
    <span class="badge b-azul" style="font-size:.7rem">Aparece no verso impresso</span>
</div><div class="card-body" style="padding:0">
    <div id="editor-toolbar" style="display:flex;flex-wrap:wrap;gap:4px;padding:10px 16px;background:var(--c50);border-bottom:1px solid var(--c200);position:sticky;top:0;z-index:10">
        <div style="display:flex;gap:2px;border-right:1px solid var(--c200);padding-right:8px;margin-right:4px">
            <button type="button" onclick="execCmd('bold')"      class="tbtn" title="Negrito"><i class="fa-solid fa-bold"></i></button>
            <button type="button" onclick="execCmd('italic')"    class="tbtn" title="Itálico"><i class="fa-solid fa-italic"></i></button>
            <button type="button" onclick="execCmd('underline')" class="tbtn" title="Sublinhado"><i class="fa-solid fa-underline"></i></button>
        </div>
        <div style="display:flex;gap:2px;border-right:1px solid var(--c200);padding-right:8px;margin-right:4px">
            <button type="button" onclick="execCmd('insertUnorderedList')" class="tbtn"><i class="fa-solid fa-list-ul"></i></button>
            <button type="button" onclick="execCmd('insertOrderedList')"   class="tbtn"><i class="fa-solid fa-list-ol"></i></button>
        </div>
        <div style="margin-left:auto">
            <button type="button" onclick="inserirModulo()" class="btn btn-ghost btn-sm" style="height:30px;font-size:.78rem">
                <i class="fa-solid fa-puzzle-piece" style="color:var(--azul-clr)"></i> Inserir Módulo
            </button>
        </div>
    </div>
    <div id="editor-cert" contenteditable="true" spellcheck="true"
         style="min-height:260px;padding:20px 24px;font-size:.9rem;font-family:'Segoe UI',sans-serif;line-height:1.85;color:#1a1a1a;outline:none">
        <?= $c['cert_conteudo_programatico'] ?? '' ?>
    </div>
    <input type="hidden" name="cert_conteudo_programatico" id="cert_prog_input">
    <div style="padding:8px 16px;background:var(--c50);font-size:.72rem;color:var(--c400)">
        <span id="cert-char-count">0 caracteres</span>
    </div>
</div></div>

<button type="submit" class="btn btn-primario" style="align-self:flex-start">
    <i class="fa-solid fa-floppy-disk"></i> Salvar Configurações do Certificado
</button>
</form>
</div><!-- /aba-certificado -->

</div><!-- /col-principal -->

<!-- ══ COLUNA LATERAL ══ -->
<div style="display:flex;flex-direction:column;gap:20px">

<!-- Status -->
<div class="card"><div class="card-header"><span class="card-titulo"><i class="fa-solid fa-toggle-on"></i> Status</span></div>
<div class="card-body" style="display:flex;flex-direction:column;gap:10px">
<?php foreach (['RASCUNHO'=>['b-cinza','Rascunho','Visível apenas para admins'],'PUBLICADO'=>['b-verde','Publicado','Visível para inscrições'],'ENCERRADO'=>['b-verm','Encerrado','Inscrições fechadas']] as $v=>[$cls,$lbl,$desc]): ?>
<label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px;border-radius:7px;border:2px solid <?= ($c['status']??'RASCUNHO')===$v?'var(--azul-clr)':'var(--c200)' ?>" id="statusCard_<?= $v ?>">
    <input type="radio" name="_status_lateral" value="<?= $v ?>" <?= ($c['status']??'RASCUNHO')===$v?'checked':'' ?>
        onchange="sincronizarStatus(this.value);highlightStatus()" style="margin-top:2px;accent-color:var(--azul-clr)">
    <div><span class="badge <?= $cls ?>"><?= $lbl ?></span><div style="font-size:.72rem;color:var(--c400);margin-top:3px"><?= $desc ?></div></div>
</label>
<?php endforeach; ?>
</div></div>

<!-- Capa -->
<div class="card"><div class="card-header"><span class="card-titulo"><i class="fa-solid fa-image"></i> Capa</span></div>
<div class="card-body">
    <div id="prevCapa" style="<?= empty($c['capa'])?'display:none':'' ?>;margin-bottom:12px;border-radius:8px;overflow:hidden;border:1px solid var(--c200)">
        <img id="imgCapa" src="<?= !empty($c['capa'])?'/crmv/uploads/capas/'.htmlspecialchars($c['capa']):'' ?>" style="width:100%;max-height:160px;object-fit:cover">
    </div>
    <div style="border:2px dashed var(--c300);border-radius:8px;padding:16px;text-align:center;cursor:pointer" onclick="document.getElementById('inpCapaDados').click()">
        <i class="fa-solid fa-image" style="font-size:1.4rem;color:var(--c300);margin-bottom:6px"></i>
        <p style="font-size:.8rem;color:var(--c500);margin:0">JPG, PNG ou WEBP — máx. 5MB</p>
    </div>
    <div id="nomeCapaSelec" style="font-size:.78rem;color:var(--verde);margin-top:6px;text-align:center"></div>
    <?php if (!empty($c['capa']) && $editando): ?>
    <div style="margin-top:8px;text-align:center">
        <a href="/crmv/admin/cursos/del_capa.php?id=<?= $id ?>" class="btn btn-ghost btn-sm" data-confirma="Remover a capa?">
            <i class="fa-solid fa-trash" style="color:var(--verm)"></i> Remover
        </a>
    </div>
    <?php endif; ?>
</div></div>

<!-- Resumo -->
<?php if ($editando):
$tA = 0; foreach ($modulos as $m_) $tA += count($m_['aulas']);
$tM = count($materiais); $tQ = count($questoes);
?>
<div class="card"><div class="card-header"><span class="card-titulo"><i class="fa-solid fa-circle-info"></i> Resumo</span></div>
<div class="card-body" style="font-size:.8rem;display:flex;flex-direction:column;gap:2px">
<?php foreach ([['fa-play-circle','Aulas',$tA],['fa-paperclip','Materiais',$tM],['fa-clipboard-question','Questões',$tQ]] as [$ic,$lbl,$n]): ?>
<div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--c100)">
    <span style="color:var(--c500)"><i class="fa-solid <?= $ic ?>"></i> <?= $lbl ?></span>
    <strong><?= $n ?></strong>
</div>
<?php endforeach; ?>
<div style="display:flex;justify-content:space-between;padding:7px 0">
    <span style="color:var(--c500)"><i class="fa-solid fa-certificate"></i> Certificado auto</span>
    <strong style="color:var(--verde)"><i class="fa-solid fa-check"></i> Ativo</strong>
</div>
</div></div>
<?php endif; ?>

</div><!-- /col-lateral -->
</div><!-- /grid -->

<style>
.tbtn{padding:5px 9px;background:#fff;border:1px solid var(--c300);border-radius:5px;cursor:pointer;color:var(--c600);font-size:.78rem;height:30px;display:inline-flex;align-items:center;transition:all .15s}
.tbtn:hover{background:var(--azul-esc);color:#fff;border-color:var(--azul-esc)}
.tipo-btn{transition:background .15s,color .15s}
.tipo-ativo{background:var(--azul-esc)!important;color:#fff!important}
#editor-cert h2{font-size:1rem;font-weight:700;color:#0d2137;margin:14px 0 6px;border-bottom:1px solid #e8d89a;padding-bottom:4px}
#editor-cert h3{font-size:.9rem;font-weight:700;color:#0d2137;margin:10px 0 4px}
#editor-cert ul,#editor-cert ol{margin-left:20px}
#editor-cert li{margin-bottom:3px}
</style>

<script>
/* ── Abas ── */
function trocarAba(q){
    document.querySelectorAll('.aba-conteudo').forEach(function(e){e.style.display='none'});
    document.querySelectorAll('.tab-btn').forEach(function(e){e.classList.remove('ativo')});
    var a=document.getElementById('aba-'+q); if(a) a.style.display='flex';
    var t=document.getElementById('tab-'+q); if(t) t.classList.add('ativo');
    document.querySelectorAll('[name="_aba_ativa"]').forEach(function(e){e.value=q});
    document.querySelectorAll('[data-guard]').forEach(function(f){f.dataset.guardClean='1'});
}

/* ── Status lateral ── */
function sincronizarStatus(v){
    var s=document.getElementById('inp_status_dados'); if(s) s.value=v;
    document.querySelectorAll('[name="status"]').forEach(function(e){e.value=v});
}
function highlightStatus(){
    document.querySelectorAll('[id^="statusCard_"]').forEach(function(el){el.style.borderColor='var(--c200)'});
    var sel=document.querySelector('[name="_status_lateral"]:checked');
    if(sel){var card=document.getElementById('statusCard_'+sel.value);if(card) card.style.borderColor='var(--azul-clr)';}
}

/* ── Capa ── */
function prevCapaFn(inp){
    if(!inp.files||!inp.files[0]) return;
    var r=new FileReader();
    r.onload=function(e){
        var img=document.getElementById('imgCapa'); var prev=document.getElementById('prevCapa');
        if(img) img.src=e.target.result; if(prev) prev.style.display='';
        var nm=document.getElementById('nomeCapaSelec'); if(nm) nm.textContent='✓ '+inp.files[0].name;
    };
    r.readAsDataURL(inp.files[0]);
}

/* ── Toggle local ── */
function toggleLocal(v){
    var s=document.getElementById('secaoLocal'); if(s) s.style.display=(v==='EAD')?'none':'';
}

/* ── Listar arquivos materiais ── */
function listarArquivos(inp){
    var div=document.getElementById('listaArqs'); if(!div||!inp.files) return;
    if(!inp.files.length){div.style.display='none';return;}
    var html='<div style="margin-top:8px;display:flex;flex-direction:column;gap:4px">';
    for(var i=0;i<inp.files.length;i++){
        html+='<div style="display:flex;align-items:center;gap:8px;padding:5px 10px;background:var(--c50);border-radius:5px;border:1px solid var(--c200)">';
        html+='<i class="fa-solid fa-file" style="color:var(--azul-clr)"></i>';
        html+='<span style="flex:1;font-size:.82rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+inp.files[i].name+'</span>';
        html+='<span style="font-size:.7rem;color:var(--c400)">'+Math.round(inp.files[i].size/1024)+' KB</span>';
        html+='</div>';
    }
    html+='</div>';
    div.innerHTML=html; div.style.display='';
}

/* ── Aulas ── */
var _aulaIdx = document.querySelectorAll('.aula-bloco').length;
function adicionarAula(){
    var idx=_aulaIdx++;
    var tpl='<div class="aula-bloco" data-idx="'+idx+'" style="border:1.5px solid var(--c200);border-radius:var(--radius-lg);overflow:hidden">'
        +'<div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--azul-esc);color:#fff">'
        +'<span class="aula-num" style="width:22px;height:22px;border-radius:50%;background:var(--ouro);color:var(--azul-esc);font-weight:800;font-size:.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">'+(idx+1)+'</span>'
        +'<input type="text" name="aula_titulo[]" placeholder="Título da aula" style="flex:1;background:transparent;border:none;border-bottom:1px solid rgba(255,255,255,.3);color:#fff;font-weight:600;font-size:.9rem;outline:none;padding:2px 0">'
        +'<input type="number" name="aula_duracao[]" min="1" max="600" placeholder="min" style="width:62px;background:transparent;border:1px solid rgba(255,255,255,.3);border-radius:5px;color:#fff;font-size:.78rem;padding:3px 6px;text-align:center">'
        +'<button type="button" onclick="removerAula(this)" style="background:transparent;border:none;color:rgba(255,255,255,.6);cursor:pointer;font-size:.9rem;padding:4px;border-radius:4px"><i class="fa-solid fa-xmark"></i></button>'
        +'</div>'
        +'<div style="display:flex;border-bottom:1px solid var(--c200)">'
        +'<button type="button" class="tipo-btn tipo-ativo" onclick="mudarTipoAula(this,\'yt\')" style="flex:1;padding:8px;border:none;cursor:pointer;font-size:.78rem;background:var(--azul-esc);color:#fff;border-right:1px solid var(--c200)"><i class="fa-brands fa-youtube" style="color:#ff6b6b"></i> YouTube</button>'
        +'<button type="button" class="tipo-btn" onclick="mudarTipoAula(this,\'link\')" style="flex:1;padding:8px;border:none;cursor:pointer;font-size:.78rem;background:var(--c50);color:var(--c600);border-right:1px solid var(--c200)"><i class="fa-solid fa-link"></i> Link Externo</button>'
        +'<button type="button" class="tipo-btn" onclick="mudarTipoAula(this,\'upload\')" style="flex:1;padding:8px;border:none;cursor:pointer;font-size:.78rem;background:var(--c50);color:var(--c600)"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Local</button>'
        +'</div>'
        +'<div class="tipo-painel tipo-yt" style="display:block;padding:12px 14px"><input type="text" name="aula_yt[]" placeholder="URL ou ID do YouTube" style="width:100%;padding:8px 10px;border:1px solid var(--c300);border-radius:6px;font-size:.85rem" oninput="prevAulaYT(this)"><div class="yt-prev-wrap" style="display:none;margin-top:8px"></div></div>'
        +'<div class="tipo-painel tipo-link" style="display:none;padding:12px 14px"><input type="url" name="aula_link[]" placeholder="https://..." style="width:100%;padding:8px 10px;border:1px solid var(--c300);border-radius:6px;font-size:.85rem"></div>'
        +'<div class="tipo-painel tipo-upload" style="display:none;padding:12px 14px"><div style="border:2px dashed var(--c300);border-radius:6px;padding:14px;text-align:center;cursor:pointer" onclick="this.querySelector(\'input\').click()"><i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;color:var(--c400);margin-bottom:6px"></i><p style="font-size:.8rem;color:var(--c500);margin:0">Clique para selecionar vídeo</p><input type="file" name="aula_video[]" accept=".mp4,.mov,.avi,.mkv,.webm" style="display:none" onchange="nomeVideoSelecionado(this)"></div><div class="nome-video" style="font-size:.78rem;color:var(--verde);margin-top:4px"></div></div>'
        +'</div>';
    var lista=document.getElementById('lista-aulas'); if(lista){lista.insertAdjacentHTML('beforeend',tpl);renumerarAulas();}
}
function removerAula(btn){var b=btn.closest('.aula-bloco');if(b)b.remove();renumerarAulas();}
function renumerarAulas(){document.querySelectorAll('#lista-aulas .aula-bloco').forEach(function(b,i){var n=b.querySelector('.aula-num');if(n)n.textContent=i+1;});}
function mudarTipoAula(btn,tipo){
    var bloco=btn.closest('.aula-bloco'); if(!bloco) return;
    bloco.querySelectorAll('.tipo-btn').forEach(function(b){b.classList.remove('tipo-ativo');b.style.background='var(--c50)';b.style.color='var(--c600)';});
    btn.classList.add('tipo-ativo'); btn.style.background='var(--azul-esc)'; btn.style.color='#fff';
    bloco.querySelectorAll('.tipo-painel').forEach(function(p){p.style.display='none';});
    var panel=bloco.querySelector('.tipo-'+tipo); if(panel) panel.style.display='block';
}
function prevAulaYT(inp){
    var raw=inp.value.trim(); if(!raw) return;
    var m=raw.match(/(?:v=|\/embed\/|\/shorts\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    var ytId=m?m[1]:(raw.length<=11?raw:''); if(!ytId) return;
    var wrap=inp.closest('.tipo-yt').querySelector('.yt-prev-wrap');
    if(wrap){wrap.innerHTML='<iframe width="100%" height="180" src="https://www.youtube.com/embed/'+ytId+'" frameborder="0" allowfullscreen style="border-radius:6px;max-width:320px"></iframe>';wrap.style.display='';}
}
function nomeVideoSelecionado(inp){var div=inp.closest('.tipo-upload').querySelector('.nome-video');if(div&&inp.files&&inp.files[0])div.textContent='✓ '+inp.files[0].name;}

/* ── Questões ── */
var _qi=document.querySelectorAll('.questao-bloco').length;
function adicionarQuestao(){
    var qi=_qi++; var letras=['A','B','C','D'];
    var h='<div class="questao-bloco" data-qi="'+qi+'" style="border:1.5px solid var(--c200);border-radius:var(--radius-lg);overflow:hidden">';
    h+='<div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--azul-esc);color:#fff">';
    h+='<span class="q-num" style="width:22px;height:22px;border-radius:50%;background:var(--ouro);color:var(--azul-esc);font-weight:800;font-size:.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">'+(qi+1)+'</span>';
    h+='<span style="font-size:.75rem;color:rgba(255,255,255,.7)">Questão '+(qi+1)+'</span>';
    h+='<div style="display:flex;align-items:center;gap:6px;margin-left:auto"><span style="font-size:.72rem;opacity:.7">Pontos:</span><input type="number" name="q_pontos[]" min="0.5" max="100" step="0.5" value="1" style="width:55px;background:transparent;border:1px solid rgba(255,255,255,.4);border-radius:4px;color:#fff;font-size:.78rem;padding:2px 6px;text-align:center"><button type="button" onclick="removerQuestao(this)" style="background:transparent;border:none;color:rgba(255,255,255,.6);cursor:pointer;padding:4px;border-radius:4px"><i class="fa-solid fa-xmark"></i></button></div></div>';
    h+='<div style="padding:12px 14px;background:var(--c50);border-bottom:1px solid var(--c200)"><textarea name="q_enunciado[]" rows="2" placeholder="Enunciado da questão..." required style="width:100%;padding:8px;border:1px solid var(--c300);border-radius:6px;font-size:.88rem;resize:vertical"></textarea></div>';
    h+='<div style="padding:12px 14px;display:flex;flex-direction:column;gap:6px"><div style="font-size:.72rem;color:var(--c400);margin-bottom:2px"><i class="fa-solid fa-circle-check" style="color:var(--verde)"></i> Marque a alternativa correta</div>';
    for(var a=0;a<4;a++){
        h+='<label style="display:flex;align-items:center;gap:8px;flex:1;padding:7px 10px;border-radius:6px;cursor:pointer;border:1.5px solid var(--c200);background:#fff">';
        h+='<input type="radio" name="q_correta['+qi+']" value="'+a+'" '+(a===0?'checked':'')+' onchange="destacarCorreta(this)" style="accent-color:var(--verde)">';
        h+='<span style="width:18px;height:18px;border-radius:50%;background:var(--azul-esc);color:var(--ouro);font-weight:700;font-size:.7rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">'+letras[a]+'</span>';
        h+='<input type="text" name="q_alt'+a+'[]" placeholder="Alternativa '+letras[a]+'" style="flex:1;border:none;outline:none;background:transparent;font-size:.85rem;color:var(--c700)">';
        h+='</label>';
    }
    h+='</div></div>';
    var lista=document.getElementById('lista-questoes'); if(lista){lista.insertAdjacentHTML('beforeend',h);atualizarContQuestoes();}
}
function removerQuestao(btn){var b=btn.closest('.questao-bloco');if(b)b.remove();atualizarContQuestoes();}
function atualizarContQuestoes(){var n=document.querySelectorAll('.questao-bloco').length;var el=document.getElementById('cont-questoes');if(el)el.textContent=n+' questão(ões)';}
function destacarCorreta(radio){
    var bloco=radio.closest('.questao-bloco'); if(!bloco) return;
    bloco.querySelectorAll('label').forEach(function(l){l.style.borderColor='var(--c200)';l.style.background='#fff';});
    var lbl=radio.closest('label'); if(lbl){lbl.style.borderColor='var(--verde)';lbl.style.background='#f0fdf4';}
}

/* ── Editor certificado ── */
var _editorCert=document.getElementById('editor-cert');
var _certInput =document.getElementById('cert_prog_input');
function execCmd(cmd,val){document.execCommand(cmd,false,val||null);if(_editorCert&&_certInput)_certInput.value=_editorCert.innerHTML;atualizarContChars();}
function inserirModulo(){var n=prompt('Nome do módulo/tópico:');if(!n)return;document.execCommand('formatBlock',false,'h2');document.execCommand('insertText',false,n);if(_editorCert&&_certInput)_certInput.value=_editorCert.innerHTML;}
function atualizarContChars(){if(!_editorCert)return;var n=(_editorCert.innerText||'').length;var el=document.getElementById('cert-char-count');if(el)el.textContent=n+' caracteres';}
if(_editorCert){
    _editorCert.addEventListener('input',function(){atualizarContChars();if(_certInput)_certInput.value=_editorCert.innerHTML;});
    atualizarContChars();
}
var fCert=document.getElementById('fFormCert');
if(fCert) fCert.addEventListener('submit',function(){if(_editorCert&&_certInput)_certInput.value=_editorCert.innerHTML;});

/* ── Sync status ao submeter qualquer form ── */
document.querySelectorAll('form').forEach(function(f){
    f.addEventListener('submit',function(){
        var sel=document.querySelector('[name="_status_lateral"]:checked');
        if(sel) f.querySelectorAll('[name="status"]').forEach(function(e){e.value=sel.value;});
        var sid=document.getElementById('inp_status_dados');
        if(sid&&sel) sid.value=sel.value;
    });
});

/* Inicialização */
highlightStatus();
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
