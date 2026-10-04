# Contributing

Thanks for your interest. This repository is a sanitized portfolio edition.

## Ground rules
- Never commit real personal data. All demo data must be fictional.
- Never commit `.env` files, credentials, uploads, logs, or archives.
- Keep the existing architecture; avoid framework rewrites.
- Run PHP lint on every changed file: `php -l path/to/file.php`.
- Rebuild the demo database from `database/schema.sql` + `database/demo_seed.sql`
  and confirm the panels still work before opening a PR.

## Pull requests
1. Describe the change and why it is needed.
2. List the files changed and the checks you ran.
3. Keep changes scoped and reversible.
