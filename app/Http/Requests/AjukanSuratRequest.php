<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AjukanSuratRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sesi_id' => ['required', 'string', 'uuid'],
            'jenis_surat' => ['required', 'string', 'in:domisili,sktm,pengantar,lainnya'],
            'data_form' => ['required', 'array'],
        ];
    }
}
