<?php
require_once __DIR__ . '/../app/util.php';
require_once __DIR__ . '/../app/telegram.php';

require_admin();
$pdo = db();
try { if (function_exists('ensure_schema_base')) ensure_schema_base($pdo); if (function_exists('ensure_schema_v3')) ensure_schema_v3($pdo); } catch (Throwable $e) {}
$ok=''; $err=''; $webhookRes=null;

if (is_post()) {
    try {
        csrf_check();
        $botToken = trim((string)($_POST['bot_token'] ?? ''));
        $openaiKey = trim((string)($_POST['openai_api_key'] ?? ''));
        $proxy = trim((string)($_POST['proxy_url'] ?? ''));
        $allowedTextModels = ['gpt-5','gpt-5-mini','gpt-5-nano','gpt-4o','gpt-4o-mini','gpt-4.1','gpt-4.1-mini','gpt-4.1-nano'];
        $allowedImageModels = ['gpt-image-1.5','gpt-image-1','gpt-image-1-mini','dall-e-3','dall-e-2'];
        $allowedImageSizes = ['1024x1024','1024x1536','1536x1024'];

        $model = trim((string)($_POST['openai_model'] ?? 'gpt-4o-mini'));
        if (!in_array($model, $allowedTextModels, true)) $model = 'gpt-4o-mini';
        $imageModel = trim((string)($_POST['image_model'] ?? 'gpt-image-1'));
        if (!in_array($imageModel, $allowedImageModels, true)) $imageModel = 'gpt-image-1';
        $imageSize = trim((string)($_POST['image_size'] ?? '1024x1024'));
        if (!in_array($imageSize, $allowedImageSizes, true)) $imageSize = '1024x1024';
        $temperature = trim((string)($_POST['temperature'] ?? '0.7'));
        $maxOut = trim((string)($_POST['max_output_tokens'] ?? '800'));
        $daily = trim((string)($_POST['default_daily_limit'] ?? '50'));
        $monthly = trim((string)($_POST['default_monthly_limit'] ?? '1000'));
        $sys = trim((string)($_POST['default_system_prompt'] ?? ''));

        $whSecret = trim((string)($_POST['tg_webhook_secret'] ?? ''));
        $whPathToken = trim((string)($_POST['tg_webhook_path_token'] ?? ''));

        $memEnabled = isset($_POST['memory_enabled']) ? '1' : '0';
        $memMax = trim((string)($_POST['memory_max_messages'] ?? '30'));

        $dailyTok = trim((string)($_POST['default_daily_tokens_limit'] ?? '0'));
        $monthlyTok = trim((string)($_POST['default_monthly_tokens_limit'] ?? '0'));

        $proMode = isset($_POST['pro_mode_enabled']) ? '1' : '0';
        $proUnlimited = isset($_POST['pro_unlimited_tokens']) ? '1' : '0';
        $proModel = trim((string)($_POST['pro_model'] ?? ''));
        if ($proModel !== '' && !in_array($proModel, $allowedTextModels, true)) $proModel = '';

        if ($botToken==='') throw new Exception("bot_token пустой");
        if ($openaiKey==='') throw new Exception("openai_api_key пустой");

        setting_set('bot_token', $botToken);
        setting_set('openai_api_key', $openaiKey);
        setting_set('proxy_url', $proxy);
        setting_set('openai_model', $model);
        setting_set('image_model', $imageModel);
        setting_set('image_size', $imageSize);
        setting_set('temperature', $temperature);
        setting_set('max_output_tokens', $maxOut);
        setting_set('default_daily_limit', $daily);
        setting_set('default_monthly_limit', $monthly);
        setting_set('default_system_prompt', $sys);

        setting_set('tg_webhook_secret', $whSecret);
        setting_set('tg_webhook_path_token', $whPathToken);

        setting_set('memory_enabled', $memEnabled);
        setting_set('memory_max_messages', $memMax);

        setting_set('default_daily_tokens_limit', $dailyTok);
        setting_set('default_monthly_tokens_limit', $monthlyTok);

        setting_set('pro_mode_enabled', $proMode);
        setting_set('pro_unlimited_tokens', $proUnlimited);
        setting_set('pro_model', $proModel);

        if (!empty($_POST['webhook_url']) && ($_POST['set_webhook'] ?? '') === '1') {
            $webhookUrl = trim((string)$_POST['webhook_url']);
            
            if ($whPathToken !== '') {
                $sep = (strpos($webhookUrl, '?') === false) ? '?' : '&';
                $webhookUrl = $webhookUrl . $sep . 't=' . rawurlencode($whPathToken);
            }
            $webhookRes = tg_set_webhook($webhookUrl, $botToken, $whSecret);
        }

        $ok = "Сохранено";
    } catch (Exception $e) {
        $err = $e->getMessage();
    }
}

