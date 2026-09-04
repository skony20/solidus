/**
 * Jedna lista modulow Solidusa.
 *
 * Uzywa jej menu boczne i router. Dodanie modulu zaczyna sie tutaj -
 * dzieki temu nawigacja i trasy nie moga sie rozjechac.
 */
export interface ModuleDefinition {
  /** Sciezka w adresie - odpowiada nazwie endpointu w API. */
  path: string
  /** Nazwa ikony z Material Symbols. */
  icon: string
  /** Podpis w menu. */
  label: string
}

export const navigationModules: ModuleDefinition[] = [
  { path: '/mission-control', icon: 'grid_view', label: 'Centrum Dowodzenia' },
  { path: '/klienci', icon: 'contacts', label: 'Klienci' },
  { path: '/aml', icon: 'shield', label: 'Ryzyko AML' },
  { path: '/delegacje', icon: 'flight_takeoff', label: 'Delegacje' },
  { path: '/komunikacja', icon: 'hub', label: 'Komunikacja' },
  { path: '/kalendarz', icon: 'calendar_month', label: 'Kalendarz' },
  { path: '/finanse', icon: 'account_balance', label: 'Finanse' },
  { path: '/zespol', icon: 'groups', label: 'Zespół' },
  { path: '/sygnalisci', icon: 'campaign', label: 'Sygnaliści' },
]
