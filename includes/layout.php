<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitulo ?? 'CRMV/TO') ?> — CRMV/TO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/crmv/assets/css/admin.css">
</head>
<body>

<aside class="sidebar" id="sidebar">
    <a href="/crmv/admin/dashboard.php" class="sb-brand">
        <div class="sb-brand-icon"><i class="fa-solid fa-paw"></i></div>
        <div class="sb-brand-txt">
            <span class="sb-brand-nome">CRMV/TO</span>
            <span class="sb-brand-sub">Educação Continuada</span>
        </div>
    </a>

    <nav class="sb-nav">
        <span class="sb-secao">Principal</span>
        <a href="/crmv/admin/dashboard.php" class="sb-link <?= ($paginaAtiva??'')=='dashboard'?'ativo':'' ?>">
            <i class="fa-solid fa-chart-line"></i><span>Dashboard</span>
        </a>

        <span class="sb-secao">Gestão</span>
        <a href="/crmv/admin/usuarios/lista.php" class="sb-link <?= ($paginaAtiva??'')=='usuarios'?'ativo':'' ?>">
            <i class="fa-solid fa-user-doctor"></i><span>Veterinários</span>
        </a>
        <a href="/crmv/admin/cursos/lista.php" class="sb-link <?= ($paginaAtiva??'')=='cursos'?'ativo':'' ?>">
            <i class="fa-solid fa-graduation-cap"></i><span>Cursos & Palestras</span>
        </a>
        <a href="/crmv/admin/avaliacoes/lista.php" class="sb-link <?= ($paginaAtiva??'')=='avaliacoes'?'ativo':'' ?>">
            <i class="fa-solid fa-clipboard-check"></i><span>Avaliações</span>
        </a>
        <a href="/crmv/admin/certificados/lista.php" class="sb-link <?= ($paginaAtiva??'')=='certificados'?'ativo':'' ?>">
            <i class="fa-solid fa-certificate"></i><span>Certificados</span>
        </a>

        <span class="sb-secao">Relatórios</span>
        <a href="/crmv/admin/relatorios/matriculas.php" class="sb-link <?= ($paginaAtiva??'')=='rel_mat'?'ativo':'' ?>">
            <i class="fa-solid fa-list-check"></i><span>Matrículas</span>
        </a>
        <a href="/crmv/admin/relatorios/certificados.php" class="sb-link <?= ($paginaAtiva??'')=='rel_cert'?'ativo':'' ?>">
            <i class="fa-solid fa-award"></i><span>Certificados Emitidos</span>
        </a>

        <span class="sb-secao">Sistema</span>
        <a href="/crmv/admin/configuracoes.php" class="sb-link <?= ($paginaAtiva??'')=='config'?'ativo':'' ?>">
            <i class="fa-solid fa-gear"></i><span>Configurações</span>
        </a>
        <a href="/crmv/admin/logs.php" class="sb-link <?= ($paginaAtiva??'')=='logs'?'ativo':'' ?>">
            <i class="fa-solid fa-clock-rotate-left"></i><span>Logs</span>
        </a>
    </nav>

    <div class="sb-footer">
        <div class="sb-user">
            <div class="sb-avatar"><?= primeiraLetra($_SESSION['usr_nome'] ?? 'A') ?></div>
            <div class="sb-user-info">
                <div class="sb-user-nome"><?= htmlspecialchars(truncaTexto($_SESSION['usr_nome'] ?? '', 22)) ?></div>
                <div class="sb-user-papel">Administrador</div>
            </div>
            <a href="/crmv/logout.php" class="sb-logout" title="Sair">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>

<div class="main-wrapper">
    <header class="topbar">
        <button class="topbar-toggle" id="btnSidebar"><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-breadcrumb">
            <a href="/crmv/admin/dashboard.php">Início</a>
            <?php if (($pageTitulo ?? '') !== 'Dashboard'): ?>
            <i class="fa-solid fa-chevron-right sep"></i>
            <span class="atual"><?= htmlspecialchars($pageTitulo ?? '') ?></span>
            <?php endif; ?>
        </div>
        <div class="topbar-acoes">
            <a href="/crmv/admin/cursos/form.php" class="btn btn-primario btn-sm">
                <i class="fa-solid fa-plus"></i> Novo Curso
            </a>
        </div>
    </header>

    <main class="pagina">
    <?php
    $flash = getFlash();
    if ($flash['msg']): ?>
    <div class="alerta alerta-<?= $flash['tipo'] ?>" id="flashMsg" style="margin-bottom:20px">
        <i class="fa-solid fa-<?= $flash['tipo']==='sucesso' ? 'circle-check' : ($flash['tipo']==='erro' ? 'circle-xmark' : 'circle-info') ?>"></i>
        <span><?= htmlspecialchars($flash['msg']) ?></span>
    </div>
    <?php endif; ?>
