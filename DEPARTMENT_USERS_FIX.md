# Department Users Detection Bug Fix

## Problem Description

On the IT Officer → Manage Users per Department page, department accordions were incorrectly showing "0 users" and "No users found in this department" even though users existed in those departments.

For example:
- **Finance** department showed "0 users"
- But users like "Admin Finance", "Agus Kusuma", "Dedi Lestari", etc. existed with `department_id` pointing to Finance

## Root Cause

The `Department` model's `users()` relationship was incorrectly configured:

```php
// BEFORE (BROKEN)
public function users(): HasMany
{
    return $this->hasMany(User::class, 'department_id');
}
```

The problem: The Department model uses `'department_id'` as its primary key (not `'id'`). When you specify only the foreign key in `hasMany()`, Laravel defaults the local key to `'id'`.

This meant the relationship was generating SQL like:
```sql
SELECT * FROM users WHERE users.department_id = departments.id
```

But `departments.id` doesn't exist! It should be:
```sql
SELECT * FROM users WHERE users.department_id = departments.department_id
```

## Solution

Added the third parameter (local key) to the `hasMany()` relationship:

```php
// AFTER (FIXED)
public function users(): HasMany
{
    return $this->hasMany(User::class, 'department_id', 'department_id');
}
```

This explicitly tells Laravel to use `department_id` as the local key on the departments table.

## Files Changed

- `app/Models/Department.php` - Fixed the `users()` relationship

## Verification

The fix ensures that:

1. **Department user counts are accurate**
   ```php
   Department::withCount('users')->get()
   // Now returns correct counts
   ```

2. **Department user queries work correctly**
   ```php
   $department->users()->count()
   // Now returns actual user count instead of 0
   ```

3. **Role-filtered queries work**
   ```php
   $department->users()
       ->whereHas('role', function ($q) {
           $q->whereIn('roles.name', ['Manager', 'Receptionist']);
       })
       ->get()
   // Now returns the correct users
   ```

## Impact Analysis

✅ **No Breaking Changes**: The fix only affects the `Department::users()` relationship which was previously broken. 

✅ **No Other Code Uses This Relationship**: Searched the entire codebase - only `UsersPerDepartment.php` uses this relationship.

✅ **Other Models Are Correct**: Verified that `Company`, `Role`, and `Room` models already have correct `hasMany` configurations with all three parameters specified.

## Testing

To verify the fix works:

1. Run the test script:
   ```bash
   php test_department_users.php
   ```

2. Or manually test in the application:
   - Navigate to IT Officer → Manage Users per Department
   - Verify each department shows the correct user count
   - Expand each department to see the users listed
   - Confirm users match their assigned departments

## Related Database Structure

```sql
-- departments table
department_id (PRIMARY KEY)
company_id
department_name

-- users table  
user_id (PRIMARY KEY)
company_id
department_id (FOREIGN KEY → departments.department_id)
role_id
full_name
email
...
```

## Laravel Relationship Signatures

For reference:
```php
hasMany($related, $foreignKey = null, $localKey = null)
```

When the parent model uses a custom primary key (not 'id'), you **must** specify the third parameter.

## Date Fixed

2026-08-08
