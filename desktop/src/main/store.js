'use strict'

/**
 * Local durable store for the desktop POS.
 *
 * SQLite is used through sql.js (WASM) rather than a native driver on
 * purpose: better-sqlite3 has to be recompiled against every Electron ABI
 * and that turns `npm install` into a Visual Studio dependency. The whole
 * database is flushed to disk after every mutation, so a power cut can lose
 * at most the single statement that was in flight -- which is what a till
 * needs.
 */

const fs = require('node:fs')
const path = require('node:path')
const initSqlJs = require('sql.js')

const SCHEMA = `
CREATE TABLE IF NOT EXISTS meta (
  key   TEXT PRIMARY KEY,
  value TEXT
);

CREATE TABLE IF NOT EXISTS products (
  id            INTEGER PRIMARY KEY,
  name          TEXT NOT NULL,
  sku           TEXT,
  price         REAL NOT NULL DEFAULT 0,
  stock         INTEGER NOT NULL DEFAULT 0,
  category_id   INTEGER,
  category_name TEXT,
  is_active     INTEGER NOT NULL DEFAULT 1,
  image         TEXT,
  updated_at    TEXT,
  deleted       INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS products_category_idx ON products (category_id);
CREATE INDEX IF NOT EXISTS products_updated_idx  ON products (updated_at);

CREATE TABLE IF NOT EXISTS orders (
  client_ref      TEXT PRIMARY KEY,
  status          TEXT NOT NULL,
  payload         TEXT NOT NULL,
  total           REAL NOT NULL DEFAULT 0,
  amount_paid     REAL NOT NULL DEFAULT 0,
  change_due      REAL NOT NULL DEFAULT 0,
  payment_method  TEXT,
  order_type      TEXT,
  customer_name   TEXT,
  order_id        INTEGER,
  order_number    TEXT,
  stock_conflict  INTEGER NOT NULL DEFAULT 0,
  conflict_reason TEXT,
  server_message  TEXT,
  created_at      TEXT NOT NULL,
  synced_at       TEXT
);

CREATE INDEX IF NOT EXISTS orders_status_idx ON orders (status);
CREATE INDEX IF NOT EXISTS orders_created_idx ON orders (created_at);

CREATE TABLE IF NOT EXISTS outbox (
  client_ref TEXT PRIMARY KEY,
  state      TEXT NOT NULL DEFAULT 'pending',
  attempts   INTEGER NOT NULL DEFAULT 0,
  last_error TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS outbox_state_idx ON outbox (state, created_at);
`

let SQL = null
let db = null
let file = null

/* ------------------------------------------------------------------ *
 * low level helpers
 * ------------------------------------------------------------------ */

/**
 * sql.js throws on an `undefined` bound value, where PHP would have quietly
 * stored NULL. Every optional field reaching this layer comes from JSON
 * parsed off the wire, so normalising here is cheaper than finding the one
 * caller that forgot a key and taking the whole till down with it.
 */
const bindable = (params) => params.map((value) => (value === undefined ? null : value))

function all (sql, params = []) {
  const stmt = db.prepare(sql)
  try {
    stmt.bind(bindable(params))
    const rows = []
    while (stmt.step()) rows.push(stmt.getAsObject())
    return rows
  } finally {
    stmt.free()
  }
}

function one (sql, params = []) {
  return all(sql, params)[0] ?? null
}

function run (sql, params = []) {
  db.run(sql, bindable(params))
}

/**
 * Group statements atomically.
 *
 * Note there is deliberately no `persist()` inside: sql.js `export()` frees
 * every prepared statement and re-syncs the emscripten file, which ends any
 * open transaction. Flushing mid-transaction both loses the COMMIT and
 * silently discards the rows that were written. Always flush after COMMIT.
 */
function transaction (fn) {
  run('BEGIN')
  try {
    const result = fn()
    run('COMMIT')
    return result
  } catch (error) {
    try {
      run('ROLLBACK')
    } catch {
      // already rolled back by SQLite
    }
    throw error
  }
}

/**
 * Flush to disk atomically: write a sibling temp file, then rename over the
 * real one. A half-written SQLite file is unrecoverable; a leftover temp
 * file is harmless.
 */
