# UI-AUDIT.md — pregled ikonica, naziva, dugmadi i razmaka (Faza A, samo popis)

Datum: 2026-11-13. **Ništa nije menjano u kodu.** Ovo je popis za odluku; ispravke (Faza B) idu tek posle potvrde.
Prioriteti: **P1** vidljivo pokvareno ili dvosmisleno, **P2** nedoslednost koju korisnik primećuje, **P3** higijena.

**Metod i granice.** Statičko skeniranje `templates/`, `assets/js/`, `src/` (Twig, JS, PHP stringovi) skriptama; stranica `/_design` je izuzeta iz brojanja neusklađenosti (prikazuje i loše primere namerno). Izgled u browseru (tamna tema, 390 px) **nije proveren** jer Playwright nema instalirane browsere; te stavke su označene "proveriti ručno". Brojevi su broj pojavljivanja u UI tekstu, bez komentara i identifikatora.

---

## 1. Ikonice

### 1.1 Stanje
- Svuda se koristi Lucide preko `ux_icon()`. **Bootstrap Icons, Font Awesome i druge biblioteke: nema** (nijedna `bi-`/`fa-` klasa).
- Dinamička imena (`'lucide:' ~ icon`) dolaze iz: `NotificationType::icon()`, `ActivityAction::icon()`, `NonWorkingDayType::icon()`, `ThemePreference::icon()`, `HelpGuide::icon()`, polja `icon` na `AbsenceType` i `JobCategory` (bira se u formi), `nav.link(...)`. Nova ikonica se mora zaključati (`ux:icons:lock`) pa dinamička imena treba proveravati posebno (P3: test koji prolazi kroz sva imena iz enuma).

### 1.2 Ikonice po značenju (kako se sada koriste)
| Značenje | Lucide | Gde |
|---|---|---|
| Dodaj / novo | `plus` (26×) | dugmad "Dodaj", "Novi …" |
| Izmeni | `pencil` (19×) | liste, kartice |
| Obriši | `trash-2` (12×) | brisanje, ActivityAction::DELETED |
| Sačuvaj | `save` (11×) | forme |
| Završi / potvrdi | `check` (10×), `circle-check` (4×) | zadatak, ActivityAction::APPROVED |
| Zatvori / ukloni | `x` | modali, UNCHECKED |
| Vrati / poništi | `undo-2`, `rotate-ccw` | RETURNED, Poništi |
| Pretraga, filter | `search`, `filter` | liste |
| Upozorenje | `triangle-alert` (8×), `circle-alert` | backup, kašnjenje |
| Info | `info` (6×) | uputne napomene |
| Zadaci | `list-checks` (20×) | meni, naslovi, zadatak |
| Klijent | `building-2` | meni, notifikacije |
| Moji klijenti | `briefcase` | meni |
| Korisnici / tim | `contact` (korisnici), `users` (timovi, Moj tim) | meni |
| Kalendar | `calendar-days` (meni), `calendar` (datum), `calendar-clock` (raspored/rok), `calendar-check`, `calendar-x` (neradni dan, odbijeno odsustvo) | |
| Odsustvo | `plane` (meni), `palmtree` (vrsta odsustva, kolektivni odmor) | |
| Podsetnik | `notebook-pen` (meni, dashboard), `list-checks` (topbar, panel) | |
| Obavesti me | `bell`, `bell-plus` (Dodaj u podsetnik) | |
| Posebna potvrda | `shield-check` (15×) | meni, zadatak, kartica |
| Dokumenti | `folder`, `file-text`, `image` | |
| Preuzimanje | `download` (izvoz), `cloud-download` (NBS) | |
| Tema | `sun`, `moon`, `monitor` | |

