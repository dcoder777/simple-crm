# Simple CRM for Small Business

A lightweight PHP + MySQL CRM with mobile-first responsive UI and minimal-click workflows.

## Features
- Contacts & leads with status tracking (`New`, `Contacted`, `Won`, `Lost`)
- Interaction logs with timestamped notes and optional next follow-up date
- Dedicated views for today's follow-ups and overdue leads
- Kanban-style pipeline with quick status updates

## Setup
1. Create database and tables:
   ```bash
   mysql -u root -p < init.sql
   ```
2. Configure environment variables (or edit defaults in `db.php`):
   - `DB_HOST` (default `127.0.0.1`)
   - `DB_PORT` (default `3306`)
   - `DB_NAME` (default `simple_crm`)
   - `DB_USER` (default `root`)
   - `DB_PASS` (default empty)
3. Start PHP server:
   ```bash
   php -S 0.0.0.0:8000
   ```
4. Open `http://localhost:8000`

## Security
- Uses PDO prepared statements to prevent SQL injection
- Escapes output in HTML via `htmlspecialchars`
- CSRF token validation on all POST forms
- Server-side input validation for required fields and email/date formats
