# Quick SQL Injection Testing Checklist

## 🎯 Quick Start (5 minutes)

### Step 1: Get Your ngrok URL
```bash
# If ngrok is running, find the URL in terminal output
# Example: https://abc123.ngrok.io
```

### Step 2: Open Browser and Test

#### Test 1: Basic SQL Injection
1. Navigate to your ngrok login page
2. Enter in email field: `admin' OR '1'='1`
3. Enter any password
4. Click Login
5. **Expected Result**: You should see **403 Forbidden** page

✅ If you see 403: **PASS** - Detection working!  
❌ If you login or see different error: **FAIL** - Detection not working!

#### Test 2: Comment-based SQL Injection
1. Enter in email field: `admin'--`
2. Enter any password
3. Click Login
4. **Expected Result**: **403 Forbidden**

#### Test 3: UNION SQL Injection
1. Enter in email field: `admin' UNION SELECT NULL--`
2. Enter any password
3. Click Login
4. **Expected Result**: **403 Forbidden**

---

## 📊 Verify Detection is Logged

### Check Laravel Logs
```bash
# Open command prompt in project directory
cd c:\laragon\www\KRB-System-Caps-main\Capstone-copy

# View recent security events
type storage\logs\laravel.log | findstr /i "SQLI_DETECTED"
```

**Expected Output:**
```
[2026-06-18 12:34:56] local.WARNING: SQLI_DETECTED ' OR 1=1-- {"ip":"x.x.x.x","path":"login","event":"login","field":"email","payload":"admin' OR '1'='1"}
```

### Check Wazuh Dashboard
1. Open Wazuh dashboard (usually http://localhost:5601)
2. Click on **Security Events** or **Threat Hunting**
3. Look for alerts with:
   - **Level**: 12
   - **Description**: Contains "SQLI_DETECTED"
   - **Recent timestamp**

### Check AI Security Reports Page
1. Login to your application as superadmin
2. Navigate to **AI Security Reports** (or the route where AISecurityReports component is mounted)
3. **Expected**: You should see the SQL injection attempts listed
4. Try filtering by severity level

---

## 🚀 Automated Testing (Recommended)

### Using PowerShell Script

```powershell
# Run the automated test script
.\test-sqli-detection.ps1 -BaseUrl "https://your-ngrok-url.ngrok.io"
```

This will automatically test 11 different attack vectors and show results.

---

## 📝 Common Test Payloads (Copy & Paste)

### SQL Injection Payloads
```
admin' OR '1'='1
admin'--
admin' OR 1=1--
admin' UNION SELECT NULL,NULL,NULL--
admin'; DROP TABLE users--
' OR 'a'='a
1' OR '1'='1
```

### XSS Payloads (to test XSS detection)
```
<script>alert('XSS')</script>
<img src=x onerror=alert(1)>
javascript:alert(1)
```

### Command Injection Payloads
```
admin; ls -la
admin | whoami
admin && cat /etc/passwd
```

---

## ✅ Success Criteria

Your security monitoring is working correctly if:

- [ ] All SQL injection attempts return **403 Forbidden**
- [ ] Laravel log contains **SQLI_DETECTED** warnings
- [ ] Wazuh dashboard shows **Level 12** security alerts
- [ ] AI Security Reports page displays the attack attempts
- [ ] Alerts contain correct IP address and timestamp
- [ ] Normal login (with valid credentials) still works

---

## ❌ Troubleshooting

### Problem: I get logged in instead of 403
**Cause**: Middleware not applied or bypassed  
**Fix**: 
1. Check `bootstrap/app.php` or `app/Http/Kernel.php`
2. Ensure `WazuhSecurityMonitor` middleware is registered
3. Clear cache: `php artisan config:clear`

### Problem: 403 appears but no logs
**Cause**: Logging not working or log file not writable  
**Fix**:
1. Check `storage/logs/` permissions
2. Verify `APP_DEBUG=true` in `.env`
3. Clear cache: `php artisan cache:clear`

### Problem: No alerts in Wazuh
**Cause**: Wazuh agent not monitoring Laravel logs  
**Fix**:
1. Check Wazuh agent status
2. Verify log file path in Wazuh config
3. Restart Wazuh agent

### Problem: Can't access ngrok URL
**Cause**: ngrok not running or wrong URL  
**Fix**:
1. Check ngrok terminal for URL
2. Verify application is running: `php artisan serve`
3. Test localhost first: `http://localhost:8000`

---

## 🎓 Understanding the Results

### What happens when you submit SQL injection?

```
1. Browser sends: admin' OR '1'='1
   ↓
2. Laravel receives request
   ↓
3. WazuhSecurityMonitor middleware checks input
   ↓
4. Detects SQL injection pattern
   ↓
5. Logs to storage/logs/laravel.log
   ↓
6. Returns 403 Forbidden (attacker blocked!)
   ↓
7. Wazuh agent reads log file
   ↓
8. Wazuh manager processes alert
   ↓
9. Alert appears in Wazuh dashboard
   ↓
10. AISecurityReports page fetches alerts via WazuhAlertService
   ↓
11. Manager sees the attack attempt
```

### Why test this?

1. **Verify Detection**: Ensure Wazuh correctly identifies threats
2. **Validate Blocking**: Confirm malicious requests are stopped
3. **Test Logging**: Verify security events are properly logged
4. **UI Verification**: Ensure alerts reach the dashboard
5. **Compliance**: Document security monitoring effectiveness

---

## 📸 Screenshot Checklist

For documentation/presentation:

- [ ] Screenshot of 403 Forbidden page after SQL injection
- [ ] Screenshot of Laravel log showing SQLI_DETECTED
- [ ] Screenshot of Wazuh dashboard with security alerts
- [ ] Screenshot of AI Security Reports page showing attacks
- [ ] Screenshot of successful test script output

---

## 🔒 Security Reminder

**ONLY test on systems you own or have permission to test!**

This testing is for:
- ✅ Your development environment
- ✅ Your testing/staging environment
- ✅ Educational purposes with authorization
- ✅ Security assessment of your own application

This testing is NOT for:
- ❌ Production systems (without proper approval)
- ❌ Third-party websites
- ❌ Systems you don't own
- ❌ Malicious purposes

---

## 📞 Need Help?

1. Check the full guide: `docs/PENETRATION_TEST_GUIDE.md`
2. Review Laravel logs: `storage/logs/laravel.log`
3. Check Wazuh agent logs: `/var/ossec/logs/ossec.log`
4. Review middleware code: `app/Http/Middleware/WazuhSecurityMonitor.php`

---

## ⏱️ Quick 2-Minute Test

```bash
# 1. Get your ngrok URL from terminal

# 2. Run PowerShell script
.\test-sqli-detection.ps1 -BaseUrl "https://YOUR-NGROK-URL.ngrok.io"

# 3. Check results immediately
type storage\logs\laravel.log | findstr /i "SQLI_DETECTED"

# Done! ✅
```

---

**Document Version:** 1.0  
**Last Updated:** June 18, 2026  
**Testing Time:** ~5-10 minutes
