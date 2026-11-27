document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-photo-cropper]').forEach(form => {
        const input = form.querySelector('[data-crop-input]');
        const stage = form.querySelector('[data-crop-viewport]');
        const image = form.querySelector('[data-crop-image]');
        const zoom = form.querySelector('[data-crop-zoom]');
        const fileName = form.querySelector('[data-crop-file-name]');
        const saveButton = form.querySelector('[data-photo-save]');
        if (!input || !stage || !image) return;
        if (saveButton) saveButton.disabled = !input.files?.length;

        let imageUrl = null;
        let scale = 1;
        let baseScale = 1;
        let offsetX = 0;
        let offsetY = 0;
        let drag = null;
        let prepared = false;

        const render = () => {
            if (!image.naturalWidth) return;
            const size = stage.clientWidth;
            const factor = baseScale * Number(zoom?.value || 1);
            const width = image.naturalWidth * factor;
            const height = image.naturalHeight * factor;
            offsetX = Math.min(0, Math.max(size - width, offsetX));
            offsetY = Math.min(0, Math.max(size - height, offsetY));
            image.style.width = `${width}px`;
            image.style.height = `${height}px`;
            image.style.left = `${offsetX}px`;
            image.style.top = `${offsetY}px`;
        };

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            prepared = false;
            if (!file) {
                if (saveButton) saveButton.disabled = true;
                if (fileName) fileName.textContent = 'No photo selected';
                return;
            }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
                input.setCustomValidity(file.size > 5 * 1024 * 1024 ? 'Choose an image no larger than 5 MB.' : 'Choose a JPG, PNG, or WEBP image.');
                input.reportValidity();
                input.value = '';
                if (saveButton) saveButton.disabled = true;
                return;
            }
            input.setCustomValidity('');
            if (imageUrl) URL.revokeObjectURL(imageUrl);
            imageUrl = URL.createObjectURL(file);
            image.onload = () => {
                const size = stage.clientWidth;
                baseScale = Math.max(size / image.naturalWidth, size / image.naturalHeight);
                offsetX = (size - image.naturalWidth * baseScale) / 2;
                offsetY = (size - image.naturalHeight * baseScale) / 2;
                if (zoom) { zoom.value = '1'; zoom.disabled = false; }
                if (saveButton) saveButton.disabled = false;
                render();
            };
            image.src = imageUrl;
            if (fileName) fileName.textContent = file.name;
        });

        zoom?.addEventListener('input', render);
        stage.addEventListener('pointerdown', event => {
            if (!input.files?.length) return;
            drag = {x: event.clientX, y: event.clientY, left: offsetX, top: offsetY};
            stage.setPointerCapture(event.pointerId);
        });
        stage.addEventListener('pointermove', event => {
            if (!drag) return;
            offsetX = drag.left + event.clientX - drag.x;
            offsetY = drag.top + event.clientY - drag.y;
            render();
        });
        const stopDrag = () => { drag = null; };
        stage.addEventListener('pointerup', stopDrag);
        stage.addEventListener('pointercancel', stopDrag);
        form.querySelector('[data-photo-skip]')?.addEventListener('click', () => {
            input.setCustomValidity('');
            input.value = '';
        });

        form.addEventListener('submit', async event => {
            const submitter = event.submitter;
            if (submitter?.matches('[data-photo-skip]')) {
                return;
            }
            if (prepared) return;
            if (!input.files?.length) {
                event.preventDefault();
                input.setCustomValidity('Choose a profile photo or select Skip for Now.');
                input.reportValidity();
                return;
            }
            event.preventDefault();
            input.setCustomValidity('');
            const canvas = document.createElement('canvas');
            canvas.width = 512;
            canvas.height = 512;
            const context = canvas.getContext('2d');
            const ratio = 512 / stage.clientWidth;
            context.drawImage(image, offsetX * ratio, offsetY * ratio,
                image.naturalWidth * baseScale * Number(zoom?.value || 1) * ratio,
                image.naturalHeight * baseScale * Number(zoom?.value || 1) * ratio);
            canvas.toBlob(blob => {
                if (!blob) {
                    input.setCustomValidity('The photo could not be cropped. Please choose another image.');
                    input.reportValidity();
                    return;
                }
                const croppedFile = new File([blob], 'fixmoto-profile.png', {type: 'image/png'});
                const transfer = new DataTransfer();
                transfer.items.add(croppedFile);
                input.files = transfer.files;
                prepared = true;
                form.requestSubmit(submitter || undefined);
            }, 'image/png');
        });
    });
});
