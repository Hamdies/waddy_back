<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property int parent_id
 * @property array name
 * @property array lang
 * @property int position
 */
class CategoryAddRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|max:100',
            'name.0' => 'required',
            // A store category is a text tab on a specialty store's page, so it
            // needs no artwork; shared main categories still do.
            'image' => \Illuminate\Validation\Rule::requiredIf(
                fn () => (int) $this->input('position') === 0 && !$this->filled('store_id')
            ),
            'store_id' => 'nullable|integer|exists:stores,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => translate('messages.Name is required!'),
            'image.required_if' => translate('messages.Image is required!'),
            'name.0.required' => translate('default_name_is_required'),
        ];
    }
}
