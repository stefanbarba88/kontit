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
- name (pun, zvaničan naziv), **shortName** (obavezan, do 40 karaktera; vidi 8f.2). Pravna forma (`legalForm`) je ukinuta (8f.3).
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
- `NbsCompanyLookupService` sa zamenljivim izvorom: `NbsWebServiceProvider` (zvanični servis, kredencijali `NBS_WS_USERNAME`, `NBS_WS_PASSWORD`, `NBS_WS_LICENCE` u `.env.local`) Izbor u `.env` (`NBS_PROVIDER`); ako izabrani nije podešen, koristi se prvi podešen izvor. Rezervni izvor preko javne stranice nije napravljen: stranica rezultate vraća samo pregledaču, a automatsko predstavljanje kao pregledač se ne radi.
- AJAX `/ajax/client/lookup?pib=...` vraća naziv, adresu, mesto, PIB, matični broj (dopunjen na 8 cifara) i tekuće račune (samo prikaz na formi). Poziv uvek ide preko našeg servera.
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
- **Proširuje se u `Absence`** (vrste odsustva kao entitet, radni dani, rok za odobrenje, prilozi): vidi 8j.

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
- **Od koraka P6 važi 8j** (šefovo sopstveno odsustvo odobrava admin, vrste odsustva, automatsko odobravanje). Dosadašnje pravilo:
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

> **Zamenjeno sekcijama 8g i 8h** (UPDATE-POSLOVI-KALENDAR) gde se razlikuju: kategorija posla postaje entitet, raspored posla, source `AD_HOC` postaje `INDIVIDUAL`.

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

> **Zamenjeno sekcijom 8i** gde se razlikuju: `JobSchedule` prelazi u `ScheduleCalculator`, `Holiday` tabela u neradne dane (8k), zamena na odsustvu pri generisanju.

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

## 8f-8n. DOPUNA (iz UPDATE-POSLOVI-KALENDAR.md)

> Ima prednost nad 8b i 8c gde se razlikuju. Redosled ugradnje je u sekciji 10 (koraci P1-P11, svi završeni). Original je u `docs/archive/UPDATE-POSLOVI-KALENDAR.md`.

## 8f. Sitne izmene (implementirano u P1)

### 8f.1 Aktivnosti (timeline): paginacija
- Timeline na profilu korisnika, klijentu i zadatku prikazuje **20 poslednjih**, a dugme **"Učitaj još"** (AJAX) donosi sledećih 20.
- Ruta `/ajax/users/{id}/activity?page=N` (i ekvivalent za klijenta i zadatak) vraća HTML fragment timeline-a i podatak da li ima još.
- Iznad timeline-a filteri: **tip akcije** i **period** (flatpickr opseg). Promena filtera učitava od prve strane.
- Upit sa `LIMIT/OFFSET` po indeksima `(entity_type, entity_id, created_at)` i `(user_id, created_at)` (oba već postoje). Rute: `/ajax/users/{id}/activity`, `/ajax/clients/{id}/activity`, `/ajax/tasks/{id}/activity`; svoj timeline vidi svako, tuđi uz `USER_VIEW`.
- Dok se učitava: skelet (DESIGN.md 7.13). Prazno stanje: "Još nema aktivnosti."

### 8f.2 Klijent: kraći naziv
- `Client.shortName` (obavezno, do 40 karaktera), npr. "Mlekara Ivanjica".
- **Kraći naziv se koristi svuda**: liste, badge-ovi, Tom Select opcije, naslovi zadataka, kalendar, notifikacije, mailovi.
- **Pun naziv** (`name`) samo na kartici klijenta, u PDF izveštajima i izvozima (posebna kolona).
- Migracija: postojećim klijentima `shortName` = `name` skraćen na 40 karaktera (korisnik ih kasnije ručno ispravlja).
- NBS: `name` = zvaničan naziv, a `shortName` se predlaže (bez pravne forme i dodataka "doo", "d.o.o.", "preduzetnik", "PR"), uz mogućnost izmene.
- Pretraga (liste) traži po oba naziva i po PIB-u.

