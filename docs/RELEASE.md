# RELEASE.md: puštanje na produkciju `095bdbf` → `v2026.11.16`

Ovaj dokument je priprema jednog konkretnog puštanja. Opšti vodič za server je `DEPLOY.md` (ovde se samo poziva na njega).
**Ništa iz ovog dokumenta nije pokrenuto na produkciji.** U fajlu nema tajni; `<UGLASTE ZAGRADE>` su mesta koja popunjavaš.

| | |
|---|---|
| Produkcija je sada na | `095bdbf` (4. novembar 2026), `git status` na serveru čist |
| Pušta se | `HEAD` na dan pripreme (`e1003e1` + commit sa ovim dokumentom), predlog oznake `v2026.11.16` |
| Razlika | `095bdbf..HEAD`: **58 commit-ova** (59 sa commit-om ovog dokumenta), **345 fajlova** (+16.999 / −923 redova) |
| Server | DigitalOcean, Ubuntu, 1 vCPU, 1 GB RAM, aplikacija u `/var/www/kontit` (git kopija), prijava kao `root` |
| Procena prekida rada | **5 do 15 minuta** (režim održavanja potreban, vidi 5d) |

> **Najvažnije u jednoj rečenici:** ima 14 novih migracija, među kojima jedna briše staru kolonu (`app_user.position`) pa **mora da se prati redosled iz odeljka 2** (migracije 5 do 14 pojedinačno, pa prenos pozicija, a migracija B poslednja); nema novih env promenljivih ni novih biblioteka (`composer.lock` je isti); dodaju se **3 nove cron linije** (jedna na svaki minut).

---

## 1. Pregled razlike `095bdbf..HEAD`

### 1.1 Commit-ovi, grupisani po funkcijama

| Funkcija | Commit-ovi (poruka u kratkom) |
|---|---|
| **Zadaci: lista i filteri** | `fd5e3fe` jedinstvena lista i filteri (naziv bez kvačica, posao, nastanak, završeni); `6187d01` tab Zadaci na klijentu; `6eff5a3` tab Zadaci na korisniku; `09f374e` komanda `app:tasks:reindex-search`; `85645d9` povratak na izvornu stranu posle čuvanja zadatka |
| **Pozicije zaposlenih** | `edf20b2` entiteti, 4 početne pozicije, komanda `app:positions:migrate-legacy`, migracija B; `fd74e0d` CRUD pozicija; `750bba4` pozicija u formi korisnika, istorija; `97889a8` kapacitet (korisnik > pozicija > opšti) |
| **Provera zadataka i posebne potvrde** | `5d79285` šema; `6b41b20` `TaskReviewPolicy`; `b7c91d8` strana Posebne potvrde; `4edee3d` dokumentacija; `0b9d57f` popuna `finished_at`; `5c026a1`, `1e9ff8d` dizajn i test |
| **Podsetnik** (bivša „Moja lista“) | `bdb7b10`, `c794675`, `1afe853`, `504f6ef`, `29bb9e2`, `a9e7ab1`, `e1ac6cd` (stavke, panel, obaveštenja, „Dodaj u podsetnik“, kalendar); `5c9e4f9` preimenovanje u Podsetnik; `7009c52` jedna stavka menija i signal u gornjoj traci; `41223e9` ispravka prozora; `fdc20f6` brisanje završenih posle 3 dana; `3725b39` dokumentacija |
| **Raspored posla po klijentu i zadatku** | `20e903d` `ScheduleResolver`; `8443226` `ScheduleChangeService`; `0a7ba6a` prilagođen raspored za klijenta; `f8f795b` „Rok i raspored“ na zadatku; `eebb44b` dokumentacija |
| **Raspored rokova (dodato posle prve verzije dokumenta)** | pravilo „tačno taj dan“ (`EXACT`), tipovi „N-ti radni dan“ i „radni dan od kraja“, izbor „tačno taj dan“ pri „Primeni i na buduće“, migracije 13 i 14; rok na neradni dan kasni tek od prvog radnog dana posle njega (`WorkingDayService::overdueCutoff`; menja brojanje „kasne“ u matrici, listi, statistici i obaveštenjima samo kad je danas vikend/praznik/odmor) |
| **Pregled poslova po klijentima (matrica)** | `e262264` servis, upit, indeks; `3aca9d9` kartica na početnoj, filteri; `f2b82b3` izvoz, meni, redosled početne; `25d118f` dokumentacija |
| **Prikaži kao korisnik (impersonacija)** | `6fb47a4` jezgro; `72d250b` privatnost Podsetnika; `5a1f8a2` režim izmena i zabrane; `3aa42e4` Sigurnost i „Pristupi mom nalogu“; `3338f93` dokumentacija |
| **UI revizija i rečnik** | `2a46865` popis; `485de9b`, `b386c39`, `5ab53e0`, `0cb1d4a` dugmad, ikonice, rečnik (Odustani/Otkaži, Obaveštenja); `750458a`, `91f9b6f` raspored početne; `c1e7722` PROGRESS |
| **Sitni popravci** | `0ce5b70` kalendar prikazuje pogrešan dan, date picker; `8f8bca6` matični broj bez provera (kolona 20 znakova); `6e2a7e5` paginator na srpskom |
| **Uputstva, dokumentacija, alat** | `dcfeb35`, `e1003e1` uputstva (`docs/uputstvo`); `7a08a89`, `153af2e` PROGRESS i DEPLOY; `eec87fa` `composer test` |

Potpun spisak: `git log --oneline 095bdbf..HEAD`.

### 1.2 Fajlovi

`git diff --name-status 095bdbf..HEAD`: **176 dodato (A), 167 izmenjeno (M), 2 obrisana (D)**.

| Grupa | Dodato | Izmenjeno | Obrisano | Napomena |
|---|---|---|---|---|
| `src/` | 69 | 73 | 2 | obrisano: `src/Validator/MaticniBroj.php`, `MaticniBrojValidator.php` (provera matičnog broja je ukinuta) |
| `templates/` | 30 | 58 | 0 | |
| `assets/` | 33 | 20 | 0 | nove ikonice (`assets/icons/lucide/*.svg`), JS (`todo.js`, `matrix.js`, `task-filters.js`...) i CSS |
| `migrations/` | 12 | 0 | 0 | vidi odeljak 2 |
| `config/` | 0 | 4 | 0 | `doctrine.yaml`, `knp_paginator.yaml`, `security.yaml`, `services.yaml` (odeljak 3) |
| `translations/` | 1 | 0 | 0 | `KnpPaginatorBundle.sr_Latn.yaml` (nov folder) |
| `tests/` | 29 | 1 | 0 | ne utiču na rad aplikacije |
| `docs/` | 2 | 4 | 0 | `docs/UI-AUDIT.md`, `docs/uputstvo/CHANGELOG.md`; uputstva |
| koren | 0 | 7 | 0 | `CLAUDE.md`, `DEPLOY.md`, `DESIGN.md`, `PROGRESS.md`, `SPEC.md`, `composer.json`, `importmap.php` |

### 1.3 Šta ne sme ili ne mora na server

`git pull` donosi **sve što je u repozitorijumu**. Provereno:

- **Ništa od ovoga ne smeta.** Web server služi samo folder `public/` (`DocumentRoot /var/www/kontit/public`, DEPLOY 3.8), pa `tests/`, `docs/`, `*.md`, `composer.json`, `router.php` i `logo/` **nisu dostupni sa interneta** (`curl .../composer.json` mora da vrati 404). Te fajlove je server dobijao i do sada (`tests/` i `docs/archive/` su bili u `095bdbf`).
- **`/_design`** (vodič komponenti) postoji u kodu, ali kontroler vraća **404 van `dev` okruženja** (`DesignController`), a pristup ima samo superadmin. Na produkciji je zatvoren; ne treba ništa da se uklanja.
- **`.env.dev` i `.env.test`** su u gitu (razvojni `APP_SECRET`), ali se učitavaju samo za `APP_ENV=dev/test`. Na produkciji je `APP_ENV=prod` u `.env.local`, pa se ne koriste.
- **`.env.local`, `var/`, `vendor/`, `public/assets/`, `assets/vendor/`** su u `.gitignore`: ne dolaze sa pull-om i `git pull` ih ne dira. Zato je `.env.local` na serveru siguran, ali **`public/assets/` mora da se ponovo napravi** (korak 5e).
- **Razvojne skripte i privremeni fajlovi nisu u repozitorijumu** (provereno `git ls-files`: nema `.sql`, `.log`, `.ps1`, `.py` ni tmp fajlova; `_template/` je u `.gitignore`).
- Zbog toga **nije potreban nikakav ručni „čišćenje“ korak**; jedino što se posle pull-a proverava je vlasništvo fajlova (korak 5b).

