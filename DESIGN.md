# DESIGN.md — Vizuelni identitet: KontIT app

> Izgled aplikacije se gradi na **Bootstrap 5.3 + tokenima i komponentama iz ovog fajla** (nema kupljenog template-a).
> Pre svakog UI posla pročitaj ceo fajl. Dozvoljene biblioteke su navedene u `docs/archive/UPDATE-DIZAJN.md` sekciji 2 i u CLAUDE.md.

---

## 1. Karakter

**KontIT app** je alat u kome knjigovođe provode ceo radni dan. Treba da deluje kao skup, pouzdan
finansijski softver: **mirno, precizno, uredno**. Ništa ne viče, sve je na svom mestu, a brojevi su glavni.

Pravac: savremeni "fintech" dashboard (u duhu Linear, Stripe i Mercury interfejsa), a ne klasičan admin panel.

**Pet principa**
1. **Tišina pa naglasak.** Interfejs je neutralan (grafitne nijanse), a plava boja iz logoa se koristi **samo**
   za ono što je bitno: primarna akcija, aktivna stavka, fokus, ključni broj.
2. **Linije umesto kutija.** Tanke ivice (1px, niskog kontrasta) i blage senke. Nema debelih okvira ni teških senki.
3. **Brojevi su heroji.** Tabularni brojevi, desno poravnati u tabelama, krupni na dashboardu.
4. **Gustina sa vazduhom.** Puno podataka na ekranu, ali sa doslednim razmakom (sistem od 4px).
5. **Detalj u pokretu.** Mikro-animacije od 120–240 ms na hover, otvaranje i štikliranje. Nikad spore i nikad nametljive.

**Motiv brenda**: četiri kruga iz znaka (dva grafitna, dva plava, koji se prepliću). Koristi se kao loader,
kao pozadinski šablon na login strani i u praznim stanjima.

---

## 2. Logo

### 2.1 Fajlovi (folder `logo/`)

| Fajl | Gde ide | Upotreba |
|---|---|---|
| `kontit-app-lockup-inline.svg` | sadržaj u `templates/components/_logo.html.twig` | **Sidebar (otvoren)**. Inline SVG, boje prate temu preko CSS-a. |
| `kontit-mark-inline.svg` | sadržaj u `templates/components/_logo_mark.html.twig` | **Sidebar (skupljen)**, loader, prazna stanja |
| `kontit-logo.svg` / `kontit-logo-dark.svg` | `public/brand/` | **Login strana** (pun logo sa "RAČUNOVODSTVO"), štampa, PDF izveštaji |
| `kontit-app-lockup.svg` / `-dark.svg` | `public/brand/` | kada inline nije moguć (`<img>`) |
| `kontit-mark.svg` / `-dark.svg` | `public/brand/` | samo znak, kao `<img>` |
| `kontit-app-icon.svg` | `public/favicon.svg` | favicon (moderni browseri) |
| `favicon.ico`, `favicon-16.png`, `favicon-32.png` | `public/` | favicon (stariji browseri) |
| `apple-touch-icon.png`, `icon-192.png`, `icon-512.png` | `public/` | iOS ikona i `manifest.webmanifest` |
| `kontit-app-lockup-email@2x.png`, `kontit-logo@2x.png` | `public/brand/` | **mailovi** (mail klijenti ne podržavaju SVG pouzdano) |

### 2.2 Verzije logoa
- **Pun logo** (znak + "KontIT" + "RAČUNOVODSTVO"): login strana, PDF dokumenti. To je logo firme.
- **App lockup** (znak + "KontIT" + "app"): unutar aplikacije. Reč **"app"** je u plavoj boji i tanjem rezu (Inter Regular),
  kao tihi sufiks uz jak znak. Tako se razlikuje proizvod (aplikacija) od firme (računovodstvo).
- **Znak** (četiri kruga): skupljen sidebar, favicon, loader.
- **App ikona**: znak u belom na plavom gradijentu, zaobljen kvadrat (favicon, telefon).

### 2.3 Boje logoa po temi
Inline SVG koristi `currentColor` za grafitni deo i `var(--kt-logo-accent)` za plavi deo:
```css
.kt-logo, .kt-mark { color: var(--kt-logo-ink); }
:root                 { --kt-logo-ink: #58595B; --kt-logo-accent: #0089FF; }
[data-bs-theme=dark]  { --kt-logo-ink: #E6E8EC; --kt-logo-accent: #3BA3FF; }
```

### 2.4 Pravila
- **Zaštitna zona** oko logoa je najmanje prečnik jednog malog kruga iz znaka.
- **Minimalna veličina**: lockup visine 28px, znak 20px.
- Visina u sidebaru: lockup **30px**, znak **28px**. Na login strani: pun logo **56px**.
- **Ne** menjati boje, proporcije ni font, ne dodavati senke ni efekte, i ne stavljati logo na šarenu ili zauzetu pozadinu.
- Naziv u tekstu i naslovima: **KontIT app**. U `<title>`: `{{ naslov stranice }} · KontIT app`.

---

## 3. Boje

### 3.1 Brend (iz logoa)
| Token | Hex | Napomena |
|---|---|---|
| **Kont plava** `--kt-brand-500` | `#0089FF` | boja iz logoa: ikonice, fokus, grafikoni, akcenti |
| **Grafit** `--kt-graphite` | `#58595B` | boja iz logoa: osnova neutralne skale |

**Važno za pristupačnost:** `#0089FF` na beloj pozadini ima kontrast **3,49:1**, što **nije dovoljno** za tekst i dugmad sa belim tekstom.
Zato se u svetloj temi za dugmad i linkove koristi **`#006FD6`** (4,95:1), a `#0089FF` ostaje za logo, ikonice, fokus i grafikone.
U tamnoj temi je primarna `#3BA3FF`, sa **tamnim** tekstom na dugmetu (7,2:1).

