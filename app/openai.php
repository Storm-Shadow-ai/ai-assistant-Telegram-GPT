<?php
require_once __DIR__ . '/util.php';

function openai_extract_text($json) {

    if (isset($json['output_text']) && is_string($json['output_text']) && $json['output_text'] !== '') {
        return $json['output_text'];
    }

    if (isset($json['output']) && is_array($json['output'])) {
        foreach ($json['output'] as $out) {
            if (!is_array($out)) continue;
            if (!isset($out['content']) || !is_array($out['content'])) continue;
            foreach ($out['content'] as $c) {
                if (is_array($c) && ($c['type'] ?? '') === 'output_text' && isset($c['text'])) {
                    return (string)$c['text'];
                }
                if (is_array($c) && isset($c['text']) && is_string($c['text'])) {
                    return (string)$c['text'];
                }
            }
        }
    }

    if (isset($json['choices'][0]['message']['content'])) {
        return (string)$json['choices'][0]['message']['content'];
    }
    return '';
}

function openai_usage_extract($json) {
    $u = $json['usage'] ?? null;
    if (!is_array($u)) return ['input'=>0,'output'=>0,'total'=>0];
    $in = 0; $out = 0; $total = 0;
    if (isset($u['input_tokens'])) $in = (int)$u['input_tokens'];
    if (isset($u['output_tokens'])) $out = (int)$u['output_tokens'];
    if (isset($u['total_tokens'])) $total = (int)$u['total_tokens'];

    if ($in === 0 && isset($u['prompt_tokens'])) $in = (int)$u['prompt_tokens'];
    if ($out === 0 && isset($u['completion_tokens'])) $out = (int)$u['completion_tokens'];
    if ($total === 0) {
        $total = $in + $out;
    }
    return ['input'=>$in,'output'=>$out,'total'=>$total];
}

function openai_apply_proxy($ch, $proxyRaw) {
    $proxyRaw = trim((string)$proxyRaw);
    if ($proxyRaw === '') return;

    $proxyNorm = $proxyRaw;
    if (!preg_match('/^[a-z]+:\/\//i', $proxyNorm)) {
        $proxyNorm = 'http://' . $proxyNorm;
    }
    $parts = parse_url($proxyNorm);
    if (!is_array($parts) || empty($parts['host'])) {
        curl_setopt($ch, CURLOPT_PROXY, $proxyRaw);
        return;
    }

    $scheme = strtolower((string)($parts['scheme'] ?? 'http'));
    $host = (string)$parts['host'];
    $port = isset($parts['port']) ? (':' . (int)$parts['port']) : '';
    curl_setopt($ch, CURLOPT_PROXY, $host . $port);
    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);

    if (isset($parts['user'])) {
        $auth = (string)$parts['user'];
        if (isset($parts['pass'])) $auth .= ':' . (string)$parts['pass'];
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $auth);
    }

    if ($scheme === 'socks5' || $scheme === 'socks5h') {
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
    } elseif ($scheme === 'socks4' || $scheme === 'socks4a') {
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
    } else {
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
    }
}

function openai_responses_create($inputText, $opts) {
    $apiKey = $opts['api_key'];
    $model = $opts['model'];
    $temperature = $opts['temperature'];
    $maxOut = $opts['max_output_tokens'];
    $proxy = $opts['proxy'];

    $payload = [
        'model' => $model,
        'input' => $inputText
    ];
    if (!preg_match('/^gpt-5/i', (string)$model)) {
        $payload['temperature'] = (float)$temperature;
    }
    $payload['max_output_tokens'] = (int)$maxOut;

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));

    openai_apply_proxy($ch, $proxy);

    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok'=>false, 'error'=>"cURL error: ".$err, 'http'=>0, 'raw'=>null, 'json'=>null];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok'=>false, 'error'=>"Bad JSON from OpenAI (HTTP $http)", 'http'=>$http, 'raw'=>$raw, 'json'=>null];
    }

    if ($http >= 400) {
        $msg = $json['error']['message'] ?? ("HTTP " . $http);
        return ['ok'=>false, 'error'=>$msg, 'http'=>$http, 'raw'=>$raw, 'json'=>$json];
    }

    $text = openai_extract_text($json);
    if ($text === '') $text = "(пустой ответ)";
    $usage = openai_usage_extract($json);
    return ['ok'=>true, 'text'=>$text, 'usage'=>$usage, 'http'=>$http, 'raw'=>$raw, 'json'=>$json];
}


