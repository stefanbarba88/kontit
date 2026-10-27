# UPDATE-DIZAJN.md — Novi UI sistem, mobilni prikaz, avatari, NBS

> **Uputstvo za Claude Code:**
> 1. Pročitaj ceo fajl, pa CLAUDE.md, DESIGN.md, SPEC.md i PROGRESS.md.
> 2. **Pre bilo kakve izmene** napravi commit trenutnog stanja (tačka povratka).
> 3. Izmeni CLAUDE.md, DESIGN.md i SPEC.md po sekciji 8.
> 4. Pokaži mi plan po koracima iz sekcije 9 i **sačekaj potvrdu**. Radi korak po korak, sa commitom posle svakog.
> 5. Na kraju ažuriraj PROGRESS.md, a ovaj fajl premesti u `docs/archive/`.

---

## 1. Uklanjanje kupljenog/preuzetog template-a

Aplikacija **više ne koristi** template iz `_template/` (ni admin ni email). Izgled gradimo sami:
**Bootstrap 5.3 kao osnova** (grid, utility klase, JS komponente) + **tokeni i komponente iz DESIGN.md** + biblioteke iz sekcije 2.

Uraditi:
- Obrisati `_template/`, `assets/vendor/template/` i sve importe CSS/JS iz template-a.
- `TEMPLATE_INVENTORY.md` premestiti u `docs/archive/`.
- Ponovo napisati `base.html.twig`, sidebar, topbar, login i sve postojeće stranice **našim markup-om**:
  Bootstrap klase + naše komponente iz `templates/components/`. Posle toga grep-om proveri da nijedna klasa iz template-a
  nije ostala u `templates/` i `assets/`.
- Email osnovu (`templates/emails/base.html.twig`) napraviti sami: tabelarni HTML, sve inline, po DESIGN.md sekciji 11.
- **Ne menjati** entitete, rute, Votere ni poslovnu logiku (osim sekcija 4 i 5 ovog fajla).

---

## 2. Biblioteke

Sve preko **AssetMapper importmap-a** (`php bin/console importmap:require <paket>`), bez Node-a.
CSS biblioteka se uvozi posebno (npr. `importmap:require tom-select/dist/css/tom-select.default.min.css`), a zatim
se **prepisuje našim tokenima** u odgovarajućem fajlu iz `assets/styles/components/`. Vendor CSS se ne menja.
Pre instalacije proveri trenutnu stabilnu verziju i da paket radi kao ES modul preko importmap-a.

### 2.1 Frontend

| Namena | Biblioteka | Napomena |
|---|---|---|
| **Ikonice** | **Lucide** preko **`symfony/ux-icons`** | U Twigu `{{ ux_icon('lucide:check', {class: 'kt-icon'}) }}` renderuje inline SVG. Posle dodavanja novih ikonica pokreni `php bin/console ux:icons:lock`, da se ikonice čuvaju lokalno i ne povlače sa interneta u produkciji. **Bootstrap Icons se uklanja.** |
| **Datum i period** | **flatpickr** + srpski latinični locale (`sr`) | Format `d.m.Y.`, nedelja počinje ponedeljkom, prečice "Danas", "Ovaj mesec", "Prošli mesec" za periode u statistici. Na telefonu koristi svoj stilizovan prikaz, ne nativni. |
| **Select / pretraga / višestruki izbor** | **Tom Select** | Za izbor klijenta, radnika, učesnika zadatka (sa avatarom u opciji), paketa. Udaljena pretraga preko `/ajax/...` za velike liste. |
| **Potvrde i toast** | **SweetAlert2** | Već dogovoreno u UPDATE.md, sada stilizovano po DESIGN.md. |
| **Grafikoni** | **ApexCharts** (umesto Chart.js) | Lepši podrazumevani izgled, ugrađeni sparkline-ovi, tamna tema, heatmap za kalendar opterećenja. Chart.js se uklanja. |
| **Drag & drop** | **SortableJS** | Checklist, redosled poslova u paketu. |
| **Maske za unos** | **IMask** | PIB (9 cifara), matični broj (8 cifara), telefon, iznosi (`1.234,56`), tekući račun (`XXX-XXXXXXXXXXXXX-XX`). |
| **Brojanje KPI vrednosti** | **CountUp.js** | |
| **Upload dokumenata (PDF)** | **FilePond** + plugin za proveru tipa i veličine | Prevlačenje fajlova, napredak uploada, lista priloženih fajlova. Za sada samo PDF, JPG i PNG, do 20 MB. |
| **Pregled PDF-a u aplikaciji** | **PDF.js** (`pdfjs-dist`) | Klik na prilog otvara pregled u modalu (na telefonu preko celog ekrana), bez preuzimanja. |
| **Auto-visina textarea** | **autosize** | Komentari i opisi rastu dok se kuca. |

