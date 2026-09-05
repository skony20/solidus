# Wdrożenie Solidusa na Cyber-Folks

Automatyczne wdrożenie po pushu na `main`: `.github/workflows/deploy.yml`.

## Układ na serwerze — jeden adres dla SPA i API

```
solidus.norios.pl              ← zbudowana SPA (frontend/dist), katalog domeny
solidus.norios.pl/api-app/     ← Yii3 (backend), PODKATALOG tego samego katalogu
solidus.norios.pl/api/...      ← przepisywane przez .htaccess SPA na api-app/public/index.php
```

**To nie jest wybór estetyczny — to jedyny układ, który na tym hostingu w ogóle działa.** Dwa fakty, sprawdzone bezpośrednio na tym koncie, nie w dokumentacji dostawcy:

1. **Hosting uruchamia PHP wyłącznie dla plików fizycznie leżących w katalogu danej domeny.** Katalog aplikacji obok niego (nawet przez dowiązanie symboliczne) zwraca 500 z pustą treścią na każde żądanie — sprawdzone również przez działającą domenę, nie tylko przez zepsutą subdomenę niżej.
2. **Osobna subdomena `api.*` (trzeciego poziomu) nie wykonuje PHP w ogóle**, niezależnie od tego, jaką wersję PHP i jaki katalog wskazano dla niej w panelu. Przyczyna nieznana, po stronie hostingu — próba naprawy kosztowała więcej niż przejście na jeden adres.

Efekt uboczny jest pozytywny: SPA i API mają to samo pochodzenie (origin), więc CORS przestaje mieć znaczenie w praktyce (kod CORS zostaje w backendzie — przyda się np. przyszłemu klientowi mobilnemu).

**Bezpieczeństwo katalogu backendu, mimo że leży „w środku" SPA, pilnuje wyłącznie `.htaccess`:**
- `backend/.htaccess` (`Deny from all`, **stara składnia** — `Require all denied` z Apache 2.4 jest na tym serwerze po cichu ignorowana, sprawdzone) blokuje CAŁY katalog aplikacji.
- `backend/public/.htaccess` daje jawne zezwolenie wyłącznie dla katalogu `public/` (kontra blokada wyżej) i deklaruje uchwyt PHP.
- `frontend/public/.htaccess` ma regułę `RewriteRule ^api/ api-app/public/index.php [L]` PRZED regułą fallbacku SPA — inaczej fallback przechwyciłby żądania do API i zwracał im `index.html` zamiast JSON-a.

Runner buduje `vendor/` (Composer) i `dist/` (Vite), bo żadnego z nich nie ma w repozytorium, a hosting współdzielony nie ma Node.js. Na serwer jedzie gotowy wynik.

---

## Krok 0. Sprawdź wersję PHP — warunek konieczny

`composer.json` ma `"php": "~8.5.0"`. Composer zapisuje tę wersję w `vendor/composer/platform_check.php`, więc **na PHP starszym niż 8.5 aplikacja nie wstanie w ogóle** — zwróci błąd 500 przy pierwszym żądaniu.

**Na koncie `lyvelmikov` (s66.cyber-folks.pl) PHP 8.5 jest dostępne, ale nie jako domyślne w konsoli:**

```bash
php -v        # 8.2 - domyslna wersja konta, NIE uzywac do Solidusa
php85 -v      # 8.5 - ta wersja liczy sie dla aplikacji
```

**Każdą komendę projektu wywołuj przez `php85`, nigdy przez samo `php`:**

```bash
php85 yii migrate:up          # zamiast `php yii migrate:up`
php85 yii admin:grant ...
php85 -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Pełna ścieżka, gdyby `php85` zniknęło z PATH: `/opt/alt/php85/usr/bin/php`.

Wersja PHP dla **strony WWW** (nie konsoli) ustawia się w panelu, dla domeny `norios.pl` (backend jedzie teraz jako jej podkatalog — nie ma tu osobnej subdomeny do konfigurowania).

---

## Krok 1. Gałąź `main`

Workflow reaguje na push do `main`. Sprawdź, że tam pracujesz:

```bash
git branch --show-current
```

---

## Krok 2. Klucz SSH do wdrożeń

```bash
ssh-keygen -t ed25519 -C "github-deploy-solidus" -f ~/.ssh/solidus_deploy -N ""
```

Klucz publiczny na serwer:

```bash
ssh -p 222 lyvelmikov@s66.cyber-folks.pl
mkdir -p ~/.ssh && chmod 700 ~/.ssh
echo "ssh-ed25519 AAAA... github-deploy-solidus" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

Sprawdź, czy działa bez hasła (dokładnie tak samo łączy się runner GitHuba):

