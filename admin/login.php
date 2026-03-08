<?php
require_once __DIR__ . '/../app/util.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @ini_set('session.cookie_httponly', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        @ini_set('session.cookie_secure', '1');
    }
    @ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

$err = '';
if (is_post()) {
    try { csrf_check(); } catch (Throwable $e) { $err = 'CSRF check failed'; }
    if ($err !== '') {

    } else {
    $u = trim((string)($_POST['username'] ?? ''));
    $p = (string)($_POST['password'] ?? '');
    $adminUser = setting_get('admin_username','admin');
    $hash = setting_get('admin_password_hash','');
    if ($u === $adminUser && $hash && password_verify($p, $hash)) {
        $_SESSION['admin_ok'] = 1;
        header("Location: /admin/settings.php");
        exit;
    }
    $err = "Неверный логин или пароль";
    }
}
?><!doctype html><html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin login</title>
<style>
body{font-family:Arial,sans-serif;background:#f6f7fb;margin:0}
.box{max-width:420px;margin:90px auto;background:#fff;border:1px solid #e5e7ef;border-radius:14px;padding:16px}
label{display:block;font-size:12px;color:#444;margin:6px 0 4px}
input{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #d9ddea;border-radius:10px;font-size:14px}
.btn{padding:10px 14px;border-radius:10px;border:0;background:#3b82f6;color:#fff;font-weight:700;cursor:pointer;width:100%}
.err{color:#b00;font-weight:700;margin:10px 0}
</style></head><body>
<div class="box">
<h2>Admin</h2>
<?php if($err): ?><div class="err"><?=h($err)?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
<label>Username</label><input name="username" value="">
<label>Password</label><input name="password" type="password" value="">
<div style="margin-top:12px"><button class="btn" type="submit">Войти</button></div>
</form>
</div></body></html>
