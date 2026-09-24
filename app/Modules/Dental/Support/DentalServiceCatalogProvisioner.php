<?php

namespace App\Modules\Dental\Support;

use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Modules\Dental\Enums\DentalRecordSection;

/**
 * Seeda il listino prestazioni di default per un nuovo tenant — Core
 * (ServiceCatalogItem) non sa cosa sia un'"Otturazione", quindi è Dental
 * (che può scrivere liberamente su una tabella Core) a farlo, stesso
 * principio di TenantRoleProvisioner::extend() applicato ai dati invece
 * che ai permessi. `category` usa i valori di DentalRecordSection perché
 * TreatmentPlanAccessChecker li confronta come stringhe — Core non
 * importa mai questo enum, lo riceve solo come valore.
 */
class DentalServiceCatalogProvisioner
{
    /**
     * @return list<array{name: string, base_price: float, category: string, vat_exempt: bool, duration: int}>
     */
    public static function defaultItems(): array
    {
        return [
            ['name' => 'Visita di controllo', 'base_price' => 50.00, 'category' => DentalRecordSection::General->value, 'vat_exempt' => true, 'duration' => 20],
            ['name' => 'Otturazione', 'base_price' => 90.00, 'category' => DentalRecordSection::General->value, 'vat_exempt' => true, 'duration' => 30],
            ['name' => 'Devitalizzazione', 'base_price' => 250.00, 'category' => DentalRecordSection::General->value, 'vat_exempt' => true, 'duration' => 60],
            ['name' => 'Estrazione', 'base_price' => 100.00, 'category' => DentalRecordSection::General->value, 'vat_exempt' => true, 'duration' => 30],
            ['name' => 'Igiene orale', 'base_price' => 70.00, 'category' => DentalRecordSection::Hygiene->value, 'vat_exempt' => true, 'duration' => 45],
            ['name' => 'Sigillatura', 'base_price' => 40.00, 'category' => DentalRecordSection::Hygiene->value, 'vat_exempt' => true, 'duration' => 20],
        ];
    }

    /**
     * Chiamare con la connessione 'tenant' gia' risolta sullo studio giusto
     * (TenantConnectionResolver::forTenant()).
     */
    public static function provisionDefaults(): void
    {
        foreach (self::defaultItems() as $item) {
            ServiceCatalogItem::firstOrCreate(
                ['name' => $item['name']],
                [
                    'category' => $item['category'],
                    'base_price' => $item['base_price'],
                    'default_vat_rate' => null,
                    'default_vat_exemption_reason' => $item['vat_exempt'] ? 'art. 10 n. 18 DPR 633/72' : null,
                    'default_duration_minutes' => $item['duration'],
                    'is_active' => true,
                ],
            );
        }
    }
}
