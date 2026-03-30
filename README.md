# Event Reservation System

Web application for managing event reservations with Symfony 7, JWT authentication, and Passkeys (WebAuthn).

## Features

- Browse and reserve events
- Admin dashboard for managing events and reservations
- Passwordless authentication with Passkeys
- Email notifications via Resend

## Tech Stack

**Backend:** Symfony 7, PHP, Doctrine ORM
**Database:** PostgreSQL
**Auth:** JWT + Passkeys (WebAuthn)
**Email:** Resend API
**Infrastructure:** Docker (PHP-FPM, Nginx, PostgreSQL)

## Quick Start

### 1. Setup Environment

Create `.env` file with required variables:

```env
APP_ENV=prod
APP_SECRET=<generate with: php -r 'echo bin2hex(random_bytes(16));'>
APP_DOMAIN=http://localhost

DB_PASSWORD=!ChangeMe!
DATABASE_URL="postgresql://user:!ChangeMe!@db:5432/app?serverVersion=16&charset=utf8"

JWT_PASSPHRASE=<generate with: openssl rand -hex 16>
RESEND_API_KEY=<optional - for emails>
```

### 2. Start with Docker

```bash
git clone https://github.com/Griffith000/MiniProjet2A-EventReservation-penguin-team.git
cd MiniProjet2A-EventReservation-penguin-team
DB_PASSWORD=!ChangeMe! docker compose up -d
```

Access the app at **http://localhost:8080/**

**Default accounts:**
- Admin: `admin@example.com` / `Password123!`
- User: `user@example.com` / `Password123!`

### Alternative: Symfony CLI

```bash
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
symfony server:start -d
```

## Testing Passkeys

Passkeys require HTTPS. To test on mobile:

1. Install [ngrok](https://ngrok.com/download)
2. Expose your local app:
   ```bash
   ngrok http 8080  # or 8000 for Symfony CLI
   ```
3. Update `APP_DOMAIN` in `.env` to the ngrok HTTPS URL
4. Add to `.env`:
   ```env
   TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
   WEBAUTHN_RP_NAME="Event Reservation"
   ```
5. Restart the app and open the ngrok URL on your phone

## Email Setup

Get a free API key from [Resend](https://resend.com) and set `RESEND_API_KEY` in `.env`.

## Team

**Ghassen Latrach** — ING A2 Groupe 4 SG2
