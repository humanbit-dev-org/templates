<?php

namespace App\Http\Requests;

use App\Models\Contatto;
use Illuminate\Foundation\Http\FormRequest;

class ContattoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public form, no auth required
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'oggetto' => 'required|string|in:'.implode(',', array_keys(Contatto::OGGETTI)),
            'messaggio' => 'required|string|max:5000',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email',
            'oggetto' => 'oggetto',
            'messaggio' => 'messaggio',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L\'email è obbligatoria.',
            'email.email' => 'Inserisci un indirizzo email valido.',
            'oggetto.required' => 'L\'oggetto è obbligatorio.',
            'oggetto.in' => 'L\'oggetto deve essere uno tra: '.implode(', ', Contatto::OGGETTI).'.',
            'messaggio.required' => 'Il messaggio è obbligatorio.',
            'messaggio.max' => 'Il messaggio non può superare :max caratteri.',
        ];
    }
}
