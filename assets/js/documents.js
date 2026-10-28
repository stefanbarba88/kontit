// Dokumenti korisnika (SPEC 8l): otpremanje preko FilePond-a (fajlovi idu uz formu, storeAsFile),
// pregled PDF-a preko PDF.js u modalu, a slika u lightbox-u. Fajlovi se uvek čitaju kroz kontroler sa proverom prava.
import { Modal } from 'bootstrap';

const input = document.querySelector('input[data-kt-filepond]');
if (input) {
    Promise.all([
        import('filepond'),
        import('filepond-plugin-file-validate-type'),
        import('filepond-plugin-file-validate-size'),
        import('filepond/dist/filepond.min.css'),
    ]).then(([FilePond, { default: ValidateType }, { default: ValidateSize }]) => {
        FilePond.registerPlugin(ValidateType, ValidateSize);
        input.removeAttribute('required');
        FilePond.create(input, {
            storeAsFile: true,
            allowMultiple: true,
            credits: false,
            acceptedFileTypes: ['application/pdf', 'image/jpeg', 'image/png'],
            maxFileSize: '20MB',
            labelIdle: 'Prevuci fajlove ovde ili <span class="filepond--label-action">izaberi</span>',
            labelFileTypeNotAllowed: 'Nedozvoljen tip fajla',
            fileValidateTypeLabelExpectedTypes: 'Dozvoljeni su PDF, JPG i PNG',
            labelMaxFileSizeExceeded: 'Fajl je prevelik',
            labelMaxFileSize: 'Najviše {filesize}',
            labelFileProcessingError: 'Greška pri otpremanju',
            labelTapToCancel: 'dodirni za otkazivanje',
            labelTapToRetry: 'dodirni za ponovni pokušaj',
            labelTapToUndo: 'dodirni za poništavanje',
            labelButtonRemoveItem: 'Ukloni',
        });
    });
}

const viewer = document.getElementById('doc-viewer');
if (viewer) {
    const body = viewer.querySelector('[data-kt-doc-body]');
    const title = viewer.querySelector('.modal-title');
    const pages = viewer.querySelector('[data-kt-doc-pages]');
    let task = null;

    const renderPdf = async (url) => {
        const pdfjs = await import('pdfjs-dist');
        pdfjs.GlobalWorkerOptions.workerSrc = import.meta.resolve('pdfjs-dist/build/pdf.worker.min.mjs');
        task = pdfjs.getDocument({ url, withCredentials: true });
        const pdf = await task.promise;
        pages.textContent = `${pdf.numPages} ${pdf.numPages === 1 ? 'strana' : 'strana'}`;
        const width = Math.min(body.clientWidth - 32, 1000);
        for (let n = 1; n <= pdf.numPages; n += 1) {
            const page = await pdf.getPage(n);
            const base = page.getViewport({ scale: 1 });
            const scale = width / base.width;
            const viewport = page.getViewport({ scale: scale * (window.devicePixelRatio || 1) });
            const canvas = document.createElement('canvas');
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            canvas.style.width = `${width}px`;
            canvas.className = 'kt-doc-page';
            body.append(canvas);
            await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        }
    };

    document.addEventListener('click', async (e) => {
        const button = e.target.closest('[data-kt-doc-view]');
        if (!button) {
            return;
        }
        title.textContent = button.dataset.title;
        pages.textContent = '';
        body.innerHTML = '<div class="text-center p-5"><span class="kt-loader"><i></i><i></i><i></i><i></i></span></div>';
        Modal.getOrCreateInstance(viewer).show();
        try {
            if (button.dataset.kind === 'image') {
                const img = new Image();
                img.alt = button.dataset.title;
                img.className = 'kt-doc-image';
                img.onload = () => { body.innerHTML = ''; body.append(img); };
                img.src = button.dataset.ktDocView;
            } else {
                await new Promise((r) => { window.setTimeout(r, 50); });
                body.innerHTML = '';
                await renderPdf(button.dataset.ktDocView);
            }
        } catch (err) {
            body.innerHTML = `<p class="text-danger p-3">Dokument ne može da se prikaže. <a href="${button.dataset.ktDocView}?download=1">Preuzmi ga</a>.</p>`;
        }
    });
    viewer.addEventListener('hidden.bs.modal', () => {
        task?.destroy();
        task = null;
        body.innerHTML = '';
    });
}
