# Wdrożenie Solidusa na Cyber-Folks

Automatyczne wdrożenie po pushu na `main`: `.github/workflows/deploy.yml`.

Solidus to **dwie aplikacje**, więc i cele wdrożenia są dwa:

```
solidus.norios.pl      → public_html/solidus/     ← zbudowana SPA (pliki statyczne)
api.solidus.norios.pl  → solidus-api/public/      ← Yii3, katalog główny subdomeny
                         solidus-api/             ← kod aplikacji, OBOK public_html
                         solidus-api/.env         ← sekrety, nigdy w repozytorium
```

Runner GitHuba buduje `vendor/` (Composer) i `dist/` (Vite), bo żadnego z nich nie ma w repozytorium, a hosting współdzielony nie ma Node.js. Na serwer jedzie gotowy wynik.

---

## Krok 0. Sprawdź wersję PHP — to jest warunek konieczny

`composer.json` ma `"php": "~8.5.0"`. Composer zapisuje tę wersję w `vendor/composer/platform_check.php`, więc **na PHP starszym niż 8.5 aplikacja nie wstanie w ogóle** — zwróci błąd 500 przy pierwszym żądaniu.

Po zalogowaniu przez SSH:

```bash
php -v
# oraz sprawdź, jakie wersje daje hosting:
ls /usr/local/php*/bin/php 2>/dev/null || ls /opt/alt/php*/usr/bin/php 2>/dev/null
```

W panelu Cyber-Folks wersję PHP ustawia się osobno **dla każdej domeny i subdomeny** — subdomena `api.` musi mieć 8.5, główna domena nie ma znaczenia (serwuje same pliki statyczne).

**Jeśli hostingu nie stać na 8.5:** nie ma sensu iść dalej z wdrożeniem. Trzeba wtedy poluzować wymaganie w `composer.json` do wersji dostępnej na serwerze (`~8.4.0`), przebudować `composer.lock` i przetestować projekt na tej wersji — kod nie używa dziś składni wyłącznej dla 8.5, ale to trzeba potwierdzić testami, a nie założyć.

Wersja PHP w kroku „Zainstaluj PHP 8.5" w workflow **musi być ta sama, co na serwerze**.

---

## Krok 1. Gałąź `main`

Repozytorium jest dziś na gałęzi `master`, a workflow reaguje na `main`. Wybierz jedno:

```bash
# albo zmień nazwę gałęzi (zalecane — zgodne z ustawieniem repo na GitHubie)
git branch -m master main
git push -u origin main
# w GitHubie: Settings → General → Default branch → main

# albo zostaw master i popraw wyzwalacz w .github/workflows/deploy.yml:
#   branches: [master]
```

---

## Krok 2. Klucz SSH do wdrożeń

Klucz generujesz **u siebie**, na serwer trafia tylko część publiczna, do GitHuba tylko prywatna.

```bash
ssh-keygen -t ed25519 -C "github-deploy-solidus" -f ~/.ssh/solidus_deploy -N ""
```

Powstaną dwa pliki: `solidus_deploy` (prywatny) i `solidus_deploy.pub` (publiczny).

Klucz publiczny dopisz na serwerze do `~/.ssh/authorized_keys` — przez panel Cyber-Folks (SSH → klucze) albo ręcznie:

```bash
ssh -p 222 lyvelmikov@s66.cyber-folks.pl
mkdir -p ~/.ssh && chmod 700 ~/.ssh
echo "ssh-ed25519 AAAA... github-deploy-solidus" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

Sprawdź, czy działa bez hasła:

```bash
ssh -i ~/.ssh/solidus_deploy -p 222 lyvelmikov@s66.cyber-folks.pl "echo polaczenie-ok"
```

---

## Krok 3. Sekrety i zmienne w GitHubie

`Settings → Secrets and variables → Actions`.

**Zakładka Secrets** (`New repository secret`):

| Nazwa | Wartość | Uwagi |
|---|---|---|
| `SSH_KEY` | cała zawartość `~/.ssh/solidus_deploy` | Z linijkami `-----BEGIN…` i `-----END…` włącznie |
| `SSH_HOST` | `s66.cyber-folks.pl` | Adres serwera z panelu, nie domena |
| `SSH_USER` | `lyvelmikov` | |
| `SSH_PORT` | `222` | Można pominąć — workflow domyślnie używa 222 |
| `REMOTE_APP_DIR` | `/home/lyvelmikov/domains/norios.pl/solidus-api` | Pełna ścieżka, **bez** ukośnika na końcu |
| `REMOTE_WEB_DIR` | `/home/lyvelmikov/domains/norios.pl/public_html/solidus` | Katalog główny subdomeny SPA |

**Zakładka Variables** (`New repository variable`):

| Nazwa | Wartość |
|---|---|
| `VITE_API_URL` | `https://api.solidus.norios.pl` |

`VITE_API_URL` jest zmienną, a nie sekretem, bo i tak trafia do plików JavaScript widocznych w przeglądarce. **Adres API jest wkompilowany w SPA** — jego zmiana wymaga ponownego zbudowania aplikacji, sama edycja plików na serwerze nic nie da.

Po SSH ścieżki są pełne. To, co panel FTP pokazuje jako katalog główny, jest widokiem po `chroot` i wygląda inaczej — sprawdź faktyczną ścieżkę komendą `pwd` po zalogowaniu przez SSH.

---

## Krok 4. Przygotuj serwer

### 4a. Katalogi

