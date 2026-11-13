# Uputstvo za admina

Ovo uputstvo opisuje upravljanje aplikacijom: korisnike, timove, klijente, katalog poslova, pakete, odsustva, dokumente i finansije. Admin može i sam da radi na klijentima i zadacima. Za to važe **Uputstvo za radnika** i **Uputstvo za šefa** (tabovi na vrhu strane), samo što admin vidi **celu firmu**, a ne samo jedan tim.

## Šta je admin

Admin upravlja celom firmom u aplikaciji. Admin:

- pravi i menja **korisnike** (radnike i šefove) i **timove**,
- pravi i menja **klijente**, dodeljuje im glavnog radnika i zamenu,
- vodi **katalog poslova** i **pakete usluga** i ugovara ih sa klijentima, **sa cenama**,
- vidi **sve zadatke** i može da potvrdi bilo koji,
- vidi **sva odsustva**, odobrava ih i može da poništi automatsko odobrenje,
- vodi **neradne dane**, **vrste odsustva**, **kategorije dokumenata** i ostala podešavanja,
- vidi **Finansije**: prihod, opterećenje, cenu sata po klijentu, spisak za naplatu.

Jedino što admin ne može: da pravi ili menja **druge admine i superadmine**. To radi superadmin (vidi poglavlje „Samo za superadmina“).

## Prvo prijavljivanje i prvo podešavanje

Prijava i profil su isti kao za radnika. Kad aplikacija tek počinje da se koristi, idi ovim redom, jer svaki korak koristi prethodni:

1. **Neradni dani**: učitaj praznike Srbije za tekuću i narednu godinu. Od toga zavise rokovi i broj dana odsustva.
2. **Vrste odsustva**: proveri vrste i podešavanja (broj dana godišnjeg odmora, rok za automatsko odobravanje).
3. **Kategorije dokumenata**: proveri spisak (Ugovor, Rešenje, Potvrda, Izvod, Doznaka, Ostalo).
4. **Korisnici**: napravi naloge za sve zaposlene. Šefovima daj rolu **Šef**.
5. **Timovi**: napravi timove, postavi šefa i dodaj članove.
6. **Katalog poslova**: kategorije, pa poslovi sa rasporedom i checklistom.
7. **Paketi usluga**: složi poslove u pakete.
8. **Klijenti**: unesi klijente (ručno ili **Uvoz iz NBS**) i dodeli im glavnog radnika i backup.
9. **Ugovorene usluge** na svakom klijentu: dodaj pakete i pojedinačne poslove sa cenama.
10. **Generisanje zadataka**: proveri pregled narednih 30 dana. Od sutra ujutru zadaci stižu sami.

[SLIKA: meni admina sa grupom Administracija]

## Meni admina

| Grupa | Stavka | Šta je tu |
|---|---|---|
| Rad | **Početna**, **Moji klijenti**, **Odsustva**, **Zadaci**, **Kalendar** | kao kod radnika, ali za celu firmu gde ima smisla |
| Klijenti | **Klijenti** | svi klijenti, novi, uvoz iz NBS |
| | **Timovi** | svi timovi, šefovi, članovi |
| Administracija | **Katalog poslova** | poslovi, kategorije poslova |
| | **Paketi usluga** | paketi poslova |
| | **Generisanje zadataka** | pregled i ručno pokretanje |
| | **Neradni dani** | praznici, kolektivni odmor, neradni dani u nedelji |
| | **Vrste odsustva** | vrste i podešavanja odsustva, zadataka i Google kalendara |
| | **Kategorije dokumenata** | kategorije za dokumente zaposlenih |
| | **Finansije** | prihod, opterećenje, naplata |
| | **Korisnici** | nalozi zaposlenih |
| Pomoć | **Uputstvo** | ovo uputstvo |

## Početna strana admina

Kartice na vrhu su jedna ispod druge, preko cele širine: **Posebne potvrde** (broj i dugme **Pregledaj**), **Naredna nedelja** i **Zadaci**. Ispod njih admin vidi:

- **Svi timovi**: isto što šef vidi za svoj tim (opterećenje, čeka potvrdu, čeka klijenta, učinak), ali za sve,
- **Cela firma**:
  - **Po timovima**: poređenje timova,
  - **Završeni zadaci po mesecima**,
  - **Klijenti po timu** i **Klijenti po radniku**,
  - **Klijenti bez backup radnika**: rizik kad je glavni odsutan, pa ove klijente sredi prve,
  - **Klijenti bez zadatka u 30 dana**: možda nisu ugovorene usluge ili je klijent neaktivan,
  - **Najaktivniji** i **Najmanje aktivni** korisnici (po istoriji izmena).

[SLIKA: početna strana admina, blok Cela firma]

## Korisnici

### Novi korisnik

