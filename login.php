<?php
/*
 * Login controller and entry point:
 * - Render views/auth/login_view.html for GET requests.
 * - Process the view's POST submission and authenticate the selected user type.
 * - On failure, set $errorMessage and render the view again so it can display the error.
 * - On success, redirect the authenticated user to the appropriate dashboard.
 *
 * Keep request handling and authentication in this file; keep presentation in the view.
 * THIS IS JUST MY RECOMMENDED IMPLEMENTATION
 */