### 2.2 Backend

| Namena | Paket | Napomena |
|---|---|---|
| **Generisanje PDF-a** (izveštaji, liste, kartica klijenta) | **dompdf/dompdf** | Radi bez dodatnog softvera na serveru. PDF šabloni u `templates/pdf/`, sa logom (`kontit-logo.svg` kao PNG), fontom Inter i bojama brenda. Ako kasnije bude Docker na serveru, može se preći na Gotenberg. |
| **Izvoz u Excel** | **phpoffice/phpspreadsheet** | Formatirano zaglavlje, širine kolona, iznosi kao brojevi (ne tekst), zamrznut prvi red, autofilter. |
| **Izvoz u CSV** | **league/csv** | UTF-8 sa BOM-om i separatorom `;`, da se srpska slova i kolone ispravno otvore u Excelu. |
| **Relativno vreme** ("pre 5 min") | `knplabs/knp-time-bundle` ili sopstveni Twig filter | Ako bundle nema dobar srpski latinični prevod, napraviti sopstveni filter `ago`. |

### 2.3 Pravila za izvoz
- Svaka lista (klijenti, zadaci, korisnici, kasnije statistika) ima dugme **"Izvoz"** sa izborom **Excel / CSV / PDF**.
- Izvozi se **ono što je trenutno filtrirano**, i to **samo redovi koje korisnik sme da vidi** (isti upit i isti Voteri kao lista).
- Cene i finansije se izvoze samo adminu i superadminu.
- Naziv fajla: `kontit-klijenti-2026-10-27.xlsx`. Veliki izvozi (preko 2.000 redova) idu preko Messenger-a, a korisnik dobije notifikaciju sa linkom kad je fajl spreman.

### 2.4 Šta se uklanja iz stacka
Bootstrap Icons, Chart.js, `liip/imagine-bundle` i, ako se ne koristi ni za šta drugo osim avatara, `vich/uploader-bundle`.
Za priloge dokumenata (FilePond) Vich može da ostane, i to treba odlučiti tek kada se prave prilozi.

---

## 3. Sitnice koje čine razliku

Sve se stilizuje tokenima iz DESIGN.md, u obe teme. Napraviti ih **pre** novih ekrana i prikazati na `/_design` vodiču.

- **Checkbox**: 18px, zaobljen 5px, ivica `--kt-border-strong`. Štikliran: plava ispuna i animirano iscrtana kvačica.
  Postoji i stanje **"delimično"** (crtica), na primer za "označi sve" u tabeli. Klik radi i na labelu, a površina klika je najmanje 32px.
- **Radio**: isti stil, krug sa unutrašnjom tačkom koja "izraste" pri izboru.
- **Switch** (prekidač): za uključeno/isključeno podešavanja (npr. "Pošalji i na mail"), plava kad je uključen, sa glatkim pomeranjem kružića.
- **Select (Tom Select)**: ista visina, ivica, radius i fokus kao input. Dropdown sa senkom `md`, opcije sa hover stanjem,
  izabrana opcija ima kvačicu desno, a višestruki izbor ima "čipove" sa x za uklanjanje. Poruka "Nema rezultata" i loader dok se traži.
- **Datum (flatpickr)**: kalendar u našim bojama, današnji dan obeležen prstenom, izabrani pun plavi, vikend tekst prigušen,
  dani praznika (iz tabele `Holiday`) obeleženi tačkicom.
- **Input stanja**: normalno, hover, fokus, greška, uspeh, **disabled** (prigušeno, kursor not-allowed), **readonly** (bez ivice, pozadina `--kt-surface-2`).
- **Ikonica unutar inputa** (pretraga levo, "x" za brisanje desno kad ima teksta).
- **Lozinka**: dugme oko za prikaz i sakrivanje i upozorenje "Caps Lock je uključen".
- **Autofill**: ukloniti žutu/plavu pozadinu Chrome autofill-a (posebno u tamnoj temi), preko `-webkit-autofill` i `box-shadow` trika.
- **Brojevi**: bez strelica na `input[type=number]`, a iznosi preko IMask maske.
- **Textarea**: auto-visina i brojač karaktera gde postoji limit.
- **Skrol bar**: tanak, u boji `--kt-border-strong`, a u tamnoj temi taman (`color-scheme: dark`).
- **Selekcija teksta**: plava providna (`::selection`).
- **Tooltip**: tamna pozadina u obe teme, mali radius, pojavljuje se posle 300 ms.
- **Prelazi između strana**: **View Transitions API** (`@view-transition { navigation: auto; }`) za mekan prelaz između strana
  u browserima koji to podržavaju, bez ikakvog JS-a. Isključen za `prefers-reduced-motion`.
