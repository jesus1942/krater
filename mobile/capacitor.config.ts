import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.escuelanuevaaustral.app',
  appName: 'Escuela Nueva Austral',
  webDir: 'dist',
  server: {
    androidScheme: 'https',
  },
  plugins: {
    StatusBar: {
      style: 'default',
    },
  },
}

export default config
