<?php
require_once __DIR__ . '/../app/util.php';
require_admin();

$pdo = db();
$ok = '';
$err = '';

if (is_post()) {
    try {
        csrf_check();
        ensure_schema_base($pdo);
        ensure_schema_v3($pdo);
        $ok = 'Миграция выполнена успешно.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

include __DIR__ . '/_layout_top.php';
?>
<h2>Миграция БД</h2>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>
<?php if($err): ?><div class="err"><?=h($err)?></div><?php endif; ?>

<div class="small" style="margin-bottom:12px;">Кнопка создаёт/обновляет базовые таблицы бота (settings, tg_users, ai_logs, chat_messages) и v3-колонки.</div>
<form method="post">
  <input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
  <button class="btn" type="submit">Выполнить миграцию</button>
</form>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
