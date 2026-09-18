# MedCare — contesto per Claude Code

Gestionale cloud multi-tenant per professioni sanitarie. Verticale in
sviluppo: odontoiatria. **Fase 1 (MVP) è quasi completa** — vedi "Stato
del progetto" subito sotto per il checklist punto per punto, cosa manca,
i debiti tecnici noti e le questioni aperte. Il resto del file documenta
il *perché* delle scelte fatte modulo per modulo — questa sezione è
l'unica pensata per essere riletta all'inizio di ogni nuova sessione.

## Stato del progetto (aggiornato 2026-09-18)

**218/218 test Pest passano** (SQLite in-memory). Ogni modulo è stato
verificato anche manualmente via HTTP reale contro MySQL locale durante
lo sviluppo (login → azione → tinker per ispezionare il DB) — pattern
consolidato, da ripetere per ogni nuovo modulo. **Un primo deploy reale
su Plesk è avvenuto** in questa fase (non più solo locale): trovato e
risolto un crash in produzione causato da `laravel/pao` (dipendenza dev
non necessaria, rimossa — vedi commit dedicato); demo seedata anche lì.
Resta comunque da verificare in modo sistematico l'intera checklist di
deploy sotto "Questioni aperte".

### Checklist Fase 1 · MVP odontoiatria (da `MedCare-Roadmap.docx`)

| # | Componente | Stato |
|---|---|---|
| 1 | [C] Fondamenta tecniche + astrazione specialità | ✅ Fatto |
| 2 | [C] Anagrafica paziente | ✅ Fatto |
| 3 | [C] Consensi e privacy | ✅ Fatto |
| 4 | [C] Agenda come hub centrale | 🟡 Parziale |
| 5 | [C] Fatturazione (Sistema TS, SdI) | 🟡 Flusso interno completo, integrazioni esterne mock |
| 6 | [V] Cartella clinica + odontogramma | ✅ Fatto |
| 7 | [V] Preventivi e piani di cura | ✅ Fatto |
| 8 | [C] WhatsApp anti no-show + layout per ruolo | ❌ Non iniziato |

**Punto 4 — cosa manca esattamente**: lo scheduling multi-operatore/
multi-poltrona con anti-sovrapposizione è completo (vedi sezione
"Agenda"), ma l'idea di "hub centrale" della roadmap — *un clic
sull'appuntamento apre cartella, piano di cura, contabilità e storico
senza cambiare schermata* — non è costruita. Oggi cartella clinica,
piano di cura, preventivi e fatturazione sono pagine separate raggiunte
da navigazione normale, non un pannello unificato agganciato
all'appuntamento. Prossimo pezzo naturale per chiudere la Fase 1.

**Punto 5 — cosa manca esattamente**: il flusso interno è ora completo,
incluso il collegamento preventivo accettato→documento fiscale (sezione
"Fatturazione", "Collegamento Preventivo → Documento fiscale"). Quello
che resta sono **solo** le tre integrazioni esterne reali dietro i
gateway mock (`ElectronicInvoiceGateway`/`HealthExpenseReportingGateway`/
`DigitalPreservationGateway`) — un provider/intermediario SdI accreditato
(+ certificati), l'accreditamento al Sistema TS (+ ambiente di test), un
conservatore accreditato, e la validazione delle causali IVA/regola
SdI-vs-TS con un commercialista. **Nessuno di questi è ancora
disponibile**, è un blocco esterno al codice, non tecnico — dettaglio
completo di cosa serve per ciascuna nella sezione "Fatturazione".

**Punto 8 — non iniziato**: nessuna integrazione WhatsApp, nessun
layout adattivo per ruolo, nessuna "scheda paziente unificata a
pannelli". Nessuna progettazione fatta finora — da affrontare da zero
quando si riprende, probabilmente in coppia col punto 4 (entrambi
toccano la UX della scheda paziente/agenda).

### Debiti tecnici noti (rimandati deliberatamente, con motivazione già registrata)

Ogni voce ha il dettaglio completo nella sezione di modulo linkata — qui
solo l'indice per orientarsi velocemente:

- **Fatturazione**: nota di credito, tracciamento incassi/pagamenti,
  intestatario azienda (non-`Patient`), le tre integrazioni esterne reali
  (SdI/Sistema TS/conservazione) — sezione "Fatturazione". Il collegamento
  preventivo→fattura NON è più tra i debiti: fatto, vedi quella sezione.
