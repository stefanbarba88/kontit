# UPDATE-POSLOVI-KALENDAR.md — Poslovi, paketi, zadaci, neradni dani, odsustva, kalendar, dokumenti

> **Uputstvo za Claude Code:**
> 1. Pročitaj ceo fajl, pa CLAUDE.md, DESIGN.md, SPEC.md i PROGRESS.md. Pogledaj i šta je od UPDATE.md već urađeno u kodu.
> 2. Napravi commit trenutnog stanja (tačka povratka).
> 3. **Ovaj fajl ima prednost nad UPDATE.md** za katalog poslova, pakete, ClientJob i generisanje zadataka (sekcije 2 i 3 u UPDATE.md).
>    Gde se razlikuju, važi ovaj fajl. `Holiday` tabela iz UPDATE.md se zamenjuje modulom iz sekcije 5.
> 4. Ugradi sadržaj u SPEC.md (nove sekcije ili dopune postojećih), ažuriraj listu faza po sekciji 12 i prebaci nedoumice iz sekcije 13.
> 5. Pokaži mi plan po koracima iz sekcije 12, sa spiskom migracija i onim što menjaš u postojećem kodu, pa **sačekaj potvrdu**.
>    Radi korak po korak, sa commitom posle svakog koraka.
> 6. Na kraju ažuriraj PROGRESS.md, a ovaj fajl premesti u `docs/archive/`.

**Faza 8 (praćenje vremena preko agenta) se odlaže.** U SPEC.md je označi kao "odloženo", ne briši je i ne radi ništa na njoj.

---

## 1. Sitne izmene

### 1.1 Aktivnosti na profilu korisnika: paginacija
- Profil više ne učitava sve aktivnosti odjednom. Prvo se prikazuje **20 poslednjih**, a ispod je dugme **"Učitaj još"** (AJAX, sledećih 20).
- Ruta `/ajax/users/{id}/activity?page=N`, koja vraća HTML fragment timeline-a i podatak da li ima još.
- Iznad timeline-a filteri: **tip akcije** i **period** (flatpickr opseg). Promena filtera ponovo učitava od prve strane.
- Upit ide sa `LIMIT/OFFSET` i indeksom na `(entity_type, entity_id, created_at)` i `(user_id, created_at)`.
  Proveri da indeksi postoje, a ako ne postoje, dodaj ih migracijom.
- Ista paginacija važi za timeline na klijentu i na zadatku.
- Dok se učitava: skelet (DESIGN.md 7.13). Prazno stanje: "Još nema aktivnosti."

### 1.2 Klijent: kraći naziv
- Novo polje **`Client.shortName`** (obavezno, do 40 karaktera, npr. "Mlekara Ivanjica" umesto "Mlekara Ivanjica doo za proizvodnju i promet").
- **Kraći naziv se koristi svuda**: liste, badge-ovi, Tom Select opcije, naslovi zadataka, kalendar, notifikacije, mailovi.
- **Pun naziv** (`name`) se prikazuje samo na kartici klijenta, u PDF izveštajima i izvozima (kao posebna kolona).
- Migracija: postojećim klijentima `shortName` se popuni iz `name` (skraćeno na 40 karaktera), pa ih ja kasnije ručno ispravim.
- Pri NBS učitavanju: `name` = zvaničan naziv iz NBS-a, a `shortName` se predloži (naziv bez pravne forme i dodataka tipa
  "doo", "d.o.o.", "preduzetnik", "PR") i korisnik ga može izmeniti.
- U pretrazi (Ctrl K, liste) traži se po oba naziva i po PIB-u.

### 1.3 Klijent: uklanja se pravna forma
- Uklanja se polje **`legalForm`** iz entiteta, forme, prikaza i izvoza, kao i enum `LegalForm`.
- Nova migracija briše kolonu (ne menjati stare migracije).
- Proveri da nigde u kodu i šablonima nije ostala referenca (grep za `legalForm`, `legal_form`, `LegalForm`).

---

## 2. Katalog: kategorije poslova i poslovi

### 2.1 Kategorije poslova (novi CRUD)
- Entitet **`JobCategory`**: name, color (iz 8 boja u DESIGN.md 3.6), icon (Lucide naziv ikonice, opciono), position, isActive.
- CRUD za admin+: lista sa drag & drop redosledom (SortableJS), dodavanje i izmena u modalu, deaktivacija umesto brisanja
  (kategorija koja ima poslove ne može da se obriše, samo deaktivira, uz SweetAlert objašnjenje).
- `JobTemplate.category` postaje **veza na `JobCategory`** umesto enuma. Migracija: od postojećih vrednosti enuma napravi
  kategorije (Knjiženje, PDV, Plate, Prijave, Završni račun, Savetovanje, Administrativno, Ostalo) i poveži postojeće poslove.
