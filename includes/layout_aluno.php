<?php
/**
 * includes/layout_aluno.php
 * Cabeçalho do portal do veterinário — mesmo padrão visual do admin
 * Variáveis esperadas: $pageTitulo, $paginaAtiva
 */
if (session_status() === PHP_SESSION_NONE) session_start();
exigeLogin();
if ((int)($_SESSION['usr_perfil'] ?? 0) === 1) {
    header('Location: /crmv/admin/dashboard.php'); exit;
}
$_nomeExib  = (explode(' ', $_SESSION['usr_nome'] ?? 'Veterinário')[0]) ?: 'Veterinário';
$_iniciais  = strtoupper(mb_substr($_SESSION['usr_nome'] ?? 'V', 0, 1));
$_flash     = getFlash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitulo ?? 'Portal') ?> — CRMV-TO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/crmv/assets/css/admin.css">
<style>
/* ── Ajustes específicos do portal do aluno ─────────────────── */
.sb-badge {
    margin-left:auto;
    background:rgba(201,162,39,.18);
    color:var(--ouro2);
    font-size:.6rem;font-weight:700;
    padding:2px 7px;border-radius:10px;
    text-transform:uppercase;letter-spacing:.05em;
}
.curso-card-aluno {
    background:var(--branco);
    border-radius:var(--radius-lg);
    border:1px solid var(--c200);
    box-shadow:var(--shadow-sm);
    overflow:hidden;
    display:flex;flex-direction:column;
    transition:transform .18s, box-shadow .18s;
}
.curso-card-aluno:hover {
    transform:translateY(-2px);
    box-shadow:var(--shadow);
}
.curso-capa-aluno {
    height:130px;
    position:relative;overflow:hidden;
    background:var(--azul-esc);
    flex-shrink:0;
}
.curso-capa-aluno img {
    width:100%;height:100%;object-fit:cover;
    position:absolute;inset:0;
}
.curso-capa-overlay {
    position:absolute;inset:0;
    background:linear-gradient(to top,rgba(0,0,0,.55) 0%,transparent 60%);
}
.progresso-bar {
    height:4px;background:var(--c200);
}
.progresso-fill {
    height:4px;border-radius:0 2px 2px 0;
    transition:width .3s;
}
</style>
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

    <!-- Logo / Brand -->
    <a href="/crmv/aluno/dashboard.php" class="sb-brand">
        <div class="sb-brand-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div class="sb-brand-txt">
            <span class="sb-brand-nome">CRMV-TO</span>
            <span class="sb-brand-sub">Portal do Veterinário</span>
        </div>
    </a>

    <!-- Navegação -->
    <nav class="sb-nav">
        <span class="sb-secao">Menu</span>

        <a href="/crmv/aluno/dashboard.php"
           class="sb-link <?= ($paginaAtiva??'') === 'dashboard' ? 'ativo' : '' ?>">
            <i class="fa-solid fa-house"></i> Início
        </a>

        <a href="/crmv/aluno/dashboard.php?aba=ativos"
           class="sb-link <?= ($paginaAtiva??'') === 'meus-cursos' ? 'ativo' : '' ?>">
            <i class="fa-solid fa-graduation-cap"></i> Meus Cursos
        </a>

        <a href="/crmv/aluno/dashboard.php?aba=concluidos"
           class="sb-link <?= ($paginaAtiva??'') === 'concluidos' ? 'ativo' : '' ?>">
            <i class="fa-solid fa-circle-check"></i> Concluídos
        </a>

        <a href="/crmv/aluno/certificados.php"
           class="sb-link <?= ($paginaAtiva??'') === 'certificados' ? 'ativo' : '' ?>">
            <i class="fa-solid fa-certificate"></i> Certificados
            <span class="sb-badge">Emitir</span>
        </a>

        <span class="sb-secao" style="margin-top:8px">Conta</span>

        <a href="/crmv/aluno/perfil.php"
           class="sb-link <?= ($paginaAtiva??'') === 'perfil' ? 'ativo' : '' ?>">
            <i class="fa-solid fa-user-circle"></i> Meu Perfil
        </a>
    </nav>

    <!-- Rodapé da sidebar: usuário + sair -->
    <div class="sb-footer">
        <div class="sb-user">
            <div class="sb-avatar"><?= htmlspecialchars($_iniciais) ?></div>
            <div class="sb-user-info">
                <div class="sb-user-nome"><?= htmlspecialchars($_nomeExib) ?></div>
                <div class="sb-user-papel">Veterinário</div>
            </div>
            <a href="/crmv/logout.php" class="sb-logout" title="Sair">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>

<!-- ═══════════════════════════════════════════════════════════
     MAIN WRAPPER
═══════════════════════════════════════════════════════════ -->
<div class="main-wrapper">

    <!-- Topbar -->
    <header class="topbar">
        <button class="topbar-toggle" id="sidebar-toggle">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-breadcrumb">
            <a href="/crmv/aluno/dashboard.php"><i class="fa-solid fa-house" style="font-size:.75rem"></i></a>
            <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
            <span class="atual"><?= htmlspecialchars($pageTitulo ?? '') ?></span>
        </div>
        <div class="topbar-acoes">
            <a href="/crmv/logout.php"
               style="display:flex;align-items:center;gap:6px;font-size:.82rem;color:var(--c500);padding:7px 12px;border-radius:var(--radius);border:1.5px solid var(--c300);text-decoration:none;transition:.15s"
               onmouseover="this.style.background='var(--c100)'"
               onmouseout="this.style.background='transparent'">
                <i class="fa-solid fa-right-from-bracket"></i> Sair
            </a>
        </div>
    </header>

    <!-- Flash messages -->
    <?php if ($_flash): ?>
    <div style="padding:0 26px;margin-top:16px">
        <div class="alerta alerta-<?= $_flash['tipo'] ?>">
            <i class="fa-solid <?= $_flash['tipo']==='sucesso'?'fa-circle-check':($_flash['tipo']==='erro'?'fa-circle-xmark':'fa-triangle-exclamation') ?>"></i>
            <?= htmlspecialchars($_flash['msg']) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Conteúdo da página -->
    <main class="pagina">
