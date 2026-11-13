# Uputstvo za šefa

Ovo uputstvo opisuje ono što šef radi **pored** običnog rada na zadacima. Kao šef ti si i radnik: imaš svoje klijente i zadatke, pa za to važi **Uputstvo za radnika** (tab **Radnik** na vrhu strane).

## Šta je šef

Šef vodi **jedan tim** i odgovoran je za njegov rad. Šef:

- vidi **klijente i zadatke svog tima** (klijente na kojima su članovi glavni ili backup, i zadatke na kojima rade),
- **ne vidi druge timove**: ni njihove klijente ni njihove zadatke,
- pravi zadatke sebi i **članovima svog tima**,
- **potvrđuje završene zadatke** ili ih **vraća na doradu**,
- menja ko je **glavni** i ko je **backup** na klijentima svog tima,
- **odobrava ili odbija** zahteve za odsustvo članova tima i upisuje im odsustvo,
- ne vidi cene ni finansije.

Šefa tima postavlja administrator (**Timovi → Izmeni → Šef tima**). Kad se šef promeni, novi šef odmah vidi klijente i zadatke tima, a stari prestaje da ih vidi.

## Prvo prijavljivanje

Prijava, promena lozinke i profil su isti kao za radnika (vidi **Uputstvo za radnika → Prvo prijavljivanje**). Posle prve prijave proveri:

1. **Moj profil → Nalog**: da li pored **Šef tima** piše tvoj tim.
2. **Moj tim** u meniju: da li su svi članovi tu.
3. **Moj profil → Obaveštenja na mail**: šef dobija dosta obaveštenja (zadaci na proveri, zahtevi za odsustvo), pa izaberi šta želiš i na mail.

## Šta šef ima u meniju

Pored stavki koje ima svaki radnik (**Početna**, **Moji klijenti**, **Odsustva**, **Zadaci**, **Kalendar**), šef ima i grupu **Klijenti**:

| Stavka | Šta je tu |
|---|---|
| **Klijenti** | svi klijenti tvog tima, sa pretragom |
| **Moj tim** | članovi tima, od kada su u timu, istorija šefova |

Brojevi u meniju:

- pored **Zadaci**: koliko zadataka čeka tvoju potvrdu,
- pored **Odsustva**: koliko zahteva za odsustvo čeka tvoju odluku.

[SLIKA: meni šefa sa brojačima pored Zadaci i Odsustva]

## Početna strana šefa

Ispod tvog **Moj pregled** je blok **Moj tim**:

- **Opterećenje po radniku**: koliko klijenata i otvorenih zadataka ima svaki član. Tu vidiš ko je preopterećen.
- **Čeka potvrdu**: koliko zadataka čeka tvoju potvrdu i koliko dugo.
- **Od kreiranja do potvrde**: prosečno vreme za zadatke tima.
- **Čeka klijenta duže od 7 dana**: zadaci koji stoje zbog klijenta. Možda treba pozvati klijenta.
- **Učinak po radniku**: procenat na vreme i procenat vraćenih na doradu, za 30 i 90 dana.

[SLIKA: početna strana šefa, blok Moj tim]

## Moj tim

Meni **Moj tim** prikazuje:

- **Članove** (ime, pozicija, od kada je u timu), a pored svakog člana dugme **Zadaci** sa njegovim zadacima (vidi „Zadaci tima“),
- **Bivše članove** (ko je bio u timu i do kada),
- **Šefa tima** i **Istoriju šefova**.

Članove u tim dodaje i premešta administrator. Ako neko treba da pređe u tvoj tim ili iz njega, javi administratoru. Kad radnik pređe u drugi tim, njegovi klijenti i otvoreni zadaci idu sa njim.

## Klijenti tima

### Pretraga

Meni **Klijenti**. Pretražuješ po nazivu, PIB-u, matičnom broju ili gradu, biraš **Aktivni**, **Neaktivni** ili **Svi**, i klikneš **Traži**. U tabeli odmah vidiš **glavnog zaduženog** i **backup**. Žuto upozorenje „nema“ znači da klijent **nema zamenu**, što je rizik kad je glavni odsutan.

Dugme **Izvoz** pravi Excel, CSV ili PDF sa istim filterom.

Na kartici klijenta je tab **Zadaci** (vidi Uputstvo za admina, „Zadaci klijenta“): isti filteri, a ti vidiš samo zadatke koje smeš da vidiš.

### Promena glavnog zaduženog i zamene

1. Otvori klijenta i klikni **Dodela**.
2. Izaberi **Glavni zaduženi** i **Backup (zamena)**. Nude se samo članovi tvog tima, a glavni i backup moraju biti različite osobe.
3. Klikni **Sačuvaj** i potvrdi.

Šta se dešava posle promene:

- **novi** zadaci za tog klijenta idu novom glavnom,
- **otvoreni** zadaci ostaju kod dosadašnjeg radnika (po potrebi ih prebaci kroz **Izmeni** na zadatku),
- novi zaduženi dobija obaveštenje,
- promena se vidi u **Istoriji dodela** na kartici klijenta.

