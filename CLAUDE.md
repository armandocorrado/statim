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
- `app/Modules/Dental/*` — verticale odontoiatrico. Contiene la cartella
  clinica (anamnesi, alert, diario, documenti — vedi sezione dedicata più
  sotto) e l'innesto dei ruoli clinici sull'RBAC (`DentalServiceProvider`).
  **L'odontogramma resta da fare**: nessuna astrazione costruita finché
  non esiste un requisito concreto. Può dipendere da `App\Core` liberamente.
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

## Agenda (`App\Core\Agenda`)

CORE trasversale: punto d'accesso al paziente, non un semplice calendario
— `Appointment` collega paziente + operatore + tempo, pensato per farci
convergere altri moduli futuri (una futura `Prestazione` potrà referenziare
`appointment_id`, nullable, senza toccare questo schema).

- **Risorsa**: `operator_id`/`assistant_id` (entrambi `User`) sono le due
  persone che un appuntamento impegna — nessun concetto di "poltrona"
  distinto nello schema (deliberatamente rimandato, nessun requisito
  concreto ancora). `patient_id` è **nullable**: un appuntamento senza
  paziente è un blocco/indisponibilità dell'operatore (pausa, ferie), non
  serve un flag separato.
- **`assistant_id`** (`User`, nullable): chi assiste alla poltrona (in
  genere un ASO), distinto da `operator_id` che tratta il paziente —
  legame reale a livello di dati, non solo una convenzione nell'elenco
  demo. Nelle viste Agenda, l'appuntamento compare sia nella colonna
  dell'operatore sia in quella dell'assistente (filtro lato frontend su
  `operator_id` OR `assistant_id`; lato backend il filtro `operator_id`
  in query string della vista fa match su entrambi).
- **Anti-sovrapposizione per persona, non per ruolo**
  (`App\Core\Agenda\Support\AppointmentOverlapChecker::personIsBusy()`):
  sia `operator_id` sia `assistant_id` (se valorizzato) partecipano al
  controllo, e per **ciascuna delle due persone** si verifica che non sia
  già impegnata in **nessuno dei due ruoli** su un altro appuntamento
  sovrapposto — non due corsie indipendenti "operatori sovrapposti" /
  "assistenti sovrapposti". Motivo: la stessa persona potrebbe essere
  operatore in un appuntamento e assistente in un altro, sovrapposti —
  due controlli separati per colonna lascerebbero passare questo caso
  (niente nello schema impedisce di assegnare un odontoiatra come
  `assistant_id` di un collega). Un appuntamento senza assistente non
  esegue affatto il secondo controllo. `assistant_id` non può inoltre
  coincidere con `operator_id` sullo stesso appuntamento (validato a
  parte, non è un controllo di sovrapposizione — non c'è un "altro
  appuntamento" da confrontare). Stesso meccanismo a due livelli già
  documentato sotto (FormRequest + ri-controllo con `lockForUpdate()` in
  transazione), applicato a entrambe le persone.
- **`status`**: enum applicativo fisso (`AppointmentStatus`) — guida
  logica reale (query "solo confermati", esclusione dal controllo
  sovrapposizioni), stessa ragione per cui ruoli RBAC e finalità dei
  consensi sono fissi. `Cancelled`/`NoShow` sono esclusi dal controllo
  sovrapposizioni: uno slot annullato o non presentato libera l'orario.
  **Nessuna rotta di delete**: annullare è un cambio di stato, mai una
  cancellazione fisica — stesso principio di `Patient`/`Consent`.
- **`appointment_type`**: al contrario di `status`, è un **catalogo
  per-tenant** (`AppointmentType`, tabella, non enum) — a differenza delle
  finalità dei consensi non guida alcuna logica applicativa (pura
  categorizzazione/colore), e ogni studio (futuro verticale incluso)
  vorrà le proprie categorie. Provisionato con 5 default sensati alla
  creazione del tenant (`AppointmentTypeProvisioner`, stesso pattern di
  `TenantRoleProvisioner`) — **nessuna UI di gestione/modifica ancora in
  questa passata**, solo lo schema pronto.
- **Squadra clinica — deliberatamente NON costruita in questa passata**:
  l'agenda è il suo aggancio naturale, ma la squadra serve solo a
  filtrare la *visibilità* — è un filtro additivo su query/policy quando
  verrà costruita, non richiede alcuna modifica allo schema `Appointment`
  qui presente. Fino ad allora `aso`/`igienista` con `agenda.view.all`
  vedono l'intero studio (stato interinale già dichiarato, nessuna
  regressione).
