<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php
// Carrega conexão sem exigir login
require_once __DIR__ . '/includes/conexao.php';
$codigo = strtoupper(trim($_GET['codigo'] ?? ''));
$cert   = null;

if ($codigo) {
    $cert = dbQueryOne(
        "SELECT cert.codigo, cert.emitido_em, cert.valido,
                u.nome_completo, u.crmv_numero, u.crmv_uf,
                c.titulo AS curso_titulo, c.tipo, c.carga_horaria,
                c.data_inicio, c.data_fim, c.local_cidade, c.local_uf,
                i.nome AS instrutor_nome
         FROM tbl_certificados cert
         INNER JOIN tbl_matriculas m ON cert.matricula_id = m.matricula_id
         INNER JOIN tbl_usuarios   u ON m.usuario_id = u.usuario_id
         INNER JOIN tbl_cursos     c ON m.curso_id = c.curso_id
         LEFT  JOIN tbl_instrutores i ON c.instrutor_id = i.instrutor_id
         WHERE cert.codigo = ?",
        [$codigo]
    );
}

function fmtD($d) { if(!$d) return ''; $p=explode('-',$d); return count($p)===3?$p[2].'/'.$p[1].'/'.$p[0]:$d; }
function dataPorExtenso($dt) {
    $ts=strtotime($dt);
    $m=['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    return date('d',$ts).' de '.$m[(int)date('n',$ts)].' de '.date('Y',$ts);
}

$titulo = $cert ? 'Certificado Válido — ' . $cert['nome_completo'] : 'Validação de Certificado — CRMV/TO';
$desc   = $cert ? 'Certificado emitido pelo CRMV-TO para ' . $cert['nome_completo'] . ' no curso ' . $cert['curso_titulo'] : 'Verifique a autenticidade de um certificado emitido pelo CRMV-TO';
?>
<title><?= htmlspecialchars($titulo) ?></title>
<meta name="description" content="<?= htmlspecialchars($desc) ?>">
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  :root {
    --azul: #0d2137; --verde: #1a6b3c; --ouro: #c9a227;
    --fundo: #f2f0eb; --branco: #ffffff;
  }
  body { font-family:'DM Sans',sans-serif; background:var(--fundo); min-height:100vh; }

  /* Header */
  .header {
    background:var(--azul);
    padding:16px 24px;
    display:flex;align-items:center;justify-content:space-between;
    box-shadow:0 2px 8px rgba(0,0,0,.25);
  }
  .header-logo { display:flex;align-items:center;gap:14px; }
  .header-escudo {
    width:44px;height:44px;border-radius:50%;background:var(--azul);
    border:2px solid var(--ouro);display:flex;align-items:center;justify-content:center;
  }
  .header-escudo i { color:var(--ouro);font-size:1.2rem; }
  .header-nome { color:#fff; }
  .header-nome strong { display:block;font-family:'Playfair Display',serif;font-size:1.1rem;letter-spacing:.05em; }
  .header-nome span { font-size:.7rem;color:rgba(255,255,255,.55);letter-spacing:.06em;text-transform:uppercase; }

  /* Container */
  .container { max-width:680px;margin:0 auto;padding:32px 16px; }

  /* Busca */
  .busca-card {
    background:var(--branco);border-radius:12px;
    box-shadow:0 2px 12px rgba(0,0,0,.08);
    padding:28px;margin-bottom:24px;
  }
  .busca-titulo { font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--azul);margin-bottom:6px; }
  .busca-desc { font-size:.875rem;color:#6b7280;margin-bottom:20px; }
  .busca-form { display:flex;gap:8px;flex-wrap:wrap; }
  .busca-input {
    flex:1;min-width:180px;padding:11px 14px;
    border:2px solid #e5e7eb;border-radius:8px;
    font-size:.9rem;font-family:inherit;outline:none;
    text-transform:uppercase;letter-spacing:.06em;
    transition:border-color .2s;
  }
  .busca-input:focus { border-color:var(--azul); }
  .busca-btn {
    padding:11px 22px;background:var(--azul);color:#fff;
    border:none;border-radius:8px;font-family:inherit;
    font-size:.9rem;font-weight:600;cursor:pointer;
    display:flex;align-items:center;gap:7px;
    transition:background .2s;
  }
  .busca-btn:hover { background:#15385c; }

  /* Resultado: VÁLIDO */
  .cert-card {
    background:var(--branco);border-radius:12px;
    box-shadow:0 2px 16px rgba(0,0,0,.1);
    overflow:hidden;margin-bottom:24px;
  }
  .cert-banner {
    background:var(--azul);
    padding:24px 28px;
    display:flex;align-items:center;gap:16px;
  }
  .cert-icon { width:56px;height:56px;border-radius:50%;background:var(--verde);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
  .cert-icon i { color:#fff;font-size:1.5rem; }
  .cert-banner-txt strong { font-size:1.05rem;color:#fff;display:block;margin-bottom:2px; }
  .cert-banner-txt span { font-size:.78rem;color:rgba(255,255,255,.6); }
  .cert-code { margin-left:auto;font-family:monospace;font-size:.85rem;font-weight:700;background:rgba(201,162,39,.2);color:var(--ouro);padding:6px 12px;border-radius:6px;letter-spacing:.06em;flex-shrink:0; }

  .cert-body { padding:28px; }
  .cert-nome { font-family:'Playfair Display',serif;font-size:1.6rem;color:var(--azul);margin-bottom:4px;line-height:1.2; }
  .cert-crmv { font-size:.8rem;color:#6b7280;letter-spacing:.06em;text-transform:uppercase;margin-bottom:20px; }
  .cert-info-grid { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px; }
  .cert-info-item { background:#f9fafb;border-radius:8px;padding:12px 14px; }
  .cert-info-label { font-size:.65rem;text-transform:uppercase;letter-spacing:.07em;color:#9ca3af;margin-bottom:4px; }
  .cert-info-value { font-size:.9rem;font-weight:600;color:var(--azul);line-height:1.3; }
  .cert-curso-nome { font-size:.82rem;font-weight:400;color:#4b5563;margin-top:2px; }
  .cert-selo {
    display:flex;align-items:center;gap:10px;
    background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:8px;
    padding:12px 16px;margin-top:16px;
  }
  .cert-selo i { color:var(--verde);font-size:1.2rem;flex-shrink:0; }
  .cert-selo-txt strong { display:block;font-size:.875rem;color:#166534; }
  .cert-selo-txt span { font-size:.75rem;color:#16a34a; }

  .cert-emissao { margin-top:16px;font-size:.8rem;color:#9ca3af;text-align:center; }

  /* PDF Button */
  .btn-imprimir {
    display:flex;align-items:center;justify-content:center;gap:8px;
    padding:12px;background:var(--ouro);color:#fff;border:none;
    border-radius:8px;font-family:inherit;font-size:.875rem;font-weight:600;
    cursor:pointer;text-decoration:none;transition:background .2s;
    margin-top:12px;
  }
  .btn-imprimir:hover { background:#b8901e; }

  /* Resultado: INVÁLIDO */
  .invalido-card {
    background:var(--branco);border-radius:12px;
    box-shadow:0 2px 12px rgba(0,0,0,.08);
    padding:40px;text-align:center;margin-bottom:24px;
  }
  .invalido-icon { width:72px;height:72px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 16px; }
  .invalido-icon i { color:#dc2626;font-size:1.8rem; }
  .invalido-titulo { font-family:'Playfair Display',serif;font-size:1.3rem;color:#7f1d1d;margin-bottom:8px; }
  .invalido-desc { font-size:.875rem;color:#6b7280;max-width:380px;margin:0 auto; }

  /* Footer */
  .footer { text-align:center;font-size:.75rem;color:#9ca3af;margin-top:24px;padding-bottom:32px; }
  .footer a { color:var(--azul);text-decoration:none; }

  @media(max-width:520px) {
    .cert-info-grid { grid-template-columns:1fr; }
    .cert-banner { flex-wrap:wrap; }
    .cert-code { margin-left:0; }
  }
</style>
</head>
<body>

<div class="header">
  <div class="header-logo">
    <div class="header-escudo"><i class="fa-solid fa-shield-halved"></i></div>
    <div class="header-nome">
      <strong>CRMV-TO</strong>
      <span>Conselho Regional de Medicina Veterinária do Tocantins</span>
    </div>
  </div>
</div>

<div class="container">

  <!-- BUSCA -->
  <div class="busca-card">
    <h1 class="busca-titulo"><i class="fa-solid fa-certificate" style="color:var(--ouro);margin-right:8px"></i>Validação de Certificado</h1>
    <p class="busca-desc">Digite o código de validação para verificar a autenticidade de um certificado emitido pelo CRMV-TO.</p>
    <form method="GET" class="busca-form">
      <input type="text" name="codigo" class="busca-input"
        value="<?= htmlspecialchars($codigo) ?>"
        placeholder="Ex: CRMV-2025-XXXXXX"
        maxlength="30" required autocomplete="off" autocapitalize="characters">
      <button type="submit" class="busca-btn">
        <i class="fa-solid fa-magnifying-glass"></i> Verificar
      </button>
    </form>
  </div>

  <!-- RESULTADO -->
  <?php if ($codigo && $cert): ?>
    <?php if ($cert['valido']): ?>
    <!-- VÁLIDO -->
    <div class="cert-card">
      <div class="cert-banner">
        <div class="cert-icon"><i class="fa-solid fa-check"></i></div>
        <div class="cert-banner-txt">
          <strong>Certificado Válido e Autêntico</strong>
          <span>Documento verificado com sucesso pelo sistema CRMV/TO</span>
        </div>
        <div class="cert-code"><?= htmlspecialchars($cert['codigo']) ?></div>
      </div>
      <div class="cert-body">
        <div class="cert-nome"><?= htmlspecialchars($cert['nome_completo']) ?></div>
        <?php if ($cert['crmv_numero']): ?>
        <div class="cert-crmv">CRMV <?= htmlspecialchars($cert['crmv_numero']) ?>-<?= htmlspecialchars($cert['crmv_uf']) ?></div>
        <?php endif; ?>

        <div class="cert-info-grid">
          <div class="cert-info-item" style="grid-column:span 2">
            <div class="cert-info-label"><i class="fa-solid fa-graduation-cap"></i> Curso / Atividade</div>
            <div class="cert-info-value"><?= htmlspecialchars($cert['curso_titulo']) ?></div>
            <div class="cert-curso-nome"><?= htmlspecialchars($cert['tipo']) ?></div>
          </div>
          <div class="cert-info-item">
            <div class="cert-info-label"><i class="fa-solid fa-clock"></i> Carga Horária</div>
            <div class="cert-info-value"><?= $cert['carga_horaria'] ?> horas</div>
          </div>
          <div class="cert-info-item">
            <div class="cert-info-label"><i class="fa-solid fa-calendar"></i> Realização</div>
            <div class="cert-info-value">
              <?= $cert['data_inicio'] ? fmtD($cert['data_inicio']) : 'Não informado' ?>
              <?= $cert['data_fim'] && $cert['data_fim']!==$cert['data_inicio'] ? ' — '.fmtD($cert['data_fim']) : '' ?>
            </div>
          </div>
          <?php if ($cert['local_cidade']): ?>
          <div class="cert-info-item">
            <div class="cert-info-label"><i class="fa-solid fa-location-dot"></i> Local</div>
            <div class="cert-info-value"><?= htmlspecialchars($cert['local_cidade']) ?>/<?= htmlspecialchars($cert['local_uf']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($cert['instrutor_nome']): ?>
          <div class="cert-info-item">
            <div class="cert-info-label"><i class="fa-solid fa-chalkboard-teacher"></i> Instrutor(a)</div>
            <div class="cert-info-value"><?= htmlspecialchars($cert['instrutor_nome']) ?></div>
          </div>
          <?php endif; ?>
        </div>

        <div class="cert-selo">
          <i class="fa-solid fa-shield-halved"></i>
          <div class="cert-selo-txt">
            <strong>Emitido pelo CRMV-TO &mdash; Documento Oficial</strong>
            <span>Conselho Regional de Medicina Veterinária do Tocantins</span>
          </div>
        </div>

        <div class="cert-emissao">
          Emitido em <?= dataPorExtenso($cert['emitido_em']) ?>
        </div>

        <a href="/crmv/admin/certificados/imprimir.php?codigo=<?= urlencode($codigo) ?>" class="btn-imprimir" target="_blank">
          <i class="fa-solid fa-file-pdf"></i> Visualizar / Baixar PDF do Certificado
        </a>
      </div>
    </div>

    <?php else: ?>
    <!-- INVÁLIDO / REVOGADO -->
    <div class="invalido-card">
      <div class="invalido-icon"><i class="fa-solid fa-ban"></i></div>
      <h2 class="invalido-titulo">Certificado Inválido ou Revogado</h2>
      <p class="invalido-desc">Este certificado foi cancelado ou não é mais válido. Entre em contato com o CRMV-TO para mais informações.</p>
    </div>
    <?php endif; ?>

  <?php elseif ($codigo && !$cert): ?>
  <!-- NÃO ENCONTRADO -->
  <div class="invalido-card">
    <div class="invalido-icon"><i class="fa-solid fa-circle-question"></i></div>
    <h2 class="invalido-titulo">Código Não Encontrado</h2>
    <p class="invalido-desc">Nenhum certificado foi encontrado com o código <strong>"<?= htmlspecialchars($codigo) ?>"</strong>. Verifique o código e tente novamente.</p>
  </div>
  <?php endif; ?>

  <div class="footer">
    &copy; <?= date('Y') ?> CRMV-TO &mdash; Conselho Regional de Medicina Veterinária do Tocantins<br>
    <a href="/crmv/login.php">Área restrita</a>
  </div>

</div>
</body>
</html>
