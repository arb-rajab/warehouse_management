---
paths:
  - 'app/Observers/**'
---

# Observers

## Register observers via #[ObservedBy], not AppServiceProvider
Observers are wired with the `#[ObservedBy(XObserver::class)]` PHP attribute directly on the model (see Row.php), not registered in AppServiceProvider::boot(). Guard side-effecting hooks with `$model->wasChanged([...])` so unrelated updates don't re-trigger them. When a factory needs a bare parent record and would otherwise trigger an observer's side effects, wrap the parent creation in `Model::withoutEvents(fn () => ...)` (see CellFactory's Row creation) rather than disabling the observer globally.
