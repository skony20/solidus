/**
 * Pytania Audytu Gotowosci AML.
 *
 * Zrodlo: docs/design/landing.html (tablica QUESTIONS). Tresc jest przepisana
 * 1:1 - to material marketingowy uzgodniony z makieta, nie tekst do poprawiania
 * przy okazji.
 *
 * `weight` to waga pytania w wyniku: brak pisemnej procedury wazy trzy razy
 * tyle, co brak formalnego wyznaczenia osoby odpowiedzialnej.
 *
 * Pytania NIE ida z bazy - w odroznieniu od cennika nie zmieniaja sie w rytmie
 * decyzji handlowych, a ich wagi sa czescia logiki wyniku. Gdy zajdzie potrzeba
 * edytowania ich przez administratora, przeniesienie tego pliku do bazy pojdzie
 * tym samym wzorcem, co cennik.
 */
export interface AuditQuestion {
  id: number
  weight: number
  text: string
  /** Pokazywane w wyniku, gdy odpowiedz brzmiala "nie". */
  risk: string
}

export const AUDIT_QUESTIONS: AuditQuestion[] = [
  {
    id: 1,
    weight: 3,
    text: 'Czy masz pisemną, wewnętrzną procedurę AML zgodną z art. 50 ustawy?',
    risk: 'Brak pisemnej procedury to jedno z pierwszych pytań przy kontroli GIIF — i najłatwiejsze do wykazania uchybienie.',
  },
  {
    id: 2,
    weight: 3,
    text: 'Czy masz udokumentowaną ocenę ryzyka instytucji (Twojego biura jako całości)?',
    risk: 'Bez oceny ryzyka instytucji trudno wykazać, że w ogóle świadomie zarządzasz ryzykiem AML.',
  },
  {
    id: 3,
    weight: 3,
    text: 'Czy dla każdego klienta masz osobną, udokumentowaną ocenę ryzyka?',
    risk: 'Ocena „en masse" albo jej brak dla części klientów to jeden z najczęstszych powodów kar.',
  },
  {
    id: 4,
    weight: 3,
    text: 'Czy weryfikujesz klientów pod kątem list sankcyjnych i statusu PEP?',
    risk: 'Brak weryfikacji sankcyjnej/PEP to naruszenie podstawowego środka bezpieczeństwa finansowego.',
  },
  {
    id: 5,
    weight: 2,
    text: 'Czy zidentyfikowałeś/aś beneficjentów rzeczywistych klientów i zweryfikowałeś ich w CRBR?',
    risk: 'Nieustalony beneficjent rzeczywisty uniemożliwia rzetelną ocenę ryzyka klienta.',
  },
  {
    id: 6,
    weight: 2,
    text: 'Czy wszyscy pracownicy mający kontakt z AML przeszli szkolenie w ciągu ostatnich 2 lat?',
    risk: 'Ustawa wprost wymaga okresowych szkoleń (art. 52) — ich brak jest łatwy do wykrycia przy kontroli.',
  },
  {
    id: 7,
    weight: 2,
    text: 'Czy potrafisz w 24h odtworzyć, kto i kiedy zweryfikował danego klienta?',
    risk: 'Brak możliwości szybkiego odtworzenia decyzji budzi wątpliwości, nawet gdy dokumentacja formalnie istnieje.',
  },
  {
    id: 8,
    weight: 2,
    text: 'Czy masz procedurę zgłaszania GIIF podejrzanych transakcji i wiesz, kto za to odpowiada?',
    risk: 'Niejasna odpowiedzialność za zgłoszenia to częsta luka w mniejszych biurach.',
  },
  {
    id: 9,
    weight: 1,
    text: 'Czy masz wdrożony wewnętrzny kanał zgłoszeń dla sygnalistów?',
    risk: 'Kanał sygnalistów (art. 53) jest wymagany niezależnie od wielkości biura.',
  },
  {
    id: 10,
    weight: 1,
    text: 'Czy archiwizujesz dokumentację AML przez wymagane min. 5 lat, w łatwy do odnalezienia sposób?',
    risk: 'Sama archiwizacja nie wystarczy — dokumenty muszą być odnajdywalne na żądanie.',
  },
  {
    id: 11,
    weight: 1,
    text: 'Czy aktualizujesz oceny ryzyka klientów zgodnie z harmonogramem (24/12/6 mies.)?',
    risk: 'Nieaktualna ocena ryzyka jest traktowana podobnie jak jej brak.',
  },
  {
    id: 12,
    weight: 1,
    text: 'Czy wyznaczenie osoby odpowiedzialnej za AML w biurze jest formalnie udokumentowane?',
    risk: 'Bez formalnego wyznaczenia trudno wykazać, kto ponosi odpowiedzialność za zgodność.',
  },
]

export const MAX_AUDIT_SCORE = AUDIT_QUESTIONS.reduce((sum, q) => sum + q.weight, 0)