### 3.2 Plava skala
| 50 | 100 | 200 | 300 | 400 | **500** | 600 | 700 | 800 | 900 | 950 |
|---|---|---|---|---|---|---|---|---|---|---|
| `#EEF7FF` | `#D9EDFF` | `#B5DCFF` | `#84C6FF` | `#3BA3FF` | **`#0089FF`** | `#006FD6` | `#0058AB` | `#004588` | `#06386B` | `#072441` |

### 3.3 Neutralna skala (hladni grafit)
| 0 | 25 | 50 | 100 | 200 | 300 | 400 | 500 | 600 | 700 | 800 | 850 | 900 | 925 | 950 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `#FFFFFF` | `#FAFBFC` | `#F5F6F8` | `#ECEEF1` | `#DDE1E6` | `#C3C9D1` | `#A1A9B4` | `#7E8794` | `#5E6672` | `#474E58` | `#2E333A` | `#1F2329` | `#161A1F` | `#11151A` | `#0B0F14` |

Nikad čista crna `#000` ni čista siva bez nijanse.

### 3.4 Semantički tokeni

| Token | Svetla tema | Tamna tema | Upotreba |
|---|---|---|---|
| `--kt-bg` | `#F5F6F8` | `#0B0F14` | pozadina strane |
| `--kt-surface` | `#FFFFFF` | `#11161D` | kartice, tabele, modali |
| `--kt-surface-2` | `#FAFBFC` | `#161C24` | sidebar, zaglavlje tabele, ugnježdeni blokovi |
| `--kt-surface-3` | `#F0F2F5` | `#1C232D` | hover, izabran red |
| `--kt-border` | `#E4E7EB` | `rgba(255,255,255,.07)` | sve ivice |
| `--kt-border-strong` | `#D3D8DE` | `rgba(255,255,255,.12)` | inputi, razdelnici |
| `--kt-text` | `#1F2329` | `#ECEEF1` | osnovni tekst (15,8:1 / 16:1) |
| `--kt-text-2` | `#5E6672` | `#A1A9B4` | sekundarni tekst, labele (5,8:1 / 7,9:1) |
| `--kt-text-3` | `#8A929D` | `#6F7782` | placeholder, pomoćni, dekorativni tekst |
| `--kt-primary` | `#006FD6` | `#3BA3FF` | dugmad, linkovi, aktivna stavka |
| `--kt-primary-hover` | `#0058AB` | `#5CB3FF` | |
| `--kt-on-primary` | `#FFFFFF` | `#06121F` | tekst na primarnom dugmetu |
| `--kt-primary-soft` | `#EEF7FF` | `rgba(59,163,255,.12)` | pozadina aktivne stavke i badge-a |
| `--kt-focus` | `rgba(0,137,255,.28)` | `rgba(59,163,255,.35)` | fokus prsten |
| `--kt-success` | `#15803D` | `#4ADE80` | |
| `--kt-warning` | `#B45309` | `#FBBF24` | |
| `--kt-danger` | `#C81E1E` | `#F87171` | |
| `--kt-info` | = primary | = primary | |

Za svaku semantičku boju postoji i `-soft` varijanta (svetla: ~8% boje na beloj, tamna: `rgba(boja, .12)`) za pozadinu badge-ova i alertova.

### 3.5 Statusi i prioriteti zadataka
Badge = **meka pozadina + tačkica u boji + tekst u boji**, nikad puna jaka boja.

| Status | Boja | | Prioritet | Boja |
|---|---|---|---|---|
| NOVO | neutralna (500) | | NIZAK | neutralna |
| U_RADU | plava | | NORMALAN | plava, samo ivica |
| CEKA_KLIJENTA | warning (amber) | | VISOK | warning |
| NA_PROVERI | ljubičasta `#7C5CFF` / `#A78BFA` | | HITAN | danger, tačkica pulsira |
| ZAVRSENO | success | | | |
| OTKAZANO | neutralna, precrtan tekst | | | |

Boje i nazivi statusa idu u PHP enum (`getColor()`, `getLabel()`), a Twig makro `status_badge(status)` ih crta. Nikad ručno u šablonu.

### 3.6 Boje timova (umesto slobodnog color pickera)
`Team.color` se bira iz **8 unapred podešenih** nijansi koje rade u obe teme:
`blue #0089FF` · `indigo #5B6CFF` · `violet #8B5CF6` · `teal #14B8A6` · `green #22C55E` · `amber #F59E0B` · `rose #F43F5E` · `slate #64748B`

### 3.7 Grafikoni
Redosled serija: `#0089FF`, `#14B8A6`, `#8B5CF6`, `#F59E0B`, `#F43F5E`, `#64748B`.
Jedna serija je uvek plava. "Plan naspram ostvarenog": plan je neutralna isprekidana linija, ostvareno plava.

---

## 4. Tipografija

| Uloga | Font | Napomena |
|---|---|---|
| Naslovi, KPI brojevi na dashboardu | **Manrope** (600–800) | geometrijski i zaobljen, slaže se sa logoom |
| Interfejs i tekst | **Inter** (400–600) | čitljiv u malim veličinama |
| Šifre, PIB, matični broj, brojevi računa | **JetBrains Mono** (400–500) | |

- Fontovi sa **Google Fonts**, sa podskupom **latin-ext** (č, ć, š, ž, đ). Učitati samo potrebne rezove, uz `display=swap` i `preconnect`.
- **Svi brojevi u tabelama, KPI karticama i iznosima** koriste `font-variant-numeric: tabular-nums;`, da se cifre poravnaju.
- Osnovna veličina je **14px** (aplikacija sa puno podataka).

| Stil | Veličina / visina reda | Font i debljina | Ostalo |
|---|---|---|---|
| Display (login, prazna stanja) | 32 / 40 | Manrope 700 | letter-spacing −0,02em |
| H1 (naslov strane) | 24 / 32 | Manrope 700 | −0,015em |
| H2 (naslov kartice/sekcije) | 18 / 26 | Manrope 650 | −0,01em |
| H3 | 15 / 22 | Inter 600 | |
| Tekst | 14 / 20 | Inter 400 | |
| Mali tekst | 13 / 18 | Inter 400 | |
| Labela iznad grupe (overline) | 11 / 16 | Inter 600 | VELIKA SLOVA, +0,06em, `--kt-text-3` |
| KPI broj | 30 / 36 | Manrope 700 | tabular, −0,02em |

