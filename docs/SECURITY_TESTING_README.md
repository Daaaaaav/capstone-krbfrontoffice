# Security Testing Documentation

## 📚 Overview

This directory contains comprehensive documentation and tools for testing the Wazuh security monitoring effectiveness in the KRB System, specifically for detecting SQL injection and other common web attacks.

## 🎯 Purpose

Validate that:
1. **SQL injection attempts are detected** and logged
2. **Wazuh monitoring** correctly identifies security threats
3. **Security alerts** appear in the AI Security Reports dashboard
4. **Attack attempts are blocked** before reaching the database

---

## 📁 Available Resources

### Documentation

| File | Description | Best For |
|------|-------------|----------|
| **PENETRATION_TEST_GUIDE.md** | Complete testing guide with detailed explanations | Comprehensive understanding and documentation |
| **QUICK_TEST_CHECKLIST.md** | Fast 5-minute testing checklist | Quick verification during development |
| **manual-test-page.html** | Interactive browser-based testing tool | Visual testing and demonstrations |

### Testing Scripts

| File | Language | Platform | Best For |
|------|----------|----------|----------|
| **test-sqli-detection.ps1** | PowerShell | Windows | Automated testing on Windows |
| **test_sqli_detection.py** | Python | Cross-platform | Automated testing on any platform |

---

## 🚀 Quick Start (Choose One Method)

### Method 1: PowerShell Script (Windows - Recommended)

```powershell
# From project root directory
cd c:\laragon\www\KRB-System-Caps-main\Capstone-copy

# Run test against ngrok URL
.\test-sqli-detection.ps1 -BaseUrl "https://your-ngrok-url.ngrok.io"

# Or test against localhost
.\test-sqli-detection.ps1 -BaseUrl "http://localhost:8000"
```

**Output Example:**
```
==========================================
 SQL Injection Detection Test
==========================================

Testing: 1. Classic OR-based SQLi
  ✅ PASSED: Blocked with 403 Forbidden

Testing: 2. Comment-based SQLi
  ✅ PASSED: Blocked with 403 Forbidden
...

Total Tests: 11
Passed: 11
Failed: 0
```

### Method 2: Python Script (Cross-platform)

```bash
# Install dependencies
pip install requests

# Run test
python test_sqli_detection.py https://your-ngrok-url.ngrok.io

# Or use default localhost
python test_sqli_detection.py
```

### Method 3: Manual Browser Testing

1. Open `docs/manual-test-page.html` in your browser
2. Enter your ngrok URL
3. Click payload buttons to test
4. View results instantly

**Note:** Browser testing may encounter CORS issues. Use scripts for accurate results.

### Method 4: Manual Form Testing

1. Navigate to your login page: `https://your-ngrok-url.ngrok.io/login`
2. Enter in email field: `admin' OR '1'='1`
3. Enter any password
4. Click Login
5. **Expected**: 403 Forbidden page

---

## ✅ Success Criteria

Your security monitoring is working if ALL of these are true:

- [ ] **Browser shows 403 Forbidden** when submitting SQL injection
- [ ] **Laravel log contains SQLI_DETECTED** warnings
- [ ] **Wazuh dashboard shows Level 12 alerts**
- [ ] **AI Security Reports page** displays the attack attempts
- [ ] **Normal login still works** with valid credentials

---

## 🔍 Verification Steps

### 1. Check Laravel Logs

```bash
# View recent security detections
type storage\logs\laravel.log | findstr /i "SQLI_DETECTED"

# View last 20 lines of log
powershell "Get-Content storage\logs\laravel.log -Tail 20"
```

**Expected output:**
```
[2026-06-18 12:34:56] local.WARNING: SQLI_DETECTED ' OR 1=1-- 
{"ip":"192.168.1.100","path":"login","event":"login","field":"email","payload":"admin' OR '1'='1"}
```

### 2. Check Wazuh Dashboard

1. Open Wazuh dashboard (usually `http://localhost:5601` or your Wazuh manager URL)
2. Navigate to **Security Events** or **Threat Hunting**
3. Filter by:
   - **Level**: 12 or higher
   - **Search**: "SQLI_DETECTED"
   - **Time**: Last 15 minutes