1. **Korisnici → Novi korisnik**.
2. **Osnovni podaci**: **Ime**, **Prezime**, **Email (login)**, **Prava pristupa** i početna lozinka (dva puta).
3. **Kontakt**: telefon, adresa (opciono).
4. **Posao**: **Pozicija** (izbor sa liste, npr. Junior), **Datum zaposlenja**, **Interna beleška** (vidi samo admin).
5. Klikni **Sačuvaj**.
6. Javi korisniku email i početnu lozinku i reci mu da je promeni posle prve prijave.

Role koje admin daje:

| Prava pristupa | Za koga |
|---|---|
| **Radnik** | knjigovođa koji radi na svojim klijentima |
| **Šef** | vodi tim. Samo korisnik sa rolom Šef može biti postavljen za šefa tima |

[SLIKA: forma Novi korisnik]

### Pretraga i pregled

Na **Korisnici** pretražuješ po imenu, prezimenu ili emailu, i filtriraš po pravima pristupa, poziciji i statusu (**Aktivni**, **Neaktivni**). Klik na korisnika otvara njegovu stranu:

- **Podaci** i **Poslednja prijava**,
- **Klijenti**: gde je glavni i gde je backup,
- **Tim**: trenutni tim i **Istorija članstva**,
- **Odsustva**: stanje godišnjeg odmora, uz izmenu prava (vidi ispod),
- **Zadaci**: tab pored **Pregled** (vidi „Zadaci korisnika“),
- **Aktivnost**: sve što je korisnik menjao,
- dugmad **Dokumenti**, **Izmeni**, **Deaktiviraj**.

### Zadaci korisnika

Strana korisnika ima dva taba: **Pregled** (podaci) i **Zadaci** (adresa `/users/<broj>/zadaci`). Zaglavlje sa imenom, pozicijom i timom je isto na oba.

- **Zaduženi** je zaključan na tog korisnika, a filter **Uloga** bira **Zadužen** (podrazumevano), **Kreirao** ili **Zadužen ili kreirao**.
- Na vrhu su brojači: **otvoreno**, **kasni**, **na proveri**, **završeno ovog meseca**. Ostali filteri (naziv, posao, nastanak, klijent, status, prioritet, rok, **Prikaži završene i otkazane**) rade kao na **Zadaci**.
- **Izvoz** (Excel, CSV, PDF) izvozi tačno prikazanu listu.
- Admin vidi zadatke svakog korisnika. Šef vidi samo članove svog tima, a radnik samo svoju stranu (dugme **Moji zadaci** na **Moj profil**); ostali dobijaju poruku da nemaju pravo.

### Izmena, prava pristupa i pozicija

**Izmeni** otvara istu formu. Lozinku ostavi praznu ako je ne menjaš.

- **Prava pristupa** određuju šta korisnik sme u aplikaciji (Radnik, Šef, Admin). Promena traži potvrdu.
- **Pozicija** je samo nivo zaposlenog (npr. Junior, Samostalni knjigovođa) i **ne daje nikakva prava**. Kad izabereš poziciju koja ima predlog prava, ispod se pojavi „Predlažemo prava pristupa: Šef“ sa dugmetom **Primeni**. Dugme samo postavlja polje, a ti i dalje čuvaš formu.
- Promena pozicije traži potvrdu uz neobaveznu napomenu. Upisuje se u **Istoriju pozicija** na strani korisnika (npr. „Junior → Samostalni knjigovođa, 1.3.2026.“).

### Godišnji odmor korisnika

Na strani korisnika, u kartici **Odsustva**, menjaš **Pravo (dana)** i **Preneto** za tekuću godinu i klikneš **Sačuvaj**. Ispod piše **predlog prenosa** iz prošle godine (neiskorišćeni dani). Podrazumevan broj dana za sve podešava se u **Vrste odsustva → Podešavanja**.

### Deaktivacija (umesto brisanja)

Korisnici se ne brišu, nego **deaktiviraju**. Deaktiviran korisnik ne može da se prijavi ni da dobija zadatke, a sva njegova istorija ostaje.

1. Otvori korisnika i klikni **Deaktiviraj**.
2. Ukucaj puno ime korisnika da potvrdiš (zaštita od slučajnog klika).

Aplikacija neće dozvoliti deaktivaciju dok korisnik:

- **vodi tim**: prvo postavi drugog šefa,
- je **glavni ili backup na klijentima**: prvo prebaci klijente na druge radnike,
- ima **zadatke u radu**: prebaci ih na nekog drugog ili ih otkaži.

Sopstveni nalog ne možeš da deaktiviraš. Ponovo aktiviraš dugmetom **Aktiviraj**.

## Pozicije

**Administracija → Pozicije** je šifarnik nivoa zaposlenih. Pozicija **ne daje prava pristupa**; prava se zadaju posebno u profilu korisnika.