---

## 5. Prostor, oblici, senke

- **Razmak**: sistem od 4px: `4, 8, 12, 16, 20, 24, 32, 40, 48, 64`. Unutrašnji razmak kartice 20px (24px na desktopu), između kartica 16–24px.
- **Zaobljenja** (krugovi iz logoa → mekan oblik):
  `--kt-radius-xs: 6px` (badge, checkbox), `--kt-radius-sm: 8px` (mali dugmići), `--kt-radius-md: 10px` (dugmad, inputi),
  `--kt-radius-lg: 14px` (kartice, tabele), `--kt-radius-xl: 20px` (modali, login panel), `999px` (pill, avatar).
- **Visina kontrola**: 36px (normalno), 32px (kompaktno, u tabelama), 44px (login).
- **Senke (svetla tema)**, uvek slojevite i blage:
  ```css
  --kt-shadow-xs: 0 1px 2px rgba(16,24,40,.05);
  --kt-shadow-sm: 0 1px 2px rgba(16,24,40,.06), 0 1px 3px rgba(16,24,40,.08);
  --kt-shadow-md: 0 4px 8px -2px rgba(16,24,40,.08), 0 2px 4px -2px rgba(16,24,40,.05);
  --kt-shadow-lg: 0 16px 32px -8px rgba(16,24,40,.14), 0 4px 8px -4px rgba(16,24,40,.06);
  ```
- **Senke (tamna tema)**: senka skoro ne postoji, a dubina se dobija **svetlijom površinom** i tankim
  "odsjajem" na gornjoj ivici: `box-shadow: inset 0 1px 0 rgba(255,255,255,.04), 0 1px 2px rgba(0,0,0,.4);`
- Kartica po defaultu ima **ivicu i `shadow-xs`**. Veća senka samo za dropdown (`md`) i modale (`lg`).

---

## 6. Teme: svetla, tamna, sistemska

- Koristi se ugrađen Bootstrap 5.3 mehanizam: atribut **`data-bs-theme`** na `<html>`.
- Tri opcije: **Svetla / Tamna / Kao sistem** (podrazumevano: kao sistem).
- Novo polje **`User.themePreference`** (enum: LIGHT, DARK, SYSTEM), koje se čuva AJAX-om kad se promeni, pa tema prati korisnika na svakom računaru.
  `localStorage` služi samo kao keš da nema treptanja.
- **Bez treptanja**: ovaj mali skript ide **inline u `<head>`, pre CSS-a**:
  ```html
  <script>
  (function(){
    var p = '{{ app.user ? app.user.themePreference.value|lower : "system" }}';
    try { p = localStorage.getItem('kt-theme') || p; } catch(e){}
    var dark = p === 'dark' || (p === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
  })();
  </script>
  ```
- Prekidač teme je u topbaru (ikonica sunce/mesec/monitor, dropdown sa tri opcije) i u profilu.
- Pri promeni teme okida se događaj `kt:themechange`, na koji se Chart.js grafikoni ponovo iscrtaju sa bojama nove teme.
- `<meta name="theme-color">` za obe teme (`#F5F6F8` / `#0B0F14`).
- Promena teme ima kratak fade (200 ms na `background-color, color, border-color`), ali **ne** na svim elementima i **ne** pri učitavanju strane.

---

## 7. Premium detalji po komponentama

### 7.1 Sidebar
- Širina 264px, a skupljen 76px (samo ikonice i znak, tooltip na hover). Stanje skupljenosti se pamti (localStorage).
- Pozadina `--kt-surface-2` i ivica desno. Logo gore (lockup, 30px), ispod grupe menija sa **overline labelama**
  ("RAD", "KLIJENTI", "ADMINISTRACIJA").
- **Aktivna stavka**: meka plava pozadina (`--kt-primary-soft`), plava ikonica i tekst, i **tanka plava traka od 3px levo**.
- Brojači (npr. "Čeka moju potvrdu 3") kao mali pill desno.
- Dno sidebara: kartica korisnika (avatar, ime, rola) sa menijem (Profil, Tema, Odjava).

### 7.2 Topbar
- Visina 64px, **"stakleni" efekat**: poluprovidna pozadina i `backdrop-filter: blur(12px) saturate(140%)`, uz ivicu dole.
  Lepljiv (sticky), sadržaj ispod se vidi zamućen pri skrolovanju.
- Levo: breadcrumbs (`Klijenti / Firma X / Zadaci`). Sredina ili desno: **pretraga sa prečicom** `Ctrl K` (vidi 7.15).
- Desno: prekidač teme, **zvonce** (crvena tačkica sa blagim pulsom kad ima nepročitanih), avatar.

### 7.3 Kartice
- `--kt-surface`, ivica, `--kt-radius-lg`, `shadow-xs`. Zaglavlje kartice: naslov H2 + akcije desno, **bez** obojene pozadine.
- Kartica na koju se klikće (npr. klijent): na hover se podigne 1px, senka se poveća i ivica dobije plavu nijansu (180 ms).

### 7.4 KPI kartice (dashboard)
- Gore labela (13px, `--kt-text-2`), ispod **krupan broj** (Manrope 30px, tabular), pored njega **pill sa promenom**
  (`▲ 12%` zeleno, `▼ 4%` crveno, u odnosu na prethodni period). Dole **sparkline** (mala linija trenda bez osa, plava sa providnim gradijentom ispod).
- Broj se pri učitavanju **"izbroji"** od 0 do vrednosti (600 ms, ease-out), ali samo prvi put.
- Na početnoj su KPI kartice bloka Zadaci kompaktne (padding 12px, broj 24px, labela 12.5px koja se prelama u dva reda, bez sečenja): 4 u redu, a od `xl` blok zauzima 3/4 širine i desno je kartica Podsetnik (`assets/styles/components/dashboard.css`, SPEC 8p.5).
- Kartica koja ukazuje na problem (npr. "Kasni: 7") ima tanku traku u boji opasnosti na vrhu, a ne crvenu celu karticu.

