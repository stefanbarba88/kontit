# TEMPLATE_INVENTORY.md

Pregled `_template/` (Limitless 6, Bootstrap 5). `_template/` je samo izvor i ne servira se javno.
Napomena: `_template/email/` **ne postoji**; `templates/emails/base.html.twig` je napravljen ručno u istom stilu.

## Stranice u `_template/admin/` (po kategorijama)
| Prefiks | Br. | Šta sadrži |
|---|---|---|
| sidebar_* | 29 | varijante sidebara (dark/light, mini, collapsed, secondary) |
| components_* | 21 | accordion, alerts, badges, breadcrumbs, buttons, dropdowns, list group, modals, offcanvas, pagination, pills, progress, tabs, toasts, tooltips... |
| datatable_* | 20 | DataTables (basic, sorting, responsive, buttons, select...) |
| form_* | 17 | inputs, select, checkboxes, validation, wizard, layouts |
| navbar_* / navigation_* | 14 / 12 | topbar varijante, meniji, tabovi |
| login_* | 12 | login, registracija, reset lozinke, lock, 2FA |
| layout_* | 10 | raspored (fixed, boxed, static...) |
| colors_* | 11 | paleta boja |
| echarts, d3, c3 | 8/8/5 | grafikoni |
| error_* | 6 | 403/404/500 |
| user_*, table_*, gridjs_*, task_*, mail_*, invoice_*, ... | | profili, tabele, task board, pošta, fakture |

## Layout (obrasci klasa)
- Body: `navbar navbar-dark navbar-expand-lg navbar-static` (topbar) + `.page-content` > `.sidebar.sidebar-dark.sidebar-main.sidebar-expand-lg` + `.content-wrapper` > `.content-inner` > `.content`.
- Sidebar: `.nav.nav-sidebar` > `.nav-item` > `.nav-link`, naslovi `.nav-item-header`.
- Zaglavlje stranice: `.page-header.page-header-light` + `.breadcrumb`.
- Login: `.login-form` kartica u centriranom `.content`.

## Komponente (koje koristimo)
- Kartice `.card > .card-header + .card-body`, tabele `.table.table-hover .table-responsive`.
- Alerti `.alert.alert-*.alert-icon-start` + `.alert-icon`, badge-ovi `.badge.bg-*.bg-opacity-10/20`.
- Forme: `.form-control`, `.form-select`, `.form-control-feedback(-start)` + `.form-control-feedback-icon`, `.invalid-feedback`.
- Dugmad `.btn .btn-primary/-light/-outline-*`, `.btn-icon`; avatari `.w-24..w-80 .h-*` + `.rounded-circle`; `.status-indicator`.
- Paginacija: Bootstrap 5 (`bootstrap_v5_pagination` iz KnpPaginator).

## Skup ikonica
**Phosphor** (`_template/assets/icons/phosphor`). Koristi se kao `<i class="ph-list"></i>` (bez `ph` bazne klase). Pre upotrebe proveriti da ikonica postoji u `assets/vendor/template/icons/phosphor/styles.min.css` (npr. `ph-user-check` NE postoji, koristi se `ph-user-circle`).

## JS plugini dostupni u `_template/assets/js/vendor/`
editors (quill, ckeditor, ace, trumbowyg), extensions, forms (inputs, selects, tags, validation, wizards), internationalization, maps, media (cropper, glightbox), notifications (noty, sweet_alert, bootbox), pickers (datepicker, daterangepicker, color), sliders, tables (datatables, gridjs), trees, ui (dragula, fullcalendar, moment...), uploaders (dropzone, fileinput, plupload), visualization (c3, d3, echarts). Nisu još uvezeni; kopirati po potrebi u `assets/vendor/template/`.

## Boje i CSS promenljive
`--primary: #0c83ff`, `--teal: #26A69A`; takođe indigo, pink, purple, yellow (klase `bg-*`, `text-*`). Font: Inter (`--font-sans-serif`).

## Šta je kopirano u `assets/vendor/template/` (neizmenjeno)
`css/all.min.css`, `css/animate.min.css`, `js/app.js`, `js/bootstrap.bundle.min.js`, `icons/phosphor/` (css + fonts), `fonts/inter/`.
Učitavanje: CSS preko `asset()` u `base.html.twig`, JS uvozom iz `assets/app.js`.

## Napomene
- `all.min.css` referiše slike koje nisu kopirane (leaflet, login_cover.jpg, boxed_bg.png) pa je `missing_import_mode: warn` u `asset_mapper.yaml`.
- Naše dopune (ne diramo template): `assets/styles/app.css` (`.object-fit-cover`, `.border-dashed`, `.badge-team`).