- **Nova pozicija** (modal): naziv, opis, boja, **Mesečni kapacitet (sati)** i **Predlog prava pristupa** (samo predlog u formi korisnika).
- Redosled po senioritetu menjaš prevlačenjem (prva je najniža).
- **Brisanje** je moguće samo za poziciju koja nikad nije korišćena. Korišćena (ima korisnike ili se pominje u istoriji napredovanja) može samo da se **deaktivira**: više se ne nudi u formama, a korisnici koji je imaju je zadržavaju.
- **Zadaci ove pozicije traže proveru**: kvačica u formi pozicije. Ako je uključena, završen zadatak radnika na toj poziciji potvrđuje šef tima (ili admin); inače se zadatak završava odmah.
- Kapacitet za analitiku opterećenja: prvo kapacitet samog radnika (Finansije → Podešavanja), pa kapacitet njegove pozicije, pa podrazumevani.
- Listu korisnika možeš da filtriraš po poziciji, a izvoz poštuje isti filter.

## Timovi

### Novi tim

1. **Timovi → Novi tim**.
2. Upiši **Naziv tima**, po želji **Opis** i **Boju** (koristi se za oznaku tima).
3. Izaberi **Šefa tima** (nude se samo korisnici sa rolom Šef).
4. Klikni **Sačuvaj**.

### Članovi i premeštanje

Otvori tim (klik na naziv). Dole, u **Radnik**, izaberi osobu i klikni **Dodaj / premesti**.

- Radnik je u **jednom timu u isto vreme**. Ako je bio u drugom timu, biće **premešten**, uz potvrdu.
- **Klijenti i otvoreni zadaci idu sa radnikom**: novi šef ih odmah vidi, stari više ne.
- Ikonica čoveka sa minusom uklanja radnika iz tima (ostaje u sistemu, bez tima).
- **Bivši članovi** i **Istorija šefova** se čuvaju, pa statistika po timovima ostaje tačna.

### Promena šefa

**Izmeni** na timu, izaberi drugog **Šefa tima** i sačuvaj. Novi šef odmah vidi klijente i zadatke tima. Promena se upisuje u **Istoriju šefova**.

### Brisanje tima

Tim mora prvo biti prazan (premesti ili ukloni članove). Zatim **Obriši** i ukucaj naziv tima da potvrdiš.

[SLIKA: strana tima sa članovima i poljem Dodaj / premesti]

## Klijenti

### Novi klijent, ručno

1. **Klijenti → Novi klijent**.
2. Upiši **PIB** i klikni **Učitaj iz NBS**: popuniće se pun naziv, matični broj, adresa i ostalo (ako su NBS pristupni podaci podešeni).
3. Proveri **Pun naziv** (zvanični, iz registra) i upiši **Kraći naziv** (do 40 znakova, npr. „Mlekara Ivanjica“). Kraći naziv se koristi **svuda** u aplikaciji: u listama, naslovima zadataka i kalendaru.
4. **Klijent od**: od kada ga vodimo. **PDV obveznik**: štikliraj ako jeste.
5. **Zaduženi radnici**: **Glavni zaduženi** (obavezno) i **Backup (zamena)**. Moraju biti različite osobe.
6. **Kontakt**: kontakt osoba, telefon, email.
7. Klikni **Sačuvaj**.

[SLIKA: forma Novi klijent sa dugmetom Učitaj iz NBS]

> **Savet:** svaki klijent treba da ima backup. Bez njega niko ne preuzima klijenta dok je glavni na odsustvu. Spisak klijenata bez backup-a je na Početnoj.

### Uvoz više klijenata iz NBS-a

1. **Klijenti → Uvoz iz NBS**.
2. U **PIB-ovi** nalepi jedan ili više PIB-ova (od 9 cifara), razdvojenih novim redom, razmakom ili zarezom.
3. Izaberi **Glavnog zaduženog** i **Backup** za sve uvezene klijente.
4. Klikni **Uvezi**.
5. U **Rezultat** vidiš za svaki PIB: **Uvezen**, **Već postoji** ili **Greška**.

Posle uvoza otvori svakog klijenta i proveri **Kraći naziv**, jer se pri uvozu pravi od punog naziva.

### Izmena, osvežavanje iz NBS-a, deaktivacija

Na kartici klijenta:

- **Izmeni**: svi podaci i zaduženi radnici.
- **Osveži iz NBS** (dole u kartici **Podaci**): ponovo povlači naziv, adresu, matični broj i ostale podatke iz NBS-a. Telefon i email se ne diraju.
- **Dodela**: brza promena glavnog i backup radnika. Otvoreni zadaci ostaju kod dosadašnjeg radnika, a novi idu novom.
- **Analitika**: finansijska slika klijenta (vidi „Finansije“).
- **Deaktiviraj**: klijent se više ne nudi za nove zadatke, a istorija ostaje. Traži da ukucaš kraći naziv. **Aktiviraj** ga vraća.

Klijenti se ne brišu, samo deaktiviraju.

### Zadaci klijenta

