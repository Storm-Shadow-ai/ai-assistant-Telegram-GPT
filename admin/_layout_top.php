<!doctype html>
<html lang="ru"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TG GPT Bot Admin</title>
<style>
body{margin:0;background:#f4f7fb;color:#132036;font:14px/1.45 Arial,Helvetica,sans-serif}
.wrap{max-width:1180px;margin:24px auto;background:#fff;border:1px solid #d9e2ef;border-radius:12px;overflow:hidden}
.top{padding:14px 16px;border-bottom:1px solid #d9e2ef;background:#f8fbff}
nav{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
nav a{display:inline-block;padding:8px 10px;border:1px solid #d9e2ef;border-radius:10px;background:#fff;color:#132036;text-decoration:none}
.content{padding:16px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #d9e2ef;padding:8px;vertical-align:top}
th{background:#eef4fb;text-align:left}
input,textarea,select{width:100%;box-sizing:border-box;padding:9px;border:1px solid #c9d5e7;border-radius:8px}
textarea{min-height:86px;resize:vertical}
.btn{padding:10px 12px;border:0;border-radius:10px;background:#0f766e;color:#fff;font-weight:700;cursor:pointer}
.btn2{padding:10px 12px;border:1px solid #d9e2ef;border-radius:10px;background:#fff;color:#132036;cursor:pointer}
.ok{padding:10px;border-radius:8px;background:#eafaf1;color:#0f5132;margin-bottom:10px}
.err{padding:10px;border-radius:8px;background:#fdecef;color:#842029;margin-bottom:10px}
.small{font-size:12px;color:#64748b}
@media (max-width:760px){.wrap{margin:10px}.content,.top{padding:10px}}
</style>
</head><body>
<div class="wrap">
  <div class="top">
    <div><b>TG GPT Bot Admin</b></div>
    <nav>
      <a href="/admin/settings.php">Настройки</a>
      <a href="/admin/users.php">Пользователи</a>
      <a href="/admin/logs.php">Логи</a>
      <a href="/admin/migrate.php">Миграция БД</a>
      <a href="/admin/logout.php">Выход</a>
    </nav>
  </div>
  <div class="content">
