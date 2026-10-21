<?php

require_once __DIR__.'/config/init.php';
$errorMessage = '';
$flashMessage = '';

// Pick up a flash message set by the auth guards (e.g. "Please log in...").
if (isset($_SESSION['flash'])) {
    $flashMessage = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

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
