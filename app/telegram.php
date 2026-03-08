<?php
require_once __DIR__ . '/util.php';

function tg_api($method, $params, $botToken) {
    $url = "https://api.telegram.org/bot{$botToken}/{$method}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $raw = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($raw, true);
    return is_array($json) ? $json : ['ok'=>false, 'raw'=>$raw];
}

function tg_send_message($chatId, $text, $botToken, $extra = []) {
    
    $parts = [];
    $t = (string)$text;
    while (mb_strlen($t, 'UTF-8') > 3900) {
        $parts[] = mb_substr($t, 0, 3900, 'UTF-8');
        $t = mb_substr($t, 3900, null, 'UTF-8');
    }
    $parts[] = $t;

    foreach ($parts as $p) {
        $params = [
            'chat_id' => $chatId,
            'text' => $p
        ];
        if (is_array($extra)) {
            foreach ($extra as $k => $v) {
                $params[$k] = $v;
            }
        }
        tg_api('sendMessage', $params, $botToken);
    }
}

function tg_main_keyboard($lang = 'en') {
    if ($lang === 'ru') {
        $rows = [
            [['text' => 'Новый диалог'], ['text' => 'Помощь']],
            [['text' => 'Модель GPT-5'], ['text' => 'Модель GPT-5 mini'], ['text' => 'Модель GPT-4o mini']],
            [['text' => 'Русский'], ['text' => 'English']]
        ];
    } else {
        $rows = [
            [['text' => 'New chat'], ['text' => 'Help']],
            [['text' => 'Model GPT-5'], ['text' => 'Model GPT-5 mini'], ['text' => 'Model GPT-4o mini']],
            [['text' => 'Русский'], ['text' => 'English']]
        ];
    }

    return json_encode([
        'keyboard' => $rows,
        'resize_keyboard' => true,
        'is_persistent' => true
    ], JSON_UNESCAPED_UNICODE);
}

function tg_set_webhook($webhookUrl, $botToken, $secretToken='') {
    $params = ['url' => $webhookUrl];
    if ($secretToken !== '') {
        
        $params['secret_token'] = $secretToken;
    }
    return tg_api('setWebhook', $params, $botToken);
}


function tg_get_file($fileId, $botToken) {
    return tg_api('getFile', ['file_id'=>$fileId], $botToken);
}

function tg_download_file($filePath, $botToken) {
    $url = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || $raw === false) return ['ok'=>false,'code'=>$code,'raw'=>$raw];
    return ['ok'=>true,'bytes'=>$raw];
}

function tg_send_photo_bytes($chatId, $bytes, $botToken, $caption='') {
    $tmp = tempnam(sys_get_temp_dir(), 'tgimg_');
    file_put_contents($tmp, $bytes);
    $file = new CURLFile($tmp, 'image/png', 'image.png');
    $params = [
        'chat_id' => $chatId,
        'photo' => $file
    ];
    if ($caption !== '') $params['caption'] = $caption;
    $res = tg_api('sendPhoto', $params, $botToken);
    @unlink($tmp);
    return $res;
}