### 8f.3 Klijent: bez pravne forme
- `legalForm` se uklanja iz entiteta, forme, prikaza, izvoza i NBS uvoza, a enum `LegalForm` se briše. Nova migracija briše kolonu.

## 8g. Katalog: kategorije, posao, raspored (implementirano u P3)

### 8g.1 Kategorije poslova
- Entitet **`JobCategory`**: name, color (8 boja iz DESIGN.md 3.6), icon (Lucide naziv, opciono), position, isActive.
- CRUD za admin+: lista sa drag & drop redosledom (SortableJS), dodavanje i izmena u modalu. Kategorija sa poslovima se ne briše, nego deaktivira (SweetAlert objašnjenje).
- `JobTemplate.category` postaje veza na `JobCategory` (enum `JobCategory` se briše). Migracija pravi kategorije od postojećih vrednosti enuma (Knjiženje, PDV, Plate, Prijave, Završni račun, Savetovanje, Administrativno, Ostalo) i povezuje poslove.
- Kategorija se prikazuje kao badge u boji svuda gde se pojavljuje posao.

### 8g.2 Posao (JobTemplate)
- name, category, description, checklist stavke (`JobTemplateChecklistItem`), raspored (8g.3), defaultPriority, completionMode, estimatedMinutes, priceUnit, isActive.
- Raspored je ugrađen objekat `Schedule` (isti na poslu i kao override na ClientJob-u; override važi ceo). Dnevni posao se ne pomera zbog neradnih dana; ključ perioda je od nominalnog roka; "na svakih N" se računa od početka posla klijenta.
- **defaultPrice** (opciona *predložena* cena; popunjava polje cene pri dodavanju pojedinačnog posla klijentu, a stvarna cena je `ClientJob.price`).

### 8g.3 Raspored izvršavanja
Ako je raspored **"Po potrebi" (NONE)**, sistem ne kreira zadatke, nego ih korisnik pravi ručno (npr. prijava/odjava radnika).

| Tip | Podešavanja | Primer |
|---|---|---|
| NONE (Po potrebi) | nema | Prijava/odjava radnika |
| DAILY | na svakih N dana; samo radni dani (da/ne) | Knjiženje izvoda (pri unosu kataloga razmisliti da li je bolje "Nedeljno" sa checklistom po danima, jer DAILY pravi mnogo zadataka; forma posla to napominje) |
| WEEKLY | na svakih N nedelja; dan u nedelji | Svakog ponedeljka |
| MONTHLY | na svakih N meseci; dan 1-31 ili "poslednji dan" | PDV prijava, 15. |
| QUARTERLY | mesec posle kvartala (1-3); dan u mesecu | 20. dan posle kvartala |
| YEARLY | mesec i dan | Završni račun, 30. jun |

- Zajedničko: **leadDays** (koliko dana pre roka nastaje zadatak; DAILY obično 0) i **nonWorkingDayShift** (NEXT = prvi sledeći radni dan, podrazumevano; PREVIOUS = prethodni radni dan). DAILY sa "samo radni dani" preskače neradne dane. Dan 31 u kraćem mesecu = poslednji dan.
- Polja na JobTemplate: `scheduleType`, `scheduleInterval`, `scheduleDayOfWeek`, `scheduleDayOfMonth`, `scheduleMonthOfQuarter`, `scheduleMonth`, `workingDaysOnly`, `leadDays`, `nonWorkingDayShift`. **Isti skup (nullable) je override na ClientJob**; prazan override = raspored posla. Zamenjuju stara polja `recurrence`, `dueDay`, `dueMonth`, `generateDaysBefore` (migracija prenosi vrednosti; dosadašnji ONE_TIME poslovi postaju NONE, a jednokratan zadatak se pravi dugmetom "Kreiraj zadatak sada").
- **`ScheduleCalculator`** (jedino mesto za logiku rasporeda, unit testovi): `nextOccurrences(schedule, from, count)` (rokovi već pomereni zbog neradnih dana), `periodKey(schedule, date)` (`2026-11-03`, `2026-W45`, `2026-11`, `2026-Q4`, `2026`), `describe(schedule)` ("Mesečno, 15. u mesecu (ako je neradni dan, prvi sledeći radni dan)").
- Testovi: 31. u februaru, prestupna godina, rok na vikend i praznik, kolektivni odmor preko kraja meseca, "poslednji dan", DAILY samo radnim danima, kvartal preko kraja godine.
- UI: segmentirana dugmad za tip, pa samo polja tog tipa; ispod opis rečima i **pregled narednih 5 rokova** (AJAX pri svakoj promeni). Pomeren rok ima ikonicu i tooltip ("15. nov je nedelja, pomereno na 16. nov").

