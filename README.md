# Orbis Monitoring

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