[SLIKA: ekran Dodela klijenta]

### Ugovorene usluge

Na kartici klijenta, tab **Ugovorene usluge** pokazuje šta je ugovoreno i kada je sledeći zadatak. Cene ne vidiš. Novi posao možeš da predložiš (**Predloži posao**), a administrator ga odobrava i upisuje cenu.

## Zadaci tima

### Šta vidiš

Na **Zadaci**, filter **Moji zadaci / Svi zadaci u mom opsegu** prebacuje između tvojih zadataka i svih zadataka tvog tima. Dodatni tab **Čeka moju potvrdu** ima zadatke koje treba da potvrdiš.

Filteri (naziv bez razlike u kvačicama, posao, nastanak, klijent, tim, radnik, status, prioritet, rok i **Prikaži završene i otkazane**) rade kao kod radnika (vidi Uputstvo za radnika). Filteri **Tim** i **Radnik** nude samo tvoj tim. Dugme **Izvoz** pravi Excel, CSV ili PDF tačno od prikazane liste.

### Zadaci jednog člana tima

U **Moj tim** klikni **Zadaci** pored člana (ili otvori adresu `/users/<broj>/zadaci`). Vidiš zaglavlje člana i listu njegovih zadataka sa istim filterima, plus:

- filter **Uloga**: **Zadužen** (podrazumevano), **Kreirao** ili **Zadužen ili kreirao**,
- brojače na vrhu: **otvoreno**, **kasni**, **na proveri** i **završeno ovog meseca**,
- dugme **Izvoz** (izvozi baš ovu listu) i **Novi zadatak**. Posle čuvanja te vraća na ovu stranu sa istim filterima.

Ovo smeš samo za članove svog tima na nivou radnika (i za sebe). Za člana tuđeg tima, drugog šefa ili admina dobijaš poruku da nemaš pravo.

### Novi zadatak za člana tima

1. **Zadaci → Novi zadatak** (ili **Novi zadatak** na kartici klijenta, tada je klijent već izabran i zaključan).
2. Izaberi **Za klijenta** ili **Interni (bez klijenta)**.
3. Za klijenta: izaberi **Klijenta**, pa po želji **Posao** (popunjava checklistu i prioritet).
4. Upiši **Naslov**, **Opis**, **Rok**, **Prioritet**.
5. **Završetak**:
   - **Svi učesnici moraju da završe**: zadatak ide na proveru tek kad svi kliknu „Moj deo je gotov“,
   - **Dovoljan je jedan učesnik**: ide na proveru čim jedan završi.
6. U **Zaduženi** štikliraj ko radi. Za klijenta se nude samo ljudi raspoređeni na tog klijenta (glavni, zamena), uz oznaku uloge.
7. Za hitne stvari štikliraj **Pošalji učesnicima i na mail**.
8. Klikni **Sačuvaj**.

> **Savet:** stavke checkliste možeš da dodeliš određenom učesniku (izbor pored stavke na strani zadatka). Tada samo on može da je štiklira i ne može da javi da je gotov dok je ne štiklira.

### Izmena i otkazivanje

Zadatke tima možeš da menjaš (**Izmeni**: naslov, rok, prioritet, učesnici, checklista) dok nisu završeni. **Otkaži zadatak** traži razlog i otkazuje ga za sve učesnike. Zadatak se nikad ne briše, samo otkazuje, pa istorija ostaje.

Rok možeš da promeniš i u **Kalendaru**, prevlačenjem zadatka na drugi dan, ili na strani zadatka, kartica **Rok i raspored**, dugme **Promeni rok**. Rok tada važi samo za taj zadatak (pored roka je znak olovke) i raspored ga ne preračunava. **Vrati na podrazumevani rok** vraća rok po rasporedu. Raspored posla za klijenta (za sve buduće periode) menja admin.

### Potvrda i vraćanje na doradu

Kad zadatak dođe **Na proveri**, dobijaš obaveštenje, a zadatak je u tabu **Čeka moju potvrdu**.

1. Otvori zadatak. Na vrhu je okvir **Čeka tvoju potvrdu**.
2. Proveri checklistu, komentare i napomene učesnika.
3. Ako je sve u redu, klikni **Potvrdi**. Zadatak postaje **Završeno** i upisuje se ko je i kada potvrdio.
4. Ako nije, upiši **Razlog vraćanja na doradu (obavezno)**, po želji štikliraj **Pošalji i na mail** i klikni **Vrati na doradu**. Zadatak se vraća **U radu**, a razlog stoji u komentarima.

[SLIKA: zadatak na proveri sa dugmadima Potvrdi i Vrati na doradu]

> **Važno:** ako si i sam učesnik zadatka, ne možeš sam sebi da ga potvrdiš. Takav zadatak potvrđuje administrator.

### Koji zadaci čekaju tvoju potvrdu

Provera zavisi od **pozicije radnika**: zadaci radnika na pozicijama koje traže proveru (podrazumevano Entry level i Junior) dolaze tebi, a zadaci Samostalnih i Šefova se završavaju odmah. Tvoju potvrdu ne traže **posebne potvrde** (poslovi koje potvrđuju određeni admini); na takvom zadatku piše **Posebna potvrda: ime**.

