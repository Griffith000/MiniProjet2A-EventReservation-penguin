# MiniProjet2A - Application Web de Gestion de Réservations d'Événements

A web application for managing event reservations, built with Symfony 7, JWT, and Passkeys.

## Description

This application allows:
- **Users** to browse events, view details, and make reservations online.
- **Administrators** to manage events and reservations through a secured interface.

Security is enforced via JWT (JSON Web Tokens) and Passkeys (WebAuthn).

## Technologies

- **Symfony 7** — PHP web framework
- **PostgreSQL** — Relational database
- **Docker** — Containerization (PHP-FPM, Nginx, PostgreSQL)
- **JWT (LexikJWTAuthenticationBundle)** — Stateless authentication
- **Passkeys / WebAuthn** — Passwordless authentication (web-auth/symfony-bundle)
- **Resend** — Transactional email (reservation confirmation)
- **Twig** — Templating engine
- **Doctrine ORM** — Database abstraction layer

## Installation

### Prerequisites
- Docker with compose support (Podman also works, just swap the command if you prefer)
- Resend API key if you want email sending
- ngrok only if you test Passkeys on a phone

### Env checklist (put these in `.env`)
```
APP_ENV=prod
APP_SECRET=<php -r 'echo bin2hex(random_bytes(16));'>
APP_DOMAIN=http://localhost

DB_PASSWORD=!ChangeMe!
DATABASE_URL="postgresql://user:!ChangeMe!@db:5432/app?serverVersion=16&charset=utf8"

JWT_PASSPHRASE=<openssl rand -hex 16>
RESEND_API_KEY=<optional>
```
Seeded accounts: `admin@example.com` and `user@example.com` (password: `Password123!`). Seed also includes 10 events with images and 3 reservations.
Passkeys: if testing with ngrok/phone, set `APP_DOMAIN=https://your-subdomain.ngrok-free.dev`, and add `TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR` and `WEBAUTHN_RP_NAME="Event Reservation"` in `.env`.
Resend: leave `RESEND_API_KEY` blank unless you provide your own key.

### Quick start (Docker compose, pre-seeded data)
```bash
git clone https://github.com/Griffith000/MiniProjet2A-EventReservation-penguin-team.git
cd MiniProjet2A-EventReservation-penguin-team
# edit .env and set APP_SECRET, JWT_PASSPHRASE, DB_PASSWORD, APP_DOMAIN

DB_PASSWORD=!ChangeMe! docker compose -f docker-compose.yml up -d
```
- App: http://localhost:8080/
- Reseed later: `docker compose down && docker volume rm miniprojet2a-eventreservation-penguin-team_postgres_data && DB_PASSWORD=!ChangeMe! docker compose -f docker-compose.yml up -d`

### Symfony CLI fallback (no containers)
```bash
composer install
# edit .env and set DATABASE_URL to point to your own Postgres, e.g.:
# DATABASE_URL="postgresql://user:pass@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
symfony server:start -d
```

## Testing Passkeys on a phone (ngrok)

WebAuthn/Passkeys require HTTPS and a real domain. If your machine does not have a fingerprint sensor, you can test Passkeys using your phone:

1. Install ngrok and sign up: https://ngrok.com/ (download: https://ngrok.com/download)
2. Start your app locally (Docker maps the app to port 8080; Symfony CLI defaults to 8000)
3. Expose it (pick the right port):
   ```bash
   ngrok http 8080   # Docker compose app on 8080
   ngrok http 8000   # Symfony CLI default
   ```
4. Copy the generated HTTPS URL (e.g. `https://xxxx.ngrok-free.app`)
5. Update `APP_DOMAIN` in `.env` to that URL
6. Restart the app so the new domain is picked up
7. Open the ngrok URL on your phone and register a Passkey using Face ID / fingerprint

> **Note for the professor:** The `APP_DOMAIN` must exactly match the origin in the browser (protocol + domain + port). If testing via ngrok, set `APP_DOMAIN` to the full ngrok HTTPS URL.

## Email Notifications (Resend)

Reservation confirmation emails are sent via [Resend](https://resend.com).

To enable them:
1. Create a free account at https://resend.com
2. Go to **API Keys** and create a new key
3. Set `RESEND_API_KEY=re_your_key` in `.env`

> The free tier allows sending from `onboarding@resend.dev` (no domain verification needed).

## Project Structure

```
src/
├── Controller/       # HTTP controllers (User, Admin, Auth)
├── Entity/           # Doctrine entities (Event, Reservation, User)
├── Repository/       # Custom Doctrine repositories
├── Service/          # Business logic (MailerService, etc.)
templates/
├── event/            # Event listing and detail templates
├── reservation/      # Reservation form and confirmation
├── admin/            # Admin dashboard templates
└── auth/             # Login / Passkey templates
docker/
└── nginx/conf.d/     # Nginx virtualhost configuration
```

## Team

- **Ghassen Latrach** — (ING A2 Groupe 4 SG2)
