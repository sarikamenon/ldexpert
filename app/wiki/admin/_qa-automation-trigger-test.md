# Admin — Export Schools List (QA automation trigger test)

> Throwaway file to test the QA automation merge trigger. Safe to delete after the test.

## Feature
On the Schools list page (`/admin/schools`), an admin can export the current list
to CSV using an **Export** button in the page header.

### Behavior
- Clicking **Export** downloads a CSV of the schools currently shown (respecting any
  active filters).
- The CSV includes: school name, status, contact email, created date.
- Only admins can access the export; other roles receive a 403.
