<?php

namespace App\Modules\Dental\Models;

use App\Core\Audit\Concerns\Auditable;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Dental\Enums\DentalRecordSection;
use Database\Factories\DentalDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Solo metadati qui — il file vive sul disco `local` (privato, mai
 * raggiungibile via URL pubblico, vedi config/filesystems.php). Nessuna
 * rotta di update/delete: un documento clinico caricato non si sostituisce
 * né si cancella, stesso principio del diario.
 */
#[Fillable([
    'patient_id', 'section', 'document_type', 'description',
    'file_path', 'original_filename', 'mime_type', 'file_size',
])]
class DentalDocument extends Model
{
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return DentalDocumentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'section' => DentalRecordSection::class,
            'description' => 'encrypted',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return list<string>
     */
    public static function auditExcludedAttributes(): array
    {
        return ['file_path'];
    }

    public function streamDownload(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return Storage::disk('local')->download($this->file_path, $this->original_filename);
    }
}