- Kategorija se prikazuje kao badge u boji, svuda gde se pojavljuje posao.

### 2.2 Posao (JobTemplate)
Posao je šablon nečega što se radi (knjiženje, PDV prijava, završni račun, kalkulacije, prijava/odjava radnika).

Polja:
- name, category, description
- **checklist stavke** (`JobTemplateChecklistItem`: text, position)
- **raspored izvršavanja** (sekcija 2.3)
- defaultPriority, completionMode (ALL/ANY)
- estimatedMinutes (procena trajanja, za analitiku)
- **defaultPrice** (predlog cene kada se posao ugovara pojedinačno), priceUnit (MESECNO, PO_IZVRSENJU, GODISNJE)
- isActive

### 2.3 Raspored izvršavanja posla
Svaki posao ima **raspored** koji kaže kada sistem sam kreira zadatak. **Ako je raspored prazan ("Po potrebi"), sistem ne kreira zadatke**,
nego ih korisnik kreira ručno kad zatreba (npr. prijava ili odjava radnika).

| Tip | Podešavanja | Primer |
|---|---|---|
| **Po potrebi** (NONE) | nema | Prijava/odjava radnika |
| **Dnevno** (DAILY) | na svakih N dana; samo radni dani (da/ne) | Knjiženje izvoda, svaki radni dan |
| **Nedeljno** (WEEKLY) | na svakih N nedelja; dan u nedelji | Svakog ponedeljka |
| **Mesečno** (MONTHLY) | na svakih N meseci; dan u mesecu (1–31 ili "poslednji dan") | PDV prijava, 15. u mesecu |
| **Kvartalno** (QUARTERLY) | koji mesec posle kvartala (1–3); dan u mesecu | 20. dan posle isteka kvartala |
| **Godišnje** (YEARLY) | mesec i dan | Završni račun, 30. jun |

Zajednička podešavanja:
- **leadDays**: koliko dana pre roka se zadatak kreira (npr. 10). Za DAILY obično 0.
- **Rok pada na neradni dan** (sekcija 5): **pomeri na prvi sledeći radni dan** (podrazumevano) ili **pomeri na prethodni radni dan**
  (za rokove koji moraju biti završeni ranije). Ako je izabrano "samo radni dani" kod DAILY, neradni dani se preskaču.
- Dan 31 u mesecu koji ga nema = poslednji dan tog meseca.

Entitet: polja se čuvaju na JobTemplate (`scheduleType`, `scheduleInterval`, `scheduleDayOfWeek`, `scheduleDayOfMonth`,
`scheduleMonthOfQuarter`, `scheduleMonth`, `workingDaysOnly`, `leadDays`, `nonWorkingDayShift`). Isti skup polja (svi nullable)
postoji kao **override** na ClientJob (sekcija 3), pa jedan klijent može imati drugačiji rok za isti posao.
Ako je override prazan, važi raspored posla.

**Servis `ScheduleCalculator`** (jedno mesto za svu logiku rasporeda, sa unit testovima):
- `nextOccurrences(schedule, from, count)` vraća naredne rokove, već pomerene zbog neradnih dana
- `periodKey(schedule, date)` vraća ključ perioda (`2026-11-03`, `2026-W45`, `2026-11`, `2026-Q4`, `2026`)
- `describe(schedule)` vraća opis rečima: "Mesečno, 15. u mesecu (ako je neradni dan, prvi sledeći radni dan)"

Testovi moraju da pokriju: 31. u februaru, prestupnu godinu, rok na vikend i praznik, kolektivni odmor preko kraja meseca,
"poslednji dan" u mesecu, DAILY samo radnim danima, kvartal preko kraja godine.

**UI rasporeda u formi posla:**
- Prvo se bira tip (segmentirana dugmad: Po potrebi · Dnevno · Nedeljno · Mesečno · Kvartalno · Godišnje),
  a zatim se prikazuju samo polja za taj tip.
- Ispod je **opis rečima** i **pregled narednih 5 rokova** (AJAX, osvežava se pri svakoj promeni), npr.
  "16. nov 2026. (pon) · 15. dec 2026. (uto) · 15. jan 2027. (čet) …". Pomereni rok je označen ikonicom i tooltipom
  "15. nov je nedelja, pomereno na 16. nov".

### 2.4 Checklist u formi posla (umesto textarea)
Checklist stavke posla se unose kao **prava lista**, ne kao jedan textarea:
- Svaka stavka je jedan red: ručka za prevlačenje (SortableJS) · polje za tekst · dugme za brisanje (Lucide `x`).
- Dugme **"+ Dodaj stavku"** ispod liste. **Enter** u poslednjem polju dodaje novu stavku i prebacuje fokus na nju,
  a **Backspace** u praznom polju briše stavku i vraća fokus na prethodnu.
