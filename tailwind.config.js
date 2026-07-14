/************************************************************
 * Tailwind CSS v4 configuration for NutriLog
 * - Prefix all utilities with `tw-` to avoid Bootstrap conflicts
 * - Disable preflight to prevent base resets from affecting Bootstrap
 ************************************************************/

/** @type {import('tailwindcss').Config} */
export default {
  prefix: 'tw',
  corePlugins: {
    preflight: false,
  },
  theme: {
    extend: {},
  },
  plugins: [],
};
