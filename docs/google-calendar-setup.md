# Google kalendar: podešavanje (SPEC 8n.3)

Aplikacija upisuje zadatke i odsustva korisnika u **poseban kalendar „KontIT“** u njegovom Google nalogu.
Smer je samo **aplikacija → Google**; izmene u Google-u se ne vraćaju u aplikaciju.
Traži se najuži pristup: `https://www.googleapis.com/auth/calendar.app.created` (aplikacija vidi i menja samo kalendare
koje je sama napravila) i `email` (da se na profilu vidi koji je nalog povezan).

Dok ovo nije podešeno, korisnici mogu da koriste **ICS link** sa profila (radi sa Google, Outlook i iPhone kalendarom, samo čitanje).

## 1. Projekat i Calendar API

1. Otvori [Google Cloud konzolu](https://console.cloud.google.com/) i prijavi se nalogom firme.
2. Gore levo izaberi **Select a project → New project**, naziv npr. `KontIT app`, pa **Create**.
3. U meniju **APIs & Services → Library** potraži **Google Calendar API** i klikni **Enable**.

## 2. OAuth consent screen

1. **APIs & Services → OAuth consent screen** (u novijoj konzoli: **Google Auth Platform → Branding**).
2. Popuni: naziv aplikacije **KontIT app**, logo (`public/brand/kontit-logo@2x.png`), kontakt mail podrške,
   domen aplikacije i mail programera.
3. **Audience / User type**:
   - Ako firma koristi **Google Workspace** (mailovi na domenu firme), izaberi **Internal**: radi samo za naloge iz firme
     i **nije potrebna Google verifikacija**.
   - Ako zaposleni koriste obične **Gmail** naloge, izaberi **External**. Dok je aplikacija u statusu **Testing**, radi samo
     za naloge dodate kao **Test users** (do 100), a Google može da poništi povezivanje posle 7 dana. Za stalni rad klikni
     **Publish app**. Za scope `calendar.app.created` Google može da traži verifikaciju; proveri u trenutnoj Google dokumentaciji
     ("OAuth app verification") pre objavljivanja.
4. **Scopes / Data access**: dodaj `.../auth/calendar.app.created` i `email`.

## 3. OAuth Client ID

1. **APIs & Services → Credentials → Create credentials → OAuth client ID**.
2. Application type: **Web application**, naziv npr. `KontIT web`.
3. **Authorized redirect URIs**: `https://<domen>/connect/google/check`
   (za lokalni rad i `http://127.0.0.1:8000/connect/google/check`).
4. **Create**: kopiraj **Client ID** i **Client secret**.

## 4. Upis u `.env.local` (nikad u git)

```
GOOGLE_CLIENT_ID=1234567890-abc.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-...
```

Zatim `php bin/console cache:clear`.

## 5. U aplikaciji

- **Podešavanja → Vrste odsustva i podešavanja → Google kalendar**: uključi za firmu (podrazumevano uključeno) i po želji
  „Šefovi dobijaju i odsustva svog tima“.
- Svaki korisnik: **Profil → Google kalendar → Poveži Google kalendar**. Posle potvrde u Google-u aplikacija napravi kalendar
  „KontIT“ i pošalje sve buduće događaje.
- Slanje ide u pozadini preko Messenger-a, transport `calendar`. Worker mora da radi:
  `php bin/console messenger:consume async calendar` (u produkciji pod supervisor-om).
  Neuspeh se ponavlja do 5 puta (1, 2, 4, 8, 16 min); posle 5 neuspeha zaredom korisnik dobija obaveštenje.
- Popravka za jednog korisnika: `php bin/console app:calendar:resync korisnik@firma.rs`.

## Bezbednost

- Tokeni su u bazi šifrovani (`App\Google\TokenCipher`: libsodium kad je PHP ekstenzija `sodium` uključena, inače OpenSSL AES-256-GCM).
  Ključ je izveden iz `APP_SECRET`; ako se `APP_SECRET` promeni, korisnici treba ponovo da povežu kalendar.
- Odvezivanje na profilu opoziva pristup u Google-u i briše vezu; kalendar „KontIT“ ostaje korisniku i može sam da ga obriše.

## Provera bez Google naloga (samo razvoj)

U `.env.dev.local` upiši `GOOGLE_CALENDAR_FAKE=1`: događaji se tada upisuju u `var/google-fake.json` umesto u Google
(kalendar sa ID-jem koji počinje sa `fail` simulira grešku). U produkciji se ova opcija ignoriše.
