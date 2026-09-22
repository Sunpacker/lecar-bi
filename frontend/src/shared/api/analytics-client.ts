import createClient from 'openapi-fetch'

import type { paths } from './generated/schema'
import { env } from '../config/env'

export const analyticsClient = createClient<paths>({
  baseUrl: env.analyticsApiUrl,
})
