---
paths:
  - 'app/Exceptions/**'
---

# Domain error codes, rendering and translations

## Domain exceptions: readonly errorCode + render(), paired with lang keys
Custom exceptions (see InvalidSlotStateException) carry a `public readonly string $errorCode` and implement `render(Request $request): JsonResponse` directly on the exception class (Laravel calls it automatically) rather than mapping them in a handler. `errorCode` is a short snake_case string that must have a matching key in `lang/*/messages.php`. Every throw site in a controller pairs with a `#[DocumentedResponse(...)]` attribute for Scramble/OpenAPI docs.

Scramble itself keeps only the last `#[DocumentedResponse]` per status. `App\Support\MergeRepeatedResponseAttributes` (an operation transformer registered in `AppServiceProvider::configureApiDocumentation()`) rebuilds a status documented more than once as one response: one `- ` bullet per attribute description, in attribute order, and one `{message, error_code}` schema whose `error_code` is an `enum` of the codes when every stacked attribute types it as a literal (`type: "array{message: string, error_code: 'slot_not_empty'}"`). So when an action can answer several codes of one status, give each its own attribute with a literal `error_code`; a status documented once keeps a plain `error_code: string` and is left exactly as Scramble builds it. `OpenApiDocumentationTest` checks every attribute's status and description reach the document.
