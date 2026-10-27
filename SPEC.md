# SPEC.md — Specifikacija: Knjigovodstvo App

Interna aplikacija **jedne** knjigovodstvene agencije (nije SaaS) za upravljanje timovima, klijentima i zadacima.
Ne postoji `Company` entitet, jer je sve u jednoj firmi.

---

## 1. Role i prava

| Rola | Šta može |
|---|---|
| **Superadmin** (vlasnik) | Sve. Jedini koji kreira i menja admine i podešavanja sistema. Admin ne može da menja ni briše superadmina. |
| **Admin** | Sve osim upravljanja superadminom. Kreira korisnike, timove, klijente, premešta radnike između timova, menja šefa tima, dodeljuje klijente i kreira zadatke bilo kome. Može i sam biti radnik. |
| **Šef** | Vodi **samo svoj tim** i **ne vidi druge timove** (ni klijente ni zadatke). Kreira zadatke članovima tima, menja dodelu klijenata unutar tima i **potvrđuje završene zadatke**. Može i sam biti radnik. |
| **Radnik** | Vidi **samo svoje klijente**, a odvojeno i klijente gde je **backup**. Vidi svoje zadatke, kreira zadatke **samo sebi**, štiklira checklistu i komentariše. |

Pravilo: viša rola ima sve što ima niža (Symfony `role_hierarchy`).
Objektna prava rešavaju Voteri:
- `CLIENT_VIEW`: admin+, šef tima u kome je trenutno glavni ili backup radnik, glavni radnik, backup radnik
- `TASK_CREATE` / `TASK_VIEW` / `TASK_EDIT` / `TASK_COMPLETE` / `TASK_APPROVE` / `TASK_CANCEL` (zadatak se ne briše, nego otkazuje)
- `TEAM_MANAGE` (samo admin+), `TEAM_MEMBERS_VIEW` (šef, samo svoj tim), `USER_MANAGE`

---

## 2. Timovi

- Tim kreira, menja i briše **samo admin** (i superadmin).
- Svaki tim ima **tačno jednog šefa** u datom trenutku. Šef **može da se promeni**, a promena se beleži u `TeamLeaderLog`.
- Radnik je u **jednom timu u datom trenutku**, ali **može prelaziti iz tima u tim**. Prelazak radi admin.
  Istorija se čuva u `TeamMember` (joinedAt, leftAt), pa uvek znamo u kom timu je neko bio i kada.
- Pri prelasku: **klijenti i otvoreni zadaci idu sa radnikom**, jer su vezani za osobu, ne za tim.
  Novi šef ih odmah vidi, a stari šef prestaje da ih vidi.

---

## 3. Entiteti

### User
- firstName, lastName, email (login, unique), password
- phone, address, city, postalCode
- avatar = inicijali na gradijentu, bez slike (upload avatara je ukinut)
- themePreference (LIGHT, DARK, SYSTEM; podrazumevano SYSTEM)
- position (npr. "Knjigovođa", "Samostalni knjigovođa")
- employedAt (datum zaposlenja)
- roles (JSON, čuva se jedna najviša rola; hijerarhiju rešava `role_hierarchy`), isActive, lastLoginAt
- Admin može da menja samo radnike i šefove. Admine i superadmina menja i kreira samo superadmin. Svako sam menja svoj profil (ime, kontakt, lozinka, tema), a email, rolu i poziciju menja admin.
- internalNote (vidi samo admin+)
- emailNotifications (JSON: koje vrste notifikacija korisnik želi i na mail)
- createdAt, updatedAt

### Team
- name, description, color (za badge u UI)
- leader → User (trenutni šef, obavezan)
- members → `TeamMember` (user, team, joinedAt, leftAt; aktivan je onaj sa leftAt = null)
- deletedAt (soft delete; tim sa aktivnim članovima ne može da se obriše)
- U izboru šefa tima nude se **samo korisnici sa rolom šef** (trenutni šef ostaje u izboru). Rola se prvo dodeli u izmeni korisnika. Postavljanjem za šefa korisnik i dalje dobija `ROLE_SEF` (ako je bio radnik). Kad prestane da vodi ijedan tim, vraća se na radnika. Admin i superadmin zadržavaju svoju rolu. Šef ne mora biti član sopstvenog tima, a superadmin sme da bude šef.
- Korisnik koji vodi tim ne može da se deaktivira niti da mu se rola spusti na radnika dok se ne promeni šef.

### TeamLeaderLog
- team, user (šef), startedAt, endedAt (null = trenutni šef), changedBy
  (kolone se zovu startedAt/endedAt, jer su `from`/`to` rezervisane reči u SQL-u)

