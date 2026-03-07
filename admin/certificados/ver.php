<?php
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

$codigo = trim($_GET['codigo'] ?? '');
if (!$codigo) { header('Location: /crmv/admin/certificados/lista.php'); exit; }

$cert = dbQueryOne(
    "SELECT cert.*, m.matricula_id, m.nota_final,
            u.nome_completo, u.crmv_numero, u.crmv_uf, u.email, u.cpf,
            c.titulo AS curso_titulo, c.tipo, c.carga_horaria, c.data_inicio, c.data_fim,
            c.local_cidade, c.local_uf, c.local_nome,
            i.nome AS instrutor_nome, i.titulo AS instrutor_titulo, i.assinatura_img
     FROM tbl_certificados cert
     INNER JOIN tbl_matriculas m ON cert.matricula_id = m.matricula_id
     INNER JOIN tbl_usuarios   u ON m.usuario_id = u.usuario_id
     INNER JOIN tbl_cursos     c ON m.curso_id = c.curso_id
     LEFT  JOIN tbl_instrutores i ON c.instrutor_id = i.instrutor_id
     WHERE cert.codigo = ?",
    [$codigo]
);

if (!$cert) { flash('Certificado não encontrado.', 'erro'); header('Location: /crmv/admin/certificados/lista.php'); exit; }

$pageTitulo  = 'Certificado ' . $codigo;
$paginaAtiva = 'certificados';
require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="pg-header">
    <div class="pg-header-row">
        <div>
            <h1 class="pg-titulo">Certificado</h1>
            <p class="pg-subtitulo">Código: <code style="font-family:monospace;font-weight:700"><?= htmlspecialchars($codigo) ?></code></p>
        </div>
        <div class="pg-acoes">
            <a href="/crmv/validar.php?codigo=<?= urlencode($codigo) ?>" target="_blank" class="btn btn-ghost">
                <i class="fa-solid fa-qrcode"></i> Página pública
            </a>
            <a href="/crmv/admin/certificados/imprimir.php?codigo=<?= urlencode($codigo) ?>" target="_blank" class="btn btn-secundario">
                <i class="fa-solid fa-print"></i> Imprimir / PDF
            </a>
            <a href="/crmv/admin/certificados/lista.php" class="btn btn-ghost">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
</div>

