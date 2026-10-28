# Orbis Monitoring

## Installation

```sh
composer install
npm install
```

## Development

### Local environment (wp-env)

```sh
npx wp-env start
npx wp-env stop
```

The `.wp-env.json` maps the plugin twice into the WordPress environment:

| Mount path | Source | Purpose |
|---|---|---|
| `wp-content/plugins/orbis-monitoring-dev` | `./` | Live development (including dev files) |
| `wp-content/plugins/orbis-monitoring` | `./build/orbis-monitoring/` | Built distribution version |

Run `composer run build` to create the build version.

## Query to delete response body

```sql
UPDATE
	orbis_monitor_responses
SET
	response_body = NULL
WHERE
	monitored_date < DATE_ADD( NOW(), INTERVAL -1 MONTH )
;
```
