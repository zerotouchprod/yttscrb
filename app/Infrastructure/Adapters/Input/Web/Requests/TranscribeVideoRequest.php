<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class TranscribeVideoRequest extends FormRequest
{
    /**
     * Public API — no authorization required.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'youtube_url' => ['required', 'string', 'min:1'],
        ];
    }

    /**
     * Match the project's error response format: { error: { code, message, details } }
     */
    protected function failedValidation(
        Validator $validator,
    ): void {
        $firstError = $validator->errors()->first();
        $failedField = $validator->errors()->keys()[0] ?? 'youtube_url';

        $response = new JsonResponse([
            'error' => [
                'code'    => 'INVALID_YOUTUBE_URL',
                'message' => $firstError,
                'details' => ['field' => $failedField, 'constraint' => 'format'],
            ],
        ], Response::HTTP_BAD_REQUEST);

        throw new HttpResponseException($response);
    }
}