### 1.3 Nedoslednosti
| # | Problem | Gde | Predlog | Prior. |
|---|---|---|---|---|
| I1 | **Dva značenja za `list-checks`**: Zadaci, Checklista, Katalog poslova, Novi zadatak i **Podsetnik** (topbar/panel) dele istu ikonicu, a Podsetnik u meniju ima `notebook-pen`. Topbar dugme i meni za isti modul imaju različite ikonice. | `base.html.twig` (topbar), `_panel.html.twig`, `NotificationType::TODO_REMINDER` | Podsetnik svuda `notebook-pen`; `list-checks` samo Zadaci. Checklistu staviti na `square-check-big`, katalog poslova na `library` (predlog, bira korisnik) | P1 |
| I2 | **Zadaci imaju dve ikonice**: `list-checks` (5×) i `circle-user` ("Moj pregled" na dashboardu, sada naslov bloka "Zadaci") | `dashboard/index.html.twig` (naslov bloka) | `list-checks` | P2 |
| I3 | `circle-user` znači i Moj profil, i Glavni zaduženi, i (dashboard) zadaci | `base.html.twig`, `client/*` | Moj profil `circle-user`; glavni zaduženi `user-round-check` | P2 |
| I4 | **`users` za Timove i za Moj tim** (ista ikonica za listu i za jedan tim) i `contact` za Korisnike | `nav` | prihvatljivo; Korisnici `users`, Timovi `users-round` (jedna ikonica po stavci menija) | P3 |
| I5 | **`plus` se koristi i kao ikonica dugmeta "Dodaj" i kao deo "Novi X"**, a `Novi klijent` ima i `building-2` na drugom mestu | `client/index`, `design` | Svi "Dodaj"/"Novi" = `plus`; entitetske ikonice samo u naslovima i meniju | P2 |
| I6 | **`download` je i CSV izvoz, i NBS uvoz praznika, i Izvoz**; `cloud-download` je NBS i "Otpremi" (upload!) | `export.html.twig`, `admin/finance/*`, `document/*` | Izvoz = `download`; uvoz iz NBS = `cloud-download`; **Otpremi = `upload`** (sada `cloud-download`, obrnut smer) | P1 |
| I7 | `check` je istovremeno "Moj deo je gotov" (3×), Odobri, Potvrdi, Završi, Završi deo kolege | `task/show`, `absence/index` | Završi = `check`; Odobri/Potvrdi = `circle-check`; Moj deo je gotov = `check-check` | P2 |
| I8 | `calendar-clock` označava pet različitih stvari (Raspored izvršavanja, Raspored za klijenta, Izaberi…, Rok i raspored, Promeni rok) | `schedule-*`, `task/show`, `job_form` | Raspored = `calendar-clock`; Promeni rok = `calendar-pen`; Izaberi datum = `calendar` | P3 |
| I9 | `refresh-cw` (Generisanje zadataka, Generiši sada, Backup/zamena, Nastavi rad). Backup/zamena i Nastavi rad nemaju veze sa osvežavanjem | `admin/generation`, `client/*`, `task/show` | Backup/zamena `repeat-2`; Nastavi rad `play` | P3 |
| I10 | `shield-check` i za posebnu potvrdu, i za "Traži potvrdu", i za admin vodič (`HelpGuide::ADMIN`) | `HelpGuide`, `task/*` | Admin vodič `shield-user` | P3 |
| I11 | `triangle-alert` za "Kasni", "Bez backup-a" i "NBS nije podešen", a `circle-alert` za TASK_OVERDUE | `NotificationType`, `dashboard` | Kašnjenje = `circle-alert` (crveno); upozorenja/rizik = `triangle-alert` (žuto) | P2 |
| I12 | `bell` je Notifikacije, ali i "Obaveštenja na mail", "Nema notifikacija" i Obavesti me | `notification/*`, `todo.js` | Notifikacije/zvono `bell`; Obavesti me `bell-ring` | P3 |
| I13 | `history` je Aktivnost, Istorija izmena, Istorija i "Završene usluge" | `client/show`, `admin/user/show` | Istorija izmena `history`; Završene usluge `archive` | P3 |
| I14 | `lock` i kao "privatno", i "Prvo izaberi klijenta", i napomena u Podsetniku | `todo/*`, `task/form` | `lock` samo privatnost; "Prvo izaberi klijenta" je `info` | P3 |
| I15 | Meni: Katalog poslova `list-checks`, Kategorije dokumenata `folder`, Vrste odsustva `palmtree` (isti `palmtree` je Kolektivni odmor) | `base.html.twig`, `NonWorkingDayType` | Vrste odsustva `calendar-range` | P3 |

