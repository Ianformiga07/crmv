<?php
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

$codigo = trim($_GET['codigo'] ?? '');
if (!$codigo) { http_response_code(404); exit('Código inválido.'); }

$cert = dbQueryOne(
    "SELECT cert.*, m.nota_final,
            u.nome_completo, u.crmv_numero, u.crmv_uf,
            c.titulo AS curso_titulo, c.tipo, c.carga_horaria, c.data_inicio, c.data_fim,
            c.local_cidade, c.local_uf,
            i.nome AS instrutor_nome, i.titulo AS instrutor_titulo, i.assinatura_img
     FROM tbl_certificados cert
     INNER JOIN tbl_matriculas m ON cert.matricula_id = m.matricula_id
     INNER JOIN tbl_usuarios   u ON m.usuario_id = u.usuario_id
     INNER JOIN tbl_cursos     c ON m.curso_id = c.curso_id
     LEFT  JOIN tbl_instrutores i ON c.instrutor_id = i.instrutor_id
     WHERE cert.codigo = ? AND cert.valido = 1",
    [$codigo]
);
if (!$cert) { http_response_code(404); exit('Certificado não encontrado.'); }

$urlValidacao = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/crmv/validar.php?codigo='.urlencode($codigo);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data='.urlencode($urlValidacao).'&color=0d2137';

