<?php
/*
 * Application settings.
 * Change the database values here if your MySQL setup is different.
 */

// ---- Database (XAMPP defaults) ----
// Optional environment values let a separate test server use its own database.
define('DB_HOST', getenv('LIBRARY_DB_HOST') !== false ? getenv('LIBRARY_DB_HOST') : 'localhost');
define('DB_NAME', getenv('LIBRARY_DB_NAME') !== false ? getenv('LIBRARY_DB_NAME') : 'ui_library');
define('DB_USER', getenv('LIBRARY_DB_USER') !== false ? getenv('LIBRARY_DB_USER') : 'root');
define('DB_PASS', getenv('LIBRARY_DB_PASS') !== false ? getenv('LIBRARY_DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// ---- Library rules ----
define('FINE_PER_DAY', 100);      // ₦100 for each day a book is late
define('FINE_CAP', 5000);         // a single fine never goes above ₦5,000
define('MAX_LOAN_DAYS', 14);      // a student can keep a book for 1–14 days
define('MAX_ACTIVE_LOANS', 3);    // at most 3 books out at the same time

// Nigeria time, so dates in receipt references match the local day.
date_default_timezone_set('Africa/Lagos');
