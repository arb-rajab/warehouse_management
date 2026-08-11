---
paths:
  - 'app/Exceptions/**'
---

# Exceptions

## Domain exceptions: readonly errorCode + render(), paired with lang keys
Custom exceptions (see InvalidSlotStateException) carry a `public readonly string $errorCode` and implement `render(Request $request): JsonResponse` directly on the exception class (Laravel calls it automatically) rather than mapping them in a handler. `errorCode` is a short snake_case string that must have a matching key in `lang/*/messages.php`. Every throw site in a controller pairs with a `#[DocumentedResponse(...)]` attribute for Scramble/OpenAPI docs.
