// Modal "Dodaj paket" (SPEC 8h.3a): po izboru paketa prikazuju se svi njegovi poslovi, štiklirani.
// Odštikliran posao dobija polje za razlog i čuva se kao isključen; posao koji klijent već ima dobija upozorenje.
const escape = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

document.querySelectorAll('form[data-kt-package-attach]').forEach((form) => {
    const packages = JSON.parse(form.dataset.packages || '{}');
    const existing = JSON.parse(form.dataset.existing || '[]').map(String);
    const select = form.querySelector('select[name$="[package]"]');
    const price = form.querySelector('input[name$="[agreedPrice]"]');
    const box = form.querySelector('[data-kt-package-jobs]');
    const list = form.querySelector('[data-kt-package-job-list]');
    let autoPrice = '';

    const render = () => {
        const pkg = packages[`p${select.value}`];
        box.hidden = !pkg;
        list.innerHTML = '';
        if (!pkg) {
            return;
        }
        pkg.jobs.forEach((job) => {
            const dup = existing.includes(String(job.id));
            const li = document.createElement('li');
            li.className = 'list-group-item';
            li.innerHTML = `<label class="d-flex align-items-start gap-2 mb-0">
                    <input type="checkbox" class="form-check-input mt-1" name="include[]" value="${job.id}" checked>
                    <span class="flex-grow-1 min-w-0"><span class="d-block">${escape(job.name)}</span><span class="kt-text-sm text-muted">${escape(job.describe)}</span>
                    ${dup ? '<span class="d-block kt-text-sm text-warning">Klijent već ima ovaj posao: neće biti dupliran, zadatak nastaje jednom po periodu.</span>' : ''}</span>
                </label>
                <input type="text" class="form-control form-control-sm mt-2" name="reason[${job.id}]" placeholder="Razlog isključenja (opciono), npr. klijent nije u sistemu PDV-a" maxlength="255" hidden>
                ${dup ? '' : `<div class="mt-1 ms-4" data-kt-sched-row data-job-id="${job.id}" data-job-name="${escape(job.name)}" data-default='${escape(JSON.stringify(job.schedule || {}))}' data-default-text="${escape(job.describe)}" data-anchor="${escape(form.querySelector('input[name$="[startDate]"]')?.value || '')}"></div>`}`;
            list.append(li);
            li.dispatchEvent(new CustomEvent('kt:content', { bubbles: true }));
        });
        // predložena cena paketa, ako cena nije ručno upisana
        if (price && (price.value === '' || price.value === autoPrice)) {
            autoPrice = pkg.price || '';
            price.value = autoPrice;
            price.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    list.addEventListener('change', (e) => {
        const check = e.target.closest('input[name="include[]"]');
        if (check) {
            const reason = check.closest('li').querySelector('input[name^="reason["]');
            reason.hidden = check.checked;
            check.closest('li').classList.toggle('kt-row-excluded', !check.checked);
            // isključen posao nema raspored za klijenta: polje se ne šalje
            const sched = check.closest('li').querySelector('[data-kt-sched-row]');
            if (sched) {
                sched.hidden = !check.checked;
                sched.querySelector('input[type="hidden"]').disabled = !check.checked;
            }
            if (!check.checked) {
                reason.focus();
            }
        }
    });
    select.addEventListener('change', render);
    render();
});