### 1.4 Ostaci koji nisu Lucide ni `ux_icon`
| # | Šta | Gde | Predlog | Prior. |
|---|---|---|---|---|
| S1 | **Ručni inline `<svg>`** umesto `ux_icon`: izbor posla (drag ručka i X), clear u poljima, toggle lozinke, ikona u mobilnoj navigaciji | `assets/js/job-picker.js:39,41`, `forms.js:72,105,110`, `mobile.js:47` | zameniti Lucide putanjama iz zaključanih ikonica ili `<template>` sa `ux_icon` (`grip-vertical`, `x`, `eye`, `search`) | P2 |
| S2 | **Glif ▲ ▼** za promenu u KPI kartici | `components/ui.html.twig:125` | `trending-up` / `trending-down` | P2 |
| S3 | **Tekstualne strelice →** u UI tekstu (istorija izmena "staro → novo", istorija pozicije) | `components/timeline.html.twig:9`, `admin/user/show.html.twig:138` | `arrow-right` (Lucide, mala) ili ostaviti "→" samo u istoriji; odluka | P3 |
| S4 | **✓** u naslovu događaja koji ide **van aplikacije** (Google kalendar, ICS) | `Google/CalendarSync.php:211`, `Service/IcsExporter.php:46` | Namerno (tekst u tuđem kalendaru); ostaviti, ali upisati kao izuzetak u DESIGN.md | P3 |
| S5 | Emoji u šablonima i JS-u | **nema** (proveren opseg 1F300–1FAFF i 2600–27BF) | — | — |
| S6 | `•` u placeholderu lozinke | `security/login.html.twig:29` | ostaje | — |

---

## 2. Nazivi i rečnik

Brojevi su pojavljivanja u UI tekstu. "Obično" = nema šta da se bira, primenjujem u Fazi B ako se složiš. **Pitanja za tebe** su označena **?**.

| Par / grupa | Danas (broj) | Predlog jedne reči | |
|---|---|---|---|
| zadatak / task | zadatak 145; "task" samo u kodu | **zadatak** | obično |
| klijent / komitent / stranka | klijent 110; komitent 0; stranka 0 | **klijent** | obično |
| Sačuvaj / Snimi | Sačuvaj 35; Snimi 0 | **Sačuvaj** | obično |
| Izmeni / Uredi / Promeni | Izmeni/Izmena 56; Uredi 0; Promeni 17 | **Izmeni** za radnju nad zapisom; **Promeni** samo za vrednost (lozinka, rok, status) | obično |
| Dodaj / Novi / Kreiraj / Napravi | Dodaj 31; Novi/Nova 69; Kreiraj 2; Napravi 1 | **Dodaj** = stavka u postojeću listu/vezu ("Dodaj paket"); **Novi X** = zaglavlje liste ("Novi klijent"); **Kreiraj** i **Napravi** se ukidaju | obično |
| Odjava | Odjava 3 | **Odjava** | obično |
| Pošalji / Predaj | Pošalji 9; Predaj 0 | **Pošalji** ("Pošalji na proveru") | obično |
| rok / termin | rok 58; termin 0 | **rok** | obično |
| uputstvo / pomoć | uputstvo 8; pomoć 0 | **Uputstvo** | obično |
| Došlo je do greške / Greška | Greška 9 | **Greška** u naslovu, rečenica "Nešto nije u redu. Pokušaj ponovo." | obično |
| obraćanje | **ti** 22; vi 3 (vidi 2.2) | **ti** svuda | obično |
| **posao / usluga** | posao 31; usluga 26 (isti pojam: katalog "Poslovi", klijent "Ugovorene usluge", "Paketi usluga", "Dodaj paket") | **?** (a) **posao** svuda (Paketi poslova, Ugovoreni poslovi) ili (b) **usluga** za ono što klijent ima i plaća (paket, cena, ugovor), **posao** za katalog i rad | **?** preporuka (b) |
| **Otkaži / Odustani / Zatvori / Poništi** | Otkaži 15; Odustani 17; Zatvori 11; Poništi 10 | Odustani = izlaz iz forme/prozora bez čuvanja; **Otkaži** = poslovna radnja (otkaži zadatak, odsustvo); Zatvori = prozor samo za čitanje; Poništi = undo i reset filtera ("Poništi filtere") | **?** preporuka: 9 formi sa "Otkaži" → "Odustani" |
| **Obriši / Ukloni / Isključi / Deaktiviraj** | Obriši 15; Ukloni 9; Isključi 1; Deaktiviraj 5 | Obriši = trajno (stavka, dokument); **Ukloni** = veza (paket sa klijenta, red iz izbora, član iz tima); **Deaktiviraj** = soft delete (korisnik, klijent, pozicija, kategorija); **Isključi** = posao za klijenta | **?** (flash poruke ih mešaju, vidi 2.3) |
| **Traži / Pretraži** | Traži 10; Pretraži/Pretraga 10 | **Pretraži** za pretragu i dugme filtera; "Traži potvrdu" → **Zatraži potvrdu** | **?** |
| **Odobri / Potvrdi** | Odobri 6; Potvrdi 3 (+ "Posebne potvrde") | Odobri = odsustvo i predlog posla; **Potvrdi** = provera zadatka | obično, samo upisati |
| **notifikacija / obaveštenje** | Notifikacije 10 (meni, zvono, stranica); obaveštenje 21 (Podsetnik, mailovi, komande) | **Obaveštenja** za zvono, meni i stranicu (usklađeno sa Podsetnikom: Obavesti me) | **?** preporuka da |
| **korisnik / radnik / član** | korisnik 36; radnik 45; član 13; zaposleni 4 | korisnik = nalog (admin deo, formular); radnik = osoba u radu (zadaci, tim, dashboard); član = samo "član tima"; **zaposleni** ukinuti | **?** |
| **šef / vođa tima** | šef 26 | **šef** (u formama "Šef tima") | obično |
| **odsustvo / godišnji odmor / odmor** | odsustvo 71; godišnji 6; odmor 13 | Odsustvo = zahtev; **Godišnji odmor** = vrsta; "odmor" samo u "Kolektivni odmor" | obično |
| **Nema / Još nema** (prazna stanja) | Nema 36; Još nema 9; Prazno 6; Nije pronađen 1 | **Još nema X.** kad korisnik može da doda (+ dugme); **Nema X.** kad je rezultat filtera ili sistema; "Prazno" ukinuti | **?** |
| **Podsetnik** (veliko / malo slovo) | "Podsetnik" kao naziv modula; malo slovo u "Dodaj u podsetnik", "Stavka ide u tvoj privatni Podsetnik" | Naziv modula je ime (**Podsetnik**, veliko P) samo kao naslov/meni/dugme; u rečenici malo ("u tvoj podsetnik") | **?** |

