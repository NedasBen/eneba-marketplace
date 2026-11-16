interface ImportMetaEnv {
  /** Base URL of the Laravel API, without a trailing slash. */
  readonly VITE_API_URL: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
