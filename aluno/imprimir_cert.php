<?php
/**
 * imprimir_cert.php  — rota do ALUNO para imprimir/PDF do certificado
 * Arquivo: /crmv/aluno/imprimir_cert.php
 *
 * NÃO usa exigeAdmin(). Usa exigeLogin() e valida que o certificado
 * pertence ao usuário logado.
 */
require_once __DIR__ . '/../includes/conexao.php';
exigeLogin();

// Admin usa rota própria
if ((int)($_SESSION['usr_perfil'] ?? 0) === 1) {
    header('Location: /crmv/admin/certificados/imprimir.php?' . $_SERVER['QUERY_STRING']); exit;
}

$usr_id = (int)$_SESSION['usr_id'];
$codigo = trim($_GET['codigo'] ?? '');
if (!$codigo) { header('Location: /crmv/aluno/certificados.php'); exit; }

/*
 * JOIN correto:
 *   tbl_instrutores i  →  c.instrutor_id   →  tem assinatura_img ✓
 *   tbl_curso_instrutores  →  NÃO usado aqui
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
     WHERE  cert.codigo   = ?
       AND  m.usuario_id  = ?
       AND  cert.valido   = 1
     LIMIT 1",
    [$codigo, $usr_id]
);

if (!$cert) {
    flash('Certificado não encontrado.', 'erro');
    header('Location: /crmv/aluno/certificados.php'); exit;
}

$urlValidacao = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . '/crmv/validar.php?codigo=' . urlencode($cert['codigo']);

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data='
    . urlencode($urlValidacao) . '&color=0d2137';

$temVerso = !empty(trim(strip_tags($cert['cert_conteudo_programatico'] ?? '')));

function fmtD2p(string $d): string {
    if (!$d) return '';
    [$y, $m, $dia] = explode('-', $d);
    return "$dia/$m/$y";
}
function dataPorExtensoPrint(string $dt): string {
    $ts = strtotime($dt);
    $meses = ['','janeiro','fevereiro','março','abril','maio','junho',
              'julho','agosto','setembro','outubro','novembro','dezembro'];
    return date('d', $ts) . ' de ' . $meses[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Certificado — <?= htmlspecialchars($cert['nome_completo']) ?></title>
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #e8e8e8;
    color: #1a1a1a;
  }
  .pagina {
    width: 297mm;
    min-height: 210mm;
    background: #fff;
    margin: 10mm auto;
    position: relative;
    overflow: hidden;
    border: 1px solid #c9a227;
  }
  .borda-ext   { position:absolute;inset:8px;border:2.5px solid #c9a227;pointer-events:none;z-index:1; }
  .borda-int   { position:absolute;inset:13px;border:1px solid rgba(201,162,39,.35);pointer-events:none;z-index:1; }
  .faixa-esq   {
    position:absolute;left:0;top:0;bottom:0;width:18px;
    background:repeating-linear-gradient(180deg,#0d2137 0,#0d2137 16px,#c9a227 16px,#c9a227 22px);
    z-index:2;
  }
  .faixa-dir   {
    position:absolute;right:0;top:0;bottom:0;width:18px;
    background:repeating-linear-gradient(180deg,#0d2137 0,#0d2137 16px,#c9a227 16px,#c9a227 22px);
    z-index:2;
  }
  .marca-dagua {
    position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
    opacity:.025;z-index:0;pointer-events:none;
  }
  .marca-dagua i { font-size:200pt;color:#0d2137; }
  .conteudo { position:relative;z-index:3;padding:22mm 26mm 18mm 26mm; }

  /* Cabeçalho */
  .cabec { display:flex;align-items:center;justify-content:center;gap:18px;
            padding-bottom:14px;border-bottom:2px solid #c9a227;margin-bottom:14px; }
  .cabec-logo { width:58px;height:58px;background:#0d2137;border-radius:50%;
                display:flex;align-items:center;justify-content:center;flex-shrink:0; }
  .cabec-logo i { font-size:1.55rem;color:#c9a227; }
  .cabec-nome { font-size:.75rem;font-weight:700;color:#0d2137;letter-spacing:.09em;text-transform:uppercase; }
  .cabec-sigla { font-size:1.65rem;font-weight:900;color:#0d2137;letter-spacing:.12em;line-height:1; }
  .cabec-sub   { font-size:.58rem;color:#888;letter-spacing:.06em;text-transform:uppercase;margin-top:1px; }
  .cabec-titulo {
    margin-left:20px;text-align:center;
    font-size:1.45rem;font-weight:900;letter-spacing:.2em;text-transform:uppercase;color:#0d2137;
  }
  .cabec-titulo small { display:block;font-size:.62rem;font-weight:400;letter-spacing:.08em;
                         text-transform:uppercase;color:#888;margin-top:2px; }

  /* Corpo */
  .corpo { text-align:center;font-size:.88rem;color:#222;line-height:1.75; }
  .destaque-nome {
    margin:12px 0;padding:12px 18px;
    border-top:1px solid #e8d89a;border-bottom:1px solid #e8d89a;
    background:linear-gradient(to right,transparent,#fefce8 30%,#fefce8 70%,transparent);
  }
  .destaque-nome strong {
    display:block;font-size:1.4rem;font-weight:900;color:#0d2137;
    font-family:Georgia,serif;letter-spacing:.02em;
  }
  .destaque-nome span { font-size:.72rem;color:#666;letter-spacing:.07em;text-transform:uppercase; }
  .curso-titulo { font-size:1.05rem;font-weight:700;color:#0d2137;margin:8px 0; }
  .detalhes { font-size:.82rem;color:#555;margin:8px 0; }

  /* Assinaturas */
  .assinaturas { display:flex;justify-content:space-around;margin-top:20px;gap:16px; }
  .assin-bloco { flex:1;text-align:center; }
  .assin-linha {
    height:40px;display:flex;align-items:flex-end;justify-content:center;
    border-bottom:1.5px solid #999;padding-bottom:4px;margin-bottom:4px;
    font-size:.75rem;color:#555;
  }
  .assin-linha img { max-height:34px;max-width:110px;object-fit:contain; }
  .assin-nome { font-size:.7rem;font-weight:700;text-transform:uppercase;
                letter-spacing:.04em;color:#0d2137; }
  .assin-cargo { font-size:.6rem;color:#888; }

  /* Rodapé QR */
  .rodape { margin-top:14px;padding-top:10px;border-top:1px solid #e8d89a;
            display:flex;align-items:center;justify-content:space-between;gap:12px; }
  .rodape-txt { font-size:.6rem;color:#999;line-height:1.5; }
  .rodape-txt strong { color:#0d2137; }
  .rodape-qr { text-align:center;flex-shrink:0; }
  .rodape-qr img { border:2px solid #0d2137;border-radius:3px;padding:2px;background:#fff; }
  .rodape-codigo { font-family:monospace;font-size:.55rem;color:#0d2137;font-weight:700;margin-top:2px; }

  /* Verso */
  .verso-cabec { display:flex;align-items:center;gap:12px;
                  padding-bottom:12px;border-bottom:2px solid #c9a227;margin-bottom:16px; }
  .verso-icone { width:36px;height:36px;background:#0d2137;border-radius:50%;
                  display:flex;align-items:center;justify-content:center;flex-shrink:0; }
  .verso-icone i { font-size:.9rem;color:#c9a227; }
  .verso-titulo { font-size:1rem;font-weight:900;letter-spacing:.08em;
                   text-transform:uppercase;color:#0d2137; }
  .verso-sub { font-size:.68rem;color:#888; }
  .verso-conteudo { font-size:.82rem;color:#1a1a1a;line-height:1.7; }
  .verso-conteudo h2 { font-size:.9rem;font-weight:700;color:#0d2137;
                        margin:10px 0 4px;border-bottom:1px solid #e8d89a;padding-bottom:3px; }
  .verso-conteudo h3 { font-size:.82rem;font-weight:700;color:#0d2137;margin:8px 0 3px; }
  .verso-conteudo h4 { font-size:.76rem;font-weight:700;color:#555;margin:6px 0 2px; }
  .verso-conteudo ul, .verso-conteudo ol { margin-left:18px; }
  .verso-conteudo li { margin-bottom:2px; }
  .verso-rodape { margin-top:16px;padding-top:8px;border-top:1px solid #e8d89a;
                   display:flex;justify-content:space-between;font-size:.6rem;color:#aaa; }

  /* Print */
  @media print {
    body { background:#fff; }
    .pagina { margin:0;border:none;width:100%; }
    .sem-impressao { display:none !important; }
    @page { size:A4 landscape;margin:0; }
  }
  .page-break { page-break-before: always; }
</style>
</head>
<body>

<!-- Botões (não imprimem) -->
<div class="sem-impressao" style="text-align:center;padding:12px;background:#333;
     display:flex;align-items:center;justify-content:center;gap:12px">
  <button onclick="window.print()"
      style="padding:9px 24px;background:#c9a227;color:#fff;border:none;
             border-radius:6px;font-size:.9rem;font-weight:700;cursor:pointer">
    <i class="fa-solid fa-print"></i> Imprimir / Salvar PDF
  </button>
  <a href="/crmv/aluno/certificado_ver.php?id=<?= $cert['matricula_id'] ?>"
     style="padding:9px 18px;background:#555;color:#fff;border-radius:6px;
            font-size:.85rem;text-decoration:none">
    <i class="fa-solid fa-arrow-left"></i> Voltar
  </a>
</div>

<!-- ══ FRENTE ══════════════════════════════════════════════ -->
<div class="pagina">
  <div class="borda-ext"></div>
  <div class="borda-int"></div>
  <div class="faixa-esq"></div>
  <div class="marca-dagua"><i class="fa-solid fa-shield-halved"></i></div>

  <div class="conteudo">
    <!-- Cabeçalho -->
    <div class="cabec">
      <div class="cabec-logo"><i class="fa-solid fa-shield-halved"></i></div>
      <div>
        <div class="cabec-nome">Conselho Regional de Medicina Veterinária</div>
        <div class="cabec-sigla">CRMV-TO</div>
        <div class="cabec-sub">Tocantins</div>
      </div>
      <div class="cabec-titulo">
        CERTIFICADO
        <small>de Participação e Conclusão</small>
      </div>
    </div>

    <!-- Corpo -->
    <div class="corpo">
      <p>O <strong>Conselho Regional de Medicina Veterinária do Tocantins — CRMV-TO</strong> certifica que</p>

      <div class="destaque-nome">
        <strong><?= htmlspecialchars($cert['nome_completo']) ?></strong>
        <?php if ($cert['crmv_numero']): ?>
        <span>CRMV <?= htmlspecialchars($cert['crmv_numero']) ?>-<?= htmlspecialchars($cert['crmv_uf']) ?></span>
        <?php endif; ?>
      </div>

      <p>participou e concluiu com êxito
        <?= match(strtoupper($cert['tipo'])) {
            'PALESTRA'  => 'a Palestra',
            'WORKSHOP'  => 'o Workshop',
            'CONGRESSO' => 'o Congresso',
            default     => 'o Curso'
        } ?>
      </p>

      <div class="curso-titulo">"<?= htmlspecialchars($cert['curso_titulo']) ?>"</div>

      <p class="detalhes">
        com carga horária de <strong><?= $cert['carga_horaria'] ?> horas</strong>
        <?php if ($cert['data_inicio']): ?>
        , realizado em
        <strong><?= fmtD2p($cert['data_inicio']) ?>
          <?= $cert['data_fim'] && $cert['data_fim'] !== $cert['data_inicio']
               ? ' a ' . fmtD2p($cert['data_fim']) : '' ?>
        </strong>
        <?php endif; ?>
        <?php if ($cert['local_cidade']): ?>
        , em <?= htmlspecialchars($cert['local_cidade']) ?>/<?= htmlspecialchars($cert['local_uf']) ?>
        <?php endif; ?>.
      </p>
    </div>

    <!-- Assinaturas -->
    <div class="assinaturas">
      <div class="assin-bloco">
        <div class="assin-linha"></div>
        <div class="assin-nome">Presidente do CRMV-TO</div>
        <div class="assin-cargo">Presidente</div>
      </div>

      <div class="assin-bloco">
        <div class="assin-linha">Palmas/TO, <?= dataPorExtensoPrint($cert['emitido_em']) ?></div>
        <div class="assin-nome">Local e Data</div>
        <div class="assin-cargo">de emissão</div>
      </div>

      <?php if ($cert['instrutor_nome']): ?>
      <div class="assin-bloco">
        <div class="assin-linha">
          <?php if ($cert['assinatura_img']): ?>
          <img src="/crmv/uploads/assinaturas/<?= htmlspecialchars($cert['assinatura_img']) ?>">
          <?php endif; ?>
        </div>
        <div class="assin-nome"><?= htmlspecialchars($cert['instrutor_nome']) ?></div>
        <div class="assin-cargo">
          <?= $cert['instrutor_titulo'] ? htmlspecialchars($cert['instrutor_titulo']) : 'Instrutor(a)' ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Rodapé QR -->
    <div class="rodape">
      <div class="rodape-txt">
        Certificado emitido digitalmente pelo sistema CRMV/TO.<br>
        Autenticidade: <strong><?= htmlspecialchars(
            parse_url($urlValidacao, PHP_URL_HOST) . parse_url($urlValidacao, PHP_URL_PATH)
        ) ?></strong>
      </div>
      <div class="rodape-qr">
        <img src="<?= $qrUrl ?>" width="70" height="70" alt="QR">
        <div class="rodape-codigo"><?= htmlspecialchars($cert['codigo']) ?></div>
      </div>
    </div>
  </div>
</div>

<?php if ($temVerso): ?>
<!-- ══ VERSO ═══════════════════════════════════════════════ -->
<div class="pagina page-break">
  <div class="borda-ext"></div>
  <div class="borda-int"></div>
  <div class="faixa-dir"></div>
  <div class="marca-dagua"><i class="fa-solid fa-shield-halved"></i></div>

  <div class="conteudo">
    <div class="verso-cabec">
      <div class="verso-icone"><i class="fa-solid fa-list-check"></i></div>
      <div>
        <div class="verso-titulo">Conteúdo Programático</div>
        <div class="verso-sub">
          <?= htmlspecialchars($cert['curso_titulo']) ?> · <?= $cert['carga_horaria'] ?>h
        </div>
      </div>
    </div>

    <div class="verso-conteudo">
      <?= $cert['cert_conteudo_programatico'] ?>
    </div>

    <div class="verso-rodape">
      <span>CRMV-TO — <?= htmlspecialchars($cert['codigo']) ?></span>
      <span>Verso do Certificado</span>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// Abre diálogo de impressão automaticamente
window.addEventListener('load', function(){ window.print(); });
</script>
</body>
</html>
