import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

export default defineConfig({
  // Production is served under https://sistermart.vip/admin/
  base: process.env.NODE_ENV === 'production' ? '/admin/' : '/',
  plugins: [vue()],
  server: {
    host: '0.0.0.0',
    port: 5174,
  },
})
