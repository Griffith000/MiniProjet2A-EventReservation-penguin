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

- Docker & Docker Compose
- A [Resend](https://resend.com) account for email notifications
- [ngrok](https://ngrok.com) (required to test Passkeys on a phone — see below)

### Environment setup

Create a `.env.local` file at the project root with the following variables:

```env
APP_ENV=prod
APP_SECRET=<generate with: php -r "echo bin2hex(random_bytes(16));">

# Database
DB_PASSWORD=your_db_password

# JWT
JWT_PASSPHRASE=your_jwt_passphrase

# Resend (email notifications)
# Get your API key at https://resend.com → API Keys → Create API Key
RESEND_API_KEY=re_your_api_key_here

# Passkeys / WebAuthn — must match the exact origin the app is served from
# For local testing use: http://localhost
# For phone testing via ngrok use: https://your-subdomain.ngrok-free.app
APP_DOMAIN=http://localhost
```

### With Docker

```bash
git clone https://github.com/Griffith000/MiniProjet2A-EventReservation-penguin-team.git
cd MiniProjet2A-EventReservation-penguin-team

# Create and fill .env.local as described above
cp .env .env.local

docker compose -f docker-compose.yml up -d --build
docker compose -f docker-compose.yml exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f docker-compose.yml exec php php bin/console doctrine:fixtures:load --no-interaction
```

App available at: `http://localhost`

### Local Development (without Docker)

```bash
composer install
cp .env .env.local
# Configure DATABASE_URL in .env.local

php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
symfony server:start
```

## Testing Passkeys on a phone (ngrok)

WebAuthn/Passkeys require HTTPS and a real domain. If your machine does not have a fingerprint sensor, you can test Passkeys using your phone:

1. Install ngrok: https://ngrok.com/download
2. Start your app locally (Docker or Symfony CLI)
3. Expose it:
   ```bash
   ngrok http 80
   ```
4. Copy the generated HTTPS URL (e.g. `https://xxxx.ngrok-free.app`)
5. Update `APP_DOMAIN` in `.env.local` to that URL
6. Restart the app so the new domain is picked up
7. Open the ngrok URL on your phone and register a Passkey using Face ID / fingerprint

> **Note for the professor:** The `APP_DOMAIN` must exactly match the origin in the browser (protocol + domain + port). If testing via ngrok, set `APP_DOMAIN` to the full ngrok HTTPS URL.

## Email Notifications (Resend)

Reservation confirmation emails are sent via [Resend](https://resend.com).

To enable them:
1. Create a free account at https://resend.com
2. Go to **API Keys** and create a new key
3. Set `RESEND_API_KEY=re_your_key` in `.env.local`

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