### 1.4 Biblioteke (`composer.json`, `composer.lock`, `importmap.php`)

| Fajl | Promena | Šta treba |
|---|---|---|
| `composer.lock` | **nije menjan** (0 redova razlike) | nijedna PHP biblioteka se ne dodaje, ne briše ni ne menja. `composer install` će reći „Nothing to install“ |
| `composer.json` | samo nov skript `test` (za razvoj) | ništa na serveru |
| `importmap.php` | uklonjena stavka `flatpickr/dist/l10n/sr.js` (date picker sada koristi sopstveni `assets/js/datepicker.js`) | ništa novo se ne preuzima, **`importmap:install` nije potreban** |

Posledica za server sa 1 GB RAM-a: **nema velikog `composer install`-a**, što rešava rizik od nestanka memorije (vidi 5c). Potrebno je samo osvežiti autoloader zbog novih klasa u `src/`.

---

## 2. Migracije

Na produkciji je poslednja izvršena migracija verovatno `Version20261102112448`; **tačno stanje se proverava u koraku 5a** (`doctrine:migrations:status`). U `095bdbf..HEAD` ima **14 novih migracija** (13 i 14 su dodate posle prve verzije ovog dokumenta: raspored „N-ti radni dan“ i „radni dan od kraja“). SQL ispod je izvučen iz `up()` metoda (razvojna baza je već izvršila sve, pa `--dry-run` nad njom ne bi prikazao ništa; `--write-sql` na već izvršenoj migraciji greši na tabeli verzija). Pravi probni prolaz na **kopiji produkcione baze** je opisan u koraku 5d-0 i preporučujem ga.

> **Napomena o bazi:** migracije su pravljene na MariaDB 10.4, a produkcija je MySQL 8 (`DEPLOY.md` 1.6). SQL ispod je običan (`CREATE/ALTER TABLE`, `INDEX`), ali **jedna stvar nije mogla da se proveri lokalno: tabela se zove `position`** (u MySQL-u 8 to je ključna reč koja nije rezervisana, pa bi trebalo da radi bez navodnika). Probni prolaz na kopiji (5d-0) to potvrđuje pre pravog puštanja.

### 2.1 Spisak po redosledu

| # | Migracija | Šta radi | Podaci |
|---|---|---|---|
| 1 | `Version20261112104338` | `client.maticni_broj` se širi na `VARCHAR(20)` | samo širi kolonu, **ne briše ni ne menja podatke** |
| 2 | `Version20261112110352` | `task.title_search` (pretraga bez kvačica), dva nova indeksa; **popunjava kolonu za sve postojeće zadatke** | menja podatke (nova kolona, red po red, PHP petlja); trajanje raste sa brojem zadataka (npr. 10.000 zadataka ≈ pola minuta do minut) |
| 3 | `Version20261112124502` | **Migracija A pozicija**: tabele `position`, `user_position_log`, `app_user.position_id`, upis 4 početne pozicije (Entry level, Junior, Samostalni knjigovođa, Šef) | samo dodaje; stara kolona ostaje |
| 4 | `Version20261112124724` | **Migracija B pozicija**: `ALTER TABLE app_user DROP COLUMN position` ⚠ | **briše kolonu**; sama staje (`abortIf`) ako neki korisnik ima staru tekstualnu poziciju bez `position_id`. **Sme tek posle `app:positions:migrate-legacy`** |
| 5 | `Version20261112132735` | šema provere zadataka: tabele `job_template_approver`, `task_required_approver`, nove kolone `task` (`review_mode`...), `job_template.requires_approval`, `position.requires_task_review`; **postavlja `requires_task_review = 1` za „Entry level“ i „Junior“** | menja ponašanje: zadaci radnika na tim pozicijama odsad idu šefu na proveru. Postojeći zadaci se ne menjaju |
| 6 | `Version20261112134053` | `task.finished_at` (puni se iz `approved_at` za završene zadatke); **svim korisnicima dodaje dva podrazumevana mejl obaveštenja** (`SPECIAL_APPROVAL_SUBMITTED`, `SPECIAL_APPROVAL_REMINDER`) | menja podatke: `app_user.email_notifications` svih korisnika |
| 7 | `Version20261112140000` | `finished_at` za završene zadatke bez `approved_at` (uzima se `updated_at`) | menja podatke (procena datuma završetka); utiče na statistiku „završeno na vreme“ |
| 8 | `Version20261112141323` | tabela `todo_item` (Podsetnik, vremena u UTC) | samo dodaje |
| 9 | `Version20261113101004` | `task.due_date_manually_set` (`DEFAULT 0`) | samo dodaje kolonu |
| 10 | `Version20261116112327` | indeks `IDX_TASK_DUE_CLIENT` na `task (due_date, client_id)` | samo indeks |
| 11 | `Version20261116132226` | tabela `impersonation_log` | samo dodaje |
| 12 | `Version20261116133704` | `activity_log.on_behalf_of_id` (nullable) + spoljni ključ + indeks | samo dodaje; postojeći redovi ostaju netaknuti (`NULL`) |
| 13 | `Version20261117081427` | `job_template.schedule_nth_working_day` i `client_job.schedule_override_nth_working_day` (`SMALLINT DEFAULT NULL`): raspored „N-ti radni dan“ | samo dodaje dve nullable kolone; postojeći redovi `NULL`, rokovi se ne menjaju |
| 14 | `Version20261117083359` | `job_template.schedule_working_day_from_end` i `client_job.schedule_override_working_day_from_end` (`SMALLINT DEFAULT NULL`): raspored „radni dan od kraja meseca“ | samo dodaje dve nullable kolone; postojeći redovi `NULL`, rokovi se ne menjaju |

Nove vrednosti rasporeda (`NTH_WORKDAY`, `WORKDAY_END`, pravilo `EXACT`) staju u postojeće kolone (`VARCHAR(12)` i `VARCHAR(10)`), pa nema izmene postojećih kolona. Prazno pravilo za neradni dan i dalje znači „prvi sledeći radni dan“.

### 2.2 SQL (iz `up()`)

