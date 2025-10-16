# Educify PHP Backend (Interview) - Plain PHP + SQLite

This repository contains a minimal PHP backend implementation to support the frontend designs that was provided.

Features;
- Plain PHP (no framework) using PDO with SQLite
- Endpoints:
  - `POST /auth/register` - register user
  - `POST /auth/login` - login (returns JWT)
  - `GET /tutors` - list tutors (filter by q or subject)
  - `GET /tutors/{id}` - tutor profile
  - `GET /tutors/{id}/availability` - tutor availability
  - `POST /bookings` - book a lesson (uses seed student id=1)
  - `GET /promo/{code}` - check promo code
  - `POST /payments/create-intent` - simulated payment intent
  - `POST /payments/webhook` - webhook stub

  --Read this and understand before use--
- you have Seed script to populate sample datasets (seed.php)
- Composer.json included for the dependencies (firebase/php-jwt)
- Dockerfile & docker-compose for easy run

Local run (php builtin server)
1. Install PHP 8.0+ and Composer first
2. run `composer install` (to get firebase/php-jwt)
3. Seed DB: `php seed.php`
4. Run: `php -S 0.0.0.0:8000 -t public`

Notes
- JWT secret is read from `.env` or defaults to `change_me`. This scaffold includes a simple middleware to validate JWT for protected routes (not strictly used in all endpoints).
- Payments are simulated. Integrate Stripe or other provider for real payments.

--This covers all--