- Prazne stavke se ignorišu pri čuvanju. Brojač "5 stavki" pored naslova.
- Tehnički: Symfony `CollectionType` sa `allow_add`, `allow_delete`, `by_reference: false` i prototipom, a redosled se upisuje u `position`.
- Isti komponent (Twig makro + `assets/js/checklist-editor.js`) koristi se i za checklist na zadatku pri ručnom kreiranju i izmeni.

---

## 3. Paketi i ugovorene usluge klijenta

### 3.1 Model
```
JobCategory ─┐
             └─< JobTemplate (posao, sa checklistom i rasporedom)
                       │
ServicePackage ───< ServicePackageItem >── JobTemplate     (paket = skup poslova, sa cenom)

Client ───< ClientPackage >── ServicePackage                (klijent može imati više paketa)
   │             │
   └──────< ClientJob >── JobTemplate                       (UGOVORENI posao: iz paketa ili pojedinačan)
                 │
                 └──< Task                                  (zadaci nastaju iz ClientJob-a)
```

- **ServicePackage**: name, description, **price** (mesečna cena), isActive, items (poslovi, sa redosledom).
- **ClientPackage**: client, package, **agreedPrice** (dogovorena cena, predlaže se cena paketa), **note**, startDate, endDate, isActive.
  Promene cene se beleže u `ClientPackagePriceLog`.
- **ClientJob** (ugovoreni posao): client, jobTemplate, **source** (PACKAGE / INDIVIDUAL), clientPackage (ako je iz paketa),
  **price** (samo za pojedinačne), **note**, override rasporeda (2.3), override zaduženog, startDate, endDate, isActive,
  **isExcluded** + excludedReason, excludedBy, excludedAt.

**Ugovoreni poslovi klijenta** = svi ClientJob-ovi koji su aktivni i nisu isključeni. Samo iz njih nastaju zadaci.

### 3.2 Forma za paket: izbor poslova
Paket može imati mnogo poslova, pa se biraju preko **grupisane liste sa pretragom** (ne običan select):
```
┌ Poslovi u paketu ──────────────────────────────────────────────┐
│ 🔍 Pretraži poslove…                         Izabrano: 6        │
│                                                                 │
│ ▾ Knjiženje                              [Izaberi sve u grupi]  │
│   ☑ Knjiženje izvoda          Dnevno, radni dani                │
│   ☑ Knjiženje ulaznih faktura Mesečno, 10.                      │
│   ☐ Kalkulacije               Po potrebi                        │
│ ▾ PDV                                                           │
│   ☑ PDV prijava               Mesečno, 15.                      │
│ ▸ Plate (0 od 3)                                                │
└─────────────────────────────────────────────────────────────────┘
┌ Izabrano (redosled) ────────────┐
│ ⠿ Knjiženje izvoda           ✕ │
│ ⠿ PDV prijava                ✕ │
└─────────────────────────────────┘
```
- Levo: poslovi grupisani po kategoriji (boja i ikonica kategorije), grupe se skupljaju, pretraga filtrira u letu,
  "Izaberi sve u grupi", a uz svaki posao je opis rasporeda u `--kt-text-2`.
- Desno (na telefonu ispod): **izabrani poslovi**, sa prevlačenjem za redosled i ✕ za uklanjanje.
- Na telefonu je ovo jedna kolona, a izabrani poslovi su sklopiv blok "Izabrano: 6".
- Komponent se pravi jednom (`templates/components/_job_picker.html.twig` + `assets/js/job-picker.js`) i koristi se i u 3.3.

### 3.3 Klijent: tab "Ugovorene usluge"
Na kartici klijenta postoji tab **"Ugovorene usluge"** sa tri dela.

**a) Dodaj paket** (dugme otvara modal, na telefonu bottom sheet):
1. Izbor paketa (Tom Select). Klijent može imati više paketa.
2. Odmah se prikažu **svi poslovi iz paketa, svi štiklirani**, sa opisom rasporeda.
3. **Odštikliraš ono što ne treba** (npr. PDV prijava). Za svaki isključen posao opciono se upisuje razlog.
4. Dogovorena cena (predložena je cena paketa), napomena, datum početka.
5. Sačuvaj: kreira se ClientPackage, a za svaki posao iz paketa ClientJob (source = PACKAGE).
   Odštiklirani poslovi se čuvaju kao ClientJob sa `isExcluded = true`, da se zna da su svesno isključeni.

**b) Dodaj pojedinačan posao** (dugme otvara modal):
1. Izbor posla iz kataloga (komponent iz 3.2, ali sa izborom jednog ili više poslova).
2. Za svaki: **cena** (predložena je `defaultPrice`), **napomena**, opciono drugačiji raspored i drugačiji zaduženi.
3. Sačuvaj: ClientJob (source = INDIVIDUAL).

Primer: paket "Standard" bez PDV prijave, plus pojedinačno "Kalkulacije". To su ugovoreni poslovi tog klijenta.