function persist () {
  const tmp = `${file}.tmp`
  fs.writeFileSync(tmp, Buffer.from(db.export()))
  fs.renameSync(tmp, file)
}

function nowIso () {
  return new Date().toISOString()
}

function uuid () {
  return require('node:crypto').randomUUID()
}

/* ------------------------------------------------------------------ *
 * lifecycle
 * ------------------------------------------------------------------ */

function init (userDataPath) {
  file = path.join(userDataPath, 'pos.sqlite')
  fs.mkdirSync(userDataPath, { recursive: true })

  const wasmDir = path.join(__dirname, '..', '..', 'node_modules', 'sql.js', 'dist')

  return initSqlJs({ locateFile: (f) => path.join(wasmDir, f) }).then((module) => {
    SQL = module

    if (fs.existsSync(file)) {
      db = new SQL.Database(new Uint8Array(fs.readFileSync(file)))
    } else {
      db = new SQL.Database()
    }

    db.run(SCHEMA)
    persist()

    if (!getMeta('client_id')) setMeta('client_id', uuid())

    return { file, clientId: getMeta('client_id') }
  })
}

function close () {
  if (db) {
    persist()
    db.close()
    db = null
  }
}

/* ------------------------------------------------------------------ *
 * meta
 * ------------------------------------------------------------------ */

function getMeta (key, fallback = null) {
  const row = one('SELECT value FROM meta WHERE key = ?', [key])
  return row ? row.value : fallback
}

function setMeta (key, value) {
  run('INSERT INTO meta (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [
    key,
    value === null || value === undefined ? null : String(value)
  ])
  persist()
}

function getJson (key, fallback) {
  const raw = getMeta(key)
  if (raw === null) return fallback
  try {
    return JSON.parse(raw)
  } catch {
    return fallback
  }
}

function setJson (key, value) {
  setMeta(key, JSON.stringify(value))
}

/* ------------------------------------------------------------------ *
 * catalog
 * ------------------------------------------------------------------ */

/**
 * Apply a catalog delta. The server sends only rows whose updated_at moved
 * past the cursor, plus soft-deleted products flagged with deleted=1.
 */
function upsertProducts (products) {
  const stmt = db.prepare(`
    INSERT INTO products (id, name, sku, price, stock, category_id, category_name, is_active, image, updated_at, deleted)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON CONFLICT(id) DO UPDATE SET
      name = excluded.name,
      sku = excluded.sku,
      price = excluded.price,
      stock = excluded.stock,
      category_id = excluded.category_id,
      category_name = excluded.category_name,
      is_active = excluded.is_active,
      image = excluded.image,
      updated_at = excluded.updated_at,
      deleted = excluded.deleted
  `)

  try {
    for (const p of products) {
      stmt.run([
        p.id,
        p.name,
        p.sku ?? null,
        Number(p.price) || 0,
        Number.isInteger(p.stock) ? p.stock : Number(p.stock) || 0,
        p.category_id ?? null,
        p.category_name ?? null,
        // Default to sellable when the key is absent. A missing flag means the
        // server did not filter on it, not that the product is withdrawn --
        // defaulting to 0 would silently empty the whole grid.
        p.is_active === undefined || p.is_active === null ? 1 : (p.is_active ? 1 : 0),
        p.image ?? null,
        p.updated_at ?? null,
        p.deleted ? 1 : 0
      ])
    }
  } finally {
    stmt.free()
  }

  persist()
}

/**
 * Optimistic local stock decrement for a sale that has not reached the
 * server yet. Clamped at zero: the server is the authority on what really
 * sold, this only keeps the grid from offering stock the till already gave
 * away.
 */
function applyLocalStockDeltas (deltas) {
  const stmt = db.prepare('UPDATE products SET stock = MAX(0, stock + ?) WHERE id = ?')
  try {
    for (const [productId, delta] of Object.entries(deltas)) {
      if (Number(delta) === 0) continue
      stmt.run([Number(delta), Number(productId)])
    }
  } finally {
    stmt.free()
  }
  persist()
}

