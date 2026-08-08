# Priority Booking 3-Hour Cancellation Test Plan

## Implementation Summary

The 3-hour cancellation validation has been implemented for both Priority Room and Vehicle Bookings with the following key features:

### Changes Made

1. **Priority Room Booking Component** (`app/Livewire/Pages/Manager/PriorityRoomBooking.php`)
   - Added timezone-aware 3-hour validation in `cancelBooking()` method
   - Uses `date` + `start_time` fields to calculate scheduled start
   - Validates against `Asia/Jakarta` timezone
   - Sends receptionist notification on successful cancellation
   - Uses DB transaction for atomic operation

2. **Priority Vehicle Booking Component** (`app/Livewire/Pages/Manager/PriorityVehicleBooking.php`)
   - Added timezone-aware 3-hour validation in `cancelBooking()` method
   - Uses `start_at` datetime field for scheduled start
   - Validates against `Asia/Jakarta` timezone
   - Sends receptionist notification with vehicle details on successful cancellation
   - Uses DB transaction for atomic operation

3. **Translation Files**
   - Added `error`, `priority_booking_cancel_min_3_hours`, and `invalid_booking_time` keys
   - Both English (`lang/en/app.php`) and Indonesian (`lang/id/app.php`) translations

4. **Notifications**
   - Receptionists are notified when Priority Bookings are created (already existed)
   - Receptionists are now notified when Priority Bookings are successfully cancelled (new)
   - Uses existing `ManagerNotification::notifyReceptionists()` with `TYPE_PRIORITY_ROOM_DIRECT` and `TYPE_PRIORITY_VEHICLE_DIRECT`

---

## Test Scenarios

### Priority Room Booking Tests

#### Test 1: Cancellation MORE than 3 hours before start
**Setup:**
- Create Priority Room Booking scheduled for today at 17:00
- Current time: 13:00 (4 hours before)

**Expected Result:**
- ✅ Cancellation should succeed
- ✅ Booking status changed to 'rejected'
- ✅ Rejection reason: "Cancelled by manager."
- ✅ Receptionist notification sent with TYPE_PRIORITY_ROOM_DIRECT
- ✅ Toast message: "Priority booking cancelled."

#### Test 2: Cancellation EXACTLY 3 hours before start
**Setup:**
- Create Priority Room Booking scheduled for today at 17:00
- Current time: 14:00 (exactly 3 hours before)

**Expected Result:**
- ✅ Cancellation should succeed (>= 3 hours is allowed)
- ✅ Booking status changed to 'rejected'
- ✅ Receptionist notification sent
- ✅ Toast message: "Priority booking cancelled."

#### Test 3: Cancellation LESS than 3 hours before start
**Setup:**
- Create Priority Room Booking scheduled for today at 17:00
- Current time: 14:30 (2.5 hours before)

**Expected Result:**
- ❌ Cancellation should be rejected
- ❌ Booking status unchanged (still actionable)
- ❌ NO receptionist notification sent
- ⚠️ Error toast: "Priority bookings must be cancelled at least 3 hours before the scheduled start time."

#### Test 4: Cancellation AFTER start time
**Setup:**
- Create Priority Room Booking scheduled for today at 14:00
- Current time: 15:00 (1 hour after start)

**Expected Result:**
- ❌ Cancellation should be rejected (negative hours)
- ❌ Booking status unchanged
- ❌ NO receptionist notification sent
- ⚠️ Error toast: "Priority bookings must be cancelled at least 3 hours before the scheduled start time."

#### Test 5: Cancellation of already completed booking
**Setup:**
- Priority Room Booking with status 'completed'

**Expected Result:**
- ❌ Cancellation should be rejected (not actionable)
- ❌ No error toast (fails isActionable() check silently)
- ❌ No receptionist notification

#### Test 6: Cancellation of already cancelled booking
**Setup:**
- Priority Room Booking with status 'rejected'

**Expected Result:**
- ❌ Cancellation should be rejected (not actionable)
- ❌ No error toast (fails isActionable() check silently)
- ❌ No receptionist notification

---

### Priority Vehicle Booking Tests

#### Test 7: Cancellation MORE than 3 hours before start
**Setup:**
- Create Priority Vehicle Booking with start_at: tomorrow at 09:00
- Current time: today at 17:00 (16 hours before)

**Expected Result:**
- ✅ Cancellation should succeed
- ✅ Booking status changed to 'rejected'
- ✅ Rejection reason: "Cancelled by manager."
- ✅ Receptionist notification sent with TYPE_PRIORITY_VEHICLE_DIRECT and vehicle details
- ✅ Toast message: "Priority booking cancelled."