Na kartici klijenta je tab **Zadaci** (adresa `/clients/<broj>/zadaci`, pa je možeš poslati kolegi sa već izabranim filterima). Pored naziva taba je broj otvorenih zadataka, a **crvena tačka** znači da neki kasne.

- Isti filteri kao na **Zadaci**: **Naziv** (bez razlike u kvačicama), **Posao**, **Nastanak** (automatski ili ručni), **Zaduženi**, status, prioritet, rok i **Prikaži završene i otkazane**. Klijent je već izabran, pa tog filtera nema.
- **Posao** nudi samo poslove koje klijent ima ili je imao, a **Zaduženi** samo ljude koji su radili na njegovim zadacima.
- Dugme **Izvoz** (Excel, CSV, PDF) izvozi tačno prikazanu listu. **Novi zadatak** otvara formu sa zaključanim klijentom i posle čuvanja te vraća na ovaj tab.

## Katalog poslova

**Posao** je šablon nečega što se radi za klijente (npr. „Mesečna PDV prijava“, „Obračun plata“, „Knjiženje izvoda“). Iz posla nastaju zadaci. Katalog nema cene, jer se cena dogovara sa svakim klijentom posebno.

### Kategorije poslova

**Katalog poslova → Kategorije**. Kategorija ima **Naziv**, **Boju** i **Ikonicu** (npr. PDV, Plate, Knjiženje).

- **Nova kategorija** otvara prozor za unos.
- Redosled menjaš **prevlačenjem reda** (ručica sa leve strane). Isti redosled važi i u katalogu i u izboru poslova.
- Kategorija bez poslova se može obrisati. Kategorija sa poslovima se samo **deaktivira** (ikonica oka).

### Novi posao

1. **Katalog poslova → Novi posao**.
2. **Podaci o poslu**: **Naziv posla**, **Kategorija**, **Opis** (kopira se u svaki zadatak).
3. **Raspored izvršavanja**: kada nastaju zadaci (vidi ispod).
4. **Checklista**: koraci posla. Upiši stavku i pritisni Enter za sledeću. Redosled menjaš prevlačenjem, a Backspace u praznom redu ga briše. Checklista se kopira u svaki zadatak.
5. **Zadatak i cena**: **Podrazumevani prioritet**, **Režim završetka**, **Procenjeno trajanje (min)** (koristi se za opterećenje i cenu sata), **Jedinica cene** (Po izvršenju, Mesečno, Godišnje), **Predložena cena** (samo predlog), **Aktivan**.
6. Klikni **Sačuvaj**.

### Raspored izvršavanja

Prvo izaberi tip, pa se pojave samo polja tog tipa:

| Tip | Kada nastaje zadatak | Primer |
|---|---|---|
| **Po potrebi** | nikad sam, samo ručno | prijava ili odjava radnika |
| **Dnevno** | svaki (radni) dan | retko; za izvode je bolji nedeljni posao |
| **Nedeljno** | izabranog **Dana u nedelji** | nedeljno knjiženje izvoda petkom |
| **Mesečno** | **Dan u mesecu** | PDV do 15. u mesecu |
| **Kvartalno** | **Dan u mesecu** u izabranom **Mesecu posle kvartala** | kvartalni PDV |
| **Godišnje** | izabrani **Mesec** i **Dan u mesecu** | završni račun |

Ostala polja:

- **Na svakih (1 = svaki)**: npr. 2 = svaki drugi mesec.
- **Zadatak nastaje dana pre roka**: koliko dana ranije zadatak stiže radniku (npr. 10).
- **Ako rok padne na neradni dan**: **Prvi sledeći radni dan** ili **Prethodni radni dan**.
- **Samo radni dani** (za dnevni raspored).

Ispod polja odmah piše opis rasporeda rečima i **narednih 5 rokova**. Rok pomeren zbog praznika ima ikonicu strelica.

[SLIKA: forma posla, Raspored izvršavanja sa pregledom narednih 5 rokova]

## Paketi usluga

**Paket** je skup poslova koji se ugovara zajedno (npr. „Paket Preduzetnik“ = knjiženje izvoda + PDV + plate).

1. **Paketi usluga → Novi paket**.
2. Upiši **Naziv paketa**, **Predloženu mesečnu cenu** (samo predlog pri dodeli) i **Opis**.
3. U **Poslovi u paketu** štikliraj poslove: levo su poslovi po kategorijama sa pretragom, desno izabrani.
4. **Aktivan (može da se dodeli klijentima)**.
5. Klikni **Sačuvaj**.

Kad menjaš paket koji klijenti već imaju, pojavi se opcija **Primeni na N klijenata koji imaju ovaj paket (dodaj nove poslove)**. Ako je štikliraš, novi poslovi se odmah dodaju svim tim klijentima. Poslovi izbačeni iz paketa ostaju kod klijenata dok ih ne isključiš.

## Ugovorene usluge klijenta

