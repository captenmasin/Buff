---
paths:
  - 'native-plugins/background-tasks/**'
---

# Background Tasks

## Use idle-safe alarms for meal reminders
Android meal reminders must be scheduled with AlarmManager.setAndAllowWhileIdle and dispatch their check as immediate expedited work. A future WorkManager initial delay is deferred by Doze and can hold notifications until the device is unlocked. Restore alarms during the one-time migration and after BOOT_COMPLETED.
