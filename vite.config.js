import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  base: './',
  plugins: [tailwindcss()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        tailwind: 'src/tailwind.css',
        'portal-ui': 'src/portal-ui.js'
      },
      output: {
        assetFileNames: '[name][extname]',
        entryFileNames: '[name].js'
      }
    }
  }
})