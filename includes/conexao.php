<?php
// ============================================================
//  CRMV/TO — includes/conexao.php
//  Conexão PDO + funções utilitárias globais
// ============================================================

// ── Configuração do banco ────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // senha do MySQL no XAMPP (geralmente vazio)
define('DB_NAME', 'crmv_cursos');
define('DB_PORT', '3306');

// Chave de segurança para o hash (não altere após criar o primeiro admin)
define('HASH_KEY', 'CRMVTO2025KEY');

// ── Conexão PDO global ───────────────────────────────────────
function getConn(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:monospace;background:#fee2e2;color:#7f1d1d;padding:20px;margin:20px;border-radius:8px">
                <strong>Erro de conexão com o banco:</strong><br>' . htmlspecialchars($e->getMessage()) . '
                <br><br>Verifique se o XAMPP está rodando e o banco <strong>' . DB_NAME . '</strong> foi criado.
            </div>');
        }
    }
    return $pdo;
}

// ── Executa SELECT e retorna todos os registros ──────────────
function dbQuery(string $sql, array $params = []): array {
    $stmt = getConn()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ── Executa SELECT e retorna um único registro ───────────────
function dbQueryOne(string $sql, array $params = []): array|false {
    $stmt = getConn()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

// ── Executa INSERT / UPDATE / DELETE ────────────────────────
function dbExecute(string $sql, array $params = []): int {
    $stmt = getConn()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

// ── Retorna o último ID inserido ────────────────────────────
function dbLastId(): string {
    return getConn()->lastInsertId();
}

// ============================================================
//  UTILITÁRIOS
// ============================================================

// Valor padrão se nulo/vazio
function nvl(mixed $val, mixed $def = ''): mixed {
    return ($val === null || $val === '') ? $def : $val;
}

// Trunca texto com reticências
function truncaTexto(string $str, int $tam): string {
    return mb_strlen($str) > $tam ? mb_substr($str, 0, $tam) . '...' : $str;
}

// Primeira letra para avatar
function primeiraLetra(string $nome): string {
    return mb_strtoupper(mb_substr(trim($nome), 0, 1));
}

// Formata data BR: 25/03/2025
function fmtData(?string $d): string {
    if (!$d) return '-';
    try { return (new DateTime($d))->format('d/m/Y'); }
    catch (Exception) { return '-'; }
}

// Formata data+hora BR: 25/03/2025 14:30
function fmtDataHora(?string $d): string {
    if (!$d) return '-';
    try { return (new DateTime($d))->format('d/m/Y H:i'); }
    catch (Exception) { return '-'; }
}

// Formata CRMV: 12345-TO
function fmtCRMV(?string $numero, string $uf = 'TO'): string {
    return $numero ? trim($numero) . '-' . $uf : 'Não informado';
}

// ============================================================
//  HASH DE SENHA — password_hash nativo do PHP
//  Simples, seguro e sem dependências externas.
// ============================================================

// Gera hash seguro (bcrypt)
function hashSenha(string $senha): string {
    return password_hash($senha . HASH_KEY, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Verifica senha
function verificaSenha(string $senha, string $hash): bool {
    return password_verify($senha . HASH_KEY, $hash);
}

// Gera salt aleatório (guardado no banco, usado como referência)
function geraSalt(): string {
    return bin2hex(random_bytes(16));
}

// ============================================================
//  CERTIFICADO
// ============================================================
function geraCodigoCert(): string {
    $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $codigo = '';
    for ($i = 0; $i < 4; $i++) $codigo .= $chars[random_int(0, strlen($chars) - 1)];
    $codigo .= '-';
    for ($i = 0; $i < 4; $i++) $codigo .= $chars[random_int(0, strlen($chars) - 1)];
    $codigo .= '-';
    for ($i = 0; $i < 4; $i++) $codigo .= $chars[random_int(0, strlen($chars) - 1)];
    return $codigo;
}

// ============================================================
//  LOG / CONFIGURAÇÃO / SESSÃO
// ============================================================
function registraLog(int|null $usr_id, string $acao, string $descricao = '', string $tabela = '', int|null $reg_id = null): void {
    try {
        dbExecute(
            "INSERT INTO tbl_log_atividades (usuario_id, acao, descricao, tabela_ref, registro_id, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $usr_id,
                $acao,
                $descricao,
                $tabela,
                $reg_id,
                $_SERVER['REMOTE_ADDR'] ?? '',
                mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
            ]
        );
    } catch (Exception) {}
}

function getConfig(string $chave): string {
    $row = dbQueryOne("SELECT valor FROM tbl_configuracoes WHERE chave = ?", [$chave]);
    return $row ? (string) $row['valor'] : '';
}

// Flash messages (mensagem passada entre páginas via sessão)
function flash(string $msg, string $tipo = 'sucesso'): void {
    $_SESSION['flash_msg']  = $msg;
    $_SESSION['flash_tipo'] = $tipo;
}

function getFlash(): array {
    $msg  = $_SESSION['flash_msg']  ?? '';
    $tipo = $_SESSION['flash_tipo'] ?? 'sucesso';
    unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
    return ['msg' => $msg, 'tipo' => $tipo];
}

// ============================================================
//  SEGURANÇA / SESSÃO
// ============================================================
function exigeLogin(): void {
    if (empty($_SESSION['usr_id'])) {
        $ret = urlencode($_SERVER['REQUEST_URI']);
        header("Location: /crmv/login.php?ret=$ret");
        exit;
    }
}

function exigeAdmin(): void {
    // Sem sessão → redireciona para o login
    if (empty($_SESSION['usr_id'])) {
        $ret = urlencode($_SERVER['REQUEST_URI'] ?? '/crmv/admin/dashboard.php');
        header("Location: /crmv/login.php?ret=$ret");
        exit;
    }
    // Logado mas não é admin → acesso negado
    if ((int)($_SESSION['usr_perfil'] ?? 0) !== 1) {
        header("Location: /crmv/acesso-negado.php");
        exit;
    }
}

// ============================================================
//  BADGES HTML
// ============================================================
function badgeModalidade(string $mod): string {
    return match(strtoupper(trim($mod))) {
        'EAD'       => '<span class="badge b-verde"><i class="fa-solid fa-wifi"></i> EAD</span>',
        'HIBRIDO'   => '<span class="badge b-ouro"><i class="fa-solid fa-layer-group"></i> Híbrido</span>',
        'PRESENCIAL'=> '<span class="badge b-cinza"><i class="fa-solid fa-map-marker-alt"></i> Presencial</span>',
        default     => '<span class="badge b-cinza">' . htmlspecialchars($mod) . '</span>',
    };
}

function badgeStatus(string $status): string {
    return match(strtoupper(trim($status))) {
        'PUBLICADO' => '<span class="badge b-verde">Publicado</span>',
        'RASCUNHO'  => '<span class="badge b-cinza">Rascunho</span>',
        'ENCERRADO' => '<span class="badge b-verm">Encerrado</span>',
        'CANCELADO' => '<span class="badge b-verm">Cancelado</span>',
        'ATIVA'     => '<span class="badge b-azul">Ativa</span>',
        'CONCLUIDA' => '<span class="badge b-verde">Concluída</span>',
        'REPROVADO' => '<span class="badge b-verm">Reprovado</span>',
        default     => '<span class="badge b-cinza">' . htmlspecialchars($status) . '</span>',
    };
}

// Inicia sessão se ainda não iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
