<?php
require_once __DIR__ . '/../includes/conexao.php';
exigeLogin();

// Admin não acessa rota do aluno
if ((int)($_SESSION['usr_perfil'] ?? 0) === 1) {
    header('Location: /crmv/admin/dashboard.php'); exit;
}

$usr_id       = (int)$_SESSION['usr_id'];
$matricula_id = (int)($_GET['id'] ?? 0);
if (!$matricula_id) { header('Location: /crmv/aluno/dashboard.php'); exit; }

/*
 * JOINS:
 *   tbl_instrutores  (alias i)  → vinculado por c.instrutor_id
 *                               → colunas: nome, titulo, assinatura_img  ✓
 *   tbl_curso_instrutores       → NÃO usado aqui (não tem assinatura_img)
 */
$cert = dbQueryOne(
    "SELECT cert.cert_id,
            cert.codigo,
            cert.emitido_em,
            cert.valido,
            m.matricula_id,
            m.nota_final,
            u.nome_completo,
            u.crmv_numero,
            u.crmv_uf,
            c.curso_id,
            c.titulo            AS curso_titulo,
            c.tipo,
            c.carga_horaria,
            c.data_inicio,
            c.data_fim,
            c.local_cidade,
            c.local_uf,
            c.cert_conteudo_programatico,
            i.nome              AS instrutor_nome,
            i.titulo            AS instrutor_titulo,
            i.assinatura_img
     FROM   tbl_certificados cert
     INNER JOIN tbl_matriculas  m ON m.matricula_id = cert.matricula_id
     INNER JOIN tbl_usuarios    u ON u.usuario_id   = m.usuario_id
     INNER JOIN tbl_cursos      c ON c.curso_id     = m.curso_id
     LEFT  JOIN tbl_instrutores i ON i.instrutor_id = c.instrutor_id
     WHERE  m.matricula_id = ?
       AND  m.usuario_id   = ?
       AND  cert.valido    = 1
     LIMIT 1",
    [$matricula_id, $usr_id]
);

if (!$cert) {
    flash('Certificado não encontrado ou não disponível.', 'erro');
    header('Location: /crmv/aluno/certificados.php'); exit;
}

$urlValidacao = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . '/crmv/validar.php?codigo=' . urlencode($cert['codigo']);

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data='
    . urlencode($urlValidacao) . '&color=0d2137';

$linkImprimir = '/crmv/aluno/imprimir_cert.php?codigo=' . urlencode($cert['codigo']);

$pageTitulo  = 'Meu Certificado';
$paginaAtiva = 'certificados';
require_once __DIR__ . '/../includes/layout_aluno.php';

