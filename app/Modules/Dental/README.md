# Modulo Dental (verticale odontoiatrico)

Verticale clinico odontoiatrico. Contiene:

- `Providers/DentalServiceProvider.php` — innesta i ruoli clinici
  (`odontoiatra`, `igienista`) e i permessi su cartella/odontogramma nel
  catalogo RBAC del core tramite `TenantRoleProvisioner::extend()`, senza
  che il core importi nulla da qui — vedi
  `App\Core\Users\Support\TenantRoleProvisioner`. Registra anche le Policy
  di questo modulo (mai nel core `AppServiceProvider`).
- **Cartella clinica** (`Models/DentalAnamnesis`, `DentalAlert`,
  `DentalDiaryEntry`, `DentalDocument`) — anamnesi, alert di sicurezza
  (allergie/rischi), diario clinico append-only, documenti su storage
  privato. Dettagli completi in `CLAUDE.md`, sezione "Cartella clinica".
- **Odontogramma interattivo** (`Models/DentalToothCondition`,
  `Support/FdiToothNumbers`, `Support/ToothConditionResolver`) —
  numerazione FDI/ISO (permanenti + decidui), storico append-only degli
  stati per dente, RBAC riservato ad admin/odontoiatra. Dettagli completi
  in `CLAUDE.md`, sezione "Odontogramma interattivo".
- **Piano di cura** (`Models/DentalTreatmentPlan`,
  `DentalTreatmentPlanItem`, `DentalTreatmentPlanItemTooth`,
  `Support/TreatmentPlanAccessChecker`,
  `Support/DentalServiceCatalogProvisioner`) — la parte clinica del
  flusso preventivi: genera un `Quote` (`App\Core\Quotes`, CORE) senza
  che Core importi mai nulla da qui — vedi `CLAUDE.md`, sezione
  "Preventivi e piani di cura", per il meccanismo di riferimento opaco
  che tiene i due mondi separati.

**Ancora da fare**: integrazione scanner/sistemi radiologici, FSE.

## Regola di confine

`App\Core\*` non deve mai importare nulla da `App\Modules\Dental\*`. Il
contrario è permesso: il modulo Dental può dipendere da `App\Core`
(model `Tenant`, `Patient`, trait `BelongsToTenant`/`Auditable`, ecc.) allo
stesso modo di qualsiasi altro consumatore del core.

Struttura:

```
app/Modules/Dental/
├── Enums/
├── Models/
├── Policies/
├── Support/
├── Http/Controllers/
└── Http/Requests/
```

con le pagine Inertia corrispondenti in `resources/js/Pages/Dental/`.