- **Prazno polje u prikazu**: crtica `—`, nikad prazno.

---

## 4. Mobilni prikaz (pravi prikaz za telefon, ne smanjen desktop)

Prelomne tačke: **telefon < 576px**, tablet 576–991px, desktop ≥ 992px. Testirati na širinama **360, 390 i 430px**.

### 4.1 Prostor
- Bočne margine strane: **12px** na telefonu (desktop 24–32px). Razmak između kartica 12px, unutrašnji razmak kartice 16px.
- **Liste od ivice do ivice**: na telefonu kartice sa listama (zadaci, klijenti) idu preko cele širine, bez bočne margine i zaobljenja,
  samo sa tankom linijom gore i dole, kao u nativnim aplikacijama.
- Bez horizontalnog skrolovanja strane, nikad. Za visinu ekrana koristiti `100dvh`.
- Poštovati "notch" i dno iPhone-a: `env(safe-area-inset-*)` za topbar, donju navigaciju i lepljiva dugmad.

### 4.2 Navigacija
- **Donja traka sa tabovima** (fiksirana, 64px + safe area): **Početna · Zadaci · (+) · Klijenti · Više**.
  - Srednje **"+" dugme** je istaknuto (plavi krug) i otvara meni brzih akcija: "Novi zadatak", a za admina i "Novi klijent".
  - Brojač na "Zadaci" (koji kasne ili čekaju potvrdu).
  - "Više" otvara bottom sheet sa ostatkom menija (Timovi, Korisnici, Katalog, Statistika, Profil, Tema, Odjava).
- Sidebar na telefonu **ne postoji**.
- **Topbar na telefonu** (56px): naslov strane na sredini, levo dugme "nazad" na stranama detalja, desno zvonce.
  Pretraga je ikonica koja otvara pretragu preko celog ekrana.

### 4.3 Tabele postaju liste
- Na telefonu se **nijedna tabela ne prikazuje kao tabela**. Svaki red postaje kartica-red:
  ```
  [avatar]  Firma X doo                    ⋯
            PIB 123456789 · Marko M.
            ● U radu · 3 zadatka
  ```
- Za svaku listu odrediti: glavni red (naziv), sekundarni red (1–2 podatka), status ili badge, i meni "⋯".
- Filteri i sortiranje se otvaraju u **bottom sheet-u** (dugme "Filteri" sa brojem aktivnih filtera).
- Paginacija: dugme "Učitaj još" ili beskonačno skrolovanje, umesto brojeva strana.

### 4.4 Zadaci na telefonu
- Lista zadataka grupisana po roku: **Kasni / Danas / Sutra / Ove nedelje / Kasnije**, sa lepljivim zaglavljima grupa.
- **Prevlačenje reda** (swipe) ulevo otvara brze akcije: "Moj deo je gotov" i "Otvori". Ova akcija ima i alternativu u meniju "⋯".
- Detalj zadatka: checklist je odmah gore (najčešća radnja), komentari ispod, a polje za komentar je **lepljivo na dnu** kao u chat aplikacijama.
- Dugme "Moj deo je gotov" je lepljivo na dnu ekrana, iznad donje navigacije.

### 4.5 Forme i modali
- Forme u jednoj koloni. Polja visine **44px**, a font u poljima **16px** (inače iPhone sam zumira stranu pri kucanju).
- Pravilni tipovi tastature: `inputmode="numeric"` za PIB i matični broj, `inputmode="decimal"` za iznose, `type="email"`, `type="tel"`.
- Dugmad "Sačuvaj / Otkaži" lepljiva na dnu ekrana.
- **Modali postaju bottom sheet-ovi**: izlaze odozdo, imaju ručku za povlačenje i zatvaraju se prevlačenjem nadole.
- SweetAlert na telefonu: pun širina sa marginom od 12px, dugmad jedno ispod drugog (primarno gore).

### 4.6 Dodir
- Površina dodira najmanje **44×44px**.
- Ništa ne sme da zavisi od hover-a: akcije redova su uvek dostupne preko "⋯".
- Ukloniti plavi "bljesak" pri dodiru (`-webkit-tap-highlight-color: transparent`) i umesto njega dati sopstveno pritisnuto stanje.

