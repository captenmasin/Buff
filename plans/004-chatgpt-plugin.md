# Plan 004: Publish a Buff plugin for ChatGPT

> **Executor instructions**: Reuse the existing Buff MCP server and OAuth flow.
> Read both repositories' `AGENTS.md` and matching `.ai/rules` before editing.
> Confirm installed package versions and search their documentation before API
> changes. Use the Laravel and testing skills for backend work. Add coverage for
> changed behavior, run affected tests, and format modified PHP with
> `vendor/bin/pint --dirty --format agent`. Update this plan and its status in
> `plans/README.md` as work proceeds. Prepare and validate the submission before
> requesting approval to publish. Keep credentials out of committed artifacts.

## Status

- **Status**: TODO
- **Priority**: P2
- **Effort**: M — packaging, integration validation, and submission preparation;
  external review time is unknown.
- **Risk**: Private fitness data, account authorization, and unintended writes.
- **Depends on**: Existing MCP implementation. Public AI meal analysis also
  depends on the subscription readiness tracked by Plan 003.
- **Planned at**: 2026-10-02; client `113f448e`, API `524258d`.
- **Evidence**: Source and official documentation inspected. Automated tests,
  live endpoint availability, ChatGPT account access, and publication are not
  verified by this planning task.

## Outcome and scope

Users install Buff in ChatGPT, connect their Buff account, and ask for summaries
or log meals and workouts through conversation. Writes use the same server
records and sync pipeline as the mobile app. ChatGPT sees server-synced data;
unsynced phone data becomes available only after the phone syncs. Plugin writes
appear on the phone after its next successful sync.

Start with a private ChatGPT trial, then package the working integration for
public review. Reuse all 11 existing tools; the public listing must accurately
describe the complete discovered tool surface, even if starter prompts focus
on summaries and logging. Evaluate every advertised tool before submission.

Initial scope is an MCP-backed plugin with text and structured results. Custom
cards, dashboards, a second API server, new dependencies, and a new billing
system are deferred. Add a bundled skill only if trial results show a workflow
needs instructions beyond the existing tool metadata. Progress-photo listing
provides metadata, not a gallery; do not promise image viewing.

## Current foundation

The API repository is `../buff-server`. Installed direct dependencies include
Laravel `13.33.0`, Laravel MCP `0.9.6`, and Passport `13.8.0`.

- `routes/ai.php` registers `/mcp/buff`, OAuth discovery/authorization, and
  dynamic client registration when `MCP_ENABLED` is true. It defaults to false.
- `EnsureMcpAccess` requires an authenticated, email-verified Buff user and an
  active assistant connection; the endpoint also requires the `mcp:use` scope.
- OAuth supports PKCE, app approval, a verified browser-login fallback, token
  refresh, and independently revocable connections. Existing tests cover these
  flows, including a ChatGPT callback and an MCP initialization handshake.
- The client already has Connected Assistants and MCP approval screens. Reuse
  them; mobile UI changes are only needed for a demonstrated integration gap.
- `McpToolRunner` returns structured results/errors and records tool outcomes.
  Shared action services provide confirmation, idempotency, and sync behavior.

| Existing tools | User capability | Behavior to preserve |
| --- | --- | --- |
| `get-daily-summary`, `get-weekly-summary` | Review meals, macros, workouts, and progress | User timezone and preferred units |
| `list-fitness-records` | Find existing records | Ownership, pagination, and bounded date ranges |
| `write-fitness-records`, `delete-fitness-records`, `confirm-action` | Log, edit, or remove records | Exact single creations may save immediately; estimates, updates, bulk writes, goals/profiles/preferences, and deletions require review |
| `analyze-meal`, `estimate-workout` | Prepare estimated meal/workout entries | Estimates remain drafts until confirmation; meal analysis retains entitlement and quota checks |
| `list-progress-photos`, `upload-progress-photo` | List photo metadata or upload a photo | Private metadata, bounded uploads, and confirmation for replacements |
| `export-account-data` | Download account data | Confirmation and a short-lived, single-use private download |

## 1. Establish the release contract

- [ ] Confirm the ChatGPT account/workspace supports developer mode and select
  the verified publisher identity for a public release.
- [ ] Use an isolated staging deployment and synthetic, email-verified Buff
  accounts. Select the actual HTTPS origin; do not invent a staging hostname or
  turn production MCP on merely to run the trial.
- [ ] Review the data exposed by every tool: records, free-text fields, body
  measurements, photos, exports, and third-party meal-analysis processing.
  OpenAI prohibits protected health information in plugins; sensitive personal
  data requires necessary collection, adequate consent, and prominent
  disclosure. Resolve public eligibility before publishing these capabilities.
