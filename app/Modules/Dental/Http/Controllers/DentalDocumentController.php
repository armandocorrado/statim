<?php

namespace App\Modules\Dental\Http\Controllers;

use App\Core\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use App\Modules\Dental\Http\Requests\StoreDentalDocumentRequest;
use App\Modules\Dental\Models\DentalDocument;
use App\Modules\Dental\Models\DentalDocumentTooth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DentalDocumentController extends Controller
{
    /**
     * Il file va sul disco `local` (privato di default in Laravel, mai
     * raggiungibile via URL — vedi config/filesystems.php), non sul disco
     * `public`. Path con tenant_id/patient_id per organizzazione, ma la
     * vera protezione è l'autorizzazione sulla rotta di download, non
     * l'imprevedibilità del path.
     */
    public function store(StoreDentalDocumentRequest $request, Patient $patient): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('file');

        $path = $file->storeAs(
            "dental-documents/{$patient->tenant_id}/{$patient->id}",
            Str::ulid().'.'.$file->getClientOriginalExtension(),
            'local',
        );

        $document = new DentalDocument([
            'section' => $data['section'],
            'document_type' => $data['document_type'],
            'description' => $data['description'] ?? null,
        ]);
        $document->patient_id = $patient->id;
        $document->tenant_id = $patient->tenant_id;
        $document->file_path = $path;
        $document->original_filename = $file->getClientOriginalName();
        $document->mime_type = $file->getClientMimeType();
        $document->file_size = $file->getSize();
        $document->uploaded_by = $request->user()->id;
        $document->save();

        foreach (array_unique($data['teeth'] ?? []) as $toothNumber) {
            $link = new DentalDocumentTooth(['tooth_number' => $toothNumber]);
            $link->document_id = $document->id;
            $link->tenant_id = $patient->tenant_id;
            $link->save();
        }

        return back()->with('success', 'Documento caricato.');
    }

    public function download(Patient $patient, DentalDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_if($document->patient_id !== $patient->id, 404);

        return $document->streamDownload();
    }

    /**
     * Stessa autorizzazione/stesso storage del download — l'unica
     * differenza è Content-Disposition: inline invece di attachment,
     * solo per i mime che il browser sa mostrare. Per un tipo non
     * visualizzabile ricade sul download, anche se qualcuno chiama questa
     * rotta direttamente scavalcando la UI.
     */
    public function preview(Patient $patient, DentalDocument $document): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $document);

        abort_if($document->patient_id !== $patient->id, 404);

        if (! $document->isPreviewable()) {
            return redirect()->route('dental.documents.download', [$patient, $document]);
        }

        return $document->streamInline();
    }
}
