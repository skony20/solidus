# Solidus

## Co to jest Solidus?

Solidus to system dla biur rachunkowych — jedno miejsce, w którym księgowa ma wszystko, co dotyczy obsługiwanych firm. Zamiast trzymać dane klientów w jednym arkuszu, terminy podatkowe w drugim, korespondencję w skrzynce pocztowej, a dokumenty na dysku, biuro pracuje w jednej aplikacji, która te rzeczy łączy: kto jest klientem, co się u niego dzieje, jakie ma terminy, kto się nim opiekuje i co ostatnio zmieniło się w jego dokumentach.

Aplikacja jest pisana z myślą o polskich realiach — NIP, KSeF, terminy podatkowe, obowiązki AML (przeciwdziałanie praniu pieniędzy) i ochrona sygnalistów. Jest też **wielofirmowa**: z tej samej instalacji korzysta wiele biur rachunkowych naraz, a każde widzi wyłącznie własne dane. To nie jest kwestia dobrych chęci, tylko konstrukcji — opisujemy to niżej przy słowie „tenant".

**Uwaga: to jest szkielet, a nie gotowy produkt.** Działa logowanie, zarządzanie klientami i dziennik zmian. Pozostałe moduły to na razie puste ekrany z opisem, co się w nich pojawi. Taki był cel tego etapu: postawić fundament, na którym da się budować dalej, zamiast zaczynać od dziesięciu niedokończonych funkcji.

---

## Jak jest zbudowana?

Najprościej wyobrazić sobie Solidusa jako **biuro**:

| Element aplikacji | Odpowiednik w biurze | Co robi |
|---|---|---|
| **Frontend** (Vue) | recepcja i sala obsługi | To, co widzisz na ekranie: menu, tabele, formularze. Sam nic nie pamięta — o wszystko pyta zaplecze. |
| **Backend** (Yii3/PHP) | zaplecze i kierownik biura | Pilnuje reguł: czy NIP jest poprawny, czy masz prawo zobaczyć te dane, co zapisać do archiwum. |
| **Baza danych** (MySQL) | segregatory w szafie | Trwałe przechowywanie: klienci, użytkownicy, dziennik zmian. |
| **Archiwum** (audit log) | dziennik korespondencji | Zapis każdej zmiany: kto, kiedy, co i z jakiego komputera. |

W tym zestawieniu brakuje jednego elementu, który normalnie by tu był: **skrzynki „do zrobienia"** — czyli miejsca, gdzie odkładane są zadania trwające długo, na przykład wysyłka maila do 300 klientów. Nazywa się to kolejką i wymaga dodatkowego programu (Redis), którego docelowy serwer na razie nie ma. Dlatego w tej wersji kolejki nie ma wcale.

Dziś to niczego nie psuje, bo moduł do masowych wysyłek jeszcze nie powstał. Ale trzeba o tym pamiętać: **bez kolejki wysyłka do 300 klientów oznacza, że przeglądarka czeka kilka minut na jedno kliknięcie** i najprawdopodobniej się rozłączy. Zanim ten moduł zacznie powstawać, trzeba zdecydować, skąd weźmiemy kolejkę. Możliwości opisuje [ARCHITECTURE.md](ARCHITECTURE.md) w sekcji 2.9 — najprostsza z nich nie wymaga żadnego nowego serwera, bo wykorzystuje bazę danych, którą i tak mamy.

Frontend i backend to dwa osobne programy, które rozmawiają ze sobą przez internet. Brzmi to jak komplikacja, ale daje konkretną korzyść: gdy w przyszłości powstanie aplikacja na telefon, będzie rozmawiać z tym samym zapleczem, bez przepisywania reguł biznesowych od nowa.

### Dlaczego AML, delegacje i sygnaliści to osobne aplikacje?

To najważniejsza decyzja w tym projekcie i warto ją rozumieć, bo wpływa na wszystko dalej. Trzy obszary **nie żyją w Solidusie** — działają jako niezależne aplikacje, z którymi Solidus tylko rozmawia:

- **Analiza ryzyka AML** — liczenie, czy klient jest podejrzany
- **Delegacje (DelegoApp)** — rozliczanie podróży służbowych
- **Kanał sygnalistów** — anonimowe zgłoszenia nieprawidłowości

Powody:

