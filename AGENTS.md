# AGENTS.md

## Wording

A monitor watches something, usually a URL. The monitor itself is never online, offline, up or down; the thing it watches is.

- Don't write texts like "the monitor is offline", "a monitor goes down", "the monitor is back online" or "the monitor is up". This applies to user-facing strings, translations, Slack messages, README, code comments and docblocks.
- Name the thing that is watched instead: "the monitored URL is offline", "when the URL goes offline", "`example.com` is back online".
- Phrases where the monitor acts are fine: "the monitor detects an outage", "the monitor checks the URL every minute".
- When you change a translatable string, update `languages/orbis-monitoring.pot` and `languages/orbis-monitoring-nl_NL.po`, and recompile `languages/orbis-monitoring-nl_NL.mo`.
- The same goes for code. The state of the monitored URL is its availability (the `Availability` enum, `$monitor->availability`), not a "monitor status". The `orbis_monitors` table still stores it in the `status` and `status_changed_at` columns, which are selected as `availability` and `availability_changed_at`.
