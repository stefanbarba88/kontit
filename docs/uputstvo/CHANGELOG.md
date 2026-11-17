# Šta je novo u KontIT aplikaciji

_Ažurirano: 16.11.2026. · Verzija 2.0_

Ovaj tekst možeš da pošalješ zaposlenima. Svako čita samo svoj deo; šefovi čitaju i deo za radnike, a admini sve. Potpuna uputstva su u aplikaciji, u meniju **Uputstvo**.

---

## Za sve (radnike, šefove i admine)

- **Ispravljeno: zahtev za odsustvo iz Kalendara**: klik na dan u Kalendaru i **Zahtev za odsustvo od ovog dana** je prikazivao grešku („Input value type is invalid“). Forma sada čeka da izabereš vrstu odsustva, a prazni filteri (npr. **Svi timovi**, **Sve pozicije**) više ne prave grešku.

- **Kašnjenje roka na neradni dan**: zadatak kome je rok subota, nedelja, praznik ili dan kolektivnog odmora ne kasni dok ne prođe prvi radni dan posle roka (matrica, lista, brojači, obaveštenja). Rok na radni dan kasni kao i do sada.

- **Vreme svuda 24h i na pola sata**: kad biraš vreme (rok i **Obavesti me** u Podsetniku) biraš sa liste na svakih 30 minuta (00:00, 00:30 ... 23:30), bez AM/PM. Uz **Obavesti me** su nove brze opcije **Za 15 min** i **Za 1 sat**. Vreme koje je ranije upisano (npr. 14:15) se ne menja.

**Podsetnik** (nova lična lista)
- Na **Početnoj** je opet kartica Podsetnik: desno od kartica Zadaci (na telefonu ispod njih), sa najviše 6 aktivnih stavki, dugmetom **Dodaj** i linkom **Otvori sve**. Blok Zadaci zauzima levu polovinu, a Podsetnik desnu (na telefonu je Podsetnik ispod). Zadaci su sada 4 kartice u matrici 2x2: Otvoreni zadaci, Kasne, Ističu u 3 dana i Završeno ovog meseca. Kartice „Završeno na vreme“, „Vraćeno na doradu“ i „Klijenti (glavni/backup)“ više nisu na Početnoj.
- Ispravljeno: mala tačka na ikonici sveske ponekad je nestajala posle osvežavanja stranice, iako imaš otvorene stavke.
- U meniju je **Podsetnik**, a u gornjoj traci ikonica sveske koja pokazuje da li imaš otvorene stavke (mala tačka) ili nešto što kasni ili je za danas (crveni broj).
- Stavku dodaš sa **+ Dodaj** ili tasterom **n**: upišeš naslov, pritisneš Enter, i to je to. Po želji dodaš rok, **Obavesti me** (da te aplikacija podseti u određeno vreme), ponavljanje (svaki dan, radni dan, nedelju ili mesec) i klijenta.
- Na zadatku i na kartici klijenta je dugme **Dodaj u podsetnik**.
- Završene stavke se brišu posle 3 dana.
- **Podsetnik vidiš samo ti.** Ni šef, ni administrator, ni superadmin ne mogu da ga otvore.

**Pregled poslova po klijentima** (nova tabela)
- Na Početnoj, ispod kartica Zadaci, i u meniju **Pregled poslova**: za svakog klijenta vidiš koliko je zadataka urađeno po paketu usluga u izabranom mesecu.
- Boje: **crveno** kasni, **žuto** ističe u naredna 3 radna dana, **zeleno** je sve gotovo, **sivo** je u toku.
- Klik na ćeliju otvara spisak zadataka, a filteri (klijent, zaposleni, paketi, mesec, samo nezavršeno, samo problematični) se pamte. Možeš i da izvezeš tabelu.

**Zadaci**
- Lista zadataka ima nove filtere: pretraga po nazivu (bez razlike u kvačicama), **Posao**, **Nastanak** (automatski ili ručni) i **Prikaži završene i otkazane**. **Poništi** vraća sve na početno.
- Isti filteri i izvoz postoje u kartici **Zadaci** na kartici klijenta i na strani **Moji zadaci** (Moj profil).
- Rok zadatka koji si sam napravio možeš da promeniš (**Promeni rok** u kartici **Rok i raspored**).

