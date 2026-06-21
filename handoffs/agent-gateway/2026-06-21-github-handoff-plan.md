# Hermes/OpenClaw GitHub Handoff Plan

Date: 2026-06-21  
Purpose: create a reliable file/artifact handoff channel between OpenClaw and
Hermes without depending on local filesystem paths.

## Decision

Use a GitHub repository as the primary source-of-truth and artifact handoff
channel for Hermes/OpenClaw technical work.

Google Drive remains useful for human-facing docs, but GitHub is better for:

- deploy handovers
- code patches
- plugin ZIP/package references
- issue tracking
- changelog/history
- agent-readable verification steps

## Critical Security Rule

Do **not** write the GitHub token itself into any source-of-truth file,
checkpoint, handover, repo file, chat attachment, or memory file.

The source of truth may store:

- repo URL
- branch name
- expected token environment variable name
- token owner/scope description
- access test result

The source of truth must not store:

- raw GitHub token
- private key
- OAuth refresh token
- password
- passport/identity data
- credit card data

## What Hermes Must Confirm

Hermes should confirm:

1. Repository URL
   - Example: `https://github.com/unitysam-dev/agent-gateway-handoffs`
2. Default branch
   - Example: `main`
3. Whether OpenClaw and Hermes both have access
4. Token mechanism
   - Preferred: environment variable, e.g. `GITHUB_TOKEN`
   - Alternative: GitHub App / deploy key
5. Token permissions
   - contents: read/write
   - issues: read/write, if using issues
   - pull requests: read/write, if using PRs
   - releases: write, if uploading plugin ZIPs as release assets
6. Maximum artifact size expected
   - Markdown handovers can live directly in repo.
   - Larger ZIPs may use GitHub Releases.

## Recommended Repository Layout

```text
/
  README.md
  SOURCE_OF_TRUTH.md
  handoffs/
    agent-gateway/
      2026-06-21-hermes-deploy-handover.md
  artifacts/
    agent-gateway/
      README.md
  checks/
    agent-gateway/
      2026-06-21-verification.md
```

## Handoff Workflow

1. OpenClaw creates or updates a handoff markdown file under:

   ```text
   handoffs/<project>/<YYYY-MM-DD>-<short-title>.md
   ```

2. If there is a deployable binary artifact:

   Preferred:

   - upload to GitHub Release
   - include release URL in the handoff

   Acceptable for small artifacts:

   - commit to `artifacts/<project>/`

3. OpenClaw commits with a clear message:

   ```text
   handoff(agent-gateway): deploy plugin request-status relay fix
   ```

4. Hermes pulls the repo and confirms receipt by either:

   - commenting on the GitHub issue/PR
   - committing a verification file under `checks/<project>/`
   - replying in Telegram with the commit hash it used

5. Hermes deploys from the repo/release artifact, not from an OpenClaw local path.

6. Hermes writes deployment result:

   ```text
   checks/<project>/<YYYY-MM-DD>-deploy-result.md
   ```

## Source Of Truth Entry To Add After Hermes Confirms

Once Hermes confirms repo URL and token mechanism, add this to the active
Agent Gateway source of truth/checkpoint:

```markdown
## Hermes/OpenClaw File Handoff

Primary handoff channel: GitHub

- Repo: <confirmed repo URL>
- Default branch: <confirmed branch>
- Token location: environment variable `<name only, no token value>`
- Token permissions: <confirmed scopes>
- Handoff directory: `handoffs/agent-gateway/`
- Verification directory: `checks/agent-gateway/`
- Binary artifacts: GitHub Releases preferred; repo `artifacts/` allowed only for small files

Rule: never reference local OpenClaw paths as Hermes-readable unless shared
filesystem access has been confirmed for that exact path.
```

## First Test

After Hermes confirms access, run a minimal end-to-end test:

1. OpenClaw commits:

   ```text
   handoffs/agent-gateway/2026-06-21-test.md
   ```

2. Hermes pulls and replies with:

   - commit hash
   - file title
   - first line of the file

3. Hermes commits:

   ```text
   checks/agent-gateway/2026-06-21-hermes-read-test.md
   ```

4. OpenClaw pulls and verifies.

Only after this test passes should GitHub become the default handoff method.

## Current Candidate Handoff To Move Into Repo

Current local file:

```text
/home/node/.openclaw/workspace/HERMES_DEPLOY_HANDOVER_2026-06-21.md
```

Suggested repo path:

```text
handoffs/agent-gateway/2026-06-21-hermes-deploy-handover.md
```

Suggested commit message:

```text
handoff(agent-gateway): deploy request-status relay fix
```

