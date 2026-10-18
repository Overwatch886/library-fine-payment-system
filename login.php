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
require_once __DIR__.'/config/init.php';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    try{
        $user = (new Auth())->login(
            $_POST['user_type'] ?? '',
            trim($_POST['username'] ?? ''),
            $_POST['password'] ?? ''
        );
        $target = ($user['role'] === 'student') ? ('catalogue.php') : ('admin_dashboard.php');
        header('Location: '.$target);
        exit;
    }
    catch(AppException $e){
        $errorMessage = $e->getMessage();
    }
    catch(PDOException $ex){
        // My SQL Server is not running
        // Hence let's inform the user in a way they can understand
        $errorMessage = "Error on Server Side. Please contact administrator";
    }
}
require __DIR__ . '/views/auth/login_view.html';
?>