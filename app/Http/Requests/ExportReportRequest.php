<?php

namespace App\Http\Requests;

use App\Reports\ReportFilters;
use App\Support\Ability;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Uses exactly the same rules as the on-screen reporting centre so an export
 * can never include rows the screen would not.
 */
class ExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Ability::EXPORT_REPORTS);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['type' => $this->route('type')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ReportFilters::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ReportFilters::messages();
    }

    public function filters(): ReportFilters
    {
        return ReportFilters::fromValidated($this->validated(), $this->user());
    }
}
