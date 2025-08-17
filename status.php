<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *'); // Permite CORS se necessário

$BASE   = 'http://sonicpanel.oficialserver.com:8342';
$SEVEN  = $BASE . '/7.html?t=' . time();
$STATUS = $BASE . '/?t=' . time();

function fetchText($url, $timeout = 4) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'StatusFetcher/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $out = curl_exec($ch);
    $err = curl_errno($ch) ? curl_error($ch) : null;
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err || $code >= 400) return null;
    return $out;
}

function normalize($s) {
    if ($s === null) return null;
    // Remove espaços extras e caracteres especiais desnecessários
    $s = preg_replace('/\s+/', ' ', trim($s));
    // Remove caracteres de controle
    $s = preg_replace('/[\x00-\x1F\x7F]/', '', $s);
    return $s === '' ? null : $s;
}

// ==== 1) 7.html (ouvintes + música) ====
$listeners = null; 
$song = null; 
$serverUp = true;

if ($txt = fetchText($SEVEN)) {
    $line = null;
    foreach (preg_split('/\R/', $txt) as $l) { 
        if (strpos($l, ',') !== false) { 
            $line = $l; 
            break; 
        } 
    }
    if (!$line) $line = $txt;
    
    $parts = array_map('trim', explode(',', $line));
    
    // Posição 0: número de ouvintes
    if (isset($parts[0]) && is_numeric($parts[0])) {
        $listeners = (int)$parts[0];
    }
    
    // Posição 6: música atual
    if (isset($parts[6])) {
        $song = normalize($parts[6]);
    }
    
    // Posição 5: status do servidor (1 = online, 0 = offline)
    if (isset($parts[5])) {
        $serverUp = trim($parts[5]) === '1';
    }
}

// ==== 2) Página de status (locutor + programação) ====
$dj = null; 
$program = null;

if ($html = fetchText($STATUS)) {
    // Busca pelo Stream Title (nome do locutor)
    // Padrões comuns em páginas de status do SonicPanel
    $patterns = [
        '/Stream\s*Title\s*:?\s*(?:<\/?[^>]+>\s*)*([^<\r\n]+)/i',
        '/Current\s*DJ\s*:?\s*(?:<\/?[^>]+>\s*)*([^<\r\n]+)/i',
        '/<td[^>]*>Stream\s*Title<\/td>\s*<td[^>]*>([^<]+)</i',
        '/stream_title["\']?\s*[:=]\s*["\']([^"\']+)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $m)) {
            $dj = normalize($m[1]);
            if ($dj) break;
        }
    }
    
    // Busca pelo Stream Genre (programação)
    $genrePatterns = [
        '/Stream\s*Genre\s*:?\s*(?:<\/?[^>]+>\s*)*([^<\r\n]+)/i',
        '/Current\s*Show\s*:?\s*(?:<\/?[^>]+>\s*)*([^<\r\n]+)/i',
        '/<td[^>]*>Stream\s*Genre<\/td>\s*<td[^>]*>([^<]+)</i',
        '/stream_genre["\']?\s*[:=]\s*["\']([^"\']+)/i'
    ];
    
    foreach ($genrePatterns as $pattern) {
        if (preg_match($pattern, $html, $m)) {
            $program = normalize($m[1]);
            if ($program) break;
        }
    }
}

// Processa o nome do DJ para identificar se é AutoDJ
$isAutoDJ = false;
if ($dj) {
    // Lista de padrões que indicam AutoDJ
    $autoDJPatterns = [
        'autodj',
        'auto dj',
        'radio habblive',
        'blume'
    ];
    
    $djLower = strtolower($dj);
    foreach ($autoDJPatterns as $pattern) {
        if (strpos($djLower, $pattern) !== false) {
            $isAutoDJ = true;
            break;
        }
    }
}

// Se for AutoDJ, retorna null para que o frontend decida como exibir
if ($isAutoDJ) {
    $dj = null;
    $program = null;
}

// Resposta JSON
$response = [
    'ok'        => true,
    'serverUp'  => $serverUp,
    'listeners' => $serverUp ? $listeners : 0,
    'song'      => $serverUp ? $song : null,
    'dj'        => $serverUp ? $dj : null,
    'program'   => $serverUp ? $program : null,
    'isAutoDJ'  => $isAutoDJ,
    'timestamp' => time()
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);