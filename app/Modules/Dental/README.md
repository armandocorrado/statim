# Modulo Dental (verticale odontoiatrico)

Cartella riservata per il verticale clinico odontoiatrico (cartella clinica,
odontogramma, piani di cura). Vuota per design nella fase di walking
skeleton: nessuna astrazione viene costruita finché non esiste un secondo
verticale o requisiti concreti per questo.

## Regola di confine

`App\Core\*` non deve mai importare nulla da `App\Modules\Dental\*`. Il
contrario è permesso: il modulo Dental potrà dipendere da `App\Core`
(model `Tenant`, `Patient`, trait `BelongsToTenant`/`Auditable`, ecc.) allo
stesso modo di qualsiasi altro consumatore del core.

Quando questo modulo verrà avviato, seguirà la stessa struttura del core:

```
app/Modules/Dental/
├── Models/
├── Http/Controllers/
├── Http/Requests/
└── Policies/
```

con le pagine Inertia corrispondenti in `resources/js/Pages/Dental/`.