### 7.5 Tabele
- Zaglavlje: `--kt-surface-2`, overline stil (11px, velika slova), **lepljivo** pri skrolovanju.
- Redovi: visina 52px (normalno) ili 40px (kompaktno, prekidač gustine pamti se po korisniku). Hover: `--kt-surface-3`.
- Bez vertikalnih linija, samo tanka horizontalna između redova.
- **Brojevi i iznosi desno poravnati**, tabularni. Datumi u formatu `12.11.2026.`, a uz rok koji kasni crveni tekst i ikonica.
- Klijent i korisnik u tabeli uvek kao **avatar + ime + sekundarni red** (npr. PIB ili pozicija u `--kt-text-2`).
- Više učesnika: **složeni avatari** (preklopljeni, sa ivicom boje pozadine, "+3" za ostale).
- Akcije reda: pojavljuju se na hover (desno), a na telefonu su uvek vidljive u "⋯" meniju.
- Prazna tabela: prazno stanje (7.12), nikad samo tekst "Nema podataka".

### 7.6 Forme
- Labela **iznad** polja (13px, 500), pomoćni tekst ispod (`--kt-text-3`). Obavezna polja: mala tačka, ne zvezdica.
- Input: visina 36px, `--kt-radius-md`, ivica `--kt-border-strong`, pozadina `--kt-surface`.
  **Fokus**: plava ivica + **prsten od 3px** (`--kt-focus`), sa animacijom od 120 ms.
- Greška: crvena ivica, crveni prsten i poruka ispod sa ikonicom. Validacija se prikazuje posle napuštanja polja, ne dok se kuca.
- Duge forme su podeljene u **sekcije sa naslovom levo i poljima desno** (na desktopu), sa lepljivom trakom
  "Sačuvaj / Odustani" dole kad ima nesačuvanih izmena.
- Select: **Tom Select**, stilizovan istim tokenima (visina, ivica, radius i fokus kao input). PIB, matični broj i broj računa su u monospace fontu.

### 7.7 Dugmad
- **Primarno**: puna `--kt-primary`, **blagi gradijent odozgo** (svetlije 4%) i unutrašnji svetli odsjaj na gornjoj ivici
  (`inset 0 1px 0 rgba(255,255,255,.18)`), plus `shadow-xs`. Na pritisak se spusti (`translateY(1px)`).
- **Sekundarno**: `--kt-surface`, ivica, tekst `--kt-text`. **Ghost**: bez pozadine, hover `--kt-surface-3`.
- **Opasno**: crveno samo kad je to glavna radnja (npr. potvrda u SweetAlert-u), inače ghost sa crvenim tekstom.
- **Stanje učitavanja**: tekst ostaje (dugme ne menja širinu), a levo se pojavi mali loader od 4 tačke (7.13).
- Jedna stranica, **jedno primarno dugme**.

### 7.8 Badge-ovi
Meka pozadina + tačkica + tekst (3.5), visina 22px, `--kt-radius-xs` ili pill, 12px, 500.

### 7.9 Avatari
- Okrugli, **samo inicijali na gradijentu** (nema upload-a slike). Boja se bira deterministički iz imena (hash imena → jedna od 8 boja timova),
  tako da ista osoba uvek ima istu boju.
- Inicijali: prvo slovo imena + prvo slovo prezimena, velikim slovima, sa ispravnim Č, Ć, Š, Ž, Đ ("Đorđe Šarić" = "ĐŠ"). Za "Dž" samo "D".
- Tanak prsten boje pozadine, pa složeni avatari izgledaju čisto. Opciono: mala tačka statusa (odsutan = amber).
- Veličine: 24, 32, 40, 64, 96px.

### 7.10 Zadatak: checklist i napredak
- **Checkbox po meri**: zaobljen kvadrat od 18px. Pri štikliranju se ispuni plavom bojom i **iscrta kvačica** (SVG stroke animacija, 200 ms),
  a tekst stavke dobije precrtavanje koje "prelazi" sleva nadesno i postane `--kt-text-3`.
- Drag & drop ručka (šest tačkica) pojavljuje se na hover.
- **Napredak po učesniku**: segmentirana traka (svaki učesnik je segment u boji svog avatara), plus prsten napretka
  checkliste (npr. 5/8) u zaglavlju zadatka.
- Kad se ceo zadatak pošalje na proveru, kratak suptilan "uspeh" efekat (prsten se ispuni, zelena kvačica). **Bez konfeta.**

### 7.11 Timeline (istorija izmena)
Vertikalna tanka linija, a na njoj mali krugovi u boji tipa akcije. Svaki red ima avatar, rečenicu ("**Marko** je štiklirao *Knjiženje izvoda*"),
relativno vreme ("pre 5 min", sa tačnim vremenom u tooltipu), a izmene polja kao `staro → novo` sa precrtanim starim.

### 7.12 Prazna stanja
Centrirano: **znak KontIT kao tanka linijska ilustracija** (samo obris krugova, `--kt-border-strong`), naslov (H3),
jedna rečenica objašnjenja i **jedno dugme** za akciju ("Dodaj prvog klijenta").

### 7.13 Loader brenda i skeleti
- **Loader**: četiri tačke raspoređene kao u znaku (gore i dole grafit, levo i desno plava) koje se **naizmenično smanjuju i rastu**
  i polako rotiraju (1,2 s, beskonačno). Koristi se za učitavanje strane, AJAX i dugmad. Mali (16px) i veliki (40px).
- **Skeleti**: za liste, tabele i kartice prilikom AJAX učitavanja: sivi blokovi sa blagim "sjajem" koji prelazi preko (1,4 s).
- Ako se nešto učita za manje od 200 ms, ne prikazivati loader (bez treptanja).

