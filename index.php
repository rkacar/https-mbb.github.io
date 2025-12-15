<!doctype html>
<html lang="tr">
<head>
	<meta http-equiv="Content-Type" content="text/html;  charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
    <meta name="robots" content="noindex, nofollow" /> 
    <meta name="Author" content="RECAİ KAÇAR, Industrial Engineer - AADYM / Antalya İl Afet ve Acil Durum Müdürlüğü" />
	<link rel="Shortcut Icon" href="favicon.png" type="image/x-icon" />

<title>MBB TASARIM & TEKSTİL</title>
<style>
  :root{font-family:Inter,Arial,Helvetica,sans-serif}
  body{margin:0;background:#f4f6f8;color:#111;display:flex;flex-direction:column;min-height:100vh}
  header{padding:18px;text-align:center;background:#0f1724;color:#fff}
  h1{margin:0;font-size:20px}
  main{flex:1;padding:16px;max-width:760px;margin:0 auto;width:100%}
  .btn1{display:block;width:100%;padding:18px;border-radius:12px;font-size:20px;margin:12px 0;border:0;background:#0b8209;color:#fff;box-shadow:0 6px 18px rgba(15,23,36,.12)}
  .btn2{display:block;width:100%;padding:18px;border-radius:12px;font-size:20px;margin:12px 0;border:0;background:#f76205;color:#fff;box-shadow:0 6px 18px rgba(15,23,36,.12)}
  .btn3{display:block;width:100%;padding:18px;border-radius:12px;font-size:20px;margin:12px 0;border:0;background:#e30202;color:#fff;box-shadow:0 6px 18px rgba(15,23,36,.12)}
  #result{background:#fff;padding:14px;border-radius:12px;margin-top:16px;min-height:120px}
  .price{font-weight:700;font-size:32px;color:green}
  .label{font-weight:700;font-size:20px;margin-top:8px}
  .others{font-size:20px}
  .amount{font-weight:700;font-size:32px;color:red}
  .meta{margin-top:16px;color:blue;font-size:14px}
  #videoWrap{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);align-items:center;justify-content:center;z-index:999}
  #video{max-width:100%;height:auto;border-radius:12px;max-height:80vh}
  #closeCam{position:absolute;top:18px;right:18px;background:#fff;border-radius:50%;padding:8px}
  .error{color:#b91c1c;font-weight:700}
</style>
</head>
<body>
<header><h1>MBB FİYAT GÖR</h1></header>
<main>
  <button class="btn1" id="btnFiyat">FİYAT</button>
  <button class="btn2" id="btnVadeli">VADELİ</button>
  <button class="btn3" id="btnPerakende">PERAKENDE</button>

  <div id="result">
    <div id="outText"><center><br>Bir butona basıp barkod okutun.</center></div>
  </div>
</main>

<!-- Kamera modal -->
<div id="videoWrap">
  <button id="closeCam">Kapat</button>
  <video id="video" playsinline></video>
</div>

<!-- jsQR fallback (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

<script>
/*
  Özet:
  - Kullanıcı butona basınca kamera açılır.
  - BarcodeDetector API varsa onu kullan, yoksa jsQR ile fallback.
  - Tarama sırasında düşük ışıkta mümkün olduğunca iyi sonuç almak için
    video constrain'larına odaklanma/torch denemesi yapılıyor (destekli tarayıcılarda).
  - Barkod bulunduğunda API'ye /api.php?mode=...&code=... isteği atılır.
*/

const result = document.getElementById('outText');
const videoWrap = document.getElementById('videoWrap');
const video = document.getElementById('video');
const closeCam = document.getElementById('closeCam');

let activeMode = null;
let stream = null;
let scanning = false;
let scanInterval = null;

// Butonlar
document.getElementById('btnFiyat').addEventListener('click', ()=> startScan('FIYAT'));
document.getElementById('btnVadeli').addEventListener('click', ()=> startScan('VADELİ'));
document.getElementById('btnPerakende').addEventListener('click', ()=> startScan('PERAKENDE'));
closeCam.addEventListener('click', stopScan);

// Helpers
function tt(msg){ result.innerHTML = msg; }

async function startScan(mode){
  activeMode = mode;
  tt('Kamera açılıyor... Lütfen izin verin ve barkoda yakınlaştırın.');
  videoWrap.style.display = 'flex';
  scanning = true;

  try {
    // camera constraints - makro/torch denemesi (tarayıcı destekliyorsa)
    const constraints = {
      video: {
        facingMode: 'environment',
        width: { ideal: 1280 },
        height: { ideal: 720 },
        focusMode: "continuous" // sadece bazı tarayıcılarda kabul edilir
      },
      audio: false
    };

    stream = await navigator.mediaDevices.getUserMedia(constraints);
    video.srcObject = stream;
    await video.play();

    // if supported, try enable torch (if device supports)
    const track = stream.getVideoTracks()[0];
    const capabilities = track.getCapabilities ? track.getCapabilities() : {};
    if (capabilities.torch) {
      try { await track.applyConstraints({ advanced: [{ torch: true }] }); } catch(e){}
    }

    // Use BarcodeDetector if available
    if ('BarcodeDetector' in window) {
      const formats = ['ean_13','ean_8','upc_e','upc_a','code_128','code_39','qr_code'];
      const detector = new BarcodeDetector({formats});
      readWithDetector(detector);
    } else {
      readWithJsQR();
    }
  } catch (err) {
    console.error(err);
    tt('<div class="error">Kameraya erişilemedi. Lütfen tarayıcı izinlerini kontrol edin veya farklı bir tarayıcı deneyin.</div>');
    videoWrap.style.display = 'none';
    scanning = false;
  }
}

function stopScan(){
  scanning = false;
  videoWrap.style.display = 'none';
  if (scanInterval) { clearInterval(scanInterval); scanInterval = null; }
  if (stream) {
    stream.getTracks().forEach(t=>t.stop());
    stream = null;
  }
}

// BarcodeDetector kullanımı
async function readWithDetector(detector) {
  tt('Barkod algılanıyor (detector)...');
  try {
    while (scanning) {
      const barcodes = await detector.detect(video);
      if (barcodes && barcodes.length) {
        const code = barcodes[0].rawValue || barcodes[0].rawData;
        if (code) { onBarcodeScanned(code.trim()); break; }
      }
      await new Promise(r=>setTimeout(r, 200));
    }
  } catch (err) {
    console.error(err);
    // fallback
    readWithJsQR();
  }
}

// jsQR fallback - video frame -> canvas -> decode
function readWithJsQR(){
  tt('Barkod algılanıyor (jsQR fallback)...');
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');

  scanInterval = setInterval(()=>{
    if (!scanning) return;
    try {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      const imageData = ctx.getImageData(0,0,canvas.width,canvas.height);
      const code = jsQR(imageData.data, canvas.width, canvas.height, { inversionAttempts: "attemptBoth" });
      if (code && code.data) {
        onBarcodeScanned(code.data.trim());
      }
    } catch(e){
      // ignore
    }
  }, 250);
}

let called = false;
function onBarcodeScanned(code){
  if (called) return;
  called = true;
  stopScan();
  tt('Barkod bulundu: ' + code + '<br>Sunucuya sorgu atılıyor...');
  fetchResultFromServer(code);
}

// SERVER İSTEĞİ
async function fetchResultFromServer(code){
  try {
    // encodeURIComponent kullanılmalı
    const resp = await fetch(`api.php?mode=${encodeURIComponent(activeMode)}&code=${encodeURIComponent(code)}`, {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    });

    if (!resp.ok) {
      const txt = await resp.text();
      tt('<div class="error">Sunucu hatası: ' + resp.status + ' ' + txt + '</div>');
      called = false;
      return;
    }

    const data = await resp.json();
    renderResult(data);
    called = false;
  } catch (err) {
    console.error(err);
    tt('<div class="error">Sunucu ile bağlantı kurulamadı.</div>');
    called = false;
  }
}

function renderResult(data){
  if (data.error){
    tt(`<div class="error">${data.error}</div><div class="meta">${data.lastUpdateText ? data.lastUpdateText : ''}</div>`);
    return;
  }
  // Başlık (FİYAT / VADELİ / PERAKENDE)
  const priceLabel = data.priceLabel || 'FİYAT';
  const priceValue = data.priceValue ?? '-';
  const currency = data.currency ?? '';
  const name = data.name ?? '';
  const color = data.color ?? '';
  const size = data.size ?? '';
  const amount = data.amount ?? '';
  const lastUpdate = data.lastUpdateText ?? '';

  const html = `
    <div class="label">${priceLabel}:</div>
    <div class="price">${priceValue} ${currency}</div>
    <div class="label">ÜRÜN ADI:</div>
    <div class="others">${escapeHtml(name)}</div>
    <div class="label">RENK / BEDEN:</div>
    <div class="others">${escapeHtml(color)} - ${escapeHtml(size)}</div>
    <div class="label">MİKTAR:</div>
    <div class="amount">${escapeHtml(String(amount))}</div>
    <div class="meta">En Son Güncelleme: ${escapeHtml(lastUpdate)}</div>
  `;
  tt(html);
}

// XSS koruması
function escapeHtml(s){ return s ? s.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;') : ''; }

</script>
</body>
</html>
