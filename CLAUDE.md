# MedCare — contesto per Claude Code

Gestionale cloud multi-tenant per professioni sanitarie. Fase attuale:
**walking skeleton** — tenant → utente con login → anagrafica paziente
(CRUD), tutto tenant-scoped, con audit log e RBAC.

## Stack

- Laravel 13 (PHP 8.3 in produzione su Plesk; PHP 8.4 va bene in locale),
  MySQL, Inertia + React + Tailwind, Pest per i test.
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
- I campi PII cifrati di `Patient` (vedi sezione Anagrafica sotto) usano il
  cast `encrypted` di Eloquent: `old_values`/`new_values` nell'audit log
  per quei campi contengono quindi ciphertext, non plaintext — è
  intenzionale (l'audit trail non deve essere un secondo posto dove
  trapela il dato in chiaro).
- **Caveat noto**: il cast `encrypted` re-cifra con IV casuale ad ogni
  assegnazione, quindi `getDirty()`/`getChanges()` può segnalare "modifica"
  anche quando il valore in chiaro non è cambiato (riassegnazione dello
  stesso valore da un form). Accettabile per la fase attuale; se diventa un
  problema, considerare un cast custom che confronta il plaintext prima di
  marcare dirty.

## Anagrafica paziente (`Patient`)

Solo dati che identificano/descrivono la persona — nessun dato clinico
(quello starà nel verticale) e nessuno "storico" (si aggancia da altre
entità, non si duplica qui).

- **Cifratura selettiva**: solo i sotto-campi "via" di domicilio/residenza
  (`address_street`, `residence_street`) e i contatti/identificativi
  (`mobile_phone`, `landline_phone`, `email`, `fiscal_code`, `vat_number`,
  `notes`) usano il cast `encrypted`. CAP/città/provincia restano in
  chiaro **di proposito**, per poter filtrare via SQL (es. "pazienti per
  città") senza dover decifrare ogni riga in memoria.
- **Codice fiscale** (`App\Core\Patients\Rules\ValidFiscalCode`): valida
  solo formato (16 caratteri, tollerante all'omocodia) + carattere di
  controllo (algoritmo autoconsistente, tabelle verificate contro un
  esempio numerico pubblicato). **Non verifica** che il codice corrisponda
  davvero a cognome/nome/data di nascita/sesso/luogo di nascita — quel
  controllo richiederebbe una tabella dei codici catastali dei ~8000
  comuni italiani (+ esteri) ed è stato deliberatamente rimandato.
- **`source`** (fonte di provenienza): enum applicativo
  `App\Core\Patients\Enums\PatientSource`, mai testo libero — serve per
  KPI di acquisizione future.
- **Tutore/referente** (`guardian_patient_id` + `guardian_relationship`):
  self-relazione su `Patient`, verificata tenant-scoped e mai
  auto-referenziale (vedi `UpdatePatientRequest`). Copre **sia** il tutore
  di un paziente minore **sia** un intestatario fattura diverso dal
  paziente per un adulto — stessa relazione, non duplicata. Il caso
  "intestatario azienda" (non una persona/paziente) è stato
  deliberatamente rimandato alla fase Fatturazione. Nessuna UI di
  selezione tutore ancora (serve un componente di ricerca pazienti,
  fuori scope per questa passata) — il campo è supportato end-to-end nel
  backend, mostrato in sola lettura in `Patients/Show.jsx` se già
  valorizzato via API/tinker.
- **Deliberatamente assente**: "prima visita" (si deriva dal futuro
  modulo Agenda — prima data appuntamento — non è un campo da mantenere a
  mano su `Patient`); consensi (struttura separata, vedi sotto); contatti
  social (dimensione marketing, non anagrafica).

## Consensi e privacy (`App\Core\Consents`)

CORE trasversale (non odontoiatrico): governa il **diritto di usare** i
contatti già presenti su `Patient` (cellulare, email). Principio:
minimizzazione GDPR e protezione by design — consenso sempre esplicito,
mai preselezionato, separato per finalità, storicizzato, revocabile.

- **Schema**: `Consent` è un record per ogni ciclo
  concessione→(eventuale) revoca — stesso pattern di `Invitation`
  (`accepted_at` nullable), non un campo mutabile su `Patient`. Revocare
  aggiorna `revoked_at`, **non cancella mai la riga**: nessuna rotta di
  delete esiste su questo model. Una nuova concessione dopo una revoca
  crea una **nuova riga**, non riapre quella vecchia — lo storico "fu
  dato, poi revocato il giorno X" resta leggibile per sempre.
- **Finalità/modalità/versione informativa**: tre enum applicativi fissi
  (`ConsentPurpose`, `ConsentCollectionMethod`, `PolicyVersion`), mai
  testo libero né cataloghi modificabili da UI — coerente con "ruoli
  fissi" dell'RBAC. Le finalità sono load-bearing (ogni finalità è
  destinata a essere letta da un gate/modulo specifico altrove, es.
  "marketing" governerà il futuro invio massivo del CRM): un catalogo
  editabile da un tenant senza codice che lo interroghi sarebbe
  fuorviante. Aggiungere una finalità/modalità/versione futura = un nuovo
  case nell'enum, zero modifiche allo schema.
- **Minore/tutore**: `given_by_patient_id` (chi ha materialmente
  espresso il consenso, se diverso dal paziente) è derivato
  **automaticamente** al momento della registrazione da
  `Patient::isMinor()` (età < 18 su `date_of_birth`) **combinato con**
  `guardian_patient_id` — non dalla sola presenza di `guardian_patient_id`,
  perché quel campo su un adulto può significare "intestatario fattura"
  (es. azienda), non "tutore che consente per me". Nessun campo/picker
  per sceglierlo a mano nella UI in questa passata.
- **Gate centrale**: `App\Core\Consents\Support\ConsentGate::allows(Patient $patient, ConsentPurpose $purpose, ?string $channel = null): bool`
  — cerca il consenso più recente per paziente+finalità, verifica che sia
  attivo, e se `$channel` è passato (es. `'mobile_phone'`) verifica anche
  che quel campo contatto non sia vuoto su `Patient`. È il punto che ogni
  futuro modulo (CRM, promemoria agenda) deve interrogare prima di usare
  un contatto.
- **Enforcement non aggirabile in UI**: `Patient` è ora `Notifiable`
  (trait Laravel). Ogni `Notification` verso un `Patient` **deve**
  implementare `App\Core\Consents\Contracts\RequiresConsent`
  (`consentPurpose()`, `consentChannel()`) — il listener
  `App\Core\Consents\Listeners\EnforceConsentGate`, agganciato
  all'evento nativo `Illuminate\Notifications\Events\NotificationSending`
  (registrato in `AppServiceProvider::boot()`), blocca **di default**
  (fail-closed) qualunque notifica a un `Patient` che non la implementi —
  dimenticarsene blocca l'invio, non lo lascia passare. Se la implementa,
  interroga `ConsentGate::allows()` e blocca se `false`.
- **Confine noto**: questo intercetta tutto ciò che passa dal sistema
  Notification di Laravel. Un codice che chiamasse `Mail::send()`
  direttamente lo scavalcherebbe — stesso tipo di confine già accettato
  per l'isolamento tenant (difesa a più livelli, ma la garanzia finale è
  la disciplina + il pattern documentato qui).
