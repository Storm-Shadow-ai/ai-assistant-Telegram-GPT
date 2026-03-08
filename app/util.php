<?php


function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_root() {
    return dirname(__DIR__);
}

function load_config() {
    $p = app_root() . '/config/config.php';
    if (!file_exists($p)) {
        return null;
    }
    return require $p;
}

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $cfg = load_config();
    if (!$cfg) {
        throw new Exception("Not installed (config missing)");
    }
    $dsn = "mysql:host={$cfg['db_host']};port={$cfg['db_port']};dbname={$cfg['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    return $pdo;
}

function setting_get($key, $default=null) {
    $pdo = db();
    $st = $pdo->prepare("SELECT v FROM settings WHERE k=? LIMIT 1");
    $st->execute([$key]);
    $row = $st->fetch();
    if (!$row) return $default;
    return $row['v'];
}

function setting_set($key, $value) {
    $pdo = db();
    $st = $pdo->prepare("INSERT INTO settings(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)");
    $st->execute([$key, (string)$value]);
}

function require_admin() {
    if (session_status() !== PHP_SESSION_ACTIVE) {

        @ini_set('session.cookie_httponly', '1');
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            @ini_set('session.cookie_secure', '1');
        }
        @ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }
    if (empty($_SESSION['admin_ok'])) {
        header("Location: /admin/login.php");
        exit;
    }
}

function csrf_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_check() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $sent = (string)($_POST['_csrf'] ?? '');
    $ok = !empty($_SESSION['_csrf']) && hash_equals((string)$_SESSION['_csrf'], $sent);
    if (!$ok) {
        throw new Exception('CSRF check failed');
    }
}

function get_header_value($name) {

    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$key])) return (string)$_SERVER[$key];
    foreach ($_SERVER as $k => $v) {
        if (strcasecmp($k, $key) === 0) return (string)$v;
    }
    return '';
}

function setting_get_int($key, $default=0) {
    $v = setting_get($key, null);
    if ($v === null || $v === '') return (int)$default;
    return (int)$v;
}

function ensure_schema_base(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
      id INT AUTO_INCREMENT PRIMARY KEY,
      k VARCHAR(120) NOT NULL UNIQUE,
      v MEDIUMTEXT NOT NULL,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tg_users (
      id BIGINT PRIMARY KEY,
      username VARCHAR(128) NULL,
      first_name VARCHAR(128) NULL,
      last_name VARCHAR(128) NULL,
      is_blocked TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      last_seen_at TIMESTAMP NULL,
      requests_count BIGINT NOT NULL DEFAULT 0,
      daily_limit INT NULL,
      monthly_limit INT NULL,
      daily_tokens_limit INT NOT NULL DEFAULT 0,
      monthly_tokens_limit INT NOT NULL DEFAULT 0,
      is_pro TINYINT(1) NOT NULL DEFAULT 0,
      memory_enabled TINYINT(1) NOT NULL DEFAULT 1,
      ui_lang VARCHAR(8) NULL,
      model VARCHAR(64) NULL,
      system_prompt MEDIUMTEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_logs (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      tg_user_id BIGINT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      model VARCHAR(64) NOT NULL,
      temperature DECIMAL(4,3) NULL,
      max_output_tokens INT NULL,
      prompt_text MEDIUMTEXT NOT NULL,
      response_text MEDIUMTEXT NULL,
      status VARCHAR(32) NULL,
      error_text TEXT NULL,
      latency_ms INT NULL,
      input_tokens INT NULL,
      output_tokens INT NULL,
      total_tokens INT NULL,
      INDEX idx_user_time (tg_user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      role VARCHAR(16) NOT NULL,
      content MEDIUMTEXT NOT NULL,
      tokens_total INT NOT NULL DEFAULT 0,
      thread_id INT NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_user_created (user_id, created_at),
      KEY idx_user_thread (user_id, thread_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensure_schema_v3(PDO $pdo) {

    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      role VARCHAR(16) NOT NULL,
      content MEDIUMTEXT NOT NULL,
      tokens_total INT NOT NULL DEFAULT 0,
      thread_id INT NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_user_created (user_id, created_at),
      KEY idx_user_thread (user_id, thread_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");


    $cols = $pdo->query('SHOW COLUMNS FROM tg_users')->fetchAll(PDO::FETCH_COLUMN, 0);
    $need = [
        'daily_tokens_limit' => 'ALTER TABLE tg_users ADD COLUMN daily_tokens_limit INT NOT NULL DEFAULT 0',
        'monthly_tokens_limit' => 'ALTER TABLE tg_users ADD COLUMN monthly_tokens_limit INT NOT NULL DEFAULT 0',
        'is_pro' => 'ALTER TABLE tg_users ADD COLUMN is_pro TINYINT(1) NOT NULL DEFAULT 0',
        'memory_enabled' => 'ALTER TABLE tg_users ADD COLUMN memory_enabled TINYINT(1) NOT NULL DEFAULT 1',
        'ui_lang' => 'ALTER TABLE tg_users ADD COLUMN ui_lang VARCHAR(8) NULL'
    ];
    foreach ($need as $c=>$sql) {
        if (!in_array($c, $cols, true)) $pdo->exec($sql);
    }


    $cols2 = $pdo->query('SHOW COLUMNS FROM ai_logs')->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('total_tokens', $cols2, true)) {
        $pdo->exec('ALTER TABLE ai_logs ADD COLUMN total_tokens INT NULL');
    }
}

function tokens_sum_since(PDO $pdo, $userId, $dt) {
    $st = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(total_tokens, COALESCE(input_tokens,0)+COALESCE(output_tokens,0))),0) s
        FROM ai_logs WHERE tg_user_id=? AND created_at >= ?");
    $st->execute([(int)$userId, $dt]);
    return (int)$st->fetchColumn();
}

function is_post() {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function now_ymd() {
    return date('Y-m-d');
}

function now_ym() {
    return date('Y-m');
}
