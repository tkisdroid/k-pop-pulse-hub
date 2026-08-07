# Repository Workflow

## Start of every session

- Before editing files, inspect the worktree, current branch, and configured remotes.
- Fetch `origin` with pruning and update the local default branch from `origin/main` with a fast-forward-only pull whenever it is safe to do so.
- Never discard, overwrite, reset, or silently stash existing user changes. If local changes prevent a safe update, preserve them and use a safe branch or worktree when possible; otherwise report the blocker before proceeding.
- Begin task work from the latest available GitHub code.

## Finish every completed task

- Run checks appropriate to the changes before publishing.
- Stage only files that belong to the task. Do not include unrelated user changes or generated artifacts unless they are explicitly in scope.
- Commit the completed task with a concise, descriptive message.
- Push the task branch to `origin` and merge the completed work into `main` through a GitHub pull request.
- Do not force-push or bypass unresolved conflicts or required failing checks. If the merge is blocked, report the exact blocker instead of claiming completion.
- After the merge, update the local `main` branch from `origin/main` so the workspace ends on the latest published version.
- Report the validation result, commit, push, pull request, and merge status in the final response.
