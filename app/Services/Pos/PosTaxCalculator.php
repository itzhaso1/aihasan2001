<?php

namespace App\Services\Pos;

use App\Models\Workspace;
use App\Support\Money\Money;
use Illuminate\Support\Collection;

/**
 * POS-only tax math. Intentionally separate from Finance TaxCalculationService.
 * Exclusive workspace-rate VAT, plus allocation of an already-authoritative header tax.
 */
class PosTaxCalculator
{
    public function workspaceRate(?Workspace $workspace): float
    {
        if (! $workspace) {
            return 0.0;
        }

        $rate = (float) data_get($workspace->settings ?? [], 'pos.tax_rate', 0);

        return $rate > 0 ? Money::round($rate) : 0.0;
    }

    public function taxAmount(?Workspace $workspace, float|string $subtotal, float|string $discountAmount): float
    {
        $rate = $this->workspaceRate($workspace);
        if ($rate <= 0) {
            return 0.0;
        }

        $taxable = Money::cmp($subtotal, $discountAmount) > 0
            ? Money::sub($subtotal, $discountAmount)
            : Money::fromMinor(0);

        return Money::round(Money::percentOf($taxable, $rate));
    }

    public function taxableAmount(float|string $subtotal, float|string $discountAmount): float
    {
        return Money::cmp($subtotal, $discountAmount) > 0
            ? Money::round(Money::sub($subtotal, $discountAmount))
            : 0.0;
    }

    /**
     * Persist-ready line tax. Client-supplied line tax is kept when present.
     * Otherwise the authoritative header tax is allocated by taxable share.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    public function attachLineTax(Collection $items, float $headerTax, float $rate): Collection
    {
        if ($items->isEmpty()) {
            return $items;
        }

        $hasProvided = $items->contains(
            fn (array $item): bool => array_key_exists('tax_amount', $item) && $item['tax_amount'] !== null && $item['tax_amount'] !== ''
        );

        if ($hasProvided) {
            return $items->map(function (array $item) use ($rate): array {
                $taxable = Money::round($item['total_amount'] ?? 0);
                $tax = Money::round($item['tax_amount'] ?? 0);
                $item['taxable_amount'] = $taxable;
                $item['tax_amount'] = $tax;
                $item['tax_rate'] = $this->effectiveRate($taxable, $tax, $rate);

                return $item;
            })->values();
        }

        $count = $items->count();
        $taxableShares = $items->map(fn (array $item): float => Money::round($item['total_amount'] ?? 0))->values();
        $taxableTotalMinor = 0;
        foreach ($taxableShares as $share) {
            $taxableTotalMinor += Money::minor($share);
        }
        $remainingMinor = Money::minor($headerTax);

        return $items->values()->map(function (array $item, int $index) use ($count, $taxableShares, $taxableTotalMinor, $headerTax, $rate, &$remainingMinor): array {
            $taxable = $taxableShares[$index];
            if ($index === $count - 1) {
                $taxMinor = max(0, $remainingMinor);
            } elseif ($taxableTotalMinor <= 0 || Money::minor($headerTax) <= 0) {
                $taxMinor = 0;
            } else {
                $taxMinor = (int) round(
                    Money::minor($headerTax) * Money::minor($taxable) / $taxableTotalMinor,
                    0,
                    PHP_ROUND_HALF_UP
                );
                $taxMinor = min($taxMinor, $remainingMinor);
                $remainingMinor -= $taxMinor;
            }

            $tax = Money::round(Money::fromMinor($taxMinor));
            $item['taxable_amount'] = $taxable;
            $item['tax_amount'] = $tax;
            $item['tax_rate'] = $this->effectiveRate($taxable, $tax, $rate);

            return $item;
        });
    }

    public function effectiveRate(float|string $taxable, float|string $tax, float $fallbackRate): float
    {
        $fallback = $fallbackRate > 0 ? Money::round($fallbackRate) : 0.0;
        if (Money::minor($taxable) <= 0) {
            return $fallback;
        }

        if ($fallback > 0 && Money::cmp(Money::percentOf($taxable, $fallback), $tax) === 0) {
            return $fallback;
        }

        $rateMinor = (int) round(
            (Money::minor($tax) * 10000) / Money::minor($taxable),
            0,
            PHP_ROUND_HALF_UP
        );

        return Money::round(Money::fromMinor($rateMinor));
    }
}
