(function () {
    'use strict';

    const tokenField = document.getElementById('modalToken');
    const modal = document.getElementById('proxyModal');

    if (!tokenField || !modal) return;

    const callbackName = document.body.dataset.scannerCallback
        || (location.pathname.toLowerCase().includes('new_application.php')
            ? 'loadProxyQrData'
            : 'searchByQrToken');

    const scannerId = 'simpleCodeScannerReader';
    const scannerPanel = document.createElement('div');
    scannerPanel.className = 'simple-code-scanner';
    scannerPanel.innerHTML = `
        <div class="simple-code-scanner__actions" aria-label="Code scanner controls">
            <button type="button" class="btn btn-primary" id="startCodeScanner">
                <i class="fas fa-camera" aria-hidden="true"></i> Open Camera
            </button>
            <button type="button" class="btn btn-secondary" id="scanCodeImage">
                <i class="fas fa-image" aria-hidden="true"></i> Upload QR Photo
            </button>
            <button type="button" class="btn btn-secondary" id="stopCodeScanner" hidden>
                <i class="fas fa-stop" aria-hidden="true"></i> Stop
            </button>
        </div>
        <input type="file" id="codeScannerFile" accept="image/*" capture="environment" hidden>
        <div id="${scannerId}" class="simple-code-scanner__reader" hidden></div>
        <p id="codeScannerStatus" class="simple-code-scanner__status" role="status" aria-live="polite">
            Point the camera at the whole QR code, upload a saved QR photo, or enter the printed PRX/PEN token below.
        </p>`;

    const tokenGroup = tokenField.closest('.form-group') || tokenField.parentElement;
    tokenGroup.parentElement.insertBefore(scannerPanel, tokenGroup);

    const startButton = document.getElementById('startCodeScanner');
    const imageButton = document.getElementById('scanCodeImage');
    const stopButton = document.getElementById('stopCodeScanner');
    const fileInput = document.getElementById('codeScannerFile');
    const reader = document.getElementById(scannerId);
    const status = document.getElementById('codeScannerStatus');

    let scanner = null;
    let cameraRunning = false;
    let processingResult = false;
    let scanHintTimer = null;
    let cameraRequestTimer = null;

    function setStatus(message, type) {
        status.textContent = message;
        status.dataset.type = type || 'info';
    }

    function ensureLibrary() {
        if (typeof window.Html5Qrcode !== 'function') {
            setStatus('Scanner could not load. Enter the token manually and continue.', 'error');
            return false;
        }

        if (!scanner) scanner = new window.Html5Qrcode(scannerId);
        return true;
    }

    function setCameraState(running) {
        cameraRunning = running;
        reader.hidden = !running;
        startButton.hidden = running;
        stopButton.hidden = !running;
        imageButton.disabled = false;
        if (!running && scanHintTimer) {
            window.clearTimeout(scanHintTimer);
            scanHintTimer = null;
        }
        if (!running && cameraRequestTimer) {
            window.clearTimeout(cameraRequestTimer);
            cameraRequestTimer = null;
        }
    }

    async function stopScanner() {
        if (scanner && cameraRunning) {
            try {
                await scanner.stop();
            } catch (error) {
                console.debug('Scanner was already stopped.', error);
            }
        }
        setCameraState(false);
        if (scanner) {
            try { scanner.clear(); } catch (error) {}
        }
    }

    async function useDecodedText(decodedText) {
        if (processingResult || !decodedText) return;
        processingResult = true;

        tokenField.value = decodedText.trim();
        tokenField.dispatchEvent(new Event('input', { bubbles: true }));
        tokenField.dispatchEvent(new Event('change', { bubbles: true }));
        await stopScanner();
        setStatus('Code detected. Loading the matching record…', 'success');

        const callback = window[callbackName];
        if (typeof callback === 'function') {
            window.setTimeout(() => {
                processingResult = false;
                callback();
            }, 250);
        } else {
            processingResult = false;
            setStatus('Code detected. Use the button below to continue.', 'success');
        }
    }

    async function startScanner() {
        if (!ensureLibrary()) return;

        processingResult = false;
        setStatus('Requesting camera access…', 'info');
        reader.hidden = false;

        cameraRequestTimer = window.setTimeout(function () {
            if (!cameraRunning) {
                setStatus('Camera permission is still waiting. Allow camera access in the browser, or use Upload QR Photo instead.', 'warning');
            }
        }, 5000);

        try {
            await scanner.start(
                { facingMode: 'environment' },
                {
                    fps: 15,
                    qrbox: function (viewfinderWidth, viewfinderHeight) {
                        const edge = Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.84);
                        return { width: edge, height: edge };
                    }
                },
                useDecodedText,
                function () { /* Normal while no code is in view. */ }
            );
            if (cameraRequestTimer) {
                window.clearTimeout(cameraRequestTimer);
                cameraRequestTimer = null;
            }
            setCameraState(true);
            setStatus('Camera ready. Center the complete QR code in the frame and hold it steady.', 'info');
            scanHintTimer = window.setTimeout(function () {
                if (cameraRunning && !processingResult) {
                    setStatus('Still scanning. Increase screen brightness, move the QR slightly farther away, or use Upload QR Photo.', 'warning');
                }
            }, 10000);
        } catch (error) {
            if (cameraRequestTimer) {
                window.clearTimeout(cameraRequestTimer);
                cameraRequestTimer = null;
            }
            setCameraState(false);
            setStatus('Camera unavailable. Allow camera access, scan an image, or enter the token manually.', 'error');
        }
    }

    async function scanImage(file) {
        if (!file || !ensureLibrary()) return;

        await stopScanner();
        processingResult = false;
        reader.hidden = false;
        setStatus('Reading code from image…', 'info');

        try {
            const decodedText = await scanner.scanFile(file, true);
            await useDecodedText(decodedText);
        } catch (error) {
            reader.hidden = true;
            setStatus('No readable QR code or barcode was found in that image.', 'error');
        } finally {
            fileInput.value = '';
        }
    }

    startButton.addEventListener('click', startScanner);
    stopButton.addEventListener('click', async function () {
        await stopScanner();
        setStatus('Scanner stopped. You can scan again or enter the token manually.', 'info');
    });
    imageButton.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () { scanImage(fileInput.files[0]); });

    tokenField.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            const callback = window[callbackName];
            if (typeof callback === 'function') callback();
        }
    });

    const observer = new MutationObserver(function () {
        if (getComputedStyle(modal).display === 'none') stopScanner();
    });
    observer.observe(modal, { attributes: true, attributeFilter: ['style', 'class'] });

    window.SeniorlinkCodeScanner = {
        stop: stopScanner,
        scanText: useDecodedText
    };
}());