- **Preventivi**: invio preventivo via email/portale, pagamenti
  dilazionati, sconto a importo fisso come alternativa alla percentuale —
  sezione "Preventivi e piani di cura".
- **Agenda**: squadra clinica (filtro visibilità per team, oggi
  `aso`/`igienista` vedono l'intero studio come stato interinale), KPI e
  spettanze per operatore, prenotazione online, promemoria automatici —
  sezione "Agenda" e "Vincoli architetturali futuri" sotto RBAC.
  Anche il "prima visita" derivato dall'Agenda (deliberatamente non un
  campo su `Patient`) dipende dal completamento del punto 4.
- **Cartella clinica/odontogramma**: integrazione scanner/sistemi
  radiologici, FSE, cifratura byte-a-byte dei file, collegamento
  retroattivo di denti a un documento già caricato (si scelgono solo
  all'upload) — sezioni "Cartella clinica" e "Odontogramma interattivo".
- **Anagrafica**: UI di selezione tutore/referente (backend pronto, serve
  un componente di ricerca pazienti riusabile — esiste già `PatientPicker`
  per Billing, andrebbe solo riusato), intestatario azienda.
- **RBAC**: super-admin di piattaforma (rinviato — quando servirà, guard/
  model separato da `App\Models\User`, mai un ruolo tenant-scoped).

### Questioni aperte che servono una decisione (non solo codice)

- **Aliquote/esenzioni IVA e regola SdI-vs-Sistema TS**
  (`FiscalChannelResolver`) sono provvisorie — **da validare con un
  commercialista** prima di qualunque uso reale, non solo prima del
  deploy. In particolare non è chiaro se un'eventuale opposizione del
  paziente all'invio al Sistema TS cambi comunque il divieto SdI.
- **Deploy Plesk**: mai tentato in questa sessione. Da verificare prima
  del primo rilascio: versione PHP effettiva su Plesk (8.3, non 8.4 come
  in locale), configurazione `.env` di produzione, migrazione del DB
  MySQL locale → Plesk, build asset (`npm run build` committato o
  buildato in pipeline?).
- **Da dove ripartire**: punto 4 (hub Agenda) o punto 8 (WhatsApp +
  layout per ruolo) sono gli unici due pezzi mancanti di Fase 1 — nessuna
  decisione ancora presa su quale affrontare per primo.

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
  tutto) può mai ricevere un permesso `clinical_records.*`, `odontogram.*`,
  `treatment_plans.clinical.manage` o `treatment_plans.hygiene.manage`.
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

CORE trasversale. **Il flusso interno è completo** (fondamenta +
collegamento col ciclo economico, sezione dedicata sotto): quello che
resta fuori sono solo le tre integrazioni esterne reali (SdI, Sistema TS,
conservazione a norma), deliberatamente dietro interfacce con
implementazioni mock — vedi "Le tre porte esterne" sotto per il motivo e
cosa serve prima di sostituirle.

