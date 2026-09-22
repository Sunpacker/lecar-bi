import { FlatCompat } from '@eslint/eslintrc'
import { defineConfig, globalIgnores } from 'eslint/config'
import prettier from 'eslint-config-prettier/flat'

const compatibility = new FlatCompat({ baseDirectory: import.meta.dirname })

const eslintConfig = defineConfig([
  ...compatibility.extends('next/core-web-vitals', 'next/typescript'),
  prettier,
  globalIgnores(['.next/**', 'out/**', 'build/**', 'next-env.d.ts']),
])

export default eslintConfig
