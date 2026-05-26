import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { crx } from '@crxjs/vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import manifest from './manifest.json' with { type: 'json' };

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    crx({ manifest }),
  ],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
});
