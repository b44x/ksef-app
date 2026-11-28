# Roadmap

Milestones are small enough to ship one by one. Each ends with something that works end to end on the KSeF TEST environment.

## M1: Connect a company
- [x] Skeleton (Laravel, Inertia + React, the SDK), CI
- [x] Companies with encrypted credentials; token store; KSeF gateway
- [x] Sign in; first-run setup of the administrator
- [x] Onboarding: environment, NIP, token or certificate (a signature made outside the app is stored as a choice; the signing screen is still to do)
- [x] Connection check (what `ksef-doctor` does), shown in the UI
- [x] Signing screen: for companies without a token or a certificate, the login is signed outside the app (qualified signature, trusted profile) and kept as long as KSeF allows

## M2: Issue invoices
- [x] Contractors
- [x] Products, numbering series (a number is never handed out twice)
- [x] Invoice draft: lines, VAT rates, live totals from the SDK, checks in words before sending
- [x] Issue: the number from the series, the FA(3) built and schema-validated by the SDK, both frozen; XML download
- [x] Corrections (KOR) of accepted invoices, before and after lines, several in a row
- [ ] Advance and settlement invoices
- [x] Send in the background (queue), follow the status, keep the UPO and the KSeF number; safe to send again after a lost connection
- [ ] Invoice view with the QR code; download the XML

## M3: Receive invoices
- [x] Synchronisation of purchase invoices: the first one from a date you choose, then every 15 minutes, incremental
- [x] List with search and filters, "new" markers; invoice page built on the SDK reader; download XML

## M4: Stay safe
- [x] Availability banner (production only: the TEST availability service shows made-up failures)
- [x] Offline invoices: the Offline certificate, QR codes I and II, the delivery deadline, a reminder on the dashboard, delivery with one click
- [ ] Error handling in the UI: rejected invoices explained, retries without duplicates
- [ ] Audit log; backup and restore

## M5: PDF and polish
- [x] PDF of issued and received invoices (Polish or English, with the KSeF QR codes)
- [x] Total failure: issuing outside KSeF without QR codes and certificate (check the delivery rules with the Ministry's announcement)
- [x] E-mail summary (offline deadlines, new purchases, rejections; MAIL_* in the environment)
- [ ] UPO as PDF
- [x] Polish and English interface (more languages: add a JSON file)
- [x] Import contractors and products from CSV (Polish or English headers, UTF-8 or Windows-1250)

## M5b: Make it a product (autonomous session)
- [x] Invoice templates: editor with live PDF preview, three layouts, colour, font, logo, visible parts, saved templates, per-invoice choice

- [x] Payments: register of payments, paid / partly paid / overdue, one-click "paid", invoice list filters and search
- [x] Dashboard and reports: sales by month, VAT by rate, top customers, receivables ageing, sales register CSV

- [x] Recurring invoices: weekly to yearly, draft or issued automatically, daily scheduler run

- [x] Invoice by e-mail: PDF attached, message editable, remembered on the invoice
- [x] Invoice defaults (payment term, method, bank account, notes)

- [x] Contractor data by NIP from the Ministry of Finance VAT register (white list); VAT status shown. Unverified against the live API from the build sandbox (the register blocks datacenter addresses): check on a real machine

## M6: Desktop
- [x] CI builds unsigned installers for Windows and macOS (see docs/DESKTOP.md)
- [ ] Manual check that the installers start and work on real Windows and macOS machines
- [ ] Packaging, signing and notarisation, auto-update

## Later
Several companies per installation, accountant access, recurring invoices, payment matching, an API and webhooks.
