<?php

namespace App\Core\Quotes\Http\Requests;

use App\Core\Quotes\Enums\QuoteStatus;
use App\Core\Quotes\Support\QuoteTransitions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateQuoteStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transition', $this->route('quote'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(QuoteStatus::class)],
        ];
    }

    /**
     * Valida la transizione stessa (non solo che il valore sia un case
     * dell'enum) — QuoteTransitions è l'unica fonte di verità su quali
     * passaggi sono ammessi da quale stato.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $target = QuoteStatus::tryFrom((string) $this->input('status'));
            $quote = $this->route('quote');

            if ($target && ! QuoteTransitions::isAllowed($quote->status, $target)) {
                $validator->errors()->add('status', 'Transizione di stato non consentita.');
            }
        });
    }
}