Na kartici klijenta, tab **Ugovorene usluge**: šta se radi za klijenta, po kom rasporedu, ko radi, kada je sledeći zadatak i po kojoj ceni.

### Dodaj paket

1. **Dodaj paket**.
2. Izaberi **Paket**. Odmah se prikažu svi poslovi paketa, štiklirani.
3. **Odštikliraj** poslove koji ne trebaju ovom klijentu i po želji upiši razlog.
   Ispod svakog posla piše **Podrazumevano: …** (raspored iz kataloga). Ako ovaj klijent treba drugačiji rok, klikni **Prilagodi za ovog klijenta**, izaberi raspored i **Primeni**. U redu se tada vidi **Prilagođeno: …** i link **Vrati na podrazumevano**. Prilagođavanje važi samo za ovog klijenta, a paket za druge klijente se ne menja.
4. Upiši **Mesečnu cenu paketa za ovog klijenta**, po želji **Popust (%)**, **Početak** i **Napomenu**.
5. Klikni **Dodaj paket**.

[SLIKA: prozor Dodaj paket sa poslovima paketa]

### Dodaj pojedinačan posao

1. **Dodaj pojedinačan posao**.
2. Štikliraj jedan ili više poslova. Za svaki upiši **Cenu**, po želji drugog **zaduženog** (podrazumevano je glavni radnik) i **Napomenu**. Isto kao kod paketa, **Prilagodi za ovog klijenta** menja raspored samo za ovog klijenta.
3. Izaberi **Početak** i klikni **Dodaj**.

### Meni ⋯ pored posla

- **Izmeni**: cena, napomena, **Zaduženi** za taj posao, procenjeno trajanje, početak i kraj. Na toj strani vidiš i raspored: podrazumevani i, ako postoji, prilagođeni.
- **Prilagodi za ovog klijenta** / **Izmeni raspored za klijenta**: drugačiji rok samo za ovog klijenta. Pre primene piše koliko otvorenih zadataka menja rok (npr. „Menja rok na 3 otvorena zadatka“) i traži potvrdu. Zadaci u radu, na proveri i završeni se ne diraju, kao ni zadaci kojima je rok ručno promenjen.
- **Vrati na podrazumevano**: klijent opet prati raspored iz kataloga (i njegove buduće izmene), uz isto pitanje o zadacima.

Kod posla koji je prilagođen stoji bedž **Prilagođeno**, a tooltip pokazuje podrazumevani raspored. Lista uvek prikazuje raspored koji stvarno važi.

> **Kad promeniš raspored posla u katalogu:** rok se preračunava samo za klijente koji nisu prilagođeni i samo za otvorene zadatke (status Novo). Posle čuvanja piše na koliko zadataka je rok promenjen. Ako se promeni vrsta rasporeda (npr. mesečno u kvartalno), stari otvoreni zadaci ostaju na starom roku i dobiješ upozorenje da ih sam otkažeš ili završiš; novi zadaci se prave po novom rasporedu.
- **Kreiraj zadatak sada**: odmah pravi zadatak iz tog posla (npr. za posao „Po potrebi“).
- **Isključi** (posao iz paketa): zadaci se više ne prave, a otvoreni se otkazuju. Traži razlog. Isključen posao je precrtan i može da se **Uključi**.
- **Završi (zadaci ostaju)** ili **Završi i otkaži zadatke** (pojedinačan posao): posao se završava danas.

### Rok jednog zadatka

Na strani zadatka, kartica **Rok i raspored**:

- **Promeni rok** menja rok samo tom zadatku. Pored roka se pojavi znak olovke (rok ručno promenjen, tooltip pokazuje podrazumevani). Raspored i generator taj rok više ne diraju. Isto važi kad rok promeniš u formi zadatka ili prevlačenjem u kalendaru.
- Kvačica **Primeni i na buduće periode za ovog klijenta** (samo admin) upisuje prilagođen raspored iz novog roka: menja se samo dan (u mesecu ili u nedelji), a „zadatak nastaje dana pre roka“ i pomeranje zbog neradnog dana ostaju kao u rasporedu posla. Pre čuvanja piše šta tačno postaje („Od sledećeg perioda: svakog 6. u mesecu“) i koliko drugih otvorenih zadataka menja rok. Za 29., 30., 31. ili poslednji dan meseca možeš da izabereš **Poslednji dan u mesecu**; inače se u kraćim mesecima rok pomera na poslednji dan.
- **Vrati na podrazumevani rok** vraća rok po rasporedu.

### Meni ⋯ pored paketa

- **Promeni cenu**: nova mesečna cena i **Važi od**. Svaka promena se čuva u istoriji cena.
- **Ukloni paket (zadaci ostaju)** ili **Ukloni paket i otkaži zadatke**. Traži razlog.

### Predlozi radnika

Radnik ili šef može da predloži posao za klijenta. Predlog se vidi u okviru **Predlozi koji čekaju odobrenje**. Upiši cenu i klikni **Odobri**. Od tada se zadaci prave po rasporedu.

