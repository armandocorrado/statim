# MedCare

Gestionale cloud multi-tenant per professioni sanitarie. Si parte dal
verticale odontoiatrico, ma l'architettura separa un **core trasversale**
(tenant, utenti, anagrafica paziente, audit log — comune a ogni
professione) da un **verticale clinico** specifico (cartella + odontogramma
per l'odontoiatria), che si innesta sul core senza che il core sappia nulla
di odontoiatria.

Stato attuale: **walking skeleton**. Tenant → utente con login →
anagrafica paziente (CRUD), tutto tenant-scoped, con audit log e RBAC.

Per l'architettura, le convenzioni e le decisioni tecniche vedi
[CLAUDE.md](./CLAUDE.md).

## Stack

- Laravel 13 (PHP 8.3+), PostgreSQL
- Inertia + React + Tailwind
- Pest (test)
- Deploy target: Plesk

## Setup locale

```sh
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Configurare in `.env` la connessione a un database PostgreSQL locale
(`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`):

```sh
php artisan migrate
php artisan db:seed
```

Il seeder crea due tenant demo ("Studio Dentistico Rossi" e "Studio
Dentistico Bianchi") con utenti admin/utente per ciascuno
(password `medcare!wild`), utile per verificare manualmente che i dati di uno
studio non siano mai visibili all'altro.

Avvio in sviluppo (server PHP + Vite in watch):

```sh
composer run dev
```

oppure separatamente:

```sh
php artisan serve
npm run dev
```

## Test

```sh
php artisan test
```

I test girano su SQLite in-memory (nessuna dipendenza da un server
Postgres). Include `tests/Feature/TenantIsolationTest.php`, che verifica
che un utente non possa mai vedere, modificare o eliminare i pazienti di
un altro tenant — nemmeno forzando l'ID nell'URL.
