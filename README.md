# Phool Delivery

**Multi-Sided Commerce & Last-Mile Delivery Platform**

Phool Delivery is a multi-sided commerce platform that connects a customer
storefront, an admin operations back office, a vendor marketplace and a
rider/last-mile delivery app around a single order lifecycle.

> **Honest framing.** This software was originally developed and operated for a
> real flower-delivery business in Nepal. After the business was discontinued,
> this sanitized portfolio edition was created. **All customer, vendor, rider,
> order, payment and catalog data in this repository is fictional demo data.**
> It is not a currently operating service.

---

## Architecture

```
Customer                Admin                 Vendor               Rider
storefront    →  Order  back office    →  marketplace    →  last-mile app
public_html/            admin/                vendor-panel/        delivery-panel/
        \__________________ shared app core + one database (phool_delivery_demo) __________________/
```

Order lifecycle:

```
Customer → Order → Admin → Vendor (accept/process) → Rider (pickup/deliver) → Delivery complete
```

## The four experiences

| Experience | Folder | Entry point |
|---|---|---|
| Customer storefront | `public_html/` + shared `app/`, `api/`, `config/` | `public_html/index.php` |
| Admin operations | `admin/` | `admin/public/login.php` |
| Vendor marketplace | `vendor-panel/` | `vendor-panel/public/index.php` |
| Rider / last-mile | `delivery-panel/` | `delivery-panel/public/index.php` |

A landing page at the repository root (`index.php`) links to all four.

## Feature summary

- Customer storefront: catalog, product detail, cart, checkout, addresses, order tracking, reviews
- Admin operations: orders, products, categories, customers, vendors, riders, payments, CRM, notifications
- Vendor marketplace: products, orders, accept/process flow, availability, payouts
- Rider delivery: assigned orders, availability, pickup → in-delivery → delivered workflow, earnings
- Cross-cutting: notifications (email/SMS/push), reviews/ratings, invoices, an integrated order lifecycle

## Tech stack

- PHP 8.x (no framework; small in-house MVC/routing per panel)
- MySQL / MariaDB (utf8mb4)
- PDO, PHPMailer (via Composer), vanilla JS + CSS front-ends
- Optional integrations: SMTP, SMS gateway, Firebase/FCM push, Google OAuth
  (all disabled by default and configured via environment variables)

## Project structure

```
app/              shared storefront core (controllers, models, views)
api/              small JSON endpoints
config/           shared configuration
public/           storefront assets
public_html/      customer storefront (public web root)
admin/            admin panel (self-contained app)
vendor-panel/     vendor panel (self-contained app)
delivery-panel/   rider panel (self-contained app)
database/         schema.sql + demo_seed.sql + README
docs/             project documentation
```

## Database setup

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p phool_delivery_demo < database/demo_seed.sql
```

See [`database/README.md`](database/README.md) for details and demo accounts.

## Installation (local)

1. Place the project in your web root, e.g. `htdocs/phool-delivery-platform`.
2. Copy `.env.example` to `.env` (root, and inside `admin/`, `vendor-panel/`,
   `delivery-panel/` if you want per-panel overrides) and set `DB_*` values.
   With no `.env`, the app defaults to XAMPP (`root`, empty password) and the
   `phool_delivery_demo` database.
3. Import the schema and demo seed (above).
4. Open `http://localhost/phool-delivery-platform/`.

## Demo credentials

| Role | Login | Password |
|---|---|---|
| Admin | `demo_admin` | `DemoAdmin@123` |
| Customer | `demo.customer@example.test` | `DemoCustomer@123` |
| Vendor | `demo.vendor@example.test` | `DemoVendor@123` |
| Rider | `demo.rider@example.test` | `DemoRider@123` |

## Privacy statement

This repository contains **no real production data**. Uploads, logs, SSL keys,
production SQL dumps and hosting archives from the original deployment are
excluded. The demo database is generated from the schema and populated with
fictional records only.

## Known limitations

- This is a portfolio edition, not a production-hardened service.
- Only the tables needed to drive the demo dashboards are seeded; many of the
  125 tables are intentionally empty.
- Some optional integrations (push, SMS, OAuth) are configured but not wired to
  live providers.
- The three panels each carry their own bootstrap/config; they are not a single
  unified framework.

## Roadmap

- Consolidate shared bootstrap/config across panels.
- Expand demo seed coverage (vendor assignments, rider earnings, CRM).
- Add automated integration tests for the order lifecycle.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md).

## License

MIT — see [LICENSE](LICENSE). Third-party components retain their own licenses.

## Related project

**Krishi Sathi Research** — Field Research & Agricultural Interview Management
Platform. Originally hosted alongside this system, now maintained as a separate
repository.