### 3. Check AI Security Reports Page

1. Login to application as **superadmin**
2. Navigate to **AI Security Reports** (from sidebar)
3. Verify alerts are displayed with:
   - IP address
   - Timestamp
   - Attack type (SQL Injection)
   - Severity level
4. Test severity filtering

---

## 🧪 Test Payloads Reference

### SQL Injection Payloads

```sql
-- Classic OR-based
admin' OR '1'='1

-- Comment-based
admin'--
admin'#

-- UNION-based
admin' UNION SELECT NULL,NULL,NULL--

-- Boolean-based
admin' OR 1=1--
admin' AND 1=1--

-- Stacked queries
admin'; DROP TABLE users--
admin'; UPDATE users SET role='admin'--

-- Time-based blind
admin' AND SLEEP(5)--

-- String concatenation
admin' || '1'='1
```

### XSS Payloads (Bonus Testing)

```html
<script>alert('XSS')</script>
<img src=x onerror=alert(1)>
<svg onload=alert(1)>
javascript:alert(document.cookie)
```

### Command Injection Payloads

```bash
admin; ls -la
admin | whoami
admin && cat /etc/passwd
admin`id`
```

---

## 📊 Understanding the Results

### When You Submit SQL Injection:

```
1. Browser sends: admin' OR '1'='1
   ↓
2. Laravel receives request
   ↓
3. WazuhSecurityMonitor middleware intercepts
   ↓
4. Pattern detection: MATCH! (SQL injection detected)
   ↓
5. Log to storage/logs/laravel.log with level 12
   ↓
6. Return 403 Forbidden to client (ATTACK BLOCKED!)
   ↓
7. Wazuh agent reads Laravel log
   ↓
8. Wazuh manager creates security alert
   ↓
9. Alert visible in Wazuh dashboard
   ↓
10. AISecurityReports fetches via WazuhAlertService
   ↓
11. Manager sees attack in real-time
```

---

## 🔧 Troubleshooting

### Problem: Tests fail with connection error

**Symptoms:**
```
⚠️  CONNECTION ERROR: Connection refused
```

**Solutions:**
1. Verify application is running:
   ```bash
   php artisan serve
   ```
2. Check ngrok is running and URL is correct
3. Test localhost first: `http://localhost:8000`

### Problem: Getting 200 OK instead of 403

**Symptoms:**
```
❌ FAILED: Expected 403, got 200
```

**Cause:** Middleware not active or bypassed

**Solutions:**
1. Check middleware is registered in `bootstrap/app.php` or `app/Http/Kernel.php`
2. Verify route has middleware applied:
   ```php
   Route::post('/login', LoginController::class)
       ->middleware(WazuhSecurityMonitor::class);
   ```
3. Clear config cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

### Problem: 403 appears but no logs

**Symptoms:**
- Attack blocked correctly
- No entries in `storage/logs/laravel.log`

**Solutions:**
1. Check file permissions:
   ```bash
   icacls storage\logs /grant Everyone:(OI)(CI)F /T
   ```
2. Verify `APP_DEBUG=true` in `.env`
3. Check log channel in `config/logging.php`
4. Try manual log test:
   ```php
   Log::info('TEST LOG');
   ```

### Problem: No Wazuh alerts

**Symptoms:**
- Laravel logs show SQLI_DETECTED
- Wazuh dashboard shows no alerts

**Solutions:**
1. Check Wazuh agent status:
   ```bash
   # Linux
   systemctl status wazuh-agent
   
   # Windows
   Get-Service WazuhSvc
   ```
2. Verify log monitoring in Wazuh `ossec.conf`:
   ```xml
   <localfile>
     <log_format>syslog</log_format>
     <location>C:\laragon\www\KRB-System-Caps-main\Capstone-copy\storage\logs\laravel.log</location>
   </localfile>
   ```
3. Check Wazuh agent logs:
   ```bash
   # Linux
   tail -f /var/ossec/logs/ossec.log
   
   # Windows
   type "C:\Program Files (x86)\ossec-agent\ossec.log"
   ```
