# DEPLOY.md: KontIT na DigitalOcean

Vodič za postavljanje aplikacije na DigitalOcean. Napisan je na osnovu analize koda, bez ijedne izmene koda aplikacije.
U fajlu nema tajni. Sve što je u `<UGLASTIM ZAGRADAMA>` je mesto koje popunjavaš svojom vrednošću.

> **Napomena o bezbednosti:** komande označene sa ⚠ brišu podatke ili menjaju produkciju. Pokrećeš ih ti, ručno,
> i tek pošto pročitaš šta rade.

---

## 0. Odluke (odgovori iz razgovora) i šta još ostaje otvoreno

| # | Tema | Odluka |
|---|---|---|
| 1 | Web server | **Apache 2.4** + PHP-FPM (`proxy_fcgi`) |
| 2 | Domen | **kontit.epars.rs** (test). DNS zapis A za `kontit` u zoni `epars.rs` |
| 3 | PHP | **8.3** |
| 4 | Baza | **MySQL 8.0** (rizik u 1.6, proverava se `schema:validate` u 3.7) |
| 5 | Kod | **GitHub**, privatni repo + deploy ključ (3.6) |
| 6 | Google kalendar i NBS | **koriste se oba** (kredencijali u 3.7, redirect adresa u 3.10) |
| 7 | Broj korisnika | do ~20: Droplet 2 GB je dovoljan |
| 8 | **Mail** | **otvoreno ("videmo naknadno").** Do odluke je `MAILER_DSN=null://null`: **mailovi se ne šalju** (reset lozinke i obaveštenja ne stižu). Kad izabereš SMTP nalog, menja se samo `MAILER_DSN` u `.env.local`. Pošiljalac je hardkodovan (1.4, tačka 1), što je izmena koda i traži tvoje odobrenje |

---

## 1. FAZA 1: Analiza aplikacije

### 1.1 Tehnologija

| Stavka | Nalaz |
|---|---|
| Jezik | PHP, `composer.json`: `>=8.2` (lokalno 8.2.12). Za produkciju predlog 8.3 |
| Framework | Symfony **7.4 LTS** (full webapp), Doctrine ORM 3, Twig |
| Package manager | **Composer** (`composer.lock` postoji). **Nema Node-a ni `npm`**: front-end ide preko AssetMapper-a (importmap) |
| Baza | MySQL/MariaDB preko Doctrine DBAL (`DATABASE_URL`). 24 migracije u `migrations/` |
| Cache | Symfony filesystem cache (`var/cache`, `var/share`). **Nema Redis-a ni Memcached-a** |
| Sesije | Native PHP sesije (`session.storage.factory.native`), upisuju se u `session.save_path` iz php.ini. `cache:clear` zato ne izbacuje korisnike |
| Queue | **Symfony Messenger, Doctrine transport** (tabela `messenger_messages`). Transporti: `async`, `calendar`, `failed` |
| Upload-ovi | **Ne idu u `public/`.** Dokumenti korisnika: `var/storage/user-documents` (PDF, JPG, PNG, do 20 MB). Izvozi: `var/exports`. Avatar su samo inicijali (nema upload-a) |
| Mail | Symfony Mailer preko Messenger-a (`SendEmailMessage` ide u `async`), pa mailovi **ne idu dok worker ne radi** |
| Eksterni servisi | **NBS** SOAP (`webservices.nbs.rs`, nusoap, kredencijali), **Google Calendar** OAuth (opciono, `GOOGLE_CLIENT_*`), **Iconify** nije potreban jer su ikonice u repou (`assets/icons/lucide`, 96 komada) |
| Plaćanja, AI API | **Nema** |
| PDF/Excel | `dompdf`, `phpspreadsheet` (traže PHP ekstenzije `gd`, `zip`, `mbstring`, `xml`) |
| Šifrovanje | Google tokeni su šifrovani ključem izvedenim iz **`APP_SECRET`** (libsodium ako postoji, inače OpenSSL). Promena `APP_SECRET`-a znači da svi moraju ponovo da povežu Google |

### 1.2 Cron poslovi i worker (iz `src/Command`, SPEC.md i PROGRESS.md)

| Komanda | Raspored | Šta radi |
|---|---|---|
| `app:approvals:auto-approve` | **na 15 minuta** | automatsko odobravanje zahteva kojima je istekao rok, podsetnici |
| `app:tasks:generate` | **06:00 svakog dana** | pravi ponavljajuće zadatke iz poslova klijenata |
| `app:documents:check` | **07:00 svakog dana** | dokumenti koji ističu, podsetnik za doznaku |
| `app:notifications:due` | **svakog dana** (predlog 07:00) | notifikacije za rokove zadataka |
| `app:nbs:sync` | **nije propisan u SPEC-u**, predlog: nedeljno | osvežava podatke klijenata iz NBS-a (samo ako imaš NBS kredencijale) |
| `app:calendar:resync <email>` | ručno | popravka Google kalendara jednog korisnika |
| `app:user:create` | ručno, jednom | pravi prvog superadmina |
| `messenger:consume async calendar` | **stalno (worker)** | mailovi, izvozi u pozadini, Google sinhronizacija |

Sistemska zona servera mora biti **Europe/Belgrade** (rasporedi u SPEC-u su po lokalnom vremenu).

### 1.3 Lista env varijabli za produkciju

Ide u `/var/www/kontit/.env.local` na serveru (fajl je u `.gitignore`). **Ne upisuj prave vrednosti u fajlove koji idu u git.**