### 4.7 Instalacija na telefon (PWA)
- `manifest.webmanifest` (naziv "KontIT app", ikonice iz `logo/`, `display: standalone`, boje teme), da radnik može da doda aplikaciju na početni ekran
  i otvara je bez trake browsera. Bez offline rada za sada.

---

## 5. Avatari: samo inicijali

- **Uklanja se upload slike za avatar.** Avatar su isključivo **inicijali na gradijentu** (DESIGN.md 7.9), gde je boja izvedena iz imena.
- Ukloniti polje iz forme, upload logiku i prikaz slike.
- Nova migracija briše kolonu avatara iz `user` tabele (ne menjati stare migracije).
- Već uploadovane slike u `public/uploads/avatars/` **ne brisati automatski**: navedi mi putanju i broj fajlova, pa ću ih obrisati ja.
- Ukloniti `liip/imagine-bundle`, a `vich/uploader-bundle` po pravilu iz 2.4.
- Inicijali: prvo slovo imena + prvo slovo prezimena, velikim slovima, sa ispravnim slovima Č, Ć, Š, Ž, Đ (npr. "Đorđe Šarić" = "ĐŠ").
  Za "Dž" koristiti samo "D" (jedno slovo po imenu).

---

## 6. NBS učitavanje podataka o klijentu: ne radi, popraviti

Na formi klijenta postoji učitavanje podataka iz NBS-a (po PIB-u ili matičnom broju), i **ne radi**.

### 6.1 Prvo dijagnoza (pre bilo kakve izmene)
Proveri i javi mi:
1. **Koji izvor se poziva**: zvanični NBS veb servis ili javna stranica "Pretraga tekućih računa rezidenata"
   (`webappcenter.nbs.rs/PnWebApp/CompanyAccount/CompanyAccountResident`)?
2. Pokreni isti zahtev iz komandne linije (Symfony komanda ili curl) za jedan poznat PIB i pokaži **status kod i početak odgovora**.
3. Proveri log (`var/log/`) i grešku koju AJAX vraća u browser konzoli.

### 6.2 Najčešći uzroci
- **Zvanični NBS veb servis traži članstvo**: pristup imaju samo pravna lica i preduzetnici koji prođu proces učlanjenja,
  a besplatni servisi (među njima i servis za pristup registru imalaca računa) dobijaju se tek posle toga. Bez korisničkog imena, lozinke
  i licence servis ne može da radi. Tehnička dokumentacija je na `webservices.nbs.rs/WebSiteDoc`.
- **Javna stranica je HTML forma, a ne API**: traži prvo GET zahtev (kolačići i eventualni skriveni token forme), pa onda pretragu.
  Odgovor je HTML koji treba parsirati.
- **Odgovor je na ćirilici**: regex i parsiranje napisani za latinicu neće ništa naći. Podatke treba **parsirati na ćirilici**,
  pa **preslovljavati u latinicu** pre upisa (naziv, adresa, mesto).
- Zahtev bez `User-Agent` zaglavlja ili sa previše kratkim timeout-om.
- Problem sa SSL sertifikatom na serveru (zastareo CA bundle).
- Browser poziva NBS direktno iz JS-a (CORS blokada). **Poziv mora ići preko našeg servera**.

### 6.3 Kako treba da radi
- Servis `NbsCompanyLookupService` sa interfejsom, da se izvor može zameniti:
  - `NbsWebServiceProvider` (zvanični servis, kredencijali u `.env.local`: `NBS_WS_USERNAME`, `NBS_WS_PASSWORD`, `NBS_WS_LICENCE`, nikad u git-u)
  - `NbsPublicPageProvider` (javna stranica, kao rezerva)
  - koji se koristi bira se u `.env`, a ako zvanični nije podešen, koristi se rezervni.
- AJAX ruta `/ajax/client/lookup?pib=...` vraća: naziv, adresu, mesto, PIB, matični broj, tekuće račune.
- **Pre poziva** proveriti kontrolnu cifru: PIB (9 cifara, ISO 7064 MOD 11,10) i matični broj (8 cifara, MOD 11).
  Pogrešan broj se odbija odmah, bez poziva NBS-u. Ista provera i kao Symfony validator na entitetu.
- **Keš** rezultata 24h po PIB-u (Symfony Cache), timeout 8 s, rate limit po korisniku.
- UI: dugme "Učitaj iz NBS" pored polja PIB, loader na dugmetu, a posle učitavanja popunjena polja kratko **zasijaju** (highlight 1 s).
  Ako korisnik već ima uneta polja, pita se SweetAlert-om da li da ih prepiše.
