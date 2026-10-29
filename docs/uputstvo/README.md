# Uputstvo u aplikaciji: kako se menja

Uputstvo se prikazuje u aplikaciji na **/uputstvo** (meni **Pomoć → Uputstvo**). Svaki fajl je jedno uputstvo:

| Fajl | Uputstvo | Ko ga vidi |
|---|---|---|
| `radnik.md` | Uputstvo za radnika | svi (radnik, šef, admin, superadmin) |
| `sef.md` | Uputstvo za šefa | šef, admin, superadmin |
| `admin.md` | Uputstvo za admina | admin i superadmin |

Izmena se vidi odmah posle snimanja fajla, bez ponovnog pokretanja aplikacije: keš se osvežava po vremenu izmene fajla.

## Pravila pisanja

- `# Naslov` (samo jedan, na vrhu) postaje naslov strane.
- `## Poglavlje` i `### Potpoglavlje` ulaze u **Sadržaj** na vrhu, sa linkovima. Poglavlja se sama numerišu.
- Nazive dugmadi i menija piši **podebljano**, tačno kako stoje u aplikaciji.
- Tabele, liste i numerisani koraci su običan Markdown.

## Slike

1. Sliku sačuvaj u `docs/uputstvo/slike/` (PNG, JPG, WEBP ili GIF; ime samo slovima bez kvačica, ciframa, `-` i `_`, npr. `radnik-prijava.png`).
2. U tekstu zameni placeholder, npr. `[SLIKA: ekran za prijavu sa poljima Email i Lozinka]`, sa:

   ```
   ![Ekran za prijavu](radnik-prijava.png)
   ```

Slike se ne serviraju iz `public/`, nego kroz aplikaciju, samo prijavljenim korisnicima, jer mogu da pokažu podatke klijenata.

Dok slika ne postoji, red `[SLIKA: opis]` (sam u svom pasusu) prikazuje se kao isprekidan okvir „Slika: opis“.

## Okviri za napomene

```
> **Savet:** zeleni okvir, za korisne prečice.

> **Pažnja:** narandžasti okvir, za stvari koje mogu da pođu naopako.

> **Važno:** isto kao Pažnja.
```

## Novo uputstvo (nova uloga)

Dodaj slučaj u `src/Enum/HelpGuide.php` (naziv, rola, ikonica), dopuni `requirements` rute u `HelpController::show` i napravi fajl `{vrednost}.md`.