**c) Pregled ugovorenih poslova:**

| Posao | Izvor | Raspored | Sledeći zadatak | Zadužen | Cena | |
|---|---|---|---|---|---|---|
| Knjiženje izvoda | Paket Standard | Dnevno, radni dani | 29. okt | Marko M. | u paketu | ⋯ |
| Kalkulacije | Pojedinačno | Po potrebi | — | Marko M. | 3.000 RSD / izvršenju | ⋯ |
| ~~PDV prijava~~ | Paket Standard | isključeno: "klijent nije u sistemu PDV-a" | | | | ⋯ |

- Grupisano po paketima, pa pojedinačni poslovi. Isključeni poslovi su prigušeni i precrtani, uz razlog, i mogu se ponovo uključiti.
- Meni "⋯": izmeni (cena, napomena, raspored, zaduženi), isključi ili uključi, završi (postavlja endDate), "Kreiraj zadatak sada".
- Dole **ukupna mesečna cena** (paketi + mesečni pojedinačni poslovi). **Cene vidi samo admin i superadmin** (pravilo iz UPDATE.md).
- Uklanjanje paketa postavlja endDate na ClientPackage i sve njegove ClientJob-ove, uz SweetAlert pitanje šta sa otvorenim zadacima.

---

## 4. Zadaci: automatski i ručni

### 4.1 Automatsko kreiranje
- Komanda `app:tasks:generate` (cron svakog dana u 06:00), za svaki aktivan, neisključen ClientJob čiji raspored **nije "Po potrebi"**:
  izračuna sledeći rok preko `ScheduleCalculator`-a, i ako je danas >= rok − leadDays i zadatak za taj period ne postoji, kreira ga.
- **Unique `(client_job_id, period_key)`** sprečava duplikate. Komanda je bezbedna za ponovno pokretanje.
- Zadatak dobija: naslov `{posao} — {kraći naziv klijenta} — {period}`, checklist kopiran iz posla, prioritet, režim završetka,
  procenu trajanja, i zaduženog: override sa ClientJob-a, inače **glavnog radnika klijenta**.
- Ako je glavni radnik tog dana **na odobrenom odsustvu** (sekcija 6), zadatak se dodeljuje **zameni** i označava kao "PREUZETO".
- Ako isti posao dolazi iz dva paketa istog klijenta, nastaje jedan zadatak po periodu (upozorenje u UI pri dodavanju paketa).
- Admin: dugme "Generiši sada" (SweetAlert sa brojem zadataka koji će nastati) i pregled "Šta će se kreirati u narednih 30 dana".

### 4.2 Ručno kreiranje
Ručno se kreiraju:
- zadaci iz ugovorenog posla sa rasporedom **"Po potrebi"** (npr. prijava radnika): korisnik bira posao, checklist se popuni iz posla,
- dodatni zadatak iz posla koji ima raspored (dugme "Kreiraj zadatak sada"), na primer kada klijent traži nešto van roka,
- **potpuno samostalni zadaci**, bez posla (i bez klijenta, kao interni zadatak).

### 4.3 Forma zadatka: klijent i zaduženi
**Ako se u kreiranje ulazi sa kartice klijenta** (`/clients/{id}/tasks/new`):
- Klijent je **zaključan**: prikazuje se kao kartica (kraći naziv, PIB), bez mogućnosti promene.
- Polje **"Posao"** nudi samo **ugovorene poslove tog klijenta** (opciono; bez izbora je samostalan zadatak).
- **Zaduženi** se biraju samo između ljudi **raspoređenih na tog klijenta**: glavni radnik, zamena i oni koji su postavljeni
  kao override zaduženi na nekom njegovom ClientJob-u. Pored imena stoji uloga ("glavni", "zamena").

**Ako se ulazi direktno iz zadataka** (`/tasks/new`):
- Prvo se bira **klijent** (Tom Select sa pretragom po kraćem nazivu, punom nazivu i PIB-u) ili opcija **"Interni zadatak (bez klijenta)"**.
- Dok klijent nije izabran, polja "Posao" i "Zaduženi" su zaključana, uz napomenu "Prvo izaberi klijenta".
- Po izboru klijenta, AJAX (`/ajax/clients/{id}/task-options`) puni **ugovorene poslove** i **zadužene raspoređene na tog klijenta**.
  Ako se klijent promeni, izbori se ponovo pune, a već izabrani zaduženi koji nisu na novom klijentu se uklanjaju (uz toast poruku).
- Za interni zadatak zaduženi se biraju po pravilima iz SPEC 4.4 (radnik sebi, šef svom timu, admin bilo kome).