**Pozicije i provera zadataka**
- Ko proverava tvoj zadatak zavisi od pozicije: Entry level i Junior prolaze proveru šefa, Samostalni knjigovođa i Šef ne. Neki poslovi traže **posebnu potvrdu** određene osobe. Na zadatku piše značka sa tim ko proverava.

**Obaveštenja i jezik aplikacije**
- „Obaveštenja“ je zvonce i lista obaveštenja iz aplikacije; **Obavesti me** je tvoj lični podsetnik.
- **Odustani** zatvara prozor bez čuvanja, a **Otkaži** postoji samo za zadatak i zahtev za odsustvo. Glavno dugme je uvek prvo.

**Ko može da gleda moj nalog**
- Administrator sada može da otvori aplikaciju „kao ti“, radi podrške. Uvek se vidi žuti baner „Gledaš kao“. Podrazumevano je samo pregled.
- Nikad u tvoje ime ne može da promeni lozinku ili mejl, da potvrdi ili odobri nešto, da napiše komentar ni da menja dokumente, Google kalendar i ICS link. Tvoj Podsetnik je skriven.
- Ko je i kada pristupao tvom nalogu vidiš u **Moj profil → Pristupi mom nalogu**.

---

## Dodatno za šefove

- Početna: **Zadaci**, **Pregled poslova po klijentima**, **Naredna nedelja**, pa blok **Moj tim**.
- Na kartici klijenta je tab **Zadaci** (sa brojem otvorenih zadataka i crvenom tačkom za kašnjenje), a pored svakog člana tima dugme **Zadaci** sa njegovom listom i izvozom.
- U formi zadatka za člana tima imaš polje **Provera**: automatski po poziciji, bez provere ili posebna potvrda. Rok jednog zadatka možeš da promeniš i prevlačenjem u **Kalendaru**.
- U **Pregledu poslova** vidiš klijente svog tima, a **Otvori listu zadataka** otvara ceo tim.

---

## Dodatno za admine

- **Novi rasporedi rokova**: uz pomeranje na prvi sledeći ili prethodni radni dan, sada možeš da izabereš **Tačno taj dan (i ako je neradni)**, a novi tipovi **N-ti radni dan** (npr. 20. radni dan u mesecu) i **Radni dan od kraja** (npr. poslednji radni dan) računaju samo radne dane. Pri promeni roka zadatka na neradni dan za buduće periode biraš da li se rok pomera ili ostaje tačno tog dana. Postojeći poslovi se ne menjaju.
- **Pozicije** (**Administracija → Pozicije**): nivoi zaposlenih sa kapacitetom i kvačicom **Zadaci ove pozicije traže proveru**. Pozicija ne daje prava pristupa.
- **Posebne potvrde** (poseban meni): zadaci koje potvrđuje baš određeni admin; brojač u meniju, tabovi i dugmad **Potvrdi** i **Vrati na doradu**. Pri deaktivaciji jedinog proveravača aplikacija te upozori.
- **Raspored poslova** po klijentu i po zadatku: za jednog klijenta možeš da prilagodiš raspored posla (**Prilagodi za ovog klijenta**), a rok jednog zadatka da promeniš i da važi za buduće periode.
- Početna je preuređena: Zadaci, Pregled poslova, Posebne potvrde, Naredna nedelja, pa **Svi timovi** i **Cela firma**.
- **Prikaži kao korisnik**: u listi korisnika (⋯) i na profilu korisnika. Gledaš šefove i radnike (superadmin i admine), uz baner i izlaz **Izađi**. **Samo pregled** je podrazumevan, a **Radi kao korisnik** (uz potvrdu) dozvoljava obične radnje u njegovo ime. Potvrde, odobravanja, komentari, lozinka, mejl, role, dokumenti, Google i ICS su uvek zabranjeni, a Podsetnik je skriven. Posle 60 minuta bez aktivnosti izlaz je automatski.

**Samo superadmin:** nova strana **Administracija → Sigurnost** pokazuje svako gledanje „kao korisnik“: ko je gledao koga, kada, koliko dugo, iz koje IP adrese i da li je radio izmene.
