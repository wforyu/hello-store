'use strict'

/**
 * Thin client for the Laravel POS API.
 *
 * Two things matter here beyond "send JSON":
 *
 * 1. `Accept: application/json` on every call. Without it Laravel answers a
 *    401 with a 302 redirect to the login page, the fetch follows it, and the
 *    caller gets HTML instead of an error object.
 * 2. Network failures are tagged `offline`. That distinction drives the whole
 *    desktop POS: a rejected order is a different situation from a shop whose
 *    internet just dropped, and only the second one belongs in the outbox.
 */

const DEFAULT_API_URL = 'http://localhost:8000'

class ApiError extends Error {
  constructor (message, { status = null, offline = false, body = null } = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.offline = offline
    this.body = body
  }
}

class PosApi {
  constructor (store) {
    this.store = store
  }

  get baseUrl () {
    return (this.store.getMeta('api_url') || DEFAULT_API_URL).replace(/\/+$/, '')
  }

  get token () {
    return this.store.getMeta('token')
  }

  setApiUrl (url) {
    this.store.setMeta('api_url', String(url).replace(/\/+$/, ''))
  }

  async request (path, { method = 'GET', body = null, auth = true, timeoutMs = 15000 } = {}) {
    const url = `${this.baseUrl}${path}`
    const controller = new AbortController()
    const timer = setTimeout(() => controller.abort(), timeoutMs)

    const headers = { Accept: 'application/json' }
    if (body) headers['Content-Type'] = 'application/json'
    if (auth && this.token) headers.Authorization = `Bearer ${this.token}`

    let response

    try {
      response = await fetch(url, {
        method,
        headers,
        signal: controller.signal,
        body: body ? JSON.stringify(body) : undefined
      })
    } catch (error) {
      // fetch only rejects on a transport problem, which for a till means
      // "treat this as offline" rather than "show an error".
      throw new ApiError(
        error.name === 'AbortError' ? 'Server tidak merespons (timeout).' : 'Tidak ada koneksi ke server.',
        { offline: true }
      )
    } finally {
      clearTimeout(timer)
    }

    const text = await response.text()
    let parsed = null

    if (text) {
      try {
        parsed = JSON.parse(text)
      } catch {
        parsed = { raw: text.slice(0, 500) }
      }
    }

    if (!response.ok) {
      const message =
        parsed?.message ||
        parsed?.error ||
        (response.status === 401 ? 'Sesi tidak valid, silakan login ulang.' : `HTTP ${response.status}`)

      throw new ApiError(message, { status: response.status, body: parsed })
    }

    return parsed
  }

  /* ---------------------------------------------------------------- */

  async login (email, password) {
    const result = await this.request('/api/login', {
      method: 'POST',
      body: { email, password },
      auth: false,
      timeoutMs: 10000
    })

    if (!result?.success || !result?.data?.token) {
      throw new ApiError(result?.message || 'Login gagal.', { status: 401 })
    }

    this.store.setMeta('token', result.data.token)
    this.store.setJson('user', result.data.user)

    return result.data.user
  }

  logout () {
    this.store.setMeta('token', null)
    this.store.setJson('user', null)
  }

  isLoggedIn () {
    return Boolean(this.token)
  }

  currentUser () {
    return this.store.getJson('user', null)
  }

  /* ---------------------------------------------------------------- */

  async config () {
    const config = await this.request('/api/pos/config')
    this.store.setJson('pos_config', config)
    return config
  }

  /**
   * Pull the catalog delta. `since` is the server_time of the previous
   * successful pull, which the server captures *before* querying, so a row
   * written mid-flight is re-sent next time instead of being skipped.
   */
  async catalog ({ since = null } = {}) {
    const query = since ? `?since=${encodeURIComponent(since)}` : ''
    const result = await this.request(`/api/pos/catalog${query}`)

    this.store.upsertProducts(result.products || [])
    this.store.setMeta('catalog_cursor', result.server_time)
    this.store.setMeta('last_catalog_at', new Date().toISOString())

    return result
  }

  async checkout (payload) {
    return this.request('/api/pos/checkout', { method: 'POST', body: payload })
  }

  async sync (clientId, orders) {
    return this.request('/api/pos/sync', { method: 'POST', body: { client_id: clientId, orders } })
  }

  async syncStatus (clientId) {
    return this.request(`/api/pos/sync/status?client_id=${encodeURIComponent(clientId)}`)
  }

  async ping () {
    await this.request('/api/pos/config', { timeoutMs: 6000 })
    return true
  }
}

module.exports = { PosApi, ApiError, DEFAULT_API_URL }
