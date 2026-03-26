NOVA · In-App Notifications PRD
Version 1.0 · Last Updated: 26 Mar 2026

1. OVERVIEW
   The Notifications module provides an in-app notification center for admins. Notifications can be viewed, marked as read/unread, and deleted.

2. FUNCTIONAL SCOPE

   2.1 Notification List
   Route: GET /admin/notifications
   - Paginated list of all notifications for the authenticated admin
   - Unread notifications visually distinct from read ones

   2.2 Unread Count
   Route: GET /admin/notifications/unread
   - Returns unread notification count (used for nav badge)

   2.3 Mark as Read
   Route: POST /admin/notifications/{id}/mark-read
   - Marks a single notification as read

   2.4 Mark All as Read
   Route: POST /admin/notifications/mark-all-read
   - Marks all unread notifications as read

   2.5 Delete Notification
   Route: DELETE /admin/notifications/{id}
   - Removes a notification

3. TECHNICAL IMPLEMENTATION
   Controller: `App\Http\Controllers\Admin\NotificationController`
   View: `admin/notifications/index.blade.php`
   Uses Laravel's built-in notification system (`DatabaseNotification`).
