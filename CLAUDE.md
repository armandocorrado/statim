# MedCare — contesto per Claude Code

Gestionale cloud multi-tenant per professioni sanitarie. Fase attuale:
**walking skeleton** — tenant → utente con login → anagrafica paziente
(CRUD), tutto tenant-scoped, con audit log e RBAC.

## Stack

- Laravel 13 (PHP 8.3 in produzione su Plesk; PHP 8.4 va bene in locale),
  PostgreSQL, Inertia + React + Tailwind, Pest per i test.
- Deploy target: Plesk.

## Architettura: core trasversale vs verticale clinico

- `app/Core/*` — tutto ciò che è comune a qualunque professione sanitaria:
  tenancy, utenti/ruoli, anagrafica paziente, audit log. **Non deve mai**
  importare nulla da `app/Modules/*`.
- `app/Modules/Dental/*` — verticale odontoiatrico (cartella clinica,
  odontogramma). **Vuoto per design** in questa fase: nessuna astrazione
  costruita finché non esiste un requisito concreto. Può dipendere da
  `App\Core` liberamente.
- `App\Models\User` resta nel namespace standard Laravel (`app/Models`)
  anziché sotto `App\Core\Users`, perché è strettamente accoppiato al
  sistema di auth del framework (Breeze/Sanctum/Fortify si aspettano
  `App\Models\User` per convenzione) — è comunque concettualmente "core",
  non verticale.

Sotto `app/Core/` l'organizzazione è per sotto-dominio (`Tenancy/`,
`Patients/`, `Audit/`, `Users/`), ciascuno con la propria struttura
`Models/`, `Http/Controllers/`, `Http/Requests/`, `Policies/` quando serve.

## Multi-tenancy (priorità numero uno: isolamento dati)

Database condiviso, `tenant_id` su ogni tabella tenant-scoped. Tre livelli
di difesa, nessuno dei quali da solo è sufficiente:

1. **`App\Core\Tenancy\Middleware\IdentifyTenant`** (alias di rotta
   `tenant`) — legge il tenant dall'utente autenticato (`$user->tenant`),
   verifica `is_active`, lo bind come singleton (`app('currentTenant')`) e
   imposta il team id di spatie/laravel-permission
   (`PermissionRegistrar::setPermissionsTeamId`). Applicato alle rotte
   autenticate che toccano dati clinici/anagrafici (es. `patients.*`), non
   al gruppo `web` globale.
2. **`App\Core\Tenancy\Concerns\BelongsToTenant`** (trait) — applica una
   Global Scope (`TenantScope`) che filtra automaticamente ogni query per
   `tenant_id`, e valorizza `tenant_id` in creazione dal tenant corrente.
   Usato da `User`, `Patient`, `AuditLog`.
3. **Policy esplicite** (es. `PatientPolicy`) — ri-verificano sempre
   `$model->tenant_id === $user->tenant_id`, indipendentemente dalla
   global scope. Questo è necessario perché il model binding implicito
   delle rotte avviene *prima* che `IdentifyTenant` giri (SubstituteBindings
   fa parte del gruppo middleware `web` di default, quindi precede i
   middleware di rotta come `tenant`): senza la policy, un ID di un altro
   tenant nell'URL risolverebbe comunque il record. La policy è quindi
   l'unica garanzia certa — vedi `tests/Feature/TenantIsolationTest.php`.

Fuori dal ciclo HTTP (job, seeder, console) non c'è tenant "magico": va
passato/bindato esplicitamente.

**Ogni nuovo model/tabella con dati di uno studio deve usare
`BelongsToTenant` e avere una Policy con il controllo esplicito del
tenant.** Non fidarsi mai della sola global scope.

## RBAC (spatie/laravel-permission) — ruoli FISSI

- Pacchetto con feature **teams**, `team_foreign_key` rimappato a
  `tenant_id` (`config/permission.php`): i ruoli sono per-tenant (ogni
  studio ha le proprie righe in `roles`), i permessi sono un catalogo
  globale.
- Le colonne `tenant_id`/`model_id` nelle tabelle del pacchetto sono state
  editate a mano nella migration pubblicata (`ulid` invece di
  `unsignedBigInteger`) per combaciare con le PK ulid di `tenants`/`users`
  — se si rigenera la migration da zero, questo va rifatto.
