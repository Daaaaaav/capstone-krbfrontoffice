# SQL Injection Penetration Testing Guide

## Purpose
This document provides authorized test cases to verify Wazuh monitoring effectiveness in detecting and logging SQL injection attempts against the KRB System.

## ⚠️ AUTHORIZATION NOTICE
This testing should ONLY be performed:
- On your own development/testing environment
- With proper authorization and documentation
- Never on production systems without explicit approval
- As part of security assessment and monitoring validation

## Prerequisites
1. Wazuh agent installed and configured
2. Application running on ngrok (or local environment)
3. Access to Wazuh dashboard to view alerts
4. Access to Laravel logs (`storage/logs/laravel.log`)

---

## Test Case 1: Login Form SQL Injection Attempts

### Objective
Verify that Wazuh detects SQL injection patterns in login form submissions.

### Test Vectors

#### Vector 1.1: Classic OR-based SQLi
```
Email: admin' OR '1'='1
Password: password
```

#### Vector 1.2: Comment-based SQLi
```
Email: admin'--
Password: [anything]
```

#### Vector 1.3: UNION-based SQLi
```
Email: admin' UNION SELECT NULL,NULL,NULL--
Password: password
```

#### Vector 1.4: Boolean-based SQLi
```
Email: admin' OR 1=1--
Password: password
```

#### Vector 1.5: Time-based SQLi
```
Email: admin' AND SLEEP(5)--
Password: password
```

### Expected Results
1. **Application Response**: 403 Forbidden (blocked by WazuhSecurityMonitor middleware)
2. **Laravel Log Entry**: `SQLI_DETECTED` warning with IP and payload details
3. **Wazuh Alert**: Level 12 security alert with `SQLI_DETECTED` message

### Testing Procedure

#### Method 1: Browser Testing
1. Open your ngrok URL in browser
2. Navigate to login page
3. Enter test vectors above
4. Click login button
5. Verify 403 error appears

#### Method 2: cURL Testing
```bash
curl -X POST "YOUR_NGROK_URL/login" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "email=admin'+OR+'1'='1&password=test123"
```

#### Method 3: Postman/Insomnia Testing
```
POST YOUR_NGROK_URL/login
Content-Type: application/x-www-form-urlencoded

email=admin' OR '1'='1&password=password
```

---

## Test Case 2: Livewire Component SQL Injection

### Objective
Test SQL injection detection in Livewire AJAX requests.

### Test Vectors

#### Vector 2.1: Search Field SQLi
If your application has search functionality, test with:
```
search_term: ' OR 1=1--
search_term: '; DROP TABLE users--
search_term: ' UNION SELECT username, password FROM users--
```

### cURL Example for Livewire
```bash
curl -X POST "YOUR_NGROK_URL/livewire/message/your-component" \
  -H "Content-Type: application/json" \
  -H "X-Livewire: true" \
  -d '{
    "fingerprint": {
      "id": "component-id",
      "name": "component-name",
      "locale": "en",
      "path": "/",
      "method": "GET"
    },
    "serverMemo": {},
    "updates": [
      {
        "type": "callMethod",
        "payload": {
          "method": "search",
          "params": ["' OR 1=1--"]
        }
      }
    ]
  }'
```

---

## Test Case 3: XSS Detection (Bonus)

### Test Vectors
```
Email: <script>alert('XSS')</script>
Email: <img src=x onerror=alert(1)>
Email: javascript:alert(document.cookie)
```

### Expected Results
- 403 Forbidden
- `XSS_DETECTED` in Laravel logs
- Wazuh alert for XSS attempt

---

## Test Case 4: Command Injection Detection

### Test Vectors
```
Email: admin; ls -la
Email: admin | whoami
Email: admin`cat /etc/passwd`
```

### Expected Results
- 403 Forbidden
- `COMMAND_INJECTION` in Laravel logs
- Wazuh alert for command injection

---

## Verification Steps

### 1. Check Laravel Logs
```bash
cd c:\laragon\www\KRB-System-Caps-main\Capstone-copy
type storage\logs\laravel.log | findstr /i "SQLI_DETECTED"
```

Look for entries like:
```
[timestamp] local.WARNING: SQLI_DETECTED ' OR 1=1-- {"ip":"x.x.x.x","path":"login","event":"login","field":"email","payload":"admin' OR '1'='1"}
```

### 2. Check Wazuh Dashboard
1. Open Wazuh dashboard
2. Navigate to **Security Events** or **Alerts**
3. Filter by:
   - **Rule level**: 12 or higher
   - **Search**: "SQLI_DETECTED"
   - **Time range**: Last 15 minutes

### 3. Check AI Security Reports Page
1. Login as manager
2. Navigate to **AI Security Reports** page
3. Verify alerts appear in the dashboard
4. Check severity filtering works correctly

---

## Monitoring Detection Patterns

The system detects the following patterns:

