# UPDATE.md — Dopuna specifikacije

> **Uputstvo za Claude Code:**
> 1. Pročitaj ceo ovaj fajl, pa CLAUDE.md, SPEC.md i PROGRESS.md.
> 2. Ugradi sadržaj u **SPEC.md** kao nove sekcije (ili dopuni postojeće) i ažuriraj listu faza po sekciji 5 ovog fajla.
>    Pravilo iz sekcije 1.4 dodaj u **CLAUDE.md**.
> 3. Nedoumice iz sekcije 6 prebaci u sekciju "NEDOUMICE" u SPEC.md.
> 4. Pokaži mi kratak plan šta menjaš u postojećem kodu i **sačekaj potvrdu** pre implementacije.
> 5. Kad je sve preneto, ovaj fajl obriši ili premesti u `docs/archive/`.

---

## 1. Potvrda kritičnih radnji (SweetAlert2)

### 1.1 Biblioteka
- **SweetAlert2** preko AssetMapper importmap-a.
- Stilizovati da se uklopi u admin template: `buttonsStyling: false` i `customClass` sa klasama dugmadi
  iz template-a (`btn btn-danger`, `btn btn-secondary` i sl.). Boje i font kao u template-u.
- Isti alat koristiti i za **toast** poruke posle AJAX akcija (uspeh ili greška, gore desno, 3 s).

### 1.2 Jedan globalni mehanizam (bez ponavljanja koda)
U `assets/js/confirm.js` napraviti jedan jQuery handler koji radi preko data atributa:

```html
<button type="submit"
        data-confirm="Deaktivirati klijenta Firma X?"
        data-confirm-text="Klijent i njegovi paketi biće deaktivirani. Zadaci se više neće generisati."
        data-confirm-type="danger"            {# info | warning | danger #}
        data-confirm-button="Da, deaktiviraj"
        data-confirm-reason="required">       {# opciono: traži razlog (textarea) #}
```

- Radi za **forme** (submit) i za **AJAX linkove i dugmad**.
- `data-confirm-reason="required"` prikazuje textarea u alertu, a tekst se šalje kao polje `reason`.
- `data-confirm-type-name="Firma X"`: za najopasnije radnje korisnik mora da **ukuca naziv** da bi potvrdio.
- Ako JS ne radi, akcija i dalje ide preko običnog POST-a sa CSRF-om, jer je potvrda samo UI sloj.
  Server uvek proverava prava (Voter) i CSRF bez obzira na alert.
- Twig makro `{{ confirm_attrs('...', type='danger') }}` u `templates/components/`, da se atributi ne pišu ručno.

### 1.3 Radnje koje traže potvrdu

| Radnja | Tip | Dodatno |
|---|---|---|
| Deaktivacija korisnika | danger | ukucati ime i prezime |
| Promena role korisnika | warning | |
| Prebacivanje radnika u drugi tim | warning | prikazati broj klijenata i zadataka koji idu sa njim |
| Promena šefa tima | warning | |
| Deaktivacija klijenta | danger | ukucati naziv klijenta |
| Promena glavnog ili backup radnika na klijentu | warning | |
| Uklanjanje paketa sa klijenta | danger | razlog obavezan |
| Isključivanje posla iz paketa kod klijenta | warning | razlog obavezan |
| Izmena paketa koji koriste klijenti | warning | prikazati broj klijenata |
| Otkazivanje zadatka | danger | razlog obavezan |
| Potvrda zadatka (šef) | info | |
| Vraćanje zadatka na doradu | warning | komentar obavezan (ide u TaskComment tipa VRACANJE_NA_DORADU) |
| Odštikliravanje stavke na zadatku koji je NA_PROVERI ili ZAVRSENO | warning | upozorenje da se zadatak ponovo otvara |
| Brisanje komentara | danger | |
| Ručno pokretanje generisanja zadataka | warning | prikazati koliko će zadataka nastati |

### 1.4 Pravilo za CLAUDE.md
> Svaka nova radnja koja briše, deaktivira, otkazuje, menja prava, menja dodelu ili utiče na više zapisa
> **mora** imati SweetAlert potvrdu preko globalnog `data-confirm` mehanizma. Nikad `confirm()` iz browsera.

---

## 2. Katalog: poslovi i paketi

### 2.1 Pojmovi
- **Posao (JobTemplate)** je šablon nečega što se radi i od njega **nastaju zadaci**.
  Primeri: "Mesečna PDV prijava", "Obračun plata (PPP-PD)", "Knjiženje izvoda", "Završni račun", "Registracija firme".
- **Paket (ServicePackage)** je skup poslova sa jednom cenom.
  Primer: "Paket Preduzetnik Start" = knjiženje izvoda + PDV prijava + obračun plata do 3 radnika.
- **Klijent** može imati **više paketa**, plus **ad hoc poslove** van paketa, a iz paketa se može **isključiti** pojedinačan posao.

### 2.2 Entiteti