- **Anti-sovrapposizione**: MySQL non ha vincoli nativi di
  range-exclusion (a differenza di Postgres). Difesa a due livelli: (1)
  validazione nella FormRequest; (2) ri-controllo dentro
  `DB::transaction()` con `lockForUpdate()` nel controller, per chiudere
  la finestra di corsa critica tra due richieste concorrenti. **Limite
  onesto**: è un lock best-effort su un range scan indicizzato
  (`tenant_id`, `operator_id`, `start_at`), non una garanzia assoluta
  come un vincolo nativo — nessun test automatico dimostra davvero la
  concorrenza reale (un singolo processo PHPUnit sincrono non può
  simularla), solo il rifiuto logico della sovrapposizione.
- **Caveat MySQL sui timestamp NOT NULL**: `start_at`/`end_at` usano
  `dateTime()`, non `timestamp()`. Con `NO_ZERO_DATE` attivo, MySQL
  assegna implicitamente `DEFAULT CURRENT_TIMESTAMP` alla prima colonna
  `TIMESTAMP NOT NULL` senza default di una tabella, ma **rifiuta** ogni
  colonna successiva con lo stesso problema ("Invalid default value") —
  `appointments` ne ha due. Trovato eseguendo la migration sul MySQL
  reale, non dai test Pest (SQLite non ha questa restrizione): se altre
  tabelle future avranno più di una colonna `NOT NULL` di tipo
  data/ora senza default, usare `dateTime()`.
