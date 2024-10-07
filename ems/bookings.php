<?php
//include_once '/home/swardgsi/public_html/includes/ems-api-class.php';
include_once 'includes/ems-api-class.php';


// $ems = new EMS();

$times = walkUpReservation(11, '2024-08-28T12:10:00-04:00', 60);
if ($times) {
  echo "<p>Start time: ".$times[0]->format(DATE_ATOM)."</p>".
      "<p>End time: ".$times[1]->format(DATE_ATOM)."</p>";
}

?>