```sql
-- 1  Version20261112104338
ALTER TABLE client CHANGE maticni_broj maticni_broj VARCHAR(20) DEFAULT NULL;

-- 2  Version20261112110352
ALTER TABLE task ADD title_search VARCHAR(255) DEFAULT '' NOT NULL;
CREATE INDEX IDX_TASK_CLIENT_STATUS ON task (client_id, status);
CREATE INDEX IDX_TASK_ASSIGNEE_USER_TASK ON task_assignee (user_id, task_id);
-- zatim PHP petlja: UPDATE task SET title_search = <naslov bez kvačica> WHERE id = ... (za SVAKI zadatak)

-- 3  Version20261112124502 (A)
CREATE TABLE position (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL,
  color VARCHAR(7) NOT NULL, level INT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, monthly_capacity_hours INT DEFAULT NULL,
  suggested_access_role VARCHAR(...) ..., created_at ..., updated_at ..., PRIMARY KEY(id)) ...;
CREATE TABLE user_position_log (...) ...;
ALTER TABLE user_position_log ADD CONSTRAINT ... FOREIGN KEY (user_id|from_position_id|to_position_id|changed_by_id) ...;   -- 4 spoljna ključa
INSERT INTO position (name, color, level, is_active, suggested_access_role, created_at, updated_at)
  SELECT ?, ?, ?, 1, ?, NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM position WHERE name = ?);   -- x4, ne dupla
ALTER TABLE app_user ADD position_id INT DEFAULT NULL;
ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E9DD842E46 FOREIGN KEY (position_id) REFERENCES position (id) ON DELETE SET NULL;
CREATE INDEX IDX_88BDF3E9DD842E46 ON app_user (position_id);

-- 4  Version20261112124724 (B) ⚠ DROP
SELECT COUNT(*) FROM app_user WHERE position IS NOT NULL AND TRIM(position) <> '' AND position_id IS NULL;   -- ako > 0, migracija staje
ALTER TABLE app_user DROP COLUMN position;

-- 5  Version20261112132735
CREATE TABLE job_template_approver (job_template_id INT NOT NULL, user_id INT NOT NULL, ..., PRIMARY KEY (job_template_id, user_id));
CREATE TABLE task_required_approver (task_id INT NOT NULL, user_id INT NOT NULL, ..., PRIMARY KEY (task_id, user_id));
ALTER TABLE ... ADD CONSTRAINT ... FOREIGN KEY ...;   -- 4 spoljna ključa
ALTER TABLE job_template ADD requires_approval TINYINT DEFAULT 0 NOT NULL;
ALTER TABLE position ADD requires_task_review TINYINT DEFAULT 0 NOT NULL;
ALTER TABLE task ADD review_mode VARCHAR(10) DEFAULT 'AUTO' NOT NULL, ADD review_decided_at DATETIME DEFAULT NULL,
  ADD review_from_job TINYINT DEFAULT 0 NOT NULL, ADD completed_without_review TINYINT DEFAULT 0 NOT NULL, ADD review_decided_by_id INT DEFAULT NULL;
ALTER TABLE task ADD CONSTRAINT FK_527EDB25C85DAF04 FOREIGN KEY (review_decided_by_id) REFERENCES app_user (id) ON DELETE SET NULL;
CREATE INDEX IDX_527EDB25C85DAF04 ON task (review_decided_by_id);
UPDATE position SET requires_task_review = 1 WHERE name IN ('Entry level', 'Junior');

-- 6  Version20261112134053
ALTER TABLE task ADD finished_at DATETIME DEFAULT NULL;
UPDATE task SET finished_at = approved_at WHERE status = 'ZAVRSENO' AND approved_at IS NOT NULL;
-- zatim PHP petlja: svakom korisniku se u email_notifications dodaju SPECIAL_APPROVAL_SUBMITTED i SPECIAL_APPROVAL_REMINDER

-- 7  Version20261112140000
UPDATE task SET finished_at = approved_at WHERE status = 'ZAVRSENO' AND finished_at IS NULL AND approved_at IS NOT NULL;
UPDATE task SET finished_at = updated_at WHERE status = 'ZAVRSENO' AND finished_at IS NULL;

-- 8  Version20261112141323
CREATE TABLE todo_item (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, note LONGTEXT DEFAULT NULL, due_at DATETIME DEFAULT NULL,
  due_has_time TINYINT DEFAULT 0 NOT NULL, remind_at DATETIME DEFAULT NULL, remind_by_email TINYINT DEFAULT 0 NOT NULL, reminder_sent_at DATETIME DEFAULT NULL, ...);
ALTER TABLE todo_item ADD CONSTRAINT ... FOREIGN KEY (owner_id | client_id | task_id | created_task_id | repeat_source_id) ...;   -- 5 spoljnih ključeva

-- 9  Version20261113101004
ALTER TABLE task ADD due_date_manually_set TINYINT DEFAULT 0 NOT NULL;

-- 10 Version20261116112327
CREATE INDEX IDX_TASK_DUE_CLIENT ON task (due_date, client_id);

-- 11 Version20261116132226
CREATE TABLE impersonation_log (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, last_activity_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL,
  edit_started_at DATETIME DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, mode VARCHAR(10) NOT NULL, end_reason VARCHAR(10) DEFAULT NULL, ... actor_id, target_id ...);
ALTER TABLE impersonation_log ADD CONSTRAINT FK_2177C39510DAF24A FOREIGN KEY (actor_id) REFERENCES app_user (id);
ALTER TABLE impersonation_log ADD CONSTRAINT FK_2177C395158E0B66 FOREIGN KEY (target_id) REFERENCES app_user (id);

-- 12 Version20261116133704
ALTER TABLE activity_log ADD on_behalf_of_id INT DEFAULT NULL;
ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647CACE8692 FOREIGN KEY (on_behalf_of_id) REFERENCES app_user (id) ON DELETE SET NULL;
CREATE INDEX IDX_FD06F647CACE8692 ON activity_log (on_behalf_of_id);

-- 13 Version20261117081427
ALTER TABLE client_job ADD schedule_override_nth_working_day SMALLINT DEFAULT NULL;
ALTER TABLE job_template ADD schedule_nth_working_day SMALLINT DEFAULT NULL;

-- 14 Version20261117083359
ALTER TABLE client_job ADD schedule_override_working_day_from_end SMALLINT DEFAULT NULL;
ALTER TABLE job_template ADD schedule_working_day_from_end SMALLINT DEFAULT NULL;
```

(Dugi `CREATE TABLE` redovi su skraćeni sa `...`; potpun tekst je u `migrations/Version*.php`.)

### 2.3 Opasne operacije i pregled

| Operacija | Migracija | Ocena | Šta uraditi |
|---|---|---|---|
| `DROP COLUMN app_user.position` | 4 (B) | **jedina destruktivna**; zaštićena `abortIf` proverom | tek posle koraka sa podacima (2.4); kolona se u `down()` vraća iz naziva pozicija |
| `NOT NULL` na postojećim redovima | 2, 5, 9 | bezbedno: sve nove `NOT NULL` kolone imaju `DEFAULT` (`''`, `'AUTO'`, `0`) | ništa |
| Promena tipa kolone | 1 | samo **proširenje** `VARCHAR(8?) → VARCHAR(20)` | ništa; pazi samo na rollback (odeljak 5i) |
| Masovni `UPDATE` | 2 (title_search), 5, 6, 7 | menja podatke, brzo za male baze | bekap pre (5a), pregled stanja posle (5h) |
| Dugi `ALTER` | 12 (`activity_log`), 5 (`task`) | `activity_log` je obično najveća tabela; dodavanje spoljnog ključa može da prepiše tabelu i zaključa upise | proveri veličinu (naredba ispod); za desetine hiljada redova su sekunde, za milione minuti. **Zato režim održavanja** |
| Promena ponašanja | 5 | „Entry level“ i „Junior“ odsad prolaze proveru šefa | najavi zaposlenima (vidi `docs/uputstvo/CHANGELOG.md`) |
| Promena podešavanja svih korisnika | 6 | svi korisnici dobijaju mejl za posebne potvrde (isključuju ga u profilu) | najavi |

Veličine tabela pre puštanja (kao root; ime baze `kontit`):

```bash
mysql -e "SELECT table_name, table_rows, ROUND((data_length+index_length)/1024/1024,1) AS MB FROM information_schema.tables WHERE table_schema='kontit' ORDER BY (data_length+index_length) DESC LIMIT 8;"
```

### 2.4 Korak sa podacima: pozicije (migracije 3 i 4; redosled izvršavanja se razlikuje od redosleda verzija)

Redosled je obavezan i **ne sme da se skrati jednom komandom `migrate`**. Dva razloga:

- migracija 4 (B) briše `app_user.position`, pa sme tek **posle** prenosa podataka;
- komanda `app:positions:migrate-legacy` koristi entitet `Position`, a on već mapira kolone iz migracije 5 i kasnijih (npr. `position.requires_task_review`), pa **pre tih migracija padne** na upitu nad tabelom `position`.

Zato se migracije 5 do 14 izvršavaju **pojedinačno, pre** prenosa pozicija, a migracija B ide **poslednja**:

1. `migrate` **do migracije A** (uključuje 1, 2, 3):
   `php bin/console doctrine:migrations:migrate 'DoctrineMigrations\Version20261112124502' --no-interaction`
2. Migracije **5 do 14 pojedinačno** (B, migracija 4, se preskače):
   `php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version<N>' --up --no-interaction` za svaku od: `Version20261112132735`, `Version20261112134053`, `Version20261112140000`, `Version20261112141323`, `Version20261113101004`, `Version20261116112327`, `Version20261116132226`, `Version20261116133704`, `Version20261117081427`, `Version20261117083359` (tim redosledom; spisak i komanda u koraku 5d-1).