function listProducts ({ search = '', categoryId = null, includeInactive = false } = {}) {
  const where = []
  const params = []

  if (!includeInactive) where.push('is_active = 1')
  if (search) {
    where.push('(name LIKE ? OR sku LIKE ?)')
    params.push(`%${search}%`, `%${search}%`)
  }
  if (categoryId) {
    where.push('category_id = ?')
    params.push(categoryId)
  }

  const clause = where.length ? `WHERE ${where.join(' AND ')}` : ''

  return all(
    `SELECT id, name, sku, price, stock, category_id, category_name, image
     FROM products ${clause}
     ORDER BY name`,
    params
  )
}

function listCategories () {
  return all(`
    SELECT category_id AS id, category_name AS name, COUNT(*) AS count
    FROM products
    WHERE is_active = 1 AND category_id IS NOT NULL AND category_name IS NOT NULL
    GROUP BY category_id, category_name
    ORDER BY name
  `)
}

function productCount () {
  return one('SELECT COUNT(*) AS c FROM products WHERE is_active = 1').c
}

/* ------------------------------------------------------------------ *
 * orders + outbox
 * ------------------------------------------------------------------ */

/**
 * Record a sale that happened while the client was offline. The entry goes
 * into both `orders` (for the cashier's receipt history) and `outbox` (for
 * the next sync attempt). The payload is stored verbatim because that exact
 * shape is what POST /api/pos/sync validates.
 */
function createPendingOrder ({ payload, total, amountPaid, changeDue, localStockDeltas }) {
  const clientRef = payload.client_ref
  const ts = nowIso()

  transaction(() => {
    run(
      `INSERT INTO orders (client_ref, status, payload, total, amount_paid, change_due,
                           payment_method, order_type, customer_name, created_at)
       VALUES (?, 'pending_sync', ?, ?, ?, ?, ?, ?, ?, ?)`,
      [clientRef, JSON.stringify(payload), total, amountPaid, changeDue,
        payload.payment_method, payload.order_type, payload.customer_name ?? null, ts]
    )

    run(
      'INSERT INTO outbox (client_ref, state, attempts, created_at, updated_at) VALUES (?, ?, 0, ?, ?)',
      [clientRef, 'pending', ts, ts]
    )
  })

  persist()
  applyLocalStockDeltas(localStockDeltas)

  return clientRef
}

/**
 * Record a sale the server accepted immediately. Same row shape as a queued
 * one -- the cashier's history query does not care how the order travelled.
 */
