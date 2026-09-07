# HyperPay

## What exists today

| Area | Status |
|------|--------|
| Subscription checkout UI label `hyperpay` | Placeholder — frontend confirm activates subscription locally |
| Order/booking payment gateways | `local` + `stripe` only in `PaymentGatewayManager` |
| Merchant marketplace / split onboarding | Abstraction + pending provider (`HyperPayMerchantSettlementProvider`) — **not live** |

## Required for production merchant settlement

1. HyperPay account product that supports marketplace / split / beneficiary settlement (confirm contract name with provider)
2. Entity ID + access token in env
3. `HYPERPAY_MERCHANT_ONBOARDING_ENABLED=true`
4. Real onboarding API integration (not invented here)
5. Webhook verification for merchant charges
6. Never route merchant customer funds through Platform subscription account

## Staging-only sandbox

`HYPERPAY_MERCHANT_SANDBOX_AUTO_APPROVE=true` may mark provider onboarding `active` for tests. **Do not enable in production.**
