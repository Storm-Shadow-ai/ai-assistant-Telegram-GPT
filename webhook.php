<?php
require_once __DIR__ . '/app/util.php';
require_once __DIR__ . '/app/telegram.php';
require_once __DIR__ . '/app/openai.php';

function bot_tr($lang, $ru, $en) {
    return ($lang === 'en') ? $en : $ru;
}

$cfg = load_config();
if (!$cfg) { http_response_code(500); echo "not installed"; exit; }

$pdo = db();

try { if (function_exists('ensure_schema_base')) ensure_schema_base($pdo); if (function_exists('ensure_schema_v3')) ensure_schema_v3($pdo); } catch (Throwable $e) {  }

$botToken = (string)setting_get('bot_token','');
if ($botToken==='') { http_response_code(500); echo "bot token missing"; exit; }


$secret = trim((string)setting_get('tg_webhook_secret',''));
$pathToken = trim((string)setting_get('tg_webhook_path_token',''));

if ($pathToken !== '') {
    $t = (string)($_GET['t'] ?? '');
    if (!hash_equals($pathToken, $t)) {
        http_response_code(403);
        echo 'forbidden';
        exit;
    }
}
if ($secret !== '') {
    $hdr = get_header_value('X-Telegram-Bot-Api-Secret-Token');
    if ($hdr === '' || !hash_equals($secret, $hdr)) {
        http_response_code(403);
        echo 'forbidden';
        exit;
    }
}

$raw = file_get_contents('php://input');
$upd = json_decode($raw, true);
if (!is_array($upd)) { echo "ok"; exit; }

$msg = $upd['message'] ?? null;
if (!$msg) { echo "ok"; exit; }

$chatId = $msg['chat']['id'] ?? null;
$from = $msg['from'] ?? null;
$textRaw = trim((string)($msg['text'] ?? ''));
$captionRaw = trim((string)($msg['caption'] ?? ''));
$text = ($textRaw !== '') ? $textRaw : $captionRaw;

if (!$chatId || !$from) { echo "ok"; exit; }

$userId = (int)($from['id'] ?? 0);
$username = $from['username'] ?? null;
$first = $from['first_name'] ?? null;
$last = $from['last_name'] ?? null;

if ($userId <= 0) { echo "ok"; exit; }
$lang = (stripos((string)($from['language_code'] ?? ''), 'en') === 0) ? 'en' : 'ru';
try {
    $stLang = $pdo->prepare("SELECT ui_lang FROM tg_users WHERE id=? LIMIT 1");
    $stLang->execute([$userId]);
    $dbLang = (string)$stLang->fetchColumn();
    if ($dbLang === 'ru' || $dbLang === 'en') $lang = $dbLang;
} catch (Throwable $e) {}
$mainKb = ['reply_markup' => tg_main_keyboard($lang)];