<!-- PREVIEW DO CERTIFICADO -->
<div class="card" style="margin-bottom:20px;overflow:visible">
    <div class="card-header">
        <span class="card-titulo"><i class="fa-solid fa-eye"></i> Prévia do Certificado</span>
        <span class="badge b-verde"><i class="fa-solid fa-circle-check"></i> Válido</span>
    </div>
    <div class="card-body" style="padding:28px;background:#f5f5f0">
        <!-- CERTIFICADO FORMAL -->
        <div id="certificado-preview" style="
            max-width:860px;margin:0 auto;
            background:#fff;
            border:1px solid #c9a227;
            box-shadow:0 4px 24px rgba(0,0,0,.14);
            font-family:'Times New Roman',Times,serif;
            position:relative;overflow:hidden
        ">
            <!-- Borda dupla decorativa -->
            <div style="position:absolute;inset:8px;border:2px solid #c9a227;pointer-events:none;z-index:1"></div>
            <div style="position:absolute;inset:13px;border:1px solid rgba(201,162,39,.4);pointer-events:none;z-index:1"></div>

            <!-- Fundo marca d'água -->
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:0;opacity:.04">
                <i class="fa-solid fa-shield-halved" style="font-size:18rem;color:var(--azul-esc)"></i>
            </div>

            <div style="position:relative;z-index:2;padding:50px 60px">

                <!-- Cabeçalho -->
                <div style="text-align:center;border-bottom:2px solid #c9a227;padding-bottom:28px;margin-bottom:28px">
                    <div style="display:flex;align-items:center;justify-content:center;gap:20px;margin-bottom:16px">
                        <!-- Brasão/Escudo CRMV -->
                        <div style="width:72px;height:72px;background:var(--azul-esc);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,.2)">
                            <i class="fa-solid fa-shield-halved" style="font-size:2rem;color:#c9a227"></i>
                        </div>
                        <div style="text-align:left">
                            <div style="font-family:'Times New Roman',serif;font-size:1.05rem;font-weight:700;color:var(--azul-esc);letter-spacing:.1em;text-transform:uppercase">
                                Conselho Regional de Medicina Veterinária
                            </div>
                            <div style="font-size:1.7rem;font-weight:900;color:var(--azul-esc);letter-spacing:.12em;line-height:1.1">
                                CRMV-TO
                            </div>
                            <div style="font-size:.72rem;color:var(--c400);letter-spacing:.06em;text-transform:uppercase">
                                Tocantins — CFMV nº <?= getConfig('cfmv_numero') ?: '0000' ?>
                            </div>
                        </div>
                    </div>
                    <div style="font-size:2rem;font-weight:900;letter-spacing:.25em;text-transform:uppercase;color:var(--azul-esc);margin-top:8px">
                        CERTIFICADO
                    </div>
                    <div style="font-size:.8rem;color:var(--c500);letter-spacing:.1em;text-transform:uppercase;margin-top:4px">
                        de Participação e Conclusão
                    </div>
                </div>

                <!-- Corpo -->
                <div style="text-align:center;line-height:1.9;font-size:.975rem;color:#222">
                    <p style="margin:0 0 8px">
                        O <strong>Conselho Regional de Medicina Veterinária do Tocantins — CRMV-TO</strong>
                        certifica que
                    </p>

                    <div style="margin:20px 0;padding:16px;border-top:1px solid #e8d89a;border-bottom:1px solid #e8d89a;background:linear-gradient(to right,transparent,#fefce8,transparent)">
                        <div style="font-size:1.7rem;font-weight:900;color:var(--azul-esc);font-family:'Times New Roman',serif;letter-spacing:.03em">
                            <?= htmlspecialchars($cert['nome_completo']) ?>
                        </div>
                        <?php if ($cert['crmv_numero']): ?>
                        <div style="font-size:.85rem;color:var(--c500);margin-top:4px;letter-spacing:.06em">
                            CRMV <?= htmlspecialchars($cert['crmv_numero']) ?>-<?= htmlspecialchars($cert['crmv_uf']) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <p style="margin:16px 0">
                        participou e concluiu com êxito o
                        <strong><?= strtolower($cert['tipo']) === 'palestra' ? 'a Palestra' : (strtolower($cert['tipo']) === 'workshop' ? 'o Workshop' : 'o Curso') ?></strong>
                    </p>

                    <div style="margin:16px 0;font-size:1.2rem;font-weight:700;color:var(--azul-esc)">
                        "<?= htmlspecialchars($cert['curso_titulo']) ?>"
                    </div>

                    <p style="margin:12px 0;font-size:.9rem;color:var(--c600)">
                        com carga horária de
                        <strong style="font-size:1rem;color:var(--azul-esc)"><?= $cert['carga_horaria'] ?> (<?= numExtenso($cert['carga_horaria']) ?>) horas</strong>

                        <?php if ($cert['data_inicio']): ?>
                        , realizado em
                        <strong><?= fmtData($cert['data_inicio']) ?><?= $cert['data_fim'] && $cert['data_fim'] !== $cert['data_inicio'] ? ' a ' . fmtData($cert['data_fim']) : '' ?></strong>
                        <?php endif; ?>

                        <?php if ($cert['local_cidade']): ?>
                        , em <?= htmlspecialchars($cert['local_cidade']) ?>/<?= htmlspecialchars($cert['local_uf']) ?>
                        <?php endif; ?>
                        .
                    </p>
                </div>

                <!-- Rodapé: assinaturas e data -->
                <div style="margin-top:44px;display:grid;grid-template-columns:1fr 1fr<?= $cert['instrutor_nome'] ? ' 1fr' : '' ?>;gap:24px;text-align:center">

                    <!-- Assinatura presidente -->
                    <div>
                        <div style="height:50px;border-bottom:1.5px solid #999;margin-bottom:6px"></div>
                        <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--azul-esc)">
                            <?= htmlspecialchars(getConfig('presidente_nome') ?: 'Presidente do CRMV-TO') ?>
                        </div>
                        <div style="font-size:.72rem;color:var(--c400)">Presidente CRMV-TO</div>
                    </div>

                    <!-- Data e local -->
                    <div>
                        <div style="height:50px;display:flex;align-items:flex-end;justify-content:center;padding-bottom:6px;border-bottom:1.5px solid #999;margin-bottom:6px">
                            <div style="font-size:.85rem;color:var(--c500)">
                                Palmas/TO, <?= dataPorExtenso($cert['emitido_em']) ?>
                            </div>
                        </div>
                        <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--azul-esc)">Local e Data</div>
                        <div style="font-size:.72rem;color:var(--c400)">de emissão</div>
                    </div>

                    <!-- Assinatura instrutor (se existir) -->
                    <?php if ($cert['instrutor_nome']): ?>
                    <div>
                        <?php if ($cert['assinatura_img']): ?>
                        <div style="height:50px;display:flex;align-items:flex-end;justify-content:center;padding-bottom:4px;border-bottom:1.5px solid #999;margin-bottom:6px">
                            <img src="/crmv/uploads/assinaturas/<?= htmlspecialchars($cert['assinatura_img']) ?>"
                                 style="max-height:44px;max-width:140px;object-fit:contain">
                        </div>
                        <?php else: ?>
                        <div style="height:50px;border-bottom:1.5px solid #999;margin-bottom:6px"></div>
                        <?php endif; ?>
                        <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--azul-esc)">
                            <?= htmlspecialchars($cert['instrutor_nome']) ?>
                        </div>
                        <div style="font-size:.72rem;color:var(--c400)">
                            <?= $cert['instrutor_titulo'] ? htmlspecialchars($cert['instrutor_titulo']) : 'Instrutor(a)' ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>

                <!-- Código de validação -->
                <div style="margin-top:28px;padding-top:18px;border-top:1px solid #e8d89a;display:flex;align-items:center;justify-content:space-between;gap:20px">
                    <div style="font-size:.72rem;color:var(--c400);max-width:60%;line-height:1.5">
                        Certificado emitido pelo sistema CRMV/TO.<br>
                        Valide a autenticidade em: <strong style="color:var(--azul-esc)"><?= (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/crmv/validar.php' ?></strong>
                    </div>
                    <!-- QR Code via Google Charts API -->
                    <div style="text-align:center">
                        <?php
                        $urlValidacao = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/crmv/validar.php?codigo=' . urlencode($codigo);
                        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=' . urlencode($urlValidacao) . '&color=0d2137';
                        ?>
                        <img src="<?= $qrUrl ?>" width="80" height="80" alt="QR Code"
                             style="border:2px solid var(--azul-esc);border-radius:4px;padding:2px;background:#fff">
                        <div style="font-size:.65rem;color:var(--c400);margin-top:3px;letter-spacing:.04em">
                            <?= htmlspecialchars($codigo) ?>
                        </div>
                    </div>
                </div>

            </div><!-- /padding -->
        </div><!-- /certificado -->
    </div>
</div>

<!-- Informações técnicas -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div class="card">
        <div class="card-header"><span class="card-titulo"><i class="fa-solid fa-circle-info"></i> Dados do Certificado</span></div>
        <div class="card-body" style="font-size:.875rem;display:flex;flex-direction:column;gap:8px">
            <?php foreach ([
                ['Código',      $cert['codigo']],
                ['Emitido em',  fmtDataHora($cert['emitido_em'])],
                ['Veterinário', $cert['nome_completo']],
                ['CRMV',        $cert['crmv_numero'] ? $cert['crmv_numero'].'-'.$cert['crmv_uf'] : '—'],
                ['Curso',       $cert['curso_titulo']],
                ['Carga Horária', $cert['carga_horaria'].'h'],
            ] as [$rot, $val]): ?>
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--c100)">
                <span style="color:var(--c500)"><?= $rot ?></span>
                <strong style="color:var(--c700)"><?= htmlspecialchars($val) ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-titulo"><i class="fa-solid fa-link"></i> Link de Validação</span></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:14px;align-items:center;text-align:center">
            <img src="<?= $qrUrl ?>" width="120" height="120" alt="QR Code"
                 style="border:3px solid var(--azul-esc);border-radius:6px;padding:4px">
            <div>
                <p style="font-size:.82rem;color:var(--c500);margin:0 0 8px">Qualquer pessoa pode validar este certificado escaneando o QR Code ou acessando o link:</p>
                <a href="<?= htmlspecialchars($urlValidacao) ?>" target="_blank"
                   style="font-size:.8rem;color:var(--azul-clr);word-break:break-all"><?= htmlspecialchars($urlValidacao) ?></a>
            </div>
            <a href="<?= htmlspecialchars($urlValidacao) ?>" target="_blank" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir página pública
            </a>
        </div>
    </div>
</div>

<?php
// Funções auxiliares de formatação para o certificado
function numExtenso($n) {
    $map = [0.5=>'meia',1=>'uma',1.5=>'uma e meia',2=>'duas',3=>'três',4=>'quatro',5=>'cinco',6=>'seis',7=>'sete',8=>'oito',9=>'nove',10=>'dez',12=>'doze',16=>'dezesseis',20=>'vinte',24=>'vinte e quatro',30=>'trinta',40=>'quarenta'];
    return $map[(float)$n] ?? (string)(int)$n;
}
function dataPorExtenso($dt) {
    $ts = strtotime($dt);
    $meses = ['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    return date('d', $ts) . ' de ' . $meses[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
}
?>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
