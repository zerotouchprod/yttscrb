<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class SearchRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    /**
     * Reject wildcard-only queries (e.g. "%%%%%%", "____").
     *
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $query = $this->validated('q');

                if (! is_string($query)) {
                    return;
                }

                if (
                    preg_match('/[^\x00-\x7F\pL\pN]/u', $query) === 0
                    && preg_match('/[\pL\pN]/u', $query) === 0
                ) {
                    $validator->errors()->add(
                        'q',
                        'Search query must contain at least one letter or digit.',
                    );
                }
            },
        ];
    }

    /**
     * Match the project's error response format: { error: { code, message, details } }
     */
    protected function failedValidation(
        Validator $validator,
    ): void {
        $firstError = $validator->errors()->first();
        $failedField = $validator->errors()->keys()[0] ?? 'q';

        $response = new JsonResponse([
            'error' => [
                'code'    => 'INVALID_QUERY',
                'message' => $firstError,
                'details' => ['field' => $failedField],
            ],
        ], Response::HTTP_BAD_REQUEST);

        throw new HttpResponseException($response);
    }
}
