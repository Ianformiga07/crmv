<?php
require_once __DIR__ . '/../../includes/conexao.php';
exigeAdmin();

$id      = (int)($_GET['id'] ?? 0);
$editando = $id > 0;
$u       = [];
$erros   = [];

// ── Carrega dados para edição ────────────────────────────────
if ($editando) {
    $u = dbQueryOne("SELECT * FROM tbl_usuarios WHERE usuario_id = ? AND perfil_id = 2", [$id]);
    if (!$u) {
        flash('Veterinário não encontrado.', 'erro');
        header('Location: /crmv/admin/usuarios/lista.php');
        exit;
    }
}

// ── POST: salvar ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Coleta e limpa campos
    $campos = [
        'nome_completo' => trim($_POST['nome_completo'] ?? ''),
        'cpf'           => preg_replace('/\D/', '', $_POST['cpf']          ?? ''),
        'rg'            => trim($_POST['rg']            ?? ''),
        'data_nascimento'=> trim($_POST['data_nascimento'] ?? ''),
        'sexo'          => $_POST['sexo']          ?? '',
        'email'         => strtolower(trim($_POST['email'] ?? '')),
        'telefone'      => preg_replace('/\D/', '', $_POST['telefone']     ?? ''),
        'celular'       => preg_replace('/\D/', '', $_POST['celular']      ?? ''),
        'crmv_numero'   => trim($_POST['crmv_numero']   ?? ''),
        'crmv_uf'       => strtoupper(trim($_POST['crmv_uf'] ?? 'TO')),
        'especialidade' => trim($_POST['especialidade'] ?? ''),
        'instituicao'   => trim($_POST['instituicao']   ?? ''),
        'cep'           => preg_replace('/\D/', '', $_POST['cep']          ?? ''),
        'logradouro'    => trim($_POST['logradouro']    ?? ''),
        'numero'        => trim($_POST['numero']        ?? ''),
        'complemento'   => trim($_POST['complemento']   ?? ''),
        'bairro'        => trim($_POST['bairro']        ?? ''),
        'cidade'        => trim($_POST['cidade']        ?? ''),
        'uf'            => strtoupper(trim($_POST['uf'] ?? '')),
        'ativo'         => isset($_POST['ativo']) ? 1 : 0,
    ];

    $novaSenha = trim($_POST['senha'] ?? '');

    // ── Validações ───────────────────────────────────────────
    if ($campos['nome_completo'] === '') $erros[] = 'Nome completo é obrigatório.';
    if ($campos['email'] === '')         $erros[] = 'E-mail é obrigatório.';
    if (!filter_var($campos['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';

    // E-mail duplicado
    if (empty($erros)) {
        $emailExiste = dbQueryOne(
            "SELECT usuario_id FROM tbl_usuarios WHERE email = ? AND usuario_id != ?",
            [$campos['email'], $id]
        );
        if ($emailExiste) $erros[] = 'Este e-mail já está cadastrado.';
    }

    if (!$editando && $novaSenha === '') $erros[] = 'Senha é obrigatória no cadastro.';
    if ($novaSenha !== '' && strlen($novaSenha) < 6) $erros[] = 'Senha deve ter no mínimo 6 caracteres.';

    // ── Salva ────────────────────────────────────────────────
    if (empty($erros)) {
        $dnasci = $campos['data_nascimento'] !== '' ? $campos['data_nascimento'] : null;
        $sexo   = in_array($campos['sexo'], ['M','F','O']) ? $campos['sexo'] : null;

        if ($editando) {
            $sql = "UPDATE tbl_usuarios SET
                nome_completo = ?, cpf = ?, rg = ?, data_nascimento = ?, sexo = ?,
                email = ?, telefone = ?, celular = ?,
                crmv_numero = ?, crmv_uf = ?, especialidade = ?, instituicao = ?,
                cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, uf = ?,
                ativo = ?, atualizado_em = NOW()
                WHERE usuario_id = ?";

            $p = [
                $campos['nome_completo'], $campos['cpf'] ?: null, $campos['rg'] ?: null,
                $dnasci, $sexo,
                $campos['email'], $campos['telefone'] ?: null, $campos['celular'] ?: null,
                $campos['crmv_numero'] ?: null, $campos['crmv_uf'],
                $campos['especialidade'] ?: null, $campos['instituicao'] ?: null,
                $campos['cep'] ?: null, $campos['logradouro'] ?: null, $campos['numero'] ?: null,
                $campos['complemento'] ?: null, $campos['bairro'] ?: null,
                $campos['cidade'] ?: null, $campos['uf'] ?: null,
                $campos['ativo'], $id
            ];
            if ($novaSenha !== '') {
                $sql = str_replace('atualizado_em = NOW()', 'senha_hash = ?, atualizado_em = NOW()', $sql);
                array_splice($p, -1, 0, [hashSenha($novaSenha)]);
            }
            dbExecute($sql, $p);
            registraLog($_SESSION['usr_id'], 'EDITAR_USUARIO', "Editou veterinário: {$campos['nome_completo']}", 'tbl_usuarios', $id);
            flash('Veterinário atualizado com sucesso!', 'sucesso');

        } else {
            $salt = geraSalt();
            dbExecute(
                "INSERT INTO tbl_usuarios
                 (perfil_id, nome_completo, cpf, rg, data_nascimento, sexo,
                  email, telefone, celular,
                  crmv_numero, crmv_uf, especialidade, instituicao,
                  cep, logradouro, numero, complemento, bairro, cidade, uf,
                  senha_hash, senha_salt, ativo, tentativas_login, criado_por)
                 VALUES (2,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,0,?)",
                [
                    $campos['nome_completo'], $campos['cpf'] ?: null, $campos['rg'] ?: null,
                    $dnasci, $sexo,
                    $campos['email'], $campos['telefone'] ?: null, $campos['celular'] ?: null,
                    $campos['crmv_numero'] ?: null, $campos['crmv_uf'],
                    $campos['especialidade'] ?: null, $campos['instituicao'] ?: null,
                    $campos['cep'] ?: null, $campos['logradouro'] ?: null, $campos['numero'] ?: null,
                    $campos['complemento'] ?: null, $campos['bairro'] ?: null,
                    $campos['cidade'] ?: null, $campos['uf'] ?: null,
                    hashSenha($novaSenha), $salt,
                    $_SESSION['usr_id']
                ]
            );
            $novoId = dbLastId();
            registraLog($_SESSION['usr_id'], 'CRIAR_USUARIO', "Criou veterinário: {$campos['nome_completo']}", 'tbl_usuarios', $novoId);
            flash('Veterinário cadastrado com sucesso!', 'sucesso');
        }

        header('Location: /crmv/admin/usuarios/lista.php');
        exit;
    }

    // Recarrega campos com o que foi enviado para não perder
    $u = array_merge($u, $campos);
}

$ufs = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];

$pageTitulo  = $editando ? 'Editar Veterinário' : 'Novo Veterinário';
$paginaAtiva = 'usuarios';
require_once __DIR__ . '/../../includes/layout.php';

$ufs = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];
?>

