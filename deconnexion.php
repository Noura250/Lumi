<?php

require 'config/security.php';

session_unset();
session_destroy();

header("Location: connexion.php");
exit;