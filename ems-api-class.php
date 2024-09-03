<?php

function walkUpReservation($roomId, $startTime, $duration) {
	//include ...ems-api-class.php
	$ems = new EMS();

	// assuming $startTime is either the string 'now' or a timestamp
	$startTime = new DateTime($startTime, $ems->time_zone);
	$startTime->setTime($startTime->format('H'), $startTime->format('i'), $second = 0);
	$endTime = $ems->calculateEndTime($startTime, $duration);

	$successful = $ems->createReservation($roomId, 
										$startTime->format(DATE_ATOM),
										$endTime->format(DATE_ATOM),
										"Room Reservation",
										$statusId=9);

	if (!$successful) {
		echo "<p>There was an error while trying to make your reservation.</p>".
			"<p>Please contact Law IT.</p>";
		return FALSE;
	} else {
		echo "<p>Reservation successfully created from " . $startTime->format('g:ia') .
			" to ". $endTime->format('g:ia') . " on " . $startTime->format('F jS') .".</p>";
	}
	return array($startTime, $endTime);
}

class EMS {
	private $client_token;
	public $time_zone;

	function __construct() {
		$this->time_zone = new DateTimeZone('US/Eastern');

	    include '../../.secret/credentials.php';
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $ems_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 10,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => "{\n\t\"clientId\": \"$ems_client_id\",\n\t\"secret\": \"$ems_secret\"\n}",
		  CURLOPT_HTTPHEADER => array(
		    "Content-Type: application/json",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			echo "<p>cURL Error: " . $err . "</p>";
			return FALSE;
		} else {
			$response = json_decode($response, $associative=true);
			if (isset($response['clientToken'])) {
				$this->client_token = $response['clientToken'];
			} else {
				echo "<p>No clientToken in response.</p>";
				return FALSE;
			}
		}
		return;
	}

	function getClientToken() {
		return $this->client_token;
	}

	function calculateEndTime(DateTime $startTime, $duration) {
		$duration = new DateInterval('PT'.$duration.'M');
		$endTime = new DateTime($startTime->format(DATE_ATOM));
		$endTime = $endTime->add($duration);
		$endMinutes = intval($endTime->format('i'));
		$remainder = $endMinutes % 15;
		if ($remainder != 0) {
			if ($endMinutes > 45) {
				$endTime->setTime($minute = 0);
				$endTime->add(new DateInterval('PT1H'));
			} else {
				$offset = 15 - $remainder;
				$endTime->add(new DateInterval('PT'.$offset.'M'));
			}
		}
		return $endTime;
	}

	// search for bookings for a building within the given timestamps.
	function getBookings($search_filters) {
		$bookings = array();
		$curl = curl_init();
		
		$post_fields = array();
		foreach($search_filters as $key => $value){
			switch($key){
				case 'buildingIds':
			   	 	$post_fields[] = '"buildingIds": [' . $value . ']';
					break;
				
				case 'roomIds':
			   	 	$post_fields[] = '"roomIds": [' . $value . ']';
					break;
				
				case 'start_time':
			   	 	$post_fields[] = '"minReserveStartTime": "' . $value . '"';
					break;
				
				case 'end_time':
			   	 	$post_fields[] = '"maxReserveStartTime": "' . $value . '"';
					break;
				
				default: 
			   	 	$post_fields[] = '"$key": "' . $value . '"';
					break;
			}
		}
		$post_fields_str = "{" . implode(", ", $post_fields) . "}";
		
		
		$successful = curl_setopt_array($curl, array(
			CURLINFO_HEADER_OUT => true,
			CURLOPT_URL => 'https://dev.rooms.wfu.edu/EmsPlatform/api/v1/bookings/actions/search?pageSize=2000',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $post_fields_str,
 			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/json",
				"x-ems-api-token: " . $this->client_token
			),
		));

		if (!$successful) {
			echo "<p>cURL options could not be set.</p>";
			return FALSE;
		}

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			echo "<p>cURL Error: " . $err . "</p>";
			return FALSE;
		} else {
			$response = json_decode($response, $associative=true);
			if (isset($response['errorCode'])){
				echo "<p>API Error: " . $response['errorCode'] . "</p>";
				return FALSE;
			} elseif (isset($response['results'])) {
				$bookings = $response['results'];
			} else {
				echo "<pre>";
				print_r($response);
				echo "</pre>";
				echo "<p>There are no bookings that match your search parameters.</p>";
			}
		}

		return $bookings;
	}

	function getBooking($bookingId) {
		$curl = curl_init();
		$booking = '';

		$successful = curl_setopt_array($curl, array(
			CURLINFO_HEADER_OUT => true,
			CURLOPT_URL => "https://dev.rooms.wfu.edu//EmsPlatform/api/v1/bookings/$bookingId",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_POSTFIELDS => $post_fields_str,
 			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/json",
				"x-ems-api-token: " . $this->client_token
			),
		));

		if (!$successful) {
			echo "<p>cURL options could not be set.</p>";
			return FALSE;
		}

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			echo "<p>cURL Error: " . $err . "</p>";
			return FALSE;
		} else {
			$response = json_decode($response, $associative=true);
			if(isset($response['errorCode'])){
				echo "<p>API Error: " . $response['errorCode'] . "</p>";
				return FALSE;
			}elseif(isset($response['results'])) {
				$booking = $response['results'];
			}
		}

		return $booking;
	}

	//a status ID of 6 is a "Web Request". 9 is "Confirmed", 13 is "Hold".
	//event type ID 3 is "meeting".
	function createReservation($roomId, $startTime, $endTime, $eventName, $statusId=6) {
		$curl = curl_init();

		$post_fields = array();
		
		$post_fields[] = '"bookings": [{' . '"roomId": ' . $roomId . ','
										. '"startTime": ' . $startTime . ','
										. '"endTime": ' . $endTime . '}]';
		$post_fields[] = '"eventTypeId": 3';
		$post_fields[] = '"eventname": ' . $eventName;
		$post_fields[] = '"statusId": ' . $statusId;

		$post_fields_str = "{" . implode(", ", $post_fields) . "}";

		$successful = curl_setopt_array($curl, array(
			CURLINFO_HEADER_OUT => true,
			CURLOPT_URL => 'https://dev.rooms.wfu.edu/EmsPlatform/api/v1/reservations/actions/create',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $post_fields_str,
 			CURLOPT_HTTPHEADER => array(
				"Accept: */*",
				"Accept-Encoding: gzip, deflate, br",
				"Cache-Control: no-cache",
				"Connection: keep-alive",
				"Content-Type: application/json",
				"Host: dev.rooms.wfu.edu",
				"Postman-Token: 894705c6-89a7-41c8-86a3-6cf131c51d5b",
				"User-Agent: PostmanRuntime/7.41.1",
				"cache-control: no-cache",
				"x-ems-api-token: " . $this->client_token
			),
		));

		if (!$successful) {
			echo "<p>cURL options could not be set.</p>";
			return FALSE;
		}

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		$reservationId = '';
		$bookingIds = array();

		if ($err) {
			echo "<p>cURL Error: " . $err . "</p>";
			return FALSE;
		} else {
			$response = json_decode($response, $associative=true);
			if (isset($response['errorCode'])){
				echo "<p>API Error: " . $response['errorCode'] . "</p>";
				return FALSE;
			// assuming "id" will be set if "bookingIds" is set.
			} elseif (isset($response['bookingIds'])) {
				$reservationId = $response['id'];
				$bookingIds = $response['bookingIds'];
			}
		}
		return array($reservationId, $bookingIds);
	}
}
?>