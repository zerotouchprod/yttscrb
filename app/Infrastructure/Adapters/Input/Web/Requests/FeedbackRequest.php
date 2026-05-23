<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FeedbackRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