#### Test 8: Cancellation EXACTLY 3 hours before start
**Setup:**
- Create Priority Vehicle Booking with start_at: today at 18:00
- Current time: today at 15:00 (exactly 3 hours before)

**Expected Result:**
- ✅ Cancellation should succeed (>= 3 hours is allowed)
- ✅ Booking status changed to 'rejected'
- ✅ Receptionist notification sent
- ✅ Toast message: "Priority booking cancelled."

#### Test 9: Cancellation LESS than 3 hours before start
**Setup:**
- Create Priority Vehicle Booking with start_at: today at 16:00
- Current time: today at 14:00 (2 hours before)

**Expected Result:**
- ❌ Cancellation should be rejected
- ❌ Booking status unchanged (still actionable)
- ❌ NO receptionist notification sent
- ⚠️ Error toast: "Priority bookings must be cancelled at least 3 hours before the scheduled start time."

#### Test 10: Cancellation AFTER start time
**Setup:**
- Create Priority Vehicle Booking with start_at: today at 10:00
- Current time: today at 14:00 (4 hours after start)

**Expected Result:**
- ❌ Cancellation should be rejected (negative hours)
- ❌ Booking status unchanged
- ❌ NO receptionist notification sent
- ⚠️ Error toast: "Priority bookings must be cancelled at least 3 hours before the scheduled start time."

#### Test 11: Cancellation of already rejected booking
**Setup:**
- Priority Vehicle Booking with status 'rejected'

**Expected Result:**
- ❌ Cancellation should be rejected (not actionable)
- ❌ No error toast (fails isActionable() check silently)
- ❌ No receptionist notification

---

### Notification Tests

#### Test 12: Receptionist receives creation notification
**Setup:**
- Manager creates new Priority Room Booking (no conflict)
- Receptionist account exists

**Expected Result:**
- ✅ Receptionist receives notification with TYPE_PRIORITY_ROOM_DIRECT
- ✅ Notification title: "Priority Room Booking"
- ✅ Notification message includes manager name, meeting title, and date
- ✅ action_required: false

#### Test 13: Receptionist receives creation notification for vehicle
**Setup:**
- Manager creates new Priority Vehicle Booking (no conflict)
- Receptionist account exists

**Expected Result:**
- ✅ Receptionist receives notification with TYPE_PRIORITY_VEHICLE_DIRECT
- ✅ Notification title: "Priority Vehicle Booking"
- ✅ Notification message includes manager name, vehicle info, and date
- ✅ action_required: false

#### Test 14: Receptionist receives cancellation notification
**Setup:**
- Manager successfully cancels Priority Room Booking (>3 hours before)
- Receptionist account exists

**Expected Result:**
- ✅ Receptionist receives notification with TYPE_PRIORITY_ROOM_DIRECT
- ✅ Notification title: "Priority Room Booking Cancelled"
- ✅ Notification message includes manager name, meeting title, and scheduled time
- ✅ action_required: false

#### Test 15: Receptionist does NOT receive notification on failed cancellation
**Setup:**
- Manager attempts to cancel Priority Room Booking (<3 hours before)
- Cancellation is rejected

**Expected Result:**
- ❌ NO notification sent to receptionist
- ❌ No database record created in manager_notifications table

---

### Edge Case Tests

#### Test 16: Invalid time format handling
**Setup:**
- Priority Room Booking with malformed date or time fields
- Manager attempts cancellation

**Expected Result:**
- ❌ Cancellation caught by try-catch
- ⚠️ Error toast: "Invalid booking time format."
- ❌ NO receptionist notification sent

#### Test 17: Concurrent cancellation attempts
**Setup:**
- Two simultaneous cancellation requests for same booking

**Expected Result:**
- ✅ DB transaction ensures only one succeeds
- ✅ First request processes normally
- ❌ Second request fails isActionable() check (status already 'rejected')

#### Test 18: Multiple receptionists receive notifications
**Setup:**
- Company has 3 receptionist accounts
- Manager cancels Priority Booking

**Expected Result:**
- ✅ All 3 receptionists receive individual notification records
- ✅ Each notification has correct recipient_id

#### Test 19: Timezone consistency
**Setup:**
- System timezone is Asia/Jakarta (UTC+7)
- Server local time may differ

**Expected Result:**
- ✅ All time calculations use Asia/Jakarta timezone
- ✅ Carbon::now($this->tz) ensures consistent timezone
- ✅ 3-hour calculation is accurate regardless of server timezone

