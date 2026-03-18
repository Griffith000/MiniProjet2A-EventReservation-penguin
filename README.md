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
- **Twig** — Templating engine
- **Doctrine ORM** — Database abstraction layer

## Installation

### Prerequisites

- Docker & Docker Compose
- Symfony CLI (optional, for local dev)

### With Docker

```bash
git clone https://github.com/Griffith000/MiniProjet2A-EventReservation-penguin-team.git
cd MiniProjet2A-EventReservation-penguin-team

cp .env .env.local
# Edit .env.local: set DB_PASSWORD and JWT_PASSPHRASE

docker compose up -d --build
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console doctrine:fixtures:load
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

## Project Structure

```
src/
├── Controller/       # HTTP controllers (User, Admin, Auth)
├── Entity/           # Doctrine entities (Event, Reservation, User, Admin)
├── Repository/       # Custom Doctrine repositories
├── Service/          # Business logic services
templates/
├── event/            # Event listing and detail templates
├── reservation/      # Reservation form and confirmation
├── admin/            # Admin dashboard templates
└── auth/             # Login templates
```

## Team

- **Ghassen Latrach** — (ING A2 Groupe 4 SG2)