3. `php bin/console app:positions:migrate-legacy --dry-run`: samo izveštaj (korisnik → stara tekstualna pozicija → nova pozicija, plus spisak **novih** pozicija za nepoznate vrednosti). Ništa se ne upisuje.
4. **Pregledaj izveštaj.** Pitanja: da li se „Junior“, „Entry level“ i slično mapiraju na očekivane pozicije? Da li su nastale neželjene nove pozicije zbog pogrešnog pisanja?
5. `php bin/console app:positions:migrate-legacy`: upisuje `position_id` (može da se ponovi, ne pravi dupliranu istoriju).
6. **Poslednja: migracija B** (briše `app_user.position`):
   `php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20261112124724' --up --no-interaction`
   Posle toga `doctrine:migrations:status` pokazuje `New Migrations: 0`.

Komanda iz koraka 3 i 5 radi samo dok stara kolona postoji. Ako je kolona već obrisana, kaže da nema šta da se prenosi.

---

## 3. Konfiguracija

### 3.1 Env promenljive

**Nema novih env promenljivih.** Fajl `.env` nije menjan (`git diff 095bdbf..HEAD -- .env` je prazan). `.env.local` na serveru ostaje kakav jeste; samo proveri:

| Promenljiva | Očekivano u `.env.local` | Zašto je sada bitno |
|---|---|---|
| `APP_ENV` | `prod` | podrazumevano u `.env` je `dev` |
| `APP_DEBUG` | `0` | |
| `DEFAULT_URI` | `https://kontit.epars.rs` | linkovi u mejlovima i obaveštenjima koje šalju komande (Podsetnik) |
| `MAILER_DSN` | pravi SMTP; ako je `null://null`, **mejlovi se ne šalju** | Podsetnik („Obavesti me“ na mejl), posebne potvrde i obaveštenja idu i na mejl; sa `null://null` ostaje samo zvonce |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` | mejlovi i izvozi u pozadini (worker) |
| `DATABASE_URL`, `APP_SECRET`, NBS i Google ključevi | kao do sada | ne dira se |

Provera bez ispisa tajni: `grep -E '^(APP_ENV|APP_DEBUG|DEFAULT_URI)=' /var/www/kontit/.env.local` i `grep -c '^MAILER_DSN=null' /var/www/kontit/.env.local` (0 = pravi SMTP).

### 3.2 Parametri (`config/services.yaml`), podrazumevane vrednosti dolaze sa kodom

| Parametar | Vrednost | Šta radi |
|---|---|---|
| `matrix.warning_days` | `3` | „ističe uskoro“ u Pregledu poslova (radni dani) |
| `todo.done_retention_days` | `3` | završene stavke Podsetnika se brišu posle 3 dana (`app:todo:cleanup`) |
| `impersonation.idle_minutes` | `60` | „Prikaži kao korisnik“ se sam prekida posle 60 minuta neaktivnosti |

Ništa se ne upisuje u `.env.local`; menjaju se samo izmenom koda.

### 3.3 Ostale izmene konfiguracije

| Fajl | Izmena | Posledica |
|---|---|---|
| `config/packages/security.yaml` | `switch_user` (pravo `USER_IMPERSONATE`, POST + CSRF) | „Prikaži kao korisnik“; bez izmena u sesijama |
| `config/packages/doctrine.yaml` | novi tip `utc_datetime_immutable` | vremena Podsetnika su u bazi u UTC; ne menja postojeće kolone |
| `config/packages/knp_paginator.yaml` | sopstveni šablon paginacije + `translations/KnpPaginatorBundle.sr_Latn.yaml` | zahteva ponovno pravljenje keša (5e) |
| `config/packages/messenger.yaml`, `mailer.yaml` | **nije menjano** | worker ostaje isti: `messenger:consume async calendar` |

---

## 4. Cron i pozadinski procesi

Sve linije moraju da rade pod **istim korisnikom koji je vlasnik `/var/www/kontit`** (ne `root`: komanda pokrenuta kao `root` pravi `var/cache` i `var/log` fajlove koje web server posle ne može da menja). Korisnika utvrđuješ u koraku 5a (`APP_USER`). Putanja PHP-a: `/usr/bin/php` (proveri sa `which php`).

Tačna lista za produkciju (**NOVO** = nije bilo u `095bdbf`):

```cron
# postojeće
*/15 * * * * cd /var/www/kontit && /usr/bin/php bin/console app:approvals:auto-approve --no-interaction >> var/log/cron.log 2>&1
0 6 * * *    cd /var/www/kontit && /usr/bin/php bin/console app:tasks:generate --no-interaction >> var/log/cron.log 2>&1
0 7 * * *    cd /var/www/kontit && /usr/bin/php bin/console app:documents:check --no-interaction >> var/log/cron.log 2>&1
0 7 * * *    cd /var/www/kontit && /usr/bin/php bin/console app:notifications:due --no-interaction >> var/log/cron.log 2>&1
# NOVO: Podsetnik, obaveštenja tačno na vreme (tiha komanda: piše u log samo kad nešto pošalje)
* * * * *    cd /var/www/kontit && /usr/bin/php bin/console app:todo:send-reminders --no-interaction >> var/log/cron.log 2>&1
# NOVO: Podsetnik, trajno brisanje završenih stavki starijih od 3 dana
30 3 * * *   cd /var/www/kontit && /usr/bin/php bin/console app:todo:cleanup --no-interaction >> var/log/cron.log 2>&1
# NOVO: zatvara evidenciju "Prikaži kao korisnik" sesija koje su napuštene
*/15 * * * * cd /var/www/kontit && /usr/bin/php bin/console app:impersonation:close-stale --no-interaction >> var/log/cron.log 2>&1
# opciono, samo uz NBS kredencijale:
# 0 3 * * 0  cd /var/www/kontit && /usr/bin/php bin/console app:nbs:sync --no-interaction >> var/log/cron.log 2>&1
```

Uz njih na serveru verovatno već postoje linije bekapa (npr. `30 2 * * * .../backup.sh` i, ako je podešeno, `rclone`): **ostavi ih netaknute**.

| Komanda | Raspored | Novo | Šta ako ne radi |
|---|---|---|---|
| `app:todo:send-reminders` | svaki minut | **da** | obaveštenja „Obavesti me“ ne stižu |
| `app:todo:cleanup` | 03:30 | **da** | završene stavke se ne brišu, ali lista ih ionako sakriva posle 3 dana |
| `app:impersonation:close-stale` | na 15 min | **da** | evidencija pristupa ostaje „U toku“, ali sesija ionako ističe sama |
| `app:approvals:auto-approve` | na 15 min | ne | automatsko odobravanje odsustva |
| `app:tasks:generate` | 06:00 | ne | zadaci iz poslova |
| `app:documents:check` | 07:00 | ne | istek dokumenata |
| `app:notifications:due` | 07:00 | ne | rokovi zadataka |

Bez novih linija aplikacija **radi**, ali Podsetnik ne šalje obaveštenja.

**Messenger worker (systemd):** jedinica `kontit-worker` (`messenger:consume async calendar --time-limit=3600 --memory-limit=192M`). Worker učitava kod pri pokretanju, pa se **posle svakog puštanja mora restartovati**, inače nastavlja sa starim kodom do 1 sata:

```bash
systemctl restart kontit-worker && sleep 3 && systemctl is-active kontit-worker && journalctl -u kontit-worker -n 15 --no-pager
```
(Ako se jedinica drugačije zove: `systemctl list-units --all | grep -i kontit`. Provera korisnika: `systemctl cat kontit-worker | grep -E '^(User|Group)='`; mora biti isti kao `APP_USER`.) U koracima ispod se worker gasi pre i pali posle migracija.

---

## 5. Puštanje, korak po korak

> **Pravilo:** komande se kucaju kao `root`, ali sve što dira kod (`composer`, `php bin/console`) ide **kroz funkciju `app`** iz 5a koja ih izvršava kao vlasnik foldera. Svaki blok kopiraš ceo. Posle bloka proveri red „Očekuješ“. **Ako se očekivano ne desi, stani** (nemoj nastavljati) i pređi na odeljak 5i ili pozovi nekoga.

Preporučeni termin: vreme kada niko ne radi (rano ujutru ili uveče), 30 minuta rezerve.

### 5a. Pre puštanja

**0) Pripremi sesiju** (isti terminal ostaje otvoren do kraja; ako se zatvori, ponovi samo ovaj blok):

```bash
export APP_DIR=/var/www/kontit
export APP_USER=$(stat -c '%U' "$APP_DIR")
export APP_GROUP=$(stat -c '%G' "$APP_DIR")
echo "Vlasnik foldera: $APP_USER:$APP_GROUP"
mkdir -p /tmp/kt-home && chown "$APP_USER" /tmp/kt-home
app() { ( cd "$APP_DIR" && runuser -u "$APP_USER" -- env HOME=/tmp/kt-home COMPOSER_HOME=/tmp/kt-home/composer COMPOSER_MEMORY_LIMIT=-1 "$@" ); }
app whoami
```
Očekuješ: ispis vlasnika (npr. `www-data:www-data`, a ako piše `root:root`, **stani**: aplikacija bi radila kao root, to ne važi za ovaj vodič) i da `app whoami` vrati isto ime. `app` je kratica: `app php bin/console ...` izvršava komandu kao vlasnik foldera.

**1) Utvrdi stvarno stanje servera** (samo čitanje, ništa se ne menja):

```bash
cd "$APP_DIR" && git config --global --add safe.directory "$APP_DIR"
git log --oneline -1                      # mora biti 095bdbf
git status --short | wc -l                # mora biti 0
git branch --show-current                 # zapamti ime grane (verovatno master)
git remote -v                             # kako server dohvata kod (ssh ili https)
php -v | head -1; ls /etc/php            # verzija PHP-a (DEPLOY pretpostavlja 8.3)
systemctl list-units --all 'php*fpm*' 'kontit*' --no-pager | cat
systemctl cat kontit-worker 2>/dev/null | grep -E '^(User|Group|ExecStart)='
free -h; df -h "$APP_DIR" | tail -1
for u in root "$APP_USER" deploy; do echo "--- crontab $u"; crontab -l -u $u 2>/dev/null; done; ls /etc/cron.d
grep -E '^(APP_ENV|APP_DEBUG|DEFAULT_URI)=' .env.local; grep -c '^MAILER_DSN=null' .env.local
```
Očekuješ: `095bdbf`, `0` izmena, da vidiš **gde su postojeće cron linije** (kod kog korisnika; nove idu **tamo**, ali korisnik mora biti `APP_USER`; ako su u `root`-ovom crontab-u, premesti ih kao u 5f), ime PHP-FPM servisa (ispod pišem `php8.3-fpm`, zameni ako je druga verzija) i imena servisa.

**2) Zapiši stanje** (za vraćanje):

```bash
cd "$APP_DIR"
echo "Pre puštanja: $(git rev-parse HEAD) $(date)" | tee /root/release-before.txt
app php bin/console doctrine:migrations:status | tee /root/release-migrations-before.txt | tail -20
app php bin/console doctrine:schema:validate 2>&1 | tail -8 | tee /root/release-schema-before.txt
```
Očekuješ: pun hash commit-a (počinje sa `095bdbf`), `Current Version` (to je `PREV_VERSION`, verovatno `Version20261102112448`), `Executed Unavailable Migrations: 0`. Ako je `doctrine:schema:validate` pre puštanja već prijavio razlike (poznat rizik MariaDB/MySQL, `DEPLOY.md` 1.6), to je **postojeće stanje**: sačuvano je u `/root/release-schema-before.txt` da posle možeš da uporediš.

**3) Snimak servera (preporuka, najbrži način vraćanja):** DigitalOcean → Droplets → kontit → **Snapshots** → *Take snapshot* (traje nekoliko minuta; preporučeno pre prvog puštanja).

**4) Obavesti korisnike** da je aplikacija nedostupna oko 15 minuta (ako je potrebno).

### 5b. Režim održavanja, bekap i dohvatanje koda

**Režim održavanja** radi tako što se zaustavi PHP-FPM: Apache tada vraća „503 Service Unavailable“, bez izmene podešavanja i za `http` i za `https`. Posao u pozadini i cron se takođe zaustavljaju da ne dodiruju bazu tokom migracija.

```bash
# 1) worker i cron
systemctl stop kontit-worker
for u in root "$APP_USER" deploy; do crontab -l -u $u > /root/crontab-$u.bak 2>/dev/null; done
ls -l /root/crontab-*.bak
# privremeno isprazni crontab SAMO onim korisnicima koji su ga imali (cron se vraća u koraku 5f)
for u in root "$APP_USER" deploy; do [ -s /root/crontab-$u.bak ] && crontab -r -u $u; done
# 2) web
systemctl stop php8.3-fpm
curl -s -o /dev/null -w "%{http_code}\n" -H "Host: kontit.epars.rs" http://127.0.0.1/login
```
Očekuješ: ispis `503` (Apache više ne sme da vrati 200). **Zapamti da `/root/crontab-*.bak` sadrži staru cron listu.** Ako je neka datoteka prazna, taj korisnik nije imao cron.

**Bekap (pun):**

```bash
install -d -m 700 /root/release-backup && cd /root/release-backup
STAMP=$(date +%F_%H%M)
mysqldump --no-tablespaces --single-transaction --routines --triggers kontit | gzip > db-$STAMP.sql.gz
tar -czf files-$STAMP.tar.gz -C "$APP_DIR" var/storage var/exports 2>/dev/null
cp -p "$APP_DIR/.env.local" env.local-$STAMP
ls -lh
gunzip -t db-$STAMP.sql.gz && echo "dump OK"
```
Očekuješ: tri fajla, `db-...sql.gz` veličine bar nekoliko stotina KB, i `dump OK`. Ako `mysqldump` traži lozinku ili javi grešku pristupa, koristi podatke iz `DATABASE_URL` u `.env.local`: `mysqldump -u kontit -p --no-tablespaces ... kontit` (lozinku upiši kada se traži; nemoj je pisati u komandu). **Ne nastavljaj bez ispravnog dump-a.** Kopiju fajlova iz `/root/release-backup` prebaci i van servera (npr. `scp` na svoj računar) ili ostavi snimak iz 5a.

**Dohvati novi kod** (ispravno je kao `root`, a zatim vratiti vlasništvo; ne pokreći komandu `git pull` kao `root` bez narednog `chown`):

```bash
cd "$APP_DIR"
git fetch origin
git log --oneline 095bdbf..origin/$(git branch --show-current) | wc -l          # očekuješ: 59 (58 + commit ovog dokumenta)
git merge --ff-only origin/$(git branch --show-current)
git log --oneline -1
chown -R "$APP_USER:$APP_GROUP" "$APP_DIR"
```
Očekuješ: `59` na prvoj proveri (ako je manje, nisi poslao sve sa računara: `git push`), „Fast-forward“ i poslednji commit koji pokazuje na novu verziju. **Posle `chown` nema nijednog fajla čiji je vlasnik root:** `find "$APP_DIR" -user root | head` ne ispisuje ništa.

Ako `git fetch` kao root ne uspe (npr. deploy ključ je kod drugog korisnika): `runuser -u "$APP_USER" -- env HOME=<NJEGOV_HOME> git -C "$APP_DIR" fetch origin`, pa `git merge --ff-only` kao gore i opet `chown`.

**Provera da su svi fajlovi tu:**

```bash
cd "$APP_DIR"
git status --short | wc -l                     # 0: nema izmena ni nedostajućih fajlova
git diff --stat 095bdbf..HEAD | tail -1        # 345 files changed (HEAD je već posle ovog dokumenta: ako je commit dodat, brojevi mogu biti 346 do 347)
git ls-files -d | wc -l                        # 0: nijedan praćeni fajl ne nedostaje
ls translations/KnpPaginatorBundle.sr_Latn.yaml assets/js/todo.js assets/js/matrix.js migrations/Version20261117083359.php
```
Očekuješ: `0`, spisak od 4 postojeća fajla bez „No such file“.

### 5c. Composer (memorija: 1 GB)

`composer.lock` **nije menjan** (1.4), pa nema ničega za preuzimanje. **Preporuka: ne radi puni `composer install`, nego samo osveži autoloader.** To troši par desetina MB, a `vendor/` ostaje isti.

```bash
free -h | grep -i swap
app composer install --no-dev --optimize-autoloader --no-scripts --no-interaction
```
Očekuješ: `Nothing to install, update or remove` pa `Generating optimized autoload files`. Ako vidiš `Installing`/`Updating`, **stani** (lock je drugačiji od očekivanog).

Zašto `--no-scripts`: obični `composer install` na kraju pokreće `cache:clear` i preuzima JS biblioteke (`importmap:install`), a to ovde nije potrebno; keš se pravi u 5e.

Ako ipak mora potpun `composer install` (npr. neko je u međuvremenu menjao lock):
1. **Uključi swap** ako ga nema (`free -h` pokazuje `Swap: 0B`): `fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile && echo '/swapfile none swap sw 0 0' >> /etc/fstab`.
2. Pokreni sa `COMPOSER_MEMORY_LIMIT=-1` (već je u funkciji `app`) i dok je PHP-FPM zaustavljen (već jeste).
3. Alternativa je `composer install --no-dev --optimize-autoloader` na računaru, pa `rsync` foldera `vendor/` na server; **preporučujem samo ako 1. i 2. ne uspeju** jer ista verzija PHP-a mora biti i na računaru (platformski zahtevi).

### 5d. Migracije

**5d-0. Probni prolaz na kopiji baze (preporučeno, 3 minuta, ne dira živu bazu):**

```bash
cd /root/release-backup && LATEST=$(ls -t db-*.sql.gz | head -1)
mysql -e "DROP DATABASE IF EXISTS kontit_probe; CREATE DATABASE kontit_probe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c "$LATEST" | mysql kontit_probe
# DATABASE_URL se samo za ovu komandu menja na kopiju (vrednost uzmi iz .env.local, a naziv baze zameni u kontit_probe)
grep '^DATABASE_URL=' "$APP_DIR/.env.local"
```
Zatim kao vlasnik, sa privremeno promenjenim `DATABASE_URL` (u istoj liniji, ne u fajlu):

```bash
app env DATABASE_URL='<ISTA_VREDNOST_KAO_U_.env.local_ALI_SA_/kontit_probe_UMESTO_/kontit>' php bin/console doctrine:migrations:migrate 'DoctrineMigrations\Version20261112124502' --no-interaction
for V in 20261112132735 20261112134053 20261112140000 20261112141323 20261113101004 20261116112327 20261116132226 20261116133704 20261117081427 20261117083359; do
  app env DATABASE_URL='<ISTO>' php bin/console doctrine:migrations:execute "DoctrineMigrations\Version$V" --up --no-interaction || break
