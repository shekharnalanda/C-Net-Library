(() => {
    const form = document.querySelector('#application');
    const camera = document.querySelector('#photoCamera');
    const gallery = document.querySelector('#photoGallery');
    const preview = document.querySelector('#photoPreview');
    const status = document.querySelector('#photoStatus');
    let version = 0, processing = false, previewUrl;

    async function select(input, other) {
        const file = input.files[0];
        if (!file) return;
        const current = ++version;
        other.value = '';
        gallery.required = input !== camera;
        processing = true;
        status.textContent = 'फोटो तैयार हो रही है…';
        try {
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                throw new Error('JPG, PNG या WebP फोटो चुनें।');
            }
            const image = new Image();
            const url = URL.createObjectURL(file);
            try {
                await new Promise((resolve, reject) => { image.onload = resolve; image.onerror = reject; image.src = url; });
                if (image.naturalWidth < 150 || image.naturalHeight < 150) throw new Error('कम से कम 150 × 150 पिक्सेल की साफ फोटो चुनें।');
                if (file.size > 1024 * 1024 || image.naturalWidth > 1600 || image.naturalHeight > 1600) {
                    const canvas = document.createElement('canvas');
                    const scale = Math.min(1, 1200 / Math.max(image.naturalWidth, image.naturalHeight));
                    canvas.width = Math.round(image.naturalWidth * scale);
                    canvas.height = Math.round(image.naturalHeight * scale);
                    canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
                    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.85));
                    if (current !== version) return;
                    if (blob && typeof DataTransfer !== 'undefined') {
                        const transfer = new DataTransfer();
                        transfer.items.add(new File([blob], 'student-photo.jpg', {type:'image/jpeg'}));
                        input.files = transfer.files;
                    }
                }
            } finally { URL.revokeObjectURL(url); }
            if (current !== version) return;
            if (input.files[0].size > 2 * 1024 * 1024) throw new Error('फोटो 2 MB से छोटी करके दोबारा चुनें।');
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(input.files[0]);
            preview.src = previewUrl;
            preview.hidden = false;
            status.textContent = 'फोटो चुन ली गई है। यही फोटो अप्रूवल के बाद डिजिटल आईडी कार्ड में आएगी।';
        } catch (error) {
            if (current !== version) return;
            input.value = '';
            gallery.required = true;
            preview.hidden = true;
            status.textContent = error.message || 'फोटो नहीं खुल सकी। दूसरी फोटो चुनें।';
        } finally { if (current === version) processing = false; }
    }
    camera.addEventListener('change', () => select(camera, gallery));
    gallery.addEventListener('change', () => select(gallery, camera));
    form.addEventListener('submit', event => {
        if (processing || (!camera.files.length && !gallery.files.length)) {
            event.preventDefault();
            status.textContent = processing ? 'फोटो तैयार होने दें, फिर आवेदन जमा करें।' : 'कैमरे से फोटो लें या मोबाइल से फोटो चुनें।';
        }
    });
})();
