// Uputstvo (SPEC 8o): pretraga po poglavljima (bez obzira na kvačice) i dugme "nazad na sadržaj".
const box = document.querySelector('[data-kt-help]');

if (box) {
    const normalize = (s) => s.toLocaleLowerCase('sr').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'dj');
    const sections = [...box.querySelectorAll('[data-kt-help-section]')].map((el) => ({
        el,
        id: el.querySelector('h2')?.id,
        text: normalize(el.textContent),
    }));
    const intro = box.querySelector('.kt-help-intro');
    const search = box.querySelector('[data-kt-help-search]');
    const empty = box.querySelector('[data-kt-help-empty]');
    let timer = null;

    const filter = () => {
        const words = normalize(search.value.trim()).split(/\s+/).filter(Boolean);
        let shown = 0;
        sections.forEach(({ el, id, text }) => {
            const match = words.every((w) => text.includes(w));
            el.hidden = !match;
            const toc = id ? box.querySelector(`[data-kt-help-toc="${CSS.escape(id)}"]`) : null;
            if (toc) {
                toc.hidden = !match;
            }
            shown += match ? 1 : 0;
        });
        if (intro) {
            intro.hidden = words.length > 0;
        }
        empty.hidden = shown > 0;
    };

    search?.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(filter, 150);
    });

    // dugme za povratak se pojavljuje kad sadržaj izađe iz ekrana
    const top = document.querySelector('[data-kt-help-top]');
    const toc = box.querySelector('.kt-help-toc');
    if (top && toc && 'IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            top.hidden = entry.isIntersecting;
        }).observe(toc);
    }
}
