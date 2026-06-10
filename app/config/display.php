<?php

return [

    /*
     * Standard 12-hour time format for all user-facing time display.
     * e.g. "9:30 AM"
     */
    'time' => 'g:i A',

    /*
     * Standard date-only format for all user-facing date display.
     * e.g. "May 11, 2026"
     */
    'date' => 'M d, Y',

    /*
     * Long date format with weekday for headers and email subjects.
     * e.g. "Monday, May 11, 2026"
     */
    'date_long' => 'l, F j, Y',

    /*
     * Year-less short date, for the start of a same-year date range.
     * e.g. "May 11" in "May 11 - May 30, 2026"
     */
    'date_short' => 'M d',

    /*
     * Year-less date with full month name, for email subjects and range starts
     * where the abbreviated month reads awkwardly.
     * e.g. "May 11" in "Invoice - May 11 - May 30"
     */
    'date_short_month' => 'F j',

    /*
     * Standard datetime format combining date + 12-hour time.
     * e.g. "May 11, 2026 9:30 AM"
     */
    'datetime' => 'M d, Y g:i A',

    /*
     * Datetime format with seconds (import status rows).
     * e.g. "May 11, 2026 9:30:05 AM"
     */
    'datetime_seconds' => 'M d, Y g:i:s A',

];
