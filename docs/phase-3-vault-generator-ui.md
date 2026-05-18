# Phase 3 - Vault functionality, password generator, and UI components

## Vault workflows

Vault entries support:

- Title, username, URL, encrypted password, encrypted notes.
- Categories, folders, tags, expiration dates, custom encrypted fields.
- Favorite/starred entries.
- Soft delete and restore.
- Password history encrypted independently from current values.
- Optional encrypted attachment metadata and storage hooks.

Reveal and copy actions require explicit clicks. Every reveal/copy/edit/delete/share event is audited. Password reveal responses return only the requested secret and use anti-cache headers.

## Generator

The generator uses `random_int()` only. On PHP 8.3 this is backed by cryptographically secure randomness from the operating system. Modes:

- Character-set mode with uppercase/lowercase/numbers/symbols and ambiguous-character exclusion.
- Readable mode with confusing characters removed.
- Pronounceable mode using consonant/vowel syllable patterns.
- Passphrase mode using a Diceware-style local word list.
- Reusable profiles stored per user.

Entropy is estimated as:

```text
character-set mode: length * log2(pool size)
passphrase mode: word count * log2(word-list size)
```

Pronounceable passwords are easier to type but have lower entropy per character; the UI labels this tradeoff clearly.

## UI

NullAuth uses Bootstrap 5 with a blue/white enterprise theme:

- Sidebar navigation.
- Dashboard cards.
- Searchable vault list.
- Collapsible forms and panels.
- Modal dialogs for reveal/copy/share.
- Toast notifications.
- Responsive layouts.

No React, Next.js, or Node.js backend is used. jQuery is not required by the current UI; Bootstrap's native JS is sufficient.

## Frontend security

- Strict CSP with per-response nonces.
- `frame-ancestors 'none'` and `X-Frame-Options: DENY`.
- All output is escaped through `e()`.
- CSRF tokens on state-changing forms and AJAX requests.
- No secrets in localStorage/sessionStorage.
- Anti-cache headers on authenticated and secret-returning endpoints.
- Clipboard writes use `navigator.clipboard.writeText()` only after a user gesture and clear UI state after a timeout.