### 2.1 Velika slova, interpunkcija, pismo
- **Naslovi i dugmad**: rečenično pisanje (samo prvo slovo veliko) uglavnom se poštuje. Izuzeci za proveru: "Posebne potvrde" (naslov bloka) vs "Zadaci sa posebnom potvrdom" (opis u zahtevu), "Moj deo je gotov". P3.
- **Tačka na kraju**: poruke u tooltip-ima i naslovima bez tačke, rečenice sa tačkom. Neusklađeni: `ChangePasswordFormType.php:28` ("Unesi lozinku"), `ResetPasswordRequestFormType.php:20` ("Unesi email adresu"): jedine validacione poruke bez tačke. P2.
- **Tri tačke**: svuda jedan znak `…` (17); `...` u UI tekstu nema (nađen samo JS spread operator). Ostaje.
- **Pismo**: svuda latinica; **ćirilice i engleskog u UI nema** (nađene samo reči Email, Status, PIB, Backup, Toast u internim imenima, što je prihvatljivo). "Backup" se piše i "backup-a", "Backup/zamena": proveriti sklonidbu. P3.
- **Mejl**: "Email" (login, forme) i "mail" (Obaveštenje na mail, Pošalji i na mail): **?** jedno: preporuka **mejl** u rečenici, "Email" samo kao naziv polja.

### 2.2 Obraćanje (ti / vi)
| Gde | Tekst | Predlog | Prior. |
|---|---|---|---|
| `src/Controller/Team/TeamController.php:25` | "Trenutno ne vodite nijedan tim." | "Trenutno ne vodiš nijedan tim." | P1 |
| `templates/base_auth.html.twig:32` | "Vaši klijenti, rokovi i tim, na jednom mestu." | "Tvoji klijenti, rokovi i tim, na jednom mestu." (ili neutralno: "Klijenti, rokovi i tim na jednom mestu.") | P2 |
| `templates/reset_password/email.html.twig:11-14` | miks: "kopirajte" i "nisi ti tražio" u istom mailu | "kopiraj", ostalo ostaje | P1 |

