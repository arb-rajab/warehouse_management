---
paths:
  - 'lang/**'
---

# Lang

## Keep lang/en and lang/ar message keys in lockstep
Every key added to lang/en/messages.php needs the matching key added to lang/ar/messages.php, and vice versa. Domain exception `errorCode`s (see app/Exceptions rule) and other user-facing backend strings are looked up via `__('messages.key')`, never hardcoded, so a missing translation silently falls back to the key name in one locale.
