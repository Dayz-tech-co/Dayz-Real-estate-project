/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}'
  ],
  theme: {
    extend: {
      colors: {
        dayz: {
          deep: '#0B1210',
          card: '#111A17',
          emerald: '#0F3D2E',
          'emerald-soft': '#1C5A45',
          gold: '#C6A75E',
          'gold-soft': '#E4C87A',
          'text-soft': '#D1D5DB',
          'border-muted': '#1F2A26'
        },
        emerald: {
          900: '#064e3b',
          950: '#022c22'
        },
        gold: {
          accent: '#c9a227'
        }
      }
    }
  },
  plugins: [require('@tailwindcss/forms')]
}