- Kad NBS ne odgovori ili ne nađe firmu: jasna poruka ("NBS trenutno ne odgovara, unesi podatke ručno"), i **forma se uvek može sačuvati ručno**.
- Napisati test za parser sa sačuvanim primerom HTML/XML odgovora (`tests/Fixtures/nbs/`), da se odmah vidi kad NBS promeni format.

**Napomena za Stefana:** za trajno i pouzdano rešenje firma treba da se učlani u sistem NBS veb servisa i dobije kredencijale.
Javna stranica je samo privremena rezerva, jer može da promeni izgled bez najave.

---

## 7. Izmene u DESIGN.md (pretraga, pa zamena)

- **7.6 Forme**: "select dodatak iz template-a" → **Tom Select**, stilizovan tokenima.
- **7.9 Avatari**: ukloniti pominjanje slike, samo inicijali.
- **9 Ikonice**: "iz seta koji koristi template" → **Lucide preko `symfony/ux-icons`**, stroke 1,75, veličine ostaju.
- **10 Grafikoni**: Chart.js → **ApexCharts**, ista pravila izgleda (font, mreža, tooltip, boje iz CSS varijabli, osvežavanje na `kt:themechange`).
- **11 Mailovi**: osnova je naš tabelarni šablon, a ne template.
- **14.2**: ukloniti mapiranje na varijable template-a, ostaje samo mapiranje na `--bs-*`.
- **Dodati sekciju 16 "Mobilni prikaz"** sa sadržajem sekcije 4 ovog fajla, i **sekciju 17 "Sitnice"** sa sadržajem sekcije 3.
- U uputstvo na vrhu DESIGN.md: ukloniti rečenicu o odnosu sa template-om.

---

## 8. Izmene u CLAUDE.md i SPEC.md

### CLAUDE.md
- **Sekciju "UI template (OBAVEZNO)" zameniti** sekcijom **"UI sistem (OBAVEZNO)"**:
  1. Izgled se gradi na **Bootstrap 5.3 + tokenima i komponentama iz DESIGN.md**. Nema kupljenog template-a.
  2. Pre svakog UI posla pročitaj DESIGN.md.
  3. Dozvoljene biblioteke su **samo one iz DESIGN.md / UPDATE-DIZAJN sekcije 2**. Nova biblioteka samo uz moje odobrenje.
  4. Ikonice **samo Lucide** preko `ux_icon()`. Posle dodavanja novih pokrenuti `ux:icons:lock`.
  5. Vendor CSS se ne menja, a izgled biblioteka se prepisuje u `assets/styles/components/`.
  6. **Mobile-first**: svaki novi ekran se pravi i proverava na 390px pre desktopa. Tabela na telefonu je uvek lista (DESIGN.md 16).
  7. Svaka nova komponenta se dodaje na `/_design` vodič, u obe teme.
- **Stack**: ukloniti Bootstrap Icons, Chart.js, `liip/imagine-bundle` (i Vich po 2.4). Dodati biblioteke iz sekcije 2.
- Pravilo za izvoz: "Izvoz uvek koristi isti upit i iste Votere kao lista koju izvozi."

### SPEC.md
- **User**: polje `avatar` → ukloniti, i dopisati "avatar = inicijali, bez slike". Dodati `themePreference` ako već nije.
- **Client**: dodati opis NBS učitavanja (sekcija 6.3) i proveru kontrolnih cifara PIB-a i MB-a.
- **Nova sekcija "Izvoz"** sa pravilima iz 2.3.

---

## 9. Redosled rada

| # | Korak | Model |
|---|---|---|
| 1 | Commit, pa **NBS dijagnoza** (6.1), izveštaj meni, pa popravka (6.3) | Opus 5.5 |
| 2 | Uklanjanje template-a, instalacija biblioteka, novi `base.html.twig`, sidebar, topbar | Opus 5.5 |
| 3 | Sitnice (sekcija 3) i `/_design` vodič | Sonnet 5.5 |
| 4 | Mobilni prikaz: donja navigacija, liste umesto tabela, bottom sheet-ovi, forme | Opus 5.5 |
| 5 | Prebacivanje svih postojećih strana na novi sistem (login, korisnici, timovi, klijenti) | Sonnet 5.5 |
| 6 | Avatari samo inicijali (sekcija 5) | Sonnet 5.5 |
| 7 | Izvoz Excel / CSV / PDF za postojeće liste | Sonnet 5.5 |
| 8 | Pregled svake strane u obe teme na 390px i 1440px, lista odstupanja i popravke | Sonnet 5.5 |

Posle svakog koraka: commit, kratak izveštaj šta je urađeno i šta treba da proverim.