| Varijabla | Obavezna | Opis |
|---|---|---|
| `APP_ENV` | da | `prod`. Podrazumevano u `.env` je **`dev`** pa se mora prepisati |
| `APP_DEBUG` | da | `0` |
| `APP_SECRET` | da | `openssl rand -hex 32`. U `.env` je prazan. Sačuvaj kopiju u menadžeru lozinki |
| `DATABASE_URL` | da | `mysql://kontit:<LOZINKA>@127.0.0.1:3306/kontit?serverVersion=8.0.46&charset=utf8mb4`. Pazi: lokalna vrednost je root bez lozinke i `mariadb-10.4.32` |
| `DEFAULT_URI` | da | `https://kontit.epars.rs`. Podrazumevano je `http://localhost`, a koristi se za linkove iz mailova koje šalju komande i worker |
| `MESSENGER_TRANSPORT_DSN` | da | `doctrine://default?auto_setup=0` (ostaje kao u `.env`) |
| `MAILER_DSN` | da | npr. `smtp://KORISNIK:LOZINKA@smtp.servis.com:587`. Podrazumevano je `null://null` (mailovi se bace) |
| `NBS_PROVIDER` | ne | `webservice` (ostaje) |
| `NBS_WS_USERNAME`, `NBS_WS_PASSWORD`, `NBS_WS_LICENCE` | ako se koristi NBS | kredencijali zvaničnog servisa |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | ako se koristi Google | prazno = povezivanje je isključeno |
| `APP_SHARE_DIR` | ne | `var/share` (ostaje) |

Hardkodovane vrednosti u kodu koje utiču na produkciju: vidi 1.4 (tačka 1: adresa pošiljaoca) i `NbsWebServiceProvider::BASE` (adresa NBS servisa, ostaje).

### 1.4 Šta NIJE spremno za produkciju (samo prijava, ništa nisam menjao)

Prioritet: **K** kritično za rad, **V** visok, **S** srednji.

| # | P | Nalaz | Gde | Šta predlažem (traži tvoje odobrenje) |
|---|---|---|---|---|
| 1 | K | **Adresa pošiljaoca je `no-reply@kontit.local`** (hardkodovana). Takvi mailovi završe u spamu ili se odbijaju, a reset lozinke ne stiže | `src/Service/NotificationService.php:21`, `src/Controller/ResetPasswordController.php:159` | Uvesti env `MAIL_FROM` ili parametar i upisati pravu adresu sa tvog domena. Do tada mailovi ne rade kako treba |
| 2 | K | **Nema git `remote`-a**, a promene stavki 1–5 (37 fajlova, 2 nove migracije, `TaskListFilters.php`, `_team_availability.html.twig`) **nisu commit-ovane** | `git status` | Commit-uj, napravi privatni repo i `git push`. Server dobija samo ono što je u repou |
| 3 | V | **`composer audit`: `symfony/mime` v7.4.19, CVE-2026-45070** (umetanje zaglavlja u mail, medium). Ispravljeno u **7.4.22** | `composer.lock` | `composer update symfony/mime --with-dependencies`, test, commit `composer.lock` |
| 4 | V | **Nema ograničenja pokušaja prijave** (`login_throttling` nije u `security.yaml`) | `config/packages/security.yaml` | Dodati `login_throttling`. Dok se to ne uradi, u vodiču je zaštita preko fail2ban-a (3.2) |
| 5 | V | `.env` ima `APP_ENV=dev` i prazan `APP_SECRET` | `.env` | Ne menja se. Prepisuje se u `.env.local` na serveru (3.7) |
| 6 | S | **Logovi u produkciji idu na `php://stderr`** u JSON-u (`fingers_crossed`, samo greške). Ako PHP-FPM ne hvata izlaz, logova nema | `config/packages/monolog.yaml` | Bez izmene koda: `catch_workers_output = yes` u FPM pool-u (3.4). Opciono kasnije dodati fajl handler |
| 7 | S | `/_design` ruta (vodič komponenti) postoji i u produkciji. Dostupna je samo superadminu (`#[IsGranted('ROLE_SUPERADMIN')]`) | `src/Controller/DesignController.php` | Prihvatljivo, ili je isključiti u prod okruženju |
| 8 | S | **`date.timezone` se ne postavlja u kodu** (samo Twig ima `Europe/Belgrade`). Datumi ("danas", cron rasporedi) zavise od PHP podešavanja | `config/packages/twig.yaml` | Bez izmene koda: `date.timezone = Europe/Belgrade` u php.ini (3.3) |
| 9 | S | **Test okruženje:** testovi su pokrenuti samo na PHP 8.2 i MariaDB-u, a produkcija je 8.3 i (predlog) MySQL 8 | n/a | Posle prvog deploy-a pokreni proveru iz 1.6 |
| 10 | S | `assets/vendor/*` je u `.gitignore`: JS biblioteke se **preuzimaju pri `composer install`** (`importmap:install`) sa interneta | `composer.json` (`auto-scripts`) | Server mora imati izlaz na internet. Ako preuzimanje padne: `php bin/console importmap:install` |
| 11 | S | `.env.dev` i `.env.test` su u git-u i sadrže razvojni `APP_SECRET` | git | Ne učitavaju se uz `APP_ENV=prod`. Nikad ne koristi te vrednosti na serveru |

Provereno i **uredu**: `web-profiler`, `debug` i `maker` su samo u `dev` okruženju (u prod rutama nema `_profiler`/`_wdt`), prod cache se zagreva bez greške (`APP_ENV=prod cache:warmup` prolazi), `ux_icons` ne zove spoljni servis (`ignore_not_found` u prod), upload-ovi su van `public/`, CSRF je uključen na prijavi, kolačić `secure=auto` i `samesite=lax`.

### 1.5 Migracije i seed podaci: redosled

1. **Nema fixture-a ni zasebnog seed-a.** Početni podaci su **unutar migracija** (neradni dani i pravilo vikenda, kategorije poslova, vrste odsustva, 6 kategorija dokumenata). Zato se na praznoj bazi sve radi jednom naredbom.
2. Redosled na novom serveru:
   1. `php bin/console doctrine:migrations:migrate --no-interaction`: pravi 24 migracije, uključujući tabelu `messenger_messages` (migracija `Version20261027104938`).
   2. `php bin/console doctrine:schema:validate`: mora da kaže da je šema usklađena.
   3. `php bin/console app:user:create <email> <lozinka>`: prvi **superadmin** (ostalo ide kroz aplikaciju).