done
app env DATABASE_URL='<ISTO>' php bin/console app:positions:migrate-legacy --dry-run
app env DATABASE_URL='<ISTO>' php bin/console app:positions:migrate-legacy
app env DATABASE_URL='<ISTO>' php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20261112124724' --up --no-interaction
app env DATABASE_URL='<ISTO>' php bin/console doctrine:schema:validate
mysql -e "DROP DATABASE kontit_probe"
```
Očekuješ: sve migracije prolaze bez greške (`[OK] Successfully migrated`), izveštaj pozicija ima smisla, a vreme trajanja ti kaže koliko traje pravo puštanje. Ako **bilo šta ovde padne, stani i javi**: živa baza nije dirnuta. (Ako `DROP DATABASE` kasnije nije izvršen zbog prekida, obriši `kontit_probe` ručno.)

**5d-1. Prava migracija (živa baza, tačan redosled iz 2.4):**

```bash
cd "$APP_DIR"
# 1) do migracije A (1, 2 i 3)
app php bin/console doctrine:migrations:migrate 'DoctrineMigrations\Version20261112124502' --no-interaction
# 2) migracije 5 do 14 POJEDINAČNO (B, migracija 4, se preskače); staje na prvoj grešci
for V in 20261112132735 20261112134053 20261112140000 20261112141323 20261113101004 20261116112327 20261116132226 20261116133704 20261117081427 20261117083359; do
  app php bin/console doctrine:migrations:execute "DoctrineMigrations\Version$V" --up --no-interaction || break