### 7.14 Modali, SweetAlert i toast-ovi
- Pozadina iza modala: tamna providna + **blur 4px**. Modal: `--kt-radius-xl`, `shadow-lg`, ulazi sa blagim scale (0,98 → 1) i fade, 180 ms.
- **SweetAlert2** dobija isti izgled preko `customClass`: font, radius, dugmad iz sekcije 7.7, ikonice u mekom krugu boje tipa (info/warning/danger),
  i radi u obe teme.
- **Toast** gore desno, odmah ispod gornje trake (ne preklapa zvonce; na telefonu pune širine ispod trake): površina, ivica, traka u boji tipa levo, ikonica, tekst, ✕ i tanka traka koja odbrojava do zatvaranja. Uspeh 3 s, greška i upozorenje 6 s, prelazak mišem zaustavlja odbrojavanje. Kontejner toasta nema zatamnjenje ni blur (to je samo za dijalog).
- **Povratna informacija posle akcije je uvek toast, nikad alert u strani.** Flash poruke iz kontrolera (`addFlash`) `base.html.twig` predaje kao JSON, a `confirm.js` ih prikazuje kao toast, jednu za drugom (bez JS-a ostaju kao alert u `<noscript>`). Alert u strani je samo za stalno stanje ekrana (npr. greška prijave, upozorenje u formi).
- **Filteri**: `kt-segmented kt-segmented-sm` (32px, ikonica + tekst) za izbor jednog opsega i `kt-chip` sa `kt-chip-dot` (boja preko `--kt-chip-dot`) kao prekidač za uključi/isključi vrstu; sve kontrole u redu filtera su iste visine kao `form-select-sm`. Na telefonu segmentirano i čipovi idu punom širinom u jednakim delovima, a selecti u dve kolone.

### 7.15 Komandna paleta (Ctrl + K)
Prozor na sredini ekrana sa poljem za pretragu: **brza pretraga** klijenata (po nazivu i PIB-u), zadataka i korisnika, plus **akcije**
("Novi zadatak", "Promeni temu", "Moji zadaci koji kasne"). Navigacija strelicama, Enter otvara.
Ovo je jedan od detalja koji aplikaciju najviše čini "skupom". Dizajn se definiše sada, a pravi se kad budu gotovi klijenti i zadaci.

### 7.16 Login strana
- **Podeljen ekran**: levo (55%) **panel brenda**, desno forma.
- Panel brenda: tamna plava pozadina (`#072441` → `#004588`), sa **mesh gradijentom** (dve meke svetle mrlje plave boje)
  i **šablonom tačaka** u ritmu četiri kruga iz znaka (vrlo nisko providan, 6%). Na njemu pun logo (svetla varijanta),
  jedna rečenica ("Vaši klijenti, rokovi i tim, na jednom mestu.") i verzija aplikacije dole.
- Forma: kartica `--kt-radius-xl`, polja od 44px, "Zapamti me", "Zaboravljena lozinka?".
- Na telefonu panel brenda postaje traka od 120px gore sa znakom.
- Strane 403, 404 i 500 imaju isti stil: znak, veliki broj greške, objašnjenje i dugme "Nazad na početnu".

---

## 8. Pokret

| Token | Trajanje | Upotreba |
|---|---|---|
| `--kt-dur-1` | 120 ms | hover, fokus, boje |
| `--kt-dur-2` | 180 ms | dropdown, tooltip, kartica hover |
| `--kt-dur-3` | 240 ms | modali, sidebar skupljanje, paneli |

Easing: `cubic-bezier(.2,.8,.2,1)` (brz početak, mekan kraj). Bez "odskakanja".
Sve animacije se isključuju za `prefers-reduced-motion: reduce`.

---

## 9. Ikonice
- Isključivo **Lucide** preko `symfony/ux-icons`: `{{ ux_icon('lucide:check', {class: 'kt-icon'}) }}` (inline SVG), debljina linije **1,75**.
  Posle dodavanja novih ikonica: `php bin/console ux:icons:lock` (ikonice se čuvaju lokalno, bez povlačenja sa interneta u produkciji).
- Veličine 16px (u tekstu i tabelama), 18px (dugmad, meni), 20px (topbar). Boja `--kt-text-2`, a aktivna `--kt-primary`.
- Ikonica nikad sama bez tooltipa ili labele, osim univerzalnih (zvonce, pretraga, zatvori).
- **JavaScript** ikonice ne crta ručno: `import { icon } from './icons.js'` i `icon('eye')` uzima ikonicu iz `<template id="kt-js-icons">` (`templates/components/_js_icons.html.twig`, isti `ux_icon`). Nova ikonica se dodaje u tu listu.
- **Jedna ikonica po radnji** (ista radnja = ista ikonica na svim ekranima, različite radnje = različite ikonice):

| Radnja / pojam | Ikonica |
|---|---|
| Zadaci (meni, naslovi, zadatak) | `list-checks` |
| Podsetnik (meni, topbar, panel, kartica, obaveštenje iz Podsetnika) | `notebook-pen` |
| Prikaži kao korisnik (impersonacija: dugme, baner „samo pregled“) | `eye` (režim izmena u baneru: `pencil`, izlaz: `log-out`) |
| Završi stavku Podsetnika / štiklirana stavka checkliste | `square-check` (neštikliran: `square`) |
| Završi zadatak ili deo kolege | `circle-check-big` |
| Moj deo je gotov | `check-check` |
| Odobri / Potvrdi (zadatak, odsustvo, posebna potvrda) | `badge-check` |
| Sačuvaj | `save` |
| Checklista (naslov bloka) | `square-check-big` |
| Katalog poslova | `library` |
| Dodaj / Novo | `plus` |
| Izmeni / Obriši | `pencil` / `trash-2` |
| Izvoz, preuzimanje | `download` |
| Otpremi (upload) | `upload` |
| Uvoz iz NBS | `cloud-download` |
| Promena u KPI kartici | `trending-up` / `trending-down` |
| Izuzetak: naslov događaja u Google kalendaru i ICS koristi znak ✓ jer je tekst u tuđem kalendaru | |

---

