---
paths:
  - 'app/Services/BuffSyncService.php'
  - 'tests/Feature/BuffSyncTest.php'
---

# Local sync apply

Remote changes can arrive out of dependency order (recipes after meals) and can collide with local unique keys (`body_metrics.date`, `health_connect_ignored_workouts` source+external id). Apply must:

- Disable foreign keys for the apply transaction so a meal can land before its recipe, including across `has_more` pages.
- Apply recipes before meal entries when both are in the same page.
- Reuse the local row that already occupies a natural key, retargeting its id to the remote id instead of inserting a second row.
