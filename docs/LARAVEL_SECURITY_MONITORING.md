# KRB System — Laravel Application Security Monitoring

This document covers the full pipeline that routes Laravel application
security events through Wazuh so they appear in the Manager and IT Officer
AI Security Reports dashboards.

---

## Architecture

```
Laravel app (Login, SecurityMonitoringService)
  │  Log::channel('security')
  ▼
storage/logs/security-YYYY-MM-DD.log   ← JSON, one object per line
  │  Wazuh agent localfile (json format)
  ▼
Wazuh Manager  (decoder: krb-laravel-security, rules: 100810–100817)
  │
  ▼
Wazuh Indexer  (wazuh-alerts-* index)
  │  App\Services\WazuhService::getSecuritySummary()
  ▼
Manager / IT Officer AI Security Reports
```

---

## Files Changed in this Implementation

| File | Change |
|---|---|
| `config/logging.php` | Added `security` channel (daily, JsonFormatter via SecurityJsonFormatter, 30 days) |
| `app/Logging/SecurityJsonFormatter.php` | NEW — forces `"channel":"security"` in JSON output |
| `app/Services/SecurityMonitoringService.php` | All methods now write to `Log::channel('security')` |
| `app/Livewire/Pages/Auth/Login.php` | Removed duplicate `Log::` calls; uses only `SecurityMonitoringService` |
| `app/Listeners/LogFailedLogin.php` | Replaced plain-text format with structured SecurityMonitoringService calls |
| `wazuh/ossec-localfile-config.xml` | Added `security*.log` localfile entry (existing health log preserved) |
| `wazuh/decoders/krb_laravel_security_decoders.xml` | NEW — decoder for Laravel JSON security log |
| `wazuh/rules/krb_laravel_security_rules.xml` | NEW — rules 100810–100817 |

---

## Rule ID Reference

| Rule ID | Level | Severity | Event | Dashboard Bucket |
|---|---|---|---|---|
| 100810 | 3 | Low | Base rule (all security channel events) | Low |
| 100811 | 3 | Low | LOGIN_ATTEMPT | Low |
| 100812 | 6 | Medium | LOGIN_FAILED | Medium |
| 100813 | 3 | Low | LOGIN_SUCCESS | Low |
| 100814 | 12 | **Critical** | BRUTE_FORCE_DETECTED | **Critical** |
| 100815 | 5 | Low | WAZUH_LARAVEL_TEST_EVENT | Low |
| 100816 | 3 | Low | FORM_SUBMIT | Low |
| 100817 | 6 | Medium | LOGIN_FAILED (captcha_failed reason) | Medium |

---

## Deployment on the Server

Run all steps on the Linux server (192.168.1.8) as the user that owns `~/caps-test`.

### 1. Pull updated code

```bash
cd ~/caps-test
git pull
```

### 2. Clear Laravel config cache

```bash
php artisan config:clear
php artisan optimize:clear
```

### 3. Verify the security channel resolves correctly

```bash
php artisan tinker --execute="
\$sec = config('logging.channels.security');
echo 'driver: ' . \$sec['driver'] . PHP_EOL;
echo 'path:   ' . \$sec['path']   . PHP_EOL;
echo 'formatter: ' . \$sec['formatter'] . PHP_EOL;
"
```

Expected output:
```
driver: daily
path:   /home/<user>/caps-test/storage/logs/security.log
formatter: App\Logging\SecurityJsonFormatter
```

### 4. Identify the running Wazuh manager container name

```bash
sudo docker ps --format "{{.Names}}" | grep wazuh
```

Use the name shown (e.g. `single-node-wazuh.manager-1`) in the steps below.
Set it in a variable:

```bash
WAZUH_MANAGER=$(sudo docker ps --format "{{.Names}}" | grep wazuh.manager | head -1)
echo "Manager: $WAZUH_MANAGER"
```

### 5. Deploy the decoder

```bash
sudo docker cp \
  ~/caps-test/wazuh/decoders/krb_laravel_security_decoders.xml \
  "${WAZUH_MANAGER}:/var/ossec/etc/decoders/krb_laravel_security_decoders.xml"

# Verify
sudo docker exec "${WAZUH_MANAGER}" cat /var/ossec/etc/decoders/krb_laravel_security_decoders.xml
```

### 6. Deploy the rules

```bash
sudo docker cp \
  ~/caps-test/wazuh/rules/krb_laravel_security_rules.xml \
  "${WAZUH_MANAGER}:/var/ossec/etc/rules/krb_laravel_security_rules.xml"

# Verify
sudo docker exec "${WAZUH_MANAGER}" cat /var/ossec/etc/rules/krb_laravel_security_rules.xml
```

### 7. Validate manager configuration (before restart)

```bash
sudo docker exec "${WAZUH_MANAGER}" /var/ossec/bin/wazuh-analysisd -t 2>&1 | tail -20
```