- **Principio: minimizzazione GDPR e protezione by design.** I ruoli sono
  fissi nel codice, a permessi predefiniti: l'Admin studio assegna ruoli
  predefiniti a utenti del proprio studio, ma **nessuno** — nemmeno lui —
  può modificare i permessi di un ruolo. Non esiste (e non deve mai
  esistere) un endpoint che editi il mapping ruolo→permessi: l'unica fonte
  di verità è `App\Core\Users\Support\TenantRoleProvisioner::defaultRolePermissions()`.
  Le Policy controllano sempre permessi (`$user->can('patients.delete')`),
  mai il nome del ruolo direttamente.
- **5 ruoli fissi per studio**: `admin` (accesso completo: dati clinici,
  economia, team, gestione utenti), `odontoiatra` (cartella clinica e
  odontogramma completi, piani di cura/preventivi, propria agenda, propria
  produzione — niente fatturazione/incassi), `igienista` (accesso clinico
  parziale, solo sezione igiene, propria agenda — niente fatturazione),
  `aso` (sola consultazione di agenda e scheda paziente — niente modifica
  dati clinici, niente economia), `segreteria` (anagrafica, agenda
  completa, fatturazione/incassi, CRM, gestione amministrativa di
  preventivi e consensi — **nessun** accesso a cartella/odontogramma).
  Solo `admin` ha permessi `users.*`: nessun altro ruolo gestisce utenti o
  assegna ruoli.
- **Confine Core/verticale**: `odontoiatra`/`igienista` e i permessi
  `clinical_records.*`/`odontogram.*` appartengono al verticale
  odontoiatrico, non al core. `TenantRoleProvisioner` (Core) non li
  conosce: espone un punto di estensione, `TenantRoleProvisioner::extend(string $key, callable $contributor)`,
  che fa merge additivo dei permessi per ruolo (chiave = idempotenza tra
  riavvii/test). `App\Modules\Dental\Providers\DentalServiceProvider`
  (registrato in `bootstrap/providers.php`) chiama `extend()` nel suo
  `boot()` per innestare i ruoli clinici e per aggiungere i permessi
  clinici al ruolo `admin` — così Core non importa mai nulla da
  `App\Modules\Dental`, ma il verticale può contribuire al catalogo.
- **Vincolo invalicabile**, verificato da un test di regressione
  (`tests/Feature/RoleGovernanceTest.php`): nessun ruolo non clinico
  (`aso`, `segreteria` — `admin` è l'eccezione esplicita, ha accesso a
  tutto) può mai ricevere un permesso `clinical_records.*`, `odontogram.*`
  o `treatment_plans.clinical.manage`.
- Provisioning di un nuovo tenant: chiamare
  `TenantRoleProvisioner::provisionDefaults($tenant)` prima di assegnare
  ruoli agli utenti di quel tenant.
- **Super-admin di piattaforma**: rinviato. Non esiste ancora nessuna
  rotta/UI di piattaforma. Quando servirà, **non** sarà un ruolo
  tenant-scoped (romperebbe il vincolo mono-tenant di `User` e
  mescolerebbe due domini di autorizzazione) — sarà un modello/guard
  separato da `App\Models\User`, così "nessun accesso ai dati clinici" è
  garantito dalla query stessa, non solo da un permesso mancante.

### Vincoli architetturali futuri (registrati ora, da NON costruire in questa fase)

- **Squadre cliniche** (nome: *squadra clinica* / `clinical_team` nel
  codice — **mai** "team", già usato per il tenant nelle spatie teams,
  per evitare collisione di nomi): uno studio ha più medici con
  collaboratori diversi organizzati in squadre; un ASO o un igienista deve
  vedere le agende **solo** degli operatori della propria squadra, non di
  tutto lo studio. Non risolvibile col permesso a grana grossa
  `agenda.view.all`: servirà un modello relazionale molti-a-molti
  (raggruppamento di operatori/collaboratori dello stesso tenant) e una
  policy che filtri le agende per appartenenza alla squadra. Da costruire
  insieme al modulo Agenda — oggi `aso`/`igienista` restano con
  `agenda.view.*`/`agenda.manage.own` a grana grossa come stato interinale.
