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

**Ancora da fare**: odontogramma interattivo (componente visuale a sé),
integrazione scanner/sistemi radiologici, FSE, collegamento a piani di
cura/preventivi.

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
