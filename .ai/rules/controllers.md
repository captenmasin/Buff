---
paths:
  - app/Http/Controllers/SettingsController.php
---

# Controllers

## Autosave endpoints do not flash global messages
Units, exercise-calorie, and meal-reminder autosaves return without the global message flash. Meal reminders may flash save_status for inline detail and must return a validation error when native scheduling fails.
