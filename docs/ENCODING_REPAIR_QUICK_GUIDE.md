# Guestbook Encoding Repair - Quick Guide

## Quick Reference

### Step 1: Diagnose (Safe - Read Only)

```bash
php artisan guestbook:diagnose-encoding
```

**What it does:**
- Scans guestbook records for mojibake patterns
- Shows affected record IDs
- Estimates total records needing repair
- **Does NOT modify any data**

---

### Step 2: Preview Repairs (Dry Run)

```bash
php artisan guestbook:repair-encoding --dry-run
```

**What it does:**
- Shows exact before/after changes for each record
- Displays which fields will be modified
- **Does NOT save any changes**
- **ALWAYS RUN THIS FIRST**

---

### Step 3: Apply Repairs (After Review)

```bash
# Default: repairs up to 50 records
php artisan guestbook:repair-encoding

# Or specify custom limit
php artisan guestbook:repair-encoding --limit=100
```

**What it does:**
- Applies the repairs shown in dry-run
- Saves changes to database
- Creates a JSON log file in `storage/logs/`
- **Only run after reviewing dry-run output**

---

## Common Scenarios

### Scenario 1: First Time Check

```bash
# 1. Check if mojibake exists
php artisan guestbook:diagnose-encoding

# 2. If mojibake found, preview repairs
php artisan guestbook:repair-encoding --dry-run --limit=10

# 3. If preview looks good, apply to first 10 records
php artisan guestbook:repair-encoding --limit=10

# 4. Verify repairs in UI, then continue
php artisan guestbook:repair-encoding --limit=50
```

---

### Scenario 2: Regular Maintenance Check

```bash
# Monthly check for new mojibake
php artisan guestbook:diagnose-encoding

# If all clear, you're done!
```

---

### Scenario 3: After CSV Import

```bash
# 1. Immediately check imported data
php artisan guestbook:diagnose-encoding

# 2. If issues found, repair
php artisan guestbook:repair-encoding --dry-run
php artisan guestbook:repair-encoding
```

---

## Example Output

### Diagnosis Output

```
Checking database connection encoding...
Charset: utf8mb4
Collation: utf8mb4_unicode_ci

Checking table encoding...
Table Collation: utf8mb4_unicode_ci

Checking sample data for mojibake patterns...

✗ Found 3 records with mojibake in the sample.
ID 123 has mojibake in: name, instansi
  Name: JosÃ© GarcÃ­a
  Instansi: PT. SahabatÂ Sejati

Affected IDs: 123, 145, 167
Total records potentially affected: 15
```

---

### Repair Dry-Run Output

```
Starting Guestbook Encoding Repair...
Mode: DRY RUN (no changes will be saved)

Found 3 records with potential mojibake.

Record ID: 123
  name:
    Before:  JosÃ© GarcÃ­a
    After:   José García
  instansi:
    Before:  PT. SahabatÂ Sejati
    After:   PT. Sahabat Sejati

Record ID: 145
  keperluan:
    Before:  Konsultasi â€" Proyek Baru
    After:   Konsultasi – Proyek Baru

Record ID: 167
  petugas_penjaga:
    Before:  Mariaâ€™s Team
    After:   Maria's Team

═══════════════════════════════════════════
Summary:
  Total checked: 50
  Records repaired: 3

⚠ DRY RUN MODE - No changes were saved to the database.
Run without --dry-run to apply these changes.
```

---

### Actual Repair Output

```
Starting Guestbook Encoding Repair...
Mode: LIVE (changes will be saved)

Found 3 records with potential mojibake.

[Same before/after output as above]

═══════════════════════════════════════════
Summary:
  Total checked: 50
  Records repaired: 3

✓ Changes have been saved to the database.
Repair log saved to: storage/logs/guestbook_encoding_repair_2026-08-10_143052.json
```

---

## Safety Features

✅ **Dry-run mode** - Preview before applying  
✅ **Record limits** - Prevents accidental mass updates  
✅ **Detailed preview** - See exact changes before/after  
✅ **JSON logging** - Audit trail of all repairs  
✅ **Targeted repairs** - Only modifies corrupted fields  
✅ **Non-destructive** - Original data preserved in logs  

---

## What Gets Fixed

Common mojibake patterns that will be repaired:

| Mojibake | Correct | Example |
|----------|---------|---------|
| `Ã©` | `é` | José |
| `Ã±` | `ñ` | España |
| `Ã§` | `ç` | França |
| `Ã­` | `í` | María |
| `â€"` | `–` | en-dash |
| `â€™` | `'` | apostrophe |
| `â€œ` | `"` | opening quote |
| `Â·` | `·` | middle dot |
| `Â ` | ` ` | non-breaking space |

---

## Backup Recommendation

Before running repairs, create a backup:

```bash
# Laravel backup command (if configured)
php artisan db:backup

# Or manual MySQL backup
mysqldump -u username -p database_name > backup_before_repair.sql
```

---

## Verification After Repair

1. **Check the UI:**
   - Navigate to Guestbook Status page
   - Look for previously corrupted names
   - Verify they now display correctly

2. **Check the log:**
   - Open the JSON log file created
   - Review before/after values
   - Confirm changes are correct

3. **Spot check database:**
   ```sql
   SELECT guestbook_id, name, instansi, keperluan 
   FROM guestbooks 
   WHERE guestbook_id IN (123, 145, 167);
   ```

---

## Troubleshooting

### "No records with mojibake detected"

✅ **Good news!** Your data is clean. No action needed.

---

### "Database connection refused"

❌ **Database server is not running.**

**Solution:** Start your database server (MySQL/MariaDB) first.

---

### "Characters still look wrong after repair"

**Possible causes:**
1. Browser cache - Try hard refresh (Ctrl+F5)
2. Triple-encoding - Run repair command again
3. New corruption source - Check data entry forms

---

### "I want to undo the repairs"

**Solution:**
1. Restore from backup (recommended)
2. Or manually reverse using the JSON log file

---

## When to Run

- ✅ After importing CSV data
- ✅ After database migration
- ✅ Monthly as maintenance check
- ✅ When users report "weird characters"
- ✅ After any bulk data operations

---

## Need Help?

See full documentation: `docs/PAGINATION_AND_ENCODING_REPORT.md`

---

**Last Updated:** August 10, 2026