### 8g.4 Checklist editor
- Stavke posla su prava lista: ručka (SortableJS) · polje · dugme `x`; "+ Dodaj stavku"; Enter u poslednjem polju dodaje novu i fokusira je; Backspace u praznom polju briše stavku i vraća fokus na prethodnu; prazne stavke se ignorišu; brojač "5 stavki".
- `CollectionType` (`allow_add`, `allow_delete`, `by_reference: false`, prototip), redosled u `position`.
- Isti komponent (Twig makro + `assets/js/checklist-editor.js`) i za checklist zadatka pri ručnom kreiranju i izmeni.

## 8h. Paketi i ugovorene usluge klijenta (implementirano u P4)

### 8h.1 Model
```
JobCategory ─< JobTemplate (posao, checklist, raspored)
ServicePackage ─< ServicePackageItem >─ JobTemplate
Client ─< ClientPackage >─ ServicePackage          (više paketa po klijentu)
Client ─< ClientJob >─ JobTemplate                 (UGOVORENI posao: iz paketa ili pojedinačan)
ClientJob ─< Task
```
- **ServicePackage**: name, description, isActive, items (sa redosledom). **price** (opciona *predložena* mesečna cena; popunjava `agreedPrice` pri dodeli klijentu, a važi samo cena na klijentu).
- **ClientPackage**: client, package, **agreedPrice**, discountPercent (ostaje kao do sada), note, startDate, endDate, isActive; promene cene u `ClientPackagePriceLog`.
- **ClientJob**: client, jobTemplate, **source** (PACKAGE / INDIVIDUAL; dosadašnji AD_HOC postaje INDIVIDUAL), clientPackage, **price** (samo pojedinačni), note, override rasporeda (8g.3), override zaduženog, startDate, endDate, isActive, isExcluded + excludedReason/By/At. Predlog radnika (`isApproved`) ostaje kao do sada.
- **Ugovoreni poslovi** = aktivni, neisključeni ClientJob-ovi. Samo iz njih nastaju zadaci.

### 8h.2 Job picker (forma paketa)
- Levo: poslovi grupisani po kategoriji (boja, ikonica), grupe se skupljaju, pretraga u letu, "Izaberi sve u grupi", opis rasporeda u `--kt-text-2`. Desno (na telefonu ispod, sklopiv blok "Izabrano: 6"): izabrani poslovi sa prevlačenjem i ✕.
- Jedan komponent: `templates/components/_job_picker.html.twig` + `assets/js/job-picker.js`, koristi se i u 8h.3.

### 8h.3 Klijent: tab "Ugovorene usluge"
- **Dodaj paket** (modal / bottom sheet): Tom Select paketa, odmah svi poslovi paketa štiklirani sa opisom rasporeda; odštikliran posao dobija opcioni razlog i čuva se kao ClientJob sa `isExcluded = true`. Dogovorena cena, napomena, datum početka. Upozorenje ako je posao već u drugom paketu klijenta.
- **Dodaj pojedinačan posao** (modal): picker sa izborom jednog ili više poslova; za svaki cena, napomena, opciono drugačiji raspored i zaduženi. Čuva se kao source = INDIVIDUAL.
- **Pregled**: Posao · Izvor · Raspored · Sledeći zadatak · Zadužen · Cena · ⋯. Grupisano po paketima, pa pojedinačni. Isključeni su prigušeni i precrtani, uz razlog, i mogu se ponovo uključiti. Meni ⋯: izmeni (cena, napomena, raspored, zaduženi), isključi/uključi, završi (endDate), "Kreiraj zadatak sada".
- Dole **ukupna mesečna cena** (paketi + mesečni pojedinačni). **Cene vidi samo admin+.**
- Uklanjanje paketa postavlja endDate na ClientPackage i njegove ClientJob-ove, uz SweetAlert pitanje šta sa otvorenim zadacima.
- Zamenjuje stranicu `/clients/{id}/packages` (tab je `/clients/{id}?tab=usluge`). Drugačiji raspored se podešava preko "Izmeni", posle dodavanja posla.

