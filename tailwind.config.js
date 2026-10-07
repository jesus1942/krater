const plugin = require('tailwindcss/plugin')

module.exports = {
  purge: [
    './resources/views/**/*.php',
    './resources/assets/js/**/*.js',
    './resources/assets/js/**/*.vue',
    './resources/assets/sass/**/*.scss',
    './node_modules/\\@bytefury/spacewind/src/**/*.js',
    './node_modules/\\@bytefury/spacewind/src/**/*.vue',
    './node_modules/\\@bytefury/spacewind/plugin/**/*.js',
    'flatpickr/**/*.js',
    './public/js/pace/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        base: ['Poppins', 'sans-serif'],
      },
      colors: {
        primary: {
          50: '#F2F5F8',
          100: '#E1E8EF',
          200: '#C2D0DE',
          300: '#91A7BC',
          400: '#536F8C',
          500: '#102340',
          600: '#0D1D35',
          700: '#0A172A',
          800: '#07101D',
          900: '#040A12',
        },
        'ena-red': '#A5121C',
        'ena-gray': '#55565C',
        'ena-paper': '#F4F0E7',
        black: '#040405',
      },
      spacing: {
        7: '1.75rem',
        9: '2.25rem',
        72: '18rem',
        80: '20rem',
        88: '22rem',
        96: '24rem',
      },
      screens: {
        xxl: '1440px',
      },
    },
  },
  variants: {
    textColor: ['responsive', 'hover', 'focus', 'active', 'visited'],
    borderColor: ['responsive', 'hover', 'focus', 'active', 'focus-within'],
    borderRadius: ['responsive', 'hover', 'first', 'last'],
    boxShadow: ['responsive', 'hover', 'focus', 'active', 'group-hover'],
    borderStyle: ['responsive', 'hover', 'first', 'last'],
    borderWidth: ['responsive', 'last', 'hover', 'focus'],
  },
  plugins: [
    require('@bytefury/spacewind/plugin'),

    plugin(({ config, addBase }) => {
      let craterDefaultTypography = {
        fontFamily: config('theme.fontFamily.base'),
      }
      addBase({
        '.h1': {
          ...craterDefaultTypography,
        },
        '.h2': {
          ...craterDefaultTypography,
        },
        '.h3': {
          ...craterDefaultTypography,
        },
        '.h4': {
          ...craterDefaultTypography,
        },
        '.h5': {
          ...craterDefaultTypography,
        },
        '.h6': {
          ...craterDefaultTypography,
        },
        '.page-title': {
          ...craterDefaultTypography,
        },
        '.section-title': {
          ...craterDefaultTypography,
        },
      })
    }),
  ],
}