### 2.3 Flash i validacione poruke (ne vide se u šablonima)
Ima **105** `addFlash` poziva (`src/Controller/**`) i ~60 `message:` u validatorima/formama. Nedoslednosti:
| # | Nalaz | Gde | Predlog | Prior. |
|---|---|---|---|---|
| F1 | **Tri glagola za isti smisao "uklonjeno"**: "Kategorija je **obrisana**", "Paket je **uklonjen**", "Posao je **isključen**", "Pozicija je obrisana", "Član je uklonjen iz tima" | `CatalogController`, `ClientController`, `PositionController`, `TeamController` | po rečniku: obrisano (trajno), uklonjeno (veza), deaktivirano (soft), isključeno (posao za klijenta) | P2 |
| F2 | **"Izmene su sačuvane."** (3×) uz "Profil je sačuvan.", "Zadatak je sačuvan.", "Dodela je sačuvana.", "Odsustvo je izmenjeno." | razni kontroleri | jedan obrazac: "<Entitet> je sačuvan." | P2 |
| F3 | `addFlash(..., $e->getMessage())` (25×): tekst dolazi iz izuzetaka servisa; nekoliko je na engleskom ili tehničkih ako izuzetak nije naš | servisi/kontroleri | proveriti svaku `\DomainException` poruku, spisak u Fazi B | P1 |
| F4 | "Greška pri pozivu NBS servisa." / "Povezivanje nije uspelo: …" (sirovi tekst izuzetka) | `NbsController`, `GoogleController` | uvek naša rečenica + detalj u logu | P2 |
| F5 | Validacione poruke Symfony-ja bez našeg `message:` (npr. `invalid_message` na formama, `UniqueEntity` default, tip podatka) | `src/Form/*`, entiteti | proveriti da prevod `sr_Latn` pokriva sve (u `translations/` postoji samo KnpPaginator; validator prevode daje Symfony) i dopuniti gde ispadne engleski | P2 |
| F6 | Uskličnik u porukama: nema; ton "Unesi …" / "Izaberi …" je dosledan | — | ostaje | — |
| F7 | JS toastovi i greške u `todo.js`, `task-due.js`, `schedule-customize.js`, `confirm.js` imaju ručno kucane rečenice | `assets/js/*` | obuhvatiti u istom popisu | P3 |

---

## 3. Dugmad i polja bez Bootstrap klasa

Pravilo koje proveravam: `<button>` ima `btn` + varijantu; `<input>`/`<select>`/`<textarea>` ima `form-control`/`form-select`/`form-check-input`; `<label>` ima `form-label`/`form-check-label`; `<table>` ima `table`; `badge` ima `bg-*`. Formulari sa `form_row()` nasleđuju Bootstrap temu i nemaju problem.

### 3.1 Najvažnije: HTML u SweetAlert prozorima
**Uzrok je zajednički**: `confirm.js` mixin ima `buttonsStyling: false` i `customClass: { cancelButton: 'btn', confirmButton: '' }`, pa dugmad u prozorima su `btn` **bez varijante** (`btn`, `btn kt-btn-confirm`, `btn kt-btn-danger`), a izgled im daje poseban `swal.css` (`.kt-swal .swal2-confirm.kt-btn-confirm`) umesto Bootstrap varijante. Zato izgledaju drugačije od `btn-primary`/`btn-light` u ostatku aplikacije.
| # | Mesto | Šta je nestilizovano / nedosledno | Predlog | Prior. |
|---|---|---|---|---|
| D1 | `assets/js/confirm.js:23-26` (mixin) | `confirmButton: ''`, `cancelButton: 'btn'`; preko `buttons[type]` = `btn kt-btn-confirm` / `btn kt-btn-danger` (:15-19, :148) | Globalno: `confirmButton: 'btn btn-primary'`, `cancelButton: 'btn btn-light'` (kako je u formama; ili `btn-outline-secondary`, **? bira korisnik**), `denyButton: 'btn btn-danger'`, `danger` potvrda `btn btn-danger` | P1 |
| D2 | `schedule-customize.js:28,148-155` ("Prilagodi raspored") | Dugmad "Primeni"/"Odustani" iz mixina; unutar prozora (`_schedule_dialog` → `schedule_form`) `<label class="btn" for=...>` + `btn-check` **bez varijante** (`components/schedule_form.html.twig:12-13`) → dugmad za tip rasporeda (Mesečno, Kvartalno…) izgledaju prazno; polja rasporeda nemaju razmak/širinu iz `swal2-html-container` | `btn btn-outline-primary` na label-ima; `.swal2-html-container .form-control/.form-select` stilovi; širina 640 mora da se skuplja na telefonu | P1 |
| D3 | `schedule-customize.js:90-93` | linkovi u redu posla su `btn btn-link btn-sm p-0` (OK), ali nema razmaka/ikone i `data-act=revert` je bez potvrde stila | uskladiti sa `btn btn-link btn-sm` + ikona | P3 |
| D4 | `todo.js:135-146` (Podsetnik prozor) | `confirmButton: 'btn kt-btn-confirm'`, `cancelButton: 'btn'`, `denyButton: 'btn kt-btn-d…'` (zasebna kopija mapiranja); `Više opcija` je `btn btn-link btn-sm px-0` (OK); `<select id="kt-td-client">` bez klase (Tom Select, očekivano) | koristiti globalni mapping iz D1 umesto lokalnog | P1 |
| D5 | `task-due.js:28-44,110-117` | isto kao D4; polje roka `form-control` (OK); checkbox-ovi `form-check-input` (OK) | globalni mapping | P1 |
| D6 | `job-picker.js:38-41`, `client-services.js:25-30` | dugme "Ukloni" je `btn kt-btn-icon btn-sm text-muted` (**bez varijante**), inline `<svg>` umesto Lucide; `label.d-flex.align-items-start.gap-2.mb-0` bez `form-check-label` | `btn btn-ghost btn-sm` + `ux_icon` iz `<template>` | P2 |
| D7 | `confirm.js:68,92,110` (undo toast, 2 dugmeta u toastu, `input: 'form-select'`) | toast dugmad su `btn btn-sm …` (OK), ostali prozori ne | pokriti D1 | P3 |
| D8 | ostali `Swal.fire` sa `html:` | `calendar.js` popover (Bootstrap Popover, ne Swal), `todo.js` ostalo | pregledati u Fazi B (ima ih 5 poziva: `schedule-customize.js` ×2, `task-due.js` ×2, `todo.js` ×1; sva ostala potvrda idu kroz `data-confirm`) | P3 |

