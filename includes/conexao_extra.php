<?php
// ================================================================
// CRMV/TO — conexao_extra.php
// Cole as funções abaixo no final do seu includes/conexao.php
// ================================================================

/**
 * Gera um código único de certificado no formato CRMV-ANO-XXXXXX
 * Ex: CRMV-2025-A3F9K2
 */
function geraCodigoCert(): string {
    $ano     = date('Y');
    $chars   = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem I, O, 0, 1 (confusos)
    $sufixo  = '';
    for ($i = 0; $i < 6; $i++) {
        $sufixo .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return "CRMV-{$ano}-{$sufixo}";
}
