# Solidus — architektura

Dokument techniczny. Wersja dla nietechnicznych czytelników: [README.md](README.md).

Stan na 4 września 2026. Opisuje szkielet pod 8-tygodniowe MVP, nie produkt.

---

## 1. Diagram warstw

```
┌──────────────────────────────────────────────────────────────────┐
│  Przeglądarka                                                    │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │ SPA: Vue 3 + TypeScript + Pinia + Tailwind (Vite :5173)    │  │
│  │  modules/*  ← ekrany           stores/*  ← stan            │  │
│  │  components/ui  ← design system                            │  │
│  │  api/http.ts  ← axios: token w nagłówku, ciche odświeżanie │  │
│  └────────────────────────────────────────────────────────────┘  │
└────────────────────────────┬─────────────────────────────────────┘
                             │ HTTPS, JSON
                             │ Authorization: Bearer <access, 15 min>
                             │ Cookie: refresh (httpOnly, /api/auth)
┌────────────────────────────▼─────────────────────────────────────┐
│  nginx :8080  →  php-fpm                                         │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │ Pipeline PSR-15                                            │  │
│  │  ErrorCatcher → CORS → Session → RequestCatcher → Router    │  │
│  │                                          │                  │  │
│  │                          grupa /api ─────┴─ TenantMiddleware│  │
│  └────────────────────────────────────────────────────────────┘  │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │ Controller  (cienki: HTTP ↔ serwis)                        │  │
│  │      ↓                                                      │  │
│  │ Service     (reguły biznesowe + wpis do audit logu)         │  │
│  │      ↓                                                      │  │
│  │ Repository  (SQL; filtr tenant_id wymuszony przez trait)    │  │
│  │      ↓                                                      │  │
│  │ Entity / DTO                                                │  │
│  └────────────────────────────────────────────────────────────┘  │
└───────┬──────────────────────┬──────────────────┬────────────────┘
        │                      ┆                  │
   ┌────▼─────┐         ┌ ─ ─ ─▼─ ─ ─ ┐   ┌───────▼────────────────┐
   │ MySQL 8  │           Redis           │ Aplikacje zewnętrzne   │
   │ dane +   │         │ kolejka     │   │ AML / DelegoApp /      │
   │ audit    │           WYŁĄCZONE       │ Sygnaliści (REST)      │
   └──────────┘         └ ─ ─ ─ ─ ─ ─ ┘   └────────────────────────┘
```

Redis narysowany linią przerywaną **nie istnieje w tej wersji** — środowisko docelowe go nie ma. Konsekwencje opisuje sekcja 2.9.

---

## 2. Decyzje projektowe

### 2.1 Dlaczego Yii3, a nie Yii2 lub Symfony

Yii3 wymusza to, czego Yii2 tylko pozwalał: kontener DI zamiast globalnego `Yii::$app`, PSR-7/PSR-15 zamiast własnych klas request/response, konfigurację jako kod zamiast tablic rozsianych po projekcie. Przy aplikacji wielofirmowej to nie jest kwestia estetyki — `TenantContext` jako wstrzykiwana zależność ma jasno określony cykl życia i można go podmienić w teście. Globalny singleton w stylu Yii2 rozlałby stan tenanta po całej aplikacji i uczyniłby izolację nietestowalną.

Symfony byłby równie dobrym wyborem technicznie. Yii3 wybrano ze względu na znajomość ekosystemu Yii w zespole i mniejszą powierzchnię do opanowania.

**Koszt tej decyzji, świadomie przyjęty:** Yii3 ma mniejszy ekosystem. Najwyraźniej widać to po `yiisoft/queue` — nie ma stabilnego tagu i wymagałby instalacji z `@dev`. Ponieważ kolejka jest teraz wyłączona (sekcja 2.9), problem nie dotyczy tej wersji: wszystkie zależności są stabilne, a `composer install` nie potrzebuje żadnych obejść.