## 9a. Dugmad i polja (pravilo, proverava `tests/Ui/UiRulesTest.php`)
- Svako `<button>` ima `btn` + varijantu (`btn-primary`, `btn-outline-secondary`, `btn-danger`, `btn-ghost`, `btn-link`) ili je dokumentovana komponenta (`kt-icon-btn`, `kt-tchip`, `dropdown-item`...); polja imaju `form-control` / `form-select` / `form-check-input`. Novi izuzetak se upisuje u listu u testu.
- **Redosled:** primarna radnja je prva (levo na računaru, gore na telefonu, puna širina u bottom sheet-u), "Odustani" (`btn-outline-secondary`) iza nje. Isto važi za forme i SweetAlert prozore (bez `reverseButtons`).
- "Odustani" izlazi iz forme ili prozora; "Otkaži" samo za poslovne radnje (zadatak, odsustvo). Rečnik: SPEC 8q.
- SweetAlert: `assets/js/confirm.js` mapira dugmad na Bootstrap klase (`confirmButton: btn btn-primary`, `denyButton: btn btn-danger`, `cancelButton: btn btn-outline-secondary`), a poziv samo dodaje svoje (`confirmButton: 'btn btn-danger'` za opasnu radnju). Polja u HTML-u prozora su obični Bootstrap (`.kt-swal .swal2-html-container .form-control`).

---

## 10. Grafikoni (ApexCharts)
- Globalne opcije u `assets/js/charts.js` (ApexCharts): font Inter 12px, boja teksta `--kt-text-2`, linije mreže samo horizontalne,
  tanke i boje `--kt-border`, bez okvira oko grafikona, bez osa koje nisu potrebne.
- Linije debljine 2px, zaobljeni krajevi, tačke samo na hover. Ispod glavne linije providan gradijent iste boje (od 18% do 0%).
- Stubići sa zaobljenim gornjim uglovima (6px), razmak između stubića veći od debljine stubića.
- Tooltip: `--kt-surface`, ivica, `shadow-md`, tabularni brojevi, iznosi u formatu `12.500 RSD`.
- Boje se čitaju iz CSS varijabli, a na `kt:themechange` grafikon se osveži.

---

## 11. Mailovi
- Osnova je naš tabelarni šablon (`templates/emails/base.html.twig`, sav stil inline), sa bojama brenda: pozadina `#F5F6F8`, kartica bela, radius 14px,
  primarno dugme `#006FD6`, tekst `#1F2329` / `#5E6672`.
- Logo: `kontit-app-lockup-email@2x.png` (prikazan u širini 220px), sa apsolutnim URL-om.
- Futer: "KontIT app · Ova poruka je poslata automatski." i link ka podešavanjima obaveštenja.
- Mailovi ostaju u svetloj temi (tamna u mail klijentima nije pouzdana).

---

## 12. Tekst i formati
- Srpski, latinica. Ton: direktan, kratak, kolegijalan, na "ti" ("Tvoji zadaci", "Sačuvaj izmene"). Bez uzvičnika i emodžija u interfejsu.
- Dugmad su glagoli: "Sačuvaj", "Dodaj klijenta", "Pošalji na proveru", a ne "OK" ili "Potvrdi" bez objekta.
- Datum: `12.11.2026.`, vreme: `14:20`, relativno: "pre 5 min", "juče u 14:20".
- Iznosi: `1.234.567,00 RSD` (Twig filter `money`). Procenti: `12,5%`.
- Prazne vrednosti: crtica `—` u `--kt-text-3`, nikad "null" ni prazno polje.

---

## 13. Pristupačnost
- Kontrast teksta najmanje 4,5:1 (tokeni iz sekcije 3 to već zadovoljavaju).
- Fokus mora uvek biti vidljiv (prsten iz 7.6), i za dugmad, linkove i redove tabele kojima se upravlja tastaturom.
- Boja nikad nije jedini nosilac značenja: status ima i tekst, a "kasni" ima i ikonicu.
- Klikabilne površine najmanje 32×32px (na telefonu 44×44px).

---

## 14. Implementacija

### 14.1 Fajlovi
```
assets/styles/
  tokens.css          (sve --kt-* varijable za :root i [data-bs-theme=dark])
  bootstrap-map.css   (mapiranje na --bs-*)
  base.css            (fontovi, tipografija, tabular-nums, selekcija teksta, scrollbar)
  components/         (sidebar.css, topbar.css, card.css, kpi.css, table.css, form.css, button.css,
                       badge.css, avatar.css, checklist.css, timeline.css, empty.css, loader.css,
                       skeleton.css, modal.css, swal.css, toast.css, login.css)
  app.css             (samo importuje gornje fajlove, ovim redom)
assets/js/
  theme.js            (prekidač teme, čuvanje preko AJAX-a, događaj kt:themechange)
  charts.js           (Chart.defaults i helper za boje iz CSS varijabli)
  countup.js          (brojanje KPI vrednosti)
templates/components/
  _logo.html.twig, _logo_mark.html.twig, _loader.html.twig, _empty_state.html.twig,
  _avatar.html.twig, _avatar_stack.html.twig, _status_badge.html.twig, _kpi_card.html.twig
public/
  favicon.svg, favicon.ico, apple-touch-icon.png, icon-192.png, icon-512.png, manifest.webmanifest
  brand/              (ostali fajlovi iz foldera logo/)
```

### 14.2 Mapiranje na Bootstrap
Bootstrap ostaje netaknut. Izgled se menja **samo preko varijabli** u `bootstrap-map.css`:
`--bs-primary`, `--bs-primary-rgb`, `--bs-body-bg`, `--bs-body-color`, `--bs-secondary-color`, `--bs-tertiary-bg`,
`--bs-border-color`, `--bs-border-radius`, `--bs-link-color`, `--bs-focus-ring-color`, `--bs-font-sans-serif`.
Izgled biblioteka (Tom Select, flatpickr, SweetAlert2, ApexCharts, FilePond) se prepisuje u odgovarajućem fajlu iz `components/`, nikad u vendor fajlu.