## 8i. Zadaci: automatski i ručni (implementirano u P5)

### 8i.1 Automatsko kreiranje
- `app:tasks:generate` (cron 06:00) za svaki ugovoren ClientJob čiji raspored nije NONE: rok preko `ScheduleCalculator`-a; ako je danas >= rok − leadDays i zadatak za period ne postoji, kreira ga.
- Jedinstven `(client_job_id, period_key)` (do sada `generation_key`) sprečava duplikate.
- Zadatak: naslov `{posao} — {kraći naziv klijenta} — {period}`, checklist iz posla, prioritet, režim završetka, procena trajanja, zaduženi = override sa ClientJob-a, inače glavni radnik klijenta.
- Ako je glavni radnik tog dana **na odobrenom odsustvu**, zadatak dobija **zamena** i označava se kao "PREUZETO" (`Task.takenOverFrom`).
- Rok koji je prošao pre najviše 31 dan se još pravi (npr. kad cron nije radio), stariji ne.
- Isti posao iz dva paketa istog klijenta: jedan zadatak po periodu.
- Admin: "Generiši sada" (SweetAlert sa brojem zadataka) i pregled narednih 30 dana (postoji).

### 8i.2 Ručno kreiranje
- Zadatak iz ugovorenog posla sa rasporedom "Po potrebi" (checklist iz posla), dodatni zadatak iz posla sa rasporedom ("Kreiraj zadatak sada"), i potpuno samostalan zadatak (sa klijentom ili interni, bez klijenta).

### 8i.3 Forma zadatka: klijent i zaduženi
- Sa kartice klijenta (`/clients/{id}/tasks/new`): klijent zaključan (kartica: kraći naziv, PIB); "Posao" nudi samo ugovorene poslove klijenta (opciono); zaduženi samo ljudi raspoređeni na klijenta (glavni, zamena, override zaduženi sa njegovih ClientJob-ova), uz ulogu ("glavni", "zamena").
- Iz zadataka (`/tasks/new`): prvo klijent (Tom Select po kraćem, punom nazivu i PIB-u) ili **"Interni zadatak (bez klijenta)"**; dok klijent nije izabran, "Posao" i "Zaduženi" su zaključani ("Prvo izaberi klijenta"). AJAX `/ajax/clients/{id}/task-options` puni poslove i zadužene; pri promeni klijenta uklanjaju se zaduženi koji nisu na novom klijentu (toast). Interni zadatak: pravila iz 4.4.
- Uvek: pravila 4.4 važe; server ponovo proverava (Voter + da je zaduženi raspoređen na klijenta). Izbor posla popunjava checklist (editor 8g.4), prioritet i procenu. Rok preko flatpickr-a, neradni dani prigušeni, a izbor neradnog dana daje upozorenje (ne zabranu).