### Client (pravno lice ili preduzetnik)
- name, legalForm (enum: DOO, AD, PREDUZETNIK, UDRUZENJE, OSTALO)
- pib, maticniBroj, pdvObveznik (bool)
- address, city, contactPerson, phone, email
- startDate (od kada ga vodimo), isActive, note
- **primaryAccountant** → User (glavni zadužen)
- **backupAccountant** → User (zamena), uvek različit od glavnog
- istorija dodela u `ClientAssignmentLog` (ko je bio zadužen, kao glavni ili backup, od kada do kada, ko je promenio)
- Glavni je obavezan, backup je opcion (klijenti bez backup-a se prikazuju kao rizik u Fazi 7).
- Klijent je vezan za **osobu**, ne za tim: pri prelasku radnika u drugi tim klijenti idu sa njim.
- Radnik se **ne može deaktivirati** dok je glavni ili backup na aktivnom klijentu (prvo se klijenti prebace). Deaktiviran radnik nema pristup, ne može da dobije klijente ni zadatke, a cela istorija (dodele, članstvo, zadaci) ostaje vidljiva. Njegovo članstvo u timu se ne zatvara.
- Podatke klijenta (kreiranje, izmena, aktivacija) menja samo admin+. **Dodelu** glavnog i backup radnika menja admin+ i šef, a šef samo na klijentima svog tima i samo na članove svog tima (ili sebe).
- Klijent se ne briše, nego se deaktivira (`isActive = false`).


**Učitavanje iz NBS-a i provera brojeva**
- `NbsCompanyLookupService` sa zamenljivim izvorom: `NbsWebServiceProvider` (zvanični servis, kredencijali `NBS_WS_USERNAME`, `NBS_WS_PASSWORD`, `NBS_WS_LICENCE` u `.env.local`) i `NbsPublicPageProvider` (javna stranica, rezerva). Izbor u `.env`; ako zvanični nije podešen, koristi se rezervni.
- AJAX `/ajax/client/lookup?pib=...` vraća naziv, adresu, mesto, PIB, matični broj i tekuće račune. Poziv uvek ide preko našeg servera.
- Pre poziva se proverava kontrolna cifra: PIB (9 cifara, ISO 7064 MOD 11,10), matični broj (8 cifara, MOD 11). Pogrešan broj se odbija bez poziva NBS-u. Ista provera je i Symfony validator na entitetu.
- Keš 24h po PIB-u, timeout 8 s, rate limit po korisniku. Odgovor na ćirilici se preslovljava u latinicu pre upisa.
- UI: dugme "Učitaj iz NBS" pored PIB-a, loader na dugmetu, popunjena polja kratko zasijaju; ako polja već imaju vrednosti, SweetAlert pita da li da se prepišu. Kad NBS ne odgovori: jasna poruka, a forma se uvek može sačuvati ručno.
- Parser ima test sa sačuvanim primerom odgovora (`tests/Fixtures/nbs/`).

### Task (zadatak)
- title, description
- client → Client (nullable, jer zadatak može biti i interni)
- createdBy → User
- dueDate, priority (enum: NIZAK, NORMALAN, VISOK, HITAN)
- status (enum, vidi 4.3)
- **completionMode** (enum: `ALL` = svi moraju, **podrazumevano**; `ANY` = dovoljan je jedan)
- submittedAt (kada je poslat na proveru)
- approvedBy → User, approvedAt
- assignees → `TaskAssignee`
- checklistItems → `ChecklistItem`
- comments → `TaskComment`

### TaskAssignee
- task, user
- status (enum: NOVO, U_RADU, ZAVRSIO)
- completedAt, note (opcioni komentar pri završetku)

### ChecklistItem
- task, text, position (redosled, drag & drop)
- **assignedTo** → User (nullable; ako je prazno, stavka je zajednička)
- isDone, doneBy → User, doneAt

### TaskComment
- task, author, body, createdAt, editedAt (izmena dozvoljena 15 min, posle se samo prikazuje "izmenjeno")
- type (enum: KOMENTAR, VRACANJE_NA_DORADU), da se razlozi vraćanja jasno vide

### UserAbsence (odsustvo)
- user, startDate, endDate (kolone se ne zovu `from`/`to` jer su rezervisane u SQL-u), reason (enum: GODISNJI, BOLOVANJE, OSTALO), note, createdBy
- status (enum: NA_CEKANJU, ODOBRENO, ODBIJENO, OTKAZANO), decidedBy, decidedAt

### Notification
- user (primalac), type (enum), title, message, link (URL ka zadatku ili klijentu)
- isRead, readAt, createdAt
- emailSent (bool)