#### Test 20: Booking with date/time at midnight
**Setup:**
- Priority Room Booking scheduled for tomorrow at 00:00 (midnight)
- Current time: today at 20:00 (4 hours before)

**Expected Result:**
- ✅ Cancellation should succeed (4 hours > 3 hours)
- ✅ Date parsing handles midnight correctly

---

## Manual Testing Steps

### Setup
1. Ensure Laravel application is running
2. Log in as Manager role
3. Ensure at least one Receptionist account exists
4. Have access to database to verify notifications

### Testing Priority Room Booking
1. Navigate to Priority Room Booking page
2. Create a test booking scheduled for future time
3. Note the booking ID and scheduled start time
4. Calculate current time relative to start time
5. Attempt cancellation and verify expected behavior
6. Check manager_notifications table for receptionist notifications
7. Repeat with different time scenarios

### Testing Priority Vehicle Booking
1. Navigate to Priority Vehicle Booking page
2. Create a test booking with future start_at
3. Note the booking ID and scheduled start time
4. Calculate current time relative to start time
5. Attempt cancellation and verify expected behavior
6. Check manager_notifications table for receptionist notifications
7. Verify vehicle relationship is loaded for notification message

### Verification Checklist
- [ ] Cancellation succeeds when ≥3 hours remain
- [ ] Cancellation fails when <3 hours remain
- [ ] Error message displays correctly in both English and Indonesian
- [ ] Receptionist receives notification on successful cancellation
- [ ] No notification sent on failed cancellation
- [ ] Creation notifications still work (existing functionality)
- [ ] Toast messages display with correct type and duration
- [ ] Database transaction ensures atomic operation
- [ ] Booking status changes correctly on success
- [ ] Booking status unchanged on failure

---

## Code Review Checklist

### Security
- ✅ Server-side validation enforced (not just UI)
- ✅ User authorization checked (manager_id matches auth user)
- ✅ Company isolation maintained (company_id filter)
- ✅ DB transaction prevents partial state
- ✅ Input validation via isActionable() check

### Performance
- ✅ Minimal database queries (single find with where clauses)
- ✅ Efficient timezone calculation using Carbon
- ✅ Notification batch creation via foreach (acceptable for small user count)

### Maintainability
- ✅ Translation keys follow existing pattern
- ✅ Notification mechanism reuses existing infrastructure
- ✅ Error handling with try-catch for parsing errors
- ✅ Consistent with existing code style
- ✅ Clear variable names and logic flow

### Business Logic
- ✅ 3-hour rule enforced correctly (>= 3 hours allowed)
- ✅ Timezone-aware using Asia/Jakarta
- ✅ Handles edge cases (midnight, past bookings, etc.)
- ✅ Receptionist approval removed (immediate cancellation)
- ✅ Notifications sent to all receptionists

---

## Known Limitations

1. **Timezone**: Hardcoded to `Asia/Jakarta` in component. If company operates across timezones, this would need refactoring.

2. **Time Calculation**: Uses `diffInHours()` which rounds down. A booking 2 hours 59 minutes away will calculate as 2 hours and be rejected. This is correct behavior per requirements.

3. **Notification Delivery**: Notifications are stored in database but no real-time push mechanism. Receptionists must refresh or check notification bell.

4. **Concurrent Cancellation**: While DB transaction prevents double-cancellation, the second request silently fails. This is acceptable since the UI is single-user-focused.

5. **Vehicle Relationship**: Requires vehicle relationship to be loaded for notification message. If vehicle is soft-deleted or missing, fallback to vehicle_id.

---

## Rollback Plan

If issues are discovered:

1. Revert changes to:
   - `app/Livewire/Pages/Manager/PriorityRoomBooking.php`
   - `app/Livewire/Pages/Manager/PriorityVehicleBooking.php`

2. Remove translation keys from:
   - `lang/en/app.php`
   - `lang/id/app.php`

3. The notification system changes are additive only (no breaking changes to existing notifications).

---

## Conclusion

The implementation follows all requirements:

✅ 3-hour cancellation validation for Priority Room Bookings
✅ 3-hour cancellation validation for Priority Vehicle Bookings
✅ Server-side enforcement (not just UI)
✅ Timezone-aware using Asia/Jakarta
✅ Immediate cancellation (no receptionist approval required)
✅ Receptionist notifications on successful cancellation
✅ Creation notifications preserved (already existed)
✅ Translation keys for error messages
✅ DB transactions for atomic operations
✅ Edge cases handled (invalid times, concurrent requests, etc.)
✅ No changes to existing UI components
✅ No changes to other booking workflows
✅ Minimal, targeted changes only

All test scenarios should be verified manually in a development environment before deploying to production.