### 3.2 Dugmad u šablonima bez `btn` klase (osim `dropdown-item`, `kt-icon-btn`, `kt-pager-btn`, `btn-close` koji su namerni sopstveni sistem)
| # | Fajl:linija | Šta | Predlog | Prior. |
|---|---|---|---|---|
| B1 | `templates/calendar/index.html.twig:22`, `components/schedule_form.html.twig:13`, `task/form.html.twig:39` | `<label class="btn">` uz `btn-check` bez `btn-outline-*` | `btn btn-outline-primary` (ili `btn-light`) | P1 |
| B2 | `templates/components/_job_schedule.html.twig:4` | `<button class="{{ class }}">` — klasa dolazi spolja, bez garancije `btn` | podrazumevano `btn btn-link btn-sm`; parametar samo dodaje | P2 |
| B3 | `templates/absence/index.html.twig:23`, `admin/user/show.html.twig:20`, `client/show.html.twig:26`, `document/index.html.twig:26-27` | `btn {{ btn }}` / `btn {{ x ?: ... }}` — varijanta se slaže u šablonu; jedna grana ostaje prazna | proveriti da svaka grana daje varijantu | P3 |
| B4 | `templates/client/_services.html.twig:47` (7×) | `<button class="dropdown-item">` | OK (Bootstrap dropdown), ostaje | — |
| B5 | `templates/todo/_item.html.twig:23`, `components/_job_picker.html.twig:26`, `components/_task_filters.html.twig:17`, `base.html.twig:91`, `_mobile_nav.html.twig:15`, `todo.js:88` | `kt-todo-title`, `kt-jp-toggle`, `kt-search-clear`, `kt-user-toggle`, `kt-tab-plus`, `kt-tchip`: sopstvene komponente iz DESIGN.md | namerno; Faza B test (3.4) ima listu izuzetaka sa ovim klasama | P3 |

