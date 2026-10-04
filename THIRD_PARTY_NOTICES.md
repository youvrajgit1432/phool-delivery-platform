# Third-party notices

This project bundles third-party open-source libraries under `vendor/`.
They are included so the project runs from a clean clone without a Composer
install step. Each library retains its own license; see the `LICENSE` file
inside each package directory.

Principal bundled components:

- **PHPMailer** — LGPL-2.1 (mail)
- **Dompdf** and related (`masterminds/html5`, `sabberworm/php-css-parser`,
  `tecnickcom/tcpdf`, `phenx/php-font-lib`, `phenx/php-svg-lib`) — LGPL / MIT (PDF)
- **vlucas/phpdotenv** and `graham-campbell/result-type`, `phpoption/phpoption` — BSD-3-Clause (env)
- **Symfony** polyfills/components — MIT
- **GuzzleHttp**, `ralouphie/getallheaders`, `psr/*` — MIT
- **minishlink/web-push**, `web-token/*`, `spomky-labs/*`, `brick/*`,
  `fgrosse/phpasn1`, `thecodingmachine/safe` — MIT (web push)

If you redistribute this project, preserve these licenses. This project's own
source is licensed under MIT (see `LICENSE`).