1. **Różne wymagania prawne wobec danych.** Zgłoszenie sygnalisty musi być anonimowe i dostępne dla wąskiej grupy osób. Gdyby jego treść leżała w tej samej bazie co lista klientów, każdy błąd w uprawnieniach Solidusa stawałby się wyciekiem danych chronionych osobną ustawą. Solidus widzi tylko metadane: że zgłoszenie istnieje, w jakim jest stanie i kiedy mija termin odpowiedzi. Treści nie widzi nigdy.

2. **Różne tempo zmian.** Metodyka oceny ryzyka AML zmienia się wraz z przepisami i praktyką nadzorczą. Gdyby siedziała w Solidusie, każda korekta scoringu wymagałaby wdrożenia całej aplikacji — z ryzykiem dla modułów, które nikomu się nie popsuły. Osobno można ją poprawić w środę po południu i nikt inny tego nie odczuje.

3. **Te aplikacje mają własne życie.** DelegoApp może być sprzedawany firmom, które nie są klientami Solidusa. Wpisanie go na stałe w Solidusa zamknęłoby tę drogę.

W praktyce wygląda to tak: klikasz „Ryzyko AML", Solidus pyta zewnętrzną aplikację „jaki jest wynik dla tego klienta?", dostaje liczbę i pokazuje ją na ekranie. Sam niczego nie liczy.

---

## Co gdzie się dzieje w kodzie?

```
solidus/
├── backend/          ← zaplecze: reguły, baza, API
├── frontend/         ← to, co widzi użytkownik
├── docker/           ← przepis na uruchomienie całości
└── docs/             ← ten plik i dokumentacja techniczna
```

### Backend (`backend/src/`)

**`Module/` — moduły dziedzinowe.** Każdy odpowiada jednej pozycji w menu.

| Katalog | Po co jest |
|---|---|
| `Client/` | Klienci biura. **Jedyny moduł zbudowany do końca** — jest wzorem dla pozostałych. |
| `Account/` | Zakładanie biura, logowanie, wylogowanie, „kim jestem". |
| `Aml/Client/` | Tu Solidus rozmawia z osobną aplikacją do analizy ryzyka AML — sam nie liczy scoringu. |
| `Delegation/Client/` | Tu Solidus pobiera rozliczenia delegacji z DelegoApp — sam ich nie prowadzi. |
| `Whistleblower/Client/` | Tu Solidus pyta o metadane zgłoszeń sygnalistów — treści nie dostaje i nie chce. |
| `MissionControl/` | Pulpit z podsumowaniem pracy całego biura. Szkielet. |
| `Communication/` | Masowe wysyłki maili i rozmowy 1:1 z klientem. Szkielet. |
| `Calendar/` | Terminy podatkowe i „Radar Zmian" — monitoring nowelizacji przepisów. Szkielet. |
| `Finance/` | Dokumenty księgowe i generator pism do urzędów. Szkielet. |
| `Team/` | Pracownicy biura, ich obciążenie i uprawnienia. Szkielet. |
| `Settings/` | Dane biura i połączenia z systemami zewnętrznymi (KSeF, Fakturownia). Szkielet. |

Wewnątrz modułu `Client/` widać podział, który powtarzamy wszędzie:

- `Entity/` — czym jest klient (jakie ma pola)
- `Dto/` — sprawdzanie danych z formularza, zanim cokolwiek zapiszemy
- `Repository/` — rozmowa z bazą danych
- `Service/` — reguły biznesowe i zapis do dziennika zmian
- `Controller/` — odbieranie żądań z przeglądarki
- `Migration/` — przepis na utworzenie tabeli w bazie

**`Shared/` — kod wspólny dla wszystkich modułów.**

| Katalog | Po co jest |
|---|---|
| `Tenant/` | Pilnuje, żeby jedno biuro nigdy nie zobaczyło danych drugiego. Serce bezpieczeństwa. |
| `Auth/` | Wystawianie i sprawdzanie „przepustek" (tokenów) przy logowaniu. |
| `Audit/` | Zapisuje każdą zmianę danych do dziennika — wymóg AML i RODO. |
| `Http/` | Wspólny sposób odpowiadania na żądania i wspólna obsługa błędów. |
| `ExternalApi/` | Wspólny typ błędu, gdy zewnętrzna aplikacja nie odpowiada. |
| `Migration/` | Przepisy na tabele wspólne: biura i dziennik zmian. |