### ActivityLog (istorija izmena, generička)
- entityType, entityId, user, action (CREATED, UPDATED, STATUS_CHANGED, ASSIGNED, CHECKED, UNCHECKED, COMMENTED, SUBMITTED, APPROVED, RETURNED, TEAM_CHANGED, DELETED)
- changes (JSON: `{ "polje": ["staro", "novo"] }`), createdAt
- prikazuje se kao timeline na zadatku, klijentu i profilu korisnika
- **Implementacija (Faza 4)**: upisuje je isključivo `ActivityLogListener` (Doctrine `onFlush` prepoznaje promenu, `postFlush` upisuje u istoj transakciji, jer novi entitet tek tada ima ID). `entityType` je `Task`, `Client` ili `User`. Izmene stavki checkliste, učesnika i komentari zadatka se beleže pod `Task`, a promene tima pod `User`. Dodato polje `entityLabel` (naziv u trenutku izmene) za timeline korisnika.
- Beleži se: zadatak (kreiranje, polja, status, učesnici, stavke, komentari, slanje na proveru, potvrda, vraćanje na doradu, ponovno otvaranje), klijent (kreiranje, polja, promena glavnog i backup radnika, aktivacija), članstvo u timu, kreiranje korisnika, aktivacija i rola. Lozinka, lični podaci i poslednja prijava se ne beleže, kao ni promena redosleda stavki i izmena komentara.
- `changes` čuva prikazni tekst (staro i novo), pa istorija ostaje čitljiva i kad se naziv ili enum kasnije promene. Zapis bez prijavljenog korisnika (komandna linija) prikazuje se kao "Sistem".
- Timeline vide: zadatak (`TASK_VIEW`), klijent (`CLIENT_VIEW`), korisnik (admin na stranici korisnika i svako na svom profilu: ono što je radio i promene tima)

---

## 4. Zadaci

### 4.1 Svaki učesnik ima **svoj status**
Svako ko je dodeljen ima svoj red u `TaskAssignee`:

```
Zadatak: "Mesečni PDV — Firma X"     (režim: SVI MORAJU)
  👤 Marko   ✅ Završio (12.11. 14:20)
  👤 Jelena  🔄 U radu
  Napredak: 1/2 učesnika · checklist 5/8
```

- Svaki učesnik klikne **"Moj deo je gotov"** i to menja samo njegov `TaskAssignee.status`.
- **Režim ALL** (podrazumevano): zadatak ide na proveru kad svi završe.
- **Režim ANY**: zadatak ide na proveru čim jedan završi.
- Logiku drži `TaskCompletionService`.

### 4.2 Checklist stavke mogu biti dodeljene osobi
- Stavka sa `assignedTo` prikazuje avatar te osobe, pa se jasno vidi ko kasni.
- Stavka bez `assignedTo` je zajednička i bilo ko je štiklira, a beleži se ko je to uradio (`doneBy`).
- Učesnik ne može da označi "moj deo je gotov" dok njegove dodeljene stavke nisu štiklirane.

### 4.3 Status zadatka i potvrda šefa
```
NOVO → U_RADU → (CEKA_KLIJENTA) → NA_PROVERI → ZAVRSENO
                                        ↓
                                 vraćen na doradu → U_RADU
OTKAZANO (iz bilo kog statusa, radi kreator / šef / admin)
```
- `U_RADU` se postavlja automatski pri prvom štikliranju ili promeni statusa učesnika.
- `NA_PROVERI` se postavlja automatski po pravilu iz 4.1.
- **Završen zadatak mora da potvrdi šef** tima u kome je radnik:
  - **Potvrdi**: zadatak prelazi u `ZAVRSENO`, uz approvedBy i approvedAt.
  - **Vrati na doradu**: obavezan komentar, zadatak se vraća u `U_RADU`, a statusi učesnika se vraćaju u `U_RADU`.
- Ako je **šef sam učesnik** zadatka (ili je radnik bez tima), potvrđuje **admin**. Admin i superadmin mogu da potvrde bilo koji zadatak.
- Ako se posle potvrde nešto promeni (odštiklira se stavka), zadatak se ponovo otvara i to se loguje.

### 4.4 Ko kome kreira zadatke
- Radnik: samo sebi
- Šef: sebi i članovima svog tima
- Admin / Superadmin: bilo kome
- Deaktiviran radnik ne može da dobije zadatak, a ne može se ni deaktivirati dok ima zadatak u radu (NOVO, U_RADU, CEKA_KLIJENTA) na kome nije završio svoj deo. Prvo se zadatak prebaci ili otkaže.

### 4.5 Pravila implementirana u Fazi 3
- **Vidljivost** (`TASK_VIEW`): admin+, kreator, učesnik i šef tima čiji je član učesnik. Šef ne vidi zadatke drugih timova.
- **Izmena** (`TASK_EDIT`: podaci, učesnici, struktura checkliste) i **otkazivanje** (`TASK_CANCEL`): admin+, kreator i šef tima, dok zadatak nije završen ni otkazan.
- **Rad na zadatku** (`TASK_COMPLETE`: štikliranje, "moj deo", čeka klijenta): admin+, učesnik i šef tima, dok zadatak nije otkazan.
- **Potvrda** (`TASK_APPROVE`): zadatak mora biti NA_PROVERI. Potvrđuje admin+ ili šef tima koji nije i sam učesnik.
- **Checklist stavka**: zajedničku štiklira svako ko radi na zadatku, a dodeljenu samo ta osoba, kreator, šef tima ili admin. Dodavanje, brisanje, dodela i redosled su pravo izmene.
- Štikliranje prebacuje zadatak i učesnika u U_RADU. **Odštiklirana stavka ili nova stavka** na zadatku koji je NA_PROVERI ili ZAVRSENO ponovo otvara zadatak (U_RADU, svi učesnici U_RADU, poništena potvrda).
- **Vraćanje na doradu**: obavezan razlog, čuva se kao komentar tipa VRACANJE_NA_DORADU, svi učesnici se vraćaju u U_RADU.
- Učesnik može da povuče "moj deo je gotov" dok zadatak nije potvrđen. Izmena učesnika ponovo računa da li je zadatak kompletan.
- Klijent na zadatku mora biti aktivan i vidljiv onom ko kreira (isti opseg kao `CLIENT_VIEW`).
- Komentar može da izmeni autor 15 minuta. Komentare vraćanja na doradu nije moguće menjati.
- Beleženje u `ActivityLog` je urađeno u Fazi 4 (vidi sekciju 3, ActivityLog), a notifikacije dolaze u Fazi 5. Prikaz tuđih zadataka kao "PREUZETO" za backup dolazi u Fazi 6.

