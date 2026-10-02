(() => {
    'use strict';
    const button = document.getElementById('cnet-app-install');
    const status = document.getElementById('cnet-app-status');
    const help = document.getElementById('cnet-app-help');
    if (!button || !status || !help) return;
    let promptEvent = null;
    let installed = false;
    let opening = false;
    const standalone = window.matchMedia('(display-mode: standalone)');
    const showInstalled = () => {
        installed = true;
        promptEvent = null;
        button.disabled = true;
        button.textContent = 'ऐप खुला है';
        status.textContent = 'आप लाइब्रेरी ऐप में हैं।';
    };
    if (standalone.matches || window.navigator.standalone === true) showInstalled();
    standalone.addEventListener?.('change', (event) => { if (event.matches) showInstalled(); });
    window.addEventListener('beforeinstallprompt', (event) => {
        if (installed) return;
        event.preventDefault();
        promptEvent = event;
        status.textContent = 'ऐप इंस्टॉल करने के लिए ऊपर का बटन दबाएँ।';
    });
    window.addEventListener('appinstalled', () => {
        showInstalled();
        status.textContent = 'ऐप इंस्टॉल हो गया है। अपनी होम स्क्रीन से C-Net Library खोलें।';
        button.textContent = 'ऐप इंस्टॉल हो गया';
    });
    button.addEventListener('click', async () => {
        if (installed || opening) return;
        if (!promptEvent) {
            help.open = true;
            status.textContent = 'नीचे दिए तरीके से अपने ब्राउज़र के मेनू से ऐप जोड़ें।';
            return;
        }
        const event = promptEvent;
        promptEvent = null;
        opening = true;
        try {
            await event.prompt();
            const choice = await event.userChoice;
            if (!installed) {
                status.textContent = choice.outcome === 'accepted'
                    ? 'इंस्टॉल का अनुरोध स्वीकार हुआ। अपनी होम स्क्रीन पर C-Net Library देखें।'
                    : 'इंस्टॉल रोक दिया गया। बाद में ब्राउज़र के मेनू से ऐप जोड़ सकते हैं।';
                help.open = choice.outcome !== 'accepted';
            }
        } catch (_) {
            help.open = true;
            status.textContent = 'ब्राउज़र के मेनू से ऐप जोड़ने का तरीका नीचे दिया है।';
        } finally { opening = false; }
    });
    if ('serviceWorker' in navigator && window.isSecureContext) {
        navigator.serviceWorker.register('/library-app-sw.js', {scope: '/', updateViaCache: 'none'})
            .catch(() => {
                if (!installed && !promptEvent) {
                    help.open = true;
                    status.textContent = 'पेज दोबारा खोलें या नीचे दिए ब्राउज़र मेनू से ऐप जोड़ें।';
                }
            });
    }
})();