- **`BillingDocument`**: `patient_id` sempre valorizzato (la prestazione,
  serve al Sistema TS anche quando l'intestatario è diverso);
  `recipient_patient_id` + campi `recipient_*` (nome, codice fiscale,
  partita IVA, indirizzo) sono una **copia congelata** dei dati fiscali
  del destinatario al momento della creazione — non un join live su
  `Patient`: se il paziente corregge poi un indirizzo, i documenti già
  creati non devono cambiare silenziosamente. Stesso principio già
  applicato a `Consent.given_by_patient_id`. Lo snapshot è isolato in
  `App\Core\Billing\Support\BillingDocumentRecipientSnapshot`, condiviso
  fra la creazione manuale (`BillingDocumentController`) e la generazione
  da preventivo (sotto) — un solo posto che sa come si congela un
  destinatario.
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
  un enum di codici norma a livello di schema — non sono certo che
  l'elenco applicabile sia solo "art. 10 n. 18 DPR 633/72", il più comune
  per le prestazioni sanitarie ma non necessariamente l'unico). La colonna
  resta libera, ma `App\Core\Billing\Support\VatExemptionReasons` offre
  ora le causali sanitarie più comuni (art. 10 n. 18/19/27-ter DPR 633/72)
  come **aiuto alla UI**: un menu a tendina in `VatExemptionReasonField`
  (riusato da Preventivi e Fatturazione, un solo punto da aggiornare) con
  "Altro" che apre il campo libero — nessuna validazione server-side
  irrigidita, l'"Altro" resta la valvola di sicurezza. **Da validare con
  un commercialista** prima dell'uso reale — sia l'elenco stesso sia se
  ne mancano altre causali applicabili a MedCare.
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
  bindate in `AppServiceProvider::register()`. Restano **mock per scelta
  deliberata**: dipendono da accreditamenti/provider esterni non
  disponibili ora, non da un limite tecnico del codice. Cambiare solo
  quei binding quando arriverà la fase reale — nessun'altra riga del
  modulo dipende dai dettagli esterni (`issue()` chiama sempre
  l'interfaccia, mai l'implementazione concreta). **Cosa serve prima di
  sostituire ciascuna**:
  - `ElectronicInvoiceGateway` (SdI): un provider/intermediario
    accreditato per la fattura elettronica (o l'accesso diretto ai
    servizi SdI dell'Agenzia delle Entrate), credenziali e certificato di
    firma/trasmissione, generazione XML FatturaPA reale (oggi non
    generato in nessuna forma — vedi vincolo esplicito sotto).
  - `HealthExpenseReportingGateway` (Sistema TS): accreditamento della
    struttura al Sistema Tessera Sanitaria (richiede tempo, non solo
    credenziali), ambiente di test messo a disposizione dal Sistema TS
    prima del reale.
  - `DigitalPreservationGateway`: un conservatore accreditato AgID (o
    equivalente), formato/canale di invio dei documenti da conservare.
  - **Trasversale a tutte e tre**: validazione delle causali di esenzione
    IVA e della regola SdI-vs-Sistema TS (`FiscalChannelResolver`) con un
    commercialista — non è un dettaglio tecnico, condiziona cosa le tre
    integrazioni reali devono effettivamente fare.
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
- **Vincolo esplicito, non solo "mock" per comodo**: nessuna riga di
  questo modulo genera un XML SdI (FatturaPA) reale, nemmeno a scopo di
  test — i tre gateway restituiscono solo un riferimento finto
  (`MOCK-SDI-...`), niente di più. Generare un XML sintatticamente corretto
  è lavoro della fase reale, condizionato dalle scelte del provider/
  intermediario che verrà scelto (ogni provider ha convenzioni proprie
  su come costruire la richiesta).
- **Rimandato deliberatamente**: nota di credito (per correggere un
  documento emesso), tracciamento incassi/pagamenti (`payments.manage`
  riservato per quello), intestatario azienda (non-`Patient`), le
  integrazioni reali stesse (vedi sopra).

### Collegamento Preventivo → Documento fiscale

`Quote::billingDocuments()` / `BillingDocument::sourceQuote()` — a
differenza di `Quote.source_treatment_plan_id` (riferimento *opaco* verso
il verticale Dental, senza relazione Eloquent) questo è un legame
**Core↔Core vero e proprio**: `billing_documents.source_quote_id` è una
FK reale verso `quotes.id` (nullable, `restrictOnDelete`). Billing può
dipendere da Quotes liberamente — sono entrambi nel core trasversale,
nessun confine da rispettare come per Dental.

- **1:N, deliberato**: un preventivo può generare **più** documenti
  fiscali nel tempo (es. fatturazione a fasi/acconti) — nessun vincolo di
  unicità su `source_quote_id`, stessa logica già in vigore per
  piano di cura→preventivi.
- **Da quali stati**: solo preventivi `Accepted`/`InProgress`/`Completed`
  — lo stesso bucket "accettato" già usato ovunque nel codice (tasso di
  accettazione), centralizzato in `QuoteStatus::acceptedStatuses()`
  (`Quote::isEligibleForBillingDocument()` lo consulta). Un preventivo in
  bozza o rifiutato non è mai stato/non è più accettato dal paziente.
  Draft/Rejected restano fuori.
- **Chi genera**: `QuoteController::generateBillingDocument()`, gate su
  `QuotePolicy::generateBillingDocument()` — permesso **`billing.manage`**
  (non `treatment_plans.administer`: è un'azione di fatturazione, non di
  gestione preventivi). Stesso pattern di
  `DentalTreatmentPlanController::generateQuote()` un livello più su
  (piano di cura → preventivo): l'azione vive sul controller del modello
  *sorgente*, gated da una policy sul modello sorgente.
- **Il documento nasce sempre in `Draft`** — mai auto-emesso: resta
  rivedibile (intestatario, righe) prima di "Emetti", stesso principio
  dei documenti creati a mano. Il preventivo **non cambia stato**
  automaticamente quando si genera un documento: sono due macchine a
  stati indipendenti, collegate dal dato (`source_quote_id`), non da un
  trigger a cascata.
- **Lo sconto si congela nel prezzo unitario finale — scelta esplicita**:
  `BillingDocumentLine` non ha un campo sconto separato (a differenza di
  `QuoteLine.discount_percent`), quindi la generazione copia
  `QuoteLine::discountedUnitPrice()` come `unit_price` della riga
  fatturata — la fattura non mostra mai una riga "sconto" a parte, il
  negoziato resta visibile solo sul preventivo.
- **UI**: bottone "Genera documento fiscale" su `Quotes/Show.jsx`
  (elenca anche i documenti già generati, se presenti); `Billing/Show.jsx`
  mostra un link indietro al preventivo di origine se `source_quote_id`
  è presente; `Patients/Show.jsx` elenca i preventivi accettati del
  paziente con link al preventivo (l'azione di generazione resta sulla
  pagina del preventivo, non duplicata sulla scheda paziente).

## Preventivi e piani di cura (`App\Core\Quotes` + `App\Modules\Dental`)

Flusso: odontogramma → **piano di cura** (clinico, verticale) → **preventivo**
(economico, core) → stato (bozza → emesso → accettato/rifiutato → in corso →
completato) → tracciamento accettazione → futuro aggancio a Fatturazione.

- **Il confine core/verticale non passa per un'astrazione condivisa, passa
  per un riferimento opaco.** `App\Core\Quotes\Models\Quote` (il
  preventivo) ha `source_treatment_plan_id` — una **stringa semplice,
  nessuna FK, nessuna relazione Eloquent lato Core** — verso il piano di
  cura che l'ha generato. Core la porta con sé ma non la interpreta mai.
  È `App\Modules\Dental\Models\DentalTreatmentPlan` (che può dipendere da
  Core liberamente) a definire la relazione nel verso permesso:
  `DentalTreatmentPlan::quotes(): HasMany`. Stesso meccanismo, un livello
  più giù, fra `QuoteLine.source_treatment_plan_item_id` e
  `DentalTreatmentPlanItem::quoteLines()`. Identico principio di
  `TenantRoleProvisioner::extend()`: Core espone un punto di aggancio
  generico, il verticale fornisce l'identità concreta.
- **`QuoteLine.description` è testo libero congelato alla generazione**
  (es. "Otturazione — dente 16, 17"), mai un join live né una colonna
  strutturata per i denti — Core non deve mai sapere cos'è un numero di
  dente. È `App\Modules\Dental\Http\Controllers\DentalTreatmentPlanController::generateQuote()`
  a comporre il testo (unendo il nome della prestazione e i denti
  collegati) prima di passarlo a Core. Stessa logica dello snapshot
  congelato di `BillingDocument.recipient_*`: se il piano cambia dopo, un
  preventivo già generato non deve mutare sotto i piedi di chi l'ha già
  mostrato al paziente. Un piano può generare **più preventivi nel
  tempo** (es. una revisione dopo trattativa) — ognuno resta uno snapshot
  indipendente, nessun vincolo di unicità fra piano e preventivo.
- **Listino prestazioni** (`App\Core\Quotes\Models\ServiceCatalogItem`):
  per-tenant, profession-agnostic come `AppointmentType` — Core non sa
  cosa significhi "Otturazione". `category` è testo libero **non
  interpretato da Core**: `App\Modules\Dental\Support\TreatmentPlanAccessChecker`
  lo confronta come stringa contro `DentalRecordSection::Hygiene->value`
  senza che `ServiceCatalogItem` importi mai quell'enum — un valore dato,
  non un accoppiamento di codice. `tenant_id` è (a differenza degli altri
  model tenant-scoped del progetto) mass-assignable: serve a
  `App\Modules\Dental\Support\DentalServiceCatalogProvisioner`, che seeda
  il listino di default per un nuovo tenant fuori dal ciclo HTTP — stesso
  motivo per cui `AppointmentType` lo fa già. Nessuna rotta di delete: una
  voce si disattiva (`is_active = false`), un piano di cura passato
  potrebbe ancora referenziarla. `default_duration_minutes` è
  predisposto per l'Agenda futura, **non collegato ora**.
- **Piano di cura NON append-only — a differenza del resto della cartella
  clinica**: diario/documenti/stati dentali sono append-only perché
  registrano eventi accaduti; un piano è un documento di lavoro in bozza,
  si aggiunge/toglie/modifica una voce mentre si valuta il da farsi. La
  modifica resta comunque tracciata dal diff generico di `Auditable` sul
  piano stesso.
- **Voci del piano — CRUD per singola voce, non sostituzione in
  blocco — confermato esplicitamente**: a differenza di
  `BillingDocumentLine`/`QuoteLine` (sempre sostituite tutte insieme a
  ogni salvataggio del documento padre), `DentalTreatmentPlanItem` ha
  rotte dedicate per voce (`DentalTreatmentPlanItemController::store()`/
  `update()`/`destroy()`) — modificare o cancellare una prestazione non
  tocca le altre voci del piano. Il controllo RBAC fine (categoria
  Hygiene vs generale) si applica **per singola richiesta**: sulla
  categoria della voce sottomessa per creazione/modifica
  (`StoreDentalTreatmentPlanItemRequest`/`UpdateDentalTreatmentPlanItemRequest`,
  che guardano `$this->input('service_catalog_item_id')` prima ancora
  della validazione formale), sulla categoria della voce esistente per la
  cancellazione (`DentalTreatmentPlanItemPolicy::delete()` — non c'è un
  valore "sottomesso" da controllare al suo posto). I denti collegati
  (`DentalTreatmentPlanItemTooth`, stesso schema di `DentalDocumentTooth`
  ma mutabile) seguono la stessa voce: sostituiti insieme ad essa
  (`delete()` + ricrea), mai toccati per le altre voci.
- **RBAC — un vuoto reale colmato, confermato esplicitamente**:
  l'igienista non aveva **nessun** permesso `treatment_plans.*`. Ora ha
  `treatment_plans.view` (vede il piano per intero) e un nuovo permesso
  `treatment_plans.hygiene.manage` (gestisce **solo** le voci la cui
  `ServiceCatalogItem.category` è `hygiene`) —
  `TreatmentPlanAccessChecker::canManageItem()` verifica ogni voce
  inviata individualmente in `UpdateDentalTreatmentPlanItemsRequest`, non
  solo che l'utente abbia *un* permesso qualunque sul piano nel suo
  insieme. `treatment_plans.clinical.manage` (odontoiatra/admin) copre
  invece qualunque categoria. **Il preventivo resta amministrativo
  end-to-end**: solo `treatment_plans.administer` (admin/segreteria) può
  generare un preventivo dal piano (`DentalTreatmentPlanPolicy::generateQuote()`)
  o farlo transitare di stato (`QuotePolicy::transition()`) — i ruoli
  clinici lo vedono (`treatment_plans.view`) ma non ne gestiscono mai il
  ciclo di vita commerciale, coerente con "segreteria gestisce
  l'amministrativo, non definisce gli interventi clinici" letto anche al
  contrario.
- **Stati e transizioni** (`App\Core\Quotes\Enums\QuoteStatus` +
  `App\Core\Quotes\Support\QuoteTransitions`): `Draft → Issued →
  Accepted|Rejected → InProgress → Completed`, `Rejected`/`Completed`
  terminali. `Draft → Issued` passa dall'azione dedicata `issue()` (che
  congela i totali, stesso principio di `BillingDocument`), le altre
  transizioni da un'unica mappa esplicita — non si può saltare da `Draft`
  a `Completed`, né tornare indietro. Una sola colonna `responded_at`
  copre sia l'accettazione sia il rifiuto (stati mutuamente esclusivi da
  `Issued`), non due colonne separate — corrisponde letteralmente a
  "stato + data di accettazione/rifiuto" della richiesta originale.