- **Test del gating**: usare `Event::fake([NotificationSent::class, NotificationSkipped::class])`,
  **mai** `Notification::fake()` — quest'ultimo sostituisce l'intero
  channel manager e non spedisce mai l'evento `NotificationSending` che
  il listener intercetta, quindi il test passerebbe per il motivo
  sbagliato (niente parte perché tutto è finto, non perché il gate ha
  bloccato). Vedi `tests/Feature/ConsentGatingTest.php`.
- **Audit**: nessun trait `Auditable` generico su `Consent` (userebbe
  l'azione generica `updated` per la revoca) — chiamate esplicite ad
  `AuditRecorder::record()` con azioni nominate `consent_granted`/
  `consent_revoked`, stesso pattern di `role_changed`/`role_assigned`.
- **Permessi**: riusa `consents.manage` (già esistente nel catalogo RBAC,
  già solo `admin`+`segreteria`) — nessun permesso nuovo creato.
- **Rimandato deliberatamente** (registrato, non costruito): firma
  digitale su tablet, portale paziente, integrazione FSE (bastano nuovi
  case negli enum quando arriveranno), e gli altri diritti
  dell'interessato GDPR (accesso, rettifica, cancellazione, portabilità).

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
- **Query portabili**: il target di produzione è MySQL, ma i test girano su
  SQLite in-memory (default Laravel/Pest, veloce, nessuna dipendenza
  esterna). Evitare SQL specifico di un solo motore — vedi
  `PatientController::index()` che usa `whereRaw('LOWER(...) LIKE ?')`
  invece di `ilike` (Postgres-only). **Migrato da PostgreSQL a MySQL
  nel 2026-09**: se si trovano ancora riferimenti a `pgsql` in config o
  `.env.example`, sono residui da correggere, non convenzione attuale.
- **Caveat MySQL noto**: la migration `create_permission_tables`
  (spatie/laravel-permission) porta un commento del pacchetto su
  possibili errori `1071 Specified key was too long` per l'indice
  composito su `roles` (team_foreign_key ulid + name + guard_name,
  entrambi `VARCHAR(255)`) sotto InnoDB con `ROW_FORMAT` non `DYNAMIC` /
  `innodb_large_prefix` disattivato — versioni MySQL/MariaDB datate. In
  locale (MySQL moderno, DB `curaly`) non si è presentato; se ricompare
  su un altro ambiente, il fix standard è `Schema::defaultStringLength(191)`
  in `AppServiceProvider::boot()`, oppure verificare che l'engine abbia
  `innodb_large_prefix=ON` e `ROW_FORMAT=DYNAMIC`.

## Comandi utili

```sh
composer install
npm install
cp .env.example .env   # poi configurare DB_* per MySQL locale
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