**Yii3 nie ma ActiveRecord w rdzeniu.** To wyszło nam na dobre: encje są zwykłymi obiektami PHP, a mapowanie robi repozytorium. Dzięki temu nie da się przypadkiem wykonać zapytania z pominięciem filtra po tenancie — bo nie ma metody `Client::find()`, którą można by wywołać z dowolnego miejsca.

### 2.1a Wersja PHP: 8.5, przypięta twardo

Serwer produkcyjny ma PHP 8.5, więc `composer.json` wymaga `~8.5.0`, obraz Dockera to `php:8.5-fpm-alpine`, a Rector celuje w zestaw reguł `php85`. Zakres w rodzaju `8.2 - 8.5` (tak jak dawał szkielet) pozwoliłby dopuścić do repozytorium kod, który przechodzi lokalnie na 8.2 i wywraca się na produkcji — przy przypiętej wersji tej rozbieżności po prostu nie ma.

Dwie konsekwencje warte odnotowania:

- **Praca z backendem odbywa się w kontenerze.** Maszyna deweloperska z PHP 8.2 nie uruchomi `composer install` bezpośrednio — i dobrze, bo uruchomiłaby go pod inną wersję niż produkcja.
- **W obrazie `php:8.5` OPcache jest wkompilowany na stałe.** Próba `docker-php-ext-install opcache` kończy się błędem `can't stat 'modules/*'` (nie powstaje żaden moduł do skopiowania), dlatego Dockerfile instaluje tylko `pdo_mysql` i `intl`.

Przejście na 8.5 odblokowało też **Psalma** — wcześniej wymagał wersji PHP nowszej niż lokalna i musiał zostać usunięty. Wrócił jako `require-dev` i przechodzi bez błędów na `errorLevel="1"` (najostrzejszym). Konfiguracja wycisza dwie rodziny problemów — `MixedReturnTypeCoercion` i `MixedArgumentTypeCoercion` — i tylko je: pochodzą z trzech granic, na których dane mają z definicji nieznany kształt (ciało żądania HTTP, wiersz z bazy, odpowiedź zewnętrznej aplikacji). Uzasadnienie jest zapisane w `psalm.xml`, przy samym wyciszeniu.

### 2.2 Dlaczego MySQL, a nie PostgreSQL

Decyzja infrastrukturalna: serwer produkcyjny nie ma Postgresa. Poza tym MySQL 8 pokrywa wszystkie potrzeby tego projektu — typ `JSON` (kolumna `changes` w audit logu), `DATETIME(6)` z mikrosekundami, `ENUM`, indeksy złożone.

Konfiguracja (`docker/mysql/my.cnf`) jest celowo restrykcyjna:

- `utf8mb4_0900_ai_ci` — polskie znaki i emoji w notatkach do klientów muszą działać. Kolacja `0900` jest zgodna z Unicode 9.0 i sortuje polskie znaki poprawnie.
- `STRICT_TRANS_TABLES` — bez tego MySQL po cichu obcina za długie wartości zamiast zgłosić błąd. W systemie księgowym cicha utrata danych jest gorsza niż awaria.
- `ONLY_FULL_GROUP_BY` — bez tego zapytania raportowe potrafią zwracać przypadkowy wiersz z grupy, a wynik wygląda wiarygodnie.

### 2.3 Multi-tenancy: jedna baza, kolumna dyskryminująca

Rozważane opcje:

| Podejście | Izolacja | Koszt operacyjny | Werdykt |
|---|---|---|---|
| Baza na tenanta | Najlepsza | Migracja × liczba biur; backup × N | Odrzucone — nie do utrzymania przy setkach biur |
| Schemat na tenanta | Dobra | MySQL nie ma schematów w sensie Postgresa | Nieosiągalne |
| **Kolumna `tenant_id`** | Zależy od dyscypliny kodu | Jedna migracja, jeden backup | **Wybrane** |

Słabym punktem wybranego podejścia jest „zależy od dyscypliny kodu" — jedno zapytanie bez `WHERE tenant_id` to wyciek danych. Adresujemy to trzema warstwami:

1. **`TenantMiddleware`** ustala tenanta z claimu `tid` w tokenie JWT. Nie z parametru URL, nie z nagłówka, nie z ciała żądania — wyłącznie z podpisanego tokenu. Klient nie może podać cudzego `tenant_id`, bo nie potrafi podrobić podpisu.
2. **Trait `TenantScoped`** daje repozytoriom metodę `scopedQuery()`, która startuje z gotowym warunkiem, oraz `tenantCondition()` dla UPDATE/DELETE. Filtrowanie jest domyślne — trzeba by świadomie napisać zapytanie od zera, żeby je pominąć.
3. **Testy izolacji** (`ClientRepositoryTest`) sprawdzają na prawdziwym MySQL cztery ścieżki: listę, odczyt po id, edycję i usunięcie. Każda nowa tabela domenowa dostaje analogiczny test.

`TenantContext` jest czyszczony w bloku `finally` middleware. Przy klasycznym php-fpm proces i tak umiera po żądaniu, ale przy długo żyjącym runtime (RoadRunner, Swoole) brak tego czyszczenia oznaczałby wyciek tenanta między żądaniami — najgroźniejszy możliwy błąd w tej architekturze.

**Indeks `(tenant_id, id)` na każdej tabeli domenowej** jest celowy: skoro każde zapytanie zaczyna się od `tenant_id`, to musi być pierwsza kolumna indeksu.

### 2.4 JWT: dlaczego para tokenów i dlaczego rotacja

Wymóg: „mobilny klient później". To wykluczyło sesje w ciasteczku jako jedyny mechanizm — aplikacja mobilna nie ma przeglądarkowego modelu ciasteczek.

Konstrukcja:

| | Access token | Refresh token |
|---|---|---|
| Żywotność | 15 min | 30 dni |
| Gdzie | Pamięć JS (nie `localStorage`) | Ciasteczko httpOnly, Secure, SameSite=Strict, ścieżka `/api/auth` |
| Do czego | Każde żądanie do API | Wyłącznie wyrobienie nowego access tokenu |
| Stan po stronie serwera | Brak (bezstanowy) | Wpis `jti` w tabeli `refresh_tokens` |

Uzasadnienia:

- **Access token nie trafia do `localStorage`.** Każdy XSS odczytałby go stamtąd. W zmiennej modułu ginie razem z kartą.
- **Refresh token jest `httpOnly`** — JavaScript go nie widzi, więc XSS go nie wykradnie. `SameSite=Strict` plus ograniczenie ścieżki do `/api/auth` sprawiają, że nie jedzie przy zwykłych zapytaniach.
- **Rotacja przy każdym odświeżeniu.** Stary `jti` jest unieważniany, wydawany jest nowy. Skradziona kopia przestaje działać, gdy prawowity właściciel odświeży sesję. To zamienia trwałą kradzież w okno czasowe.
- **Tabela `refresh_tokens` przechowuje tylko `jti`, nigdy tokenu.** Wyciek tej tabeli nie pozwala nikomu się zalogować. Kolumny `user_agent` i `ip` są podkładem pod ekran „aktywne urządzenia", a `revoked_at` pozwala wylogować sesję zdalnie — czego czysty JWT sam z siebie nie potrafi.
- **Role są odczytywane z bazy przy każdym odświeżeniu,** a nie przepisywane ze starego tokenu. Odebranie uprawnień działa po maksymalnie 15 minutach, a nie po 30 dniach.

**Cała logika żyje w `Shared/Auth/JwtService`, nie w kontrolerze.** Kontroler webowy chowa refresh token w ciasteczku; przyszły kontroler mobilny zwróci go w ciele odpowiedzi. Obie ścieżki przechodzą przez ten sam `issue()` i `refresh()`. Gdyby ta logika siedziała w kontrolerze, klient mobilny wymusiłby jej skopiowanie — a dwie kopie kodu uwierzytelniania rozjeżdżają się zawsze.

`AuthController::refresh()` już teraz czyta token z ciasteczka **albo** z ciała żądania, więc ścieżka mobilna jest przygotowana.

### 2.5 Audit log

Osobna tabela `audit_log`, zapisywana przez `AuditLogger` wołany z serwisów domenowych.

