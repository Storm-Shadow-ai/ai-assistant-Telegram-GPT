<?php
require_once __DIR__ . '/../app/util.php';
require_admin();
$textModels = ['gpt-5','gpt-5-mini','gpt-5-nano','gpt-4o','gpt-4o-mini','gpt-4.1','gpt-4.1-mini','gpt-4.1-nano'];

$pdo = db();
try { if (function_exists('ensure_schema_base')) ensure_schema_base($pdo); if (function_exists('ensure_schema_v3')) ensure_schema_v3($pdo); } catch (Throwable $e) {}

$ok=''; $err='';

if (is_post()) {
    try {
        csrf_check();
        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0) throw new Exception("bad id");
        $is_blocked = isset($_POST['is_blocked']) ? 1 : 0;
        $is_pro = isset($_POST['is_pro']) ? 1 : 0;
        $memory_enabled = isset($_POST['memory_enabled']) ? 1 : 0;
        $daily = trim((string)($_POST['daily_limit'] ?? ''));
        $monthly = trim((string)($_POST['monthly_limit'] ?? ''));
        $dailyTok = trim((string)($_POST['daily_tokens_limit'] ?? ''));
        $monthlyTok = trim((string)($_POST['monthly_tokens_limit'] ?? ''));
        $model = trim((string)($_POST['model'] ?? ''));
        $sys = trim((string)($_POST['system_prompt'] ?? ''));
        $dailyTokVal = ($dailyTok==='') ? 0 : (int)$dailyTok;
        $monthlyTokVal = ($monthlyTok==='') ? 0 : (int)$monthlyTok;

        $dailyVal = ($daily==='') ? null : (int)$daily;
        $monthlyVal = ($monthly==='') ? null : (int)$monthly;
        if ($model !== '' && !in_array($model, $textModels, true)) {
            throw new Exception("Недопустимая модель");
        }
        $modelVal = ($model==='') ? null : $model;
        $sysVal = ($sys==='') ? null : $sys;

        $st = $pdo->prepare("UPDATE tg_users SET is_blocked=?, is_pro=?, memory_enabled=?, daily_limit=?, monthly_limit=?, daily_tokens_limit=?, monthly_tokens_limit=?, model=?, system_prompt=? WHERE id=?");
        $st->execute([$is_blocked, $is_pro, $memory_enabled, $dailyVal, $monthlyVal, $dailyTokVal, $monthlyTokVal, $modelVal, $sysVal, $id]);
        $ok="Сохранено для user_id={$id}";
    } catch (Exception $e) {
        $err=$e->getMessage();
    }
}

$users = $pdo->query("SELECT * FROM tg_users ORDER BY last_seen_at DESC, created_at DESC LIMIT 500")->fetchAll();

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit = null;
if ($editId>0) {
    $st = $pdo->prepare("SELECT * FROM tg_users WHERE id=?");
    $st->execute([$editId]);
    $edit = $st->fetch();
}

include __DIR__.'/_layout_top.php';
?>
<h2>Пользователи</h2>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>
<?php if($err): ?><div class="err"><?=h($err)?></div><?php endif; ?>

<?php if($edit): ?>
  <h3>Настройки пользователя: <?=h($edit['id'])?></h3>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?=h($edit['id'])?>">
    <table>
      <tr><td style="width:220px;">Блокировка</td><td><label><input type="checkbox" name="is_blocked" value="1" <?=((int)$edit['is_blocked']===1?'checked':'')?>> Заблокирован</label></td></tr>
      <tr><td>PRO</td><td><label><input type="checkbox" name="is_pro" value="1" <?=((int)($edit['is_pro'] ?? 0)===1?'checked':'')?>> Pro пользователь</label></td></tr>
      <tr><td>Память</td><td><label><input type="checkbox" name="memory_enabled" value="1" <?=((int)($edit['memory_enabled'] ?? 1)===1?'checked':'')?>> Включить память</label></td></tr>
      <tr><td>Лимит запросов в день (пусто = по умолчанию)</td><td><input name="daily_limit" value="<?=h($edit['daily_limit'])?>"></td></tr>
      <tr><td>Лимит запросов в месяц (пусто = по умолчанию)</td><td><input name="monthly_limit" value="<?=h($edit['monthly_limit'])?>"></td></tr>
      <tr><td>Лимит токенов в день (0 = по умолчанию)</td><td><input name="daily_tokens_limit" value="<?=h($edit['daily_tokens_limit'] ?? 0)?>"></td></tr>
      <tr><td>Лимит токенов в месяц (0 = по умолчанию)</td><td><input name="monthly_tokens_limit" value="<?=h($edit['monthly_tokens_limit'] ?? 0)?>"></td></tr>
      <tr><td>Модель (пусто = по умолчанию)</td><td>
<select name="model">
  <option value="" <?=((string)$edit['model']===''?'selected':'')?>>(по умолчанию)</option>
  <?php foreach($textModels as $m): ?>
    <option value="<?=h($m)?>" <?=((string)$edit['model']===(string)$m?'selected':'')?>><?=h($m)?></option>
  <?php endforeach; ?>
</select>
</td></tr>
      <tr><td>System prompt (пусто = по умолчанию)</td><td><textarea name="system_prompt"><?=h($edit['system_prompt'])?></textarea></td></tr>
    </table>
    <div style="margin-top:10px;">
      <button class="btn" type="submit">Сохранить</button>
      <a class="btn2" href="/admin/users.php" style="text-decoration:none;">Отмена</a>
    </div>
  </form>
  <hr>
<?php endif; ?>

<table>
<tr>
  <th>ID</th><th>Username</th><th>Name</th><th>Requests</th><th>Pro</th><th>Tok/D</th><th>Tok/M</th><th>Daily</th><th>Monthly</th><th>Model</th><th>Last seen</th><th>Blocked</th><th></th>
</tr>
<?php foreach($users as $u): ?>
<tr>
  <td><?=h($u['id'])?></td>
  <td><?=h($u['username'])?></td>
  <td><?=h(trim(($u['first_name']??'').' '.($u['last_name']??'')))?></td>
  <td><?=h($u['requests_count'])?></td>
  <td><?=(((int)($u['is_pro'] ?? 0)===1)?'yes':'')?></td>
  <td><?=h($u['daily_tokens_limit'] ?? 0)?></td>
  <td><?=h($u['monthly_tokens_limit'] ?? 0)?></td>
  <td><?=h($u['daily_limit'] ?? '')?></td>
  <td><?=h($u['monthly_limit'] ?? '')?></td>
  <td><?=h($u['model'] ?? '')?></td>
  <td><?=h($u['last_seen_at'] ?? '')?></td>
  <td><?=(((int)($u['is_blocked'] ?? 0)===1)?'yes':'')?></td>
  <td><a href="/admin/users.php?edit=<?=h($u['id'])?>">Настроить</a></td>
</tr>
<?php endforeach; ?>
</table>

<?php include __DIR__.'/_layout_bottom.php'; ?>