**JobTemplate** (posao)
- name, description, category (enum: KNJIZENJE, PDV, PLATE, PRIJAVE, ZAVRSNI_RACUN, SAVETOVANJE, ADMINISTRATIVNO, OSTALO)
- **recurrence** (enum: ONE_TIME, WEEKLY, MONTHLY, QUARTERLY, YEARLY)
- dueDay (dan u mesecu za MONTHLY/QUARTERLY, npr. 15), dueMonth (za YEARLY)
- generateDaysBefore (koliko dana pre roka se kreira zadatak, npr. 10)
- defaultPriority, completionMode (ALL/ANY)
- **estimatedMinutes** (procena trajanja, koristi se za analitiku opterećenja i isplativosti)
- **defaultPrice** (nullable; cena kada se posao naplaćuje zasebno, kao ad hoc)
- priceUnit (enum: PO_IZVRSENJU, MESECNO, GODISNJE)
- checklist šablon → `JobTemplateChecklistItem` (text, position) koji se kopira u svaki novi zadatak
- isActive

**ServicePackage** (paket)
- name, description, **monthlyPrice**, isActive
- items → `ServicePackageItem` (package, jobTemplate, position)

**ClientPackage** (paket vezan za klijenta)
- client, package
- **agreedPrice** (dogovorena cena, može se razlikovati od cene paketa)
- discountPercent (nullable), startDate, endDate (nullable), isActive, note
- istorija promena cene u `ClientPackagePriceLog` (stara cena, nova cena, od kada, ko je promenio)

**ClientJob** (konkretan posao koji se radi za klijenta; **iz ovoga nastaju zadaci**)
- client, jobTemplate
- **source** (enum: PACKAGE, AD_HOC)
- clientPackage (nullable, popunjeno ako je source = PACKAGE)
- **isExcluded** (bool; posao iz paketa koji je isključen za ovog klijenta) + excludedReason, excludedBy, excludedAt
- overrides (nullable, ako je prazno važi vrednost iz JobTemplate): recurrence, dueDay, estimatedMinutes, assignee
- **price** (za AD_HOC; za PACKAGE je null jer je u ceni paketa)
- startDate, endDate (nullable), isActive

### 2.3 Pravila
- **Povezivanje paketa sa klijentom** automatski kreira `ClientJob` za svaki posao iz paketa (source = PACKAGE).
- **Uklanjanje paketa** postavlja `endDate` na ClientPackage i na njegove ClientJob-ove. Ništa se ne briše,
  a već kreirani otvoreni zadaci ostaju (pitati u alertu da li da se otkažu).
- **Isključivanje posla iz paketa** postavlja `isExcluded = true` na tom ClientJob-u, i za njega se više ne generišu zadaci.
- **Ad hoc posao**: dodaje se direktno klijentu (source = AD_HOC), sa svojom cenom i ponavljanjem. Može biti i jednokratan.
- Ako se isti posao nađe u dva paketa istog klijenta, generiše se **jedan** zadatak po periodu, uz upozorenje u UI.
- **Ko je zadužen**: zadatak se dodeljuje **glavnom radniku klijenta**, osim ako ClientJob ima override assignee.
  Kad se klijentu promeni glavni radnik, budući zadaci idu novom, a otvoreni se nude za prebacivanje (alert).

### 2.4 Ko šta vidi
- Katalog poslova i paketa: kreira i menja **admin+**.
- **Cene vidi samo admin i superadmin.** Radnik i šef ne vide cene ni finansijsku analitiku (vidi nedoumicu 6.1).
- Šef vidi koje pakete i poslove imaju klijenti njegovog tima, ali bez cena.

---

## 3. Ponavljajući zadaci (radi se odmah, u okviru Faze 3)

### 3.1 Generisanje
- Komanda `app:tasks:generate` ide na cron **svakog dana u 06:00**.
- Za svaki aktivan, neisključen ClientJob izračuna sledeći period i rok. Ako je danas >= (rok − generateDaysBefore)
  i zadatak za taj period ne postoji, kreira ga.
- **Task dobija nova polja**: `clientJob` (nullable), `periodKey` (npr. `2026-11`, `2026-Q4`, `2026`, `2026-W45`),
  `estimatedMinutes`, `generatedAutomatically` (bool).
- **Unique ograničenje** `(client_job_id, period_key)` sprečava duplikate čak i ako se komanda pokrene dva puta.
- Checklist se kopira iz JobTemplateChecklistItem.
- Naslov zadatka: `{posao} — {klijent} — {period}`, npr. "PDV prijava — Firma X — oktobar 2026".
- **Neradni dani**: ako rok padne na vikend ili praznik, pomera se na **prvi sledeći radni dan**.
  Praznici su u tabeli `Holiday` (date, name), koju admin održava.
- **ONE_TIME** ClientJob generiše tačno jedan zadatak.
- Admin ima dugme "Generiši sada" (sa SweetAlert potvrdom i prikazom koliko će zadataka nastati) i pregled
  "Šta će se generisati u narednih 30 dana".
- Svako generisanje se loguje (ActivityLog, user = sistem).

