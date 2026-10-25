<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\{ValidationRule, Validator};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;


class UpdateSiteSettingRequest extends FormRequest
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
            'paypal_id' => ['required'],
            'paypal_secret' => ['required'],
            'paypal_base_url' => ['required'],
            'bog_client_id' => ['required'],
            'bog_secret' => ['required'],
            'bog_auth_url' => ['required'],
            'bog_base_url' => ['required'],
            // 'bog_callback_url' => ['required'],
            // 'bog_success_url' => ['required'],
            // 'bog_fail_url' => ['required'],
            'tbc_merchant_id' => ['required'],
            'tbc_secret_key' => ['required'],
            'tbc_base_url' => ['required'],
            // 'tbc_callback_url' => ['required'],
            // 'tbc_response_url' => ['required'],
            // 'tbc_cancel_url' => ['required'],
            'tagline_translations' => ['required'],
            'site_name_translations' => ['required'],
            'logo' => ['nullable'],
            'dark_logo' => ['nullable'],
            'favicon' => ['nullable'],
            'frontend_api_key' => ['required'],
            'site_name' => ['required'],
            'tagline' => ['required'],
        ];
    }

    protected function failedValidation(Validator $validator): void {
        dd($validator->errors()->toArray());

        throw new HttpResponseException(
            response()->json([
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
