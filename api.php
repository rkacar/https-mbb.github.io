<?php
// api.php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Istanbul');

$storageDir = __DIR__ . '/storage';
$jsonPath  = $storageDir . '/stok.json';
$metaPath  = $storageDir . '/meta.json';

// Basit CORS (mobil sayfanın farklı domaine gelmesi durumunda ayarla)
header('Access-Control-Allow-Origin: *');

$mode = isset($_GET['mode']) ? trim($_GET['mode']) : 'FIYAT';
$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if ($code === '') {
    http_response_code(400);
    echo json_encode(['error'=>'Barkod değeri gerekli.']);
    exit;
}

if (!file_exists($jsonPath)) {
    http_response_code(500);
    echo json_encode(['error'=>'Veri dosyası bulunamadı. Lütfen Excel yükleyin.']);
    exit;
}

$data = json_decode(file_get_contents($jsonPath), true);
$meta = file_exists($metaPath) ? json_decode(file_get_contents($metaPath), true) : null;

$lastUpdateText = $meta['last_update_display'] ?? null;
$iso = $meta['last_update_iso'] ?? null;

// Try direct match
$found = $data[$code] ?? null;

// Try numeric-only match or remove leading zeros if not found
if (!$found) {
    // common barcode formatting tries
    $codeAlt = ltrim($code, "0");
    foreach ($data as $k=>$v){
        if ($k === $codeAlt || ltrim($k,"0") === $codeAlt || preg_replace('/\D/','',$k) === preg_replace('/\D/','',$code)) {
            $found = $v; break;
        }
    }
}

if (!$found) {
    // Not found - return specified error format
    $msg = "Barkod Kayıtlı Değildir, Excel Verilerini Güncelleyerek Ürüne Ait Bilgileri Görebilirsiniz. En Son Güncelleme Tarihi: ";
    $dateText = $lastUpdateText ? $lastUpdateText : '—';
    echo json_encode(['error'=>$msg, 'lastUpdateText'=>$dateText], JSON_UNESCAPED_UNICODE);
    exit;
}

// Determine which price to show based on mode
$priceLabel = 'FİYAT';
$priceValue = $found['FIYAT'] ?? $found['FİYAT'] ?? ($found['FIYAT'] ?? '');
if (strtoupper($mode) === 'VADELİ' || mb_strtoupper($mode,'UTF-8') === 'VADELİ') {
    $priceLabel = 'VADELİ';
    $priceValue = $found['VADELI'] ?? $found['VADELİ'] ?? $found['VADELI'] ?? $priceValue;
}
if (strtoupper($mode) === 'PERAKENDE' || mb_strtoupper($mode,'UTF-8') === 'PERAKENDE') {
    $priceLabel = 'PERAKENDE';
    $priceValue = $found['PRK'] ?? $found['PRK'] ?? $priceValue;
}

$response = [
    'priceLabel' => $priceLabel,
    'priceValue' => $priceValue,
    'currency'   => $found['DVZ'] ?? '',
    'name'       => $found['ADI'] ?? '',
    'color'      => $found['RENK'] ?? '',
    'size'       => $found['BEDEN'] ?? '',
    'amount'     => $found['MIKTAR'] ?? $found['MIKTAR'] ?? '',
    'lastUpdateText' => $lastUpdateText ? $lastUpdateText : '',
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
