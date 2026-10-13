<?php

namespace App\Http\Requests\Concerns;

trait ValidatesSupportReplies
{
    /** @return list<string> */
    protected function supportReplyRules(): array
    {
        return ['required', 'string', 'max:10000'];
    }
}