$upsertUser = function($langForInsert = null) use ($pdo, $userId, $username, $first, $last) {
    $st = $pdo->prepare("INSERT INTO tg_users(id,username,first_name,last_name,last_seen_at,ui_lang) VALUES(?,?,?,?,NOW(),?)
        ON DUPLICATE KEY UPDATE username=VALUES(username), first_name=VALUES(first_name), last_name=VALUES(last_name), last_seen_at=NOW(), ui_lang=COALESCE(ui_lang, VALUES(ui_lang))");
    $st->execute([$userId, $username, $first, $last, $langForInsert]);
};


if ($text === '/start') {
    tg_send_message($chatId, bot_tr($lang,
        "Привет! Я готов помочь.\n/new — новый диалог\n/help — помощь\n/model — выбрать модель\nЯзык: Русский/English",
        "Hi! I am ready to help.\n/new - new chat\n/help - help\n/model - choose model\nLanguage: Русский/English"
    ), $botToken, $mainKb);
    echo "ok"; exit;
}
if ($text === '/help' || $text === 'Help' || $text === 'Помощь') {
    tg_send_message($chatId, bot_tr($lang,
        "Команды:\n/start - показать меню\n/new - очистить историю\n/model - выбор модели",
        "Commands:\n/start - show menu\n/new - clear chat history\n/model - show model commands"
    ), $botToken, $mainKb);
    echo "ok"; exit;
}
if ($text === '/model') {
    tg_send_message($chatId, bot_tr($lang,
        "Выбери модель кнопкой: Модель GPT-5 / Модель GPT-5 mini / Модель GPT-4o mini",
        "Choose model from keyboard: Model GPT-5 / Model GPT-5 mini / Model GPT-4o mini"
    ), $botToken, $mainKb);
    echo "ok"; exit;
}
if ($text === 'Новый диалог' || $text === 'New chat') {
    $text = '/new';
}
if ($text === 'Русский' || $text === 'English') {
    $lang = ($text === 'English') ? 'en' : 'ru';
    $mainKb = ['reply_markup' => tg_main_keyboard($lang)];
    $upsertUser($lang);
    $pdo->prepare("UPDATE tg_users SET ui_lang=? WHERE id=?")->execute([$lang, $userId]);
    tg_send_message($chatId, bot_tr($lang, "Язык переключен на русский.", "Language switched to English."), $botToken, $mainKb);
    echo "ok"; exit;
}

$modelButtons = [
    'Model GPT-5' => 'gpt-5',
    'Model GPT-5 mini' => 'gpt-5-mini',
    'Model GPT-4o mini' => 'gpt-4o-mini',
    'Модель GPT-5' => 'gpt-5',
    'Модель GPT-5 mini' => 'gpt-5-mini',
    'Модель GPT-4o mini' => 'gpt-4o-mini'
];
if (isset($modelButtons[$text])) {
    $selectedModel = $modelButtons[$text];
    $upsertUser($lang);
    $pdo->prepare("UPDATE tg_users SET model=? WHERE id=?")->execute([$selectedModel, $userId]);
    tg_send_message($chatId, bot_tr($lang, "Модель установлена: ", "Model set: ") . $selectedModel, $botToken, $mainKb);
    echo "ok"; exit;
}
if ($text === '/new') {
    try {
        $pdo->prepare("DELETE FROM chat_messages WHERE user_id=?")->execute([$userId]);
    } catch (Throwable $e) {}
    tg_send_message($chatId, bot_tr($lang, "Ок. Новый диалог.", "OK. New chat."), $botToken);
    echo "ok"; exit;
}



$hasPhoto = isset($msg['photo']) && is_array($msg['photo']) && count($msg['photo'])>0;


$cmd = $text;

if (strpos($cmd, '/img') === 0 || strpos($cmd, '/edit') === 0 || $text === 'Сгенерировать изображение' || $text === 'Image generate' || $text === 'Редактировать фото' || $text === 'Image edit') {
    tg_send_message($chatId, bot_tr($lang, "Функции генерации и редактирования изображений отключены.", "Image generation and editing are disabled."), $botToken, $mainKb);
    echo "ok"; exit;
}


if (strpos($cmd, '/img') === 0) {
    $prompt = trim(preg_replace('/^\/img\s*/u', '', $cmd));
    if ($prompt === '') {
        tg_send_message($chatId, bot_tr($lang, "Команда: /img <описание>", "Command: /img <prompt>"), $botToken);
        echo "ok"; exit;
    }

    $apiKey = (string)setting_get('openai_api_key','');
    if ($apiKey==='') { tg_send_message($chatId, bot_tr($lang, "OpenAI API ключ не настроен в админке.", "OpenAI API key is not configured in admin."), $botToken); echo "ok"; exit; }

    $proxy = (string)setting_get('proxy_url','');
    $imageModel = (string)setting_get('image_model','gpt-image-1');
    $imageSize = (string)setting_get('image_size','1024x1024');

    $t0 = microtime(true);
    
    $st = $pdo->prepare("INSERT INTO ai_logs(tg_user_id, model, temperature, max_output_tokens, prompt_text, status) VALUES(?,?,?,?,?,?)");
    $st->execute([$userId, $imageModel, 0, 0, $prompt, 'started']);
    $logId = (int)$pdo->lastInsertId();

    $resImg = openai_image_generate($prompt, [
        'api_key'=>$apiKey,
        'model'=>$imageModel,
        'size'=>$imageSize,
        'proxy'=>$proxy
    ]);
    $ms = (int)round((microtime(true) - $t0) * 1000);

    if (!$resImg['ok']) {
        $errMsg = (string)($resImg['error'] ?? '');
        if ($errMsg === '') $errMsg = substr((string)($resImg['raw'] ?? 'image error'), 0, 500);
        $pdo->prepare("UPDATE ai_logs SET response_text=?, status=?, latency_ms=? WHERE id=?")
            ->execute([$errMsg, 'error', $ms, $logId]);
        tg_send_message($chatId, bot_tr($lang, "Не удалось сгенерировать изображение. Ошибка: ", "Image generation failed. Error: ").$errMsg, $botToken);
        echo "ok"; exit;
    }

    $usage = $resImg['usage'] ?? ['input'=>0,'output'=>0,'total'=>0];
    $pdo->prepare("UPDATE ai_logs SET status=?, latency_ms=?, input_tokens=?, output_tokens=?, total_tokens=? WHERE id=?")
        ->execute(['ok', $ms, (int)$usage['input'], (int)$usage['output'], (int)$usage['total'], $logId]);

    tg_send_photo_bytes($chatId, $resImg['bytes'], $botToken, "");
    echo "ok"; exit;
}


if ($hasPhoto && strpos($cmd, '/edit') === 0) {
    $prompt = trim(preg_replace('/^\/edit\s*/u', '', $cmd));
    if ($prompt === '') {
        tg_send_message($chatId, bot_tr($lang, "Отправь фото с подписью: /edit <что изменить>", "Send photo with caption: /edit <what to change>"), $botToken);
        echo "ok"; exit;
    }

    $apiKey = (string)setting_get('openai_api_key','');
    if ($apiKey==='') { tg_send_message($chatId, bot_tr($lang, "OpenAI API ключ не настроен в админке.", "OpenAI API key is not configured in admin."), $botToken); echo "ok"; exit; }

    $proxy = (string)setting_get('proxy_url','');
    $imageModel = (string)setting_get('image_model','gpt-image-1');
    $imageSize = (string)setting_get('image_size','1024x1024');
    $editModel = $imageModel;
    $editSupportedModels = ['gpt-image-1.5','gpt-image-1','gpt-image-1-mini','dall-e-2'];
    if (!in_array($editModel, $editSupportedModels, true)) {
        $editModel = 'gpt-image-1';
    }

    
    $photos = $msg['photo'];
    $ph = end($photos);
    $fileId = $ph['file_id'] ?? '';
    if ($fileId === '') { tg_send_message($chatId, bot_tr($lang, "Не удалось получить фото.", "Failed to read photo."), $botToken); echo "ok"; exit; }

    $fileInfo = tg_get_file($fileId, $botToken);
    $filePath = $fileInfo['result']['file_path'] ?? '';
    if ($filePath === '') { tg_send_message($chatId, bot_tr($lang, "Не удалось получить путь файла из Telegram.", "Failed to get file path from Telegram."), $botToken); echo "ok"; exit; }

    $dl = tg_download_file($filePath, $botToken);
    if (!$dl['ok']) { tg_send_message($chatId, bot_tr($lang, "Не удалось скачать фото.", "Failed to download photo."), $botToken); echo "ok"; exit; }

    $t0 = microtime(true);
    $st = $pdo->prepare("INSERT INTO ai_logs(tg_user_id, model, temperature, max_output_tokens, prompt_text, status) VALUES(?,?,?,?,?,?)");
    $st->execute([$userId, $editModel, 0, 0, $prompt, 'started']);
    $logId = (int)$pdo->lastInsertId();

    $resImg = openai_image_edit($dl['bytes'], $prompt, [
        'api_key'=>$apiKey,
        'model'=>$editModel,
        'size'=>$imageSize,
        'proxy'=>$proxy
    ]);
    $ms = (int)round((microtime(true) - $t0) * 1000);

    if (!$resImg['ok']) {
        $errMsg = (string)($resImg['error'] ?? '');
        if ($errMsg === '') $errMsg = substr((string)($resImg['raw'] ?? 'image error'), 0, 500);
        $pdo->prepare("UPDATE ai_logs SET response_text=?, status=?, latency_ms=? WHERE id=?")
            ->execute([$errMsg, 'error', $ms, $logId]);
        tg_send_message($chatId, bot_tr($lang, "Не удалось отредактировать изображение. Ошибка: ", "Image edit failed. Error: ").$errMsg, $botToken);
        echo "ok"; exit;
    }

    $usage = $resImg['usage'] ?? ['input'=>0,'output'=>0,'total'=>0];
    $pdo->prepare("UPDATE ai_logs SET status=?, latency_ms=?, input_tokens=?, output_tokens=?, total_tokens=? WHERE id=?")
        ->execute(['ok', $ms, (int)$usage['input'], (int)$usage['output'], (int)$usage['total'], $logId]);

    tg_send_photo_bytes($chatId, $resImg['bytes'], $botToken, "");
    echo "ok"; exit;
}


if ($hasPhoto && $text === '') {
    tg_send_message($chatId, bot_tr($lang, "Обработка изображений отключена.", "Image processing is disabled."), $botToken);
    echo "ok"; exit;
}

if ($text === '') { echo "ok"; exit; }


$upsertUser($lang);

$st = $pdo->prepare("SELECT * FROM tg_users WHERE id=? LIMIT 1");
$st->execute([$userId]);
$u = $st->fetch();
if (!$u) { echo "ok"; exit; }
if (($u['ui_lang'] ?? '') === 'ru' || ($u['ui_lang'] ?? '') === 'en') {
    $lang = (string)$u['ui_lang'];
}

if ((int)($u['is_blocked'] ?? 0) === 1) { echo "ok"; exit; }


$apiKey = (string)setting_get('openai_api_key','');
$proxy = (string)setting_get('proxy_url','');

$modelDefault = (string)setting_get('openai_model','gpt-4o-mini');
$proModeEnabled = setting_get_int('pro_mode_enabled', 1) === 1;
$proUnlimited = setting_get_int('pro_unlimited_tokens', 1) === 1;
$proModel = trim((string)setting_get('pro_model',''));

$isPro = ((int)($u['is_pro'] ?? 0) === 1);
$model = $u['model'] ? (string)$u['model'] : $modelDefault;
if ($proModeEnabled && $isPro && $proModel !== '') {
    $model = $proModel;
}

$temperature = (float)setting_get('temperature','0.7');
$maxOut = (int)setting_get('max_output_tokens','800');

$globalPrompt = (string)setting_get('default_system_prompt','');
$userPrompt = $u['system_prompt'] ? (string)$u['system_prompt'] : '';
$systemPrompt = trim($userPrompt !== '' ? $userPrompt : $globalPrompt);


$memoryGlobal = setting_get_int('memory_enabled', 1) === 1;
$memoryMax = setting_get_int('memory_max_messages', 30);
$memoryUser = ((int)($u['memory_enabled'] ?? 1) === 1);
$memoryOn = $memoryGlobal && $memoryUser && $memoryMax > 0;


$defaultDaily = (int)setting_get('default_daily_limit','50');
$defaultMonthly = (int)setting_get('default_monthly_limit','1000');
$dailyLimit = array_key_exists('daily_limit',$u) && $u['daily_limit'] !== null ? (int)$u['daily_limit'] : $defaultDaily;
$monthlyLimit = array_key_exists('monthly_limit',$u) && $u['monthly_limit'] !== null ? (int)$u['monthly_limit'] : $defaultMonthly;


$defaultDailyTok = setting_get_int('default_daily_tokens_limit', 0);
$defaultMonthlyTok = setting_get_int('default_monthly_tokens_limit', 0);
$dailyTokLimit = (int)($u['daily_tokens_limit'] ?? 0);
$monthlyTokLimit = (int)($u['monthly_tokens_limit'] ?? 0);
if ($dailyTokLimit <= 0) $dailyTokLimit = $defaultDailyTok;
if ($monthlyTokLimit <= 0) $monthlyTokLimit = $defaultMonthlyTok;

$today = date('Y-m-d 00:00:00');
$monthStart = date('Y-m-01 00:00:00');


if (!($proModeEnabled && $isPro && $proUnlimited)) {
    if ($dailyTokLimit > 0) {
        $used = tokens_sum_since($pdo, $userId, $today);
        if ($used >= $dailyTokLimit) {
            tg_send_message($chatId, bot_tr($lang, "Лимит токенов на сегодня исчерпан.", "Daily token limit reached."), $botToken);
            echo 'ok'; exit;
        }
    }
    if ($monthlyTokLimit > 0) {
        $used = tokens_sum_since($pdo, $userId, $monthStart);
        if ($used >= $monthlyTokLimit) {
            tg_send_message($chatId, bot_tr($lang, "Лимит токенов на месяц исчерпан.", "Monthly token limit reached."), $botToken);
            echo 'ok'; exit;
        }
    }
}


if (($dailyTokLimit <= 0) && ($monthlyTokLimit <= 0)) {
    $st = $pdo->prepare("SELECT COUNT(*) c FROM ai_logs WHERE tg_user_id=? AND created_at >= ?");
    $st->execute([$userId, $today]);
    $todayCount = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) c FROM ai_logs WHERE tg_user_id=? AND created_at >= ?");
    $st->execute([$userId, $monthStart]);
    $monthCount = (int)$st->fetchColumn();

    if ($dailyLimit > 0 && $todayCount >= $dailyLimit) {
        tg_send_message($chatId, bot_tr($lang, "Лимит запросов на сегодня исчерпан.", "Daily request limit reached."), $botToken);
        echo "ok"; exit;
    }
    if ($monthlyLimit > 0 && $monthCount >= $monthlyLimit) {
        tg_send_message($chatId, bot_tr($lang, "Лимит запросов на месяц исчерпан.", "Monthly request limit reached."), $botToken);
        echo "ok"; exit;
    }
}