- [ ] Prepare accurate privacy, terms, support, and website URLs. Explain the
  plugin's data recipients, retention, assistant revocation, account deletion,
  and the distinction between disconnecting Buff and deleting chat history.
- [ ] Keep summaries and ordinary logging usable without Buff+. Existing paid
  users can access their included AI feature; the plugin must not sell
  subscriptions, display plans, or promote upgrades.

**Completion evidence:** Named staging environment, synthetic test accounts,
agreed listing scope, and a documented public data/consent decision in this plan.
Unresolved public eligibility does not prevent testing with synthetic data.

## 2. Validate the existing server and fix concrete gaps

- [ ] Confirm staging migrations, stable Passport keys, canonical HTTPS
  `APP_URL`, shared atomic cache, private storage, queue, and scheduler are
  configured before enabling staging MCP.
- [ ] Inspect `/mcp/buff` with MCP Inspector. Verify initialization, Streamable
  HTTP, OAuth discovery, PKCE, registration, token refresh, and the 11-tool list.
  Validate the actual tool names, schemas, structured results, authentication
  declarations/challenges, and safety annotations against current OpenAI
  requirements. Patch missing compatibility metadata using the installed MCP
  package's supported APIs.
- [ ] Exercise app approval and browser fallback, denial, unverified accounts,
  incorrect scopes, expired tokens, and connection revocation. Verify revoking
  an assistant does not revoke the mobile app's account session.
- [ ] Give MCP a neutral `subscription_required` message. The current shared
  meal-analysis error says to purchase in the iOS/Android app, and
  `McpToolRunner` forwards it verbatim. Normalize that error at the MCP response
  boundary, retaining the error code and existing subscription/quota checks.
  Suggested message: "AI meal analysis is not included in this account's
  current entitlement." Add a regression test through the MCP endpoint.
- [ ] Verify confirmation previews disclose affected records and estimated
  values. Do not treat an initial edit/delete request as approval of a later
  preview. Confirm only after the user accepts the reviewed action.
- [ ] Validate attachment handling inside ChatGPT. Existing meal analysis accepts
  text/base64 images; progress uploads can return a private browser upload link.
  Use that existing link when attachment data is unavailable. Do not assume
  ChatGPT can pass arbitrary attachments as base64, fetch user-supplied remote
  URLs, or bypass upload validation. Advertise only verified attachment flows.

Reuse the existing feature tests. Add cases only for changed behavior or an
identified coverage gap. Run the affected file after each change; use the
following focused MCP baseline before the private trial:

```sh
cd /Users/mason/Sites/buff-server
php artisan test --compact tests/Feature/McpOAuthTest.php tests/Feature/McpReadToolsTest.php tests/Feature/McpMutationToolsTest.php tests/Feature/McpDraftAndMediaToolsTest.php
```

If client sync or approval behavior changes, also run the corresponding
`BuffSyncTest.php` or `McpApprovalTest.php` in the client. Any native device
build/run remains a user action; ask which platform before giving commands.

**Completion evidence:** Passing relevant tests and Inspector results, verified
schemas/annotations/authentication, and a list of working attachment paths.

## 3. Run the private ChatGPT trial

- [ ] Enable ChatGPT developer mode, register the staging MCP endpoint, and
  connect a synthetic Buff account using OAuth. Account/workspace policy may
  limit developer-mode access.
- [ ] Run direct prompts, paraphrases, follow-ups, empty results, and error
  cases. Record tool selection, arguments, result, confirmation, and persisted
  state. Reuse the same examples after metadata changes.
- [ ] Prove both sync directions: a mobile-synced record appears in ChatGPT;
  a ChatGPT-created record appears after the client's next sync without a
  duplicate. Check account switching and an offline client with pending writes.
- [ ] Exercise every discovered tool, including photo uploads, exports,
  pagination, quota exhaustion, idempotent retries, and expired/conflicting
  confirmation tokens. Inspect the saved records after writes.

Use these as the core public-review scenarios, alongside tool-level checks:

| Scenario | Expected outcome |
| --- | --- |
| "How much protein have I logged today?" | Daily summary uses the account timezone and actual logged values |
| "Show my workouts this week." | Appropriate weekly summary or record lookup; no invented activity |
| Log a meal with complete, user-supplied nutrition | One exact record; a repeated request with the same idempotency key produces no duplicate |
| "Estimate and log my 30-minute run." | Workout draft, review, then confirmation; nothing saved before approval |
| "Remove the meal I logged twice." | Identify the correct record, preview deletion, and confirm only after approval |
| Request another user's record | Refused without disclosing private data |
| Request an edit, then decline confirmation | No write; expired/reused tokens cannot execute it later |
| Use a revoked connection | Access refused; reconnect requires valid authorization |

