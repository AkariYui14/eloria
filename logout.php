<?php

session_start();


/* =========================================
   LOGOUT
========================================= */

session_unset();

session_destroy();


/* =========================================
   REDIRECT TO LOGIN
========================================= */

header("Location: login.php?logged_out=1");

exit;

?>