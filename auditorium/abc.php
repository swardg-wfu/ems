<?php

session_start();
$_SESSION['vroot'] = '/Users/garrettsward/project-files/ems/';

include_once $_SESSION['vroot'] . 'includes/ems-api-class.php';


// default behavior is to capture events from now until two weeks (rounded down) in the future.
$weeks = 2;
if (isset($_GET['weeks'])) {
	$weeks = $_GET['weeks'];
}

$time_zone = new DateTimeZone('US/Eastern');

$start = new DateTime('now', $time_zone);
$end = new DateTime($start->format(DATE_ATOM));

$start_day = $start->format('N'); // Mon = 1, Tues = 2, etc.
$days_left_in_current_week = 7 - $start_day;
$end->modify("+$days_left_in_current_week day");

// $ems = new EMS('Dev');

$events_by_week = array();

$additions = array();
$deletions = array();

$i = 1;
while ($i <= $weeks) {
	if ($i > 1) {
		$start = new DateTime($end->format(DATE_ATOM));
		$start->modify('+1 day');
		$end->modify('+1 week');
	}
	$start->setTime(0, 0);
	$end->setTime(23, 59);

	$parameters = array(
		'buildingIds' => [11],
		'roomIds' => [715],
		'minReserveStartTime' => $start->format(DATE_ATOM),
		'maxReserveStartTime' => $end->format(DATE_ATOM)
		);

	echo "<pre>";
	print_r(json_encode($parameters));
	echo "</pre>";

	$year = $start->format('o');
	$week_number = $start->format('W');
	$week_id = $year . '_week_' . $week_number; //e.g. '2024_week_41'
	$filename = $week_id . '_events.json'; //e.g. '2024_week_41_events.json'
	$digestion_flag_filename = $year . '_digestion_flags.json';

	// $response = $ems->getBookings($parameters);
	// if ($bookings) {
	// 	$new_bookings = $bookings;
	// }
	// will have to return error code from getBookings somehow,
	// so we can determine whether or not no bookings for a day is because
	// those have been deleted.

	$new_bookings = array();
	$mock_api_response = json_decode(file_get_contents('API_auditorium_events.json'), $associative=true);
	if (isset($mock_api_response['week_' . $week_number]) AND isset($mock_api_response['week_' . $week_number]['results'])) {
		$new_bookings = $mock_api_response['week_' . $week_number]['results'];
	}

	$new_bookings_by_id = array();
	if (count($new_bookings) > 0) {
		foreach ($new_bookings as $booking) {
			$id = $booking['id'];
			$new_bookings_by_id[$id] = $booking;
		}
		$new_bookings = $new_bookings_by_id;
	}

	if (file_exists($filename)) {
		
		$cached_data = file_get_contents($filename);
		$cached_data = json_decode($cached_data, $associative=true);
		$old_bookings = $cached_data['bookings'];
		// $additions = $cached_data['additions'];
		// $deletions = $cached_data['deletions'];
		$digestion_flag = 0;
		if (file_exists($digestion_flag_filename)) {
			$digestion_flags_encoded = file_get_contents($digestion_flag_filename);
			$digestion_flags = json_decode($digestion_flags_encoded, $associative=true);
			$digestion_flag = $digestion_flags[$year . '_week_' . $week_number];
		}
		if ($digestion_flag) {
			foreach ($new_bookings as $id => $new_booking) {
				if (array_key_exists($id, $old_bookings)) {
					unset($new_bookings[$id]);
					unset($old_bookings[$id]);
				}
				// foreach ($old_bookings as $old_id => $old_booking) {
					// if ($new_id == $old_id) {
					//	unset($new_bookings[$new_id]);
					// 	unset($old_bookings[$old_id]);
					//  break;
					// }
				// }
			}

			//retain information about added and deleted events.
			// foreach ($additions as $id => $addition) {
			// 	if (in_array($id, array_keys($new_bookings_by_id))) {
			// 		$new_bookings[$id] = $addition;
			// 	}
			// }
			// foreach ($old_bookings as $id => $deletion) {
			// 	if (!in_array($id, array_keys($new_bookings_by_id))) {
			// 		$old_bookings[$id] = $deletion;
			// 	}
			// }

			if (count($new_bookings) > 0) {
				$additions[$week_number] = $new_bookings;
			}
			if (count($old_bookings) > 0) {
				$deletions[$week_number] = $old_bookings;
			}
		}
	}

	$data = array(
		'bookings' => $new_bookings_by_id//,
		// 'additions' => $additions,
		// 'deletions' => $deletions
	);
	$data_json = json_encode($data, JSON_PRETTY_PRINT);
	file_put_contents($filename, $data_json);
	$events_by_week[$week_id] = $data_json;

	$i++;
}


if (count($additions) > 0 OR count($deletions) > 0) {
	// We need to send an email
	$message = "We just ran an import...\n";

		if (count($additions) > 0) {
			// we found additional events
			$message .= "\nWe found some additional events:\n";
			foreach ($additions as $week_number => $addition_week) {
				$message .= "Additions for week $week_number:\n";
				foreach ($addition_week as $addition) {
					// list this item
					$message .= $addition['eventName'] . " on " . $addition['eventStartTime'] . "\n";
				}
			}
		}

		if (count($deletions) > 0) {
			// we found some events that were removed
			$message .= "\nWe found some removed events:\n";
			foreach ($deletions as $week_number => $deletion_week) {
				$message .= "Deletions for week $week_number:\n";
				foreach ($deletion_week as $deletion) {
					// list this item
					$message .= $deletion['eventName'] . " on " . $deletion['eventStartTime'] . "\n";
				}
			}
		}
	// Now, send the message:
	// $email = new WFUMailer();
	// $email->Subject = "EMS Auditorium Events Import Report";
	// $email->Body = $message; 
	// $email->AddAddress("lawhelp@wfu.edu");
	// $email->Send();

	echo "<hr>";
	echo nl2br($message);
	// foreach($events_by_week as $week => $events) {
	// 	echo "<pre>";
	// 	echo $week . ": <br/>";
	// 	print_r($events);
	// 	echo "</pre>";
	// }
}