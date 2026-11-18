import { ApiError } from './api/client'

/** Why a request failed, in words a shopper understands. */
export function describeError(error: unknown): string {
  if (error instanceof ApiError && error.isNetworkError) {
    return 'The server didn’t respond. On the free hosting tier it can take up to a minute to wake up.'
  }
  return 'Something went wrong on our side. Please try again.'
}
