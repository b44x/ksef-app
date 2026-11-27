# Roadmap

Milestones are small enough to ship one by one. Each ends with something that works end to end on the KSeF TEST environment.

## M1: Connect a company
- [x] Skeleton (Laravel, Inertia + React, the SDK), CI
- [x] Companies with encrypted credentials; token store; KSeF gateway
- [x] Sign in; first-run setup of the administrator
- [x] Onboarding: environment, NIP, token or certificate (a signature made outside the app is stored as a choice; the signing screen is still to do)
- [x] Connection check (what `ksef-doctor` does), shown in the UI
- [ ] Signing screen for logins signed outside the app (trusted profile)

## M2: Issue invoices
- [x] Contractors
- [x] Products, numbering series (a number is never handed out twice)
- [ ] Invoice form: VAT, corrections, advance and settlement invoices; totals as the SDK computes them
- [ ] Send in the background, follow the status, keep the UPO and the KSeF number
- [ ] Invoice view with the QR code; download the XML

## M3: Receive invoices
- [ ] Scheduled synchronisation of purchase invoices (incremental export)
- [ ] List with search and filters; invoice view built on the SDK reader; download XML

## M4: Stay safe
- [ ] Availability banner; offline invoices with the delivery deadline and a reminder
- [ ] Error handling in the UI: rejected invoices explained, retries without duplicates
- [ ] Audit log; backup and restore

## M5: PDF and polish
- [ ] PDF of the invoice (a separate library), UPO as PDF
- [x] Polish and English interface (more languages: add a JSON file)
- [ ] Import contractors and products from CSV

## M6: Desktop
- [x] CI builds unsigned installers for Windows and macOS (see docs/DESKTOP.md)
- [ ] Manual check that the installers start and work on real Windows and macOS machines
- [ ] Packaging, signing and notarisation, auto-update

## Later
Several companies per installation, accountant access, recurring invoices, payment matching, an API and webhooks.