```bash
ssh -p 222 lyvelmikov@s66.cyber-folks.pl
cd ~/domains/norios.pl
mkdir -p solidus-api/public solidus-api/runtime
chmod 755 solidus-api
chmod 775 solidus-api/runtime     # Yii musi mieć tu prawo zapisu
```

### 4b. Subdomena `api.solidus.norios.pl`

W panelu Cyber-Folks: **Domeny → Subdomeny → Dodaj**.

- nazwa: `api.solidus`
- katalog: `domains/norios.pl/solidus-api/public` ← katalog **obok** `public_html`, nie w środku
- PHP: **8.5**
- SSL: włącz Let's Encrypt

Kluczowy jest ten katalog. Wskazanie na `public/`, a nie na `backend/`, sprawia, że `src/`, `config/`, `vendor/` i `.env` są fizycznie poza zasięgiem przeglądarki — nawet gdyby `.htaccess` przestał działać.

### 4c. Baza danych

W panelu: **Bazy danych → Dodaj**. Zanotuj nazwę bazy, użytkownika i hasło — Cyber-Folks zwykle dokleja do nich prefiks konta.

### 4d. Plik `.env` na serwerze

Tego pliku **nie wysyła wdrożenie** (jest na liście wykluczeń), więc tworzysz go raz, ręcznie:

```bash
cd ~/domains/norios.pl/solidus-api
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

Sekret wygeneruj **na serwerze** i wklej do pliku:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Trzy rzeczy, które łatwo tu przeoczyć:

- **`APP_DEBUG=false` jest obowiązkowe.** Przy `true` strona błędu pokazuje ślad stosu razem z hasłem do bazy.
- **`JWT_SECRET` musi mieć min. 32 znaki.** Aplikacja odmówi startu z krótszym — celowo, bo pusty sekret pozwoliłby każdemu wystawić sobie token dowolnego użytkownika.
- **`FRONTEND_ORIGIN` musi być dokładnym adresem SPA**, z `https://` i bez ukośnika na końcu. Ta wartość trafia do nagłówka CORS; literówka oznacza, że przeglądarka zablokuje każde żądanie do API.

---

## Krok 5. Pierwsze wdrożenie

```bash
git push origin main
```

Postęp: zakładka **Actions** w repozytorium. Można też uruchomić ręcznie: **Actions → Deploy na serwer (SSH) → Run workflow**.

Po zakończeniu — migracje, raz, ręcznie:

```bash
ssh -p 222 lyvelmikov@s66.cyber-folks.pl
cd ~/domains/norios.pl/solidus-api
php yii migrate:up
```

Migracje **nie idą automatycznie** i to jest decyzja projektowa: migracja odpalona przy każdym pushu potrafi zablokować tabelę w środku dnia pracy biura. Uruchamiasz je świadomie, po każdym wdrożeniu, które dodało pliki w `Module/*/Migration/`.

Konto administratora systemu (dostęp do cennika) — też z konsoli:

```bash
php yii admin:grant slug-biura adres@email.pl
```

---

## Krok 6. Sprawdź, czy działa

```bash
# API odpowiada i widzi bazę
curl -s https://api.solidus.norios.pl/api/pricing

# CORS zwraca właściwe pochodzenie
curl -s -I -X OPTIONS https://api.solidus.norios.pl/api/auth/login \
  -H "Origin: https://solidus.norios.pl" -H "Access-Control-Request-Method: POST" \
  | grep -i access-control-allow-origin
```

W przeglądarce:

1. `https://solidus.norios.pl` — strona informacyjna z cennikiem (cennik = dowód, że SPA rozmawia z API).
2. `https://solidus.norios.pl/klienci` — **odśwież stronę**. Jeśli widzisz 404, `.htaccess` nie zadziałał (patrz niżej).
3. Zaloguj się, odśwież — sesja ma przetrwać. Jeśli wyrzuca do logowania, sprawdź HTTPS: ciasteczko refresh ma flagę `Secure` i po HTTP nie zostanie zapisane.

---

## Co robić, gdy coś nie działa

| Objaw | Przyczyna |
|---|---|
| Błąd 500 na całym API | Najczęściej wersja PHP (krok 0) albo brak praw zapisu do `runtime/`. Log: `solidus-api/runtime/logs/` |
| `Brak tokenu dostepowego` mimo zalogowania | Serwer zjada nagłówek `Authorization`. Obsługuje to `backend/public/.htaccess` — sprawdź, czy plik dojechał |
| 404 po odświeżeniu na `/klienci` | Brak `.htaccess` w `public_html` lub wyłączony `mod_rewrite`. Plik jedzie z `frontend/public/.htaccess` przez `dist/` |
| Przeglądarka blokuje żądania (CORS) | `FRONTEND_ORIGIN` w `.env` nie zgadza się co do znaku z adresem SPA |
| Cennik pusty, reszta strony działa | API nie odpowiada albo `VITE_API_URL` wskazuje zły adres. Zmiana wymaga **ponownego wdrożenia**, nie edycji na serwerze |
| Zmiany w kodzie nie widać | Cache opcode PHP. Restart PHP z panelu Cyber-Folks |

### Czego wdrożenie celowo nie robi

- **Nie kasuje plików usuniętych z repozytorium** (brak `--delete` w `rsync`). Chroni to `.env`, `runtime/` i dane na serwerze; plik skasowany w repo trzeba usunąć ręcznie.
- **Nie odpala migracji** — patrz krok 5.
- **Nie nadpisuje `.env`.**
- **Nie robi kopii zapasowej.** Backup bazy przed wdrożeniem z migracjami jest po Twojej stronie: `mysqldump -u UZYTKOWNIK_BAZY -p NAZWA_BAZY > backup-$(date +%F).sql`