Dole je **Ukupno mesečno** (paketi + mesečni pojedinačni poslovi), a u **Završene usluge** je istorija ugovorenog.

## Generisanje zadataka

Zadaci iz ugovorenih poslova nastaju **sami, svako jutro u 06:00**.

- Zadatak nastaje kad stigne dan „rok minus broj dana pre roka“ iz rasporeda.
- Naslov je „posao — kraći naziv klijenta — period“, checklista se kopira iz posla.
- Zadatak dobija **zaduženi za taj posao**, a ako ga nema, **glavni radnik klijenta**.
- Ako je glavni radnik tog dana na odobrenom odsustvu, zadatak dobija **backup**, sa oznakom **PREUZETO**.
- Za isti posao i period nikad ne nastaju dva zadatka.

Strana **Generisanje zadataka** pokazuje **Pregled narednih 30 dana**: posao, klijent, period, rok, kada nastaje, ko je zadužen i status (**Napravljen**, **Za generisanje**, **Zakazan**). Crveno „nema zaduženog“ znači da klijent nema glavnog radnika, pa zadatak neće nastati.

Dugme **Generiši sada (N)** odmah pravi zadatke čiji je dan stigao (npr. posle novog ugovora, da se ne čeka jutro).

[SLIKA: Generisanje zadataka, pregled narednih 30 dana]

## Zadaci (admin)

Admin vidi **sve zadatke** (filter **Svi zadaci u mom opsegu**) i ima tab **Čeka moju potvrdu**. Admin potvrđuje:

- zadatke radnika koji **nemaju šefa** (nisu ni u jednom timu),
- zadatke na kojima je **šef sam učesnik**,
- bilo koji drugi zadatak, kad je potrebno.

Zadatak možeš da napraviš **bilo kome**, ali za klijenta se nude samo ljudi raspoređeni na tog klijenta.

## Neradni dani

**Neradni dani** pokazuje kalendar godine (12 meseci) sa obojenim praznicima i kolektivnim odmorom, i listu ispod. Strelicama menjaš godinu.

- **Učitaj praznike Srbije za GODINU**: predlog zakonskih praznika, sa pravoslavnim Uskrsom. Pregledaj listu, štikliraj šta čuvaš i klikni **Sačuvaj izabrane**. Praznik koji padne u nedelju predlaže slobodan prvi naredni radni dan. To štikliraj samo ako se slažeš.
- **Dodaj**: **Vrsta** (**Praznik, svake godine**, **Praznik, ove godine**, **Kolektivni odmor**), **Naziv**, **Od**, **Do (uključujući)**, **Napomena**.
- **Neradni dani u nedelji** (desno): podrazumevano subota i nedelja, važi za celu firmu.

Neradni dani pomeraju rokove zadataka i ne računaju se u dane odsustva.

> **Pažnja:** praznike učitaj **pre nove godine**, inače rokovi u januaru neće znati za praznike.

## Vrste odsustva i podešavanja

### Vrste odsustva

**Vrste odsustva → Nova vrsta** ili olovka za izmenu:

| Polje | Značenje |
|---|---|
| **Naziv**, **Boja**, **Ikonica** | kako se vrsta prikazuje u listama i kalendaru |
| **Traži odobrenje** | bez odobrenja = samo evidencija, odmah važi (npr. bolovanje) |
| **Troši dane godišnjeg odmora** | samo za godišnji odmor |
| **Traži dokument (npr. doznaka)** | radnik dobija obaveštenje ako ga ne doda u roku od 3 dana |
| **Najviše dana godišnje** | prazno = bez ograničenja |
| **Redosled**, **Aktivna** | vrsta koja se više ne koristi se deaktivira, a stara odsustva je zadržavaju |

### Podešavanja (desno na istoj strani)

- **Podrazumevan godišnji odmor (dana)**: za korisnike bez svog broja.
- **Rok za automatsko odobravanje odsustva (radnih sati)**: podrazumevano 48. Računaju se samo sati radnih dana, a rok je najkasnije dan pre početka odsustva.
- **Automatski potvrdi zadatke na proveri** + **posle (radnih dana)**: ako je uključeno, zadatak koji niko ne potvrdi u roku se sam potvrđuje. Podrazumevano je isključeno.
- **Google kalendar**: **Uključen za firmu** i **Šefovi dobijaju i odsustva svog tima** (vidi „Google kalendar“).

## Odsustva (admin)

Admin vidi odsustva **svih**, sa istim tabovima kao šef (**Zahtevi**, **Na odobrenju**, **Pregled**, **Evidencija**), uz filter po timu.

Admin odobrava:

- odsustva **šefova**,
- odsustva radnika **bez tima**,
- odsustva radnika čiji je šef **odsutan**.

Samo admin može:

