/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}'
  ],
  theme: {
    extend: {
      colors: {
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
