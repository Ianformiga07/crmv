<?php
require_once __DIR__ . '/includes/conexao.php';
// Não exige login — só mostra mensagem
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Acesso Negado — CRMV/TO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body {
    font-family:'DM Sans',sans-serif;
    background:#f0f2f5;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
  }
  .card {
    background:#fff;
    border-radius:14px;
    box-shadow:0 4px 24px rgba(0,0,0,.1);
    padding:48px 40px;
    text-align:center;
    max-width:440px;
    width:100%;
  }
  .icone {
    width:72px; height:72px; border-radius:50%;
    background:#fef2f2;
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 20px;
  }
  .icone i { font-size:1.8rem; color:#dc2626; }
  h1 {
    font-family:'Playfair Display',serif;
    font-size:1.5rem;
    color:#0d2137;
    margin-bottom:10px;
  }
  p { font-size:.9rem; color:#6b7280; line-height:1.6; margin-bottom:28px; }
  .btns { display:flex; flex-direction:column; gap:10px; }
  .btn {
    display:flex; align-items:center; justify-content:center; gap:8px;
    padding:11px 20px; border-radius:8px;
    font-family:inherit; font-size:.875rem; font-weight:500;
    text-decoration:none; cursor:pointer; border:none;
    transition:all .2s;
  }
  .btn-primario { background:#0d2137; color:#fff; }
  .btn-primario:hover { background:#15385c; }
  .btn-ghost { background:transparent; color:#6b7280; border:1.5px solid #e5e7eb; }
  .btn-ghost:hover { background:#f9fafb; color:#374151; }
  .logo {
    display:flex; align-items:center; justify-content:center; gap:10px;
    margin-bottom:28px; padding-bottom:20px;
    border-bottom:1px solid #f3f4f6;
  }
  .logo-escudo {
    width:36px; height:36px; border-radius:50%;
    background:#0d2137;
    display:flex; align-items:center; justify-content:center;
  }
  .logo-escudo i { color:#c9a227; font-size:.9rem; }
  .logo-txt strong { display:block; font-size:.85rem; font-weight:700; color:#0d2137; }
  .logo-txt span { font-size:.65rem; color:#9ca3af; text-transform:uppercase; letter-spacing:.06em; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-escudo"><i class="fa-solid fa-shield-halved"></i></div>
    <div class="logo-txt">
      <strong>CRMV-TO</strong>
      <span>Medicina Veterinária</span>
    </div>
  </div>

  <div class="icone"><i class="fa-solid fa-lock"></i></div>

  <h1>Acesso Negado</h1>
  <p>
    Você não tem permissão para acessar esta área.<br>
    Esta seção é restrita a administradores do sistema.
  </p>

  <div class="btns">
    <?php if (!empty($_SESSION['usr_id'])): ?>
    <a href="/crmv/login.php" class="btn btn-primario" onclick="event.preventDefault(); fetch('/crmv/logout.php').then(()=>location.href='/crmv/login.php')">
      <i class="fa-solid fa-right-from-bracket"></i> Sair e entrar com outra conta
    </a>
    <?php else: ?>
    <a href="/crmv/login.php" class="btn btn-primario">
      <i class="fa-solid fa-arrow-right-to-bracket"></i> Ir para o Login
    </a>
    <?php endif; ?>
    <a href="javascript:history.back()" class="btn btn-ghost">
      <i class="fa-solid fa-arrow-left"></i> Voltar
    </a>
  </div>
</div>
</body>
</html>
