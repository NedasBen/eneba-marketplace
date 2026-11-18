import { useEffect, useState } from 'react'

/**
 * How far the server's clock is ahead of this device's, from a response's
 * `server_time` and the moment it arrived. Keeps the reservation countdown
 * honest on a device whose clock is wrong.
 */
export function clockOffsetMs(serverTime: string, receivedAtMs: number): number {
  return Date.parse(serverTime) - receivedAtMs
}

/** Whole seconds left until `expiresAt` on the server's clock, never negative. */
export function secondsLeft(expiresAt: string, offsetMs: number, nowMs: number): number {
  return Math.max(0, Math.ceil((Date.parse(expiresAt) - (nowMs + offsetMs)) / 1000))
}

/** 754 → "12:34". */
export function formatCountdown(seconds: number): string {
  const minutes = Math.floor(seconds / 60)
  return `${minutes}:${String(seconds % 60).padStart(2, '0')}`
}

/** Seconds left on a reservation, ticking once a second. */
export function useCountdown(expiresAt: string | undefined, offsetMs: number): number | null {
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    if (expiresAt === undefined) return
    const timer = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(timer)
  }, [expiresAt])

  return expiresAt === undefined ? null : secondsLeft(expiresAt, offsetMs, now)
}