**Dlaczego z serwisu, a nie z kontrolera:** dane zmienia nie tylko kontroler. Import masowy, zadanie z kolejki i komenda konsolowa też. Gdyby zapis do dziennika siedział w kontrolerze, każde nowe wejście do systemu byłoby cichą luką w audycie.

**Dlaczego `changes` to JSON:** kształt zmian jest inny dla każdej encji. Kolumny per pole wymagałyby migracji przy każdej zmianie modelu. Filtrowanie po wnętrzu JSON-a nie jest dziś potrzebne; gdy będzie, dokłada się generated column plus indeks — bez zmiany danych historycznych.

**Zapisujemy wyłącznie pola, które faktycznie się zmieniły** (`AuditLogger::updated()` porównuje stan przed i po). Dziennik ma być czytelny dla audytora, a nie kopią tabeli.

**Brak klucza obcego do encji jest celowy** — wpis o usunięciu musi przetrwać usunięcie encji, której dotyczy. FK jest tylko do `tenants`.

**`DATETIME(6)`** — przy imporcie masowym kilkanaście zmian wpada w tę samą sekundę; mikrosekundy zachowują ich kolejność.

### 2.6 Kontrakt z aplikacjami zewnętrznymi

Trzy moduły (AML, Delegacje, Sygnaliści) to niezależne aplikacje. Uzasadnienie biznesowe jest w README; tu strona techniczna.

Każdy z nich ma w Solidusie identyczną strukturę:

```
Module/<Nazwa>/
├── Client/
│   ├── <Nazwa>ApiClientInterface.php   ← kontrakt; tylko to zna kod domenowy
│   ├── Http<Nazwa>ApiClient.php        ← implementacja na Guzzle (STUB)
│   └── Fake<Nazwa>ApiClient.php        ← implementacja do testów
└── Dto/
    └── <Kontrakt>.php                  ← AmlRiskScore / DelegationReport / WhistleblowerCase
```

Zasady kontraktu:

- **Kod domenowy zależy wyłącznie od interfejsu.** Podmiana na `Fake*` w testach nie wymaga sieci ani działającej instancji zewnętrznej aplikacji.
- **Uwierzytelnianie:** `Authorization: Bearer <klucz>` plus nagłówek `X-Tenant-Id` — zewnętrzne aplikacje też są wielofirmowe i muszą wiedzieć, czyje dane liczą.
- **Błędy są tłumaczone na `Shared\ExternalApi\ExternalApiException`.** Wyjątki Guzzle nie przeciekają do warstwy domenowej — inaczej zmiana biblioteki HTTP dotknęłaby serwisów.
- **Konfiguracja w `config/common/params.php`** (`solidus.externalApi.*`), wartości puste do czasu powstania tych aplikacji. Pusty `baseUrl` powoduje czytelny wyjątek zamiast żądania donikąd.
- **`WhistleblowerApiClientInterface` celowo nie ma metody zwracającej treść zgłoszenia.** Solidus operuje wyłącznie na metadanych. To ograniczenie zapisane w typie, nie w komentarzu.

Kontrakty DTO są **propozycją do potwierdzenia** po obu stronach. Dlatego `Http*ApiClient` nie mają testów — testowanie zgadywanego kształtu odpowiedzi utrwaliłoby zgadywanie.

### 2.7 SPA zamiast renderowania po stronie serwera

Backend zwraca wyłącznie JSON; interfejs renderuje się w przeglądarce. Powody: to samo API obsłuży aplikację mobilną, a interfejs z makiety (dużo stanu, filtry, panele boczne) jest bardziej aplikacją niż dokumentem. SSR/Nuxt odrzucono — SEO nie dotyczy narzędzia za logowaniem, a doszłaby trzecia warstwa do utrzymania.

**Konsekwencja: CORS.** SPA na :5173 i API na :8080 to różne originy. `CorsMiddleware` stoi **przed routerem** w pipeline — zapytanie kontrolne `OPTIONS` nie pasuje do żadnej zdefiniowanej trasy, więc router odpowiedziałby na nie sam, bez nagłówków CORS, i przeglądarka zablokowałaby właściwe żądanie.

