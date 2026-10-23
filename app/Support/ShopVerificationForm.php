<?php

namespace App\Support;

use App\Models\BusinessSetting;
use stdClass;

final class ShopVerificationForm
{
    /** @return list<stdClass> */
    public static function fields(): array
    {
        $fields = json_decode((string) BusinessSetting::query()->where('type', 'verification_form')->value('value'));
        if (! is_array($fields)) {
            return [];
        }
        foreach ($fields as $field) {
            if (! $field instanceof stdClass || ! is_string($field->label ?? null) || ! in_array($field->type ?? null, ['text', 'file', 'select', 'multi_select', 'radio'], true)) {
                return [];
            }
            if (in_array($field->type, ['select', 'multi_select', 'radio'], true)) {
                $options = is_string($field->options ?? null) ? json_decode($field->options) : null;
                if (! is_array($options) || $options === [] || count(array_filter($options, is_string(...))) !== count($options)) {
                    return [];
                }
            }
        }

        return array_values($fields);
    }
}