function openai_image_generate($prompt, $opts) {
    $apiKey = $opts['api_key'];
    $model = $opts['model'];
    $size = $opts['size'] ?? '1024x1024';
    $proxy = $opts['proxy'] ?? '';

    $payload = [
        'model' => $model,
        'prompt' => (string)$prompt,
        'size' => $size
    ];

    if (preg_match('/^dall-e-/i', (string)$model)) {
        $payload['response_format'] = 'b64_json';
    }

    $ch = curl_init('https://api.openai.com/v1/images/generations');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer '.$apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
    openai_apply_proxy($ch, $proxy);

    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($raw, true);
    if ($code !== 200 || !is_array($json)) {
        $err = is_array($json) ? (string)($json['error']['message'] ?? '') : '';
        return ['ok'=>false, 'code'=>$code, 'raw'=>$raw, 'error'=>$err];
    }
    $b64 = $json['data'][0]['b64_json'] ?? '';
    if (!is_string($b64) || $b64 === '') {
        return ['ok'=>false, 'code'=>$code, 'raw'=>$raw, 'error'=>'Image payload is empty'];
    }
    $bytes = base64_decode($b64, true);
    if ($bytes === false) return ['ok'=>false, 'code'=>$code, 'raw'=>$raw, 'error'=>'Image decode failed'];
    return ['ok'=>true, 'bytes'=>$bytes, 'usage'=>openai_usage_extract($json)];
}

function openai_image_edit($imageBytes, $prompt, $opts) {
    $apiKey = $opts['api_key'];
    $model = $opts['model'];
    $size = $opts['size'] ?? '1024x1024';
    $proxy = $opts['proxy'] ?? '';

    $tmp = tempnam(sys_get_temp_dir(), 'oaiimg_');
    file_put_contents($tmp, $imageBytes);
    $mime = 'image/png';
    $name = 'image.png';
    if (function_exists('finfo_buffer')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $det = (string)finfo_buffer($finfo, $imageBytes);
            finfo_close($finfo);
            if ($det === 'image/jpeg') { $mime = 'image/jpeg'; $name = 'image.jpg'; }
            elseif ($det === 'image/webp') { $mime = 'image/webp'; $name = 'image.webp'; }
            elseif ($det === 'image/png') { $mime = 'image/png'; $name = 'image.png'; }
        }
    }
    $file = new CURLFile($tmp, $mime, $name);

    $fields = [
        'model' => $model,
        'prompt' => (string)$prompt,
        'size' => $size,
        'image' => $file
    ];


    if (preg_match('/^dall-e-/i', (string)$model)) {
        $fields['response_format'] = 'b64_json';
    }

    $ch = curl_init('https://api.openai.com/v1/images/edits');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_TIMEOUT, 240);
    openai_apply_proxy($ch, $proxy);

    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @unlink($tmp);

    $json = json_decode($raw, true);
    if ($code !== 200 || !is_array($json)) {
        $err = is_array($json) ? (string)($json['error']['message'] ?? '') : '';
        return ['ok'=>false, 'code'=>$code, 'raw'=>$raw, 'error'=>$err];
    }
    $b64 = $json['data'][0]['b64_json'] ?? '';
    if (!is_string($b64) || $b64 === '') {
        return ['ok'=>false, 'code'=>$code, 'raw'=>$raw, 'error'=>'Image payload is empty'];
    }
    $bytes = base64_decode($b64, true);
    if ($bytes === false) return ['ok'=>false, 'code'=>$code, 'raw'=>$raw, 'error'=>'Image decode failed'];
    return ['ok'=>true, 'bytes'=>$bytes, 'usage'=>openai_usage_extract($json)];
}