### 3.3 Polja, labele, tabele, bedževi
| # | Fajl:linija | Šta | Predlog | Prior. |
|---|---|---|---|---|
| P1 | `templates/admin/non_working/load.html.twig:36`, `admin/finance/billing.html.twig:19`, `assets/js/client-services.js:25` | `<label>` bez `form-label`/`form-check-label` | dodati klasu | P3 |
| P2 | `assets/js/todo.js:117` | `<select id="kt-td-client">` bez klase (Tom Select ga zamenjuje) | dodati `form-select` radi izgleda pre inicijalizacije | P3 |
| P3 | `templates/emails/base.html.twig`, `templates/pdf/list.html.twig` | tabele bez `table` | namerno (mail/PDF imaju svoj stil), izuzetak u testu | — |
| P4 | `components/ui.html.twig:19` + `dashboard/index.html.twig:176` (`badge badge-team`), `kt-cat-badge` (4×) | `badge` bez `bg-*` | namerno (boja kategorije dolazi iz `--kt-cat`); upisati kao izuzetak | P3 |
| P5 | Veličine: `btn` podrazumevane (default 109×) vs `btn-sm` (15×) u istom tipu ekrana; kartice sa `btn-sm` u zaglavlju (OK), forme sa podrazumevanom | | vidi 4 | P2 |

### 3.4 Predlog zaštite u Fazi B
Mali test (`tests/Ui/ButtonClassesTest.php`) koji skenira `templates/**/*.twig` i `assets/js/**/*.js` i pada ako postoji `<button` bez klase iz liste (`btn`, `dropdown-item`, `kt-icon-btn`, `kt-pager-btn`, `kt-tchip`, `kt-todo-title`, `kt-jp-toggle`, `kt-search-clear`, `kt-user-toggle`, `kt-tab-plus`, `btn-close`) ili `<input type=submit|button>` bez `btn`. Izuzeci: `templates/design/`, `emails/`, `pdf/`.

---

## 4. Veličine i razmaci
| # | Nalaz | Gde | Predlog | Prior. |
|---|---|---|---|---|
| R1 | **Redosled dugmadi nije jedan**: "Otkaži pa Sačuvaj" u 11 formi (`admin/*/form`), "Sačuvaj pa Otkaži" u `comments.js:33`. Primarna akcija je u nekim formama levo, u nekim desno | `templates/admin/*/form.html.twig`, `assets/js/comments.js` | jedan obrazac: **Sačuvaj levo, Odustani desno** u formama; u prozorima (Swal, `reverseButtons: true`) **Odustani levo, potvrda desno**. Bira korisnik koji obrazac važi | **?** P2 |
| R2 | Dugme otkazivanja je `btn-light` (24×), na jednom `btn-outline-danger` (`Otkaži`), na stranici greške `btn btn-primary` ("Nazad", `bundles/TwigBundle/Exception/error.html.twig:24`), na jednom `btn swal2-styled` | `task/*`, `document/index`, `client/*` | `btn-light` za "Odustani"/"Nazad", `btn-outline-danger` samo za opasne | P2 |
| R3 | Veličine: primarna akcija u zaglavlju stranice je `btn` (podrazumevano) na većini, `btn-sm` na kartici; u zaglavlju kartice prisutni i `btn-ghost btn-sm` i `btn-light btn-sm` | `page_actions` (26 šablona), `card-header` | pravilo: zaglavlje stranice = `btn`, zaglavlje kartice = `btn-sm`, red u listi = `btn-sm` ili `kt-icon-btn` | P2 |
| R4 | **Primarna akcija stranice gore desno** (`kt-page-actions`) postoji u 26 šablona; bez nje su (proveriti pri Fazi B): `profile/index`, `notification/index`, `help/show`, `design/index` | | dodati gde lista ima "Novi/Dodaj" a dugme je u telu | P3 |
| R5 | Naslovi kartica: `h5` (kartice sa akcijom), `h6` (podkartice), mešano `strong` (npr. `todo` demo) | `dashboard`, `design`, `client/show` | `h5` kartica, `h6` unutar kartice, nikad `strong` kao naslov | P3 |
| R6 | Razmak između kartica: `mb-4` (Bootstrap, 24 px) i `.card { margin-bottom: 16px }` iz `card.css` istovremeno; na novom dashboardu je razmak 24 px preko grida | `assets/styles/components/card.css:11`, šabloni | jedna vrednost (16 ili 24 px) kao token | P3 |
| R7 | Visina polja i dugmadi: dugmad u modalu na telefonu 44 px (`mobile.css`), polja `form-control` 40 px; `btn-sm` 32 px; proveriti poravnanje u istom redu (npr. Podsetnik prozor: `form-control` + čipovi) | `mobile.css`, `todo.css` | proveriti ručno na 390 px | P3 |

