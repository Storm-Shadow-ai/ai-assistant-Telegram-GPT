# Storm-Shadow-AI

Telegram bot with admin panel, usage limits, dialog memory, webhook protection, and OpenAI API integration.

## Stack

- PHP 7.4+
- MySQL / MariaDB
- Telegram Bot API
- OpenAI API

## Repository structure

- `admin/` — admin panel
- `app/` — core functions and integrations
- `config/` — local configuration files
- `migrations/` — SQL migrations
- `schema.sql` — base schema
- `webhook.php` — Telegram webhook entry point

## Quick start

1. Create a database.
2. Copy `config/config.example.php` to `config/config.php`.
3. Fill in database credentials.
4. Import `schema.sql`.
5. Apply SQL from `migrations/` if needed.
6. Open `/admin/login.php` and complete setup in the admin panel.
7. Set Telegram webhook to `https://your-domain/webhook.php`.

## Requirements

- PHP extensions: `pdo_mysql`, `curl`, `json`, `mbstring`
- HTTPS for Telegram webhook

## Security

- Do not commit `config/config.php`
- Keep API keys and webhook tokens only in local config or settings storage
- Restrict access to `/admin`

## License

See `LICENSE`.