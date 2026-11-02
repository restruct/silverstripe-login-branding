# README screenshots

`readme.shot.ts` writes the PNGs in `docs/images/` that the module README shows. It is not a test
suite: it runs with its own config, `playwright.screenshots.config.ts` in this directory, which the
browser tests and CI never load (their config only looks in `../specs`), so a normal test run never
rewrites the images. Never edit the PNGs by hand; regenerate them.

What it needs:

- a Silverstripe 6 host with this module installed (symlinked path repository) and the browser
  fixtures from `tests/browser/fixtures/` copied into its `app/`, then the database built.
  `LbReadmeShowcaseMiddleware` provides the demo states, switched on per request by the
  `lb-readme-shot` cookie: `stock` (login-forms without this module) and `branded` (neutral demo
  branding: `showcase-theme/templates/LoginIcon.ss` from this directory, `app_brand`, `built_by`).
  Without the cookie the host shows the module's defaults;
- `BROWSER_TARGET_URLS` pointing at that host (only the `ss6` target is used, else the first).

The shared runner in the maintainers' workspace does all of that:

```bash
BROWSER_ONLY=ss6 tools/browser/run.sh silverstripe-login-branding --config=screenshots/playwright.screenshots.config.ts
```

Against a host you started yourself, from `tests/browser/`:

```bash
npm ci
BROWSER_TARGET_URLS="ss6=http://127.0.0.1:8890" npx playwright test --config=screenshots/playwright.screenshots.config.ts
```

`showcase-theme/` lives here and not under `fixtures/` on purpose: the runner copies `fixtures/`
into the host's `app/`, where a `templates/` directory is part of the `$default` theme, so its
`LoginIcon.ss` would replace the default icon on every request and break `specs/branding.spec.ts`.