**Uvek:**
- Pravila iz SPEC 4.4 važe i ovde: radnik vidi samo sebe kao zaduženog, a šef samo članove svog tima.
- Server **ponovo proverava** sve (Voter + provera da je zaduženi zaista raspoređen na klijenta), bez obzira na to šta je UI ponudio.
- Izbor posla popunjava checklist (može se menjati pre čuvanja, editor iz 2.4), prioritet i procenu trajanja.
- Rok se bira flatpickr-om, a neradni dani su u kalendaru prigušeni. Ako se izabere neradni dan, ispod polja se prikaže upozorenje (ne zabrana).

---

## 5. Neradni dani

Zamenjuje `Holiday` tabelu iz UPDATE.md. Admin+ upravlja neradnim danima na stranici **Podešavanja → Neradni dani**.

### 5.1 Vrste
| Vrsta | Primer | Kako se unosi |
|---|---|---|
| **Pravilo za dane u nedelji** | Svaka subota i nedelja je neradna | štikliranje dana u nedelji (pon–ned) |
| **Praznik, isti datum svake godine** | 1. i 2. januar, 15. i 16. februar, 1. i 2. maj, 11. novembar | dan i mesec, ponavlja se |
| **Praznik za određenu godinu** | Pravoslavni Uskrs i Veliki petak (svake godine drugi datum) | tačan datum ili opseg datuma |
| **Kolektivni odmor** | 24.–31. decembar 2026. | opseg datuma, naziv |

- Entiteti: `WeeklyNonWorkingRule` (dani u nedelji, jedno pravilo za celu firmu) i `NonWorkingDay`
  (type: HOLIDAY_RECURRING, HOLIDAY_ONCE, COLLECTIVE_LEAVE; name, dateFrom, dateTo, recurring, note).
- Dugme **"Učitaj praznike Srbije za godinu"** popuni zakonske praznike za izabranu godinu, uključujući datume Uskrsa
  (računa se pravoslavni Uskrs po julijanskom kalendaru), a admin ih pregleda pre čuvanja.
- Praznik koji pada u nedelju: po zakonu se ne radi narednog radnog dana. Ponuditi to kao automatski predlog pri učitavanju, a admin potvrđuje.
- Pregled: godišnji kalendar (12 malih meseci) sa obojenim neradnim danima, i lista ispod.

### 5.2 Servis `WorkingDayService`
Jedino mesto koje odgovara na pitanje "da li je ovo radni dan":
- `isWorkingDay(date)`, `nextWorkingDay(date)`, `previousWorkingDay(date)`, `countWorkingDays(from, to)`, `nonWorkingDaysBetween(from, to)`
- Rezultat se kešira po godini i keš se briše pri svakoj izmeni neradnih dana.
- Koriste ga: `ScheduleCalculator` (rokovi), odsustva (broj dana), flatpickr (prigušeni dani preko `/ajax/calendar/non-working?year=`), kalendar.

---

## 6. Odsustva

### 6.1 Vrste odsustva (CRUD za admina)
Entitet **`AbsenceType`**: name, color, icon, **requiresApproval** (bool), **countsAgainstLeave** (troši dane godišnjeg odmora),
**requiresDocument** (traži prilog), maxDaysPerYear (opciono), isActive.

Početne vrste:
| Vrsta | Odobrenje | Troši godišnji | Dokument |
|---|---|---|---|
| Godišnji odmor | da | da | ne |
| Slava | da | ne | ne |
| Bolovanje | ne (samo evidencija) | ne | da, doznaka (može naknadno) |
| Plaćeno odsustvo | da | ne | ne |
| Neplaćeno odsustvo | da | ne | ne |
| Ostalo | da | ne | ne |

### 6.2 Zahtev za odsustvo
- Entitet **`Absence`** (proširuje postojeći `UserAbsence`): user, type, dateFrom, dateTo, **halfDay** (opciono, za jedan dan),
  **workingDays** (izračunato preko WorkingDayService-a, bez vikenda, praznika i kolektivnog odmora), note, status,
  requestedBy, decidedBy, decidedAt, decisionNote, **approvalDeadline**, prilozi (sekcija 8).
- Statusi: `NA_ODOBRENJU` → `ODOBRENO` / `ODBIJENO` / **`AUTOMATSKI_ODOBRENO`**, plus `OTKAZANO` (otkazuje podnosilac dok je na odobrenju,
  ili admin u bilo kom trenutku).
- Ko podnosi: radnik za sebe. Šef i admin mogu upisati odsustvo bilo kome koga vide, i tada je odobreno odmah.
- **Ko odobrava:** šef tima podnosioca. Ako podnosilac jeste šef, nema tim ili mu je šef odsutan, odobrava admin.
- Pri podnošenju forma odmah pokazuje: broj radnih dana, preostale dane godišnjeg odmora posle ovog zahteva,
  i **ko je još iz tima odsutan u tom periodu** (upozorenje, ne zabrana).
- Preklapanje sa postojećim odsustvom istog korisnika nije dozvoljeno.
- Odobreno odsustvo pokreće pravila za zamenu iz SPEC sekcije 5 (backup preuzima klijente i zadatke).

