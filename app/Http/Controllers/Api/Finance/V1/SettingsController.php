<?php

namespace App\Http\Controllers\Api\Finance\V1;

use App\Http\Controllers\Api\Finance\Concerns\HandlesFinanceClient;
use App\Http\Controllers\Api\Finance\FinanceApiController;
use App\Models\Finance\FinanceSetting;
use App\Services\Finance\Api\FinanceClientPresenter;
use App\Services\Finance\FinanceBootstrapService;
use App\Services\Finance\Tax\TaxCalculationService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends FinanceApiController
{
    use HandlesFinanceClient;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly FinanceBootstrapService $financeBootstrapService,
        private readonly FinanceClientPresenter $presenter,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'finance.settings');
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);
        $setting = FinanceSetting::forWorkspaceId((int) $workspace->id);

        return $this->ok($setting ? $this->presenter->settings($setting) : null);
    }

    public function update(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'finance.settings');

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_name_ar' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'commercial_registration' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'building_number' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'default_payment_terms' => ['nullable', 'string', 'max:255'],
            'default_vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        unset(
            $validated['zatca_integration_mode'],
            $validated['zatca_certificate_serial'],
            $validated['metadata'],
        );

        $setting = FinanceSetting::withoutGlobalScopes()->firstOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'workspace_id' => $workspace->id,
                'currency' => 'SAR',
                'country_code' => 'SA',
                'invoice_prefix' => 'INV',
                'next_invoice_sequence' => 1,
                'allow_manual_invoice_numbers' => false,
                'default_vat_rate' => TaxCalculationService::FALLBACK_STANDARD_RATE,
            ]
        );

        $setting->fill($validated);
        $setting->save();

        return $this->ok($this->presenter->settings($setting->fresh()), message: 'تم حفظ إعدادات الشركة.');
    }
}