### 4.1 Tamna tema (proveriti ručno na svakom ekranu)
- **Tvrdo kodirane boje izvan tokena**: `avatar.css` (`#fff`, 8 gradijenata), `badge.css:32` (`.badge-team { color: #fff }`), `button.css:49,68`, `swal.css:16-17` (`.kt-btn-danger { color: #fff }` pa u tamnoj `#1a0505`: ručni override). Grafikoni (`dashboard`, `admin/finance`) imaju fiksne boje serija (`#5c6bc0` itd.) bez tamne varijante. P2.
- **Nezavisne od teme**: `reset_password/email.html.twig` ima inline boje (`#333`, `#666`); to je mail, ostaje. 
- Izuzeci koji se moraju ručno otvoriti u tamnoj: SweetAlert polja (D2), FullCalendar popover, Tom Select dropdown u prozoru, flatpickr u prozoru, nova mreža početne strane.

---

## 5. Telefon 390 px (nije proveren u browseru)
Statički nalazi:
- Svaka tabela ima `table-responsive` ili mobilnu listu u istom fajlu (osim mail/PDF); fiksnih širina ≥300 px u šablonima i JS-u **nema** osim `Swal width: 640` (`schedule-customize.js:31`) i `max-width` u `help.css`/`login.css`.
- **Za ručnu proveru na 390 px (kandidati za horizontalni skrol):**
  1. Prozor "Prilagodi raspored" (`width: 640`; unutra dugmad tipa rasporeda i datumi).
  2. Prozor Podsetnika sa čipovima i redom "Obavesti me" (`col-7`/`col-5`).
  3. Novi raspored početne (grid, `kt-dash-stats` sa 160 px kolonama, Podsetnik 3/12 → jedna kolona).
  4. Kalendar (FullCalendar toolbar) i "Naredna nedelja".
  5. Lista zadataka sa filterima (bottom sheet).
  6. Tabele "Učinak po radniku", "Po timovima" (`table-responsive`: treba li mobilna lista po DESIGN.md 16?).
  7. `/_design` (nije za produkciju, najniži prioritet).
- Dugmad u Swal prozoru na telefonu: `mobile.css:127` postavlja punu širinu samo za `.swal2-styled`; posle D1 (Bootstrap klase umesto `swal2-styled`) taj pravilo se mora preneti na `.btn` unutar `.swal2-actions`. P1 vezano za D1.

---

## Predlog redosta u Fazi B (po tvom planu)
1. **a)** Globalni `customClass` za SweetAlert (D1, D2, D4, D5, D6) + stilovi polja u prozorima + prenos `mobile.css:127`.
2. **b)** Ikonice (I1, I2, I6, I7, I11 prvo; S1, S2).
3. **c)** Rečnik i poruke (2.1–2.3), uključujući ti/vi (2.2) i flash/validatore.
4. **d)** Dugmad/polja po fajlu (3.2, 3.3) + test iz 3.4.
5. **e)** Tamna tema i telefon (4.1, 5), ručno uz tebe.
6. Dokumentacija: DESIGN.md (tabela ikonica i izuzeci), SPEC.md (rečnik), uputstva, PROGRESS.md, `/_design`; `composer test`; commit po celini.

## Pitanja za tebe (samo neočigledna)
1. **posao / usluga** — (a) samo "posao", ili (b) "usluga" za ono što klijent plaća, "posao" za katalog i rad? (preporuka b)
2. **Otkaži / Odustani** — 9 formi sa "Otkaži" → "Odustani"; "Otkaži" ostaje samo za poslovnu radnju (zadatak, odsustvo). Slažeš li se?
3. **Obriši / Ukloni / Deaktiviraj / Isključi** — podela iz tabele u 2 (trajno / veza / soft / posao za klijenta).
4. **Notifikacije → Obaveštenja** u meniju i zvonu (da bi se slagalo sa "Obavesti me").
5. **Traži / Pretraži** — "Traži potvrdu" → "Zatraži potvrdu"; dugme filtera "Traži" → "Pretraži".
6. **Nema / Još nema** — pravilo iz tabele.
7. **Redosled dugmadi** — forma: Sačuvaj levo, Odustani desno; prozor: Odustani levo, potvrda desno (ili obrnuto za forme)?
8. **Dugme "Odustani"**: `btn-light` (kao u formama danas) ili `btn-outline-secondary`?
9. **Mejl** — "mejl" u rečenicama (preporuka) ili "mail"/"email"?
10. **Podsetnik** — veliko P samo kao naziv modula, malo u rečenici ("u tvoj podsetnik")?