### 6.3 Evidencija i pregled
- **`User.annualLeaveDays`** (broj dana godišnjeg odmora po godini) i **`LeaveBalance`** (user, year, entitled, carriedOver, used).
  `used` se računa iz odobrenih odsustava koja troše godišnji.
- Na profilu korisnika: kartica "Odsustva" (preostalo / iskorišćeno / preneto, lista zahteva po godini).
- Stranica **Odsustva** (šef za svoj tim, admin za sve):
  - tab **"Na odobrenju"** (brojač u meniju), sa dugmadima Odobri / Odbij (odbijanje traži razlog, SweetAlert),
  - tab **"Pregled"**: tabela radnik × dani u mesecu (obojena polja po vrsti odsustva, neradni dani prigušeni), filter po timu,
  - tab **"Evidencija"**: zbir po radniku i vrsti za izabranu godinu, sa izvozom Excel / PDF.
- Na telefonu: "Na odobrenju" je lista kartica sa prevlačenjem za Odobri / Odbij, a pregled je lista po danima.

---

## 7. Automatsko odobravanje posle roka

**Pravilo:** ako se nešto traži na odobrenje, a odobravalac ne odgovori do roka, **smatra se odobrenim**.

- Jedan zajednički mehanizam: interfejs `ApprovableInterface` (status, approvalDeadline, approve(), approveAutomatically()),
  koji implementiraju `Absence` i ostali entiteti kojima treba odobrenje.
- **Rok** se računa pri podnošenju: `min(vreme podnošenja + X radnih sati, početak odsustva − 1 dan)`.
  X se podešava u Podešavanjima po vrsti zahteva (podrazumevano **48 h**). Ako je odsustvo za sutra, rok je kraći, a odobravalac dobija hitnu notifikaciju.
- Komanda `app:approvals:auto-approve` ide na cron **na 15 minuta**: svaki zahtev `NA_ODOBRENJU` sa isteklim rokom prelazi u
  `AUTOMATSKI_ODOBRENO`, uz ActivityLog (user = sistem) i notifikacije podnosiocu i odobravaocu.
- **24 h pre isteka roka** odobravalac dobija podsetnik.
- U UI zahtev na odobrenju prikazuje odbrojavanje ("Automatski se odobrava za 5 h"). Automatski odobren zahtev ima poseban badge, da se razlikuje od ručno odobrenog.
- Admin može da poništi automatsko odobrenje (uz razlog i notifikaciju podnosiocu).

**Gde se primenjuje:** zahtevi za odsustvo (odmah). Da li se primenjuje i na potvrdu zadataka (NA_PROVERI) i na
predloge pojedinačnih poslova od radnika, vidi nedoumicu 13.1. Mehanizam napraviti tako da se uključuje po vrsti zahteva u Podešavanjima.

---

## 8. Dokumenti korisnika

- Uz svakog korisnika mogu se priložiti dokumenti: rešenje o godišnjem odmoru, ugovor, potvrda, izvod, doznaka za bolovanje i sl.
- Entitet **`UserDocument`**: user, **category** (`DocumentCategory`, CRUD za admina: Ugovor, Rešenje, Potvrda, Izvod, Doznaka, Ostalo),
  title, file (PDF, JPG, PNG, do 20 MB), originalName, mimeType, size, **validUntil** (opciono), note, uploadedBy, createdAt,
  opciono **absence** (veza na odsustvo, npr. doznaka).
- Upload preko **FilePond-a** (UPDATE-DIZAJN.md), pregled PDF-a preko **PDF.js** u modalu, slike u lightbox-u.
- **Fajlovi se čuvaju van `public/`** (`var/storage/user-documents/{userId}/`, nasumično ime fajla) i serviraju se kroz kontroler
  koji proverava Voter `USER_DOCUMENT_VIEW`. Nikad direktan link ka fajlu.
- **Ko vidi:** sam korisnik (svoje dokumente), admin i superadmin. Šef vidi samo dokumente vezane za odsustva svog tima (doznake).
  Upload: admin+ za bilo koga, korisnik za sebe (npr. doznaka).
- Na profilu korisnika: tab **"Dokumenti"**, sa listom po kategorijama (ikonica tipa, naziv, datum, veličina, ko je dodao) i filterom.
- Dokument sa `validUntil` (npr. ugovor na određeno): notifikacija adminu 30 i 7 dana pre isteka.
- Brisanje dokumenta je soft delete, uz SweetAlert potvrdu. Fajl se fizički briše tek kada ga admin trajno obriše iz korpe.

---

## 9. Kalendar

