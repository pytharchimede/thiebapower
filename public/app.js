(() => {
  const $ = id => document.getElementById(id);
  const screens = [...document.querySelectorAll('.kiosk-screen')];
  const input = $('station-code'), result = $('scan-result'), video = $('scan-video'), scanButton = $('scan-button');
  const money = amount => new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
  let step = 1, stream = null, scanning = false, selected = null;
  function show(next) {
    step = next;
    screens.forEach(screen => { screen.hidden = Number(screen.dataset.step) !== next; });
    $('step-counter').textContent = `Étape ${next} sur 3`;
    $('progress-bar').style.width = (next / 3 * 100) + '%';
    stopCamera();
    document.querySelector('.kiosk-workflow').scrollIntoView({behavior:'smooth',block:'start'});
  }
  function stopCamera() {
    scanning = false;
    if (stream) stream.getTracks().forEach(track => track.stop());
    stream = null; video.hidden = true; scanButton.textContent = '▣   Scanner le QR code';
  }
  input.addEventListener('input', () => { result.textContent = input.value.trim() ? 'Code saisi : ' + input.value.trim() : 'La caméra ne s’active que lorsque vous appuyez sur Scanner.'; });
  scanButton.addEventListener('click', async () => {
    if (scanning) { stopCamera(); return; }
    if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) { result.textContent = 'Scanner indisponible sur ce navigateur. Saisissez le code de la station.'; return; }
    try {
      if (!(await BarcodeDetector.getSupportedFormats()).includes('qr_code')) throw new Error('unsupported');
      const detector = new BarcodeDetector({formats:['qr_code']});
      stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false});
      video.srcObject = stream; video.hidden = false; await video.play(); scanning = true;
      scanButton.textContent = 'Arrêter la caméra';
      const tick = async () => {
        if (!scanning) return;
        try {
          const codes = await detector.detect(video);
          if (codes.length) { input.value = codes[0].rawValue.slice(0,120); result.textContent = 'Code lu : ' + input.value; stopCamera(); return; }
        } catch (_) { /* Camera may not have a frame yet. */ }
        if (scanning) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    } catch (_) { stopCamera(); result.textContent = 'Caméra indisponible ou accès refusé. Saisissez le code de la station.'; }
  });
  $('station-next').addEventListener('click', () => {
    if (!input.value.trim()) { result.textContent = 'Saisissez ou scannez d’abord le code de la station.'; input.focus(); return; }
    show(2);
  });
  document.querySelectorAll('[data-back]').forEach(button => button.addEventListener('click', () => show(Number(button.dataset.back))));
  document.querySelectorAll('.battery-option').forEach(button => button.addEventListener('click', () => {
    selected = {serial:button.dataset.serial,deposit:Number(button.dataset.deposit)};
    $('summary-station').textContent = input.value.trim();
    $('summary-battery').textContent = selected.serial;
    $('summary-deposit').textContent = money(selected.deposit);
    $('summary-total').textContent = money(Number(window.TB_PRICE) + selected.deposit);
    show(3);
  }));
  document.addEventListener('visibilitychange', () => { if (document.hidden) stopCamera(); });
})();