done
# 3) pregled prenosa pozicija, samo izveštaj (tek sada: Position već ima kolone iz migracije 5)
app php bin/console app:positions:migrate-legacy --dry-run
```
Očekuješ: `[OK] Successfully migrated to version: DoctrineMigrations\Version20261112124502`, za svaku od 10 pojedinačnih migracija `[OK] Successfully executed ... (up)` i izveštaj „PREGLED (ništa nije upisano)“.
**Pročitaj izveštaj** (tačka 4 u 2.4). Ako je u redu:

```bash
# 4) stvarni prenos pozicija
app php bin/console app:positions:migrate-legacy
# 5) POSLEDNJA: migracija B (briše app_user.position; sama staje ako je neki korisnik ostao bez position_id)
app php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20261112124724' --up --no-interaction
app php bin/console doctrine:migrations:status | grep -E "Current Version|New Migrations|Executed Migrations"
app php bin/console doctrine:schema:validate 2>&1 | tail -8
```
Očekuješ: `Current Version: DoctrineMigrations\Version20261117083359`, `New Migrations: 0`. `doctrine:schema:validate`: ako prijavi razlike, uporedi sa `/root/release-schema-before.txt`; **nove razlike** (npr. nova tabela koja se pominje) znače problem: stani i javi; razlike koje su već bile (MariaDB/MySQL, JSON) ne zaustavljaju puštanje i ne „popravljaju“ se na živoj bazi.

Kontrola podataka posle migracija:

```bash
mysql kontit -e "SELECT COUNT(*) AS korisnika, SUM(position_id IS NOT NULL) AS sa_pozicijom FROM app_user; SELECT name, requires_task_review AS provera FROM position ORDER BY level;"
mysql kontit -e "SELECT COUNT(*) AS zadataka, SUM(title_search <> '') AS sa_pretragom, SUM(status='ZAVRSENO' AND finished_at IS NULL) AS zavrseno_bez_datuma FROM task;"
app php bin/console app:tasks:reindex-search --dry-run
```
Očekuješ: `sa_pozicijom` jednak broju korisnika koji su imali tekstualnu poziciju, „Entry level“ i „Junior“ sa `provera = 1`, `zavrseno_bez_datuma = 0`, i da reindeks javi da nema zadataka za izmenu (0 razlika).

Trajanje: za bazu reda desetina korisnika i hiljada zadataka **ispod 1 do 2 minuta** (dominira petlja `title_search`). Tačno vreme znaš iz probnog prolaza (5d-0).

### 5e. Keš, asseti, vlasništvo

```bash
cd "$APP_DIR"
rm -rf var/cache/prod public/assets
app php bin/console asset-map:compile --env=prod
app php bin/console cache:clear --env=prod
app php bin/console cache:warmup --env=prod
mkdir -p var/storage/user-documents var/exports var/log
chown -R "$APP_USER:$APP_GROUP" var public/assets
chmod -R go-rwx var
ls public/assets | head -3; ls public/assets | wc -l
app php bin/console about | grep -E "Environment|Debug"
```
Očekuješ: u `public/assets` desetine fajlova (ne sme biti 0), `Environment prod` i `Debug false`, bez grešaka. Ako `cache:warmup` prijavi „Allowed memory size“: ponovi sa `app php -d memory_limit=512M bin/console cache:warmup --env=prod` (swap, 5c).

**Napomene iz produkcije (važe za svako puštanje, i za svaki običan `git pull` posle ovog):**

1. Posle `git pull` **uvek**, kao korisnik `deploy` (`APP_USER`), ne kao `root`: `php bin/console asset-map:compile --env=prod`, pa `cache:clear --env=prod`, pa `cache:warmup --env=prod`. Bez `asset-map:compile` browser dobija stare ili nepostojeće JS/CSS fajlove.
2. Zatim **obavezno** `systemctl restart php8.3-fpm` (OPcache drži stari kod: `opcache.validate_timestamps = 0`, pa izmene PHP fajlova nisu vidljive dok se FPM ne restartuje) i `systemctl restart kontit-worker` (worker učitava kod pri pokretanju, pa bez restarta obrađuje mejlove i izvoze starim kodom).
3. Vlasnik `var/` mora biti `deploy:deploy`. Ako je neka komanda pokrenuta kao `root`, keš i log fajlovi ostanu root-ovi i aplikacija javlja 500 ili ne može da piše keš: `chown -R deploy:deploy var public/assets` i ponovi `cache:clear` kao `deploy`. Provera: `ls -ld var var/cache var/log` (vlasnik `deploy deploy`).
4. **Ne dodavaj cron linije dvaput.** Pre svakog `crontab -u deploy ...` pogledaj šta već postoji: `crontab -l` (kao `deploy`, ili `crontab -l -u deploy`), pa dodaj samo linije kojih nema. Dupla linija znači da se komanda (npr. `app:todo:send-reminders` svakog minuta) izvršava dvaput i obaveštenja mogu da stignu duplo. Provera broja: `crontab -l -u deploy | grep -c 'app:todo:send-reminders'` mora biti `1`.

### 5f. Cron i pozadinski procesi

Vrati staru cron listu i dodaj 3 nove linije **kao `APP_USER`** (ako su stare linije bile u `root` crontab-u, prebacuju se ovde na `APP_USER`):

```bash
# spoji sve stare linije (iz svih sačuvanih crontab-a) bez duplikata, pa dodaj nove
cat /root/crontab-*.bak 2>/dev/null | grep -vE 'send-reminders|todo:cleanup|impersonation:close-stale' | sort -u > /tmp/kt-cron-old
cat /tmp/kt-cron-old
```
Pregledaj ispis (to su stare linije: auto-approve, generate, documents:check, notifications:due, bekap...). Ako neka linija pokreće `bin/console` kao `root`, ona će sada raditi kao `APP_USER` (to je ispravno). Zatim:

```bash
cat /tmp/kt-cron-old - > /tmp/kt-cron-new <<'EOF'
* * * * *    cd /var/www/kontit && /usr/bin/php bin/console app:todo:send-reminders --no-interaction >> var/log/cron.log 2>&1
30 3 * * *   cd /var/www/kontit && /usr/bin/php bin/console app:todo:cleanup --no-interaction >> var/log/cron.log 2>&1
*/15 * * * * cd /var/www/kontit && /usr/bin/php bin/console app:impersonation:close-stale --no-interaction >> var/log/cron.log 2>&1
EOF
crontab -u "$APP_USER" /tmp/kt-cron-new
crontab -l -u "$APP_USER"
```
Provera duplikata: `crontab -l -u "$APP_USER" | grep -cE 'send-reminders|todo:cleanup|impersonation:close-stale'` mora biti `3` (po jedna linija svake komande); ako je više, ukloni duplikate pre nastavka (`crontab -e -u "$APP_USER"`).
Očekuješ: 4 stare + 3 nove linije (+ bekap ako je postojao). Ako je `APP_USER` = `www-data`, a on nema shell, `crontab -u www-data` ipak radi. Ako je bekap skripta ostala u `root` crontab-u i **mora** da radi kao root (npr. `/root/...`), vrati samo tu liniju: `crontab -l -u root` pa dodaj ručno (`crontab -e -u root`).

Proveri da `var/log/cron.log` može da se piše: `ls -l "$APP_DIR/var/log/cron.log"` (vlasnik `APP_USER`).

**Pokretanje procesa:**

```bash
systemctl start php8.3-fpm
systemctl restart kontit-worker
sleep 3
systemctl is-active php8.3-fpm kontit-worker
journalctl -u kontit-worker -n 10 --no-pager
cd "$APP_DIR" && app php bin/console messenger:stats
```
Očekuješ: dva puta `active`, bez grešaka u dnevniku worker-a i `failed: 0`. (`systemctl start php8.3-fpm` posle zaustavljanja istovremeno čisti OPcache; `opcache.validate_timestamps = 0` je razlog zašto ovo nije opcionalno.)

### 5g. Isključivanje režima održavanja

PHP-FPM je pokrenut u 5f, pa je aplikacija ponovo dostupna.

```bash
curl -s -o /dev/null -w "%{http_code}\n" -H "Host: kontit.epars.rs" http://127.0.0.1/login
curl -s -o /dev/null -w "%{http_code}\n" -H "Host: kontit.epars.rs" http://127.0.0.1/composer.json
curl -s -o /dev/null -w "%{http_code}\n" -H "Host: kontit.epars.rs" http://127.0.0.1/_design
```
Očekuješ: `200`, `404`, `404` (ili `302` ka prijavi za `/_design`; nikad `200` bez prijave). U ovom trenutku korisnici smeju da rade.

### 5h. Provera posle puštanja (smoke test)

Radi u pregledaču, prijavljen kao superadmin. Svaka stavka ima očekivani ishod; **ako neka padne, vidi 5i**.

| # | Šta kliknuti | Očekuješ |
|---|---|---|
| 1 | Prijava sa tačnom, pa sa pogrešnom lozinkom | tačna ulazi na Početnu; pogrešna daje poruku, ne grešku 500 |
| 2 | **Početna** | redosled: **Zadaci**, **Pregled poslova po klijentima**, **Posebne potvrde**, **Naredna nedelja**; u gornjoj traci ikonica sveske (Podsetnik) |
| 3 | Otvori jedan zadatak, štikliraj stavku, vrati je | radi, u **Istoriji** se pojavi zapis; značka provere (**Proverava: …**, **Bez provere** ili **Posebna potvrda**) |
| 4 | **Klijenti** → klijent → tab **Zadaci** | lista sa filterima, broj otvorenih zadataka uz naziv taba |
| 5 | **Zadaci**: kucaj naziv bez kvačica (npr. „racun“) | nalazi zadatke sa „Račun“ (potvrda da je `title_search` popunjen) |
| 6 | **Administracija → Pozicije** i **Korisnici** → korisnik | spisak pozicija (Entry level, Junior, Samostalni knjigovođa, Šef + eventualno nove); svaki korisnik ima svoju poziciju |
| 7 | **Posebne potvrde** | strana se otvara (može da bude prazna), brojač u meniju bez greške |
| 8 | **Podsetnik**: **+ Dodaj**, naslov „Test“, **Obavesti me** za 2 minuta | stavka se pojavi; **posle ~2 minuta stiže obaveštenje u zvonce** (dokaz da radi cron `send-reminders`); posle toga obriši stavku |
| 9 | **Pregled poslova**: klikni ćeliju | otvara se prozor sa zadacima; **Izvoz → Excel** se preuzima |
| 10 | **Kalendar** | mesec se prikazuje; dani u nedelji odgovaraju datumu (bivši problem); filter **Podsetnik** postoji |
| 11 | **Odsustva** → **Zahtev za odsustvo** | forma se otvara i prikazuje **Pregled zahteva** |
| 12 | **Moj profil → Moji dokumenti**: otvori postojeći dokument | otvara se pregled (potvrda da su `var/storage` i prava ispravni) |
| 13 | **Korisnici** → radnik → ⋯ **Prikaži kao ovaj korisnik** | potvrda, žuti baner „Gledaš kao“, **Podsetnik nema**; pokušaj izmene javlja „Samo pregled“; **Izađi** vraća na tvoj nalog |
| 14 | **Administracija → Sigurnost** (superadmin) | spisak sa upravo viđenim pristupom (korak 13); kod radnika u **Moj profil → Pristupi mom nalogu** isti zapis, bez IP adrese |
| 15 | Mejl (**samo ako je `MAILER_DSN` pravi**): **Zaboravljena lozinka** na svoj mejl, i „Obavesti me“ sa mejlom | stiže u roku od par minuta (provera worker-a); ako je `null://null`, preskoči: mejl se ne šalje (poznato) |

