(() => {
    const video = document.getElementById('video');
    const status = document.getElementById('camera-status');
    const placeholder = document.getElementById('camera-placeholder');
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d', {willReadFrequently: true});
    let stream, running = false, starting = false, frame, generation = 0;
    function stop() {
        generation++; running = false; cancelAnimationFrame(frame);
        if (stream) stream.getTracks().forEach(t => t.stop());
        stream = null; video.srcObject = null; video.hidden = true; placeholder.hidden = false;
    }
    function tick() {
        if (!running) return;
        if (video.readyState >= 2) {
            canvas.width = 640; canvas.height = Math.round(video.videoHeight / video.videoWidth * 640);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const result = jsQR(pixels.data, pixels.width, pixels.height);
            if (result?.data) {
                stop(); status.textContent = 'QR terbaca. Membuka checklist…';
                document.getElementById('qr-code').value = result.data;
                document.getElementById('qr-form').requestSubmit(); return;
            }
        }
        frame = requestAnimationFrame(tick);
    }
    document.getElementById('start-camera').addEventListener('click', async () => {
        if (starting || running) return;
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            status.textContent = 'Kamera membutuhkan HTTPS atau localhost. Gunakan kode mesin sementara.'; return;
        }
        if (typeof jsQR !== 'function') { status.textContent = 'Pembaca QR tidak tersedia. Gunakan kode mesin.'; return; }
        starting = true; const current = ++generation;
        try {
            const acquired = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}}, audio:false});
            if (current !== generation) { acquired.getTracks().forEach(t=>t.stop()); return; }
            stream = acquired; video.srcObject = stream; await video.play();
            if (current !== generation) return;
            video.hidden = false; placeholder.hidden = true; running = true;
            status.textContent = 'Arahkan kamera ke QR mesin.'; tick();
        } catch (e) { stop(); status.textContent = 'Kamera tidak dapat dibuka. Periksa izin kamera atau gunakan kode mesin.'; }
        finally { starting = false; }
    });
    document.getElementById('stop-camera').addEventListener('click', () => { stop(); status.textContent = 'Kamera dihentikan.'; });
    window.addEventListener('pagehide', stop);
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
})();