**Completion evidence:** Five positive and three negative scenarios pass in
ChatGPT, every tool has been exercised, and sync/confirmation behavior matches
the app. These examples supplement the existing automated tests.

## 4. Package the installable plugin

- [ ] Store the package source under the existing `artifacts/` base directory,
  at `artifacts/chatgpt-plugin/`. Keep runtime implementation in `buff-server`.
- [ ] Use the current portable Agent Plugins format: root `plugin.json`, root
  `mcp.json` with the Streamable HTTP connection, and referenced assets under
  `assets/`. Follow the current schemas rather than copying an old plugin
  manifest. Use a stable package ID such as `buff` and listing name **Buff**.
- [ ] Put OpenAI listing metadata under `extensions.com.openai`: concise
  descriptions, brand assets, actual policy/support links, and tested starter
  prompts. Include the MCP declaration in the initial submission package.
- [ ] For a local installed-plugin trial, map the registered developer-mode
  connection using its real `plugin_asdk_app...` ID where the current package
  format requires it. Keep staging and production mappings explicit.
- [ ] Validate JSON, referenced files, asset requirements, and ZIP structure.
  Exclude `.env`, keys, access tokens, user exports, logs, and private test data.
- [ ] Install from a local marketplace and rerun the trial in a fresh chat.
  Confirm account linking, tools, and starter prompts work after installation.

**Completion evidence:** A validated, installable ZIP and its versioned source.
If a skill is needed, create one focused Buff workflow using the skill-creator
instructions; do not add empty skills, hooks, or lifecycle scripts.

## 5. Prepare review and publish

- [ ] Deploy the validated server to the canonical production HTTPS origin.
  A stable public endpoint is required for submission; a development tunnel
  alone is insufficient. Verify it before enabling production MCP.
- [ ] Configure the production publisher identity and connection, complete the
  exact domain-verification challenge, and scan the MCP tools. Resolve required
  automated findings and confirm the scanned surface matches this plan.
- [ ] Supply an immediately usable synthetic reviewer account. Its browser
  fallback must work without installing Buff, external MFA approval, magic
  links, or access to a private network. Provide a subscribed test account when
  AI analysis is part of the listing.
- [ ] Prepare the required five positive and three negative test cases, video
  walkthrough, release notes, and sign-in instructions. Enter reviewer
  credentials separately in the review portal, never in the ZIP or this plan.
- [ ] Run the relevant automated checks and installed-plugin scenarios against
  production synthetic data. After affected tests pass, ask the user to run the
  complete `php artisan test --compact` suite in repositories changed by the
  implementation.
- [ ] Present the exact package, listing, endpoint configuration, privacy
  disclosures, and validation evidence for approval to submit/publish. Submit
  for review after approval; publish after OpenAI approval and user authorization.

**Completion evidence:** Published directory listing, a successful fresh-account
connection, completed scenarios, and operator-recorded publication evidence.
Track source readiness, review submission, and publication separately; a ZIP or
submitted draft alone does not make this plan DONE.

## Rollback and maintenance

Retain the prior plugin package and server revision. Revoke an individual
connection for an account-specific problem. For a system-wide security incident,
use the existing `MCP_ENABLED=false` switch; it affects every Buff MCP assistant,
not just ChatGPT. Preserve synced records and mobile account tokens.

Monitor existing tool outcomes, authentication failures, rejected writes, and
AI quota/cost signals without logging tokens or unnecessary fitness data.
Refresh developer-mode metadata after server changes and rerun affected
scenarios. Published MCP changes undergo OpenAI checks; package metadata or
skill changes require a new ZIP and the current review/publication flow.

## STOP conditions

- Cross-account access, unauthorized writes, lost sync data, or confirmation
  bypass: stop rollout and fix the shared server path with regression coverage.
- Unresolved restricted-data eligibility or inadequate consent: do not publish
  the affected capabilities. Do not assume personal fitness data is PHI or that
  every fitness field is automatically permitted.
- Missing public HTTPS access, publisher verification, reviewer access, or
  required scan results: retain the private trial and completed package until
  those external prerequisites are resolved.
- A required new dependency or base directory: obtain approval before adding
  it, as required by the repositories' instructions.

## Official references

Requirements were checked on 2026-10-02. Recheck before implementing or submitting
because packaging, account access, and review requirements can change.

- [Build an MCP server](https://developers.openai.com/plugins/build/mcp-server)
- [Authenticate users](https://developers.openai.com/plugins/build/auth)
- [Connect and test a plugin](https://developers.openai.com/plugins/deploy/connect-chatgpt)
- [Package a plugin](https://developers.openai.com/plugins/build/plugins)
- [Upload and submit a plugin](https://developers.openai.com/plugins/deploy/submission)
- [Plugin guidelines: data and monetization](https://developers.openai.com/plugins/plugin-guidelines)
