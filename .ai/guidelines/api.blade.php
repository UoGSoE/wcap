## API Endpoints

If you've changed anything under `routes/api.php` or `app/Http/Controllers/Api/`, please run through this short checklist before considering the work done:

- **Update the Scramble attributes.** The `#[QueryParameter]` attributes on each handler are what drive `/docs/api` (our live OpenAPI spec). If you've added, renamed, or removed a query parameter, update them. New endpoints need a one-line PHPDoc summary too — Scramble surfaces it to consumers.
- **Update `API.md`** if response shapes changed or new endpoints landed. It's the short, conventions-focused document people read first. `/docs/api` is the generated-from-code reference; `API.md` is the human bit.
- **Check the golden-master fixtures.** Anything in `tests/fixtures/coverage-report-*.json` (and friends) is asserting on the *exact* JSON shape. If you changed a report response, the corresponding fixture needs regenerating — delete it and re-run the test, it'll write a fresh one.
- **Reach for the existing helpers.** `App\Http\Controllers\Api\Concerns\ParsesDateWindowFilter` for `filter[from]/filter[to]`. The `accessManagerApi` gate for manager/admin-only endpoints. Spatie's `QueryBuilder` with `allowedFilters` for filtering. Don't reinvent these.

Our consumers are mostly Power BI users and AI agents acting on behalf of admins, *not* developers. So we optimise for "obvious from the response" over "minimal payload" — code+label pairs, slug-not-id references, named envelopes, that kind of thing.

The full set of conventions lives in the `practical-laravel-api` skill — load it before designing a brand-new endpoint or response shape.
