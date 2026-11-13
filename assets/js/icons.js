// Lucide ikonice za HTML koji gradi JavaScript: isti ux_icon() kao u šablonima, preko <template id="kt-js-icons">
// (templates/components/_js_icons.html.twig). Nova ikonica se prvo doda tamo, pa se koristi ovde.
export function icon(name, extraClass = '') {
    const svg = document.querySelector(`#kt-js-icons`)?.content.querySelector(`[data-icon="${name}"] svg`);
    if (!svg) {
        return '';
    }
    const copy = svg.cloneNode(true);
    copy.setAttribute('class', `kt-icon ${extraClass}`.trim());
    copy.setAttribute('aria-hidden', 'true');

    return copy.outerHTML;
}
