<?php
// ============================================================
//  CRMV/TO — gerar_admin.php
//  Acesse este arquivo UMA VEZ no navegador para criar o admin.
//  DEPOIS DE USAR: APAGUE este arquivo do servidor!
// ============================================================
require_once __DIR__ . '/includes/conexao.php';

$senha = 'Admin@2025';
$salt  = bin2hex(random_bytes(16)); // salt aleatório (não usado pelo bcrypt, mas guardado)
$hash  = hashSenha($senha);

// Verifica se admin já existe
$jaExiste = dbQueryOne("SELECT usuario_id FROM tbl_usuarios WHERE email = 'admin@crmvto.gov.br'");
$sqlUpdate = $sqlInsert = '';

if ($jaExiste) {
    $sqlUpdate = "UPDATE tbl_usuarios SET senha_hash = '$hash', senha_salt = '$salt', tentativas_login = 0, bloqueado_ate = NULL, ativo = 1 WHERE email = 'admin@crmvto.gov.br';";
} else {
    $sqlInsert = "INSERT INTO tbl_usuarios (perfil_id, nome_completo, cpf, email, senha_hash, senha_salt, crmv_uf, ativo, tentativas_login)
VALUES (1, 'Administrador CRMV/TO', '000.000.000-00', 'admin@crmvto.gov.br', '$hash', '$salt', 'TO', 1, 0);";
}

// Executa automaticamente
$msg = '';
try {
    if ($jaExiste) {
        dbExecute("UPDATE tbl_usuarios SET senha_hash = ?, senha_salt = ?, tentativas_login = 0, bloqueado_ate = NULL, ativo = 1 WHERE email = 'admin@crmvto.gov.br'", [$hash, $salt]);
        $msg = '✅ Admin atualizado com sucesso!';
    } else {
        dbExecute("INSERT INTO tbl_usuarios (perfil_id, nome_completo, cpf, email, senha_hash, senha_salt, crmv_uf, ativo, tentativas_login) VALUES (1, 'Administrador CRMV/TO', '000.000.000-00', 'admin@crmvto.gov.br', ?, ?, 'TO', 1, 0)", [$hash, $salt]);
        $msg = '✅ Admin criado com sucesso!';
    }
} catch (Exception $e) {
    $msg = '❌ Erro: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Gerar Admin — CRMV/TO</title>
<style>body{font-family:monospace;background:#0d2137;color:#e2e8f0;padding:40px;max-width:700px;margin:0 auto}
h2{color:#e6bb45;margin-bottom:24px}
.box{background:#15385c;padding:24px;border-radius:10px;margin-bottom:20px}
.ok{color:#4ade80;font-size:1.2rem;margin-bottom:16px}
.err{color:#f87171;font-size:1.2rem}
pre{background:#0d2137;padding:16px;border-radius:8px;overflow:auto;font-size:.85rem;color:#93c5fd}
.aviso{background:#7f1d1d;padding:14px;border-radius:8px;color:#fca5a5;margin-top:20px}
a{color:#e6bb45}</style>
</head>
<body>
<h2>🔑 Gerar Admin — CRMV/TO</h2>

<div class="box">
    <div class="<?= str_starts_with($msg, '✅') ? 'ok' : 'err' ?>"><?= $msg ?></div>
    <p><strong>E-mail:</strong> admin@crmvto.gov.br</p>
    <p><strong>Senha:</strong> Admin@2025</p>
    <p><strong>Hash gerado:</strong></p>
    <pre><?= htmlspecialchars($hash) ?></pre>
</div>

<div class="aviso">
    ⚠️ <strong>APAGUE este arquivo imediatamente após usar!</strong><br>
    Deixar este arquivo no servidor é um risco de segurança.<br><br>
    <a href="/crmv/login.php">→ Ir para o Login</a>
</div>
</body>
</html>
