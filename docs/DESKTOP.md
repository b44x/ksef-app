# Desktop app (Windows and macOS)

The desktop app is the same application as the web one, packaged with NativePHP: Electron shows the window, a bundled
PHP runs Laravel, SQLite keeps the data on the user's computer. Nothing else has to be installed.

## Building

The **Desktop build** workflow builds unsigned installers on GitHub's macOS and Windows runners (manually from the
Actions tab, or by pushing to `spike/desktop`). Download them from the run's artifacts:

| Platform | Files |
| --- | --- |
| macOS | `ksef-app-<version>-arm64.dmg` (Apple Silicon), `…-x64.dmg` (Intel), and `.zip` of both |
| Windows | `ksef-app-<version>-setup.exe` |

Locally: `composer run native:dev` (development) or `php artisan native:build mac|win` (installers in
`nativephp/electron/dist`).

## Running an unsigned build

Unsigned builds are for testing. Until the app is signed:

- **macOS** shows "cannot be opened because the developer cannot be verified". Open the app with right-click →
  Open, or in System Settings → Privacy & Security → "Open Anyway". If the app is reported as damaged, run
  `xattr -cr /Applications/ksef-app.app`.
- **Windows** SmartScreen shows "Windows protected your PC": More info → Run anyway.

## Signing (needed before real users)

| | What it takes | Cost |
| --- | --- | --- |
| macOS | Apple Developer Program account, a "Developer ID Application" certificate, notarisation (`NATIVEPHP_APPLE_ID`, `NATIVEPHP_APPLE_ID_PASS`, `NATIVEPHP_APPLE_TEAM_ID`) | about 99 USD per year |
| Windows | A code-signing certificate, or Azure Trusted Signing (`NATIVEPHP_AZURE_*`) | from about 10 USD per month (Azure) |

The workflow takes these from repository secrets once they exist. Never commit them.

## Known limits

- The build says "INSECURE BUILD: secure app bundle not found": the PHP source ships readable inside the app. That is fine
  for an open-source project; NativePHP's secure bundle is optional.
- Automatic updates need a provider (GitHub releases, S3) configured under `updater` in `config/nativephp.php`.