Expected: `Configuration seems to be fine.`  
If any error appears, **stop here**, check the rule/decoder XML for syntax issues, and fix before proceeding.

### 8. Restart the Wazuh manager container

```bash
sudo docker restart "${WAZUH_MANAGER}"

# Wait for startup
sleep 10
sudo docker logs "${WAZUH_MANAGER}" --tail=20
```

### 9. Add the security log localfile entry to the Wazuh agent config

The agent config is at `/var/ossec/etc/ossec.conf` on the **host** (not inside Docker).

First, back it up:

```bash
sudo cp /var/ossec/etc/ossec.conf \
        /var/ossec/etc/ossec.conf.bak-$(date +%Y%m%d-%H%M%S)
```

Then add the following block **inside the existing `<ossec_config>` element**:

```xml
<!-- KRB Laravel Application Security Events -->
<localfile>
  <log_format>json</log_format>
  <location>/home/asus/caps-test/storage/logs/security*.log</location>
</localfile>
```

> **Important**: Verify the path matches where the Laravel app writes logs.
> Check: `ls -la ~/caps-test/storage/logs/security*.log`
>
> If the app runs as a different user or in Docker, adjust the path accordingly:
>
> - Native (user `asus`): `/home/asus/caps-test/storage/logs/security*.log`
> - Docker bind mount: `/var/www/html/storage/logs/security*.log`

### 10. Check file permissions

The Wazuh agent runs as `root` on a standard install; it can read most files.
Verify the security log is readable:

```bash
ls -la ~/caps-test/storage/logs/security*.log 2>/dev/null || echo "No security log yet"
```

If needed, ensure the directory is world-executable and the file is world-readable:

```bash
chmod o+x ~/caps-test/storage/logs/
# After the first log entry is created:
chmod o+r ~/caps-test/storage/logs/security*.log
```

**Do NOT use chmod 777.** Only the other-read bit (`o+r`) is needed for the root agent.

### 11. Validate the agent config

```bash
sudo /var/ossec/bin/wazuh-control config-check 2>&1 | tail -20
# or
sudo /var/ossec/bin/ossec-analysisd -t 2>&1 | tail -5
```

### 12. Restart the Wazuh agent (only if validation passes)

```bash
sudo systemctl restart wazuh-agent
# or: sudo /var/ossec/bin/wazuh-control restart
```

---

## Phase 3 Testing — Run on the Server

### Test B: Generate and verify a security log entry

```bash
cd ~/caps-test
php artisan tinker --execute="
\App\Services\SecurityMonitoringService::logLoginFailed('test@wazuh-test.local', 'pipeline_test');
echo 'Event written' . PHP_EOL;
"
sleep 1
ls -la storage/logs/security*.log
tail -1 storage/logs/security-$(date +%Y-%m-%d).log | python3 -m json.tool
```

Expected JSON output:
```json
{
    "message": "LOGIN_FAILED",
    "context": {
        "ip": "127.0.0.1",
        "email": "test@wazuh-test.local",
        "reason": "pipeline_test"
    },
    "level": 300,
    "level_name": "WARNING",
    "channel": "security",
    "datetime": "...",
    "extra": {}
}
```

### Test C: Verify Wazuh agent sees the file

```bash
sudo tail -f /var/ossec/logs/ossec.log | grep "security"
```

Look for a line like: `INFO: Monitoring file 'security-....log'`

If you see `WARN: Unable to open file` it is a permissions issue — apply step 10 above.

### Test D: Test Wazuh rule matching (wazuh-logtest)

```bash
LOG_LINE=$(tail -1 ~/caps-test/storage/logs/security-$(date +%Y-%m-%d).log)
echo "${LOG_LINE}" | sudo docker exec -i "${WAZUH_MANAGER}" /var/ossec/bin/wazuh-logtest
```

Expected output includes:
```
**Phase 1: Completed pre-decoding.
    ...
**Phase 2: Completed decoding.
    decoder: 'krb-laravel-security'
**Phase 3: Completed filtering (rules).
    Rule id: '100812'
    Level: '6'
    Description: 'KRB: Failed login attempt ...'
```

#### Test rule 100814 (BRUTE_FORCE_DETECTED — Critical):

```bash
BRUTE_LINE='{"message":"BRUTE_FORCE_DETECTED","context":{"ip":"10.0.0.1","email":"attacker@example.com","attempts":7},"level":300,"level_name":"WARNING","channel":"security","datetime":"2026-08-23T00:00:00+07:00","extra":{}}'
echo "${BRUTE_LINE}" | sudo docker exec -i "${WAZUH_MANAGER}" /var/ossec/bin/wazuh-logtest
```

Expected: `Rule id: '100814'   Level: '12'`

#### Test rule 100815 (test event):

