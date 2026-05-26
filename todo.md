# TODO

## MVP
- [x] The "Add" shortcut should open the app normal with the "Add" bottom sheet open
- [x] Create a settings page, put everything from "Goals" (Except calories/macros) and also the "Height" form in there
- [x] Appearance settings (Dark/light/system) in settings page
- [x] Replace the "Food, search or scan" button in the add sheet with 2 buttons, 1 for scan and 1 for search... on clicking scan it should open the "add food" page with the barcode scanner already open
- [x] Refactor Add.vue, extract into dedicated components.
- [x] Ability to see roundup of calories and macros for the week
- [x] Health Connect & Bridge Polish.  Sync Visibility: There is no clear indicator of when the last background sync occurred (the UI mostly shows manual sync status).  Detailed Error Reporting: If the bridge fails or permissions are partially revoked at the OS level, the current UI messages are somewhat generic.
- [x] Tests. Native Bridge Mocking: Tests for how the JS reacts when nativephp_call returns unexpected or malformed data.

## V2
 - [ ] Ability to take a picture of a meal and have AI estimate calories, macros, and give a small description
 - [ ] Ability to create "meals" that are collections of entries that can be re-used later (for example, 30g cheerios and 100ml milk to make one Breakfast meal)
 - [ ] Add multiple images to progress
 - [ ] Ability to see roundup of food TYPES (dairy, meat, veg, etc)
 - [ ] New user onboarding wizard
 - [ ] Empty states
 - [ ] Add unit options in settings page
 - [ ] Import/Export
 - [ ] Marketing website
 - [ ] Cloud API rather than interfacing directly with OpenFood
