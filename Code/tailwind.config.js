/**
 * @type {import('tailwindcss').Config}
 */
module.exports = {
  content: [
    "./src/**/*.{html,js}",
    "./public/index.html"
  ],
  theme: {
    extend: {
      colors: {
        'green-primary': '#21fa90', // Hexadecimal
      },
    },
  },
  plugins: [],
}
