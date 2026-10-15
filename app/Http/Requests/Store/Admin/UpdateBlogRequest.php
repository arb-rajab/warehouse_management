<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreBlog;
use App\Models\Blog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBlogRequest extends FormRequest
{
    use ValidatesStoreBlog;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $blog = $this->route('blog');

        return $this->blogRules($blog instanceof Blog ? $blog : null);
    }
}