### Provera pri pravljenju zadatka

U formi zadatka za člana tima možeš da izabereš polje **Provera**: **Automatski, po poziciji**, **Bez provere** ili **Posebna potvrda** (bira se admin). Promena je moguća dok zadatak nije poslat na proveru, a samo za zadatke čiji su svi učesnici u tvom timu. Polje se ne vidi ako je tvoja pozicija sa proverom.

## Odsustva tima

Na strani **Odsustva** šef ima četiri taba.

### Zahtevi

Lista odsustava tvog tima i tvojih, sa dugmadima **Aktuelna** i **Istorija**. Ovde možeš i da odlučuješ (**Odobri**, **Odbij**).

### Na odobrenju

Zahtevi koji čekaju **tvoju** odluku, kao kartice. Za svaki vidiš ko, period, broj radnih dana, vrstu, napomenu i **kada se automatski odobrava**.

- **Odobri**: zahtev postaje **Odobreno**, radnik dobija obaveštenje, a njegova zamena preuzima klijente za taj period.
- **Odbij**: upisuješ razlog, koji radnik dobija.

Na telefonu prevuci karticu ulevo za **Odobri** / **Odbij**.

> **Pažnja:** ako ne odgovoriš u roku (podrazumevano 48 radnih sati, a najkasnije dan pre početka odsustva), zahtev se **automatski odobrava**. 24 sata pre isteka roka dobijaš podsetnik.

[SLIKA: tab Na odobrenju sa karticama zahteva]

### Pregled

Mesečna tabela: radnici tima × dani u mesecu. Obojeno polje je odsustvo (boja po vrsti; prugasto = na odobrenju), a vikendi i praznici su prigušeni. Strelicama menjaš mesec. Ovde najlakše vidiš da li će previše ljudi biti odsutno istovremeno.

### Evidencija

Za izabranu godinu: koliko je dana svaki član bio odsutan, po vrsti, ukupno, i koliko mu je ostalo godišnjeg.

### Upis odsustva za člana tima

1. **Odsustva → Zahtev za odsustvo**.
2. U polju **Radnik** izaberi člana tima.
3. Izaberi vrstu, period, po želji **Pola dana** i napomenu.
4. Klikni **Sačuvaj**.

Odsustvo koje upiše šef je **odmah odobreno**.

> **Važno:** tvoje sopstveno odsustvo ne odobravaš ti, nego administrator.

### Doznake članova tima

Kod člana tima vidiš samo dokumente vezane za odsustvo (npr. doznaku za bolovanje). Ostale lične dokumente (ugovore, rešenja) vidi samo administrator.

## Kalendar za tim

U **Kalendaru** gore biraš **Moji** ili **Moj tim**. **Moj tim** prikazuje zadatke i odsustva celog tima, a filter **radnik** sužava na jednog člana. Zadatak koji smeš da menjaš prevlačiš na drugi dan da mu promeniš rok.

Ako je administrator uključio „Šefovi dobijaju i odsustva svog tima“, a ti si povezao Google kalendar, odobrena odsustva članova tima stižu i u tvoj Google kalendar „KontIT“.

## Moja lista

Imaš i **Moju listu**: ličnu listu obaveza i podsetnika (opis je u uputstvu za radnika). Vidiš samo svoju, a tvoju ne vidi niko, ni administrator. Nije zadatak, pa tvoj tim ne vidi šta je u njoj.
## Česta pitanja

**Zašto ne vidim zadatak koji je napravio kolega iz drugog tima?**
Šef vidi samo zadatke na kojima rade članovi njegovog tima. Ako član tvog tima nije učesnik, zadatak ne vidiš.

**Član tima je na godišnjem, ko radi njegove zadatke?**
Backup (zamena) na njegovim klijentima. Njegovi otvoreni zadaci za te klijente se pojavljuju kod zamene sa oznakom **PREUZETO**, a novi zadaci stižu direktno zameni. Zato je važno da svaki klijent ima backup.

**Mogu li da prebacim zadatak sa jednog radnika na drugog?**
Da: **Izmeni** na zadatku, promeni štiklirane **Zaduženi** i sačuvaj.

**Mogu li da obrišem zadatak?**
Ne, zadatak se **otkazuje** (**Otkaži zadatak**, uz razlog). Tako ostaje trag.

**Zašto ne mogu da dodelim klijenta nekome?**
Nude se samo članovi tvog tima. Za ljude iz drugih timova javi se administratoru.

## Česte greške

- **„Glavni zaduženi i backup moraju biti različite osobe“**: izaberi drugog čoveka za backup.
- **„Unesi razlog vraćanja na doradu“**: razlog je obavezan da bi radnik znao šta da ispravi.
- **Zaboravljen zahtev za odsustvo** se sam odobri posle roka. Prati brojač pored **Odsustva** u meniju.
- **Promena glavnog zaduženog ne prebacuje otvorene zadatke**: posle promene proveri otvorene zadatke klijenta i, ako treba, prebaci ih ručno.