---

## 5. Backup radnik i odsustva
- Backup **uvek vidi** klijenta, u odvojenom bloku "Zamena".
- Kada se glavnom radniku upiše **odsustvo** (`UserAbsence`), backup za taj period:
  - dobija notifikaciju "Preuzimaš klijente za [ime] od [datum] do [datum]"
  - vidi otvorene zadatke glavnog radnika za te klijente, označene kao **"PREUZETO"**
  - može da ih štiklira i završava (ActivityLog beleži da je to uradio kao zamena)
- Odsustvo upisuje admin (za bilo koga), šef (za sebe i svoj tim) ili radnik sam sebi.
- **Odobravanje (odluka)**: "šef odobrava tuđe, svoje ne". Odsustvo koje radnik sam sebi upiše je `NA_CEKANJU` dok ga ne odobri šef njegovog tima (ili admin; ako radnik nema šefa, obaveštavaju se admini). Odsustvo koje upiše šef ili admin (i šef sam sebi) je odmah `ODOBRENO`. Šef ne odobrava sopstveno odsustvo, ali ga ne mora ni da odobrava jer je odmah na snazi.
- Backup preuzima klijente **samo dok je odsustvo ODOBRENO i danas traje** (od početka do kraja, uključujući oba dana). Čekanje, odbijeno i otkazano ne aktiviraju zamenu. Otkazati može radnik, onaj ko je odsustvo upisao, šef tima i admin, dok period nije prošao.
- Preklapanje sa drugim odsustvom koje čeka ili je odobreno (istog radnika) se odbija.
- **Implementacija (Faza 6)**: `AbsenceService` (upis, odobravanje, odbijanje, otkazivanje, obaveštenja), `ReplacementResolver` ("koga ja danas zamenjujem na ovom zadatku"), `AbsenceVoter` (ABSENCE_CREATE/DECIDE/CANCEL). Zamena važi za zadatke vezane za klijenta kome je korisnik backup, kad je glavni zaduženi učesnik zadatka i odsutan. Backup tada vidi zadatak (TASK_VIEW), štiklira stavke, komentariše i može da "Završi deo kolege (zamena)", ali ne menja zadatak i ne postaje učesnik. Obaveštenje "Preuzimate klijente..." ide odmah pri odobrenju (ne na dan početka); "Zamena je otkazana" pri otkazivanju. Dva nova tipa obaveštenja: `ABSENCE_REQUESTED` (šefu/adminu) i `ABSENCE_DECIDED` (radniku).

---

## 6. Prikaz za radnika

**Moji klijenti** u dva jasno odvojena bloka:
1. **Glavni zadužen** (puna kartica)
2. **Backup / zamena** (siva ili isprekidana ivica, badge "ZAMENA", ime glavnog zaduženog; aktivna zamena je istaknuta)

**Moji zadaci**: tabovi Danas / Ova nedelja / Kasni / Na proveri / Svi, uz filter po klijentu i prioritetu.

**Šef** ima dodatni tab **"Čeka moju potvrdu"** sa brojačem u navigaciji.

---

## 7. Notifikacije
- Prikazuju se **u aplikaciji**: zvonce u navigaciji sa brojem nepročitanih, dropdown sa poslednjih 10 i stranica sa svim.
  Osvežavanje preko AJAX poll-a na 60 s.
- **Mail je opcion**:
  - na mestima gde ima smisla (kreiranje zadatka, vraćanje na doradu, hitni zadaci) postoji checkbox **"Pošalji i na mail"**
  - korisnik u profilu bira koje vrste notifikacija uvek želi i na mail (`emailNotifications`)