- **Otkaži** već odobreno odsustvo (zamena prestaje da važi),
- **Poništi** automatsko odobrenje: upisuje razlog, zahtev postaje odbijen, a radnik dobija obaveštenje,
- **Izvoz** evidencije (Excel, CSV, PDF) u tabu **Evidencija**.

Odsustvo koje admin upiše bilo kome je odmah odobreno.

## Dokumenti zaposlenih

### Kategorije dokumenata

**Kategorije dokumenata**: menjaš redosled (broj levo) i naziv i klikneš **Sačuvaj**. **Deaktiviraj** sklanja kategoriju iz izbora za nove dokumente. Novu dodaješ u poslednjem redu (**Dodaj**).

### Dokumenti korisnika

Na strani korisnika klikni **Dokumenti**. Admin vidi sve dokumente i dodaje ih bilo kome (isti obrazac kao „Moji dokumenti“ u uputstvu za radnika).

- **Važi do**: 30 i 7 dana pre isteka dobijaš obaveštenje (npr. ugovor na određeno).
- **Korpa** (dugme gore): obrisani dokumenti. **Vrati iz korpe** ih vraća, a **Trajno obriši** briše fajl sa diska. To traži da ukucaš „obriši“ i **ne može da se vrati**.

Fajlovi se nikad ne otvaraju direktnim linkom, nego samo kroz aplikaciju, uz proveru prava.

## Finansije

Finansije vidi samo admin (i superadmin). Svi iznosi su u RSD, bez PDV-a.

**Finansije** (strana):

- **MRR po mesecima**: mesečni ponavljajući prihod (paketi + mesečni pojedinačni poslovi),
- **Prihod po paketu**, **Popularnost paketa**, **Izgubljeni MRR (ukinuti paketi)**, **Prihod od pojedinačnih poslova**,
- **Vrednost portfolija i opterećenje po radniku**: procenjeni sati mesečno naspram kapaciteta. Preko 100% znači preopterećen,
- **Portfolio po timu**,
- **Klijenti po ceni sata (najlošiji prvo)**: crveno su klijenti ispod praga, tj. potcenjeni,
- **Najčešće isključivani poslovi**: znak da paket možda treba prepakovati,
- **Kalendar opterećenja**: procenjeni sati po danu za naredna 2 meseca (vide se špicevi oko rokova).

Dugmad gore:

- **Za naplatu**: za izabrani **Mesec** spisak klijenata sa stavkama, iznosom paketa, pojedinačnih poslova i ukupno. **CSV** preuzima spisak za fakturisanje.
- **Podešavanja**: **Minimalna prihvatljiva cena sata (RSD)**, **Podrazumevani mesečni kapacitet radnika (sati)** i kapacitet po radniku (prazno = podrazumevani).

Na kartici klijenta dugme **Analitika** pokazuje finansije tog klijenta: mesečni prihod, procenjeno vreme, efektivnu cenu sata, kašnjenja klijenta sa dokumentacijom i **Pakete i istoriju cena**.

[SLIKA: Finansije, opterećenje po radniku]

## Izvoz

Liste **Klijenti**, **Zadaci**, **Korisnici** i **Odsustva → Evidencija** imaju dugme **Izvoz**: **Excel (.xlsx)**, **CSV** ili **PDF**. Izvozi se tačno ono što je trenutno filtrirano (na tabovima **Zadaci** klijenta i korisnika samo ta lista). Veliki izvozi (preko 2.000 redova) se prave u pozadini, pa stiže obaveštenje sa linkom kad je fajl spreman.

## Google kalendar i ICS

- **ICS link** radi uvek: svaki korisnik ga pravi sam na **Moj profil**.
- **Google kalendar**: svaki korisnik **sam** povezuje svoj Google nalog na **Moj profil**. Admin ne povezuje nikoga. Da bi dugme radilo:
  1. Tehničko lice podešava Google pristup jednom za celu firmu (uputstvo u `docs/google-calendar-setup.md`).
  2. U **Vrste odsustva → Podešavanja** uključi **Uključen za firmu**.
  3. Po želji uključi **Šefovi dobijaju i odsustva svog tima**.

Ako se posle 5 pokušaja događaji ne upišu, korisnik dobija obaveštenje i vidi grešku na profilu, a obično je dovoljno da ponovo poveže nalog.

## Podsetnik i privatnost

Svako (i admin) ima **Podsetnik**, ličnu listu obaveza i obaveštenja (vidi uputstvo za radnika). Stavke vidi **samo vlasnik**: ni admin ni superadmin ne mogu da ih otvore, izmene, pretraže ni izvezu, a pokušaj pristupa tuđoj stavci vraća „nije pronađeno“. Stavke se ne beleže u istoriji izmena i ne ulaze u statistiku. Za obaveštenja mora da radi cron `app:todo:send-reminders` (svakog minuta, vidi `DEPLOY.md`). Završene stavke se brišu posle 3 dana komandom `app:todo:cleanup` (jednom dnevno, takođe u `DEPLOY.md`).
## Samo za superadmina