```bash
ssh -i ~/.ssh/solidus_deploy -p 222 lyvelmikov@s66.cyber-folks.pl "echo polaczenie-ok"
```

Jeśli dostajesz `Corrupted MAC on input` — to problem samego łącza (router, Wi-Fi, VPN), nie klucza; nie wpływa na runnera GitHuba, który łączy się z innej sieci. Można spróbować `-o Ciphers=aes128-ctr -o MACs=hmac-sha2-512`, ale jeśli i to zawiedzie, wystarczy sprawdzić klucz bezpośrednio w Actions (krok 5) zamiast lokalnie.

---

## Krok 3. Sekrety i zmienne w GitHubie

`Settings → Secrets and variables → Actions`.

**Zakładka Secrets:**

| Nazwa | Wartość dla `norios.pl` | Uwagi |
|---|---|---|
| `SSH_KEY` | zawartość `~/.ssh/solidus_deploy` (cały plik, bez `.pub`) | Z liniami `-----BEGIN…`/`-----END…` włącznie |
| `SSH_HOST` | `s66.cyber-folks.pl` | Adres serwera, nie domena aplikacji |
| `SSH_USER` | `lyvelmikov` | |
| `SSH_PORT` | `222` | Można pominąć — workflow i tak domyślnie używa 222 |
| `REMOTE_WEB_DIR` | `/home/lyvelmikov/domains/norios.pl/public_html/solidus` | Katalog domeny SPA |
| `REMOTE_APP_DIR` | `/home/lyvelmikov/domains/norios.pl/public_html/solidus/api-app` | **Podkatalog** powyższego — nie osobna ścieżka |

**Zakładka Variables:**

| Nazwa | Wartość |
|---|---|
| `VITE_API_URL` | `https://solidus.norios.pl` |

`VITE_API_URL` jest tym samym adresem co SPA (jeden origin) — **bez** `/api` na końcu, ten fragment dokleja już kod frontendu przy wywołaniach. Adres jest wkompilowany w pliki SPA w czasie budowania — zmiana wymaga ponownego wdrożenia, nie edycji na serwerze.

---

## Krok 4. Przygotuj serwer

### 4a. Katalogi

```bash
ssh -p 222 lyvelmikov@s66.cyber-folks.pl
mkdir -p ~/domains/norios.pl/public_html/solidus/api-app/public
mkdir -p ~/domains/norios.pl/public_html/solidus/api-app/runtime
chmod 775 ~/domains/norios.pl/public_html/solidus/api-app/runtime
```

### 4b. Baza danych

Panel: **Bazy danych → Dodaj**. Zanotuj nazwę bazy, użytkownika i hasło (Cyber-Folks dokleja do nich prefiks konta).

Sprawdź wersję — **to jest MariaDB, nie MySQL 8**:

```bash
mysql -u UZYTKOWNIK_BAZY -p NAZWA_BAZY -e "SELECT VERSION();"
```