- Mail se šalje asinhrono preko Symfony Messenger-a, da ne usporava akciju.
- Vrste: TASK_ASSIGNED, TASK_SUBMITTED (šefu), TASK_APPROVED, TASK_RETURNED, TASK_COMMENTED, TASK_DUE_SOON, TASK_OVERDUE, CLIENT_ASSIGNED, BACKUP_ACTIVATED, TEAM_CHANGED.
- **Implementacija (Faza 5)**: `NotificationService::notify()` je jedino mesto koje pravi notifikacije (servisi ga pozivaju posle uspešne izmene). Ne obaveštava aktera o sopstvenoj akciji ni neaktivne korisnike. Mail se šalje ako je pošiljalac štiklirao "Pošalji i na mail" ili ako je primalac tu vrstu izabrao u profilu; ide kroz `MailerInterface`, a `SendEmailMessage` je rutiran na `async` transport, pa ga šalje `messenger:consume async`.
- Primaoci: TASK_ASSIGNED (novi učesnici), TASK_SUBMITTED (šefovi timova učesnika koji sami nisu učesnici, a ako takvih nema, admini), TASK_APPROVED i TASK_RETURNED (učesnici; razlog vraćanja je u poruci), TASK_COMMENTED (učesnici i kreator, bez autora), CLIENT_ASSIGNED (novi glavni ili backup), TEAM_CHANGED (radnik koji je dodat ili prebačen, novi i bivši šef). BACKUP_ACTIVATED se uvodi u Fazi 6.
- TASK_DUE_SOON (rok u narednih 3 dana) i TASK_OVERDUE šalje komanda `app:notifications:due`, jednom po zadatku i korisniku (preskače učesnike koji su završili svoj deo). Pokreće se dnevno preko crona.
- Hitan zadatak (prioritet HITAN) ima naslov notifikacije "Novi HITAN zadatak", a checkbox za mail se u formi sam štiklira.

---

## 8. Statistika (dashboard)

### Radnik (svoj dashboard)
- otvoreni zadaci, zadaci koji kasne, zadaci koji ističu u naredna 3 dana
- završeno ovog meseca naspram prošlog meseca
- % završenih na vreme
- broj vraćanja na doradu (poslednjih 30 dana)
- broj klijenata (glavni / backup)

### Šef (samo za svoj tim)
- **opterećenje po radniku**: broj klijenata i broj otvorenih zadataka (bar chart)
- radnici sa najviše zadataka koji kasne
- prosečno vreme od kreiranja do potvrde zadatka
- % na vreme i % vraćenih na doradu po radniku (poslednjih 30 / 90 dana)
- koliko zadataka čeka njegovu potvrdu i koliko dugo
- zadaci u statusu "Čeka klijenta" duže od X dana

### Admin / Superadmin (cela firma)
- sve gore navedeno, po timovima
- **klijenti bez backup radnika** (rizik)
- **klijenti bez ijednog zadatka u poslednjih 30 dana**
- raspodela klijenata po timu i po radniku
- trend završenih zadataka po mesecima (line chart)
- najaktivniji i najmanje aktivni korisnici (po ActivityLog-u)

Statistika po timu se računa po **istoriji članstva** (`TeamMember`), da prelazak radnika ne pokvari stare brojke.
Grafikoni: Chart.js. Računa `StatisticsService` preko agregatnih upita.

**Implementacija (Faza 7)**: jedna stranica `/` (dashboard) sa blokovima po roli: "Moj pregled" svi, "Moj tim" šef (timovi koje vodi), "Cela firma" i "Svi timovi" admin+. Odluke:
- "X dana" za zadatke u statusu "Čeka klijenta" je **7** (`StatisticsService::WAITING_DAYS`). Računa se od poslednje izmene zadatka (`updated_at`), jer se vreme ulaska u taj status ne čuva.
- **Na vreme** = zadatak sa rokom koji je potvrđen (ZAVRSENO), a predat (`submittedAt`) je do roka; računa se na zadacima sa rokom. Za radnika je period 90 dana. **Vraćeno %** = vraćanja na doradu / završeni zadaci u periodu.
- Prosek "od kreiranja do potvrde" je za zadatke potvrđene u poslednjih 90 dana.
- Završen zadatak i vraćanje pripadaju timu u kome je radnik bio član u trenutku potvrde/vraćanja (istorija `TeamMember`); otvoreni zadaci i klijenti trenutnom članstvu.
- Aktivnost korisnika = broj zapisa u ActivityLog-u u poslednjih 30 dana.

---

## 8a-8d. DOPUNA (iz UPDATE.md)

> Sekcije 8a do 8d su dopuna specifikacije. **Još nisu implementirane.** Redosled ugradnje je u sekciji 10.

## 8a. Potvrda kritičnih radnji (SweetAlert2)

### 8a.1 Biblioteka
- **SweetAlert2** preko AssetMapper importmap-a.
- Stilizovati da se uklopi u admin template: `buttonsStyling: false` i `customClass` sa klasama dugmadi
  iz template-a (`btn btn-danger`, `btn btn-secondary` i sl.). Boje i font kao u template-u.
- Isti alat koristiti i za **toast** poruke posle AJAX akcija (uspeh ili greška, gore desno, 3 s).

### 8a.2 Jedan globalni mehanizam (bez ponavljanja koda)
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

### 8a.3 Radnje koje traže potvrdu

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