$data = [
    'bot_token' => setting_get('bot_token',''),
    'openai_api_key' => setting_get('openai_api_key',''),
    'proxy_url' => setting_get('proxy_url',''),
    'openai_model' => setting_get('openai_model','gpt-4o-mini'),
    'temperature' => setting_get('temperature','0.7'),
    'max_output_tokens' => setting_get('max_output_tokens','800'),
    'default_daily_limit' => setting_get('default_daily_limit','50'),
    'default_monthly_limit' => setting_get('default_monthly_limit','1000'),
    'default_system_prompt' => setting_get('default_system_prompt',''),

    'tg_webhook_secret' => setting_get('tg_webhook_secret',''),
    'tg_webhook_path_token' => setting_get('tg_webhook_path_token',''),

    'memory_enabled' => setting_get('memory_enabled','1'),
    'memory_max_messages' => setting_get('memory_max_messages','30'),

    'default_daily_tokens_limit' => setting_get('default_daily_tokens_limit','0'),
    'default_monthly_tokens_limit' => setting_get('default_monthly_tokens_limit','0'),

    'pro_mode_enabled' => setting_get('pro_mode_enabled','1'),
    'pro_unlimited_tokens' => setting_get('pro_unlimited_tokens','1'),
    'pro_model' => setting_get('pro_model',''),
];


$textModels = [
  'gpt-5','gpt-5-mini','gpt-5-nano',
  'gpt-4o','gpt-4o-mini',
  'gpt-4.1','gpt-4.1-mini','gpt-4.1-nano'
];
$imageModels = [
  'gpt-image-1.5','gpt-image-1','gpt-image-1-mini',
  'dall-e-3','dall-e-2'
];
$imageSizes = ['1024x1024','1024x1536','1536x1024'];

function opt_selected($val, $cur) { return ((string)$val === (string)$cur) ? 'selected' : ''; }

include __DIR__.'/_layout_top.php';
?>
<h2>Настройки</h2>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>
<?php if($err): ?><div class="err"><?=h($err)?></div><?php endif; ?>

<form method="post">
<input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">

<table>
<tr><th style="width:260px;">Параметр</th><th>Значение</th></tr>

<tr><td colspan="2" style="background:#f6f7f9;font-weight:600;">Telegram</td></tr>
<tr>
  <td>Токен Telegram-бота</td>
  <td>
    <input name="bot_token" value="<?=h($data['bot_token'])?>" placeholder="123456:ABC-DEF...">
    <div class="small">Токен из BotFather. Нужен для отправки/получения сообщений.</div>
  </td>
</tr>

<tr><td colspan="2" style="background:#f6f7f9;font-weight:600;">OpenAI (текст)</td></tr>
<tr>
  <td>API-ключ OpenAI</td>
  <td>
    <input name="openai_api_key" value="<?=h($data['openai_api_key'])?>" placeholder="sk-...">
    <div class="small">Ключ OpenAI API. Храни в секрете.</div>
  </td>
</tr>
<tr>
  <td>Прокси (опционально)</td>
  <td>
    <input name="proxy_url" value="<?=h($data['proxy_url'])?>" placeholder="user:pass@host:port">
    <div class="small">Формат: <code>user:pass@ip:port</code> или <code>ip:port</code>. Оставь пустым, если прокси не нужен.</div>
  </td>
</tr>
<tr>
  <td>Модель по умолчанию</td>
  <td>
    <select name="openai_model">
      <?php foreach($textModels as $m): ?>
        <option value="<?=h($m)?>" <?=opt_selected($m, $data['openai_model'])?>><?=h($m)?></option>
      <?php endforeach; ?>
    </select>
    <div class="small">Модель для обычных сообщений (чат).</div>
  </td>
</tr>
<tr>
  <td>Креативность (Temperature)</td>
  <td>
    <input name="temperature" type="number" step="0.1" min="0" max="2" value="<?=h($data['temperature'])?>">
    <div class="small">
      0.0–0.3 — строгие ответы · 0.4–0.7 — баланс · 0.8–1.2 — более креативно
    </div>
  </td>
</tr>
<tr>
  <td>Максимум токенов ответа</td>
  <td>
    <input name="max_output_tokens" type="number" min="1" value="<?=h($data['max_output_tokens'])?>">
    <div class="small">Ограничивает длину ответа. 1 токен ≈ 3–4 символа.</div>
  </td>
</tr>

<tr><td colspan="2" style="background:#f6f7f9;font-weight:600;">Лимиты</td></tr>
<tr>
  <td>Лимит запросов в день</td>
  <td>
    <input name="default_daily_limit" type="number" min="0" value="<?=h($data['default_daily_limit'])?>">
    <div class="small">0 = без ограничения. Используется только если лимиты токенов выключены.</div>
  </td>