## 8k. Neradni dani (implementirano u P2)
Zamenjuje `Holiday` tabelu. Admin+ upravlja na **Podešavanja → Neradni dani** (`/admin/non-working-days`). `nextWorkingDay` i `previousWorkingDay` vraćaju sam dan ako je radni.
- `WeeklyNonWorkingRule` (dani u nedelji, jedno pravilo za firmu; podrazumevano subota i nedelja) i `NonWorkingDay` (type: HOLIDAY_RECURRING, HOLIDAY_ONCE, COLLECTIVE_LEAVE; name, dateFrom, dateTo, recurring, note). Postojeći praznici iz `Holiday` prelaze u HOLIDAY_ONCE.
- "Učitaj praznike Srbije za godinu": zakonski praznici, uključujući pravoslavni Uskrs (julijanski kalendar) i Veliki petak; admin pregleda pre čuvanja. Praznik u nedelju: predlog da se ne radi narednog radnog dana, admin potvrđuje.
- Pregled: godišnji kalendar (12 malih meseci) sa obojenim neradnim danima i lista ispod.
- **`WorkingDayService`** (jedino mesto za "da li je radni dan"): `isWorkingDay`, `nextWorkingDay`, `previousWorkingDay`, `countWorkingDays`, `nonWorkingDaysBetween`. Keš po godini, briše se pri izmeni. Koriste ga `ScheduleCalculator`, odsustva, flatpickr (`/ajax/calendar/non-working?year=`) i kalendar.

## 8j. Odsustva (zamenjuje UserAbsence iz sekcije 3 i dopunjuje sekciju 5; implementirano u P6)

> Entitet se i dalje zove `UserAbsence` (tabela `user_absence`); to je "Absence" iz ove sekcije. `createdBy` je podnosilac (requestedBy).

### 8j.1 Vrste odsustva (CRUD za admina)
`AbsenceType`: name, color, icon, requiresApproval, countsAgainstLeave, requiresDocument, maxDaysPerYear (opciono), isActive.

| Vrsta | Odobrenje | Troši godišnji | Dokument |
|---|---|---|---|
| Godišnji odmor | da | da | ne |
| Slava | da | ne | ne |
| Bolovanje | ne (evidencija) | ne | da, doznaka (može naknadno) |
| Plaćeno odsustvo | da | ne | ne |
| Neplaćeno odsustvo | da | ne | ne |
| Ostalo | da | ne | ne |

### 8j.2 Zahtev
- `Absence` (proširuje `UserAbsence`): user, type, dateFrom, dateTo, halfDay (samo za jedan dan), **workingDays** (preko WorkingDayService-a), note, status, requestedBy, decidedBy, decidedAt, decisionNote, **approvalDeadline**, prilozi (8l).
- Statusi: `NA_ODOBRENJU` → `ODOBRENO` / `ODBIJENO` / `AUTOMATSKI_ODOBRENO`, plus `OTKAZANO` (podnosilac dok je na odobrenju, admin uvek). Dosadašnji `NA_CEKANJU` postaje `NA_ODOBRENJU`.
- Radnik podnosi za sebe. Šef i admin upisuju bilo kome koga vide, i tada je odobreno odmah.
- **Odobrava** šef tima podnosioca; ako je podnosilac šef (i za sopstveno odsustvo), nema tim ili mu je šef odsutan, odobrava admin.
- Forma odmah pokazuje broj radnih dana, preostale dane godišnjeg posle zahteva i ko je još iz tima odsutan (upozorenje).
- Preklapanje sa postojećim odsustvom istog korisnika nije dozvoljeno. Odobreno odsustvo pokreće zamenu iz sekcije 5.

### 8j.3 Evidencija i pregled
- `User.annualLeaveDays` i `LeaveBalance` (user, year, entitled, carriedOver, used; `used` iz odobrenih odsustava koja troše godišnji).
- **Prenos**: neiskorišćeni dani se prenose u sledeću godinu (`carriedOver`) i mogu se koristiti **do 30. juna**; posle toga preneti dani propadaju. Godišnji odmor prvo troši prenete dane.
- **Bolovanje** je samo evidencija (bez odobravanja, `requiresApproval = false`, odmah ODOBRENO i pokreće zamenu); doznaka se prilaže u roku od 3 dana, a posle toga korisnik i admin dobijaju podsetnik.
- **Slava** se za sada ne upisuje na profil i nema automatskog predloga zahteva (može kasnije).
- Profil: kartica "Odsustva" (preostalo / iskorišćeno / preneto, zahtevi po godini).
- Stranica Odsustva (šef svoj tim, admin svi): tab **"Na odobrenju"** (brojač u meniju, Odobri / Odbij uz razlog), tab **"Pregled"** (radnik × dani u mesecu, boje po vrsti, neradni dani prigušeni, filter po timu), tab **"Evidencija"** (zbir po radniku i vrsti za godinu, izvoz Excel / PDF).
- Telefon: "Na odobrenju" kao kartice sa prevlačenjem za Odobri / Odbij, pregled kao lista po danima.