### 8a.4 Pravilo
Upisano i u CLAUDE.md: svaka nova radnja koja briše, deaktivira, otkazuje, menja prava, menja dodelu ili utiče na više zapisa mora imati SweetAlert potvrdu preko globalnog `data-confirm` mehanizma. Nikad `confirm()` iz browsera.

## 8b. Katalog: poslovi i paketi

### 8b.1 Pojmovi
- **Posao (JobTemplate)** je šablon nečega što se radi i od njega **nastaju zadaci**.
  Primeri: "Mesečna PDV prijava", "Obračun plata (PPP-PD)", "Knjiženje izvoda", "Završni račun", "Registracija firme".
- **Paket (ServicePackage)** je skup poslova sa jednom cenom.
  Primer: "Paket Preduzetnik Start" = knjiženje izvoda + PDV prijava + obračun plata do 3 radnika.
- **Klijent** može imati **više paketa**, plus **ad hoc poslove** van paketa, a iz paketa se može **isključiti** pojedinačan posao.

### 8b.2 Entiteti

**JobTemplate** (posao)
- name, description, category (enum: KNJIZENJE, PDV, PLATE, PRIJAVE, ZAVRSNI_RACUN, SAVETOVANJE, ADMINISTRATIVNO, OSTALO)
- **recurrence** (enum: ONE_TIME, WEEKLY, MONTHLY, QUARTERLY, YEARLY)
- dueDay (dan u mesecu za MONTHLY/QUARTERLY, npr. 15), dueMonth (za YEARLY)
- generateDaysBefore (koliko dana pre roka se kreira zadatak, npr. 10)
- defaultPriority, completionMode (ALL/ANY)
- **estimatedMinutes** (procena trajanja, koristi se za analitiku opterećenja i isplativosti)
- ~~defaultPrice~~ (ukinuto): katalog nema cene, cena se upisuje tek na klijentu
- priceUnit (enum: PO_IZVRSENJU, MESECNO, GODISNJE)
- checklist šablon → `JobTemplateChecklistItem` (text, position) koji se kopira u svaki novi zadatak
- isActive

**ServicePackage** (paket)
- name, description, isActive
- items → `ServicePackageItem` (package, jobTemplate, position)

**ClientPackage** (paket vezan za klijenta)
- client, package
- **agreedPrice** (obavezna mesečna cena paketa za tog klijenta; paket u katalogu nema cenu, jer se cene razlikuju po klijentu)
- discountPercent (nullable), startDate, endDate (nullable), isActive, note
- istorija promena cene u `ClientPackagePriceLog` (stara cena, nova cena, od kada, ko je promenio)

**ClientJob** (konkretan posao koji se radi za klijenta; **iz ovoga nastaju zadaci**)
- client, jobTemplate
- **source** (enum: PACKAGE, AD_HOC)
- clientPackage (nullable, popunjeno ako je source = PACKAGE)
- **isExcluded** (bool; posao iz paketa koji je isključen za ovog klijenta) + excludedReason, excludedBy, excludedAt
- overrides (nullable, ako je prazno važi vrednost iz JobTemplate): recurrence, dueDay, estimatedMinutes, assignee
- **price** (cena pojedinačnog posla za tog klijenta, upisuje se pri dodavanju; za PACKAGE je null jer je u ceni paketa)
- startDate, endDate (nullable), isActive

### 8b.3 Pravila
- **Povezivanje paketa sa klijentom** automatski kreira `ClientJob` za svaki posao iz paketa (source = PACKAGE).
- **Uklanjanje paketa** postavlja `endDate` na ClientPackage i na njegove ClientJob-ove. Ništa se ne briše,
  a već kreirani otvoreni zadaci ostaju (pitati u alertu da li da se otkažu).
- **Isključivanje posla iz paketa** postavlja `isExcluded = true` na tom ClientJob-u, i za njega se više ne generišu zadaci.
- **Ad hoc posao**: dodaje se direktno klijentu (source = AD_HOC), sa svojom cenom i ponavljanjem. Može biti i jednokratan.
- Ako se isti posao nađe u dva paketa istog klijenta, generiše se **jedan** zadatak po periodu, uz upozorenje u UI.
- **Ko je zadužen**: zadatak se dodeljuje **glavnom radniku klijenta**, osim ako ClientJob ima override assignee.
  Kad se klijentu promeni glavni radnik, budući zadaci idu novom, a otvoreni se nude za prebacivanje (alert).

### 8b.4 Ko šta vidi
- Katalog poslova i paketa: kreira i menja **admin+**.
- **Cene vidi samo admin i superadmin.** Radnik i šef ne vide cene ni finansijsku analitiku (vidi Nedoumice, tačka 2).
- Šef vidi koje pakete i poslove imaju klijenti njegovog tima, ali bez cena.

---

## 8c. Ponavljajući zadaci (Faza 3b)