Superadmin ima sve što i admin, plus:

- pravi korisnike sa rolom **Admin** i **Superadmin** i menja ih (admin to ne može: u listi korisnika kod admina i superadmina nema olovke),
- jedini može da promeni rolu, lozinku ili podatke drugog admina, i da ga deaktivira,
- admin nikad ne može da menja ni deaktivira superadmina.

> **Savet:** neka postoje bar **dva** superadmin naloga (npr. vlasnik i zamenik), da firma ne ostane bez pristupa ako jedan zaboravi lozinku ili ode.

## Česta pitanja

**Kako da nekom radniku dam rolu Šef i napravim ga šefom tima?**
Prvo **Korisnici → Izmeni → Rola: Šef**, pa **Timovi → Izmeni → Šef tima**.

**Klijent je dobio novog glavnog radnika, a stari zadaci su ostali starom.**
Tako je namerno. Novi zadaci idu novom radniku, a otvorene prebaci ručno (**Izmeni** na zadatku, promeni **Zaduženi**).

**Zadaci za klijenta ne nastaju.**
Proveri redom:

1. klijent je aktivan i ima **glavnog zaduženog**,
2. posao je u **Ugovorenim uslugama**, nije isključen ni završen,
3. raspored nije **Po potrebi**,
4. u **Generisanju zadataka** se vidi kada nastaje,
5. na serveru radi zakazana komanda (cron) u 06:00.

**Kako da promenim cenu paketa samo jednom klijentu?**
Na tom klijentu: ⋯ pored paketa, pa **Promeni cenu**. Cena u katalogu je samo predlog.

**Radnik je otišao iz firme.**
Prebaci mu klijente (**Dodela** na svakom klijentu), prebaci ili otkaži otvorene zadatke, skloni ga sa mesta šefa ako vodi tim, pa ga **Deaktiviraj**.

**Šta se dešava sa podacima obrisanog paketa ili posla?**
Ništa se ne briše fizički: poslovi i paketi se deaktiviraju ili završavaju, a istorija i stari zadaci ostaju.

## Česte greške

- **„Korisnik vodi tim. Prvo promeni šefa tima.“**: postavi drugog šefa na timu, pa ponovi.
- **„Korisnik je zadužen na N klijent(a)...“**: prebaci klijente pre deaktivacije.
- **„Tim ima aktivne članove...“**: premesti ili ukloni članove pre brisanja tima.
- **„Glavni zaduženi i backup moraju biti različite osobe.“**
- **Dupli klijent**: pri unosu PIB-a aplikacija proverava da li klijent već postoji, a uvoz iz NBS-a preskače postojeće.
- **Praznici nisu učitani za novu godinu**: rokovi u januaru padaju na praznike. Učitaj praznike na vreme.
- **Promena neradnih dana u nedelji** menja rokove budućih zadataka i broj radnih dana u odsustvima. Radi to pažljivo.
- **Cena sa tačkom i zarezom**: upisuj kao „2.500,00“ ili „2500“. Obe varijante su ispravne.

## Posebne potvrde

Neki poslovi traže da zadatak potvrdi tačno određeni admin, bez obzira na poziciju radnika.

1. **Katalog → posao → Zatraži potvrdu**: uključi kvačicu i izaberi proveravače (aktivni admini i superadmini, najmanje jedan). Posao dobija značku **Zatraži potvrdu**. Ako izabereš samo jednog, on ne može da potvrdi sopstveni zadatak (tada potvrđuje drugi proveravač ili superadmin).
2. Zadatak nastao iz tog posla pamti proveravače u trenutku kreiranja. Kasnija izmena posla ne menja već napravljene zadatke.
3. Pregled je u meniju **Posebne potvrde** (brojač pokazuje koliko čeka tebe). Tabovi: **Čeka potvrdu** (najstariji prvi, crveno posle 2 radna dana), **U radu** i **Potvrđeno** (sa izvozom). Superadmin ima prekidač **Sve posebne potvrde**.
4. U listi su dugmad **Potvrdi** i **Vrati na doradu** (razlog je obavezan); na telefonu se prevlači red.
5. Obaveštenja (u aplikaciji i mailom, mail se isključuje u profilu) stižu pri slanju na potvrdu, a upozorenje jednom dnevno za zadatke koji čekaju duže od 2 radna dana. Automatsko odobravanje se ne primenjuje.
6. Pri deaktivaciji admina (ili skidanju prava) koji je jedini proveravač nekog posla, dobijaš upozorenje sa brojem poslova i otvorenih zadataka. Dok ne izabereš novog proveravača, zadatke potvrđuje superadmin.

U formi zadatka polje **Provera** (Automatski / Bez provere / Posebna potvrda) možeš da menjaš dok zadatak nije poslat na proveru. Na početnoj strani vidiš i koliko je zadataka „Bez provere“ ko odlučio u poslednjih 90 dana.