### Frontend (`frontend/src/`)

| Katalog | Po co jest |
|---|---|
| `modules/` | Ekrany — jeden katalog na moduł, lustrzane odbicie backendu. |
| `components/ui/` | Klocki, z których zbudowane są ekrany: karta, przycisk, etykieta, menu. |
| `components/layout/` | Rama aplikacji: menu boczne + górny pasek + miejsce na treść. |
| `styles/` | Kolory i kształty przepisane z projektu graficznego. |
| `router/` | Które kliknięcie w menu prowadzi na który ekran. |
| `stores/` | Pamięć podręczna przeglądarki: kto jest zalogowany, jacy są klienci. |
| `api/` | Jedno miejsce, przez które front rozmawia z backendem. |

---

## Jak to uruchomić?

Zakładamy, że nigdy nie otwierałeś terminala. Terminal to okno, w którym pisze się polecenia zamiast klikać.

### Krok 1: zainstaluj Dockera

Docker to program, który uruchamia wszystkie części Solidusa naraz — nie musisz osobno instalować bazy danych, PHP ani reszty. Pobierz **Docker Desktop** ze strony docker.com, zainstaluj i uruchom. Poczekaj, aż ikonka wieloryba w pasku zadań przestanie się animować.

### Krok 2: otwórz terminal w katalogu projektu

- **Windows:** wejdź do folderu z projektem w Eksploratorze plików, kliknij pasek adresu u góry, wpisz `cmd` i naciśnij Enter.
- **Mac:** kliknij folder prawym przyciskiem → „Usługi" → „Nowy terminal w folderze".

### Krok 3: uruchom aplikację

Wpisz dokładnie to i naciśnij Enter:

```
docker compose -f docker/docker-compose.yml up
```

Za pierwszym razem potrwa to kilka minut — Docker pobiera wszystkie potrzebne części. Zobaczysz dużo przewijającego się tekstu; to normalne. Gdy tekst przestanie się przewijać, aplikacja działa.

### Krok 4: przygotuj bazę danych

Otwórz **drugie** okno terminala (pierwsze musi zostać otwarte, bo w nim działa aplikacja) i wpisz:

```
docker compose -f docker/docker-compose.yml exec php ./yii migrate:up
```

To tworzy w bazie puste tabele. Robisz to tylko raz.

### Krok 5: otwórz aplikację

W przeglądarce wejdź na **http://localhost:5173**

Dodatkowo:
- **http://localhost:8080** — zaplecze (API); zwykły użytkownik tam nie zagląda
- **http://localhost:8025** — Mailhog, podgląd maili wysyłanych podczas testów. Żaden mail z wersji deweloperskiej nie trafia do prawdziwych ludzi — wszystkie lądują tutaj.

### Krok 6: załóż biuro

Nie ma jeszcze ekranu rejestracji, więc pierwsze biuro zakłada się poleceniem. W drugim oknie terminala:

```
curl -X POST http://localhost:8080/api/auth/register -H "Content-Type: application/json" -d "{\"tenantName\":\"Moje Biuro\",\"email\":\"ja@biuro.pl\",\"password\":\"bezpiecznehaslo\",\"name\":\"Jan Kowalski\"}"
```

W odpowiedzi zobaczysz `"slug":"moje-biuro"` — to identyfikator Twojego biura. Podajesz go przy logowaniu razem z e-mailem i hasłem.

### Jak zatrzymać?

W pierwszym oknie terminala naciśnij `Ctrl + C`.

---

## Jak dodać nową funkcję?

Powiedzmy, że chcesz dodać moduł **Umowy**. Kolejność jest zawsze taka sama — na wzór modułu Klienci.

### Zaplecze (backend)