4. Restart Wazuh agent:
   ```bash
   # Linux
   systemctl restart wazuh-agent
   
   # Windows
   Restart-Service WazuhSvc
   ```

### Problem: AI Security Reports page empty

**Symptoms:**
- Wazuh dashboard shows alerts
- AI Security Reports page shows no data

**Solutions:**
1. Check `WazuhAlertService` configuration
2. Verify API endpoints in service
3. Check browser console for errors (F12)
4. Verify manager role/permissions
5. Check Livewire component mounting

---

## 📈 Testing Best Practices

### DO:
- ✅ Test on development/staging environments
- ✅ Document all test results
- ✅ Verify logs after each test
- ✅ Test normal functionality still works
- ✅ Clear test data after testing
- ✅ Get proper authorization before testing

### DON'T:
- ❌ Test on production without approval
- ❌ Test third-party systems
- ❌ Use real user credentials in payloads
- ❌ Assume blocking = detection (always verify logs)
- ❌ Skip verification steps
- ❌ Test without documentation

---

## 📝 Test Report Template

Use this template to document your testing:

```markdown
# Security Monitoring Test Report

**Date:** [Date]
**Tester:** [Your Name]
**Environment:** [Dev/Staging/Test]
**Application URL:** [URL]

## Test Summary
- Total Tests: X
- Passed: X
- Failed: X
- Success Rate: X%

## SQL Injection Tests
| Payload | Expected | Actual | Status |
|---------|----------|--------|--------|
| admin' OR '1'='1 | 403 | 403 | ✅ Pass |
| admin'-- | 403 | 403 | ✅ Pass |

## Verification
- [x] Laravel logs confirmed
- [x] Wazuh alerts confirmed
- [x] Dashboard displays correctly
- [x] Normal login works

## Issues Found
[List any problems]

## Recommendations
[Suggestions for improvement]

## Conclusion
[Overall assessment]
```

---

## 🔐 Security Reminders

1. **Authorization Required**: Only test systems you own or have permission to test
2. **Document Everything**: Keep records of all tests
3. **Responsible Disclosure**: Report vulnerabilities properly
4. **No Real Data**: Don't use actual user data in tests
5. **Cleanup**: Remove test artifacts after testing
6. **Compliance**: Follow security testing policies

---

## 📚 Additional Resources

### Internal Documentation
- `PENETRATION_TEST_GUIDE.md` - Comprehensive testing guide
- `QUICK_TEST_CHECKLIST.md` - Quick reference
- `../app/Http/Middleware/WazuhSecurityMonitor.php` - Middleware source
- `../app/Services/SecurityMonitoringService.php` - Security service

### External Resources
- [OWASP SQL Injection](https://owasp.org/www-community/attacks/SQL_Injection)
- [Wazuh Documentation](https://documentation.wazuh.com/)
- [Laravel Security](https://laravel.com/docs/security)
- [OWASP Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)

---

## 🤝 Support

If you encounter issues:

1. Check troubleshooting section above
2. Review Laravel logs: `storage/logs/laravel.log`
3. Check Wazuh agent logs
4. Review middleware and service code
5. Verify configuration files

---

## 📊 Performance Metrics

Expected performance:
- **Detection Rate**: 100% for common SQL injection patterns
- **False Positive Rate**: <1% for legitimate inputs
- **Response Time**: <100ms overhead
- **Log Write Time**: <50ms

---

## 🔄 Continuous Testing

Integrate security testing into your workflow:

```bash
# Run tests after deployment
.\test-sqli-detection.ps1 -BaseUrl "https://staging.yourdomain.com"

# Schedule regular tests (Task Scheduler or cron)
# Daily at 2 AM
schtasks /create /tn "SecurityTest" /tr "powershell.exe -File C:\path\to\test-sqli-detection.ps1" /sc daily /st 02:00
```

---

**Document Version:** 1.0  
**Last Updated:** June 18, 2026  
**Maintained By:** Security Team  
**Review Cycle:** Monthly