3. `Version20261102112448` (nova, još neizvršena na serveru) menja samo podatke (šef postaje član tima). Na praznoj bazi ne radi ništa. Mora biti u repou pre deploy-a.
4. Nikad ne menjaj već izvršene migracije (pravilo iz `CLAUDE.md`).
5. Transporti `calendar` i `failed` koriste `doctrine://default?queue_name=...` sa podrazumevanim `auto_setup=true`. Zato korisnik baze mora imati sva prava nad svojom bazom (ne samo SELECT/INSERT). U vodiču ima `GRANT ALL ... ON kontit.*`, a ne root.

6. **Pozicije zaposlenih (stara tekstualna pozicija → `Position`)**, tačan redosled na produkciji:
   1. `php bin/console doctrine:migrations:migrate 'DoctrineMigrations\Version20261112124502' --no-interaction` (migracija A: šema + 4 početne pozicije). Migracija B (`Version20261112124724`) **ne sme još**; ako je pokrećeš celu, ona sama staje sa porukom dok postoje nemapirani korisnici, pa prvo uradi korake 2–4.
   2. `php bin/console app:positions:migrate-legacy --dry-run`: samo izveštaj (korisnik → stara vrednost → pozicija, i spisak novih pozicija).
   3. Pregledaj izveštaj (nepoznate vrednosti postaju nove pozicije).
   4. `php bin/console app:positions:migrate-legacy`: upisuje (idempotentno, ne pravi istoriju).
   5. `php bin/console doctrine:migrations:migrate --no-interaction`: migracija B briše staru kolonu `app_user.position`.

### 1.6 Rizik: MariaDB (razvoj) naspram MySQL 8 (produkcija)

Migracije su generisane na MariaDB 10.4. Kolone `JSON` se u MariaDB-u čuvaju kao `LONGTEXT`, u MySQL-u 8 kao pravi `JSON`, pa Doctrine može da prijavi razliku u šemi. **Ovo nisam mogao da proverim jer lokalno nema MySQL-a 8.**
Provera je jednostavna i bezbedna na praznoj bazi: posle `migrate`, pokreni `doctrine:schema:validate` (korak 3.7). Ako prijavi razlike, **nemoj ih "popravljati" na živoj bazi**. Javi mi, pa pravim novu migraciju. Alternativa bez rizika je MariaDB 10.11 na serveru (`apt install mariadb-server`, `serverVersion=mariadb-10.11.x`), jer odgovara onome na čemu je aplikacija razvijana.

### 1.7 Testovi

`php bin/phpunit` na razvojnoj mašini (PHP 8.2.12, MariaDB 10.4): **46 testova, 127 provera, sve prolazi**, plus 1 PHPUnit deprecation (nije greška). Dodatno `lint:container`, `lint:twig` i prod `cache:warmup` prolaze. Testovi se ne pokreću na serveru (`require-dev` se ne instalira).

---

## 2. FAZA 2: Izbor infrastrukture

