import { cpSync, createReadStream, existsSync, statSync } from 'node:fs'
import type { IncomingMessage, ServerResponse } from 'node:http'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import vue from '@vitejs/plugin-vue'
import { defineConfig, type Connect, type Plugin } from 'vite'

const mockDir = fileURLToPath(new URL('../../docs/design-assets/mock-images', import.meta.url))

function sendMock(req: IncomingMessage, res: ServerResponse, next: Connect.NextFunction) {
  const rel = decodeURIComponent((req.url || '/').split('?')[0]).replace(/^\/+/, '')
  const root = path.resolve(mockDir)
  const file = path.resolve(root, rel)
  if (!file.startsWith(`${root}${path.sep}`) || !existsSync(file) || !statSync(file).isFile()) {
    next()
    return
  }
  res.setHeader('Content-Type', file.endsWith('.png') ? 'image/png' : 'image/jpeg')
  res.setHeader('Cache-Control', 'public, max-age=3600')
  createReadStream(file).pipe(res)
}

function mockImages(): Plugin {
  const mount = (middlewares: Connect.Server) => {
    middlewares.use('/mock', sendMock)
  }
  return {
    name: 'sistermart-mock-images',
    configureServer(server) {
      mount(server.middlewares)
    },
    configurePreviewServer(server) {
      mount(server.middlewares)
    },
    closeBundle() {
      cpSync(mockDir, path.resolve('dist/mock'), { recursive: true })
    },
  }
}

export default defineConfig({
  plugins: [vue(), mockImages()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    fs: {
      allow: [path.resolve(mockDir, '../..'), path.resolve('.')],
    },
  },
})