### 14.3 Redosled "UI osvežavanja"
1. Tokeni, mapiranje i fontovi (posle ovoga se cela aplikacija odmah promeni)
2. Teme: polje `User.themePreference` + migracija, skript bez treptanja, prekidač, `kt:themechange`
3. Logo, favicon i manifest
4. Sidebar i topbar
5. Dugmad, forme, badge-ovi, avatari
6. Tabele i kartice (postojeće liste korisnika, timova, klijenata)
7. Loader, skeleti, prazna stanja, SweetAlert i toast izgled
8. Login, reset lozinke, 403/404/500
9. Email osnova
10. **Pregled svake postojeće strane u obe teme**. Napraviti screenshot-ove ili proći ručno i popraviti ono što odstupa.

Model: prvi prolaz (tačke 1–4) **Opus 5.5**, ostalo **Sonnet 5.5**.

### 14.4 Stranica vodič (opciono, preporučeno)
Ruta `/_design` (samo superadmin i samo u `dev` okruženju) koja prikazuje sve komponente u obe teme: boje, tipografiju,
dugmad, forme, badge-ove, tabele, KPI kartice, loader, prazno stanje. Služi da se odmah vidi kad nešto odstupa.

---

## 15. Šta NE raditi
- Nema jarkih gradijenata po celom interfejsu. Gradijent samo na primarnom dugmetu (jedva vidljiv), app ikoni i login panelu.
- Nema druge akcentne boje pored plave. Ostale boje su samo semantičke (statusi, grafikoni, timovi).
- Nema teških senki, debelih okvira, obojenih zaglavlja kartica ni "zebra" tabela.
- Nema centriranog teksta u tabelama (osim ikonica) i nema dugih tekstova velikim slovima.
- Nema čiste crne pozadine u tamnoj temi i nema čisto belog teksta (koristiti `--kt-text`).
- Nema animacija dužih od 300 ms (osim loadera i brojanja KPI vrednosti).

---

## 16. Mobilni prikaz (pravi prikaz za telefon, ne smanjen desktop)

Prelomne tačke: **telefon < 576px**, tablet 576–991px, desktop ≥ 992px. Testirati na širinama **360, 390 i 430px**.

### 16.1 Prostor
- Bočne margine strane: **12px** na telefonu (desktop 24–32px). Razmak između kartica 12px, unutrašnji razmak kartice 16px.
- **Liste od ivice do ivice**: na telefonu kartice sa listama (zadaci, klijenti) idu preko cele širine, bez bočne margine i zaobljenja, samo sa tankom linijom gore i dole.
- Bez horizontalnog skrolovanja strane, nikad. Za visinu ekrana koristiti `100dvh`.
- Poštovati "notch" i dno iPhone-a: `env(safe-area-inset-*)` za topbar, donju navigaciju i lepljiva dugmad.

### 16.2 Navigacija
- **Donja traka sa tabovima** (fiksirana, 64px + safe area): **Početna · Zadaci · (+) · Klijenti · Više**.
  - Srednje **"+" dugme** je istaknuto (plavi krug) i otvara meni brzih akcija: "Novi zadatak", a za admina i "Novi klijent".
  - Brojač na "Zadaci" (koji kasne ili čekaju potvrdu).
  - "Više" otvara bottom sheet sa ostatkom menija (Timovi, Korisnici, Katalog, Statistika, Profil, Tema, Odjava).
- Sidebar na telefonu **ne postoji**.
- **Topbar na telefonu** (56px): naslov strane na sredini, levo dugme "nazad" na stranama detalja, desno zvonce. Pretraga je ikonica koja otvara pretragu preko celog ekrana.

### 16.3 Tabele postaju liste
- Na telefonu se **nijedna tabela ne prikazuje kao tabela**. Svaki red postaje kartica-red: avatar, glavni red (naziv), sekundarni red (1–2 podatka), status ili badge, i meni "⋯".
- Filteri i sortiranje se otvaraju u **bottom sheet-u** (dugme "Filteri" sa brojem aktivnih filtera).
- Paginacija: dugme "Učitaj još" ili beskonačno skrolovanje, umesto brojeva strana.

### 16.4 Zadaci na telefonu
- Lista zadataka grupisana po roku: **Kasni / Danas / Sutra / Ove nedelje / Kasnije**, sa lepljivim zaglavljima grupa.
- **Prevlačenje reda** (swipe) ulevo otvara brze akcije: "Moj deo je gotov" i "Otvori". Ista akcija postoji i u meniju "⋯".
- Detalj zadatka: checklist je odmah gore, komentari ispod, a polje za komentar je **lepljivo na dnu** kao u chat aplikacijama.
- Dugme "Moj deo je gotov" je lepljivo na dnu ekrana, iznad donje navigacije.

### 16.5 Forme i modali
- Forme u jednoj koloni. Polja visine **44px**, a font u poljima **16px** (inače iPhone sam zumira stranu pri kucanju).
- Pravilni tipovi tastature: `inputmode="numeric"` za PIB i matični broj, `inputmode="decimal"` za iznose, `type="email"`, `type="tel"`.
- Dugmad "Sačuvaj / Odustani" lepljiva na dnu ekrana.
- **Modali postaju bottom sheet-ovi**: izlaze odozdo, imaju ručku za povlačenje i zatvaraju se prevlačenjem nadole.
- SweetAlert na telefonu: puna širina sa marginom od 12px, dugmad jedno ispod drugog (primarno gore).

### 16.6 Dodir
- Površina dodira najmanje **44×44px**.
- Ništa ne sme da zavisi od hover-a: akcije redova su uvek dostupne preko "⋯".
- Ukloniti plavi "bljesak" pri dodiru (`-webkit-tap-highlight-color: transparent`) i umesto njega dati sopstveno pritisnuto stanje.

### 16.7 Instalacija na telefon (PWA)
- `manifest.webmanifest` (naziv "KontIT app", ikonice iz `logo/`, `display: standalone`, boje teme). Bez offline rada za sada.

---

## 17. Sitnice

Sve se stilizuje tokenima, u obe teme, i prikazuje na `/_design` vodiču.

