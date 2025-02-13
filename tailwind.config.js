// tailwind.config.js
module.exports = {
  content: [
    "./src/**/*.{js,jsx}",
    "./build/**/*.{html,js}",
    "./**/*.php", // if you want to catch classes in your PHP files
  ],
  theme: {
    extend: {},
  },
  plugins: [],
};