**`CsrfTokenMiddleware` ze szkieletu został usunięty z pipeline.** Chroni formularze uwierzytelniane ciasteczkiem sesji. Nasze API jest bezstanowe — tożsamość jedzie w nagłówku `Authorization`, którego przeglądarka nie doklei automatycznie do żądania z obcej strony. Ciasteczko refresh tokenu jest `SameSite=Strict` i ograniczone do `/api/auth`. Token CSRF nic by tu nie dodał, a blokowałby każdy POST z SPA.

### 2.8 Design system

Makieta `docs/design/solidus.html` jest źródłem prawdy dla wyglądu. Została **zrekonstruowana**, a nie skopiowana:

- Zmienne CSS z `:root` → `frontend/src/styles/tokens.css` (1:1).
- `tailwind.config.ts` czyta te zmienne przez `var(--…)`, więc paleta ma jedno źródło prawdy — zmiana koloru w `tokens.css` przechodzi przez wszystkie klasy Tailwinda bez przebudowy configu.
- Prymitywy `.glass`, `.glass-sm`, `.hairline` zostały klasami CSS, a nie zestawami utility. Gradient, obramowanie i `backdrop-filter` zawsze występują razem; rozbicie ich na sześć klas Tailwinda kończy się kopiowaniem tej szóstki po całym projekcie.
- Reszta to komponenty Vue z Tailwindem.

Tailwind przypięto do wersji **3.4**, a nie 4.x. Wersja 4 przenosi konfigurację do CSS (`@theme`) i nie generuje `tailwind.config.ts`; przy założonym mapowaniu tokenów w pliku konfiguracyjnym wersja 3 jest zgodna z tym, jak projekt ma być utrzymywany.

### 2.9 Kolejka — WYŁĄCZONA

**Stan: brak kolejki i brak Redisa.** Środowisko docelowe nie udostępnia Redisa, więc `yiisoft/queue`, `yiisoft/queue-redis`, usługi `redis` i `queue` w Dockerze oraz rozszerzenie `ext-redis` w obrazie PHP zostały usunięte. Nie są zakomentowane ani „uśpione" — w kodzie nie ma po nich śladu poza tym akapitem.

Skutki uboczne są pozytywne: wszystkie zależności backendu są teraz **stabilne**, a `composer install` działa bez `--ignore-platform-req=ext-redis`.

**Czego to nas kosztuje.** Zadania, które miały działać w tle, muszą działać w cyklu żądania HTTP:

| Zadanie | Konsekwencja braku kolejki |
|---|---|
| Masowa wysyłka e-mail (moduł Komunikacja) | Żądanie trwa tyle, ile wysyłka. Przy 300 klientach to minuty i realne ryzyko timeoutu nginx/PHP. **To jest blokada dla tego modułu**, nie niedogodność. |
| Odświeżanie scoringu AML | Użytkownik czeka na odpowiedź zewnętrznej aplikacji. Do zniesienia przy pojedynczym kliencie, nie do zniesienia przy przeliczaniu całej bazy. |

Żaden z tych modułów nie jest jeszcze zaimplementowany, więc dziś nic nie jest zepsute. **Ale zanim moduł Komunikacja wejdzie do prac, trzeba podjąć decyzję** — inaczej powstanie funkcja, która wywraca się na produkcji przy pierwszej większej wysyłce.

**Opcje na moment, gdy kolejka będzie potrzebna:**

1. **Redis wraca** — najprostsze, jeśli infrastruktura się zmieni. Instrukcja poniżej.
2. **Kolejka na MySQL** (`yiisoft/queue-db`) — bez nowej infrastruktury, wolniejsza, ale przy skali biura rachunkowego całkowicie wystarczająca. **Rekomendacja**, jeśli Redis pozostaje niedostępny.
3. **Cron plus tabela zadań** — najbardziej prymitywne, ale bez żadnych zależności.

**Jak przywrócić wariant Redisowy:**

