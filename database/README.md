# Database

Public demo database for the Phool Delivery platform.

- **Database name:** `phool_delivery_demo`
- **Engine:** MySQL / MariaDB (utf8mb4)
- **Tables:** 125 (schema only — no production rows)

## Files

| File | Purpose |
|---|---|
| `schema.sql` | Full table structure (125 tables, constraints, triggers). **No data.** |
| `demo_seed.sql` | Fictional demo data (admin, customer, vendors, riders, catalog, orders, delivery, reviews, earnings). |

## Import

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p phool_delivery_demo < database/demo_seed.sql
```

`schema.sql` creates the database and drops/recreates tables, so it is safe to re-run.

## Demo credentials

| Role | Login | Password |
|---|---|---|
| Admin | `demo_admin` | `DemoAdmin@123` |
| Customer | `demo.customer@example.test` | `DemoCustomer@123` |
| Vendor | `demo.vendor@example.test` | `DemoVendor@123` |
| Vendor 2 | `demo.vendor2@example.test` | `DemoVendor2@123` |
| Rider | `demo.rider@example.test` | `DemoRider@123` |
| Rider 2 | `demo.rider2@example.test` | `DemoRider2@123` |

Passwords are stored as `password_hash()` bcrypt hashes, never plaintext.

## Notes

- All names, phones, addresses, bank/tax values and orders are **fictional**.
- Only the tables needed to drive the demo dashboards and workflows are seeded; the remaining tables are created empty by `schema.sql`.
