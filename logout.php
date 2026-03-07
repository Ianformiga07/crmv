<?php
require_once __DIR__ . '/includes/conexao.php';

if (!empty($_SESSION['usr_id'])) {
    registraLog($_SESSION['usr_id'], 'LOGOUT', 'Logout realizado', 'tbl_usuarios', $_SESSION['usr_id']);
}

session_destroy();
header('Location: /crmv/login.php');
exit;
