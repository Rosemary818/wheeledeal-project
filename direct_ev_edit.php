<?php
// Force direct inclusion of edit_ev.php
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    include 'edit_ev.php';
} else {
    echo "Error: No vehicle ID provided";
}
?> 