function recordOnlineOrder ({ payload, quote, result }) {
  run(
    `INSERT INTO orders (client_ref, status, payload, total, amount_paid, change_due,
                         payment_method, order_type, customer_name, order_id, order_number,
                         stock_conflict, conflict_reason, server_message, created_at, synced_at)
     VALUES (?, 'synced', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      payload.client_ref,
      JSON.stringify(payload),
      quote.total,
      payload.amount_paid ?? 0,
      result.change ?? 0,
      payload.payment_method,
      payload.order_type,
      payload.customer_name ?? null,
      result.order_id ?? null,
      result.order_number ?? null,
      result.stock_conflict ? 1 : 0,
      result.conflict_reason ?? null,
      result.message ?? null,
      nowIso(),
      nowIso()
    ]
  )

  persist()
}

/**
 * A sale the server refused outright (strict mode, not enough stock). Kept so
 * the cashier can see why money did not change hands, but never queued.
 */
function recordRejectedOrder ({ payload, quote, message }) {
  run(
    `INSERT INTO orders (client_ref, status, payload, total, amount_paid, change_due,
                         payment_method, order_type, customer_name, server_message, created_at)
     VALUES (?, 'rejected', ?, ?, ?, 0, ?, ?, ?, ?, ?)`,
    [
      payload.client_ref,
      JSON.stringify(payload),
      quote.total,
      payload.amount_paid ?? 0,
      payload.payment_method,
      payload.order_type,
      payload.customer_name ?? null,
      message,
      nowIso()
    ]
  )

  persist()
}

/**
 * Claim a batch of unsent sales. Marked `sending` before the HTTP call so a
 * crash mid-flight leaves a visible state instead of a silent duplicate.
 */
function claimOutboxBatch (limit = 100) {
  const ts = nowIso()

  const claimed = transaction(() => {
    // The payload lives on `orders`; `outbox` only tracks retry bookkeeping.
    const rows = all(
      `SELECT ord.client_ref, ord.payload
       FROM outbox o
       LEFT JOIN orders ord ON ord.client_ref = o.client_ref
       WHERE o.state IN ('pending', 'sending')
         AND ord.status = 'pending_sync'
       ORDER BY o.created_at
       LIMIT ?`,
      [limit]
    )

    if (rows.length) {
      const stmt = db.prepare("UPDATE outbox SET state = 'sending', updated_at = ? WHERE client_ref = ?")
      try {
        for (const r of rows) stmt.run([ts, r.client_ref])
      } finally {
        stmt.free()
      }
    }

    return rows.map((r) => ({ client_ref: r.client_ref, payload: JSON.parse(r.payload) }))
  })

  persist()

  return claimed
}

function finishOutbox (clientRef, state, error = null) {
  run(
    `UPDATE outbox
     SET state = ?, attempts = attempts + 1, last_error = ?, updated_at = ?
     WHERE client_ref = ?`,
    [state, error, nowIso(), clientRef]
  )
  persist()
}

/**
 * Apply what the server said about one order. The order row is the record of
 * truth for the UI; the outbox row is just retry bookkeeping.
 */
function applySyncResult (result) {
  const clientRef = result.client_ref
  if (!clientRef) return

  run(
    `UPDATE orders
     SET status = ?, order_id = ?, order_number = ?, stock_conflict = ?,
         conflict_reason = ?, server_message = ?, synced_at = ?
     WHERE client_ref = ?`,
    [
      result.status,
      result.order_id ?? null,
      result.order_number ?? null,
      result.stock_conflict ? 1 : 0,
      result.conflict_reason ?? null,
      result.message ?? null,
      nowIso(),
      clientRef
    ]
  )

  run('DELETE FROM outbox WHERE client_ref = ?', [clientRef])
  persist()
}

function releaseOutboxClaims () {
  run("UPDATE outbox SET state = 'pending' WHERE state = 'sending'")
  persist()
}

function listOrders (limit = 50) {
  return all(
    `SELECT client_ref, status, total, amount_paid, change_due, payment_method,
            order_type, customer_name, order_id, order_number, stock_conflict,
            conflict_reason, server_message, created_at, synced_at
     FROM orders
     ORDER BY created_at DESC
     LIMIT ?`,
    [limit]
  )
}

/**
 * A single sale including the payload it was sent with. The list view leaves
 * `payload` out on purpose -- a history screen of 80 orders should not ship
 * 80 baskets across the IPC boundary -- but a reprint needs the original line
 * items, and the server has no endpoint that hands back basket contents for an
 * order placed by this till.
 */
function getOrder (clientRef) {
  const row = one('SELECT * FROM orders WHERE client_ref = ?', [clientRef])

  if (!row) return null

  try {
    row.payload = JSON.parse(row.payload)
  } catch {
    row.payload = null
  }

  return row
}

function stats () {
  const today = new Date().toISOString().slice(0, 10)

  return {
    pendingSync: one("SELECT COUNT(*) AS c FROM orders WHERE status = 'pending_sync'").c,
    rejected: one("SELECT COUNT(*) AS c FROM orders WHERE status = 'rejected'").c,
    conflicts: one('SELECT COUNT(*) AS c FROM orders WHERE stock_conflict = 1').c,
    todayOrders: one('SELECT COUNT(*) AS c FROM orders WHERE substr(created_at, 1, 10) = ?', [today]).c,
    todayRevenue: one(
      'SELECT COALESCE(SUM(total), 0) AS c FROM orders WHERE substr(created_at, 1, 10) = ?',
      [today]
    ).c,
    products: productCount()
  }
}

function exportDatabase () {
  return Buffer.from(db.export())
}

module.exports = {
  init,
  close,
  getMeta,
  setMeta,
  getJson,
  setJson,
  upsertProducts,
  applyLocalStockDeltas,
  listProducts,
  listCategories,
  createPendingOrder,
  recordOnlineOrder,
  recordRejectedOrder,
  claimOutboxBatch,
  finishOutbox,
  applySyncResult,
  releaseOutboxClaims,
  listOrders,
  getOrder,
  stats,
  exportDatabase
}
