/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Josefin Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        serif: ['"Marcellus"', 'ui-serif', 'serif'],
        display: ['"Marcellus"', 'serif'],
        body: ['"Josefin Sans"', 'sans-serif'],
      },
      colors: {
        gold: '#fdfc04', // Using our yellow as gold
        cyan: '#00fbff',
        obsidian: '#041628',
        charcoal: '#000000',
      },
      boxShadow: {
        'glow-gold': '0 0 15px rgba(253, 252, 4, 0.3)',
        'glow-cyan': '0 0 15px rgba(0, 251, 255, 0.3)',
      },
      letterSpacing: {
        'widest': '0.2em',
      }
    },
  },
  plugins: [],
}