Cene su **okvirne** (mesečno, USD, bez poreza) i menjaju se. Proveri na [digitalocean.com/pricing](https://www.digitalocean.com/pricing) pre nego što poručiš. Izvori kojima sam se vodio: [Droplets pricing](https://digitalocean.com/pricing/droplets) i ostali pregledi cena (Basic 2 GB / 1 vCPU / 50 GB oko 12 USD, upravljana MySQL baza od oko 15 USD, App Platform od oko 5 USD po komponenti).

### Varijanta A: Droplet (VPS, sve na jednom serveru)

| | |
|---|---|
| Sadržaj | Ubuntu 24.04 + Apache 2.4 + PHP-FPM 8.3 + MySQL 8 + systemd worker + cron |
| Cena | Droplet Basic 2 GB: **~12 USD**. Automatski backup (nedeljni) **+20% ≈ 2,40 USD**. Opciono Spaces za kopije van servera **~5 USD**. **Ukupno ≈ 15–20 USD/mesečno** |
| Prednosti | Najjeftinije. Pun pristup: cron, systemd worker i fajl sistem rade kako je aplikacija pisana. Jedno mesto, jednostavan backup |
| Mane | Ti održavaš server: bezbednosna ažuriranja, backup, nadzor. Jedna tačka otkaza. Nema automatskog skaliranja |

### Varijanta B: App Platform (managed)

| | |
|---|---|
| Sadržaj | Web servis iz GitHub-a + **worker** komponenta + **job** komponente za cron + **upravljana baza** |
| Cena | Web (1–2 GB) i worker po ~10–25 USD svaki, upravljana MySQL od ~15 USD, job-ovi se naplaćuju po izvršavanju. **Ukupno otprilike 40–60 USD/mesečno** (procena) |
| Prednosti | Nema održavanja OS-a, deploy iz git-a, automatski TLS, automatski backup baze |
| Mane za ovu aplikaciju | **Fajl sistem kontejnera je privremen.** Dokumenti korisnika (`var/storage/user-documents`) i izvozi (`var/exports`) bi se gubili pri svakom deploy-u. Trebalo bi **menjati kod** da koristi DigitalOcean Spaces (S3). Takođe: filesystem cache i sesije, cron kao zasebni job-ovi sa ograničenjima rasporeda, `importmap:install` i `asset-map:compile` u build koraku |

### Preporuka: **Varijanta A, Droplet**

1. **Ne traži nijednu izmenu koda.** Na App Platform-i bi se čuvanje dokumenata moralo prepisati.
2. Aplikacija je pisana za klasičan server: cron, dugotrajan worker, lokalni fajl sistem.
3. Oko 3 puta jeftinije, a za desetak do dvadeset korisnika 2 GB je sasvim dovoljno.
4. Mane (održavanje i backup) ublažavaju se ovim vodičem: firewall, fail2ban, automatska bezbednosna ažuriranja, DO backup, dnevni dump baze.

Ako broj korisnika raste ili ne želiš da održavaš bazu: bazu kasnije prebaci na upravljanu MySQL (korak 3.5 se zamenjuje samo `DATABASE_URL`-om), a aplikacija ostaje na Droplet-u.

---

## 3. FAZA 3: Vodič korak po korak (Droplet)

Konvencije: komande sa `$` na serveru, `PS>` na tvom Windows računaru. Svaki korak ima **šta radi**, **komandu** i **proveru**.

### 3.1 Kreiranje Droplet-a

**Šta:** novi server u DigitalOcean kontrol panelu.

1. Na računaru napravi SSH ključ (ako ga nemaš) i prikaži javni deo:
   ```powershell
   PS> ssh-keygen -t ed25519 -C "kontit-deploy"
   PS> Get-Content $env:USERPROFILE\.ssh\id_ed25519.pub
   ```
2. DigitalOcean → **Create → Droplets**:
   - **Region:** Frankfurt (`FRA1`), najbliži regionu.
   - **Image:** Ubuntu **24.04 (LTS) x64**.
   - **Size:** Basic → Regular (SSD) → **2 GB / 1 vCPU / 50 GB**.
   - **Authentication:** SSH Key → **New SSH Key** → nalepi javni ključ.
   - **Backups:** uključi (Enable backups, nedeljni).
   - **Hostname:** `kontit-prod`.
3. DigitalOcean → **Networking → Firewalls → Create Firewall**, primeni na Droplet:
   - Inbound: `SSH 22` (ako imaš stalnu IP, ograniči samo na nju), `HTTP 80`, `HTTPS 443`, svi sa `All IPv4/IPv6`.
   - Outbound: ostavi sve dozvoljeno (treba za `composer`, NBS, SMTP, Google).

**Provera:**
```powershell
PS> ssh root@<IP_DROPLETA>
```
Treba da uđeš bez lozinke. Zapiši `<IP_DROPLETA>`.

### 3.2 Početno podešavanje servera

**Šta:** ažuriranja, vremenska zona, korisnik bez root-a, zaključavanje SSH-a, ufw, fail2ban, swap.

Kao `root`:
```bash
apt update && apt full-upgrade -y
timedatectl set-timezone Europe/Belgrade

adduser deploy                      # postavi jaku lozinku (treba za sudo)
usermod -aG sudo deploy
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy
```
**Provera (u NOVOM terminalu, ne zatvaraj root sesiju):** `ssh deploy@<IP_DROPLETA>` pa `sudo whoami` (treba `root`).

Zaključavanje SSH-a (kao `deploy`, preko `sudo`):
```bash
sudo tee /etc/ssh/sshd_config.d/00-hardening.conf >/dev/null <<'EOF'
PermitRootLogin no
PasswordAuthentication no
EOF
sudo sshd -t && sudo systemctl reload ssh
```
**Provera:** `sudo sshd -T | grep -E "permitrootlogin|passwordauthentication"` daje `no` i `no`. Pre nego što zatvoriš staru sesiju, otvori još jednu novu i uveri se da `ssh deploy@...` i dalje radi.

Firewall na serveru (pored DO firewalla):
```bash
sudo ufw allow 22/tcp && sudo ufw allow 80/tcp && sudo ufw allow 443/tcp
sudo ufw --force enable
```
**Provera:** `sudo ufw status verbose`.

fail2ban:
```bash
sudo apt install -y fail2ban
sudo tee /etc/fail2ban/jail.local >/dev/null <<'EOF'
[DEFAULT]
bantime = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true

[kontit-login]
enabled = false
port = http,https
filter = kontit-login
logpath = /var/log/apache2/kontit_access.log
maxretry = 10
findtime = 10m
bantime = 1h
EOF
sudo tee /etc/fail2ban/filter.d/kontit-login.conf >/dev/null <<'EOF'
[Definition]
failregex = ^<HOST> \S+ \S+ \[[^\]]+\] "POST /login(\?\S*)? HTTP/[\d.]+" \d+
ignoreregex =
EOF
```
Jail `kontit-login` blokira IP posle 10 slanja forme za prijavu u 10 minuta (aplikacija još nema `login_throttling`, vidi 1.4). Jail je za sada isključen (`enabled = false`), jer log fajl još ne postoji. Uključuje se u koraku 3.8, kad Apache napravi log. Za sada samo:
```bash
sudo systemctl enable --now fail2ban
```
**Provera:** `sudo fail2ban-client status sshd`.

Automatska bezbednosna ažuriranja i swap (composer na 2 GB ume da zatreba):
```bash
sudo apt install -y unattended-upgrades && sudo dpkg-reconfigure -plow unattended-upgrades
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```
**Provera:** `free -h` (Swap 2.0Gi), `timedatectl | grep "Time zone"` (Europe/Belgrade).

### 3.3 Instalacija paketa

**Šta:** Apache, PHP 8.3 sa potrebnim ekstenzijama, MySQL 8, Composer, git.

```bash
sudo apt install -y apache2 mysql-server git unzip curl acl composer \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-intl php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-gd php8.3-opcache
```
**Provera:**
```bash
php -v                                  # PHP 8.3.x
composer --version
mysql --version                         # mysql  Ver 8.0.x
apache2 -v
php -m | grep -iE "intl|mbstring|xml|curl|zip|gd|pdo_mysql|sodium|opcache|ctype|iconv"
```
Sve ekstenzije iz liste moraju biti prisutne (`sodium` dolazi u `php8.3-common`).

PHP podešavanja (web i CLI):
```bash
sudo tee /etc/php/8.3/fpm/conf.d/99-kontit.ini >/dev/null <<'EOF'
date.timezone = Europe/Belgrade
memory_limit = 256M
upload_max_filesize = 25M
post_max_size = 30M
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
realpath_cache_size = 4096K
realpath_cache_ttl = 600
EOF
sudo tee /etc/php/8.3/cli/conf.d/99-kontit.ini >/dev/null <<'EOF'
date.timezone = Europe/Belgrade
EOF
```
`upload_max_filesize` mora biti veći od 20 MB (limit aplikacije za dokumente). `opcache.validate_timestamps = 0` znači da posle svakog deploy-a **moraš reload-ovati PHP-FPM** (to radi skripta u 3.13).
**Provera:** `php -i | grep date.timezone` i `php-fpm8.3 -i | grep upload_max_filesize`.

### 3.4 PHP-FPM pool (jedan korisnik za web, CLI, worker i cron)

**Šta:** poseban pool koji radi kao `deploy`, da `var/` ne pravi probleme sa dozvolama između web-a, cron-a i worker-a. Apache (`www-data`) čita samo `public/` i samo prosleđuje zahteve PHP-FPM-u preko socket-a.

```bash
sudo mv /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.3/fpm/pool.d/www.conf.disabled
sudo tee /etc/php/8.3/fpm/pool.d/kontit.conf >/dev/null <<'EOF'
[kontit]
user = deploy
group = deploy
listen = /run/php/php8.3-fpm-kontit.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
catch_workers_output = yes
decorate_workers_output = no
EOF
sudo php-fpm8.3 -t && sudo systemctl restart php8.3-fpm
```
`catch_workers_output = yes` je ono što logove aplikacije (koji idu na stderr) upisuje u `/var/log/php8.3-fpm.log`.
**Provera:** `ls -l /run/php/php8.3-fpm-kontit.sock` i `systemctl is-active php8.3-fpm` (active).

### 3.5 Baza podataka (korisnik sa ograničenim pravima)

**Šta:** baza `kontit` i korisnik `kontit` koji ima prava **samo** nad tom bazom. Root se u aplikaciji ne koristi.

```bash
sudo mysql_secure_installation          # ukloni anonimne korisnike i test bazu, zabrani udaljeni root
DB_PASS="$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 28)"; echo "$DB_PASS"
sudo mysql <<SQL
CREATE DATABASE kontit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'kontit'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON kontit.* TO 'kontit'@'localhost';
FLUSH PRIVILEGES;
SQL
```
Zapiši `DB_PASS` u menadžer lozinki (trebaće ti u koraku 3.7). Lozinka je namerno samo slova i brojevi, da se ne mora kodirati u `DATABASE_URL`-u.
Migracije prave tabele bez navedene kolacije, pa nasleđuju kolaciju baze (`utf8mb4_unicode_ci`).

**Provera:**
```bash
mysql -u kontit -p kontit -e "SELECT 1"          # traži lozinku, vraća 1
sudo ss -ltnp | grep 3306                        # sluša samo 127.0.0.1
```

### 3.6 Deploy koda

**Šta:** git repo na server. Pretpostavka je **privatni repo na GitHub-u** (pitanje 4). Pre ovoga na svom računaru commit-uj sve promene i pošalji ih:
```powershell
PS> git add -A ; git commit -m "..."        # prethodno pregledaj `git status`
PS> git remote add origin git@github.com:<ORG>/kontit.git
PS> git push -u origin master
```
Na serveru kao `deploy`: deploy ključ (samo čitanje) za taj repo.
```bash
ssh-keygen -t ed25519 -f ~/.ssh/kontit_deploy -N "" -C "kontit-deploy@server"
cat ~/.ssh/kontit_deploy.pub                  # nalepi u GitHub: Repo → Settings → Deploy keys (bez Write)
cat >> ~/.ssh/config <<'EOF'
Host github.com
  IdentityFile ~/.ssh/kontit_deploy
  IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config
ssh -T git@github.com                         # "Hi <repo>! You've successfully authenticated"

sudo mkdir -p /var/www/kontit && sudo chown deploy:deploy /var/www/kontit
git clone git@github.com:<ORG>/kontit.git /var/www/kontit
cd /var/www/kontit && git branch --show-current && ls composer.json
```
Ako ne želiš GitHub, alternativa je `rsync` sa računara (bez `vendor/`, `var/`, `.env.local`), ali tada nema jednostavnih ažuriranja.

### 3.7 `.env.local`, composer, migracije, optimizacija

**Redosled je bitan.** `.env.local` mora postojati **pre** `composer install`, jer `composer` na kraju pokreće `cache:clear`, a podrazumevani `APP_ENV` je `dev` (razvojni paketi nisu instalirani, pa bi pao).

```bash
cd /var/www/kontit
umask 077
cat > .env.local <<'EOF'
APP_ENV=prod
APP_DEBUG=0
DEFAULT_URI=https://kontit.epars.rs
DATABASE_URL="mysql://kontit:<DB_PASS>@127.0.0.1:3306/kontit?serverVersion=8.0.46&charset=utf8mb4"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
MAILER_DSN=null://null
NBS_PROVIDER=webservice
NBS_WS_USERNAME=<NBS_KORISNIK>
NBS_WS_PASSWORD=<NBS_LOZINKA>
NBS_WS_LICENCE=<NBS_LICENCA>
GOOGLE_CLIENT_ID=<GOOGLE_CLIENT_ID>
GOOGLE_CLIENT_SECRET=<GOOGLE_CLIENT_SECRET>
EOF
echo "APP_SECRET=$(openssl rand -hex 32)" >> .env.local
nano .env.local                                  # zameni sve <...>: <DB_PASS> i NBS/Google vrednosti
chmod 600 .env.local
```
`MAILER_DSN=null://null` je privremeno (mail "videmo naknadno"): aplikacija radi, ali **ne šalje mailove**. Kad izabereš SMTP, zameni tu liniju npr. sa `smtp://KORISNIK:LOZINKA@smtp.servis.com:587`, pa `php bin/console cache:clear` i `sudo systemctl restart kontit-worker`.
`<GOOGLE_CLIENT_ID>` i `<GOOGLE_CLIENT_SECRET>` pravis u Google konzoli (`docs/google-calendar-setup.md`), a redirect adresa je u koraku 3.10. Ako ih još nemaš, ostavi prazno (`GOOGLE_CLIENT_ID=`), pa ih dopuni kasnije. NBS vrednosti su iz tvog ugovora sa NBS-om.
Sačuvaj kopiju `APP_SECRET`-a u menadžeru lozinki. Ako ga izgubiš ili promeniš, svi korisnici moraju ponovo da povežu Google kalendar, a "zapamti me" prijave prestaju da važe.
**Provera:** `grep -c "<" .env.local` treba da vrati **0** (nije ostalo nijedno nepopunjeno mesto).

Instalacija:
```bash
composer install --no-dev --optimize-autoloader --no-interaction
```
**Provera:** `ls assets/vendor | head` (JS biblioteke preuzete, korak `importmap:install`). Ako je prazno: `php bin/console importmap:install`.

Migracije, provera šeme, assets, cache:
```bash
php bin/console doctrine:migrations:migrate --no-interaction          # ⚠ menja bazu
php bin/console doctrine:schema:validate
php bin/console asset-map:compile
php bin/console cache:clear && php bin/console cache:warmup
mkdir -p var/storage/user-documents var/exports var/log && chmod -R go-rwx var
```
**Provera:**
- `php bin/console doctrine:migrations:status` → `Executed Migrations: 24`, `New: 0`. (Toliko ih je u repou, plus nove ako ih dodaš.)
- `doctrine:schema:validate` → `[OK] The database schema is in sync`. Ako ovde prijavi razlike (rizik iz 1.6), **stani i javi mi**.
- `ls public/assets | head` (kompajlirani asset-i).
- `php bin/console about | grep -E "Environment|Debug"` → `prod`, `false`.

Prvi superadmin:
```bash
 php bin/console app:user:create <EMAIL> '<PRIVREMENA_LOZINKA>' --first-name=<IME> --last-name=<PREZIME>
```
(razmak ispred komande sprečava upis u istoriju ljuske). Lozinku promeni odmah po prvoj prijavi.
**Provera:** `php bin/console dbal:run-sql "SELECT id, email, roles FROM app_user"`.

### 3.8 Apache virtual host (document root = `public/`)

**Šta:** Apache sluša na 80, `DocumentRoot` je `public/`, a PHP prosleđuje FPM pool-u iz 3.4. `FallbackResource /index.php` šalje sve što nije fajl na Symfony front kontroler (zato `.htaccess` nije potreban).

```bash
sudo a2enmod proxy_fcgi setenvif rewrite headers expires
sudo a2dismod -f mpm_prefork php8.3 2>/dev/null; sudo a2enmod mpm_event   # event MPM + FPM (apt to obično već uradi)

sudo tee /etc/apache2/sites-available/kontit.conf >/dev/null <<'EOF'
<VirtualHost *:80>
    ServerName kontit.epars.rs
    DocumentRoot /var/www/kontit/public

    <Directory /var/www/kontit/public>
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>

    # PHP samo kroz FPM pool "kontit"
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:/run/php/php8.3-fpm-kontit.sock|fcgi://localhost"
    </FilesMatch>
    ProxyTimeout 120

    # dokumenti do 20 MB (PHP: upload_max_filesize 25M, post_max_size 30M)
    LimitRequestBody 31457280

    # sakrivene datoteke (.env, .git...) nikad ne idu napolje
    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>

    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    <Location /assets/>
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header set Cache-Control "public, immutable"
    </Location>

    ErrorLog ${APACHE_LOG_DIR}/kontit_error.log
    CustomLog ${APACHE_LOG_DIR}/kontit_access.log combined
</VirtualHost>
EOF
sudo a2ensite kontit && sudo a2dissite 000-default
sudo apache2ctl configtest && sudo systemctl reload apache2
sudo systemctl enable apache2
```
**Provera:**
```bash
curl -I -H "Host: kontit.epars.rs" http://127.0.0.1/login          # HTTP/1.1 200 OK
curl -I -H "Host: kontit.epars.rs" http://127.0.0.1/.env           # 403 ili 404, nikad 200
curl -I -H "Host: kontit.epars.rs" http://127.0.0.1/composer.json  # 404 (van public/)
```
`apache2ctl configtest` mora da kaže `Syntax OK`.

Uključi fail2ban zaštitu prijave (filter je napravljen u 3.2). Log `/var/log/apache2/kontit_access.log` nastaje tek posle prvog zahteva, zato prvo pošalji jedan:
```bash
curl -s -o /dev/null -H "Host: kontit.epars.rs" http://127.0.0.1/login
sudo sed -i '/^\[kontit-login\]/,/^\[/ s/enabled = false/enabled = true/' /etc/fail2ban/jail.local
sudo systemctl restart fail2ban
```
**Provera:** `sudo fail2ban-client status kontit-login` (jail postoji, `Currently banned: 0`).

### 3.9 DNS zapis (kod registratora domena)

Pre SSL-a. Kod registratora (ili DNS zone) dodaj:

| Tip | Host | Vrednost | TTL |
|---|---|---|---|
| A | `kontit` (u zoni `epars.rs`) | `<IP_DROPLETA>` | 300 |

**Provera** (može potrajati do nekoliko sati): `nslookup kontit.epars.rs` treba da vrati `<IP_DROPLETA>`.

### 3.10 SSL (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d kontit.epars.rs --redirect -m <EMAIL> --agree-tos -n
```
**Provera:**
```bash
curl -I https://kontit.epars.rs/login              # HTTP/2 200
curl -I http://kontit.epars.rs/login               # 301 na https
sudo certbot renew --dry-run               # "Congratulations, all simulated renewals succeeded"
systemctl list-timers | grep certbot       # automatska obnova je zakazana
```
Kad se uveriš da HTTPS radi, u fajl koji je certbot napravio (`sudo nano /etc/apache2/sites-available/kontit-le-ssl.conf`, unutar `<VirtualHost *:443>`) možeš dodati HSTS:
`Header always set Strict-Transport-Security "max-age=31536000"` pa `sudo apache2ctl configtest && sudo systemctl reload apache2`.

Ako koristiš Google kalendar: u Google konzoli dodaj redirect adresu `https://kontit.epars.rs/connect/google/check` (detalji u `docs/google-calendar-setup.md`).

### 3.11 Worker (systemd)

Bez workera **ne idu mailovi, izvozi u pozadini ni sinhronizacija Google kalendara.**

```bash
sudo tee /etc/systemd/system/kontit-worker.service >/dev/null <<'EOF'
[Unit]
Description=KontIT Messenger worker
After=network.target mysql.service

[Service]
User=deploy
Group=deploy
WorkingDirectory=/var/www/kontit
ExecStart=/usr/bin/php /var/www/kontit/bin/console messenger:consume async calendar --time-limit=3600 --memory-limit=192M
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
sudo systemctl daemon-reload && sudo systemctl enable --now kontit-worker
```
**Provera:**
```bash
systemctl is-active kontit-worker                 # active
journalctl -u kontit-worker -n 30 --no-pager      # bez grešaka
cd /var/www/kontit && php bin/console messenger:stats     # redovi async/calendar/failed
```
Posle jednog sata worker se sam uredno gasi (`--time-limit`) i systemd ga diže. Neuspele poruke: `php bin/console messenger:failed:show`.

### 3.12 Cron

```bash
crontab -e          # kao korisnik deploy
```
```cron
*/15 * * * * cd /var/www/kontit && /usr/bin/php bin/console app:approvals:auto-approve --no-interaction >> var/log/cron.log 2>&1
0 6 * * *    cd /var/www/kontit && /usr/bin/php bin/console app:tasks:generate --no-interaction >> var/log/cron.log 2>&1
0 7 * * *    cd /var/www/kontit && /usr/bin/php bin/console app:documents:check --no-interaction >> var/log/cron.log 2>&1
0 7 * * *    cd /var/www/kontit && /usr/bin/php bin/console app:notifications:due --no-interaction >> var/log/cron.log 2>&1
# opciono, samo uz NBS kredencijale (raspored je predlog, SPEC ga ne propisuje):
# 0 3 * * 0  cd /var/www/kontit && /usr/bin/php bin/console app:nbs:sync --no-interaction >> var/log/cron.log 2>&1
```
Sistemska zona je već `Europe/Belgrade`, pa vremena važe kako su napisana. Rotacija loga:
```bash
sudo tee /etc/logrotate.d/kontit >/dev/null <<'EOF'
/var/www/kontit/var/log/cron.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    su deploy deploy
}
EOF
```
**Provera:**
```bash
crontab -l
cd /var/www/kontit && php bin/console app:approvals:auto-approve ; echo "izlaz: $?"     # izlaz: 0
journalctl -u cron --since "1 hour ago" --no-pager | tail
```
Posle 15 minuta u `var/log/cron.log` mora da postoji bar jedan zapis bez greške.

### 3.13 Backup baze i fajlova

Tri nivoa: (a) DigitalOcean nedeljni backup Droplet-a (uključen u 3.1), (b) dnevni dump baze + upload-i na serveru, (c) kopija **van servera** u DigitalOcean Spaces.

Podaci koji se čuvaju: baza, `var/storage` (dokumenti korisnika), `var/exports` i `.env.local` (njega čuvaj odvojeno, u menadžeru lozinki, ne u istom bekapu na istom mestu).

```bash
mkdir -p ~/bin
sudo install -d -o deploy -g deploy /var/backups/kontit
cat > ~/.my.cnf <<'EOF'
[client]
user=kontit
password=<DB_PASS>
EOF
chmod 600 ~/.my.cnf

cat > ~/bin/backup.sh <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
DIR=/var/backups/kontit
STAMP=$(date +%F_%H%M)
mysqldump --no-tablespaces --single-transaction --routines --triggers kontit | gzip > "$DIR/db-$STAMP.sql.gz"
tar -czf "$DIR/files-$STAMP.tar.gz" -C /var/www/kontit var/storage var/exports
find "$DIR" -type f -mtime +14 -delete        # ⚠ briše SAMO stare kopije (starije od 14 dana)
EOF
chmod 700 ~/bin/backup.sh
(crontab -l; echo '30 2 * * * /home/deploy/bin/backup.sh >> /var/www/kontit/var/log/cron.log 2>&1') | crontab -
```
**Provera:** `~/bin/backup.sh && ls -lh /var/backups/kontit` (dva fajla, nisu prazna).

Kopija van servera (DigitalOcean Spaces, oko 5 USD): napravi Space i **Spaces access key** (Settings → API → Spaces Keys).
```bash
sudo apt install -y rclone
rclone config                                   # n → ime: spaces, tip: s3, provider: DigitalOcean, endpoint: fra1.digitaloceanspaces.com
rclone copy /var/backups/kontit spaces:<NAZIV_SPACE_A>/kontit      # proveri ručno
(crontab -l; echo '45 2 * * * rclone copy /var/backups/kontit spaces:<NAZIV_SPACE_A>/kontit >> /var/www/kontit/var/log/cron.log 2>&1') | crontab -
```
Ključevi se čuvaju samo u `~/.config/rclone/rclone.conf` na serveru.
**Provera:** `rclone ls spaces:<NAZIV_SPACE_A>/kontit`.

**Test vraćanja (restore) na PRIVREMENU bazu, ne na živu:**
```bash
sudo mysql -e "CREATE DATABASE kontit_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
LATEST=$(ls -t /var/backups/kontit/db-*.sql.gz | head -1)
gunzip -c "$LATEST" | sudo mysql kontit_restore
sudo mysql kontit_restore -e "SELECT COUNT(*) AS korisnika FROM app_user; SELECT COUNT(*) AS migracija FROM doctrine_migration_versions;"
sudo mysql -e "DROP DATABASE kontit_restore"          # ⚠ briše samo privremenu bazu kontit_restore
```
Broj korisnika i migracija mora da odgovara živoj bazi.

### 3.14 Ažuriranje aplikacije kasnije

Skripta `/home/deploy/deploy.sh` (van repoa):
```bash
cat > ~/deploy.sh <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
cd /var/www/kontit
/home/deploy/bin/backup.sh                                # dump baze pre migracija
git pull --ff-only origin master
composer install --no-dev --optimize-autoloader --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration   # ⚠ menja bazu
php bin/console asset-map:compile
php bin/console cache:clear && php bin/console cache:warmup
sudo systemctl reload php8.3-fpm                          # opcache (validate_timestamps=0)
php bin/console messenger:stop-workers                    # worker se sam podiže (systemd)
echo "Deploy gotov: $(git rev-parse --short HEAD)"
EOF
chmod 700 ~/deploy.sh
echo 'deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm' | sudo tee /etc/sudoers.d/deploy-reload
sudo visudo -cf /etc/sudoers.d/deploy-reload
```
Ažuriranje: `~/deploy.sh`.
**Provera:** u pregledaču osveži aplikaciju, `git -C /var/www/kontit log --oneline -1` odgovara onome što si poslao, `php bin/console doctrine:migrations:status` ima `New: 0`.

**Vraćanje unazad:** `git checkout <prethodni_commit>` pa isti koraci. Ako je migracija menjala šemu, vrati bazu iz dumpa koji je skripta napravila pre migracije. ⚠ To briše trenutne podatke u bazi, pa uvek prvo pitaj sebe da li je to potrebno.

---

## 4. FAZA 4: Provera posle deploy-a (checklist)

- [ ] `https://kontit.epars.rs` se učitava sa važećim sertifikatom, a `http://` preusmerava na `https://`.
- [ ] `sudo certbot renew --dry-run` prolazi.
- [ ] **Prijava** prvim superadminom radi. Pogrešna lozinka daje poruku, ne grešku 500.
- [ ] Posle prijave: dashboard, lista klijenata, lista zadataka i `/profile` se otvaraju (stranice sa CSS-om i JS-om, nema 404 u konzoli pregledača za `/assets/...`).
- [ ] **Upload radi:** na korisniku dodaj dokument (PDF do 20 MB). Fajl se pojavljuje u `var/storage/user-documents` i može da se preuzme. Fajl od 25 MB je odbijen porukom, ne greškom servera.
- [ ] **Izvoz** (Excel/PDF) iz liste klijenata se pravi i preuzima (proverava worker i `var/exports`).
- [ ] **Mejl (tek kad se odluči nalog):** do tada je `MAILER_DSN=null://null` i mailovi se ne šalju. Kad se postavi SMTP: "Zaboravljena lozinka" na tvoj mejl. Stiže, pošiljalac nije `kontit.local` (vidi 1.4, tačka 1), link u mejlu vodi na `https://kontit.epars.rs` (proverava `DEFAULT_URI`).
- [ ] `php bin/console messenger:stats`: red `async` se prazni, `failed` je 0.
- [ ] `systemctl is-active kontit-worker` je `active`; `journalctl -u kontit-worker` bez grešaka.
- [ ] **Cron radi:** `var/log/cron.log` ima zapise nakon 15 minuta; `crontab -l` ima 4 komande + backup.
- [ ] **Greške se loguju:** pri prvoj stvarnoj grešci (500) proveri `tail /var/log/php8.3-fpm.log /var/log/apache2/kontit_error.log`. Aplikacija beleži samo greške, a logrotate za te fajlove je podrazumevano uključen.
- [ ] **Backup postoji:** `ls -lh /var/backups/kontit`, kopija u Spaces-u, uključen DO Backup (Droplet → Backups).
- [ ] **Restore test urađen** (korak 3.13), podaci odgovaraju.
- [ ] Zona i datum: `date` na serveru je lokalno vreme, "danas" u aplikaciji se poklapa.
- [ ] Debug isključen: stranica sa nepostojećom adresom prikazuje običnu poruku, ne Symfony debug stranicu; `https://kontit.epars.rs/_profiler` daje 404.
- [ ] Prijava je ograničena: posle 10 slanja forme za prijavu u 10 minuta `sudo fail2ban-client status kontit-login` prikazuje blokiranu IP adresu.
- [ ] (Google) Povezivanje kalendara na profilu radi i redirect je na tvom domenu.
- [ ] (NBS) "Osveži iz NBS" na klijentu radi (server mora imati izlaz ka `webservices.nbs.rs`).

---

## 5. Dodatak

### Kratak rečnik putanja na serveru

| Šta | Gde |
|---|---|
| Kod | `/var/www/kontit` |
| Tajne (`APP_SECRET`, baza, SMTP) | `/var/www/kontit/.env.local` (mod 600, nije u git-u) |
| Dokumenti korisnika | `/var/www/kontit/var/storage/user-documents` |
| Izvozi | `/var/www/kontit/var/exports` |
| Cron log | `/var/www/kontit/var/log/cron.log` |
| Logovi aplikacije (greške) | `/var/log/php8.3-fpm.log` |
| Logovi Apache-a | `/var/log/apache2/kontit_{access,error}.log` |
| Worker | `journalctl -u kontit-worker` |
| Backup | `/var/backups/kontit` i Spaces |

### Najčešći problemi

| Simptom | Uzrok / rešenje |
|---|---|
| Bela stranica / 502 | `systemctl status php8.3-fpm`; da li postoji `/run/php/php8.3-fpm-kontit.sock`; `/var/log/apache2/kontit_error.log` |
| 500 odmah posle deploy-a | nedostaje `.env.local`, ili `APP_ENV` nije `prod`; `php bin/console cache:clear` |
| Stranice bez stila | nije pokrenut `asset-map:compile` ili `assets/vendor` je prazan (`importmap:install`) |
| Mailovi ne stižu | worker ne radi, loš `MAILER_DSN`, ili pošiljalac `kontit.local` (1.4); `messenger:failed:show` |
| `schema:validate` prijavljuje razlike | rizik MariaDB/MySQL (1.6). Ne popravljaj na živoj bazi, javi mi |
| Posle izmene koda nema promene | PHP-FPM nije reload-ovan (opcache): `sudo systemctl reload php8.3-fpm` |
| Dokument se ne šalje (413) | `LimitRequestBody` u Apache vhost-u i `upload_max_filesize`/`post_max_size` u PHP-u |
| Google kalendar traži ponovno povezivanje | promenjen `APP_SECRET` |
