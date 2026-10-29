# CLAUDE.md — Knjigovodstvo App

Ovaj fajl Claude Code čita automatski na početku svake sesije. Ovde su **trajna pravila**.
Šta aplikacija radi je u `SPEC.md`, a šta je do sada urađeno je u `PROGRESS.md`.

## Obavezno na početku svake sesije
1. Pročitaj `SPEC.md` (specifikacija) i `PROGRESS.md` (trenutno stanje).
2. Pogledaj `git log --oneline -20` i strukturu `src/Entity`, `src/Controller`.
3. Ne pravi ponovo nešto što već postoji, nego nadogradi postojeće.
4. Ako nešto iz SPEC.md nije jasno ili je u sekciji "Nedoumice", **pitaj pre implementacije**.

## Obavezno na kraju svakog zadatka
1. Ažuriraj `PROGRESS.md`: šta je urađeno, koje su migracije dodate, šta je sledeće.
2. Ako je doneta nova odluka (npr. rešena nedoumica), upiši je u `SPEC.md` i ukloni je iz nedoumica.
3. Predloži commit poruku (konvencija: `feat:`, `fix:`, `refactor:`, `docs:`).

## Stack
- PHP 8.3+, Symfony 7.4 LTS (full webapp skeleton)
- MySQL 8, Doctrine ORM + Doctrine Migrations
- Twig + Bootstrap 5.3 (bez kupljenog template-a), tokeni i komponente iz DESIGN.md
- Ikonice: Lucide preko `symfony/ux-icons`
- AssetMapper (bez Node/Webpacka, osim ako ne odlučimo drugačije)
- jQuery za AJAX i interakcije (preko AssetMapper importmap-a)
- Frontend biblioteke (importmap): ApexCharts (grafikoni), SortableJS (drag & drop), SweetAlert2 (potvrde i toast), Tom Select,
  flatpickr (sr locale), IMask, CountUp.js, FilePond, PDF.js (`pdfjs-dist`), autosize, FullCalendar 6 (MIT paketi)
- Symfony Messenger (doctrine transport) za asinhrono slanje mailova
- Paketi: `symfonycasts/reset-password-bundle`, `knplabs/knp-paginator-bundle`, `symfony/ux-icons`,
  `dompdf/dompdf` (PDF), `phpoffice/phpspreadsheet` (Excel), `league/csv` (CSV),
  `knpuniversity/oauth2-client-bundle` + `league/oauth2-google` + `google/apiclient` (samo Calendar servis) za Google kalendar,
  `twig/markdown-extra` + `league/commonmark` za uputstvo u aplikaciji (`docs/uputstvo/*.md`)
- Avatar je samo inicijali (bez upload-a slike). `vich/uploader-bundle` i `liip/imagine-bundle` su uklonjeni. Prilozi (FilePond) se čuvaju bez Vich-a: `UserDocumentService`, van `public/`, a fajl se servira samo kroz kontroler sa Voterom.

## Konvencije koda
- **Kod, entiteti, kolone, rute na engleskom**, a **UI tekst na srpskom (latinica)**.
- Statusi, prioriteti i role kao **PHP backed enum** (`src/Enum/`).
- Kontroleri su tanki, a poslovna logika ide u `src/Service/`.
- Prava pristupa: **Symfony Voters** (`src/Security/Voter/`), nikad `if role` razbacano po kontrolerima i Twigu.
  U Twigu se koristi `is_granted('TASK_EDIT', task)`.
- Forme preko Symfony Form komponente, a validacija preko Assert atributa na entitetu.
- Svaki entitet ima `createdAt`, `updatedAt` (trait `TimestampableTrait`).
- Brisanje korisnika i klijenata je **soft** (`isActive=false` / `deletedAt`), bez fizičkog brisanja.
- Svaka promena na Task, ChecklistItem, Client i Assignment se beleži u `ActivityLog` preko Doctrine listenera.
- AJAX rute: prefiks `/ajax/...`, vraćaju `JsonResponse` u formatu `{ "success": bool, "data": ..., "message": "..." }`.
  CSRF token se šalje u headeru `X-CSRF-Token`.
- Posle svake izmene entiteta: `php bin/console make:migration`, pregledaj migraciju, pa je pokreni.
- Ne menjaj već izvršene migracije, nego napravi novu.
- Izvoz (Excel / CSV / PDF) uvek koristi isti upit i iste Votere kao lista koju izvozi.

## UI sistem (OBAVEZNO)
1. Izgled se gradi na **Bootstrap 5.3 + tokenima i komponentama iz `DESIGN.md`**. Nema kupljenog template-a.
2. Pre svakog UI posla pročitaj `DESIGN.md`.
3. Dozvoljene biblioteke su **samo one iz `DESIGN.md` / `docs/archive/UPDATE-DIZAJN.md` sekcije 2**. Nova biblioteka samo uz odobrenje korisnika.
4. Ikonice **samo Lucide** preko `ux_icon()`. Posle dodavanja novih pokrenuti `php bin/console ux:icons:lock`.
5. Vendor CSS se ne menja, a izgled biblioteka se prepisuje u `assets/styles/components/`.
6. **Mobile-first**: svaki novi ekran se pravi i proverava na 390px pre desktopa. Tabela na telefonu je uvek lista (`DESIGN.md` 16).
7. Svaka nova komponenta se dodaje na `/_design` vodič, u obe teme.
8. Komponente koje se ponavljaju idu u `templates/components/` kao Twig include ili macro.

## Struktura
```
src/
  Controller/        (Admin/, Team/, Client/, Task/, Dashboard/, Ajax/)
  Entity/
  Enum/
  Repository/
  Security/Voter/
  Service/           (TaskCompletionService, StatisticsService, ActivityLogger...)
  EventListener/     (ActivityLogListener)
  Form/
templates/
  base.html.twig
  components/        (kartice, badge-ovi, modali)
assets/js/           (po modulu: tasks.js, checklist.js, comments.js)
```

## Role (kratko, detaljno u SPEC.md)
`ROLE_SUPERADMIN` > `ROLE_ADMIN` > `ROLE_SEF` > `ROLE_RADNIK` (preko role_hierarchy).
Da li je neko "radnik u praksi" zavisi od toga da li mu je dodeljen klijent ili zadatak, a ne od role.

## Potvrda kritičnih radnji
Svaka nova radnja koja briše, deaktivira, otkazuje, menja prava, menja dodelu ili utiče na više zapisa **mora** imati SweetAlert potvrdu preko globalnog `data-confirm` mehanizma (`assets/js/confirm.js`, Twig makro u `templates/components/`). Nikad `confirm()` iz browsera. Potvrda je samo UI sloj: server uvek proverava prava (Voter) i CSRF.