### 9.1 Biblioteka
**FullCalendar** (v6, standardni MIT paketi: `@fullcalendar/core`, `daygrid`, `timegrid`, `list`, `interaction`) preko importmap-a,
sa srpskim latiničnim locale-om, nedeljom od ponedeljka i stilom po DESIGN.md (tokeni, obe teme). Pre instalacije proveri trenutnu
stabilnu verziju i rad preko importmap-a. Premium (plaćeni) paketi FullCalendar-a se ne koriste.

### 9.2 Šta prikazuje
| Događaj | Izgled | Ko vidi |
|---|---|---|
| Zadaci (po roku) | boja statusa, ikonica prioriteta, kraći naziv klijenta | po Voterima (svoje / tim / sve) |
| Odsustva | boja vrste odsustva, avatar sa inicijalima | svoja; šef svog tima; admin sve |
| Neradni dani i kolektivni odmor | prigušena pozadina dana i naziv praznika | svi |

- Prikazi: **Mesec**, **Nedelja**, **Dan**, **Lista**. Na telefonu je podrazumevano **Lista** (agenda), uz prevlačenje levo/desno za sledeći ili prethodni period.
- **Filteri** (čipovi iznad kalendara): Moji / Moj tim / Svi (po pravima), vrsta događaja, klijent, radnik.
- Događaji se učitavaju AJAX-om za vidljivi opseg (`/ajax/calendar/events?start=&end=&filters=`), nikad sve odjednom.
- **Klik na prazan dan** otvara meni: "Novi zadatak za ovaj dan" ili "Zahtev za odsustvo od ovog dana".
- **Klik na događaj** otvara popover sa detaljima i linkom.
- **Prevlačenje zadatka** na drugi dan menja rok (samo ako `TASK_EDIT` to dozvoljava), uz SweetAlert potvrdu i upozorenje ako je dan neradni.
- Kalendar je u glavnom meniju, a mali pregled naredne nedelje je i na dashboardu.

### 9.3 Povezivanje sa Google Calendar-om
**Cilj:** kada se u aplikaciji doda ili promeni zadatak ili odsustvo, to se **istovremeno** pojavi i u Google kalendaru korisnika.

- **Svaki korisnik sam povezuje svoj Google nalog**: Profil → "Poveži Google kalendar" (OAuth 2.0). Odvezivanje je jednim klikom.
- Aplikacija u njegovom Google nalogu napravi **poseban kalendar "KontIT"**, i u njega upisuje samo svoje događaje.
  Lični kalendar korisnika ostaje netaknut, a "KontIT" kalendar može da isključi ili oboji kako hoće.
- Traži se najuži mogući pristup: scope `https://www.googleapis.com/auth/calendar.app.created`
  (aplikacija vidi i menja samo kalendare koje je sama napravila).
- **Smer sinhronizacije: aplikacija → Google** (aplikacija je izvor istine). Izmena u Google-u se ne vraća u aplikaciju (vidi nedoumicu 13.3).
- Šta se šalje: **zadaci u kojima je korisnik učesnik** (celodnevni događaj na dan roka, naslov, link ka zadatku u opisu) i
  **njegova odobrena odsustva**. Šef opciono (podešavanje) dobija i odsustva svog tima.
- Kreiranje, izmena roka, promena statusa (završen zadatak dobija ✓ u naslovu), otkazivanje i brisanje se šalju **asinhrono preko Messenger-a**,
  da akcija u aplikaciji ne čeka Google. Neuspeli pokušaji se ponavljaju (retry sa pauzom), a posle 5 neuspeha korisnik dobija notifikaciju.
- Entitet **`GoogleCalendarConnection`**: user, googleEmail, calendarId, accessToken, **refreshToken (šifrovano**, Symfony secrets ili libsodium),
  expiresAt, connectedAt, lastSyncAt, lastError.
- Entitet **`CalendarSyncLink`**: user, entityType, entityId, googleEventId, etag, syncedAt. Tako se zna koji događaj u Google-u odgovara kom zadatku.
- Komanda `app:calendar:resync {user}` ponovo šalje sve buduće događaje korisnika (posle povezivanja i za popravke).
- Paketi: `knpuniversity/oauth2-client-bundle` + `league/oauth2-google` za prijavu, `google/apiclient` za Calendar API
  (u composer.json ograničiti samo na Calendar servis, da se ne povuče ceo paket).

**Dodatno i jednostavno: ICS link.** Svaki korisnik ima i tajni link za pretplatu (`/calendar/ics/{token}.ics`), koji radi sa bilo kojim
kalendarom (Google, Outlook, iPhone), samo za čitanje, uz mogućnost da se link poništi i napravi novi. Služi kao rezerva ako Google povezivanje ne prođe.