function fmtD2(string $d): string {
    if (!$d) return '';
    [$y, $m, $dia] = explode('-', $d);
    return "$dia/$m/$y";
}
function dataPorExtensoAluno(string $dt): string {
    $ts = strtotime($dt);
    $meses = ['','janeiro','fevereiro','março','abril','maio','junho',
              'julho','agosto','setembro','outubro','novembro','dezembro'];
    return date('d', $ts) . ' de ' . $meses[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
}
?>

<div class="pg-header">
  <div class="pg-header-row">
    <div>
      <h1 class="pg-titulo">Meu Certificado</h1>
      <p class="pg-subtitulo">Código:
        <code style="font-family:monospace;font-weight:700;color:var(--azul-esc)">
          <?= htmlspecialchars($cert['codigo']) ?>
        </code>
      </p>
    </div>
    <div class="pg-acoes">
      <a href="<?= $linkImprimir ?>" target="_blank" class="btn btn-primario">
        <i class="fa-solid fa-print"></i> Imprimir / PDF
      </a>
      <a href="/crmv/validar.php?codigo=<?= urlencode($cert['codigo']) ?>"
         target="_blank" class="btn btn-ghost">
        <i class="fa-solid fa-qrcode"></i> Validar
      </a>
      <a href="/crmv/aluno/certificados.php" class="btn btn-ghost">
        <i class="fa-solid fa-arrow-left"></i> Voltar
      </a>
    </div>
  </div>
</div>

<!-- ── Tabs Frente / Verso ──────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header" style="padding:0 20px">
    <div class="tabs-barra" style="border-bottom:none;margin:0;width:100%">
      <a href="javascript:void(0)" onclick="mostrarAba('frente',this)"
         class="tab-btn ativo" id="tab-frente">
        <i class="fa-solid fa-id-card"></i> Frente
      </a>
      <a href="javascript:void(0)" onclick="mostrarAba('verso',this)"
         class="tab-btn" id="tab-verso">
        <i class="fa-solid fa-list-check"></i> Verso — Conteúdo Programático
      </a>
    </div>
  </div>

  <!-- ════ FRENTE ═════════════════════════════════════════════ -->
  <div id="aba-frente" class="card-body" style="padding:28px;background:#f5f5f0">
    <div style="max-width:860px;margin:0 auto;background:#fff;border:1px solid #c9a227;
                box-shadow:0 4px 24px rgba(0,0,0,.14);position:relative;overflow:hidden">

      <!-- Bordas decorativas -->
      <div style="position:absolute;inset:8px;border:2px solid #c9a227;pointer-events:none;z-index:1"></div>
      <div style="position:absolute;inset:13px;border:1px solid rgba(201,162,39,.35);pointer-events:none;z-index:1"></div>
      <!-- Faixa lateral esquerda -->
      <div style="position:absolute;left:0;top:0;bottom:0;width:18px;
                  background:repeating-linear-gradient(180deg,#0d2137 0,#0d2137 16px,#c9a227 16px,#c9a227 22px);
                  z-index:2"></div>
      <!-- Marca d'água -->
      <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
                  opacity:.03;z-index:0;pointer-events:none">
        <i class="fa-solid fa-shield-halved" style="font-size:18rem;color:#0d2137"></i>
      </div>

      <div style="position:relative;z-index:3;padding:40px 52px 36px 52px">

        <!-- Cabeçalho -->
        <div style="display:flex;align-items:center;justify-content:center;gap:20px;
                    padding-bottom:18px;border-bottom:2px solid #c9a227;margin-bottom:18px">
          <div style="width:62px;height:62px;background:#0d2137;border-radius:50%;
                      display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fa-solid fa-shield-halved" style="font-size:1.7rem;color:#c9a227"></i>
          </div>
          <div>
            <div style="font-size:.8rem;font-weight:700;color:#0d2137;letter-spacing:.1em;text-transform:uppercase">
              Conselho Regional de Medicina Veterinária
            </div>
            <div style="font-size:1.75rem;font-weight:900;color:#0d2137;letter-spacing:.12em;line-height:1">
              CRMV-TO
            </div>
            <div style="font-size:.62rem;color:#888;letter-spacing:.07em;text-transform:uppercase;margin-top:2px">
              Tocantins
            </div>
          </div>
          <div style="margin-left:24px;text-align:center">
            <div style="font-size:1.5rem;font-weight:900;letter-spacing:.22em;text-transform:uppercase;color:#0d2137">
              CERTIFICADO
            </div>
            <div style="font-size:.7rem;color:#888;letter-spacing:.1em;text-transform:uppercase;margin-top:3px">
              de Participação e Conclusão
            </div>
          </div>
        </div>

        <!-- Corpo -->
        <div style="text-align:center;line-height:1.85;font-size:.95rem;color:#222">
          <p style="margin:0 0 6px">
            O <strong>Conselho Regional de Medicina Veterinária do Tocantins — CRMV-TO</strong>
            certifica que
          </p>
          <div style="margin:16px 0;padding:14px 20px;
                      border-top:1px solid #e8d89a;border-bottom:1px solid #e8d89a;
                      background:linear-gradient(to right,transparent,#fefce8 30%,#fefce8 70%,transparent)">
            <div style="font-size:1.5rem;font-weight:900;color:#0d2137;
                        letter-spacing:.03em;font-family:Georgia,serif">
              <?= htmlspecialchars($cert['nome_completo']) ?>
            </div>
            <?php if ($cert['crmv_numero']): ?>
            <div style="font-size:.78rem;color:#666;margin-top:3px;letter-spacing:.07em;text-transform:uppercase">
              CRMV <?= htmlspecialchars($cert['crmv_numero']) ?>-<?= htmlspecialchars($cert['crmv_uf']) ?>
            </div>
            <?php endif; ?>
          </div>

          <p style="margin:12px 0">participou e concluiu com êxito
            <?= match(strtoupper($cert['tipo'])) {
                'PALESTRA'  => 'a Palestra',
                'WORKSHOP'  => 'o Workshop',
                'CONGRESSO' => 'o Congresso',
                default     => 'o Curso'
            } ?>
          </p>
          <div style="font-size:1.12rem;font-weight:700;color:#0d2137;margin:10px 0">
            "<?= htmlspecialchars($cert['curso_titulo']) ?>"
          </div>
          <p style="font-size:.88rem;color:#555;margin:10px 0">
            com carga horária de <strong><?= $cert['carga_horaria'] ?> horas</strong>
            <?php if ($cert['data_inicio']): ?>
            , realizado em
            <strong><?= fmtD2($cert['data_inicio']) ?>
              <?= ($cert['data_fim'] && $cert['data_fim'] !== $cert['data_inicio'])
                   ? ' a ' . fmtD2($cert['data_fim']) : '' ?>
            </strong>
            <?php endif; ?>
            <?php if ($cert['local_cidade']): ?>
            , em <?= htmlspecialchars($cert['local_cidade']) ?>/<?= htmlspecialchars($cert['local_uf']) ?>
            <?php endif; ?>.
          </p>
        </div>

        <!-- Assinaturas -->
        <div style="margin-top:30px;display:grid;
                    grid-template-columns:1fr 1fr<?= $cert['instrutor_nome'] ? ' 1fr' : '' ?>;
                    gap:20px;text-align:center">

          <!-- Presidente -->
          <div>
            <div style="height:44px;border-bottom:1.5px solid #999;margin:0 12px 5px"></div>
            <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;
                        letter-spacing:.05em;color:#0d2137">
              Presidente do CRMV-TO
            </div>
            <div style="font-size:.65rem;color:#888">Presidente</div>
          </div>

          <!-- Data -->
          <div>
            <div style="height:44px;display:flex;align-items:flex-end;justify-content:center;
                        padding-bottom:5px;border-bottom:1.5px solid #999;margin:0 12px 5px;
                        font-size:.8rem;color:#555">
              Palmas/TO, <?= dataPorExtensoAluno($cert['emitido_em']) ?>
            </div>
            <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;
                        letter-spacing:.05em;color:#0d2137">Local e Data</div>
            <div style="font-size:.65rem;color:#888">de emissão</div>
          </div>

          <!-- Instrutor (se houver) -->
          <?php if ($cert['instrutor_nome']): ?>
          <div>
            <div style="height:44px;border-bottom:1.5px solid #999;margin:0 12px 5px;
                        display:flex;align-items:flex-end;justify-content:center;padding-bottom:4px">
              <?php if ($cert['assinatura_img']): ?>
              <img src="/crmv/uploads/assinaturas/<?= htmlspecialchars($cert['assinatura_img']) ?>"
                   style="max-height:38px;max-width:120px;object-fit:contain">
              <?php endif; ?>
            </div>
            <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;
                        letter-spacing:.05em;color:#0d2137">
              <?= htmlspecialchars($cert['instrutor_nome']) ?>
            </div>
            <div style="font-size:.65rem;color:#888">
              <?= $cert['instrutor_titulo'] ? htmlspecialchars($cert['instrutor_titulo']) : 'Instrutor(a)' ?>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- QR + código -->
        <div style="margin-top:20px;padding-top:12px;border-top:1px solid #e8d89a;
                    display:flex;align-items:center;justify-content:space-between;gap:16px">
          <div style="font-size:.66rem;color:#999;line-height:1.6;max-width:65%">
            Certificado emitido digitalmente pelo sistema CRMV/TO.<br>
            Autenticidade: <strong style="color:#0d2137"><?= htmlspecialchars(
                parse_url($urlValidacao, PHP_URL_HOST) . parse_url($urlValidacao, PHP_URL_PATH)
            ) ?></strong>
          </div>
          <div style="text-align:center;flex-shrink:0">
            <img src="<?= $qrUrl ?>" width="78" height="78" alt="QR"
                 style="border:2px solid #0d2137;border-radius:4px;padding:2px;background:#fff">
            <div style="font-family:monospace;font-size:.6rem;color:#0d2137;font-weight:700;margin-top:3px">
              <?= htmlspecialchars($cert['codigo']) ?>
            </div>
          </div>
        </div>

      </div><!-- /padding interno -->
    </div><!-- /card certificado frente -->
  </div><!-- /aba-frente -->

  <!-- ════ VERSO ══════════════════════════════════════════════ -->
  <div id="aba-verso" class="card-body" style="padding:28px;background:#f5f5f0;display:none">
    <div style="max-width:860px;margin:0 auto;background:#fff;border:1px solid #c9a227;
                box-shadow:0 4px 24px rgba(0,0,0,.14);position:relative;overflow:hidden">

      <div style="position:absolute;inset:8px;border:2px solid #c9a227;pointer-events:none;z-index:1"></div>
      <div style="position:absolute;inset:13px;border:1px solid rgba(201,162,39,.35);pointer-events:none;z-index:1"></div>
      <!-- Faixa lateral direita (espelho) -->
      <div style="position:absolute;right:0;top:0;bottom:0;width:18px;
                  background:repeating-linear-gradient(180deg,#0d2137 0,#0d2137 16px,#c9a227 16px,#c9a227 22px);
                  z-index:2"></div>
      <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
                  opacity:.03;z-index:0;pointer-events:none">
        <i class="fa-solid fa-shield-halved" style="font-size:18rem;color:#0d2137"></i>
      </div>

      <div style="position:relative;z-index:3;padding:36px 52px 36px 36px">
        <!-- Cabeçalho do verso -->
        <div style="display:flex;align-items:center;gap:14px;
                    padding-bottom:16px;border-bottom:2px solid #c9a227;margin-bottom:20px">
          <div style="width:40px;height:40px;background:#0d2137;border-radius:50%;
                      display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fa-solid fa-list-check" style="font-size:1rem;color:#c9a227"></i>
          </div>
          <div>
            <div style="font-size:1.1rem;font-weight:900;letter-spacing:.1em;
                        text-transform:uppercase;color:#0d2137">
              Conteúdo Programático
            </div>
            <div style="font-size:.72rem;color:#888">
              <?= htmlspecialchars($cert['curso_titulo']) ?> · <?= $cert['carga_horaria'] ?>h
            </div>
          </div>
        </div>

        <!-- Conteúdo -->
        <?php if (!empty(trim(strip_tags($cert['cert_conteudo_programatico'] ?? '')))): ?>
        <div style="font-size:.88rem;color:#1a1a1a;line-height:1.75">
          <?= $cert['cert_conteudo_programatico'] ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:48px 20px;color:#aaa">
          <i class="fa-solid fa-file-circle-xmark"
             style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:12px"></i>
          Nenhum conteúdo programático cadastrado para este curso.
        </div>
        <?php endif; ?>

        <div style="margin-top:28px;padding-top:12px;border-top:1px solid #e8d89a;
                    display:flex;justify-content:space-between;font-size:.65rem;color:#aaa">
          <span>CRMV-TO — <?= htmlspecialchars($cert['codigo']) ?></span>
          <span>Verso do Certificado</span>
        </div>
      </div>
    </div>
  </div><!-- /aba-verso -->

</div><!-- /card tabs -->

<!-- Info + QR -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div class="card">
    <div class="card-header">
      <span class="card-titulo"><i class="fa-solid fa-circle-info"></i> Dados do Certificado</span>
    </div>
    <div class="card-body" style="font-size:.875rem;display:flex;flex-direction:column;gap:7px">
      <?php foreach ([
          ['Código',        $cert['codigo']],
          ['Emitido em',    fmtDataHora($cert['emitido_em'])],
          ['Veterinário',   $cert['nome_completo']],
          ['CRMV',          $cert['crmv_numero'] ? $cert['crmv_numero'].'-'.$cert['crmv_uf'] : '—'],
          ['Curso',         $cert['curso_titulo']],
          ['Carga Horária', $cert['carga_horaria'].'h'],
      ] as [$rot, $val]): ?>
      <div style="display:flex;justify-content:space-between;padding:5px 0;
                  border-bottom:1px solid var(--c100)">
        <span style="color:var(--c500)"><?= $rot ?></span>
        <strong style="color:var(--c700)"><?= htmlspecialchars((string)$val) ?></strong>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-titulo"><i class="fa-solid fa-qrcode"></i> Validação Online</span>
    </div>
    <div class="card-body" style="display:flex;flex-direction:column;align-items:center;
                                   gap:14px;text-align:center">
      <img src="<?= $qrUrl ?>" width="120" height="120" alt="QR Code"
           style="border:3px solid var(--azul-esc);border-radius:8px;padding:4px">
      <div>
        <p style="font-size:.82rem;color:var(--c500);margin:0 0 8px">
          Qualquer pessoa pode verificar a autenticidade:
        </p>
        <a href="<?= htmlspecialchars($urlValidacao) ?>" target="_blank"
           style="font-size:.8rem;color:var(--azul-clr);word-break:break-all">
          <?= htmlspecialchars($urlValidacao) ?>
        </a>
      </div>
      <div style="display:flex;gap:8px">
        <a href="<?= htmlspecialchars($urlValidacao) ?>" target="_blank" class="btn btn-ghost btn-sm">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir
        </a>
        <a href="<?= $linkImprimir ?>" target="_blank" class="btn btn-secundario btn-sm">
          <i class="fa-solid fa-print"></i> Imprimir
        </a>
      </div>
    </div>
  </div>
</div>

<script>
function mostrarAba(qual, el) {
    ['frente','verso'].forEach(function(id){
        document.getElementById('aba-'+id).style.display = 'none';
        document.getElementById('tab-'+id).classList.remove('ativo');
    });
    document.getElementById('aba-'+qual).style.display = 'block';
    el.classList.add('ativo');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_aluno_footer.php'; ?>
