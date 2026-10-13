<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreLanguage;
use App\Http\Requests\Concerns\ValidatesStoreUploadReference;
use App\Http\Requests\Concerns\ValidatesWebsiteSettings;
use App\Models\Upload;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The footer form's typed writer: the keys the retired Blade footer posted to
 * the generic settings writer, and nothing else
 * (.ai/rules/store-backoffice-migration.md §20).
 */
class UpdateWebsiteFooterRequest extends FormRequest
{
    use ValidatesStoreLanguage;
    use ValidatesStoreUploadReference;
    use ValidatesWebsiteSettings;

    /** Stored per language, as the Blade form's `types[][<lang>]` entries were. */
    public const LOCALIZED = ['about_us_description', 'contact_address', 'widget_one', 'widget_one_labels', 'frontend_copyright_text'];

    /** Edited with the shared editor and cleaned with HtmlSanitizer before validation. */
    public const RICH_TEXT = ['about_us_description', 'frontend_copyright_text'];

    /** Stored as `on` or null, as the Blade checkboxes were. */
    public const SWITCHES = ['show_social_links', 'registration_tutorial_button'];

    /** Stored as JSON arrays: the labels per language, the links shared. */
    public const ARRAYS = ['widget_one_labels', 'widget_one_links'];

    /** Validated as http(s), root-relative or fragment links. */
    public const LINKS = ['play_store_link', 'app_store_link', 'facebook_link', 'twitter_link', 'instagram_link', 'youtube_link', 'linkedin_link', 'registration_tutorial_link', 'seller_app_link', 'delivery_boy_app_link'];

    /**
     * Every key the form can write. `seller_app_link` is shown only while the
     * vendor system is on and `delivery_boy_app_link` only while the delivery
     * boy addon is (enabledKeys()).
     */
    public const KEYS = ['footer_logo', 'about_us_description', 'play_store_link', 'app_store_link', 'contact_address', 'contact_phone', 'contact_email', 'widget_one', 'widget_one_labels', 'widget_one_links', 'frontend_copyright_text', 'show_social_links', 'facebook_link', 'twitter_link', 'instagram_link', 'youtube_link', 'linkedin_link', 'seller_app_link', 'delivery_boy_app_link', 'payment_method_images', 'registration_tutorial_button', 'registration_tutorial_text', 'registration_tutorial_link'];

    /** @return list<string> */
    public static function enabledKeys(): array
    {
        return self::enabledSettingKeys(self::KEYS, 'seller_app_link', 'delivery_boy_app_link');
    }

    protected function prepareForValidation(): void
    {
        $this->cleanRichTextSettings(self::RICH_TEXT);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $keys = self::enabledKeys();
        $rules = [
            'settings' => ['required', 'array:'.implode(',', $keys)],
            ...$this->languageRules('required'),
            'settings.footer_logo' => ['present', 'nullable', 'integer', $this->retainableImageUploadRule('settings.footer_logo', $this->storedSettingValue('footer_logo'))],
            'settings.about_us_description' => ['present', 'nullable', 'string'],
            'settings.contact_address' => ['present', 'nullable', 'string', 'max:255'],
            'settings.contact_phone' => ['present', 'nullable', 'string', 'max:255'],
            'settings.contact_email' => ['present', 'nullable', 'string', 'email', 'max:255'],
            'settings.widget_one' => ['present', 'nullable', 'string', 'max:255'],
            'settings.widget_one_labels' => ['present', 'array', 'list', 'max:50'],
            'settings.widget_one_labels.*' => ['required', 'string', 'max:255'],
            'settings.widget_one_links' => ['present', 'array', 'list', 'max:50'],
            'settings.widget_one_links.*' => ['required', ...$this->linkSettingRules()],
            'settings.frontend_copyright_text' => ['present', 'nullable', 'string'],
            'settings.show_social_links' => ['required', 'boolean'],
            'settings.payment_method_images' => ['present', 'nullable', 'string', 'max:1000', $this->paymentImagesRule()],
            'settings.registration_tutorial_button' => ['required', 'boolean'],
            'settings.registration_tutorial_text' => ['present', 'nullable', 'string', 'max:255'],
        ];
        foreach (array_intersect(self::LINKS, $keys) as $key) {
            $rules['settings.'.$key] = ['present', ...$this->linkSettingRules()];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->refuseUnexpectedSettingInput($validator, ['settings', 'lang']);
            $this->validatePairedSettings($validator, 'widget_one_labels', 'widget_one_links');
        });
    }

    /**
     * The payment method logos: comma-separated image upload ids, as the
     * legacy uploader stored them. An id already stored round-trips whoever
     * owns it, without granting a preview; any other must pass the library
     * rule of imageUploadRule().
     */
    private function paymentImagesRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || preg_match('/^\d+(?:,\d+)*$/', $value) !== 1) {
                $fail(__('validation.regex', ['attribute' => $attribute]));

                return;
            }
            $ids = explode(',', $value);
            $stored = explode(',', $this->storedSettingValue('payment_method_images') ?? '');
            $user = $this->user('store');
            $images = Upload::query()->where('type', 'image');
            $found = (clone $images)->whereIn('id', array_intersect($ids, $stored))->count()
                + (clone $images)->whereIn('id', array_diff($ids, $stored))
                    ->when(! $user->can('view_uploads') || ! $user->can('viewAny', Upload::class), fn ($query) => $query->where('user_id', $user->id))
                    ->count();
            if (count($ids) > 50 || count(array_unique($ids)) !== count($ids) || $found !== count($ids)) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        };
    }
}