function numExtenso($n) {
    $map=[0.5=>'meia',1=>'uma',1.5=>'uma e meia',2=>'duas',3=>'três',4=>'quatro',5=>'cinco',6=>'seis',7=>'sete',8=>'oito',9=>'nove',10=>'dez',12=>'doze',16=>'dezesseis',20=>'vinte',24=>'vinte e quatro',30=>'trinta',40=>'quarenta'];
    return $map[(float)$n] ?? (string)(int)$n;
}
function dataPorExtenso($dt) {
    $ts=strtotime($dt);
    $m=['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    return date('d',$ts).' de '.$m[(int)date('n',$ts)].' de '.date('Y',$ts);
}
function fmtD($d) { if(!$d) return ''; $p=explode('-',$d); return count($p)===3?$p[2].'/'.$p[1].'/'.$p[0]:$d; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Certificado <?= htmlspecialchars($codigo) ?> — CRMV/TO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Cinzel:wght@400;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  html, body { background:#d4c9a8; font-family:'Lato',sans-serif; }

  .page {
    width: 297mm;
    min-height: 210mm;
    margin: 0 auto;
    background: #fff;
    position: relative;
    overflow: hidden;
  }

  /* Bordas decorativas */
  .borda-ext { position:absolute;inset:0;border:6px solid #0d2137;pointer-events:none;z-index:10; }
  .borda-int { position:absolute;inset:10px;border:2px solid #c9a227;pointer-events:none;z-index:10; }
  .borda-int2{ position:absolute;inset:14px;border:1px solid rgba(201,162,39,.35);pointer-events:none;z-index:10; }

  /* Cantos ornamentais */
  .canto {
    position:absolute;width:48px;height:48px;z-index:11;
    background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Cpath d='M0,0 L40,0 L40,6 L6,6 L6,40 L0,40 Z' fill='%23c9a227'/%3E%3C/svg%3E") no-repeat center/contain;
  }
  .canto-tl { top:8px;left:8px; }
  .canto-tr { top:8px;right:8px;transform:scaleX(-1); }
  .canto-bl { bottom:8px;left:8px;transform:scaleY(-1); }
  .canto-br { bottom:8px;right:8px;transform:scale(-1,-1); }

  /* Faixa lateral esquerda */
  .faixa-esq {
    position:absolute;left:0;top:0;bottom:0;width:22px;
    background: repeating-linear-gradient(
      180deg,
      #0d2137 0px, #0d2137 18px,
      #c9a227 18px, #c9a227 24px
    );
    z-index:5;
  }
  .faixa-dir {
    position:absolute;right:0;top:0;bottom:0;width:22px;
    background: repeating-linear-gradient(
      180deg,
      #0d2137 0px, #0d2137 18px,
      #c9a227 18px, #c9a227 24px
    );
    z-index:5;
  }

  /* Marca d'água */
  .watermark {
    position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
    opacity:.04;z-index:1;pointer-events:none;
  }
  .watermark i { font-size:320px;color:#0d2137; }

  /* Conteúdo */
  .conteudo { position:relative;z-index:2;padding:28px 52px 24px; }

  /* Cabeçalho */
  .cabecalho {
    display:flex;align-items:center;justify-content:center;gap:22px;
    padding-bottom:16px;
    border-bottom:2px solid #c9a227;
    margin-bottom:16px;
  }
  .escudo {
    width:68px;height:68px;border-radius:50%;
    background:#0d2137;display:flex;align-items:center;justify-content:center;
    box-shadow:0 2px 10px rgba(0,0,0,.25);flex-shrink:0;
  }
  .escudo i { font-size:2rem;color:#c9a227; }
  .org-nome { text-align:left; }
  .org-linha1 { font-family:'Cinzel',serif;font-size:.82rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#0d2137; }
  .org-linha2 { font-family:'Cinzel',serif;font-size:2rem;font-weight:700;letter-spacing:.14em;color:#0d2137;line-height:1; }
  .org-linha3 { font-size:.65rem;color:#888;letter-spacing:.07em;text-transform:uppercase;margin-top:2px; }
  .titulo-cert { font-family:'Cinzel',serif;font-size:1.55rem;font-weight:700;letter-spacing:.28em;text-transform:uppercase;color:#0d2137;text-align:center; }
  .subtitulo-cert { font-family:'Lato',sans-serif;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#888;text-align:center;margin-top:3px; }

  /* Corpo */
  .corpo { text-align:center;font-family:'IM Fell English',serif;font-size:.96rem;line-height:1.85;color:#1a1a1a;margin:14px 0; }
  .nome-vet { font-family:'Cinzel',serif;font-size:1.55rem;font-weight:700;color:#0d2137;letter-spacing:.04em;line-height:1.2; }
  .crmv-vet { font-family:'Lato',sans-serif;font-size:.75rem;letter-spacing:.1em;color:#666;margin-top:3px;text-transform:uppercase; }
  .nome-bloco {
    margin:14px 0;padding:14px 24px;
    border-top:1px solid #e8d89a;border-bottom:1px solid #e8d89a;
    background:linear-gradient(to right,transparent,#fefce8 30%,#fefce8 70%,transparent);
  }
  .nome-curso { font-family:'Cinzel',serif;font-size:1.1rem;font-weight:700;color:#0d2137;line-height:1.3; }

  /* Assinaturas */
  .assinaturas { display:flex;gap:0;margin-top:20px;border-top:1px solid #e8d89a;padding-top:16px; }
  .assinatura { flex:1;text-align:center;padding:0 12px; }
  .assinatura:not(:last-child) { border-right:1px dashed #ddd; }
  .linha-ass { height:40px;border-bottom:1px solid #555;margin:0 16px 5px;display:flex;align-items:flex-end;justify-content:center;padding-bottom:3px; }
  .ass-nome { font-family:'Lato',sans-serif;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#0d2137; }
  .ass-cargo { font-size:.62rem;color:#888;margin-top:1px; }

  /* Rodapé QR */
  .rodape-qr { margin-top:14px;padding-top:12px;border-top:1px solid #e8d89a;display:flex;align-items:center;justify-content:space-between;gap:16px; }
  .rodape-texto { font-size:.62rem;color:#999;line-height:1.6; }
  .rodape-qr-bloco { text-align:center;flex-shrink:0; }
  .rodape-qr-bloco img { border:2px solid #0d2137;border-radius:3px;padding:2px; }
  .codigo-cert { font-family:monospace;font-size:.65rem;color:#0d2137;font-weight:700;margin-top:3px;letter-spacing:.04em; }

  @media print {
    html,body { background:#fff; }
    .page { width:100%;margin:0;box-shadow:none; }
    .no-print { display:none!important; }
    @page { size:A4 landscape;margin:0; }
  }
</style>
</head>
<body>

<!-- Botão imprimir (some ao imprimir) -->
<div class="no-print" style="background:#0d2137;padding:10px 24px;display:flex;align-items:center;justify-content:space-between">
  <span style="color:#c9a227;font-family:'Lato',sans-serif;font-size:.875rem;font-weight:700">
    <i class="fa-solid fa-certificate"></i> CRMV/TO — Certificado <?= htmlspecialchars($codigo) ?>
  </span>
  <div style="display:flex;gap:8px">
    <a href="/crmv/admin/certificados/ver.php?codigo=<?= urlencode($codigo) ?>"
       style="padding:7px 16px;background:transparent;border:1.5px solid #c9a227;color:#c9a227;border-radius:6px;font-size:.82rem;text-decoration:none;font-family:'Lato',sans-serif">
      <i class="fa-solid fa-arrow-left"></i> Voltar
    </a>
    <button onclick="window.print()"
       style="padding:7px 18px;background:#c9a227;border:none;color:#0d2137;border-radius:6px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:'Lato',sans-serif">
      <i class="fa-solid fa-print"></i> Imprimir / Salvar PDF
    </button>
  </div>
</div>

<!-- CERTIFICADO -->
<div style="padding:20px;display:flex;justify-content:center" class="no-print-pad">
<div class="page">

  <!-- Elementos decorativos -->
  <div class="borda-ext"></div>
  <div class="borda-int"></div>
  <div class="borda-int2"></div>
  <div class="canto canto-tl"></div>
  <div class="canto canto-tr"></div>
  <div class="canto canto-bl"></div>
  <div class="canto canto-br"></div>
  <div class="faixa-esq"></div>
  <div class="faixa-dir"></div>
  <div class="watermark"><i class="fa-solid fa-shield-halved"></i></div>

  <div class="conteudo">

    <!-- CABEÇALHO -->
    <div class="cabecalho">
      <div style="flex:1;display:flex;align-items:center;justify-content:flex-end;gap:16px">
        <div class="org-nome">
          <div class="org-linha1">Conselho Regional de Medicina Veterinária</div>
          <div class="org-linha2">CRMV-TO</div>
          <div class="org-linha3">Tocantins &mdash; CFMV nº <?= getConfig('cfmv_numero') ?: '0000' ?></div>
        </div>
        <div class="escudo"><i class="fa-solid fa-shield-halved"></i></div>
      </div>
      <div style="width:1px;height:64px;background:#c9a227;margin:0 10px;flex-shrink:0"></div>
      <div style="flex:1">
        <div class="titulo-cert">Certificado</div>
        <div class="subtitulo-cert">de Participação e Conclusão</div>
      </div>
    </div>

    <!-- CORPO -->
    <div class="corpo">
      <div>O <strong>Conselho Regional de Medicina Veterinária do Tocantins — CRMV-TO</strong> certifica que</div>

      <div class="nome-bloco">
        <div class="nome-vet"><?= htmlspecialchars($cert['nome_completo']) ?></div>
        <?php if ($cert['crmv_numero']): ?>
        <div class="crmv-vet">CRMV <?= htmlspecialchars($cert['crmv_numero']) ?>-<?= htmlspecialchars($cert['crmv_uf']) ?></div>
        <?php endif; ?>
      </div>

      <div>
        participou e concluiu com êxito <?= match(strtoupper($cert['tipo'])) {'PALESTRA'=>'a Palestra', 'WORKSHOP'=>'o Workshop', 'CONGRESSO'=>'o Congresso', default=>'o Curso'} ?>
      </div>

      <div class="nome-curso" style="margin:8px 0">&ldquo;<?= htmlspecialchars($cert['curso_titulo']) ?>&rdquo;</div>

      <div style="font-size:.88rem;color:#444;margin-top:4px">
        com carga horária de <strong><?= $cert['carga_horaria'] ?> (<?= numExtenso($cert['carga_horaria']) ?>) horas</strong>
        <?php if ($cert['data_inicio']): ?>
        , realizado em <strong><?= fmtD($cert['data_inicio']) ?><?= $cert['data_fim'] && $cert['data_fim']!==$cert['data_inicio'] ? ' a '.fmtD($cert['data_fim']) : '' ?></strong>
        <?php endif; ?>
        <?php if ($cert['local_cidade']): ?>
        , <?= htmlspecialchars($cert['local_cidade']) ?>/<?= htmlspecialchars($cert['local_uf']) ?>
        <?php endif; ?>.
      </div>
    </div>

    <!-- ASSINATURAS -->
    <div class="assinaturas">
      <div class="assinatura">
        <div class="linha-ass"></div>
        <div class="ass-nome"><?= htmlspecialchars(getConfig('presidente_nome') ?: 'Presidente CRMV-TO') ?></div>
        <div class="ass-cargo">Presidente do CRMV-TO</div>
      </div>

      <div class="assinatura">
        <div class="linha-ass" style="font-size:.78rem;color:#555;font-family:'IM Fell English',serif;align-items:flex-end;padding-bottom:5px">
          Palmas/TO, <?= dataPorExtenso($cert['emitido_em']) ?>
        </div>
        <div class="ass-nome">Local e Data</div>
        <div class="ass-cargo">de emissão do certificado</div>
      </div>

      <?php if ($cert['instrutor_nome']): ?>
      <div class="assinatura">
        <div class="linha-ass">
          <?php if ($cert['assinatura_img']): ?>
          <img src="/crmv/uploads/assinaturas/<?= htmlspecialchars($cert['assinatura_img']) ?>"
               style="max-height:38px;max-width:130px;object-fit:contain">
          <?php endif; ?>
        </div>
        <div class="ass-nome"><?= htmlspecialchars($cert['instrutor_nome']) ?></div>
        <div class="ass-cargo"><?= $cert['instrutor_titulo'] ? htmlspecialchars($cert['instrutor_titulo']) : 'Instrutor(a)' ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- QR / CÓDIGO -->
    <div class="rodape-qr">
      <div class="rodape-texto">
        Certificado emitido digitalmente pelo sistema CRMV/TO.<br>
        Autenticidade pode ser verificada em: <strong style="color:#0d2137"><?= htmlspecialchars(parse_url($urlValidacao, PHP_URL_HOST).parse_url($urlValidacao, PHP_URL_PATH)) ?></strong>
      </div>
      <div class="rodape-qr-bloco">
        <img src="<?= $qrUrl ?>" width="72" height="72" alt="QR">
        <div class="codigo-cert"><?= htmlspecialchars($codigo) ?></div>
      </div>
    </div>

  </div><!-- /conteudo -->
</div><!-- /page -->
</div>

<script>
// Auto-print se vier com ?print=1
<?php if (!empty($_GET['print'])): ?>window.onload = function(){ window.print(); };<?php endif; ?>
</script>
</body>
</html>
