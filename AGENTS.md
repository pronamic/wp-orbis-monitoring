# AGENTS.md

## Wording

A monitor checks whether something, usually a URL, responds as expected (status code, redirect location, …). It does not know whether that thing is online or offline: a `202` where a `200` is expected is a deviation, not an outage.

- The status of a monitor is the outcome of its checks: OK, Pending, Alert or Unknown (the `Status` enum, `$monitor->status`, stored in the `status` and `status_changed_at` columns of the `orbis_monitors` table).
- Don't claim that the monitor or the watched thing is online, offline, up or down. This applies to user-facing strings, translations, Slack messages, README, code comments and docblocks.
- Describe what the monitor saw instead: "`example.com` does not respond as expected", "an alert is raised", "the alert is resolved", "the response matches the expectations again".
- Phrases where the monitor acts are fine: "the monitor raises an alert", "the monitor checks the URL every minute".
- When you change a translatable string, update `languages/orbis-monitoring.pot` and `languages/orbis-monitoring-nl_NL.po`, and recompile `languages/orbis-monitoring-nl_NL.mo`.
