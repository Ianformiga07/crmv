<?php
/**
 * CRMV/TO — verificar_admin.php
 * Diagnóstico e correção do usuário admin
 * DELETE este arquivo após usar!
 */
require_once __DIR__ . '/includes/conexao.php';

$acao = $_POST['acao'] ?? '';

// Corrigir perfil
if ($acao === 'corrigir') {
    $email = trim($_POST['email'] ?? '');
    if ($email) {
        dbExecute("UPDATE tbl_usuarios SET perfil_id = 1, ativo = 1 WHERE email = ?", [$email]);
        echo "<p style='color:green;font-weight:bold'>✓ Usuário '$email' atualizado para perfil_id=1 (Admin) e ativo=1</p>";
    }
}

// Recriar admin
if ($acao === 'recriar') {
    $email = 'admin@crmvto.gov.br';
    $existe = dbQueryOne("SELECT usuario_id FROM tbl_usuarios WHERE email = ?", [$email]);
    if ($existe) {
        dbExecute(
            "UPDATE tbl_usuarios SET perfil_id=1, senha_hash=?, ativo=1, tentativas_login=0, bloqueado_ate=NULL WHERE email=?",
            [hashSenha('Admin@2025'), $email]
        );
        echo "<p style='color:green;font-weight:bold'>✓ Admin resetado! Email: $email | Senha: Admin@2025</p>";
    } else {
        dbExecute(
            "INSERT INTO tbl_usuarios (perfil_id, nome_completo, email, senha_hash, senha_salt, ativo, tentativas_login)
             VALUES (1, 'Administrador', ?, ?, '', 1, 0)",
            [$email, hashSenha('Admin@2025')]
        );
        echo "<p style='color:green;font-weight:bold'>✓ Admin criado! Email: $email | Senha: Admin@2025</p>";
    }
}

// Listar usuários
$usuarios = dbQuery("SELECT usuario_id, nome_completo, email, perfil_id, ativo, tentativas_login, bloqueado_ate FROM tbl_usuarios ORDER BY perfil_id, nome_completo");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Verificar Admin — CRMV/TO</title>
<style>
  body { font-family: sans-serif; max-width: 860px; margin: 40px auto; padding: 0 20px; }
  h1 { color: #0d2137; }
  table { width:100%; border-collapse:collapse; margin:20px 0; }
  th { background:#0d2137; color:#fff; padding:8px 12px; text-align:left; font-size:.8rem; }
  td { padding:8px 12px; border-bottom:1px solid #eee; font-size:.85rem; }
  tr:hover td { background:#f9f9f9; }
  .ok   { color:green; font-weight:bold; }
  .erro { color:red;   font-weight:bold; }
  .box  { background:#f5f5f0; border:1px solid #ddd; border-radius:8px; padding:20px; margin:16px 0; }
  input[type=email] { padding:8px; width:260px; border:1px solid #ccc; border-radius:5px; }
  button { padding:8px 18px; background:#0d2137; color:#fff; border:none; border-radius:5px; cursor:pointer; margin-left:8px; }
  .warn { background:#fef9c3; border:1px solid #fde68a; border-radius:6px; padding:12px 16px; margin:16px 0; font-size:.85rem; }
</style>
</head>
<body>
<h1>🔍 Diagnóstico CRMV/TO — Usuários</h1>

<div class="warn">⚠️ <strong>Delete este arquivo após usar!</strong> Ele expõe dados sensíveis.</div>

<h2>Usuários cadastrados</h2>
<table>
  <thead><tr><th>ID</th><th>Nome</th><th>Email</th><th>perfil_id</th><th>ativo</th><th>Tentativas</th><th>Bloqueado até</th></tr></thead>
  <tbody>
  <?php foreach ($usuarios as $u): ?>
  <tr>
    <td><?= $u['usuario_id'] ?></td>
    <td><?= htmlspecialchars($u['nome_completo']) ?></td>
    <td><?= htmlspecialchars($u['email']) ?></td>
    <td class="<?= $u['perfil_id']==1 ? 'ok' : 'erro' ?>"><?= $u['perfil_id'] ?> <?= $u['perfil_id']==1 ? '(Admin ✓)' : '(NÃO é admin!)' ?></td>
    <td class="<?= $u['ativo'] ? 'ok' : 'erro' ?>"><?= $u['ativo'] ? 'Sim ✓' : 'NÃO!' ?></td>
    <td><?= $u['tentativas_login'] ?></td>
    <td class="<?= $u['bloqueado_ate'] ? 'erro' : '' ?>"><?= $u['bloqueado_ate'] ?? '—' ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (empty($usuarios)): ?>
  <tr><td colspan="7" style="color:red;text-align:center">Nenhum usuário encontrado!</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<div class="box">
  <h3>Corrigir perfil de um usuário existente para Admin</h3>
  <form method="POST">
    <input type="hidden" name="acao" value="corrigir">
    <input type="email" name="email" placeholder="email@exemplo.com" required>
    <button type="submit">Tornar Admin</button>
  </form>
</div>

<div class="box">
  <h3>Recriar / resetar o admin padrão</h3>
  <p style="font-size:.85rem;color:#666">Cria ou reseta o usuário <strong>admin@crmvto.gov.br</strong> com senha <strong>Admin@2025</strong></p>
  <form method="POST">
    <input type="hidden" name="acao" value="recriar">
    <button type="submit" style="background:#1a6b3c">Recriar Admin Padrão</button>
  </form>
</div>
</body>
</html>