1. **Tabela w bazie.** Utwórz `backend/src/Module/Contract/Migration/M<data>CreateContracts.php`, skopiuj strukturę z migracji klientów. **Pamiętaj o kolumnie `tenant_id`** — bez niej umowy jednego biura byłyby widoczne dla wszystkich.
2. **Zarejestruj migrację** w `backend/config/common/params.php`, w sekcji `sourceNamespaces`.
3. **Encja** — `Entity/Contract.php`: jakie pola ma umowa.
4. **Walidacja** — `Dto/ContractInput.php`: co musi być wypełnione, żeby zapis miał sens.
5. **Repozytorium** — `Repository/ContractRepository.php`. Użyj traitu `TenantScoped` i stałej `TABLE`; wtedy filtrowanie po biurze dzieje się samo.
6. **Serwis** — `Service/ContractService.php`: reguły biznesowe. **Każdy zapis zgłoś do `AuditLogger`.**
7. **Kontroler** — `Controller/ContractController.php`, dziedziczący po `ApiController`.
8. **Trasy** — dopisz adresy w `backend/config/common/routes.php`, w grupie chronionej.

### Interfejs (frontend)

9. **Menu** — dopisz pozycję w `frontend/src/router/modules.ts`.
10. **Trasa** — dopisz ekran w `frontend/src/router/index.ts`.
11. **Pamięć** — `frontend/src/stores/contracts.ts` na wzór `clients.ts`.
12. **Ekrany** — `frontend/src/modules/contract/`: lista, formularz, szczegóły.

### Sprawdzenie

13. **Test izolacji** — skopiuj `ClientRepositoryTest` i podmień nazwy. To nie jest formalność: bez tego testu nikt nie wie, czy nowa tabela naprawdę oddziela biura.
14. **Uruchom testy:** `docker compose -f docker/docker-compose.yml exec php composer test` oraz `npm test` w katalogu `frontend/`.

---

## Słowniczek

**Tenant** — jedno biuro rachunkowe korzystające z Solidusa. Wszystkie biura pracują na jednej instalacji aplikacji, ale każde widzi wyłącznie swoje dane. Analogia: wieżowiec z wieloma firmami — wspólne windy i recepcja, ale do cudzego biura nie wejdziesz.

**Migracja** — przepis na zmianę w bazie danych („dodaj tabelę umów", „dodaj kolumnę z datą"). Zapisany w kodzie, więc każdy komputer i serwer wykona dokładnie te same zmiany w tej samej kolejności. Bez tego jedna baza wyglądałaby inaczej niż druga.

**Endpoint** — jeden adres, pod który front zwraca się po konkretną rzecz. Jak numer wewnętrzny w firmie: `/api/clients` to „poproszę listę klientów".

**API** — zestaw takich adresów; sposób, w jaki dwa programy się dogadują. Front nie sięga do bazy sam — zawsze prosi backend przez API.

**JWT (token dostępowy)** — cyfrowa przepustka, którą dostajesz po zalogowaniu. Przy każdym żądaniu przeglądarka ją pokazuje, a backend sprawdza podpis. Jest ważna **15 minut** — gdyby ktoś ją przechwycił, szybko traci wartość.

**Refresh token** — druga przepustka, ważna **30 dni**, służąca wyłącznie do wyrobienia nowej 15-minutowej. Leży w ciasteczku, którego JavaScript strony nie może odczytać, więc jest trudniejsza do wykradzenia. Dzięki temu nie logujesz się co kwadrans, a mimo to krótka przepustka pozostaje krótka. Przy każdym odświeżeniu stara jest unieważniana — jeśli ktoś ukradł kopię, przestaje ona działać, gdy Ty odświeżysz sesję.

**Kolejka** — lista zadań „do zrobienia później". Gdy klikasz „wyślij do wszystkich klientów", aplikacja nie każe Ci czekać kilku minut na 300 maili: wpisuje zadanie do kolejki i odpowiada od razu, a osobny proces wysyła je w tle. **W tej wersji Solidusa kolejki nie ma** — wymaga programu (Redis), którego docelowy serwer nie udostępnia. Szczegóły i możliwe rozwiązania: ARCHITECTURE.md, sekcja 2.9.

**Audit log (dziennik zmian)** — zapis każdej zmiany danych: kto, kiedy, co zmienił i z jakiego adresu. Wymóg przepisów AML i RODO. Zapisujemy tylko pola, które faktycznie się zmieniły, żeby dziennik dało się czytać.

**Docker** — program, który uruchamia całą aplikację wraz z bazą i resztą jednym poleceniem, tak samo na każdym komputerze. Eliminuje problem „u mnie działa".

---

Techniczne uzasadnienie decyzji projektowych znajdziesz w [ARCHITECTURE.md](ARCHITECTURE.md).