Domyślna kolacja bazy założonej przez panel to zwykle `utf8mb3_general_ci` — **nie** utf8mb4. Migracje Solidusa mają `utf8mb4_unicode_ci` wymuszone jawnie w każdej tabeli (nie `utf8mb4_0900_ai_ci`, który istnieje wyłącznie w MySQL 8 i dawał błąd 1273 „Unknown collation" na tym serwerze), więc polskie znaki działają mimo to — ale każda NOWA tabela dodana bez jawnego `CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` odziedziczy utf8mb3 i po cichu obetnie takie znaki.

### 4c. Plik `.env`

Ten plik **nie jest wysyłany przez wdrożenie** (na liście `--exclude`) — tworzysz go raz, ręcznie:

```bash
cd ~/domains/norios.pl/public_html/solidus/api-app
cat > .env <<'EOF'
APP_ENV=prod
APP_DEBUG=false

DB_HOST=localhost
DB_PORT=3306
DB_NAME=nazwa_bazy
DB_USER=uzytkownik_bazy
DB_PASSWORD=haslo_bazy

JWT_SECRET=TU_WKLEJ_WYGENEROWANY_SEKRET
JWT_ACCESS_TTL=900
JWT_REFRESH_TTL=2592000
JWT_ISSUER=solidus

FRONTEND_ORIGIN=https://solidus.norios.pl
EOF
chmod 600 .env
```

Sekret JWT wygeneruj na serwerze, wersją PHP 8.5:

```bash
php85 -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Trzy rzeczy łatwe do przeoczenia:

- **`APP_DEBUG=false` jest obowiązkowe.** Przy `true` strona błędu pokazuje ślad stosu razem z hasłem do bazy.
- **`JWT_SECRET` musi mieć min. 32 znaki.** Aplikacja odmówi startu z krótszym — celowo, bo pusty sekret pozwoliłby każdemu wystawić sobie token dowolnego użytkownika.
- **`FRONTEND_ORIGIN` = dokładnie ten sam adres, pod którym stoi SPA** (`https://solidus.norios.pl`, bez ukośnika na końcu). Skoro API i SPA mają teraz jedno pochodzenie, ta wartość rzadko kiedy zadziała inaczej niż poprawnie — ale zostaje w kodzie jako zabezpieczenie na wypadek żądań z innego originu (np. narzędzia deweloperskie, przyszły klient mobilny).

---

## Krok 5. Pierwsze wdrożenie

```bash
git push origin main
```

Postęp: zakładka **Actions**. Można też uruchomić ręcznie: **Actions → Deploy na serwer (SSH) → Run workflow**.

Po zakończeniu — migracje, ręcznie, wersją PHP 8.5:

```bash
ssh -p 222 lyvelmikov@s66.cyber-folks.pl
cd ~/domains/norios.pl/public_html/solidus/api-app
php85 yii migrate:up
```

Migracje **nie idą automatycznie** — decyzja projektowa: migracja odpalona przy każdym pushu potrafi zablokować tabelę w środku dnia pracy biura. Uruchamiasz je świadomie, po każdym wdrożeniu, które dodało pliki w `Module/*/Migration/`.

Pierwsze biuro (ekranu rejestracji jeszcze nie ma):

```bash
curl -s -X POST https://solidus.norios.pl/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"tenantName":"Nazwa biura","email":"twoj@email.pl","password":"tu-dlugie-haslo","name":"Imie Nazwisko"}'
```

W odpowiedzi jest `slug` biura. Konto administratora systemu (panel `/admin/cennik` i `/admin/biura`) — z konsoli:

```bash
php85 yii admin:grant slug-biura twoj@email.pl
```

---

## Krok 6. Sprawdź, czy działa

```bash
curl -s https://solidus.norios.pl/api/pricing              # JSON z cennikiem
curl -s -o /dev/null -w "%{http_code}\n" https://solidus.norios.pl/api-app/.env            # ma byc 403
curl -s -o /dev/null -w "%{http_code}\n" https://solidus.norios.pl/api-app/src/bootstrap.php  # ma byc 403
```

W przeglądarce:

1. `https://solidus.norios.pl` — strona informacyjna z cennikiem (dowód, że SPA rozmawia z API).
2. `https://solidus.norios.pl/klienci` — **odśwież stronę**. 404 tutaj = `.htaccess` nie zadziałał.
3. Zaloguj się, odśwież — sesja ma przetrwać.

---

## Co robić, gdy coś nie działa

| Objaw | Przyczyna |
|---|---|
| Błąd 500 na całym API, nawet na pustym pliku `.php` | Katalog backendu wylądował poza `public_html` albo poza katalogiem domeny SPA — na tym hostingu to zawsze 500, patrz sekcja o układzie na początku |
| 404 na `/api/...` | Reguła `RewriteRule ^api/ ...` w `.htaccess` SPA brakuje albo stoi PO regule fallbacku, nie przed nią |
| `Brak tokenu dostepowego` mimo zalogowania | Serwer zjada nagłówek `Authorization` — patrz reguła w `backend/public/.htaccess` |
| 404 po odświeżeniu na `/klienci` | `.htaccess` SPA nie dojechał albo wyłączony `mod_rewrite` |
| Cennik pusty, reszta strony działa | `VITE_API_URL` wskazuje zły adres — zmiana wymaga **ponownego wdrożenia**, nie edycji na serwerze |
| Migracja pada na `Unknown collation` | Migracja używa `utf8mb4_0900_ai_ci` (MySQL 8) na serwerze z MariaDB — popraw na `utf8mb4_unicode_ci` |
| `php yii migrate:up` nic nie robi / błąd wersji | Użyj `php85 yii migrate:up`, nie `php yii migrate:up` — domyślne `php` na koncie to 8.2 |
| Zmiany w kodzie nie widać | Cache opcode PHP — restart PHP z panelu Cyber-Folks |

### Czego wdrożenie celowo nie robi

- **Nie kasuje plików usuniętych z repozytorium** (brak `--delete` w `rsync`). Chroni to `.env`, `runtime/` i dane na serwerze; plik skasowany w repo trzeba usunąć ręcznie.
- **Nie odpala migracji** — patrz krok 5.
- **Nie nadpisuje `.env`.**
- **Nie robi kopii zapasowej.** Backup bazy przed wdrożeniem z migracjami jest po Twojej stronie: `mysqldump -u UZYTKOWNIK_BAZY -p NAZWA_BAZY > backup-$(date +%F).sql`
