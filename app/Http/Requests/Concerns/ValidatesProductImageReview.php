<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ValidatesProductImageReview
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function imageReviewRules(): array
    {
        return [
            'image_review_status' => ['required', 'in:matches,doesnt_match,image_missing'],
            'image_review_note' => ['nullable', 'string', 'max:1000'],
            'image_source' => ['present', 'nullable', 'string', 'max:8192'],
        ];
    }

    /** @return array{image_review_status: string, image_source: string, image_review_note: string} */
    public function imageReviewData(): array
    {
        return [
            'image_review_status' => $this->string('image_review_status')->value(),
            'image_source' => $this->string('image_source')->value(),
            'image_review_note' => $this->string('image_review_note')->value(),
        ];
    }
}