## 8m. Automatsko odobravanje posle roka (implementirano u P7)
- Ako odobravalac ne odgovori do roka, zahtev se smatra odobrenim.
- `ApprovableInterface` (status, approvalDeadline, approve(), approveAutomatically()); implementira ga `Absence`.
- Rok pri podnošenju: `min(podnošenje + X radnih sati, početak odsustva − 1 dan)`; X po vrsti zahteva u Podešavanjima (podrazumevano 48 h). Ako je odsustvo za sutra, odobravalac dobija hitnu notifikaciju.
- `app:approvals:auto-approve` (cron na 15 min): `NA_ODOBRENJU` sa isteklim rokom → `AUTOMATSKI_ODOBRENO`, ActivityLog (sistem), notifikacije obojici. Podsetnik odobravaocu 24 h pre isteka.
- UI: odbrojavanje ("Automatski se odobrava za 5 h"), poseban badge za automatski odobreno. Admin može da poništi automatsko odobrenje (razlog, notifikacija).
- "X radnih sati" = sati koji padaju u radne dane (vikend, praznici i kolektivni odmor se preskaču), bez radnog vremena od-do. Podrazumevano 48 h za sve vrste odsustva, uključujući slavu (menja se u Podešavanjima).
- Primena: odsustva (uključeno). **Potvrda zadataka (NA_PROVERI)**: mehanizam postoji, podrazumevano isključen; kad se uključi u Podešavanjima, rok je 3 radna dana. Uključivanje je po vrsti zahteva.

## 8l. Dokumenti korisnika (implementirano u P8)
- `UserDocument`: user, category (`DocumentCategory`, CRUD: Ugovor, Rešenje, Potvrda, Izvod, Doznaka, Ostalo), title, file (PDF, JPG, PNG, do 20 MB), originalName, mimeType, size, validUntil, note, uploadedBy, createdAt, absence (opciono), deletedAt.
- Upload preko FilePond-a, PDF preko PDF.js u modalu, slike u lightbox-u.
- Fajlovi van `public/` (`var/storage/user-documents/{userId}/`, nasumično ime), servira ih kontroler uz Voter `USER_DOCUMENT_VIEW`. Nikad direktan link.
- Vidi: korisnik svoje, admin+ sve, šef samo dokumente vezane za odsustva svog tima. Upload: admin+ za bilo koga, korisnik za sebe.
- Profil: tab "Dokumenti" po kategorijama (ikonica tipa, naziv, datum, veličina, ko je dodao) sa filterom.
- `validUntil`: notifikacija adminu 30 i 7 dana pre isteka. Brisanje je soft (SweetAlert); fajl se fizički briše tek trajnim brisanjem iz korpe (admin).

