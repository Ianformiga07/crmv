<?php
require_once __DIR__ . '/includes/conexao.php';

// Redireciona se já logado
if (!empty($_SESSION['usr_id'])) {
    header('Location: /crmv/admin/dashboard.php');
    exit;
}

$sErro  = '';
$sEmail = '';
$sRet   = $_GET['ret'] ?? '/crmv/admin/dashboard.php';

// ── POST: processar login ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sEmail = strtolower(trim($_POST['email'] ?? ''));
    $sSenha = trim($_POST['senha'] ?? '');

    if ($sEmail === '' || $sSenha === '') {
        $sErro = 'Preencha o e-mail e a senha.';
    } else {
        $usuario = dbQueryOne(
            "SELECT usuario_id, nome_completo, perfil_id, senha_hash,
                    ativo, bloqueado_ate, tentativas_login
             FROM tbl_usuarios
             WHERE email = ?",
            [$sEmail]
        );

        if (!$usuario) {
            $sErro = 'E-mail ou senha incorretos.';
        } else {
            // Verifica bloqueio temporário
            if ($usuario['bloqueado_ate'] && new DateTime($usuario['bloqueado_ate']) > new DateTime()) {
                $sErro = 'Conta bloqueada temporariamente. Aguarde alguns minutos e tente novamente.';
            } elseif (!$usuario['ativo']) {
                $sErro = 'Sua conta está inativa. Entre em contato com o administrador.';
            } else {
                if (verificaSenha($sSenha, $usuario['senha_hash'])) {
                    // ── LOGIN OK ────────────────────────────────
                    session_regenerate_id(true);
                    $_SESSION['usr_id']     = $usuario['usuario_id'];
                    $_SESSION['usr_nome']   = $usuario['nome_completo'];
                    $_SESSION['usr_perfil'] = $usuario['perfil_id'];
                    $_SESSION['usr_email']  = $sEmail;

                    dbExecute(
                        "UPDATE tbl_usuarios SET ultimo_acesso = NOW(), tentativas_login = 0, bloqueado_ate = NULL WHERE usuario_id = ?",
                        [$usuario['usuario_id']]
                    );
                    registraLog($usuario['usuario_id'], 'LOGIN', 'Login realizado', 'tbl_usuarios', $usuario['usuario_id']);

                    // Após validação bem-sucedida, redirecionar pelo perfil:
                    $perfil = (int)($_SESSION['usr_perfil'] ?? 0);
                    if ($perfil === 1) {
                        // Admin → painel administrativo
                        $destino = $sRet ?: '/crmv/admin/dashboard.php';
                    } else {
                        // Veterinário/aluno → portal do aluno
                        $destino = '/crmv/aluno/dashboard.php';
                    }
                    header('Location: ' . $destino);
                    exit;

                } else {
                    // ── SENHA ERRADA ─────────────────────────────
                    $iTent = (int)$usuario['tentativas_login'] + 1;
                    if ($iTent >= 5) {
                        dbExecute(
                            "UPDATE tbl_usuarios SET tentativas_login = ?, bloqueado_ate = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE usuario_id = ?",
                            [$iTent, $usuario['usuario_id']]
                        );
                        $sErro = 'Senha incorreta. Conta bloqueada por 15 minutos após 5 tentativas.';
                    } else {
                        dbExecute(
                            "UPDATE tbl_usuarios SET tentativas_login = ? WHERE usuario_id = ?",
                            [$iTent, $usuario['usuario_id']]
                        );
                        $sErro = "E-mail ou senha incorretos. ($iTent/5 tentativas)";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar — CRMV/TO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --azul-esc:#0d2137;--azul-med:#15385c;--azul-clr:#1e5080;
    --verde:#1a6b3c;--verde2:#22883f;
    --ouro:#c9a227;--ouro2:#e6bb45;
    --branco:#fff;--c100:#f4f5f7;--c300:#d1d5db;
    --c500:#6b7280;--c700:#374151;
    --erro:#dc2626;--erro-bg:#fef2f2;
}
html,body{height:100%;font-family:'DM Sans',sans-serif;background:var(--azul-esc)}
.split{display:grid;grid-template-columns:1fr 480px;min-height:100vh}
.painel-visual{position:relative;display:flex;flex-direction:column;justify-content:flex-end;padding:60px;overflow:hidden}
.painel-visual::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 20% 110%,rgba(26,107,60,.45) 0%,transparent 60%),radial-gradient(ellipse 60% 80% at 90% -10%,rgba(201,162,39,.20) 0%,transparent 55%),linear-gradient(160deg,#0d2137 0%,#15385c 60%,#0d2137 100%)}
.painel-visual::after{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:52px 52px}
.anel{position:absolute;border-radius:50%;border:1px solid rgba(201,162,39,.15)}
.anel-1{width:600px;height:600px;top:-200px;right:-200px}
.anel-2{width:380px;height:380px;top:-80px;right:-80px;border-color:rgba(201,162,39,.08)}
.anel-3{width:260px;height:260px;bottom:30px;left:-90px;border-color:rgba(26,107,60,.22)}
.v-content{position:relative;z-index:2;animation:fadeUp .8s ease both}
.v-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(201,162,39,.12);border:1px solid rgba(201,162,39,.30);color:var(--ouro2);font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.12em;padding:6px 14px;border-radius:20px;margin-bottom:28px}
.v-titulo{font-family:'Playfair Display',serif;font-size:2.9rem;font-weight:700;color:var(--branco);line-height:1.18;margin-bottom:18px;max-width:520px}
.v-titulo em{color:var(--ouro2);font-style:normal}
.v-divider{width:52px;height:3px;background:linear-gradient(90deg,var(--ouro),transparent);border-radius:2px;margin-bottom:24px}
.v-desc{font-size:.925rem;color:rgba(255,255,255,.5);line-height:1.7;max-width:440px;margin-bottom:40px}
.v-stats{display:flex;gap:36px}
.s-item{display:flex;flex-direction:column}
.s-num{font-family:'Playfair Display',serif;font-size:1.85rem;font-weight:700;color:var(--branco);line-height:1}
.s-lbl{font-size:.7rem;color:rgba(255,255,255,.38);text-transform:uppercase;letter-spacing:.08em;margin-top:5px}
.painel-form{background:var(--branco);display:flex;flex-direction:column;justify-content:center;padding:60px 52px;position:relative}
.painel-form::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--verde),var(--ouro))}
.form-brand{display:flex;align-items:center;gap:14px;margin-bottom:44px;animation:fadeDown .6s ease both}
.brand-icone{width:52px;height:52px;border-radius:12px;background:linear-gradient(135deg,var(--verde),var(--azul-med));display:flex;align-items:center;justify-content:center;color:white;font-size:1.3rem;box-shadow:0 4px 14px rgba(26,107,60,.3);flex-shrink:0}
.brand-nome{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--azul-esc);line-height:1.2}
.brand-sub{font-size:.72rem;color:var(--c500);text-transform:uppercase;letter-spacing:.1em;margin-top:2px}
.form-titulo{font-family:'Playfair Display',serif;font-size:1.75rem;font-weight:700;color:var(--azul-esc);margin-bottom:6px;animation:fadeDown .7s ease both}
.form-sub{font-size:.875rem;color:var(--c500);margin-bottom:36px;animation:fadeDown .75s ease both}
.alerta-erro{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--erro-bg);border:1px solid #fca5a5;border-left:3px solid var(--erro);border-radius:8px;margin-bottom:24px;font-size:.875rem;color:#7f1d1d;animation:shake .4s ease}
.alerta-erro i{color:var(--erro);margin-top:1px;flex-shrink:0}
.campo-grupo{margin-bottom:20px}
.campo-label{display:block;font-size:.78rem;font-weight:600;color:var(--c700);margin-bottom:7px;text-transform:uppercase;letter-spacing:.06em}
.campo-wrap{position:relative}
.campo-icone{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--c300);font-size:.875rem;transition:color .2s;pointer-events:none}
.campo-input{width:100%;padding:12px 14px 12px 42px;border:1.5px solid var(--c300);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.925rem;color:var(--c700);background:var(--c100);transition:all .2s;outline:none}
.campo-input:focus{background:var(--branco);border-color:var(--azul-clr);box-shadow:0 0 0 3px rgba(30,80,128,.10)}
.campo-wrap:focus-within .campo-icone{color:var(--azul-clr)}
.campo-input.erro{border-color:var(--erro);background:var(--erro-bg)}
.btn-olho{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--c300);cursor:pointer;font-size:.875rem;padding:4px 6px;border-radius:6px;transition:color .2s}
.btn-olho:hover{color:var(--c700)}
.linha-esqueceu{display:flex;justify-content:flex-end;margin-top:-8px;margin-bottom:28px}
.link-esq{font-size:.8rem;color:var(--azul-clr);text-decoration:none;font-weight:500}
.link-esq:hover{text-decoration:underline}
.btn-entrar{width:100%;padding:13px;background:linear-gradient(135deg,var(--verde),var(--verde2));color:white;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;transition:all .25s;box-shadow:0 4px 14px rgba(26,107,60,.3)}
.btn-entrar:hover{transform:translateY(-1px);box-shadow:0 7px 20px rgba(26,107,60,.4)}
.btn-entrar:disabled{opacity:.65;cursor:not-allowed;transform:none}
.spinner-btn{display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,.35);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite}
.rodape-form{margin-top:36px;padding-top:24px;border-top:1px solid var(--c100);text-align:center;font-size:.775rem;color:var(--c500);line-height:1.65}
@keyframes fadeUp  {from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeDown{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:translateY(0)}}
@keyframes shake   {0%,100%{transform:translateX(0)}20%{transform:translateX(-6px)}40%{transform:translateX(6px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}
@keyframes spin    {to{transform:rotate(360deg)}}
@media(max-width:900px){.split{grid-template-columns:1fr}.painel-visual{display:none}.painel-form{padding:48px 28px}}
</style>
</head>
<body>
<div class="split">
    <div class="painel-visual">
        <div class="anel anel-1"></div><div class="anel anel-2"></div><div class="anel anel-3"></div>
        <div class="v-content">
            <span class="v-badge"><i class="fa-solid fa-shield-halved"></i> Plataforma Oficial</span>
            <h2 class="v-titulo">Educação Continuada<br>para <em>Médicos Veterinários</em></h2>
            <div class="v-divider"></div>
            <p class="v-desc">Gerencie cursos, palestras e eventos do CRMV/TO. Emita certificados com QR Code, controle avaliações e acompanhe o progresso dos participantes.</p>
            <div class="v-stats">
                <div class="s-item"><span class="s-num">EAD</span><span class="s-lbl">& Presencial</span></div>
                <div class="s-item"><span class="s-num">QR</span><span class="s-lbl">Certificados</span></div>
                <div class="s-item"><span class="s-num">100%</span><span class="s-lbl">Online</span></div>
            </div>
        </div>
    </div>

    <div class="painel-form">
        <div class="form-brand">
            <div class="brand-icone"><i class="fa-solid fa-paw"></i></div>
            <div>
                <div class="brand-nome">CRMV/TO</div>
                <div class="brand-sub">Conselho Regional de Medicina Veterinária</div>
            </div>
        </div>
        <h1 class="form-titulo">Bem-vindo de volta</h1>
        <p class="form-sub">Acesse com seu e-mail e senha de administrador.</p>

        <?php if ($sErro): ?>
        <div class="alerta-erro" id="alertaErro">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($sErro) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="/crmv/login.php?ret=<?= urlencode($sRet) ?>" id="fLogin">
            <div class="campo-grupo">
                <label class="campo-label" for="email">E-mail</label>
                <div class="campo-wrap">
                    <input type="email" id="email" name="email"
                        class="campo-input <?= $sErro ? 'erro' : '' ?>"
                        value="<?= htmlspecialchars($sEmail) ?>"
                        placeholder="seu@email.com.br"
                        autocomplete="email" autofocus required>
                    <i class="fa-solid fa-envelope campo-icone"></i>
                </div>
            </div>
            <div class="campo-grupo">
                <label class="campo-label" for="senha">Senha</label>
                <div class="campo-wrap">
                    <input type="password" id="senha" name="senha"
                        class="campo-input <?= $sErro ? 'erro' : '' ?>"
                        placeholder="••••••••"
                        autocomplete="current-password" required>
                    <i class="fa-solid fa-lock campo-icone"></i>
                    <button type="button" class="btn-olho" onclick="alternarSenha()" tabindex="-1">
                        <i class="fa-solid fa-eye" id="iOlho"></i>
                    </button>
                </div>
            </div>
            <div class="linha-esqueceu">
                <a href="#" class="link-esq">Esqueceu a senha?</a>
            </div>
            <button type="submit" class="btn-entrar" id="btnEntrar">
                <span class="spinner-btn" id="spinnerBtn"></span>
                <i class="fa-solid fa-arrow-right-to-bracket" id="iBtn"></i>
                <span id="txtBtn">Entrar no sistema</span>
            </button>
        </form>

        <div class="rodape-form">
            CRMV/TO &copy; <?= date('Y') ?> &mdash; Plataforma de Educação Continuada<br>
            Área restrita. Acesso somente para administradores autorizados.
        </div>
    </div>
</div>
<script>
function alternarSenha() {
    var i = document.getElementById('senha'), ic = document.getElementById('iOlho');
    if (i.type==='password'){ i.type='text'; ic.className='fa-solid fa-eye-slash'; }
    else { i.type='password'; ic.className='fa-solid fa-eye'; }
}
document.getElementById('fLogin').addEventListener('submit', function(e) {
    if (!document.getElementById('email').value.trim() || !document.getElementById('senha').value.trim()){ e.preventDefault(); return; }
    var btn = document.getElementById('btnEntrar');
    btn.disabled = true;
    document.getElementById('spinnerBtn').style.display = 'block';
    document.getElementById('iBtn').style.display = 'none';
    document.getElementById('txtBtn').textContent = 'Verificando...';
});
var al = document.getElementById('alertaErro');
if (al) setTimeout(function(){ al.style.transition='opacity .5s'; al.style.opacity='0'; setTimeout(function(){ al.remove(); },500); }, 8000);
</script>
</body>
</html>
