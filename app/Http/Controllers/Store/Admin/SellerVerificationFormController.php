<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\UpdatesStoreSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\UpdateSellerVerificationFormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class SellerVerificationFormController extends Controller implements HasMiddleware
{
    use UpdatesStoreSettings;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:seller_verification_form_configuration')];
    }

    public function index(): Response
    {
        /** @var list<array{type: string, label: string, options?: string}> $stored */
        $stored = json_decode((string) get_setting('verification_form', '[]'), true) ?: [];
        $fields = [];
        foreach ($stored as $field) {
            $item = ['type' => $field['type'], 'label' => $field['label']];
            if (in_array($field['type'], UpdateSellerVerificationFormRequest::SELECT_TYPES, true)) {
                $item['options'] = json_decode($field['options'] ?? '[]', true) ?: [];
            }
            $fields[] = $item;
        }

        return Inertia::render('Store/Admin/Sellers/VerificationForm/Index', ['fields' => $fields]);
    }

    public function update(UpdateSellerVerificationFormRequest $request): RedirectResponse
    {
        $this->updateSettings(['verification_form' => $request->storedForm()]);

        return redirect()->route('seller_verification_form.index');
    }
}