**Šta Stefan treba da uradi u Google Cloud konzoli** (Claude Code napravi korak-po-korak uputstvo u `docs/google-calendar-setup.md`):
1. Napravi projekat i uključi Google Calendar API.
2. Podesi OAuth consent screen (naziv KontIT app, logo, domen, kontakt mail).
3. Napravi OAuth Client ID (Web application) sa redirect URI-jem `https://<domen>/connect/google/check`.
4. Upiše Client ID i Secret u `.env.local` (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`), nikad u git.
5. Proveri da li firma koristi Google Workspace: tada consent screen može biti "Internal" i nema Google verifikacije.
   Ako koriste obične Gmail naloge, aplikacija je "External", a dok je u statusu "Testing" radi samo za dodate test korisnike i
   povezivanje može da istekne posle nekoliko dana. Za stalni rad treba je objaviti (Publish), i to proveriti u trenutnoj Google dokumentaciji.

---

## 10. Podešavanja (admin) koja nastaju ovim update-om
- Neradni dani (sekcija 5)
- Vrste odsustva (6.1) i kategorije dokumenata (8)
- Rokovi za automatsko odobravanje po vrsti zahteva (7)
- Podrazumevan broj dana godišnjeg odmora (6.3)
- Google kalendar: da li je uključen za celu firmu i da li šefovi dobijaju odsustva tima (9.3)

---

## 11. Notifikacije koje se dodaju
ABSENCE_REQUESTED (odobravaocu), ABSENCE_APPROVED, ABSENCE_REJECTED, ABSENCE_AUTO_APPROVED (obojici), APPROVAL_DEADLINE_SOON (odobravaocu, 24 h pre),
DOCUMENT_EXPIRING (adminu), DOCUMENT_ADDED (korisniku, kad mu admin doda dokument), CALENDAR_SYNC_FAILED (korisniku).

---

## 12. Redosled rada

| # | Korak | Model |
|---|---|---|
| 1 | Sitne izmene: paginacija aktivnosti, `shortName`, uklanjanje pravne forme (sekcija 1) | Sonnet 5.5 |
| 2 | Neradni dani i `WorkingDayService` sa testovima (sekcija 5) | Opus 5.5 |
| 3 | Kategorije poslova (CRUD), checklist editor, raspored posla i `ScheduleCalculator` sa testovima (sekcija 2) | Opus 5.5 |
| 4 | Paketi sa job picker-om, tab "Ugovorene usluge" na klijentu (sekcija 3) | Opus 5.5 |
| 5 | Generisanje zadataka po novom modelu i forma zadatka sa klijentom i zaduženima (sekcija 4) | Opus 5.5 |
| 6 | Odsustva: vrste, zahtevi, odobravanje, evidencija i pregled (sekcija 6) | Sonnet 5.5 |
| 7 | Automatsko odobravanje posle roka (sekcija 7) | Opus 5.5 |
| 8 | Dokumenti korisnika (sekcija 8) | Sonnet 5.5 |
| 9 | Kalendar u aplikaciji (FullCalendar) i ICS link (9.1, 9.2) | Sonnet 5.5 |
| 10 | Google Calendar povezivanje i sinhronizacija (9.3) | Opus 5.5 |
| 11 | Pregled svih novih ekrana u obe teme, na 390 px i 1440 px | Sonnet 5.5 |

Posle svakog koraka: commit, kratak izveštaj šta je urađeno, spisak migracija i šta treba da proverim.

---

## 13. Nedoumice (prebaciti u SPEC.md)

1. **Automatsko odobravanje važi i za potvrdu zadataka** (NA_PROVERI)? Predlog: da, ali sa dužim rokom (npr. 3 radna dana) i
   isključivo uz podešavanje, jer potvrda zadatka proverava kvalitet rada, a ne samo dozvolu.
2. **Rok za automatsko odobravanje odsustva:** 48 h je predlog. Da li slava treba da ima kraći rok, jer se zna unapred?
3. **Google kalendar u oba smera?** Predlog: za sada samo aplikacija → Google. Dvosmerna sinhronizacija (pomeranje roka u Google-u menja
   zadatak) je mnogo složenija i otvara pitanje prava pristupa.
4. **Bolovanje:** da li šef treba da ga "vidi i potvrdi" ili je samo evidencija? Predlog: samo evidencija, a doznaka se prilaže u roku od 3 dana.
5. **Prenos neiskorišćenih dana godišnjeg odmora** u sledeću godinu: da li i do kog datuma (po zakonu se deo koristi do 30. juna)?
6. **Slava:** da li da se na profilu korisnika upiše slava (naziv i datum), pa da sistem sam predloži zahtev za odsustvo nekoliko nedelja ranije?
7. **Šef vidi dokumente svog tima?** Predlog: samo one vezane za odsustva (doznake), a ugovore i rešenja samo admin.
8. **Zadaci sa rasporedom "Dnevno"**: jedan zadatak po danu može da napravi mnogo zadataka. Da li je za knjiženje izvoda bolje
   "Nedeljno" sa checklistom po danima? Odluka po poslu, ali treba je imati na umu pri unosu kataloga.
