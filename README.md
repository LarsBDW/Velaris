# Velaris Automotive

A database-backed public inventory and enquiry foundation for Velaris Automotive. It follows the supplied brand reference: near-black surfaces, a restrained `#FF6A00` accent, Montserrat typography and a reusable geometric V mark.

## Run locally

```powershell
php -S 127.0.0.1:8123 -t public
```

Open `http://127.0.0.1:8123`. SQLite data is created and seeded at `storage/velaris.sqlite` on the first request.

## Data protection boundary

`/api/vehicles` explicitly selects public vehicle columns only. Dealer contacts, purchase/target prices, commissions and internal notes live in separate `dealers` and `vehicle_internal` tables and never appear in that endpoint. The private operational page at `/admin.php` requires `VELARIS_ADMIN_USER` and `VELARIS_ADMIN_PASSWORD` in the PHP/web-server environment; it deliberately fails closed when unset.

## Included assets

- `public/brand/logo-primary.svg` — primary wordmark
- `public/brand/logo-mark.svg` — favicon/app-mark-ready standalone V
- `public/assets/velaris-brand-reference.png` — supplied visual reference, used as the cinematic hero and inventory artwork source

Before production deployment, replace the seeded vehicle imagery and details with verified dealer-provided data; configure HTTPS, an authenticated admin system with roles, rate limiting/CAPTCHA, secure media storage, backups and transactional email. This foundation intentionally does not invent claims, pricing, dealer data or customer testimonials.