Tehnička provera:

```bash
cd "$APP_DIR"
sleep 120
tail -n 20 var/log/cron.log                                        # bez grešaka; "Poslato obaveštenja: N" samo kad je nešto poslato
app php bin/console messenger:stats                                # failed: 0
tail -n 30 /var/log/php8.3-fpm.log /var/log/apache2/kontit_error.log 2>/dev/null | grep -iE "error|critical|exception" | tail    # nema novih grešaka
find "$APP_DIR" -user root | head -3                               # prazno: nijedan fajl nije kod root-a
```

Ako je sve u redu, **zabeleži rezultat**: `git -C "$APP_DIR" log --oneline -1 | tee /root/release-after.txt`, a posle 24 sata obriši `/root/release-backup` ako više nije potreban (zadrži bar jedan dump).

### 5i. Plan vraćanja (rollback)

**Odluči prema trenutku:**

| Situacija | Šta uraditi |
|---|---|
| Pad pre migracija (u 5b, 5c) | samo kod: R1 |
| Pad tokom ili posle migracija, **prvih sat vremena, bez bitnih novih podataka** | kod + baza iz dump-a: R1 + R2 (najčistije) |
| Problem posle sat i više, korisnici su već unosili | **ne vraćaj dump** (izgubićeš unos); ispravi unapred ili R1 + R3 |