## 8n. Kalendar (8n.1, 8n.2 i ICS implementirani u P9, Google u P10)
- **FullCalendar v6** (MIT paketi: core, daygrid, timegrid, list, interaction) preko importmap-a, srpski latinični locale, nedelja od ponedeljka, tokeni iz DESIGN.md u obe teme. Premium paketi se ne koriste.
- Prikazuje: zadatke po roku (boja statusa, ikonica prioriteta, kraći naziv klijenta; po Voterima), odsustva (boja vrste, avatar; svoja / šef tim / admin sve), neradne dane i kolektivni odmor (prigušena pozadina i naziv; svi).
- Prikazi Mesec, Nedelja, Dan, Lista; na telefonu podrazumevano Lista, prevlačenje levo/desno za period. Filteri (čipovi): Moji / Moj tim / Svi, vrsta događaja, klijent, radnik.
- Događaji AJAX-om za vidljiv opseg (`/ajax/calendar/events?start=&end=&filters=`).
- Klik na prazan dan: "Novi zadatak za ovaj dan" ili "Zahtev za odsustvo od ovog dana". Klik na događaj: popover sa detaljima i linkom. Prevlačenje zadatka menja rok (samo uz `TASK_EDIT`), uz SweetAlert i upozorenje za neradni dan.
- Kalendar je u glavnom meniju; mali pregled naredne nedelje na dashboardu.
- **ICS link**: tajni `/calendar/ics/{token}.ics` (samo čitanje), uz poništavanje i novi link.
- **Google Calendar** (samo jedan smer, aplikacija → Google; izmene u Google-u se ne vraćaju u aplikaciju): svaki korisnik sam povezuje nalog (OAuth 2.0, scope `calendar.app.created`), aplikacija pravi poseban kalendar "KontIT". Šalju se zadaci gde je korisnik učesnik (celodnevno na dan roka, link u opisu) i njegova odobrena odsustva; šef opciono i odsustva tima. Kreiranje, izmena roka, status (✓ u naslovu), otkazivanje i brisanje idu asinhrono preko Messenger-a, sa ponavljanjem; posle 5 neuspeha notifikacija korisniku. Entiteti `GoogleCalendarConnection` (refreshToken šifrovan) i `CalendarSyncLink`. Komanda `app:calendar:resync {user}`. Paketi `knpuniversity/oauth2-client-bundle`, `league/oauth2-google`, `google/apiclient` (samo Calendar servis). Uputstvo za Google Cloud konzolu u `docs/google-calendar-setup.md`; `GOOGLE_CLIENT_ID` i `GOOGLE_CLIENT_SECRET` samo u `.env.local`.

### Podešavanja (admin) iz ove dopune
Neradni dani, vrste odsustva i kategorije dokumenata, rokovi automatskog odobravanja po vrsti zahteva, podrazumevan broj dana godišnjeg odmora, Google kalendar (uključen za firmu, šefovi dobijaju odsustva tima).

### Nove notifikacije
ABSENCE_REQUESTED (postoji), ABSENCE_APPROVED, ABSENCE_REJECTED (zamenjuju ABSENCE_DECIDED), ABSENCE_AUTO_APPROVED, APPROVAL_DEADLINE_SOON, DOCUMENT_EXPIRING, DOCUMENT_ADDED, CALENDAR_SYNC_FAILED.

## 8o. Uputstvo za korisnike (u aplikaciji)
- Meni **Pomoć → Uputstvo** (`/uputstvo`, na telefonu u „Više“) otvara uputstvo najviše role korisnika. Uputstva: **radnik**, **šef**, **admin** (`HelpGuide` enum). Superadmin nema posebno uputstvo: poglavlje „Samo za superadmina“ je u admin uputstvu.
- Ko šta vidi (`HelpVoter`, `HELP_VIEW`, preko role_hierarchy): radnik samo svoje; šef šefovo i radničko; admin i superadmin sva tri. Tabovi na vrhu kad ih ima više.
- Sadržaj je **Markdown** u `docs/uputstvo/{radnik,sef,admin}.md` (`twig/markdown-extra` + `league/commonmark`, GFM tabele). `HelpRenderer` pravi HTML, sadržaj (h2 i h3, sa id-jevima) i deli tekst na poglavlja (za pretragu); keš po vremenu izmene fajla.
- Dogovor u tekstu (`docs/uputstvo/README.md`): `[SLIKA: opis]` = okvir za sliku koja dolazi kasnije; `![opis](fajl.png)` = slika iz `docs/uputstvo/slike/`, servira je `HelpController` samo prijavljenima (ne iz `public/`); `> **Savet:**` zeleni i `> **Pažnja:**` / `> **Važno:**` narandžasti okvir.
- Strana: naslov iz `#`, sadržaj sa pretragom (bez obzira na kvačice) na vrhu, na desktopu lepljiv sa strane; poglavlja numerisana; dugme „nazad na sadržaj“.

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
- **UPDATE-DIZAJN (završen)**: sopstveni UI sistem, mobilni prikaz, izvoz (8e).
- **Faza 8 (ODLOŽENO)**: Praćenje vremena (agent), stvarno vreme zamenjuje procenjeno u analitici. Ne raditi dok korisnik ne odluči.
- **Faza 9** (kasnije): portal za klijente, ostalo
(Ranije planirani "ponavljajući zadaci" iz Faze 8 su prebačeni u Fazu 3b.)