- **Tasso di accettazione** = preventivi con stato
  `Accepted`+`InProgress`+`Completed` / preventivi con **qualunque**
  stato diverso da `Draft` — una bozza non è mai stata proposta al
  paziente, non entra nel denominatore. Vista in `Quotes/Index.jsx`.
- **Sconto**: un unico meccanismo, `discount_percent` (percentuale) per
  riga — non anche un importo fisso alternativo, per evitare l'ambiguità
  di quale dei due vince se entrambi fossero valorizzati. Si applica
  prima di passare i dati a `BillingDocumentTotalsCalculator` (riusato
  tal quale da Billing, non duplicato): lo sconto sconta il prezzo
  unitario, quella classe resta quella già testata, senza doverle
  insegnare a conoscere gli sconti.
- **L'aggancio fiscale preventivo→fattura è fatto** (`BillingDocument.
  source_quote_id`, FK reale Core↔Core) — vedi sezione "Fatturazione",
  "Collegamento Preventivo → Documento fiscale". Non è più tra i debiti
  di questa sezione.
- **Rimandato deliberatamente** (registrato, non costruito): invio del
  preventivo al paziente via email/portale, pagamenti
  dilazionati/finanziamenti, collegamento retroattivo di denti a una voce
  di piano già salvata (i denti si scelgono solo alla creazione/modifica
  della voce), sconto a importo fisso come alternativa alla percentuale.

### Modifica prezzi per singolo dentista (`treatment_plans.prices.edit`)

Tre livelli distinti sulla stessa risorsa (`Quote`), non annidati l'uno
nell'altro — vedi `QuotePolicy`:

1. **Visualizzazione** (prezzi inclusi) — `treatment_plans.view`,
   per-ruolo com'era già (admin/segreteria/odontoiatra/igienista).
2. **Modifica prezzi in bozza** — `treatment_plans.administer` **oppure**
   `treatment_plans.prices.edit`, quest'ultimo concesso **direttamente al
   singolo utente** dall'admin studio, mai a un ruolo. Di default nessun
   dentista ce l'ha.
3. **Emissione/gestione** (transizioni di stato) — **solo**
   `treatment_plans.administer`, mai `treatment_plans.prices.edit`.

- **Permesso diretto-all'utente, non per-ruolo — stesso meccanismo di
  spatie/laravel-permission già usato per i ruoli**: `model_has_permissions`
  ha la stessa colonna team-scoped (`tenant_id`) di `model_has_roles` (vedi
  la migration pubblicata) — `$user->givePermissionTo(...)` funziona già
  scoped per tenant, **nessuna migration nuova** è servita per questa
  funzionalità. `QuotePolicy::PRICES_EDIT_PERMISSION` è l'unica fonte di
  verità per il nome del permesso.
- **Punto critico corretto durante l'implementazione**: `QuotePolicy::issue()`/
  `::delete()` **prima** delegavano a `update()` (`return $this->update(...)`)
  — allargare `update()` con l'OR sul nuovo permesso avrebbe fatto
  ereditare automaticamente anche emissione/cancellazione a un dentista
  abilitato solo ai prezzi. Ora sono tre metodi indipendenti, nessuna
  delega fra loro.
- **Caveat spatie trovato in test**: i metodi "diretti" (`hasDirectPermission()`,
  `hasPermissionTo()` chiamati fuori dal Gate) **lanciano**
  `PermissionDoesNotExist` se la riga `Permission` non esiste ancora —
  non ritornano `false`. Capita al primissimo utilizzo in assoluto (nessun
  ruolo registra mai questo permesso, quindi `TenantRoleProvisioner` non
  lo crea). `UserController::updateQuotePricePermission()` chiama
  `Permission::findOrCreate()` **prima** di qualunque controllo, per
  questo. Il Gate (`$user->can(...)`, usato da `QuotePolicy`) invece è
  già sicuro di suo — ritorna `false`/nega senza eccezioni anche se il
  permesso non esiste — verificato empiricamente, nessuna modifica
  necessaria lì.
- **Chi assegna**: solo admin (`UserPolicy::update()`, già `users.update`,
  già solo admin — nessuna Policy nuova). UI: colonna/toggle nella pagina
  Utenti già esistente (`/users`), non una pagina dedicata. Concedibile
  solo a odontoiatra/igienista (`UpdateUserQuotePricePermissionRequest`
  rifiuta segreteria/ASO/admin come target — ridondante o pericoloso).
- **Audit**: `quote_price_permission_granted`/`_revoked` su
  `AuditRecorder::record($user, ...)`, stesso identico pattern di
  `role_changed` già in uso per il cambio ruolo.
- **Governance**: `RoleGovernanceTest` verifica che
  `treatment_plans.prices.edit` non compaia **mai** nel catalogo
  permessi di nessun ruolo di default — se comparisse lì, ogni utente di
  quel ruolo lo erediterebbe automaticamente, vanificando il controllo
  per-utente.

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

Utenti demo dopo il seeder (password `MedCare#Wild2026` per tutti):
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