**R1. Vrati kod na `095bdbf`:**

```bash
systemctl stop php8.3-fpm kontit-worker
cd "$APP_DIR" && git checkout --detach 095bdbf        # bezbedno: ne briše commit-e, samo premešta HEAD
chown -R "$APP_USER:$APP_GROUP" "$APP_DIR"
rm -rf var/cache/prod public/assets
app composer install --no-dev --optimize-autoloader --no-scripts --no-interaction
app php bin/console asset-map:compile --env=prod
app php bin/console cache:warmup --env=prod
chown -R "$APP_USER:$APP_GROUP" var public/assets
crontab -u "$APP_USER" /tmp/kt-cron-old                # stari cron (ili vrati iz /root/crontab-*.bak)
systemctl start php8.3-fpm kontit-worker
```
Kasnije se na novu verziju vraća sa `git checkout master` (ime grane iz 5a/1).

**R2. Vrati bazu iz bekapa** ⚠ *briše sve podatke upisane posle bekapa:*

```bash
systemctl stop php8.3-fpm kontit-worker
mysqldump --no-tablespaces --single-transaction kontit | gzip > /root/release-backup/db-pred-vracanje-$(date +%F_%H%M).sql.gz    # sačuvaj trenutno stanje za svaki slučaj
mysql -e "DROP DATABASE kontit; CREATE DATABASE kontit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c /root/release-backup/db-<ČASOVNIK>.sql.gz | mysql kontit
app php bin/console doctrine:migrations:status | grep "Current Version"     # mora biti PREV_VERSION iz 5a
```
Ako je korisnik baze `kontit` sa ograničenim pravima, `DROP DATABASE` radi samo `root` (kao gore). Pa R1 i pokretanje servisa. Alternativa najbrža: vraćanje **snimka servera** iz 5a (DigitalOcean → Snapshots → Restore).

**R3. Vrati samo migracije (bez vraćanja podataka iz dump-a)**, kad je kod već vraćen na `095bdbf`: stari kod koristi kolonu `app_user.position`, koju je migracija B obrisala, pa **bez vraćanja migracije B stari kod ne radi**:

```bash
app php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20261112124724' --down --no-interaction   # vraća kolonu i puni je iz naziva pozicija
```
Potpuno vraćanje na stanje pre puštanja (sve migracije unazad, redom od najnovije):

```bash
app php bin/console doctrine:migrations:migrate '<PREV_VERSION iz 5a>' --no-interaction
```
**Koje migracije se ne vraćaju bez posledica:**

| Migracija | Šta se desi pri vraćanju |
|---|---|
| 1 (matični broj) | kolona se vraća na `VARCHAR(8)`: **ako je neko upisao duži matični broj, vraćanje pada ili skraćuje vrednost** (proveri: `SELECT MAX(CHAR_LENGTH(maticni_broj)) FROM client`) |
| 3 (A), 5, 8, 11 | brišu tabele `position`/`user_position_log`, `job_template_approver`/`task_required_approver`, **`todo_item` (svi Podsetnici)**, **`impersonation_log`**: podaci se **gube** (prvo `mysqldump` tih tabela) |
| 4 (B) | vraća staru kolonu, ali iz **naziva pozicije**; istorija pozicija se gubi sa tabelom iz migracije 3 |
| 6 | briše `finished_at`; **ne vraća** dva mejl obaveštenja svim korisnicima (podatak se ne vraća) |
| 7 | nema vraćanja (`down()` je prazan): popunjeni datumi `finished_at` ostaju (nije štetno) |
| 12 | briše `on_behalf_of_id`: oznake „u ime“ u istoriji se gube |

**Ako ništa ne uspe:** snimak servera iz 5a, pa 5a-5h ponovo kad se uzrok otkloni.

---

## 6. Rizici i na šta treba paziti

1. **Migracija B briše `app_user.position`.** Ne pokreći je pre `app:positions:migrate-legacy`, a ni `migrate` bez argumenta (pokrenuo bi je). Zato se u 5d migracije izvršavaju ovim redom (2.4): do A, pa 5 do 14 pojedinačno (`execute --up`), pa `migrate-legacy` (prvo `--dry-run`), a B poslednja. Bekap pre je obavezan.
2. **Ponašanje se menja za zaposlene:** „Entry level“ i „Junior“ prolaze proveru šefa; svi korisnici dobijaju mejl za posebne potvrde; „Podsetnik“, „Pregled poslova“ i „Prikaži kao korisnik“ su novi. Pošalji `docs/uputstvo/CHANGELOG.md`.
3. **Vremenske zone.** Aplikacija koristi PHP-ovu zonu (`date.timezone = Europe/Belgrade` u `/etc/php/8.3/fpm/conf.d/99-kontit.ini` **i** `/etc/php/8.3/cli/conf.d/99-kontit.ini`; cron koristi CLI). Podsetnik čuva vremena u UTC i sam računa Beograd (`TodoClock`), ali ostatak aplikacije se oslanja na zonu PHP-a. Proveri: `php -i | grep date.timezone` i `timedatectl | grep "Time zone"`. Zona MySQL servera ne utiče na Podsetnik; utiče samo `NOW()` u seed-u migracije A (nebitno).
4. **Novi cron na svaki minut** (`app:todo:send-reminders`) na 1 vCPU / 1 GB: pokreće se jednom u minuti, kratko; nema ispisa u log dok nema šta da se pošalje. Ako server tada radi na ivici memorije, prvi simptom je prekid. Prati prvih sat vremena (`free -h`, `tail var/log/cron.log`).
5. **Vlasništvo fajlova.** Kod `root` + `git pull` ostaju fajlovi kod root-a i web server (npr. `www-data`) ne može da piše u `var/` (greške 500, prazan keš). Zato `chown -R` u 5b, 5e i provera `find ... -user root`. Cron i worker moraju biti pod `APP_USER`, ne `root`.
6. **Memorija (1 GB).** Nema velikog `composer install` (lock isti), ali `cache:warmup` i migracije mogu da tražu 200 do 300 MB: uključi swap pre puštanja (5c). Worker ima limit 192 MB, a FPM do 5 procesa, plus MySQL; ako server često ide u swap, razmisli o Droplet-u sa 2 GB (`DEPLOY.md` 0 i 3.1 su planirani za 2 GB).
7. **Dugi `ALTER` na `activity_log`.** Proveri veličinu (2.3). Za milion redova pusti probni prolaz (5d-0) i predvidi više vremena.
8. **Worker učitava kod pri pokretanju:** posle puštanja mora `systemctl restart kontit-worker`, inače sat vremena radi sa starim kodom (izvozi i mejlovi).
9. **OPcache** (`validate_timestamps = 0`): bez restarta PHP-FPM-a vidiš stari kod. Rešeno u 5f (`start` posle `stop`).
10. **Mejl.** Ako je `MAILER_DSN=null://null`, „Obavesti me“ na mejl i obaveštenja na mejl **ne stižu** (zvonce radi). To je postojeća odluka iz `DEPLOY.md`, nije novi problem.
11. **`/_design`** je zatvoren van dev okruženja; ne treba ga uklanjati.
12. **MariaDB → MySQL 8:** migracije nisu probane na MySQL-u 8 (poznat rizik, `DEPLOY.md` 1.6). Zato probni prolaz na kopiji (5d-0) pre žive baze.

---

## 7. Git oznaka za ovo izdanje

Predlog: `v2026.11.16`. **Komande nisu pokrenute**; izvršavaš ih ti na računaru, **posle** završenog puštanja i uspešnog smoke testa (da oznaka označava ono što stvarno radi):

```bash
git tag -a v2026.11.16 -m "Izdanje 2026-11-16: Podsetnik, Pregled poslova, pozicije i provera zadataka, raspored posla, Prikaži kao korisnik" HEAD
git push origin v2026.11.16
```
(Opciono i oznaka prethodnog stanja produkcije: `git tag -a v2026.11.04 095bdbf -m "Produkcija pre izdanja 2026-11-16"` pa `git push origin v2026.11.04`.) Na serveru kasnije: `git fetch --tags` i `git checkout v2026.11.16` ima isti efekat kao grana.
