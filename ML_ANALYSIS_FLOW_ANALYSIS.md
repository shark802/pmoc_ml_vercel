# ML Analysis Automatic Trigger - Flow Analysis

## ✅ Current Implementation Status

### **YES, the ML analysis IS automatically triggered** after both partners submit their profiles and MEAI questionnaires.

## Complete Flow Diagram

```
1. COUPLE PROFILE SUBMISSION
   ├─ Male submits profile
   │  └─ Sets: male_profile_submitted = TRUE (couple_profile.php:424)
   │
   └─ Female submits profile
      └─ Sets: female_profile_submitted = TRUE (couple_profile.php:424)

2. MEAI QUESTIONNAIRE SUBMISSION
   ├─ Male submits questionnaire
   │  └─ Sets: male_questionnaire_submitted = TRUE (questionnaire.php:170)
   │  └─ Checks: Are all 4 flags TRUE? → NO → Continue
   │
   └─ Female submits questionnaire (LAST PARTNER)
      └─ Sets: female_questionnaire_submitted = TRUE (questionnaire.php:170)
      └─ Checks: Are all 4 flags TRUE? → YES ✅
         └─ TRIGGERS ML ANALYSIS (questionnaire.php:200)
            └─ Calls: trigger_ml_analysis($access_id)
               └─ Calls: ml_api.php?action=analyze
                  └─ Calls: Heroku ML Service (https://endpoint-pmoc-a0a6708d039f.herokuapp.com/analyze)
```

## Trigger Conditions

The ML analysis is triggered **ONLY** when **ALL 4 conditions** are met:

1. ✅ `male_profile_submitted` = TRUE
2. ✅ `female_profile_submitted` = TRUE  
3. ✅ `male_questionnaire_submitted` = TRUE
4. ✅ `female_questionnaire_submitted` = TRUE

**Location:** `questionnaire/questionnaire.php` lines 186-200

## Code Flow Details

### 1. Profile Submission
**File:** `couple_profile/couple_profile.php`
- Line 424: Sets `{$respondent}_profile_submitted = TRUE`
- Both partners must submit before accessing questionnaire

### 2. Questionnaire Submission  
**File:** `questionnaire/questionnaire.php`
- Line 170: Sets `{$respondent}_questionnaire_submitted = TRUE`
- Lines 177-183: Checks all 4 submission flags
- Lines 186-191: Validates all conditions are met
- Line 200: **Triggers ML analysis**

### 3. ML Analysis Trigger
**File:** `ml_php/trigger_ml_analysis.php`
- Calls local PHP API: `ml_api.php?action=analyze`
- Uses cURL with 60-second timeout
- Returns `true` on success, `false` on failure

### 4. ML API Processing
**File:** `ml_php/ml_api.php`
- Line 117: Calls Heroku ML service: `get_ml_service_url('analyze')`
- Collects couple data (profile + questionnaire responses)
- Sends to Heroku endpoint
- Saves results to database

## ⚠️ Potential Issues Found

### Issue 1: Silent Failure
**Problem:** The return value of `trigger_ml_analysis()` is not checked in `questionnaire.php:200`

```php
// Current code (line 200):
trigger_ml_analysis($access_id);  // Return value ignored!

// If ML analysis fails:
// - User doesn't know it failed
// - Transaction still commits
// - User redirected to completion page
// - Error only logged, not shown to user
```

**Impact:** 
- ML analysis failures are silent
- User completes form but analysis may not have run
- No retry mechanism

### Issue 2: Synchronous Execution
**Problem:** ML analysis runs synchronously during form submission

**Impact:**
- User must wait for ML analysis to complete (up to 60 seconds)
- If Heroku service is slow, user experience degrades
- Form submission blocks until analysis completes

### Issue 3: No Error Feedback
**Problem:** No user-facing error message if ML analysis fails

**Impact:**
- User completes assessment but analysis silently fails
- No indication that they should retry or contact support

## ✅ What's Working Correctly

1. ✅ **Automatic Trigger:** ML analysis is automatically triggered when all conditions are met
2. ✅ **Proper Sequencing:** Profiles must be submitted before questionnaires
3. ✅ **Both Partners Required:** System correctly checks that both partners completed everything
4. ✅ **Database Integration:** Results are saved to database if successful
5. ✅ **Error Logging:** Errors are logged to PHP error log

## 🔧 Recommendations

### 1. Add Error Handling (High Priority)
```php
// In questionnaire.php line 200:
$ml_result = trigger_ml_analysis($access_id);
if (!$ml_result) {
    error_log("ML analysis failed for access_id: $access_id");
    // Optionally: Log to database or send notification
    // Don't block user flow, but track the failure
}
```

### 2. Consider Asynchronous Processing (Medium Priority)
- Move ML analysis to background job/queue
- Return immediately to user
- Process analysis asynchronously
- Notify user when complete

### 3. Add User Feedback (Medium Priority)
- Show loading indicator during analysis
- Display success/failure message
- Provide retry option if analysis fails

### 4. Add Retry Mechanism (Low Priority)
- If analysis fails, queue for retry
- Retry up to 3 times with exponential backoff
- Alert admin if all retries fail

## Testing Checklist

- [ ] Test with both partners submitting profiles
- [ ] Test with both partners submitting questionnaires
- [ ] Verify ML analysis triggers on last submission
- [ ] Test with Heroku service down (should handle gracefully)
- [ ] Test with slow Heroku response (60s timeout)
- [ ] Check error logs for failures
- [ ] Verify results saved to database

## Conclusion

**The automatic ML analysis trigger IS working**, but there are opportunities to improve error handling and user experience. The core functionality is correct - it automatically triggers when all 4 conditions are met.

