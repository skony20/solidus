import type { Config } from 'tailwindcss'

/**
 * Tailwind czyta kolory ze zmiennych CSS zdefiniowanych w src/styles/tokens.css,
 * ktore z kolei sa przepisane z makiety docs/design/solidus.html.
 *
 * Dzieki temu paleta ma jedno zrodlo prawdy: zmiana koloru w tokens.css
 * przechodzi przez wszystkie klasy Tailwinda bez przebudowy configu.
 */
export default {
  content: ['./index.html', './src/**/*.{vue,ts,tsx}'],
  theme: {
    extend: {
      colors: {
        space: 'var(--space-bg)',
        surface: {
          DEFAULT: 'var(--surface)',
          low: 'var(--surface-container-low)',
          container: 'var(--surface-container)',
          high: 'var(--surface-container-high)',
          highest: 'var(--surface-container-highest)',
        },
        outline: 'var(--outline-variant)',
        content: {
          DEFAULT: 'var(--on-surface)',
          variant: 'var(--on-surface-variant)',
        },
        cyan: {
          DEFAULT: 'var(--cyan)',
          bright: 'var(--cyan-bright)',
        },
        magenta: {
          DEFAULT: 'var(--magenta)',
          bright: 'var(--magenta-bright)',
        },
        emerald: {
          DEFAULT: 'var(--emerald)',
          bright: 'var(--emerald-bright)',
        },
        amber: 'var(--amber)',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
      },
      borderRadius: {
        // Promienie z makiety: karty 2rem, male karty 1rem, elementy nawigacji 14px.
        glass: '2rem',
        'glass-sm': '1rem',
        nav: '14px',
      },
      boxShadow: {
        // Poswiaty z makiety - aktywny element nawigacji i przycisk akcji.
        'nav-active':
          '0 0 24px -6px rgba(0, 219, 231, 0.45) inset, 0 0 18px -8px rgba(0, 219, 231, 0.5)',
        glow: '0 0 26px -2px rgba(0, 219, 231, 0.65)',
        'glow-magenta': '0 0 26px -2px rgba(255, 75, 137, 0.65)',
      },
      backdropBlur: {
        glass: '20px',
        bar: '18px',
      },
      keyframes: {
        fadeIn: {
          from: { opacity: '0', transform: 'translateY(6px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
      },
      animation: {
        // Przejscie miedzy modulami - identyczne jak w makiecie.
        'fade-in': 'fadeIn .35s ease',
      },
    },
  },
  plugins: [],
} satisfies Config
