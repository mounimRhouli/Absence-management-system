<?php
require '../vendor/autoload.php'; // Ensure you include the Composer autoloader

use Carbon\Carbon;
use Carbon\CarbonPeriod;

// Include your database connection
include '../Includes/dbcon.php';

// Define the Calendarific API URL and your API key
$api_key = 'pvZkV2WNPGOqt3k2OxvMkovxGuiDBN9h'; // Replace 'YOUR_API_KEY_HERE' with your actual API key
$country = 'MA'; // Country code for Morocco
$year = date('Y');

// Fetch holidays from Calendarific API
$api_url = "https://calendarific.com/api/v2/holidays?&api_key=$api_key&country=$country&year=$year";
$response = file_get_contents($api_url);
if ($response === FALSE) {
    die("Error fetching holidays: " . $http_response_header[0]); // Print HTTP error if request fails
}
$holidays = json_decode($response, true);

// Verify if the response contains data
if (!isset($holidays['response']['holidays'])) {
    die("Error: No holiday data found in API response.");
}

// Extract holidays from the API response
$days_off = [];
foreach ($holidays['response']['holidays'] as $holiday) {
    $holiday_name = $holiday['name'];
    $holiday_date = $holiday['date']['iso'];
    $days_off[$holiday_name] = $holiday_date;
}

// Check and insert/update holidays in the `daysoff` table
$update_stmt = $conn->prepare("UPDATE daysoff SET date_DayOff = ? WHERE Name = ?");
$update_stmt->bind_param("ss", $date, $name);

$insert_stmt = $conn->prepare("INSERT INTO daysoff (Name, date_DayOff) VALUES (?, ?)");
$insert_stmt->bind_param("ss", $name, $date);

foreach ($days_off as $name => $date) {
    // Check if holiday with the same name already exists
    $check_stmt = $conn->prepare("SELECT date_DayOff FROM daysoff WHERE Name = ?");
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Holiday with the same name already exists
        $existing_date = $check_result->fetch_assoc()['date_DayOff'];
        if ($existing_date !== $date) {
            // Update the date if it's different
            $update_stmt->execute();
        }
    } else {
        // Insert the new holiday if it doesn't exist
        $insert_stmt->execute();
    }
}

$update_stmt->close();
$insert_stmt->close();

// Fetch timetables from the `timetable` table
$timetables_query = "SELECT * FROM timetable";
$timetables_result = mysqli_query($conn, $timetables_query);

// Prepare the statement for inserting into `timetable_daysoff`
$insert_timetable_stmt = $conn->prepare("INSERT INTO timetable_daysoff (Timetable_id, DaysOff_id) VALUES (?, ?)");

// Loop through each timetable
while ($timetable = mysqli_fetch_assoc($timetables_result)) {
    $timetable_id = $timetable['id_Timetable'];
    $start_date = $timetable['Start_Date'];
    $end_date = $timetable['End_Date'];

    // Fetch days off between start date and end date from the `daysoff` table
    $days_off_query = "SELECT * FROM daysoff WHERE date_DayOff BETWEEN ? AND ?";
    $days_off_stmt = $conn->prepare($days_off_query);
    $days_off_stmt->bind_param("ss", $start_date, $end_date);
    $days_off_stmt->execute();
    $days_off_result = $days_off_stmt->get_result();

    // Insert days off into `timetable_daysoff`
    while ($day_off = $days_off_result->fetch_assoc()) {
        $day_off_id = $day_off['id_DaysOff'];
        $insert_timetable_stmt->bind_param("ii", $timetable_id, $day_off_id);
        $insert_timetable_stmt->execute();
    }
}

// Close the statement for inserting into timetable_daysoff
$insert_timetable_stmt->close();

// Close the database connection
$conn->close();

echo "Holidays have been successfully inserted/updated in the daysoff table, and added to the timetable_daysoff table!";
?>
