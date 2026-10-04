# Security Policy

## About this repository
This is a **sanitized public portfolio edition** of a system that was originally
used in production. All data shipped in this repository is fictional. There are
no real customer, vendor, rider or payment records.

## Reporting a vulnerability
If you find a security issue in the demo, please open a private report or email
the maintainer rather than filing a public issue with exploit details.

## Secrets
- No production credentials, API keys, SSL private keys or `.env` files are
  committed.
- Configuration is provided through environment variables; see `.env.example`.
- If you find a committed secret, treat it as compromised and report it.

## Scope
This project is a portfolio/demo edition and is not maintained as a
production-ready service.
