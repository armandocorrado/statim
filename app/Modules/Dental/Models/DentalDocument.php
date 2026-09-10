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
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Coincide con i mime accettati in upload da StoreDentalDocumentRequest
     * (mimes:pdf,jpg,jpeg,png) — oggi ogni documento caricabile è quindi
     * sempre visualizzabile inline. La whitelist esplicita resta comunque
     * come blindatura lato server: se in futuro si accettassero altri tipi
     * in upload (es. .docx), ricadono sul download senza bisogno di
     * ricordarsi di aggiornare anche qui.
     */
    private const PREVIEWABLE_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    /**
     * Percorso interno di storage — mai la vera protezione (lo è la Policy
     * sulla rotta), ma non ha motivo di essere esposto ai props Inertia.
     */
    protected $hidden = ['file_path'];

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
     * Denti a cui il documento è collegato — facoltativo, mai obbligatorio
     * (un OPT d'insieme può non riferirsi a nessun dente specifico).
     * Valorizzato solo al momento dell'upload, mai dopo — vedi
     * DentalDocumentTooth.
     */
    public function teeth(): HasMany
    {
        return $this->hasMany(DentalDocumentTooth::class, 'document_id');
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

    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, self::PREVIEWABLE_MIME_TYPES, true);
    }

    /**
     * Stesso file, stesso disco privato, stessa protezione — l'unica
     * differenza da streamDownload() è che Storage::response() imposta
     * Content-Disposition: inline invece di attachment.
     */
    public function streamInline(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return Storage::disk('local')->response($this->file_path, $this->original_filename);
    }
}