<div class="pg-header">
    <div class="pg-header-row">
        <div>
            <h1 class="pg-titulo"><?= $editando ? 'Editar Veterinário' : 'Novo Veterinário' ?></h1>
            <p class="pg-subtitulo"><?= $editando ? 'Atualize os dados do cadastro' : 'Preencha os dados para cadastrar um novo veterinário' ?></p>
        </div>
        <div class="pg-acoes">
            <a href="/crmv/admin/usuarios/lista.php" class="btn btn-ghost">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
</div>

<?php if (!empty($erros)): ?>
<div class="alerta alerta-erro" style="margin-bottom:20px">
    <i class="fa-solid fa-circle-xmark"></i>
    <div>
        <strong>Corrija os erros abaixo:</strong>
        <ul style="margin:6px 0 0 16px;padding:0">
            <?php foreach ($erros as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<form method="POST" data-guard id="fForm">
<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

    <!-- COLUNA PRINCIPAL -->
    <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Dados Pessoais -->
        <div class="card">
            <div class="card-header">
                <span class="card-titulo"><i class="fa-solid fa-user"></i> Dados Pessoais</span>
            </div>
            <div class="card-body">
                <div class="form-grid">

                    <div class="c8 form-group">
                        <label class="req">Nome Completo</label>
                        <input type="text" name="nome_completo" required
                            value="<?= htmlspecialchars($u['nome_completo'] ?? '') ?>"
                            placeholder="Nome completo do veterinário">
                    </div>

                    <div class="c4 form-group">
                        <label>CPF</label>
                        <input type="text" name="cpf" data-mask="cpf" maxlength="14"
                            value="<?= htmlspecialchars(isset($u['cpf']) && $u['cpf'] ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $u['cpf']) : '') ?>"
                            placeholder="000.000.000-00">
                    </div>

                    <div class="c4 form-group">
                        <label>RG</label>
                        <input type="text" name="rg"
                            value="<?= htmlspecialchars($u['rg'] ?? '') ?>"
                            placeholder="Documento de identidade">
                    </div>

                    <div class="c4 form-group">
                        <label>Data de Nascimento</label>
                        <input type="date" name="data_nascimento"
                            value="<?= htmlspecialchars($u['data_nascimento'] ?? '') ?>">
                    </div>

                    <div class="c4 form-group">
                        <label>Sexo</label>
                        <select name="sexo">
                            <option value="">Não informado</option>
                            <option value="M" <?= ($u['sexo'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
                            <option value="F" <?= ($u['sexo'] ?? '') === 'F' ? 'selected' : '' ?>>Feminino</option>
                            <option value="O" <?= ($u['sexo'] ?? '') === 'O' ? 'selected' : '' ?>>Outro</option>
                        </select>
                    </div>

                </div>
            </div>
        </div>

        <!-- CRMV e Atuação -->
        <div class="card">
            <div class="card-header">
                <span class="card-titulo"><i class="fa-solid fa-id-badge" style="color:var(--ouro)"></i> CRMV e Atuação Profissional</span>
            </div>
            <div class="card-body">
                <div class="form-grid">

                    <div class="c5 form-group">
                        <label>Número do CRMV</label>
                        <input type="text" name="crmv_numero"
                            value="<?= htmlspecialchars($u['crmv_numero'] ?? '') ?>"
                            placeholder="Ex: 12345">
                    </div>

                    <div class="c3 form-group">
                        <label>UF do CRMV</label>
                        <select name="crmv_uf">
                            <?php foreach ($ufs as $uf): ?>
                            <option value="<?= $uf ?>" <?= ($u['crmv_uf'] ?? 'TO') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="c4 form-group">
                        <label>Especialidade</label>
                        <input type="text" name="especialidade"
                            value="<?= htmlspecialchars($u['especialidade'] ?? '') ?>"
                            placeholder="Ex: Clínica de Pequenos Animais">
                    </div>

                    <div class="c12 form-group">
                        <label>Instituição / Local de Trabalho</label>
                        <input type="text" name="instituicao"
                            value="<?= htmlspecialchars($u['instituicao'] ?? '') ?>"
                            placeholder="Nome da clínica, hospital veterinário ou empresa">
                    </div>

                </div>
            </div>
        </div>

        <!-- Contato -->
        <div class="card">
            <div class="card-header">
                <span class="card-titulo"><i class="fa-solid fa-address-book"></i> Contato</span>
            </div>
            <div class="card-body">
                <div class="form-grid">

                    <div class="c6 form-group">
                        <label class="req">E-mail</label>
                        <input type="email" name="email" required
                            value="<?= htmlspecialchars($u['email'] ?? '') ?>"
                            placeholder="email@exemplo.com">
                    </div>

                    <div class="c3 form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" data-mask="tel"
                            value="<?= htmlspecialchars(isset($u['telefone']) && $u['telefone'] ? preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $u['telefone']) : '') ?>"
                            placeholder="(63) 0000-0000">
                    </div>

                    <div class="c3 form-group">
                        <label>Celular / WhatsApp</label>
                        <input type="text" name="celular" data-mask="tel"
                            value="<?= htmlspecialchars(isset($u['celular']) && $u['celular'] ? preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $u['celular']) : '') ?>"
                            placeholder="(63) 00000-0000">
                    </div>

                </div>
            </div>
        </div>

        <!-- Endereço -->
        <div class="card">
            <div class="card-header">
                <span class="card-titulo"><i class="fa-solid fa-location-dot"></i> Endereço</span>
            </div>
            <div class="card-body">
                <div class="form-grid">

                    <div class="c3 form-group">
                        <label>CEP</label>
                        <input type="text" name="cep" data-mask="cep"
                            value="<?= htmlspecialchars(isset($u['cep']) && $u['cep'] ? preg_replace('/(\d{5})(\d{3})/', '$1-$2', $u['cep']) : '') ?>"
                            placeholder="77000-000">
                        <span class="dica">Preenchimento automático</span>
                    </div>

                    <div class="c7 form-group">
                        <label>Logradouro</label>
                        <input type="text" name="logradouro"
                            value="<?= htmlspecialchars($u['logradouro'] ?? '') ?>"
                            placeholder="Rua, Avenida, etc.">
                    </div>

                    <div class="c2 form-group">
                        <label>Número</label>
                        <input type="text" name="numero"
                            value="<?= htmlspecialchars($u['numero'] ?? '') ?>"
                            placeholder="Nº">
                    </div>

                    <div class="c4 form-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento"
                            value="<?= htmlspecialchars($u['complemento'] ?? '') ?>"
                            placeholder="Apto, Sala, etc.">
                    </div>

                    <div class="c4 form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro"
                            value="<?= htmlspecialchars($u['bairro'] ?? '') ?>"
                            placeholder="Bairro">
                    </div>

                    <div class="c4 form-group">
                        <label>Cidade</label>
                        <input type="text" name="cidade"
                            value="<?= htmlspecialchars($u['cidade'] ?? '') ?>"
                            placeholder="Cidade">
                    </div>

                    <div class="c2 form-group">
                        <label>UF</label>
                        <select name="uf">
                            <option value="">—</option>
                            <?php foreach ($ufs as $uf): ?>
                            <option value="<?= $uf ?>" <?= ($u['uf'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
            </div>
        </div>

    </div><!-- /col-principal -->

    <!-- COLUNA LATERAL -->
    <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Acesso -->
        <div class="card">
            <div class="card-header">
                <span class="card-titulo"><i class="fa-solid fa-lock"></i> Acesso ao Sistema</span>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:16px">

                <div class="form-group">
                    <label><?= $editando ? 'Nova Senha' : 'Senha *' ?></label>
                    <?php if ($editando): ?>
                    <span class="dica" style="margin-top:-2px">Deixe em branco para não alterar</span>
                    <?php endif; ?>
                    <div style="position:relative">
                        <input type="password" name="senha" id="campSenha"
                            placeholder="<?= $editando ? 'Nova senha (opcional)' : 'Mínimo 6 caracteres' ?>"
                            style="padding-right:40px">
                        <button type="button" onclick="toggleSenha()"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--c400)">
                            <i class="fa-solid fa-eye" id="iSenha"></i>
                        </button>
                    </div>
                </div>

                <label class="toggle-label">
                    <input type="checkbox" class="toggle" name="ativo" id="chkAtivo" <?= ($u['ativo'] ?? 1) ? 'checked' : '' ?>>
                    <span class="toggle-track"></span>
                    <span>Conta ativa</span>
                </label>
                <span class="dica" style="margin-top:-10px">Veterinários inativos não conseguem fazer login</span>

            </div>
        </div>

        <!-- Resumo (só na edição) -->
        <?php if ($editando): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-titulo"><i class="fa-solid fa-chart-simple"></i> Resumo</span>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
                <?php
                $stats = dbQueryOne(
                    "SELECT
                        (SELECT COUNT(*) FROM tbl_matriculas WHERE usuario_id = ?) AS matriculas,
                        (SELECT COUNT(*) FROM tbl_matriculas WHERE usuario_id = ? AND certificado_gerado = 1) AS certificados,
                        (SELECT COUNT(*) FROM tbl_matriculas WHERE usuario_id = ? AND status = 'CONCLUIDA') AS concluidas",
                    [$id, $id, $id]
                );
                ?>
                <div style="display:flex;justify-content:space-between;font-size:.875rem;padding:6px 0;border-bottom:1px solid var(--c100)">
                    <span style="color:var(--c500)"><i class="fa-solid fa-list-check" style="width:16px"></i> Matrículas</span>
                    <strong><?= $stats['matriculas'] ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.875rem;padding:6px 0;border-bottom:1px solid var(--c100)">
                    <span style="color:var(--c500)"><i class="fa-solid fa-graduation-cap" style="width:16px"></i> Concluídos</span>
                    <strong><?= $stats['concluidas'] ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.875rem;padding:6px 0">
                    <span style="color:var(--c500)"><i class="fa-solid fa-certificate" style="width:16px;color:var(--ouro)"></i> Certificados</span>
                    <strong style="color:var(--verde)"><?= $stats['certificados'] ?></strong>
                </div>
                <a href="/crmv/admin/usuarios/ver.php?id=<?= $id ?>" class="btn btn-ghost btn-sm" style="margin-top:4px">
                    <i class="fa-solid fa-eye"></i> Ver perfil completo
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="dica" style="display:flex;flex-direction:column;gap:6px">
                    <div><i class="fa-solid fa-calendar-plus" style="width:16px"></i> Cadastrado em: <?= fmtDataHora($u['criado_em']) ?></div>
                    <div><i class="fa-solid fa-clock" style="width:16px"></i> Último acesso: <?= $u['ultimo_acesso'] ? fmtDataHora($u['ultimo_acesso']) : 'Nunca' ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botões -->
        <div style="display:flex;flex-direction:column;gap:8px">
            <button type="submit" class="btn btn-primario" style="justify-content:center">
                <i class="fa-solid fa-floppy-disk"></i>
                <?= $editando ? 'Salvar Alterações' : 'Cadastrar Veterinário' ?>
            </button>
            <a href="/crmv/admin/usuarios/lista.php" class="btn btn-ghost" style="justify-content:center">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </a>
        </div>

    </div><!-- /col-lateral -->
</div>
</form>

<script>
function toggleSenha() {
    var i = document.getElementById('campSenha'), ic = document.getElementById('iSenha');
    i.type = i.type === 'password' ? 'text' : 'password';
    ic.className = i.type === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
}
</script>

<?php require_once __DIR__ . '/../../includes/layout_footer.php'; ?>
