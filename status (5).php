<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

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
    CURLOPT_USERAGENT => 'StatusFetcher/1.0'
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
  $s = preg_replace('/\s+/', ' ', trim($s));
  return $s === '' ? null : $s;
}

// ==== 1) 7.html (ouvintes + música) ====
$listeners = null; $song = null; $serverUp = true;
if ($txt = fetchText($SEVEN)) {
  $line = null;
  foreach (preg_split('/\R/', $txt) as $l) { if (strpos($l, ',') !== false) { $line = $l; break; } }
  if (!$line) $line = $txt;
  $parts = array_map('trim', explode(',', $line));
  if (isset($parts[0]) && is_numeric($parts[0])) $listeners = (int)$parts[0];
  if (isset($parts[6])) $song = normalize($parts[6]);
  if (isset($parts[5])) $serverUp = trim($parts[5]) === '1';
}

// ==== 2) Página de status (locutor + programação) ====
$dj = null; $program = null;
if ($html = fetchText($STATUS)) {
  if (preg_match('/Stream\s*Title\s*:?\s*(?:<\/?[^>]+>\s*)*([^<\r\n]+)/i', $html, $m))
    $dj = normalize($m[1]);     // NOME DO LOCUTOR
  if (preg_match('/Stream\s*Genre\s*:?\s*(?:<\/?[^>]+>\s*)*([^<\r\n]+)/i', $html, $m))
    $program = normalize($m[1]); // PROGRAMAÇÃO
}

echo json_encode([
  'ok'        => true,
  'serverUp'  => $serverUp,
  'listeners' => $serverUp ? $listeners : null,
  'song'      => $serverUp ? $song : null,
  'dj'        => $serverUp ? $dj : null,
  'program'   => $serverUp ? $program : null,
  'ts'        => time()
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
