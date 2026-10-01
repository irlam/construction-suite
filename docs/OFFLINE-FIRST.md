# Offline-First Standard

Offline support is a product requirement, not a fallback screen.

## Target field workflow

```text
Office / Wi-Fi
  ↓
Sign in
  ↓
Prepare device / download working set
  ↓
Leave connectivity
  ↓
Work all day using local data
  ↓
Save every action locally first
  ↓
Connectivity returns
  ↓
Automatically retry queued actions
  ↓
Server acknowledges
  ↓
Mark local item synced
```

## Non-negotiable rules

### 1. Local-first submission

A field action that supports offline use is written to IndexedDB before the UI claims it is safely saved.

### 2. Stable client ID

Every queued state-changing action gets a client-generated UUID/idempotency key.

The same key is reused for retries.

### 3. Server idempotency

The server must be able to receive the same action more than once without creating duplicate records.

### 4. Queue states

Use a consistent state model such as:

- draft
- pending
- syncing
- synced
- needs_auth
- needs_attention

Avoid silently discarding failed items.

### 5. Retry triggers

Attempt synchronisation when:

- an action is submitted while online
- the browser fires `online`
- the app returns to the foreground
- the user requests Sync now
- a reasonable foreground interval runs
- Background Sync fires on supporting browsers

Do not rely on Background Sync alone, especially on iOS.

### 6. Account isolation

Local records belong to a stable user/account identifier. Data saved by one user must never appear automatically when a different account signs in on the same device.

### 7. Conflict handling

Automatic merge is safe only for operations whose rules are explicit.

Otherwise show **Needs attention** and preserve both client/server information until resolved.

### 8. Connectivity UX

Every module should use the same Suite status language:

- Online
- Offline
- X queued
- Syncing
- Up to date
- Sign in required
- X need attention

### 9. Cached application shell

The service worker should make the core shell/opening experience available offline after preparation.

Do not indiscriminately cache authenticated HTML or private responses without a reviewed strategy.

### 10. Testing

Each offline-capable module must include automated or scripted tests for:

- offline reopen
- draft persistence
- photos/files where supported
- queue persistence after refresh
- reconnect sync
- duplicate retry
- expired session
- switch to different account
- server validation failure
- server timeout/lost acknowledgement

## Reference implementations

Use the strongest existing patterns from:

- `irlam/docs` for preparation, cached working sets, account isolation and browser tests
- `irlam/defect-tracker` for durable field submissions, idempotency and reconnect queue behavior