$input = $text;
if ($memoryOn) {
    $messages = [];
    if ($systemPrompt !== '') {
        $messages[] = ['role'=>'system','content'=>$systemPrompt];
    }
    
    $st = $pdo->prepare("SELECT role, content FROM chat_messages WHERE user_id=? ORDER BY id DESC LIMIT ?");
    $st->bindValue(1, $userId, PDO::PARAM_INT);
    $st->bindValue(2, $memoryMax, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();
    if ($rows) {
        $rows = array_reverse($rows);
        foreach ($rows as $r) {
            $role = (string)($r['role'] ?? 'user');
            $content = (string)($r['content'] ?? '');
            if ($content === '') continue;
            if (!in_array($role, ['user','assistant','system'], true)) $role = 'user';
            $messages[] = ['role'=>$role, 'content'=>$content];
        }
    }
    $messages[] = ['role'=>'user','content'=>$text];
    $input = $messages;
} else {
    if ($systemPrompt !== '') {
        $input = $systemPrompt . "\n\n" . $text;
    }
}

$t0 = microtime(true);


$st = $pdo->prepare("INSERT INTO ai_logs(tg_user_id, model, temperature, max_output_tokens, prompt_text, status) VALUES(?,?,?,?,?,?)");
$st->execute([$userId, $model, $temperature, $maxOut, $text, 'started']);
$logId = (int)$pdo->lastInsertId();


if ($memoryOn) {
    try {
        $pdo->prepare("INSERT INTO chat_messages(user_id, role, content) VALUES(?,?,?)")
            ->execute([$userId, 'user', $text]);
    } catch (Throwable $e) {}
}

$res = openai_responses_create($input, [
    'api_key' => $apiKey,
    'model' => $model,
    'temperature' => $temperature,
    'max_output_tokens' => $maxOut,
    'proxy' => $proxy
]);

$ms = (int)round((microtime(true) - $t0) * 1000);

if ($res['ok']) {
    $answer = (string)$res['text'];
    $usage = $res['usage'] ?? ['input'=>0,'output'=>0,'total'=>0];

    $pdo->prepare("UPDATE ai_logs SET response_text=?, status=?, latency_ms=?, input_tokens=?, output_tokens=?, total_tokens=? WHERE id=?")
        ->execute([$answer, 'ok', $ms, (int)$usage['input'], (int)$usage['output'], (int)$usage['total'], $logId]);

    $pdo->prepare("UPDATE tg_users SET requests_count=requests_count+1 WHERE id=?")->execute([$userId]);

    if ($memoryOn) {
        try {
            $pdo->prepare("INSERT INTO chat_messages(user_id, role, content, tokens_total) VALUES(?,?,?,?)")
                ->execute([$userId, 'assistant', $answer, (int)$usage['total']]);
        } catch (Throwable $e) {}
    }

    tg_send_message($chatId, $answer, $botToken);
} else {
    $pdo->prepare("UPDATE ai_logs SET status=?, error_text=?, latency_ms=? WHERE id=?")
        ->execute(['error', (string)$res['error'], $ms, $logId]);
    tg_send_message($chatId, bot_tr($lang, "Ошибка AI: ", "AI error: ") . (string)$res['error'], $botToken);
}

echo "ok";