### 8c.1 Generisanje
- Komanda `app:tasks:generate` ide na cron **svakog dana u 06:00**.
- Za svaki aktivan, neisključen ClientJob izračuna sledeći period i rok. Ako je danas >= (rok − generateDaysBefore)
  i zadatak za taj period ne postoji, kreira ga.
- **Task dobija nova polja**: `clientJob` (nullable), `periodKey` (npr. `2026-11`, `2026-Q4`, `2026`, `2026-W45`),
  `estimatedMinutes`, `generatedAutomatically` (bool).
- **Unique ograničenje** na koloni `generation_key` (`idPosla:period`) sprečava duplikate čak i ako se komanda pokrene dva puta. (Doctrine ne dozvoljava UniqueConstraint nad asocijacijom + kolonom na ovaj način, pa je ključ posebna kolona.)
- Checklist se kopira iz JobTemplateChecklistItem.
- Naslov zadatka: `{posao} — {klijent} — {period}`, npr. "PDV prijava — Firma X — oktobar 2026".
- **Neradni dani**: ako rok padne na vikend ili praznik, pomera se na **prvi sledeći radni dan**.
  Praznici su u tabeli `Holiday` (date, name), koju admin održava.
- **ONE_TIME** ClientJob generiše tačno jedan zadatak.
- Admin ima dugme "Generiši sada" (sa SweetAlert potvrdom i prikazom koliko će zadataka nastati) i pregled
  "Šta će se generisati u narednih 30 dana".
- Svako generisanje se loguje (ActivityLog, user = sistem).

### 8c.1a Period i rok (implementirano u `JobSchedule`)
- MONTHLY: ključ `2026-11`, rok = `dueDay` tog meseca (dan veći od dužine meseca se skraćuje na poslednji dan).
- QUARTERLY: ključ `2026-Q4`, rok = `dueDay` meseca **posle** kraja kvartala (PDV za Q4 stiže 15. januara).
- YEARLY: ključ `2026`, rok = `dueDay`.`dueMonth` te godine.
- WEEKLY: ključ `2026-W45`, rok = dan u nedelji `dueDay` (1 = ponedeljak, podrazumevano 5 = petak).
- ONE_TIME: ključ `ONCE`, rok = datum početka posla.
- Zadatak nastaje kad je `danas >= rok - generateDaysBefore`; rok koji je odavno prošao se ne generiše retroaktivno.
- Izvršilac: `assigneeOverride` posla, inače glavni radnik klijenta, inače backup. Bez aktivnog izvršioca zadatak se ne pravi, a admin vidi upozorenje.
- Komanda prima `--date=YYYY-MM-DD` (generiši kao da je taj dan, za proveru). Zadatke pravi u ime prvog aktivnog admina.

### 8c.2 Ručni zadaci
Postojeće kreiranje zadataka ostaje nepromenjeno. Ručno kreiran zadatak nema `clientJob` i ne ponavlja se.

---

## 8d. Analitika (Faza 7b; vidi samo admin+)

Svi iznosi su u RSD, bez PDV-a. **Procenjeno vreme** = zbir `estimatedMinutes` poslova.
Kasnije se zamenjuje stvarnim vremenom iz agenta (Faza 8), pa analitiku treba pisati tako da izvor vremena može da se zameni.

### 8d.1 Po klijentu (kartica klijenta, tab "Analitika")
- **Mesečni prihod**: zbir aktivnih paketa (agreedPrice) i mesečnih ad hoc poslova
- **Jednokratni prihod** u izabranom periodu (završeni ad hoc zadaci sa cenom)
- **Procenjeno mesečno vreme** (h)
- **Efektivna cena sata** = mesečni prihod / procenjeni sati
  - crveno ako je ispod praga iz podešavanja (npr. 1.500 RSD/h): **potcenjen klijent**
- broj zadataka mesečno, % na vreme
- **koliko puta je klijent kasnio sa dokumentacijom** (broj ulazaka u CEKA_KLIJENTA i prosečno trajanje)
- broj vraćanja na doradu
- istorija cene (grafikon promena agreedPrice)

### 8d.2 Firma (admin dashboard, tab "Finansije")
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

### 8d.3 Podešavanja (admin)
- prag minimalne cene sata (RSD/h)
- mesečni kapacitet radnika u satima (globalno + override po radniku)

---

## 8e. Izvoz

- Svaka lista (klijenti, zadaci, korisnici, kasnije statistika) ima dugme **"Izvoz"** sa izborom **Excel / CSV / PDF**.
- Izvozi se **ono što je trenutno filtrirano**, i to **samo redovi koje korisnik sme da vidi** (isti upit i isti Voteri kao lista).
- Cene i finansije se izvoze samo adminu i superadminu.
- Naziv fajla: `kontit-klijenti-2026-10-27.xlsx`.
- Veliki izvozi (preko 2.000 redova) idu preko Messenger-a, a korisnik dobije notifikaciju sa linkom kad je fajl spreman.
- Excel (phpspreadsheet): formatirano zaglavlje, širine kolona, iznosi kao brojevi, zamrznut prvi red, autofilter. CSV (league/csv): UTF-8 sa BOM-om, separator `;`. PDF (dompdf): šabloni u `templates/pdf/`, logo, Inter, boje brenda.

