# Buff MCP implementation plan

## Goal

Add a public remote MCP server to `buff-server` so verified Buff users can connect compatible AI assistants, inspect progress, and manage fitness data without opening Buff.

```text
AI assistant
    │ OAuth 2.1
    ▼
Buff MCP endpoint
    ├── Read summaries and records
    ├── Create, update, and delete fitness records
    ├── Draft meals and workouts
    ├── Upload progress photos
    └── Export data
             │
             ▼
Existing SyncService → SyncRecord → Buff mobile sync
```

## Product boundaries

The MCP will manage:

- Meals and recipes
- Workout summaries
- Body weight, body fat, measurements, and notes
- Daily goals and body profile
- App fitness preferences
- Progress-photo uploads and metadata
- Daily and weekly summaries
- Account data export

It will not manage:

- Passwords, email, social accounts, or authentication settings
- Account deletion
- Individual exercises, sets, reps, or weights
- Coaching or medical advice
- Existing progress-photo image contents
- Raw AI conversations

## MCP tools

Keep the surface small:

| Tool | Purpose | Confirmation |
|---|---|---|
| `get-daily-summary` | Meals, macros, goals, workouts, and remaining calories for one date | No |
| `get-weekly-summary` | Daily totals, adherence, workouts, and weight movement | No |
| `list-fitness-records` | Paginated meals, workouts, metrics, recipes, goals, profile, and preferences | No |
| `write-fitness-records` | Atomic single or bulk create/update operations | Updates and bulk writes |
| `delete-fitness-records` | Atomic deletion of one or more records | Always |
| `analyze-meal` | Produce a draft from text and optional images using Buff's analyzer | Confirmation logs it |
| `estimate-workout` | Produce a workout/calorie draft from description, duration, and body weight | Confirmation logs it |
| `list-progress-photos` | Return IDs, dates, poses, and MIME types without URLs | No |
| `upload-progress-photo` | Accept supported attachment data or create a secure upload fallback | Replacements require confirmation |
| `export-account-data` | Produce a short-lived export download | Always |
| `confirm-action` | Execute a reviewed pending action | The confirmation itself |

All tools return structured MCP responses with clear validation errors and appropriate read-only/destructive annotations.

## Phase 1: MCP and OAuth foundation

Work in `/Users/mason/Sites/buff-server`.

- Add `laravel/mcp` as a direct production dependency. It currently exists only transitively through development-only Laravel Boost.
- Add Laravel Passport for OAuth 2.1.
- Prove Passport and the existing Sanctum mobile authentication can coexist without changing current token behaviour.
- Register `routes/ai.php` and `/mcp/buff`.
- Enable OAuth discovery, PKCE, dynamic client registration, and the single `mcp:use` scope.
- Require verified email accounts.
- Add multiple named, independently revocable connections.
- Implement app-first approval with hosted browser-login fallback.
- Add a connection page/API showing client name, linked date, last-used date, and revoke action.

This phase must include regression tests proving existing Sanctum login, refresh, and API access remain unchanged.

## Phase 2: Read-only tools

Build server-side readers over the existing `SyncRecord` store.

- Port the relevant calculations from Buff's local daily and weekly summary services.
- Use the user's configured timezone for all relative dates.
- Ignore tombstoned records.
- Limit record queries to 90 days and 100 records per page.
- Return canonical units alongside the user's preferred display unit.
- Exclude photo URLs, storage paths, deleted records, and authentication data.

This makes the MCP useful before enabling any mutations.

## Phase 3: Safe synchronized writes

Route MCP mutations through the existing `SyncService` so assistant changes appear naturally in the mobile app.

- Give every MCP connection a stable synthetic device UUID.
- Preserve the current newest-timestamp-wins conflict policy.
- Reuse `config/buff.php` as the canonical record validation contract.
- Extract the shared sync-record validation where needed instead of maintaining separate MCP rules.
- Support atomic batches of up to 50 operations.
- Validate the entire batch before changing anything.
- Require an idempotency UUID for every mutation.
- Make retries return the original outcome instead of duplicating entries.

