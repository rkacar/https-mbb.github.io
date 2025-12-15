<?php
// upload.php
session_start();
date_default_timezone_set('Europe/Istanbul');

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;


set_time_limit(0);
ini_set('memory_limit', '512M');

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

$excelPath = $storageDir . '/stok.xlsx';
$jsonPath  = $storageDir . '/stok.json';
$metaPath  = $storageDir . '/meta.json';

if (!isset($_SESSION['captcha'])) {
    $_SESSION['captcha'] = ['a'=>rand(2,9),'b'=>rand(2,9)];
    $_SESSION['captcha']['ans'] = $_SESSION['captcha']['a'] + $_SESSION['captcha']['b'];
}

function normalizeHeader($h) {
    $h = trim((string)$h);
    $h = mb_strtoupper($h, 'UTF-8');
    $map = [
        'FİYAT'=>'FIYAT',
        'VADELİ'=>'VADELI',
        'MİKTAR'=>'MIKTAR'
    ];
    return $map[$h] ?? $h;
}

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {

    if ($_POST['csrf'] !== $_SESSION['csrf']) $errors[]='CSRF hatası';
    if (!empty($_POST['hp_email'])) $errors[]='Bot';
    if ((int)$_POST['captcha_answer'] !== $_SESSION['captcha']['ans']) $errors[]='Captcha yanlış';

    if (!$errors && move_uploaded_file($_FILES['excel']['tmp_name'],$excelPath)) {

        $reader = IOFactory::createReaderForFile($excelPath);
        $spreadsheet = $reader->load($excelPath);
        $sheet = $spreadsheet->getActiveSheet();

        // Başlıklar
        $headers = [];
        foreach ($sheet->getRowIterator(1,1) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            foreach ($cellIterator as $cell) {
                $headers[] = normalizeHeader($cell->getValue());
            }
        }

        $data = [];

        foreach ($sheet->getRowIterator(2) as $row) {
            $rowAssoc = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $i = 0;
            foreach ($cellIterator as $cell) {
                $key = $headers[$i] ?? null;
                if ($key) {
                    $val = $cell->getFormattedValue();
                    if ($val === null || trim($val) === '') {
                        $raw = $cell->getValue();
                        $val = is_numeric($raw) ? (string)$raw : (string)$raw;
                    }
                    $rowAssoc[$key] = trim(str_replace('.',',',$val));
                }
                $i++;
            }

            if (empty($rowAssoc['KODU'])) continue;

            $data[$rowAssoc['KODU']] = [
                'ADI'=>$rowAssoc['ADI']??'',
                'RENK'=>$rowAssoc['RENK']??'',
                'BEDEN'=>$rowAssoc['BEDEN']??'',
                'FIYAT'=>$rowAssoc['FIYAT']??'',
                'VADELI'=>$rowAssoc['VADELI']??'',
                'PRK'=>$rowAssoc['PRK']??'',
                'MIKTAR'=>$rowAssoc['MIKTAR']??'',
                'DVZ'=>$rowAssoc['DVZ']??'',
            ];
        }

        file_put_contents($jsonPath,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

        $dt=new DateTime('now',new DateTimeZone('Europe/Istanbul'));
        file_put_contents($metaPath,json_encode([
            'last_update_display'=>$dt->format('d.m.Y - H:i')
        ],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

        $success="Yükleme başarılı (".count($data)." kayıt)";
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
	<meta http-equiv="Content-Type" content="text/HTML"; charset=UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow" /> 
    <meta name="Author" content="RECAİ KAÇAR, Industrial Engineer - AADYM / Antalya İl Afet ve Acil Durum Müdürlüğü" />
    <link rel="Shortcut Icon" href="favicon.png" type="image/x-icon" />

    <title>MBB TEKSTİL & TASARIM</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;padding:20px;background:#f7fafc}
        .box{max-width:720px;margin:0 auto;background:#fff;padding:18px;border-radius:10px;box-shadow:0 6px 18px rgba(2,6,23,.06)}
        input[type=file], input[type=number]{width:100%;padding:8px;margin-top:5px;box-sizing:border-box}
        .err{color:#b91c1c;background:#fee;border:1px solid #fecaca;padding:12px;border-radius:5px;margin:10px 0}
        .ok{color:green;background:#f0fdf4;border:1px solid #bbf7d0;padding:12px;border-radius:5px;margin:10px 0}
  .btn{padding:8px 12px;border-radius:6px;text-decoration:none;color:#fff;background:#1976d2;display:inline-block}
  .btn.secondary{background:#6c757d}
        label{display:block;margin-top:15px;font-weight:bold}
        button{margin-top:20px;padding:12px 24px;font-size:16px;cursor:pointer}
    </style>
</head>
<body>
<div class="box">
    <h2>MBB Stok Excel/CSV Yükle (*.xls, *.xlsx, *.csv)</h2>

    <?php if ($errors): ?>
        <div class="err"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="ok"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label><?php //Excel veya CSV dosyası seçin (*.xls, *.xlsx, *.csv) ?>
            <input type="file" name="excel" accept=".xls,.xlsx,.csv" required>
        </label>

        <div style="display:none">
            <label>Email (doldurmayın)
                <input type="text" name="hp_email" autocomplete="off">
            </label>
        </div>
<br><br><br>
        <label>İşlemin Sonucunu Kutucuğa Girin: <?php echo $_SESSION['captcha']['a'] . " + " . $_SESSION['captcha']['b']; ?> =
            <input type="number" name="captcha_answer" required min="4" max="18">
        </label>

        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
<br><br><br>
        <button class="btn" type="submit">Yükle ve Güncelle</button> <a class="btn secondary" href="index.php">Geri</a>
    </form>
<br><br><br><br>
    <hr>
    <h4>Mevcut Durum</h4>
    <ul>
        <li>Excel/CSV dosyası: <code><?php echo htmlspecialchars($excelPath); ?></code></li>
        <li>JSON veri dosyası: <code><?php echo htmlspecialchars($jsonPath); ?></code></li>
        <li>Meta dosyası: <code><?php echo htmlspecialchars($metaPath); ?></code></li>
        <li>Son güncelleme:
            <?php
            if (file_exists($metaPath)) {
                $m = json_decode(file_get_contents($metaPath), true);
                echo htmlspecialchars($m['last_update_display'] ?? '—');
            } else {
                echo '—';
            }
            ?>
        </li>
    </ul>

    <p><small>İpucu: Büyük dosyalar için <strong>.csv</strong> formatı kullanmak çok daha hızlıdır.</small></p>
</div>
</body>
</html>