### SQL Injection Patterns
- `OR 1=1`, `AND 1=1` (boolean-based)
- `UNION SELECT` (union-based)
- `DROP TABLE`, `DELETE FROM`, `INSERT INTO`
- `--`, `#`, `/*` (SQL comments)
- `SELECT ... FROM` patterns

### XSS Patterns
- `<script>` tags
- `javascript:` protocol
- Event handlers: `onerror=`, `onload=`
- Suspicious HTML tags with events

### Command Injection Patterns
- Shell operators: `|`, `;`, `&`, `||`
- Common commands: `ls`, `cat`, `whoami`, `wget`, `curl`

---

## Test Execution Checklist

- [ ] Wazuh agent is running
- [ ] Application is accessible via ngrok
- [ ] Laravel logs are writable (`storage/logs/`)
- [ ] Baseline test: Normal login works
- [ ] Test Case 1: SQL injection in login form
- [ ] Test Case 2: SQL injection in Livewire components
- [ ] Test Case 3: XSS detection
- [ ] Test Case 4: Command injection detection
- [ ] Verify Laravel logs contain security events
- [ ] Verify Wazuh dashboard shows alerts
- [ ] Verify AI Security Reports page displays alerts
- [ ] Document any false positives or missed detections

---

## Automated Testing Script (PowerShell)

Save as `test-security-monitoring.ps1`:

```powershell
# Security Monitoring Penetration Test Script
param(
    [Parameter(Mandatory=$true)]
    [string]$BaseUrl
)

Write-Host "==================================" -ForegroundColor Cyan
Write-Host "SQL Injection Detection Test" -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan
Write-Host ""

$testCases = @(
    @{name="Classic OR-based SQLi"; email="admin' OR '1'='1"; password="test"},
    @{name="Comment-based SQLi"; email="admin'--"; password="test"},
    @{name="UNION-based SQLi"; email="admin' UNION SELECT NULL--"; password="test"},
    @{name="Boolean SQLi"; email="admin' OR 1=1--"; password="test"},
    @{name="DROP TABLE attempt"; email="admin'; DROP TABLE users--"; password="test"}
)

foreach ($test in $testCases) {
    Write-Host "Testing: $($test.name)" -ForegroundColor Yellow
    
    $body = @{
        email = $test.email
        password = $test.password
    }
    
    try {
        $response = Invoke-WebRequest -Uri "$BaseUrl/login" `
            -Method POST `
            -Body $body `
            -ContentType "application/x-www-form-urlencoded" `
            -UseBasicParsing `
            -ErrorAction Stop
        
        Write-Host "  ❌ FAILED: Expected 403, got $($response.StatusCode)" -ForegroundColor Red
    } catch {
        if ($_.Exception.Response.StatusCode -eq 403) {
            Write-Host "  ✅ PASSED: Blocked with 403 Forbidden" -ForegroundColor Green
        } else {
            Write-Host "  ⚠️  UNEXPECTED: $($_.Exception.Message)" -ForegroundColor Yellow
        }
    }
    
    Start-Sleep -Milliseconds 500
}

Write-Host ""
Write-Host "==================================" -ForegroundColor Cyan
Write-Host "Test completed. Check:" -ForegroundColor Cyan
Write-Host "1. storage\logs\laravel.log for SQLI_DETECTED" -ForegroundColor White
Write-Host "2. Wazuh dashboard for security alerts" -ForegroundColor White
Write-Host "3. AI Security Reports page" -ForegroundColor White
Write-Host "==================================" -ForegroundColor Cyan
```

### Usage
```powershell
.\test-security-monitoring.ps1 -BaseUrl "https://your-ngrok-url.ngrok.io"
```

---

## Python Testing Script (Alternative)

Save as `test_security_monitoring.py`:

```python
#!/usr/bin/env python3
import requests
import sys
from colorama import init, Fore, Style

init()

def test_sqli_detection(base_url):
    print(f"{Fore.CYAN}{'='*50}")
    print("SQL Injection Detection Test")
    print(f"{'='*50}{Style.RESET_ALL}\n")
    
    test_cases = [
        {"name": "Classic OR-based SQLi", "email": "admin' OR '1'='1", "password": "test"},
        {"name": "Comment-based SQLi", "email": "admin'--", "password": "test"},
        {"name": "UNION-based SQLi", "email": "admin' UNION SELECT NULL--", "password": "test"},
        {"name": "Boolean SQLi", "email": "admin' OR 1=1--", "password": "test"},
        {"name": "DROP TABLE attempt", "email": "admin'; DROP TABLE users--", "password": "test"},
    ]
    
    for test in test_cases:
        print(f"{Fore.YELLOW}Testing: {test['name']}{Style.RESET_ALL}")
        
        try:
            response = requests.post(
                f"{base_url}/login",
                data={
                    "email": test["email"],
                    "password": test["password"]
                },
                allow_redirects=False,
                timeout=10
            )
            
            if response.status_code == 403:
                print(f"  {Fore.GREEN}✅ PASSED: Blocked with 403 Forbidden{Style.RESET_ALL}")
            else:
                print(f"  {Fore.RED}❌ FAILED: Expected 403, got {response.status_code}{Style.RESET_ALL}")
                
        except requests.exceptions.RequestException as e:
            print(f"  {Fore.YELLOW}⚠️  ERROR: {str(e)}{Style.RESET_ALL}")
    
    print(f"\n{Fore.CYAN}{'='*50}")
    print("Test completed. Check:")
    print("1. storage/logs/laravel.log for SQLI_DETECTED")
    print("2. Wazuh dashboard for security alerts")
    print("3. AI Security Reports page")
    print(f"{'='*50}{Style.RESET_ALL}")

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python test_security_monitoring.py <base_url>")
        print("Example: python test_security_monitoring.py https://your-ngrok-url.ngrok.io")
        sys.exit(1)
    
    base_url = sys.argv[1].rstrip('/')
    test_sqli_detection(base_url)