- **KPI e spettanze per operatore** (indipendenti dalle squadre): le
  metriche del singolo operatore (produzione, fatturato, accettazione
  preventivi) e le spettanze dei collaboratori si basano sul legame
  prestazione→operatore, non sull'appartenenza a una squadra clinica.
  Vincolo da rispettare quando si costruirà il modulo
  prestazioni/cartella clinica: ogni prestazione deve **sempre**
  registrare l'operatore esecutore, ed eventualmente i collaboratori con
  la rispettiva quota — le squadre servono solo per la visibilità
  dell'agenda, mai per il calcolo di produzione/compensi.

## Audit log (GDPR art. 9)

- `App\Core\Audit\Concerns\Auditable` (trait) + `AuditObserver` +
  `AuditLog` model. Applicato a `Patient`.
- Tabella **append-only**: niente `updated_at`, nessuna rotta di
  update/delete. `created_at` ha `useCurrent()` a livello DB.
- **Attenzione alla ricorsione di boot**: `Model::observe()` in questa
  versione di Laravel fa `new static` internamente, quindi non può essere
  chiamato direttamente dentro un metodo `boot{Trait}()` (il model non ha
  finito di bootarsi). Va differito con `static::whenBooted(fn () =>
  static::observe(...))`, come fa il framework stesso per gli attributi
  `#[ObservedBy]`. Vedi `Auditable::bootAuditable()`.
- I campi PII di `Patient` (`fiscal_code`, `email`, `phone`, `address`,
  `notes`) usano il cast `encrypted` di Eloquent: `old_values`/`new_values`
  nell'audit log per quei campi contengono quindi ciphertext, non
  plaintext — è intenzionale (l'audit trail non deve essere un secondo
  posto dove trapela il dato in chiaro).
- **Caveat noto**: il cast `encrypted` re-cifra con IV casuale ad ogni
  assegnazione, quindi `getDirty()`/`getChanges()` può segnalare "modifica"
  anche quando il valore in chiaro non è cambiato (riassegnazione dello
  stesso valore da un form). Accettabile per la fase attuale; se diventa un
  problema, considerare un cast custom che confronta il plaintext prima di
  marcare dirty.

## Convenzioni

- **Chiavi primarie**: ULID (`HasUlids`) su `tenants`, `users`, `patients`,
  `audit_logs`. Non bigint autoincrement — dati sanitari non devono avere
  ID indovinabili.
- **Utente**: mono-tenant (un utente appartiene a un solo `tenant_id`,
  colonna diretta, non pivot). `email` unica globalmente (non per tenant).
- **Auto-registrazione pubblica disabilitata** (`routes/auth.php`): non ha
  senso per un SaaS B2B dove gli utenti vengono provisionati dentro un
  tenant. Gli utenti vengono creati via seeder per ora; un flusso di
  onboarding/invito è lavoro futuro. `RegisteredUserController` e la
  pagina `Register.jsx` sono stati rimossi (non lasciare codice morto).
- **Cancellazione paziente** = disattivazione (`is_active = false` +
  soft delete), mai cancellazione fisica — vincoli di conservazione del
  dato sanitario.
- **Lingua**: codice, nomi di variabili/colonne, commit message in
  inglese; UI in italiano. Le pagine Auth secondarie di Breeze
  (forgot/reset password, conferma password, verifica email) sono rimaste
  in inglese per ora — solo login, dashboard e le pagine Pazienti sono
  state tradotte, essendo l'unico percorso realmente in scope per questo
  walking skeleton.
- **Query portabili**: il target di produzione è PostgreSQL, ma i test
  girano su SQLite in-memory (default Laravel/Pest, veloce, nessuna
  dipendenza esterna). Evitare SQL specifico di Postgres (es. `ilike`) —
  vedi `PatientController::index()` che usa `whereRaw('LOWER(...) LIKE
  ?')` invece.

## Comandi utili

```sh
composer install
npm install
cp .env.example .env   # poi configurare DB_* per Postgres locale
php artisan key:generate
php artisan migrate
php artisan db:seed     # crea 2 tenant demo con un utente per ciascuno dei 5 ruoli
npm run dev              # oppure: composer run dev
php artisan test         # Pest, SQLite in-memory
```

Utenti demo dopo il seeder (password `medcare!wild` per tutti):
`admin@rossi.test`, `odontoiatra@rossi.test`, `igienista@rossi.test`,
`aso@rossi.test`, `segreteria@rossi.test` (tenant Studio Rossi) e gli
equivalenti `@bianchi.test` (tenant Studio Bianchi) — utili per verificare
manualmente l'isolamento tra tenant e i permessi per ruolo.