</tr>
<tr>
  <td>Лимит запросов в месяц</td>
  <td>
    <input name="default_monthly_limit" type="number" min="0" value="<?=h($data['default_monthly_limit'])?>">
    <div class="small">0 = без ограничения. Используется только если лимиты токенов выключены.</div>
  </td>
</tr>
<tr>
  <td>Лимит токенов в день</td>
  <td>
    <input name="default_daily_tokens_limit" type="number" min="0" value="<?=h($data['default_daily_tokens_limit'])?>">
    <div class="small">0 = без ограничения. При включении токен-лимитов лимиты по запросам игнорируются.</div>
  </td>
</tr>
<tr>
  <td>Лимит токенов в месяц</td>
  <td>
    <input name="default_monthly_tokens_limit" type="number" min="0" value="<?=h($data['default_monthly_tokens_limit'])?>">
    <div class="small">0 = без ограничения.</div>
  </td>
</tr>

<tr><td colspan="2" style="background:#f6f7f9;font-weight:600;">Память диалога</td></tr>
<tr>
  <td>Память включена</td>
  <td>
    <label><input type="checkbox" name="memory_enabled" value="1" <?=((string)$data['memory_enabled']==='1'?'checked':'')?>> Да</label>
    <div class="small">Если включено — бот учитывает историю (последние сообщения).</div>
  </td>
</tr>
<tr>
  <td>Глубина памяти (сообщений)</td>
  <td>
    <input name="memory_max_messages" type="number" min="1" value="<?=h($data['memory_max_messages'])?>">
    <div class="small">Сколько последних сообщений подмешивать в контекст (обычно 20–40).</div>
  </td>
</tr>

<tr><td colspan="2" style="background:#f6f7f9;font-weight:600;">Системный промпт</td></tr>
<tr>
  <td>Промпт по умолчанию</td>
  <td>
    <textarea name="default_system_prompt" rows="5" placeholder="Инструкции для ассистента..."><?=h($data['default_system_prompt'])?></textarea>
    <div class="small">Задаёт стиль и правила поведения ассистента. Можно переопределять на уровне пользователя.</div>
  </td>
</tr>

<tr><td colspan="2" style="background:#f6f7f9;font-weight:600;">Безопасность Webhook</td></tr>
<tr>
  <td>Webhook secret (header)</td>
  <td>
    <input name="tg_webhook_secret" value="<?=h($data['tg_webhook_secret'])?>" placeholder="sec_...">
    <div class="small">Telegram будет отправлять заголовок <code>X-Telegram-Bot-Api-Secret-Token</code>. Разрешены латиница/цифры/</code>_</code>/<code>-</code>.</div>
  </td>
</tr>
<tr>
  <td>Webhook path token (URL ?t=)</td>
  <td>
    <input name="tg_webhook_path_token" value="<?=h($data['tg_webhook_path_token'])?>" placeholder="t_...">
    <div class="small">Только токен (не URL). В webhook добавится как <code>?t=TOKEN</code>.</div>
  </td>
</tr>

<tr><td colspan="2" style="background:#f6f7f9;font-weight:600;">Pro режим</td></tr>
<tr><td>Pro mode enabled</td><td><label><input type="checkbox" name="pro_mode_enabled" value="1" <?=((string)$data['pro_mode_enabled']==='1'?'checked':'')?>> Да</label></td></tr>
<tr><td>Pro unlimited tokens</td><td><label><input type="checkbox" name="pro_unlimited_tokens" value="1" <?=((string)$data['pro_unlimited_tokens']==='1'?'checked':'')?>> Да</label></td></tr>
<tr>
  <td>Pro модель (опционально)</td>
  <td>
    <select name="pro_model">
      <option value="" <?=opt_selected('', $data['pro_model'])?>>(не задано)</option>
      <?php foreach($textModels as $m): ?>
        <option value="<?=h($m)?>" <?=opt_selected($m, $data['pro_model'])?>><?=h($m)?></option>
      <?php endforeach; ?>
    </select>
    <div class="small">Если выбрано — Pro пользователи будут работать на этой модели.</div>
  </td>
</tr>

</table>


<h3>Webhook</h3>
<div class="small">Если надо — установи webhook прямо отсюда.</div>
<div style="display:grid; grid-template-columns: 1fr auto; gap:10px; align-items:center; margin-top:8px;">
  <input name="webhook_url" value="" placeholder="https://yourdomain.ru/webhook.php">
  <label style="white-space:nowrap;"><input type="checkbox" name="set_webhook" value="1"> Установить</label>
</div>

<div style="margin-top:12px;"><button class="btn" type="submit">Сохранить</button></div>
</form>

<?php if($webhookRes!==null): ?>
  <h3>Ответ setWebhook</h3>
  <pre><?=h(json_encode($webhookRes, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre>
<?php endif; ?>

<?php include __DIR__.'/_layout_bottom.php'; ?>
