(() => {
    const get = id => document.getElementById(id);
    const screens = [...document.querySelectorAll('.kiosk-screen')];
    const stationInput = get('station-code');
    const scanResult = get('scan-result');
    const video = get('scan-video');
    const scanButton = get('scan-button');
    const formatMoney = value => new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';

    let stream = null;
    let scanning = false;

    function stopCamera() {
        scanning = false;
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        stream = null;
        video.hidden = true;
        scanButton.textContent = 'Scanner le QR code';
    }

    function showStep(step) {
        screens.forEach(screen => {
            screen.hidden = Number(screen.dataset.step) !== step;
        });
        get('step-counter').textContent = `Étape ${step} sur 3`;
        get('progress-bar').style.width = `${step / 3 * 100}%`;
        stopCamera();
        document.querySelector('.kiosk-workflow').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    stationInput.addEventListener('input', () => {
        scanResult.textContent = stationInput.value.trim()
            ? `Code saisi : ${stationInput.value.trim()}`
            : 'La caméra ne s’active qu’à votre demande.';
    });

    scanButton.addEventListener('click', async () => {
        if (scanning) {
            stopCamera();
            return;
        }
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
            scanResult.textContent = 'Scanner indisponible sur ce navigateur. Saisissez le code de la station.';
            return;
        }
        try {
            if (!(await BarcodeDetector.getSupportedFormats()).includes('qr_code')) {
                throw new Error('QR non pris en charge');
            }
            const detector = new BarcodeDetector({ formats: ['qr_code'] });
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            video.srcObject = stream;
            video.hidden = false;
            await video.play();
            scanning = true;
            scanButton.textContent = 'Arrêter la caméra';

            const scanFrame = async () => {
                if (!scanning) return;
                try {
                    const codes = await detector.detect(video);
                    if (codes.length) {
                        stationInput.value = codes[0].rawValue.slice(0, 120);
                        scanResult.textContent = `Code lu : ${stationInput.value}`;
                        stopCamera();
                        return;
                    }
                } catch (_) {
                    // The camera may not have produced a frame yet.
                }
                if (scanning) requestAnimationFrame(scanFrame);
            };
            requestAnimationFrame(scanFrame);
        } catch (_) {
            stopCamera();
            scanResult.textContent = 'Caméra indisponible ou accès refusé. Saisissez le code de la station.';
        }
    });

    get('station-next').addEventListener('click', () => {
        if (!stationInput.value.trim()) {
            scanResult.textContent = 'Saisissez ou scannez d’abord le code de la station.';
            stationInput.focus();
            return;
        }
        showStep(2);
    });

    document.querySelectorAll('[data-back]').forEach(button => {
        button.addEventListener('click', () => showStep(Number(button.dataset.back)));
    });

    document.querySelectorAll('.battery-option').forEach(button => {
        button.addEventListener('click', () => {
            const selected = {
                id: button.dataset.id,
                serial: button.dataset.serial,
                deposit: Number(button.dataset.deposit),
            };
            if (get('checkout-station')) get('checkout-station').value = stationInput.value.trim();
            if (get('checkout-battery')) get('checkout-battery').value = selected.id;
            get('summary-station').textContent = stationInput.value.trim();
            get('summary-battery').textContent = selected.serial;
            get('summary-deposit').textContent = formatMoney(selected.deposit);
            get('summary-total').textContent = formatMoney(Number(window.TB_PRICE) + selected.deposit);
            showStep(3);
        });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stopCamera();
    });
})();
