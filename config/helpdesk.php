<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SLA Resolution Times (in minutes)
    |--------------------------------------------------------------------------
    |
    | Defines the resolution SLA timeframe for each ticket priority level.
    | - Low: 48 hours (2880 minutes)
    | - Medium: 24 hours (1440 minutes)
    | - High: 8 hours (480 minutes)
    | - Critical: 2 hours (120 minutes)
    |
    | Placing this in config ensures business rules can be modified without
    | changing database schemas or core application code.
    |
    */
    'sla_minutes' => [
        'low' => 2880,
        'medium' => 1440,
        'high' => 480,
        'critical' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA Warning Threshold
    |--------------------------------------------------------------------------
    |
    | Percentage (fraction) of SLA elapsed before triggering an approaching breach
    | warning notification. Default 0.8 means warn at 80% of SLA duration.
    |
    */
    'warning_threshold' => 0.8,

    /*
    |--------------------------------------------------------------------------
    | Business Hours Configuration
    |--------------------------------------------------------------------------
    |
    | SLA deadlines are calculated strictly within configured working hours.
    | - Timezone: Stored in UTC in the database, but business hours are evaluated
    |   in the local operating timezone (Asia/Dhaka).
    | - Days: 0 (Sunday) through 4 (Thursday) matching standard BD workdays.
    |   (0 = Sunday, 1 = Monday, 2 = Tuesday, 3 = Wednesday, 4 = Thursday)
    | - Start & End: Working hours from 09:00 to 18:00 (9 hours/day).
    | - Holidays: List of specific 'Y-m-d' date strings excluded from business hours. e.g. ["2026-01-01", "2026-12-25"]
    |
    */
    'business_hours' => [
        'timezone' => 'Asia/Dhaka',
        'days' => [0, 1, 2, 3, 4],
        'start' => '09:00',
        'end' => '18:00',
        'holidays' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Ticket Assignment
    |--------------------------------------------------------------------------
    |
    | If enabled, newly created tickets will be automatically assigned to
    | available support agents using the round-robin/workload strategy.
    |
    */
    'auto_assign_on_create' => true,

    /*
    |--------------------------------------------------------------------------
    | Attachment Upload Restrictions
    |--------------------------------------------------------------------------
    |
    | Constraints to ensure secure and controlled file uploads:
    | - disk: 'local' (private, served only via authenticated routes)
    | - max_kb: 5120 (5MB max file size)
    | - max_files: Maximum files allowed per ticket/reply
    | - mimes: Permitted file extensions
    |
    */
    'attachments' => [
        'disk' => 'local',
        'max_kb' => 5120,
        'max_files' => 5,
        'mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'txt', 'docx'],
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV Import Settings
    |--------------------------------------------------------------------------
    |
    | Chunk size for streaming and inserting bulk tickets to avoid memory spikes.
    |
    */
    'import' => [
        'batch_size' => 500,
    ],
];