```bash
cd backend
composer require "yiisoft/queue:^3.0@dev" "yiisoft/queue-redis:@dev" --ignore-platform-req=ext-redis
```

1. W `docker/Dockerfile.php` przywróć `pecl install redis && docker-php-ext-enable redis` (oraz `linux-headers` w `.build-deps`).
2. W `docker/docker-compose.yml` przywróć usługę `redis` (`redis:7-alpine`, port 6379), usługę `queue` (ten sam obraz co `php`, komenda `php yii queue:listen-all`) i zmienne `REDIS_HOST` / `REDIS_PORT` w usłudze `php`.
3. W `config/common/params.php` przywróć sekcję `solidus.redis` (host, port z `Env`).
4. Odtwórz `config/common/di/queue.php`: definicja `Redis::class` i `QueueProviderInterface` → `Yiisoft\Queue\Redis\QueueProvider` z `channelName: 'solidus'`.
5. Odkomentuj `REDIS_HOST` / `REDIS_PORT` w `backend/.env.example`.

Historia Git zawiera działającą wersję tej konfiguracji (commit `dff781c` i wcześniejsze) — najszybciej przywrócić ją stamtąd.

---

## 3. Struktura bazy

```
tenants ──┬─< users ──< refresh_tokens
          ├─< clients
          └─< audit_log
```

| Tabela | Klucze i indeksy | Uwagi |
|---|---|---|
| `tenants` | PK `id`, UNIQUE `slug` | Slug trafia do adresów, więc unikalny globalnie |
| `users` | UNIQUE `(tenant_id, email)`, INDEX `(tenant_id, id)`, FK → `tenants` | E-mail unikalny **w obrębie biura** — ta sama osoba może pracować w dwóch |
| `refresh_tokens` | PK `jti`, INDEX `(user_id, revoked_at)`, FK → `users`, `tenants` | Wyłącznie identyfikatory, nigdy token |
| `clients` | INDEX `(tenant_id, id)`, UNIQUE `(tenant_id, nip)`, INDEX `(tenant_id, status)`, FK → `tenants` | NIP unikalny per biuro — dwa biura mogą obsługiwać tę samą firmę |
| `audit_log` | INDEX `(tenant_id, entity_type, entity_id)`, INDEX `(tenant_id, created_at)`, FK tylko → `tenants` | Brak FK do encji jest celowy |

Migracje leżą przy modułach, których dotyczą (`Module/*/Migration/`), wspólne w `Shared/Migration/`. Rejestracja przez `sourceNamespaces` w `config/common/params.php`.

---

## 4. Testy

| Zakres | Narzędzie | Co sprawdza |
|---|---|---|
| `ClientRepositoryTest` | PHPUnit + prawdziwy MySQL | Izolacja tenantów na liście, odczycie, edycji i usunięciu; unikalność NIP per biuro; zapis do audit logu |
| `ClientList.spec.ts` | Vitest + Vue Test Utils | Stany listy (ładowanie, pusta, z danymi), formatowanie NIP, emitowane zdarzenia |

**Testy izolacji celowo używają prawdziwej bazy.** Izolacja tenantów jest egzekwowana przez warunki SQL, klucze obce i indeksy. Test na atrapie repozytorium sprawdzałby wyłącznie własną atrapę i przechodziłby także wtedy, gdyby produkcyjne zapytanie gubiło `WHERE tenant_id`. Testy pomijają się z czytelnym komunikatem, gdy MySQL jest niedostępny.

Szkielet `yiisoft/app` przychodzi z Codeception; te zestawy zostały nietknięte (`composer test-codeception`). PHPUnit działa na osobnym katalogu `tests/Integration`, żeby narzędzia sobie nie przeszkadzały.

---

## 5. Dług techniczny i znane ograniczenia

Rzeczy świadomie odłożone — do rozstrzygnięcia przed produkcją:

1. **Brak kolejki zadań** (sekcja 2.9). Środowisko nie ma Redisa. Dopóki nie ma modułu Komunikacja, nic to nie blokuje — ale **decyzja o wariancie kolejki musi zapaść przed rozpoczęciem prac nad masowymi wysyłkami**, nie po nich. Rekomendacja przy braku Redisa: `yiisoft/queue-db`.
2. **Lokalny PHP nie wystarcza do pracy poza Dockerem.** Projekt celuje w PHP 8.5 (`"php": "~8.5.0"`), a maszyna deweloperska ma 8.2 — `composer install` uruchomiony bezpośrednio na hoście odmówi. To nie jest usterka, tylko konsekwencja przypięcia do wersji produkcyjnej: cała praca z backendem idzie przez kontener (`docker compose … exec php …`). Alternatywą jest doinstalowanie PHP 8.5 na hoście.
3. **Brak ekranu rejestracji.** Endpoint `POST /api/auth/register` działa, interfejsu do niego nie ma. Pierwsze biuro zakłada się poleceniem `curl`.
4. **Brak autoryzacji ról.** Role są w tokenie (`roles`) i w bazie, ale żaden endpoint ich nie sprawdza — każdy zalogowany użytkownik biura może wszystko. `yiisoft/access` jest zainstalowany, kontrola nie jest podpięta.
5. **Usuwanie klienta jest trwałe.** Docelowo powinno być archiwizacją (`status = archived`); dziś wiersz znika, a ślad zostaje wyłącznie w audit logu.
6. **Brak paginacji w interfejsie.** API przyjmuje `limit`/`offset`, front pobiera pierwsze 50 rekordów i nie pokazuje nawigacji po stronach.
7. **Historia zmian nie jest wystawiona w UI.** `AuditLogger::historyFor()` istnieje, ekranu nie ma.
8. **Rate limiting na `/api/auth/login`.** Brak — do dodania przed wystawieniem na świat.
9. **`Http*ApiClient` to stuby.** Kontrakty DTO wymagają potwierdzenia z zespołami zewnętrznych aplikacji.
10. **Sekret JWT w `docker-compose.yml`** jest wartością deweloperską. Na produkcji musi pochodzić z sekretów środowiska; aplikacja go nie waliduje pod kątem długości.
11. **Wolne odpowiedzi API na Windowsie: ~3 s na żądanie** (zmierzone: 3,0–6,7 s dla `GET /api/aml`, z czego 0,48 s to samo wczytanie autoloadera). Przyczyną jest bind-mount katalogu z dysku Windows do kontenera — każde żądanie odczytuje kilka tysięcy plików z `vendor/` przez granicę systemów plików. To nie jest problem aplikacji: na Linuksie i na produkcji nie występuje. Obejścia, w kolejności skuteczności: (a) trzymać repozytorium w systemie plików WSL2 (`\\wsl$\...`), a nie na `D:\`; (b) `opcache.validate_timestamps=0` w obrazie — szybko, ale wymaga restartu kontenera po każdej zmianie kodu; (c) zaakceptować na czas developmentu.

---

## 6. Uruchomienie dla deweloperów

```bash
# Całe środowisko
docker compose -f docker/docker-compose.yml up

# Migracje (na czystej bazie)
docker compose -f docker/docker-compose.yml exec php ./yii migrate:up

# Testy backendu (wymagają bazy solidus_test)
docker exec solidus-mysql-1 mysql -uroot -proot \
  -e "CREATE DATABASE IF NOT EXISTS solidus_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
      GRANT ALL ON solidus_test.* TO 'solidus'@'%'; FLUSH PRIVILEGES;"
docker compose -f docker/docker-compose.yml exec php composer test

# Testy frontendu
cd frontend && npm test

# Analiza statyczna backendu (Psalm, errorLevel 1)
docker compose -f docker/docker-compose.yml exec php ./vendor/bin/psalm
```

Backend wymaga PHP 8.5, więc `composer` i `phpunit` uruchamiamy **w kontenerze**, nie na hoście (patrz sekcja 2.1a).

Wszystkie zależności są stabilne i nie wymagają rozszerzeń spoza standardu — `composer install` działa bez żadnych flag obejściowych, także poza Dockerem.

Porty: API `:8080`, SPA `:5173`, MySQL `:3306`, Mailhog `:8025`.