```bash
TEST_LINE='{"message":"WAZUH_LARAVEL_TEST_EVENT","context":{"ip":"127.0.0.1","note":"test"},"level":200,"level_name":"INFO","channel":"security","datetime":"2026-08-23T00:00:00+07:00","extra":{}}'
echo "${TEST_LINE}" | sudo docker exec -i "${WAZUH_MANAGER}" /var/ossec/bin/wazuh-logtest
```

Expected: `Rule id: '100815'   Level: '5'`

### Test E: Verify the event reaches the Wazuh Indexer

Generate a test event, wait 30–60 seconds, then query the indexer:

```bash
cd ~/caps-test

# Write the test event
php artisan tinker --execute="
\Illuminate\Support\Facades\Log::channel('security')->info('WAZUH_LARAVEL_TEST_EVENT', [
    'ip'   => '127.0.0.1',
    'note' => 'indexer-ingestion-test',
]);
echo 'Written' . PHP_EOL;
"

# Wait for ingestion
sleep 45

# Query the indexer (adjust credentials from .env)
source .env 2>/dev/null || true
curl -sk -u "${WAZUH_INDEXER_USER}:${WAZUH_INDEXER_PASS}" \
  "${WAZUH_INDEXER_URL}/wazuh-alerts-*/_search" \
  -H 'Content-Type: application/json' \
  -d '{
    "size": 5,
    "sort": [{"timestamp": {"order": "desc"}}],
    "query": {
      "match": { "full_log": "WAZUH_LARAVEL_TEST_EVENT" }
    }
  }' | python3 -m json.tool | grep -A5 '"_source"'
```

### Test F: Verify through WazuhService (Laravel tinker)

```bash
cd ~/caps-test
php artisan tinker --execute="
\$result = app(\App\Services\WazuhService::class)->getSecuritySummary(50);
echo 'AVAILABLE: ' . (\$result['available'] ? 'yes' : 'no') . PHP_EOL;
echo 'ALERT_COUNT: ' . count(\$result['alerts']) . PHP_EOL;
foreach (\$result['alerts'] as \$alert) {
    if (
        str_contains(\$alert['rule_description'] ?? '', 'Laravel')
        || str_contains(\$alert['full_log'] ?? '', 'WAZUH_LARAVEL_TEST_EVENT')
    ) {
        echo json_encode(\$alert, JSON_PRETTY_PRINT) . PHP_EOL;
    }
}
"
```

Expected: at least one alert with:
- `"rule_id": "100815"` (or the appropriate matching rule)
- `"rule_description"` containing "KRB"
- `"severity": "low"` (level 5)
- `"full_log"` containing `WAZUH_LARAVEL_TEST_EVENT`

---

## Rollback Instructions

To undo ONLY the changes made in this implementation:

### 1. Restore backed-up Laravel files

Backup timestamp: `20260823-173613`

```bash
cd ~/caps-test

cp config/logging.php.bak-20260823-173613 config/logging.php
cp app/Services/SecurityMonitoringService.php.bak-20260823-173613 app/Services/SecurityMonitoringService.php
cp app/Livewire/Pages/Auth/Login.php.bak-20260823-173613 app/Livewire/Pages/Auth/Login.php
cp app/Listeners/LogFailedLogin.php.bak-20260823-173613 app/Listeners/LogFailedLogin.php
cp wazuh/ossec-localfile-config.xml.bak-20260823-173613 wazuh/ossec-localfile-config.xml

# Remove new files
rm -f app/Logging/SecurityJsonFormatter.php
rm -f wazuh/decoders/krb_laravel_security_decoders.xml
rm -f wazuh/rules/krb_laravel_security_rules.xml

php artisan config:clear
```

### 2. Remove the Wazuh agent localfile entry

Edit `/var/ossec/etc/ossec.conf` and remove the block added in step 9 (the `security*.log` localfile entry). Then:

```bash
sudo systemctl restart wazuh-agent
```

### 3. Remove the Wazuh manager rules/decoder

```bash
WAZUH_MANAGER=$(sudo docker ps --format "{{.Names}}" | grep wazuh.manager | head -1)
sudo docker exec "${WAZUH_MANAGER}" rm -f /var/ossec/etc/rules/krb_laravel_security_rules.xml
sudo docker exec "${WAZUH_MANAGER}" rm -f /var/ossec/etc/decoders/krb_laravel_security_decoders.xml
sudo docker restart "${WAZUH_MANAGER}"
```

### What is NOT affected by rollback

- `wazuh/rules/krb_app_health_rules.xml` — untouched throughout
- `wazuh/decoders/krb_app_health_decoders.xml` — untouched throughout
- `app/Services/WazuhService.php` — untouched throughout
- `app/Livewire/Pages/Manager/AISecurityReports.php` — untouched throughout
- `app/Livewire/Pages/ItOfficer/AISecurityReports.php` — untouched throughout
- All existing Wazuh system-level monitoring (PAM, sudo, journald) — untouched throughout
