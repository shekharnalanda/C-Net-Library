# QR / UTR fee collection — 3 October 2026

Pay Fee opens the shared merchant checkout on https://pay.mciedu.com/pay/upi. The supplied Axis merchant QR and a UPI app link collect payment; submitting a bank reference only marks the claim pending. Group Finance Admin verifies the actual bank credit, amount and reference before any receipt is issued.

The core reserves each pending/verified UTR globally across the three connections. HMAC-signed, timestamped messages bind the expected institution, student order, INR amount and monotonic revision. Only bank-verified messages can enter a fee ledger. Duplicate delivery is idempotent; changed balances are retained for office reconciliation. Do not automatically trust browser return URLs or a submitted UTR.

## Connected applications

- C-Net Library: membership dues and initial admission membership fee. Admission payment waits for approval/student creation; membership renewals remain controlled by the existing office renewal process.
- Micro Computer Institute: outstanding course fee/installments through the existing application/student login. Cash and other existing gateways remain available. The shared UPI option replaces new legacy manual UPI submissions when enabled.
- C-Net Pathshala: existing outstanding fees, plus office-issued monthly/admission fee invoices. An administrator creates new fee bills under `/admin/upi-invoices`. Admission bills must be linked to the enrolled student before posting. Never create a new bill for debt already in the fee ledger.
- Other institutions can use the central collection form with their unit and student reference. Their website-specific ledgers are not automatically connected by this release.

## Deployment

Use the coordinated `deploy-mci-upi-20261003.py` package. It contains exact source patches, original QR, preflight file checks, database/file backups, forward migrations, matched per-institution secrets and health checks. Keep the script and backups outside all web roots. It never seeds users or changes bank credentials. Do not enable clients before core and all migrations are ready.

Core: `MCI_UPI_ENABLED`, `MCI_UPI_LIBRARY_SECRET`, `MCI_UPI_INSTITUTE_SECRET`, `MCI_UPI_PATHSHALA_SECRET`. Each client: `MCI_PAY_ENABLED`, `MCI_PAY_SECRET`, `MCI_PAY_BASE_URL=https://pay.mciedu.com`. Use distinct random secrets of at least 32 characters for each connection. Values must stay in server environment files.

Group Finance Admin reviews `/admin/upi-payments` on the core. Each client has its own scoped listing at the same path and student entry at `/mci-pay`. Library/Pathshala admission recovery uses `/mci-pay/access` with application number and registered mobile. Core `mci-pay:retry-sync` retries failed notifications; clients also offer a signed status refresh.

## Validation and limits

32 new payment tests pass (139 assertions) across four applications: no premature receipt, duplicate UTR, bank mismatch, role restrictions, callback signatures, idempotency, delayed revisions, fee ledger posting, changed balances, admission waiting and cross-student access. All 88 changed PHP/Blade files passed syntax checks and Blade views compiled. Full source suites have the same pre-existing failing test cases as their unmodified baselines in this isolated environment; no additional failing cases were introduced. Production bank settlement and browser payment-app behavior require a real deployment check; no live payment was made.

The QR is static, so another-phone scan requires the displayed amount to be entered in the UPI app. The mobile UPI link carries the amount. This release is a bank-verified QR collection workflow, not automatic PSP settlement confirmation.