---

## 9. Planirano za kasnije (NE implementirati sada, ali ne blokirati)
- **Praćenje vremena**: neće biti start/stop tajmer, a način ćemo definisati kasnije.
  Za sada ne dodavati nikakva polja za vreme.
- **Portal za klijente**: klijenti će jednog dana pristupati i slati dokumentaciju.
  Zato držati `Client` (firma) odvojeno od `User` (zaposleni). Kasnije dolazi `ClientUser` (login za klijenta)
  i `Document` (vezan za Client). Ne vezivati logiku tako da pretpostavlja da su svi korisnici zaposleni.
- **Ponavljajući zadaci**: šabloni (mesečni PDV, plate PPP-PD, godišnji završni račun) koji se automatski kreiraju svakog meseca ili godine.

---

## 10. Faze razvoja

- **Faza 0**: Symfony projekat, Bootstrap layout, login, reset lozinke, `role_hierarchy`
- **Faza 1**: User CRUD (avatar, profil, aktivacija i deaktivacija), Team CRUD, članstvo i prelazak između timova, promena šefa
- **Faza 2**: Client CRUD, dodela glavnog i backup radnika, istorija dodela, "Moji klijenti"
- **Međukorak (završen)**: Prelazak na Limitless template (vidi `TEMPLATE_INVENTORY.md`)
- **Faza 3 (završena)**: Zadaci: kreiranje, više učesnika, checklist (AJAX, drag & drop), komentari (AJAX), potvrda šefa i vraćanje na doradu
- **Faza 4 (završena)**: ActivityLog listener i timeline prikaz
- **Faza 5 (završena)**: Notifikacije (in-app i opcioni mail preko Messenger-a)
- **Faza 6 (završena)**: Odsustva i aktivacija backup radnika
- **Faza 7 (završena)**: Dashboardi i statistika

**Dopuna (iz UPDATE.md), novi redosled posle završene Faze 7:**
- **Korak A (završen)**: SweetAlert2 i globalni `data-confirm` mehanizam (8a), primenjen na postojeće kritične radnje: korisnici, timovi, klijenti, zadaci, komentari, checklist. *(Sonnet 5.5)*
- **Faza 2b (završena)**: Katalog poslova (JobTemplate i checklist šablon), paketi, povezivanje paketa sa klijentom, ClientJob (isključivanje, ad hoc), istorija cena. Tab "Paketi i poslovi" na kartici klijenta (8b). *(Opus 5.5)*
- **Faza 3b (završena)**: Ponavljajući zadaci: polja na Task, `Holiday` tabela, komanda `app:tasks:generate`, "Generiši sada" (8c). *(Opus 5.5)*
- **Faza 7b (završena)**: Finansijska analitika, samo admin+ (8d). Podešavanja praga cene sata i kapaciteta.
- **Faza 8**: Praćenje vremena (agent), stvarno vreme zamenjuje procenjeno u analitici
- **Faza 9** (kasnije): portal za klijente, eksport, ostalo
(Ranije planirani "ponavljajući zadaci" iz Faze 8 su prebačeni u Fazu 3b.)

---

## 11. NEDOUMICE

> Kad odlučiš, upiši odluku u odgovarajuću sekciju iznad i obriši stavku odavde.

_Trenutno nema otvorenih nedoumica._

### Rešeno (podrazumevane vrednosti iz predloga, implementirano u fazama 2b/3b/7b)
1. **Šef i cene**: šef i radnik **ne vide** cene ni analitiku (`PRICE_VIEW` = admin+).
2. **Izmena sadržaja paketa**: pri čuvanju paketa admin bira "Primeni na N klijenata koji ga imaju". Novi poslovi se dodaju samo ako je opcija označena; poslovi uklonjeni iz paketa ostaju klijentima dok se ručno ne ukinu.
3. **Fakturisanje**: samo izveštaj "Za naplatu" po klijentu za izabrani mesec (paket + ad hoc poslovi sa cenom), izvoz u CSV (otvara se u Excelu). Fakture kasnije.
4. **PDV**: cene se unose i prikazuju bez PDV-a; posebno podešavanje nije dodato.
5. **Ad hoc posao koji doda radnik**: radnik ga **predlaže** (`isApproved=false`, bez cene), admin odobrava i upisuje cenu; do odobrenja se ne generišu zadaci.

### Klijent: podaci iz NBS-a (implementirano)
Klijent se uvozi i osvežava po PIB-u iz NBS CoreXmlService (`NbsService`, `/clients/import`, dugme "Osveži iz NBS", komanda `app:nbs:sync [--only-missing]`). Iz NBS-a se čuvaju i prikazuju: naziv, matični broj, adresa, opština, region, pravna forma, delatnost (šifra i naziv), datum osnivanja i status; pristupni podaci su u `.env.local` (`NBS_*`), ne u repou.