```

### Usage
```bash
pip install requests colorama
python test_security_monitoring.py https://your-ngrok-url.ngrok.io
```

---

## Expected Monitoring Flow

```
1. Attacker sends SQLi payload
   ↓
2. WazuhSecurityMonitor middleware intercepts
   ↓
3. Detects malicious pattern
   ↓
4. Logs to Laravel log with level 12
   ↓
5. Returns 403 Forbidden to attacker
   ↓
6. Wazuh agent reads log file
   ↓
7. Wazuh manager processes alert
   ↓
8. Alert appears in Wazuh dashboard
   ↓
9. AISecurityReports page fetches and displays alert
```

---

## Troubleshooting

### Issue: No alerts appearing in Wazuh
**Solutions:**
- Check Wazuh agent status: `systemctl status wazuh-agent`
- Verify log file monitoring in `ossec.conf`
- Check Wazuh manager connectivity
- Review Wazuh agent logs: `/var/ossec/logs/ossec.log`

### Issue: Laravel not logging security events
**Solutions:**
- Check log permissions: `storage/logs/` should be writable
- Verify `.env` has `APP_DEBUG=true` for testing
- Check middleware is registered in `bootstrap/app.php` or `Kernel.php`
- Clear config cache: `php artisan config:clear`

### Issue: 403 not appearing
**Solutions:**
- Verify WazuhSecurityMonitor is in middleware stack
- Check route middleware assignments
- Review middleware priority order
- Test with simple payload first: `' OR 1=1`

---

## Security Testing Best Practices

1. **Document Everything**: Keep records of all tests performed
2. **Test Incrementally**: Start with obvious patterns, then try obfuscation
3. **Monitor Logs in Real-time**: Use `tail -f` to watch logs during testing
4. **Verify Detection**: Don't assume - check logs and alerts for each test
5. **Test False Positives**: Use legitimate inputs with special characters
6. **Cleanup**: Clear test data after testing
7. **Report Findings**: Document what works and what doesn't

---

## Sample Test Report Template

```
# Security Monitoring Penetration Test Report

**Date:** [Date]
**Tester:** [Your Name]
**Environment:** [Development/Staging/Testing]
**Application URL:** [ngrok URL]

## Executive Summary
[Brief overview of testing performed and results]

## Test Results

### SQL Injection Detection
| Test Case | Payload | Expected | Actual | Status |
|-----------|---------|----------|--------|--------|
| OR-based SQLi | admin' OR '1'='1 | Blocked | 403 | ✅ Pass |
| Comment SQLi | admin'-- | Blocked | 403 | ✅ Pass |
| UNION SQLi | UNION SELECT | Blocked | 403 | ✅ Pass |

### Wazuh Alert Verification
- [ ] Alerts appeared in Wazuh dashboard
- [ ] Alert severity correctly set (Level 12)
- [ ] Source IP correctly logged
- [ ] Timestamps accurate

### AISecurityReports Page
- [ ] Alerts displayed correctly
- [ ] Severity filtering works
- [ ] Auto-refresh functionality works
- [ ] Alert details complete

## Issues Found
[List any problems or false negatives]

## Recommendations
[Suggested improvements]

## Conclusion
[Overall assessment of security monitoring effectiveness]
```

---

## Legal and Ethical Considerations

**Remember:**
- Only test systems you own or have written permission to test
- Document authorization before testing
- Do not test production systems without proper approval and change control
- Follow responsible disclosure if vulnerabilities are found
- Keep test data confidential
- Do not exfiltrate or modify real data during testing

---

## Additional Resources

- [OWASP SQL Injection Guide](https://owasp.org/www-community/attacks/SQL_Injection)
- [Wazuh Documentation](https://documentation.wazuh.com/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [OWASP Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)

---

**Document Version:** 1.0  
**Last Updated:** June 18, 2026  
**Maintained By:** Security Team
