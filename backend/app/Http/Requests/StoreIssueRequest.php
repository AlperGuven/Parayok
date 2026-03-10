<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => 'required_without:jql|nullable|url',
            'jql' => 'required_without:url|nullable|string',
            'max_results' => 'nullable|integer|min:1|max:50',
        ];
    }
}