Single exact-value creations may execute immediately. Updates, deletes, bulk operations, goal changes, and exports enter the confirmation flow.

## Phase 4: Confirmation and auditing

Risky calls return:

```json
{
  "status": "confirmation_required",
  "summary": "Delete 4 meal entries from 20–22 August?",
  "confirmation_token": "opaque-short-lived-token",
  "expires_at": "..."
}
```

Implementation:

- Store the validated pending action encrypted in cache.
- Bind it to the user and OAuth connection.
- Expire it after five minutes.
- Consume it once through `confirm-action`.
- Reject expired, reused, or cross-connection tokens.

Add two small tables:

- `mcp_connections`: user, OAuth client, display name, linked/last-used/revoked timestamps.
- `mcp_tool_audits`: connection, request ID, tool, affected record IDs, outcome, error code, timestamp.

Do not store tool arguments, results, images, or conversation text in the audit log.

## Phase 5: Meal and workout drafts

### Meals

Generalize the existing `MealAnalysisService` to accept:

- One to three meal images
- A text description without images
- Text plus images

Buff produces the macros, confidence, and recognized components. The existing daily analysis quota remains in force. Nothing is logged until the draft is confirmed.

### Workouts

Add a small workout estimation service:

1. Parse the activity and estimated MET value using the already-installed Laravel AI package.
2. Read the latest body weight.
3. Calculate calories deterministically from MET, weight, and duration.
4. Return the title, duration, calories, assumptions, and confidence as a draft.
5. Require confirmation before logging.

If duration or body weight is unavailable, return an actionable request for the missing value rather than inventing it.

## Phase 6: Progress photos

Reuse the existing body-metric photo storage and replacement behaviour.

- Accept front, side, or back pose labels inferred by the assistant.
- Preserve the existing three-photo and 5 MiB limits.
- Validate actual image contents and MIME type.
- Never fetch arbitrary remote image URLs.
- Permit base64 attachment data only where the client can supply it.
- Otherwise return a short-lived, single-use Buff upload link.
- Require confirmation before replacing an existing pose or deleting a photo.
- Never return existing photo URLs through MCP.

## Phase 7: Rate limits and rollout

Initial limits:

- 60 read calls per user/client per minute
- 20 mutation calls per user/client per minute
- 2 analysis calls per minute
- Existing five-per-day meal-analysis quota
- 50 records per atomic mutation
- 90-day ordinary read range

Add an environment kill switch such as `MCP_ENABLED`. Once verified, enable it for all signed-in, email-verified users as requested.

## Verification

Add focused Pest coverage for:

- OAuth discovery, PKCE, dynamic registration, linking, and revocation
- App approval and browser fallback
- Existing Sanctum authentication regression
- Cross-user and cross-connection isolation
- Daily and weekly summaries and timezone boundaries
- Mobile-to-MCP and MCP-to-mobile synchronization
- Atomic bulk operations
- Idempotent retries
- Confirmation expiry, replay, and connection binding
- Meal text/photo analysis and quotas
- Workout calculation and missing-profile handling
- Photo upload, replacement, privacy, and fallback links
- Audit logs excluding payload contents
- Rate limits
- Export excluding credentials and progress-photo contents

Run affected Pest files during each phase, then the full server suite and Pint before completion.

## Definition of done

A user can connect ChatGPT, Claude, or another OAuth-capable MCP client and successfully:

1. Ask how today or the current week is going.
2. Log an exact meal immediately.
3. Receive and confirm an estimated meal from text or a photo.
4. Receive and confirm an estimated workout.
5. Add or update measurements.
6. Attach a progress image or receive a secure upload fallback.
7. Preview and confirm bulk edits or deletions.
8. Export their fitness data.
9. Revoke that assistant without affecting Buff's mobile session.

## Explicitly deferred

- Granular OAuth scopes
- Account and security management
- Set-and-rep exercise tracking
- Buff-generated coaching
- Retrieval of existing progress-photo contents
- Conversation storage
- Autonomous background changes
- An MCP-hosted dashboard beyond the secure photo upload fallback