- **Viste**: giorno = colonne affiancate per operatore (la vista
  d'insieme multi-operatore); settimana = focalizzata su un operatore
  alla volta (una griglia settimanale multi-operatore sarebbe
  illeggibile). Creazione/modifica via modale sopra il calendario, non
  pagine separate come `Patients/Create` — pattern standard per
  interfacce di questo tipo.
- **Permessi**: riusa `agenda.view.own`/`agenda.manage.own`/
  `agenda.view.all`/`agenda.manage.all` (già nel catalogo RBAC) — nessun
  permesso nuovo. Chi ha solo `.own` può assegnare l'appuntamento solo a
  se stesso, sia in creazione (`AppointmentPolicy::create($user, $operatorId)`)
  sia in modifica (validazione dedicata in `UpdateAppointmentRequest`
  contro la riassegnazione).
- **Rimandato deliberatamente** (registrato, non costruito): promemoria
  automatici (dipendono da CRM e consensi, già pronti), prenotazione
  online dal portale paziente, collegamento a piano di cura/prestazioni.

## Fatturazione (`App\Core\Billing`)

CORE trasversale. **Solo le fondamenta interne in questa fase**: nessuna
integrazione reale con SdI (fattura elettronica) o Sistema TS (Tessera
Sanitaria), nessuna conservazione a norma vera — tutte e tre isolate
dietro interfacce con implementazioni mock, vedi sotto.

- **`BillingDocument`**: `patient_id` sempre valorizzato (la prestazione,
  serve al Sistema TS anche quando l'intestatario è diverso);
  `recipient_patient_id` + campi `recipient_*` (nome, codice fiscale,
  partita IVA, indirizzo) sono una **copia congelata** dei dati fiscali
  del destinatario al momento della creazione — non un join live su
  `Patient`: se il paziente corregge poi un indirizzo, i documenti già
  creati non devono cambiare silenziosamente. Stesso principio già
  applicato a `Consent.given_by_patient_id`.
- **Stati: solo `Draft` → `Issued`**, deliberatamente niente "annullato".
  Una volta emesso (numero assegnato), un documento fiscale non si
  cancella né si modifica — correggerlo richiederà una nota di credito
  (documento separato, futuro, fuori scope qui). Le bozze si modificano/
  eliminano liberamente (nessun impegno fiscale ancora). `document_number`/
  `document_year`/`fiscal_channel`/i tre campi `total_*` restano `null`
  finché bozza, **congelati** all'emissione — un futuro bugfix al calcolo
  totali non deve cambiare retroattivamente l'importo di una fattura già
  emessa.
- **Esenzione IVA senza aliquote inventate**: `vat_rate` (decimale,
  nullable — null/0 = esente) + `vat_exemption_reason` (testo libero, **non**
  un enum di codici norma — non sono certo che l'elenco applicabile sia
  solo "art. 10 n. 18 DPR 633/72", il più comune per le prestazioni
  sanitarie ma non necessariamente l'unico). Precompilato come
  suggerimento nella UI, resta modificabile. **Da validare con un
  commercialista** prima dell'uso reale.
- **Numerazione**: annuale con reset (convenzione più comune in Italia),
  tabella contatore dedicata `billing_document_counters`
  (`App\Core\Billing\Support\BillingDocumentNumberer`), **non** un
  `MAX(document_number)+1` con `lockForUpdate()` come si potrebbe pensare
  di fare per analogia con l'anti-sovrapposizione dell'Agenda — quel
  pattern lì lockerebbe un set di righe potenzialmente vuoto (nessun
  documento ancora quell'anno), che non protegge dal primo inserimento
  concorrente. La riga contatore esiste sempre una volta inizializzata,
  quindi c'è sempre qualcosa da lockare; la race sulla sua stessa
  creazione (primo documento dell'anno) è gestita a parte catturando
  `UniqueConstraintViolationException` e ri-lockando la riga del
  vincitore. Assegnato **solo** all'emissione, mai in bozza.
- **Le tre porte esterne** (`App\Core\Billing\Contracts`):
  `ElectronicInvoiceGateway` (SdI), `HealthExpenseReportingGateway`
  (Sistema TS), `DigitalPreservationGateway` (conservazione a norma) — tre
  interfacce, tre implementazioni `Mock*` (`App\Core\Billing\Gateways`)
  bindate in `AppServiceProvider::register()`. **Cosa serve prima di
  sostituirle con implementazioni reali**: certificati e credenziali SdI,
  ambiente di test Sistema TS, un conservatore accreditato per la
  conservazione. Cambiare solo quei binding quando arriverà quella fase —
  nessun'altra riga del modulo dipende dai dettagli esterni.
- **La regola SdI vs Sistema TS — esplicita, non implicita**
  (`App\Core\Billing\Support\FiscalChannelResolver`): un documento le cui
  spese sono riportate al Sistema TS non deve **mai** essere inviato
  anche allo SdI verso il privato — divieto di legge a tutela della
  riservatezza dei dati sanitari (soggetto a proroga annuale, verificare
  la normativa vigente al momento dell'integrazione reale). Il campo
  `fiscal_channel` è **un unico enum**, non due booleani indipendenti
  (`sent_to_sdi`/`sent_to_ts`): la violazione "inviato a entrambi" è
  strutturalmente irrappresentabile nello schema, non solo vietata da un
  controllo runtime dimenticabile. Oggi il resolver ha una sola regola
  (destinatario sempre persona fisica, perché non esiste ancora un
  intestatario azienda — deliberatamente rimandato, come già per
  l'anagrafica) → sempre Sistema TS. **Da validare con un commercialista**:
  l'esatta condizione che fa scattare il divieto SdI (qui legata a
  "destinatario persona fisica"; non è chiaro se l'eventuale opposizione
  del paziente all'invio al Sistema TS cambi comunque il divieto SdI o
  meno).
- **Permessi**: riusa `billing.view`/`billing.manage` (già solo
  admin+segreteria) — nessun permesso nuovo. `payments.manage` resta
  **riservato e non usato**: il tracciamento incassi è un'entità distinta,
  non nello scope di questa passata (lo suggerisce già il fatto che sia
  un permesso separato da `billing.manage`).
- **Rimandato deliberatamente**: nota di credito (per correggere un
  documento emesso), tracciamento incassi/pagamenti (`payments.manage`
  riservato per quello), intestatario azienda (non-`Patient`), le
  integrazioni reali stesse (vedi sopra).

## Cartella clinica (`App\Modules\Dental`) — primo contenuto reale del verticale

**Non è core**: vive in `App\Modules\Dental`, il core non la importa mai.
Si aggancia a `Patient` (core) via `patient_id`, senza che `Patient` sappia
nulla di lei. 4 entità: `DentalAnamnesis`, `DentalAlert`,
`DentalDiaryEntry`, `DentalDocument`, più `DentalToothCondition` per
l'odontogramma (sezione dedicata più sotto).

- **Anamnesi e alert NON sono sezionati per igiene — scelta deliberata,
  confermata esplicitamente**: sono dati di sicurezza (allergie, fattori
  di rischio) che riguardano chiunque tratti il paziente, non solo chi fa
  igiene — un'allergia al lattice deve essere nota anche all'igienista.
  Visibili/modificabili da chiunque abbia **un qualunque** permesso
  clinico (`clinical_records.view`/`.update` **oppure**
  `clinical_records.hygiene.view`/`.update`) — vedi
  `App\Modules\Dental\Support\ClinicalAccessChecker::canView()`/`canManage()`.
  Un alert risolto si disattiva (`is_active = false`), non si cancella.
- **Diario e documenti SONO sezionati** (`DentalRecordSection`:
  `General`/`Hygiene`) — è qui che si applica davvero il permesso parziale
  dell'igienista: vede/scrive solo le voci `Hygiene`, odontoiatra/admin
  vedono e taggano entrambe le sezioni
  (`ClinicalAccessChecker::canViewSection()`/`canManageSection()`).
- **Diario e documenti sono append-only**: nessuna rotta di update/delete
  su nessuno dei due model. Una nota di seduta non si corregge, si annota
  con una nuova voce — stesso principio di
  `AuditLog`/`Consent`/`BillingDocument` emesso, qui applicato perché una
  cartella clinica cartacea funziona così nella pratica sanitaria reale:
  non si cancella un'annotazione passata.
- **RBAC bloccato dal sistema**: segreteria e ASO non hanno **nessuno**
  dei permessi `clinical_records.*` nel catalogo RBAC attuale — le Policy
  ritornano `false` per costruzione, senza bisogno di un controllo ad hoc
  "blocca segreteria/ASO". Nessun permesso nuovo creato in questa passata.
- **Audit anche in lettura — capacità nuova**: finora `Auditable` tracciava
  solo le scritture. Qui, in aggiunta (scritture su `DentalAnamnesis`/
  `DentalAlert`/`DentalDiaryEntry`/`DentalDocument` via trait `Auditable`
  come al solito), l'apertura della cartella clinica registra **una sola**
  voce esplicita `AuditRecorder::record($patient, 'clinical_record_viewed', [], [])`
  per l'intera pagina — legata al `Patient` (non a un sotto-model, che
  potrebbe non esistere ancora), non una voce per ogni sotto-risorsa
  renderizzata (altrimenti è rumore, non tracciabilità utile).
- **Cifratura**: cast `encrypted` su tutti i campi testuali clinici
  (`DentalAnamnesis.*`, `DentalAlert.description`,
  `DentalDiaryEntry.content`, `DentalDocument.description`) — stesso
  meccanismo già in uso per gli indirizzi di `Patient`.
- **Documenti — solo storage privato per ora, confermato esplicitamente**:
  i file vivono sul disco `local` (`storage/app/private`, mai raggiungibile
  via URL pubblico — a differenza del disco `public`, non ha symlink in
  `public/`). Download solo tramite rotta autenticata+autorizzata dalla
  Policy (stessa logica sezione/ruolo di sopra), mai un URL diretto. **Non**
  cifrati byte-per-byte sul disco (rimandato — complessità reale per file
  grandi come radiografie, sproporzionata per questa passata). Nessuna
  rotta di update/delete, stesso principio del diario.
- **Policy delle Policy**: `ClinicalAccessChecker` (in
  `App\Modules\Dental\Support`) centralizza la regola "pieno OR igiene"
  usata da tutte e 4 le Policy — un solo punto invece di ripeterla quattro
  volte leggermente diverse.
- **Gate dedicato per l'apertura della pagina**: `view-clinical-record`
  (registrato in `DentalServiceProvider::boot()`, non in un metodo su
  `PatientPolicy` — quest'ultima è nel core e non deve mai importare
  `ClinicalAccessChecker` dal verticale).
- **Rimandato deliberatamente** (registrato, non costruito): integrazione
  scanner/sistemi radiologici, FSE, collegamento a piani di cura/preventivi
  (l'odontogramma è progettato per agganciarsi in futuro, vedi sotto),
  cifratura dei file a livello di byte.

## Odontogramma interattivo (`App\Modules\Dental`)

Un'unica entità append-only, `DentalToothCondition` — non una tabella per
dente né uno "stato corrente" salvato. Deliberatamente **non collegato**
ora a preventivi/piani di cura (rimandato, confermato esplicitamente), ma
`tooth_number` (stringa FDI) e l'id del record sono chiavi stabili già
pronte per un futuro aggancio.

- **Numerazione FDI/ISO 3950 a due cifre** (quadrante + posizione), non
  l'"Universal" 1-32 americano — standard italiano/europeo. Copre fin da
  subito sia la dentatura **permanente** (quadranti 1-4 × posizioni 1-8:
  `11`-`48`) sia quella **decidua** (quadranti 5-8 × posizioni 1-5:
  `51`-`85`), perché uno studio tratta bambini e capita la dentizione
  mista (6-12 anni, permanenti e decidui coesistono nello stesso
  paziente). Nessuna tabella: è uno standard fisso, validato da
  `App\Modules\Dental\Support\FdiToothNumbers` (usata sia lato server per
  la validazione sia per generare l'elenco denti passato alla UI).
- **Stati clinici** (`App\Modules\Dental\Enums\ToothCondition`): set
  iniziale estendibile (`Carious`, `Filled`, `Missing`, `ToExtract`,
  `Implant`, `Crown`, `RootCanalTreated`, `Fractured`, `Bridge`,
  `Sealant`) — nuovo case quando serve, mai un catalogo editabile da UI.
  "Sano" **non è un case**: l'assenza di qualunque record per un dente è
  lo stato di default, per non dover scrivere una riga per ogni dente
  sano di ogni paziente.
- **Storicizzazione append-only, coerente col diario clinico**: un
  cambio di stato non aggiorna la riga precedente, ne aggiunge una nuova
  — nessuna rotta di update/delete su `DentalToothCondition`. Lo stato
  **corrente** di un dente è derivato, non salvato: il record più recente
  per quel `(paziente, tooth_number)` vince, **qualunque sia il suo
  `condition_type`** (un solo simbolo per dente — scelta esplicita,
  confermata: non stati concorrenti indipendenti per condition_type,
  come un vero cartellino cartaceo). Calcolato da
  `App\Modules\Dental\Support\ToothConditionResolver::currentStates()`,
  mai inline nel controller. Ordinato per `recorded_date` (la data
  clinica dell'evento, può essere retrodatata) e a parità di data per
  `id` — gli ULID sono cronologicamente ordinabili, `created_at` ha solo
  granularità al secondo, stesso accorgimento già adottato per la
  numerazione dei documenti di fatturazione. Lo storico completo resta
  sempre consultabile, indipendentemente da quale record sia "corrente".
- **Collegamento al diario clinico**: `diary_entry_id` nullable verso
  `DentalDiaryEntry` — un intervento su un dente può referenziare la nota
  di diario della stessa seduta, ma non è obbligatorio (es. rilevazione
  durante un controllo, senza nota di diario associata).
- **RBAC — deliberatamente più stretto della cartella clinica generale**:
  usa i permessi `odontogram.view`/`odontogram.update` (già esistenti nel
  catalogo, riservati ad admin/odontoiatra), **non**
  `ClinicalAccessChecker` (pieno OR igiene). L'igienista **non ha nessun
  accesso** all'odontogramma — confermato esplicitamente: "secondo la
  sua competenza" qui significa nessuno, la diagnosi/stato degli
  elementi dentali non rientra nella competenza igienica. Se dovesse
  cambiare in futuro, il punto d'ingresso è
  `App\Modules\Dental\Policies\DentalToothConditionPolicy`.
- **Audit**: scritture via trait `Auditable` come sul diario (azione
  generica `created`, sufficiente — ogni record è un evento singolo e
  immutabile). Lettura: stessa logica della cartella clinica, un'unica
  voce esplicita per apertura pagina — `odontogram_viewed`, nome
  distinto da `clinical_record_viewed` perché sono due pagine separate.
- **Cifratura**: cast `encrypted` su `notes`, stesso meccanismo del resto
  della cartella clinica.
- **UI** (`resources/js/Pages/Dental/Odontogram.jsx`): arcata renderizzata
  nell'ordine di lettura clinico standard (non l'ordine numerico grezzo
  11-18/21-28/...), con toggle permanenti/decidui per la dentizione
  mista. Colore per stato a colpo d'occhio (mappa fissa in
  `CONDITION_COLORS`); click su un dente apre un pannello con stato
  attuale, storico completo e — solo per chi ha `odontogram.update` — il
  form per registrare un nuovo stato. Pagina separata dalla cartella
  clinica generale (propria rotta `dental.odontogram.show`), linkata da
  essa quando l'utente ha `odontogram.view`.

### Collegamento documento ↔ dente (`DentalDocumentTooth`)

Un documento clinico (`DentalDocument`: referto, radiografia, foto, ora
anche panoramica/OPT) può riferirsi a uno o più denti specifici, per
vedere dal pannello di un dente anche le immagini/radiografie che lo
riguardano — senza toccare cifratura/storage dei documenti già in essere.

- **Non è un many-to-many fra due entità**: come per
  `DentalToothCondition`, il "dente" non è mai una riga di una tabella
  propria — è solo un codice FDI. `DentalDocumentTooth` è quindi un
  semplice *one-to-many* da `DentalDocument` (`document_id`,
  `tooth_number` validato contro `FdiToothNumbers::all()`), non una
  pivot fra `DentalDocument` e un modello "Tooth" inesistente.
- **Facoltativo e multiplo, mai obbligatorio**: un'endorale può riguardare
  1-2 denti, un OPT d'insieme nessuno in particolare — zero righe in
  `dental_document_teeth` è lo stato normale per un documento senza
  denti specifici, non un caso speciale da rappresentare a parte.
- **Niente auto-link per l'OPT — confermato esplicitamente**: anche una
  panoramica non genera automaticamente un collegamento a tutti i denti.
  `document_type = 'panoramica'` resta solo un'etichetta come le altre
  (`document_type` è testo libero, mai stato un enum — aggiungerla non
  ha richiesto nessuna migrazione, solo una nuova opzione nel form); il
  clinico sceglie manualmente quali denti collegare, se lo ritiene utile.
- **Append-only e immutabile solo al momento dell'upload — confermato
  esplicitamente**: le righe si creano un'unica volta, nella stessa
  richiesta di `DentalDocumentController::store()`, e non sono mai
  modificabili dopo (nessuna colonna `updated_at`, nessuna rotta
  dedicata). Un documento già caricato senza denti collegati **non può**
  riceverne in un secondo momento — stessa immutabilità già in vigore su
  `DentalDocument` stesso.
- **Nessun permesso e nessuna Policy nuovi**: l'autorizzazione resta
  quella già esistente di `DentalDocumentPolicy` — `createFor()` per
  scegliere i denti in fase di upload (quindi anche l'igienista può
  collegare denti su un documento di sezione `hygiene` che è già
  autorizzato a caricare), `view()` per il download raggiunto dal
  pannello dente. Il collegamento non introduce nessun varco nuovo: il
  download resta sempre dietro la stessa Policy, il pannello dente si
  limita a mostrare un link alla stessa rotta di sempre.
- **Difesa in profondità nell'odontogramma**: `DentalOdontogramController`
  applica lo stesso filtro di sezione (`hasFullAccess`/hygiene) già usato
  da `DentalClinicalRecordController` quando costruisce
  `documentsByTooth`, anche se oggi chi apre l'odontogramma (solo
  admin/odontoiatra) ha già accesso pieno a entrambe le sezioni per
  costruzione — se il catalogo RBAC cambiasse, il filtro è già lì.
- **Nessuna voce di audit dedicata**: la riga si crea nello stesso
  istante/stesso attore dell'upload del documento, già tracciato dal
  trait `Auditable` su `DentalDocument` — una seconda voce sarebbe
  rumore duplicato, non nuova informazione. Anche la sola lettura tramite
  il pannello dente non aggiunge un audit nuovo, stessa logica per cui i
  documenti elencati nella cartella clinica generale non generano un
  evento per ciascuno.

### Visualizzazione inline dei documenti (`dental.documents.preview`)

Accanto al link "Scarica" (`dental.documents.download`, sempre presente),
un secondo link "Visualizza" apre PDF/immagini direttamente nel browser
(es. una radiografia durante la visita) invece di forzarne il download.

- **Stessa rotta protetta, nessuna Policy nuova**: `DentalDocumentController::preview()`
  applica **esattamente** la stessa autorizzazione di `download()`
  (`$this->authorize('view', $document)` + verifica `patient_id`) — è lo
  stesso file, sullo stesso disco privato, dietro la stessa Policy;
  cambia solo l'header di risposta.
- **Unica differenza: `Content-Disposition`**. `DentalDocument::streamInline()`
  usa `Storage::response()` (imposta `inline`) invece di
  `Storage::download()` (imposta `attachment`, usato da `streamDownload()`)
  — nessun meccanismo di protezione diverso tra i due.
- **Whitelist esplicita dei mime visualizzabili** (`DentalDocument::PREVIEWABLE_MIME_TYPES`
  — `application/pdf`, `image/jpeg`, `image/png`): coincide oggi con i
  mime già accettati in upload da `StoreDentalDocumentRequest`, quindi
  ogni documento caricabile è già previewable. La whitelist resta
  comunque una blindatura lato server, non solo lato UI: se un mime non
  è visualizzabile, `preview()` **ricade sul download** con un redirect,
  anche se la rotta viene chiamata direttamente scavalcando la UI (che
  già nasconde il link "Visualizza" per quei casi).
- **Nessun audit dedicato — coerente con lo stato attuale**: il download
  di un documento non genera oggi nessuna voce di audit; la preview resta
  coerente con questo, non ne introduce una. Se in futuro si vorrà
  tracciare l'accesso in lettura ai documenti, è un intervento a parte
  che tocca entrambe le rotte insieme, non solo quella nuova.
- **Effetto collaterale incluso**: `DentalDocument` ora nasconde
  `file_path` (`protected $hidden = ['file_path']`) dai props Inertia —
  non è mai stata la vera protezione (lo è la Policy sulla rotta), ma
  non c'era motivo di esporre il percorso interno di storage lato client.

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

`Database\Seeders\DemoTeamSeeder` (richiamato da `DatabaseSeeder`, anche
rilanciabile da solo — `php artisan db:seed --class="Database\Seeders\DemoTeamSeeder"`
— idempotente, verifica per email prima di creare) aggiunge, per ciascuno
dei due tenant demo, altri 4 `odontoiatra` + 4 `igienista` + 4 `aso`
(`nome.cognome@{dominio}`, stessa password) — servono per esercitare
davvero le viste multi-operatore dell'Agenda, non solo un operatore per
ruolo. I 4 ASO sono elencati nello stesso ordine dei 4 odontoiatri (un
assistente "corrispondente" per ciascuno) — l'abbinamento è anche un
legame reale a livello di dati, vedi `DemoAppointmentSeeder` sotto. Nomi
in `DemoTeamSeeder::teamMembers()`.

`Database\Seeders\DemoAppointmentSeeder` (richiamato subito dopo, stesso
pattern di idempotenza — salta un operatore che ha già almeno un
appuntamento) dà a ciascun odontoiatra demo 5 appuntamenti (oggi + i due
giorni successivi, orari fissi in `DemoAppointmentSeeder::SLOTS`) così le
viste dell'Agenda non sono vuote appena si accede in demo. Solo
odontoiatri ("i dottori") — igienisti restano senza appuntamenti propri
in questa passata. Ogni appuntamento viene anche legato (`assistant_id`)
all'ASO "corrispondente" secondo lo stesso abbinamento di
`DemoTeamSeeder` (`DemoAppointmentSeeder::asoPairings()`), così l'agenda
dell'assistente mostra davvero gli appuntamenti del proprio odontoiatra.