**Dopuna (iz UPDATE-POSLOVI-KALENDAR.md), redosled (P1-P11 završeni 2026-10-28):**
- **P1**: Sitne izmene: paginacija aktivnosti, `shortName`, uklanjanje pravne forme (8f) *(Sonnet 5.5)*
- **P2**: Neradni dani i `WorkingDayService` sa testovima (8k) *(Opus 5.5)*
- **P3**: Kategorije poslova, checklist editor, raspored posla i `ScheduleCalculator` sa testovima (8g) *(Opus 5.5)*
- **P4**: Paketi sa job picker-om, tab "Ugovorene usluge" (8h) *(Opus 5.5)*
- **P5**: Generisanje po novom modelu i forma zadatka sa klijentom i zaduženima (8i) *(Opus 5.5)*
- **P6**: Odsustva: vrste, zahtevi, odobravanje, evidencija i pregled (8j) *(Sonnet 5.5)*
- **P7**: Automatsko odobravanje posle roka (8m) *(Opus 5.5)*
- **P8**: Dokumenti korisnika (8l) *(Sonnet 5.5)*
- **P9**: Kalendar u aplikaciji (FullCalendar) i ICS link (8n) *(Sonnet 5.5)*
- **P10**: Google Calendar povezivanje i sinhronizacija (8n) *(Opus 5.5)*
- **P11**: Pregled svih novih ekrana u obe teme, na 390 px i 1440 px *(Sonnet 5.5)*

---

## 11. NEDOUMICE

> Kad odlučiš, upiši odluku u odgovarajuću sekciju iznad i obriši stavku odavde.

_Trenutno nema otvorenih nedoumica._

### Rešeno 2026-10-28 (UPDATE-POSLOVI-KALENDAR, prihvaćeni predlozi)
Automatsko odobravanje potvrde zadataka postoji, ali je isključeno (rok 3 radna dana kad se uključi); rok odsustva 48 h u radnim danima za sve vrste; Google samo u jednom smeru; bolovanje samo evidencija; prenos godišnjeg do 30. juna; slava se ne upisuje; šef vidi samo dokumente vezane za odsustva tima; DAILY je odluka po poslu; predložene cene u katalogu (opcione); ONE_TIME postaje NONE; šefovo odsustvo odobrava admin; popust na paketu ostaje. Odluke su upisane u 8g-8n.

### Rešeno (podrazumevane vrednosti iz predloga, implementirano u fazama 2b/3b/7b)
1. **Šef i cene**: šef i radnik **ne vide** cene ni analitiku (`PRICE_VIEW` = admin+).
2. **Izmena sadržaja paketa**: pri čuvanju paketa admin bira "Primeni na N klijenata koji ga imaju". Novi poslovi se dodaju samo ako je opcija označena; poslovi uklonjeni iz paketa ostaju klijentima dok se ručno ne ukinu.
3. **Fakturisanje**: samo izveštaj "Za naplatu" po klijentu za izabrani mesec (paket + ad hoc poslovi sa cenom), izvoz u CSV (otvara se u Excelu). Fakture kasnije.
4. **PDV**: cene se unose i prikazuju bez PDV-a; posebno podešavanje nije dodato.
5. **Ad hoc posao koji doda radnik**: radnik ga **predlaže** (`isApproved=false`, bez cene), admin odobrava i upisuje cenu; do odobrenja se ne generišu zadaci.

### Klijent: podaci iz NBS-a (implementirano)
Klijent se uvozi i osvežava po PIB-u iz NBS CoreXmlService (`NbsService`, `/clients/import`, dugme "Osveži iz NBS", komanda `app:nbs:sync [--only-missing]`). Iz NBS-a se čuvaju i prikazuju: naziv, matični broj, adresa, opština, region, pravna forma, delatnost (šifra i naziv), datum osnivanja i status; pristupni podaci su u `.env.local` (`NBS_*`), ne u repou.
