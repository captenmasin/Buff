# Buff mobile app

Buff is the offline-first Laravel, Inertia, Vue, and NativePHP client. Health logs, sync state, encrypted credentials, the sync outbox, and pending progress photos live locally on the device. The sibling `../buff-server` repository owns remote accounts and synced data.

## Setup

Requirements: PHP and Composer, Node.js and pnpm, SQLite, and the relevant Apple or Android toolchain only when building a native shell.

```sh
composer run setup
```

Copy values from `.env.example`. `BUFF_API_URL` must target the server's `/api/v1` base URL. Keep `BUFF_ALLOW_REMOTE_HTTP=false` outside explicitly controlled local development.

For web development:

```sh
composer run dev
```

Stable verification commands are:

```sh
composer run test
pnpm test:frontend
pnpm type-check
```

## Native shell

Native configuration uses `NATIVEPHP_APP_ID`, `NATIVEPHP_APP_VERSION`, `NATIVEPHP_APP_VERSION_CODE`, and `NATIVEPHP_DEEPLINK_SCHEME`. Android signing uses the `ANDROID_*` variables in `.env.example`.

NativePHP build, run, watch, and IDE commands are manual and platform-specific. Choose iOS or Android before running them; do not use a production API over insecure HTTP.

## Data and sync

Writes are local first and enter the sync outbox. The app sends them to `buff-server` when an authenticated connection is available. Pending progress photos remain on-device until upload succeeds or the related local/account data is explicitly removed.

Signing out or deleting an account removes user-owned local data. Signing into a different account requires confirmation and then replaces the prior account's local data; already-synced server data is unaffected.

## Troubleshooting

- Confirm `BUFF_API_URL` is reachable from the simulator or device, not only from the host machine.
- PHPUnit forces Inertia SSR off so local `.env` settings cannot create test-only SSR requests.
- If frontend changes are missing, run the existing Vite development or build script; generated assets are not a substitute for source changes.

See `../buff-server/README.md` for OAuth, storage, queue, cache-lock, scheduler, and deployment requirements.
