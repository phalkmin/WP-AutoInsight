// Minimal flat config. The plugin's JS is hand-written jQuery in the WP admin;
// this catches undeclared globals and syntax rot, nothing stylistic.
module.exports = [
  {
    files: ["js/**/*.js"],
    languageOptions: {
      ecmaVersion: 2019,
      sourceType: "script",
      globals: {
        jQuery: "readonly",
        window: "readonly",
        document: "readonly",
        console: "readonly",
        wp: "readonly",
        ajaxurl: "readonly",
        abccAdmin: "readonly",
      },
    },
    rules: {
      "no-undef": "error",
      "no-unused-vars": ["warn", { args: "none" }],
      "no-redeclare": "error",
    },
  },
];
