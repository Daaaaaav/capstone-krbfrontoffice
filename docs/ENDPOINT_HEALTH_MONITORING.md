# KRB System — Endpoint-Based Application Health Monitoring

This document details the endpoint-based application availability and health monitoring architecture, deployment instructions, and Wazuh integration.

## Architecture

Unlike systemd service monitoring (which only checks if a process daemon is running), endpoint-based health monitoring tests actual HTTP response status codes and network latency.

```text
Host / Server Endpoints
├── 1. KRB Laravel Local   : http://127.0.0.1:8000/health  (HTTP 200)
├── 2. LSTM FastAPI Local  : http://127.0.0.1:8001/        (HTTP 200)
└── 3. KRB Public Endpoint : https://receptionistkebunraya.online/health (HTTP 200)
```

## Critical Safety & Isolation Rules

1. **Wazuh Rule 40704 Preserved**:
   - Rule `40704` continues to monitor systemd service failures.
   - `ngrok.service` failure is NOT treated as application failure.
2. **Dedicated Custom Rule Range**:
   - Endpoint health checks use rule IDs `100800`–`100807`.
3. **Zero Credential Exposure**:
   - `/health` endpoints and logs never include database credentials, environment variables, filesystem paths, cookies, or secrets.
4. **Local Port Isolation**:
   - Ports `8000` and `8001` remain bound to `127.0.0.1` locally and are never directly exposed to the public internet.

---

## Wazuh Custom Rules (`100800`–`100807`)

| Rule ID | Severity Level | Event Type | Description |
| :--- | :--- | :--- | :--- |
| **100800** | 3 (Low) | Info | KRB Application health monitoring event recorded. |
| **100801** | 7 (Medium) | Down | KRB Laravel application endpoint is unavailable. |
| **100802** | 7 (Medium) | Down | LSTM Forecast Service endpoint is unavailable. |
| **100803** | 7 (Medium) | Down | KRB public endpoint is unavailable. |
| **100804** | 9 (High) | Outage | Multiple KRB application endpoints unavailable (Degraded or System Outage). |
| **100805** | 3 (Low) | Recovery | KRB Laravel application endpoint recovered. |
| **100806** | 3 (Low) | Recovery | LSTM Forecast Service endpoint recovered. |
| **100807** | 3 (Low) | Recovery | KRB public endpoint recovered. |

---

## Installation & Deployment Instructions on Linux Host

### 1. Install the Health Check Script
```bash
sudo cp scripts/krb-health-check.sh /usr/local/bin/krb-health-check.sh
sudo chmod +x /usr/local/bin/krb-health-check.sh
```

### 2. Configure Systemd Timer (Runs every 30s)
```bash
sudo cp wazuh/systemd/krb-health-check.service /etc/systemd/system/
sudo cp wazuh/systemd/krb-health-check.timer /etc/systemd/system/

sudo systemctl daemon-reload
sudo systemctl enable --now krb-health-check.timer
```

### 3. Deploy Wazuh Rules & Decoders
On the Wazuh Manager / Docker container:
- Copy `wazuh/decoders/krb_app_health_decoders.xml` to `/var/ossec/etc/decoders/local_decoder.xml` (or `/var/ossec/etc/decoders/`).
- Copy `wazuh/rules/krb_app_health_rules.xml` to `/var/ossec/etc/rules/local_rules.xml` (or `/var/ossec/etc/rules/`).
- Add the localfile log collector to `/var/ossec/etc/ossec.conf`:
```xml
<localfile>
  <log_format>json</log_format>
  <location>/var/log/krb-health.log</location>
</localfile>
```
- Validate configuration with `/var/ossec/bin/wazuh-analysisd -t` and restart Wazuh manager:
```bash
sudo systemctl restart wazuh-manager
# or for Docker: docker compose restart wazuh.manager
```

---

## IT Officer Dashboard
The Livewire dashboard at `/it-officer-dashboard` displays:
- Overall status pill (Operational / Degraded / Outage)
- Individual cards for KRB Laravel, LSTM FastAPI, and Public Endpoint
- Latency (ms), last checked timestamp, and HTTP response codes
- Non-blocking 30-second polling (`wire:poll.30s`) with server-side caching to avoid hammering endpoints