### 3.2 Ručni zadaci
Postojeće kreiranje zadataka ostaje nepromenjeno. Ručno kreiran zadatak nema `clientJob` i ne ponavlja se.

---

## 4. Analitika (dodaje se u Fazu 7; vidi samo admin+)

Svi iznosi su u RSD, bez PDV-a. **Procenjeno vreme** = zbir `estimatedMinutes` poslova.
Kasnije se zamenjuje stvarnim vremenom iz agenta (Faza 8), pa analitiku treba pisati tako da izvor vremena može da se zameni.

### 4.1 Po klijentu (kartica klijenta, tab "Analitika")
- **Mesečni prihod**: zbir aktivnih paketa (agreedPrice) i mesečnih ad hoc poslova
- **Jednokratni prihod** u izabranom periodu (završeni ad hoc zadaci sa cenom)
- **Procenjeno mesečno vreme** (h)
- **Efektivna cena sata** = mesečni prihod / procenjeni sati
  - crveno ako je ispod praga iz podešavanja (npr. 1.500 RSD/h): **potcenjen klijent**
- broj zadataka mesečno, % na vreme
- **koliko puta je klijent kasnio sa dokumentacijom** (broj ulazaka u CEKA_KLIJENTA i prosečno trajanje)
- broj vraćanja na doradu
- istorija cene (grafikon promena agreedPrice)

### 4.2 Firma (admin dashboard, tab "Finansije")
- **MRR**: ukupan mesečni ponavljajući prihod, i trend po mesecima
- prihod po paketu i **popularnost paketa** (broj klijenata po paketu)
- **vrednost portfolija po radniku i po timu** (zbir prihoda klijenata koje vode)
- **opterećenje po radniku**: procenjeni sati mesečno naspram kapaciteta (podešavanje, npr. 160 h), pa iskorišćenost u %
  - prikaz: ko je preopterećen (>100%), a ko ima prostora za novog klijenta
- **lista potcenjenih klijenata**, sortirana po efektivnoj ceni sata
- **najčešće isključivani poslovi** iz paketa (signal da paket treba prepakovati)
- **kalendar opterećenja**: broj i procenjeni sati zadataka po nedeljama za naredna 2 meseca (heatmap), da se vide špicevi (npr. oko 15. u mesecu)
- **odliv**: paketi i klijenti koji su završeni u periodu, i izgubljeni MRR
- **ad hoc prihod** po mesecima (dodatna zarada van paketa)

### 4.3 Podešavanja (admin)
- prag minimalne cene sata (RSD/h)
- mesečni kapacitet radnika u satima (globalno + override po radniku)

---

## 5. Redosled ugradnje (izmena liste faza u SPEC.md)

Trenutno smo u **Fazi 2**. Novi redosled:

- **Odmah (pre nastavka Faze 2)**: SweetAlert2 i globalni `data-confirm` mehanizam, primenjen na postojeće
  kritične radnje iz Faze 1 (korisnici, timovi).
- **Faza 2**: Klijenti (kako je planirano)
- **Faza 2b (NOVO)**: Katalog poslova (JobTemplate i checklist šablon), paketi, povezivanje paketa sa klijentom,
  ClientJob (isključivanje, ad hoc), istorija cena. Prikaz na kartici klijenta: tab "Paketi i poslovi".
- **Faza 3**: Zadaci (kako je planirano) **+ ponavljajući zadaci** (sekcija 3), Holiday tabela, komanda za generisanje.
- **Faza 4–6**: bez promene
- **Faza 7**: Statistika **+ finansijska analitika** (sekcija 4)
- **Faza 8**: Praćenje vremena (agent), kada stvarno vreme zamenjuje procenjeno u analitici
- **Faza 9**: ostalo kasnije

Modeli: Faza 2b → **Opus 5.5** (relacije i pravila paketa), generisanje zadataka → **Opus 5.5**, SweetAlert → Sonnet 5.5.

---

## 6. Nedoumice (prebaciti u SPEC.md)

1. **Da li šef vidi cene i finansijsku analitiku svog tima?** Predlog: ne, samo admin+.
2. **Kada se menja sadržaj paketa** (doda se ili ukloni posao), da li se to primenjuje na sve klijente koji ga već imaju?
   Predlog: pri čuvanju se pita "Primeni na N klijenata?" (da / samo novi klijenti).
3. **Fakturisanje**: da li aplikacija treba da pravi mesečni obračun ili fakturu klijentu na osnovu paketa i ad hoc poslova?
   Predlog: za sada samo izveštaj "Za naplatu ovog meseca" po klijentu (izvoz u Excel), a fakture kasnije.
4. **Cene sa ili bez PDV-a?** Predlog: unos i prikaz bez PDV-a, sa jednim podešavanjem da li je firma u sistemu PDV-a.
5. **Ad hoc posao koji radnik sam doda** (npr. klijent tražio nešto dodatno): da li radnik može da ga doda, ili samo admin?
   Predlog: radnik može da ga **predloži**, a admin odobrava i upisuje cenu.
