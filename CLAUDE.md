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
- Twig + Bootstrap 5.3 (+ Bootstrap Icons)
- AssetMapper (bez Node/Webpacka, osim ako ne odlučimo drugačije)
- jQuery za AJAX i interakcije (preko AssetMapper importmap-a), Chart.js za grafikone, SortableJS za drag & drop
- Symfony Messenger (doctrine transport) za asinhrono slanje mailova
- Paketi: `symfonycasts/reset-password-bundle`, `knplabs/knp-paginator-bundle`,
  `vich/uploader-bundle` (avatari), `liip/imagine-bundle` (thumbnail avatara)

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

UI template (OBAVEZNO)

Izgled aplikacije se pravi isključivo na osnovu gotovih template-a u _template/:

_template/admin/: Bootstrap 5 admin template (izgled cele aplikacije)

Pravila:

Pre prvog UI posla pregledaj ceo _template/admin/ i napravi TEMPLATE_INVENTORY.md: spisak stranica i čemu koja služi, komponente (kartice, tabele, forme, modali, badge-ovi, tabovi, dropdown, timeline, avatari), set ikonica i način korišćenja, uključeni JS dodaci i boje i CSS varijable. Posle toga koristi taj popis, ne pretražuj template ispočetka svaki put.
Layout (base.html.twig, sidebar, topbar, login) se prenosi iz template-a što vernije. Komponente koje se ponavljaju idu u templates/components/ kao Twig include ili macro.
CSS i JS iz template-a se kopiraju u assets/vendor/template/ i ne menjaju se. Sve naše izmene idu u assets/styles/app.css.
Ikonice samo iz seta koji template koristi. Ne dodavati drugi icon set.
Kad komponenta ne postoji u template-u, napravi je u istom stilu, od postojećih klasa template-a i Bootstrap utility klasa. Ne uvoditi nove UI biblioteke bez pitanja.
JS dodatke (datepicker, select, grafikoni) uzimaj iz template-a ako ih ima, pre nego što dodaš nove.

_template/ je samo izvor i ne servira se javno (nije u public/).

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

## Dizajn (DESIGN.md)
Pre svakog UI posla pročitaj `DESIGN.md`. Template određuje **strukturu** (layout, raspored, komponente), a `DESIGN.md` određuje **izgled** (boje, tipografija, razmaci, senke, teme, detalji). Kad se razlikuju, važi `DESIGN.md`.