- **Checkbox**: 18px, zaobljen 5px, ivica `--kt-border-strong`. Štikliran: plava ispuna i animirano iscrtana kvačica. Stanje **"delimično"** (crtica) za "označi sve". Klik radi i na labelu, površina klika najmanje 32px.
- **Radio**: isti stil, krug sa unutrašnjom tačkom koja "izraste" pri izboru.
- **Switch**: za uključeno/isključeno podešavanja, plava kad je uključen, sa glatkim pomeranjem kružića.
- **Select (Tom Select)**: ista visina, ivica, radius i fokus kao input. Dropdown sa senkom `md`, hover na opcijama, izabrana opcija ima kvačicu desno, višestruki izbor ima "čipove" sa x. Poruka "Nema rezultata" i loader dok se traži.
- **Datum (flatpickr)**: kalendar u našim bojama, današnji dan obeležen prstenom, izabrani pun plavi, vikend prigušen, praznici (tabela `Holiday`) obeleženi tačkicom. Format `d.m.Y.`, nedelja počinje ponedeljkom (dan je tačno 1/7 reda, oblici stanja idu u `::before`; kontrola na `/_design` je otvorena na novembru 2026), jedan picker u `assets/js/datepicker.js` preko `data-datepicker` / `data-datepicker-range`, prečice "Danas", "Ovaj mesec", "Prošli mesec".
- **Input stanja**: normalno, hover, fokus, greška, uspeh, **disabled** (prigušeno, kursor not-allowed), **readonly** (bez ivice, pozadina `--kt-surface-2`).
- **Ikonica unutar inputa** (pretraga levo, "x" za brisanje desno kad ima teksta).
- **Lozinka**: dugme oko za prikaz i sakrivanje i upozorenje "Caps Lock je uključen".
- **Autofill**: ukloniti žutu/plavu pozadinu Chrome autofill-a (posebno u tamnoj temi), preko `-webkit-autofill` i `box-shadow` trika.
- **Brojevi**: bez strelica na `input[type=number]`, a iznosi preko IMask maske (`1.234,56`). Maske i za PIB, matični broj, telefon i tekući račun.
- **Textarea**: auto-visina (autosize) i brojač karaktera gde postoji limit.
- **Skrol bar**: tanak, u boji `--kt-border-strong`, a u tamnoj temi taman (`color-scheme: dark`).
- **Selekcija teksta**: plava providna (`::selection`).
- **Tooltip**: tamna pozadina u obe teme, mali radius, pojavljuje se posle 300 ms.
- **Prelazi između strana**: View Transitions API (`@view-transition { navigation: auto; }`), bez JS-a. Isključen za `prefers-reduced-motion`.
- **Prazno polje u prikazu**: crtica `—`, nikad prazno.

---

## 18. Komponente iz UPDATE-POSLOVI-KALENDAR (sve su na `/_design`)
- **Badge kategorije / vrste** (`ui.category_badge`, `.kt-cat-badge`): meka pozadina u boji iz baze (`--kt-cat`), opciona Lucide ikonica.
- **Segmentirana dugmad** (`.kt-segmented`): izbor jedne opcije (raspored posla, za klijenta / interni, opseg kalendara). Radio se iscrtava ručno (`btn-check` + `label.btn`), bez labele iz Symfony teme. Na telefonu se ne lome u dva reda.
- **Raspored posla** (`components/schedule_form.html.twig`): prikazuju se samo polja izabranog tipa (sakriva se cela kolona), ispod opis rečima i narednih 5 rokova; pomeren rok je u boji upozorenja, sa ikonicom i tooltip-om.
- **Checklist editor** (`components/checklist_editor.html.twig`): ručka, polje, ✕; Enter dodaje, Backspace u praznom briše; brojač "3 stavke".
- **Job picker** (`components/_job_picker.html.twig`): grupe po kategoriji, pretraga bez kvačica, desno izabrani sa redosledom; na telefonu jedna kolona i sklopljena lista izabranih.
- **Godišnji kalendar** (`.kt-year`): 12 malih meseci; praznik crveno, kolektivni odmor plavo, vikend prigušen.
- **Odsustva**: traka stanja godišnjeg (`.kt-leave-strip`), kartica na odobrenju sa odbrojavanjem (na telefonu prevlačenje za Odobri / Odbij), mesečni pregled radnik × dani (`.kt-absence-grid`, šrafirano = na odobrenju).
- **Kalendar** (`components/calendar.css`): FullCalendar u tokenima (`--fc-*` promenljive), zadatak u boji statusa sa "!" za hitan, odsustvo u boji vrste sa inicijalima, praznici kao pozadina; filteri kao čipovi (`.kt-chip`); popover u Bootstrap-u. Mali pregled naredne nedelje (`.kt-week`) na dashboardu.

## 19. Uputstvo (`/uputstvo`, `components/help.css`, na `/_design`)
- Raspored: kartica **Sadržaj** (pretraga + numerisana poglavlja sa potpoglavljima) i kartica sa tekstom. Na desktopu (≥ 992px) sadržaj je kolona od 280px, lepljiva dok se čita; na telefonu je na vrhu, može da se skupi i prikazuje samo poglavlja.
- Tekst (`.kt-help`): najviše 780px širine, 15px / 1,65, naslovi u display fontu, h2 numerisani u boji brenda, poglavlja odvojena linijom, `scroll-margin-top` ispod gornje trake.
- Okviri: `blockquote.is-tip` (zeleno, „Savet“) i `blockquote.is-warn` (narandžasto, „Pažnja“ / „Važno“), sa trakom levo i mekom pozadinom.
- Mesto za sliku (`.kt-help-shot.is-placeholder`): isprekidan okvir, oznaka „Slika“ i opis. Prava slika dobija ivicu, radius i blagu senku.
- Tabele u `table-responsive` sa ivicom; `kbd` i `code` u mono fontu.
- Dugme „nazad na sadržaj“: okruglo 44px dole desno (na telefonu iznad donje trake), pojavljuje se kad sadržaj izađe iz ekrana.
