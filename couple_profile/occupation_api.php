<?php
header('Content-Type: application/json');

// Sample occupation data (you can expand this list)
$occupations = [
    "Accountant",
    "Actor",
    "Architect",
    "Artist",
    "Attorney",
    "Baker",
    "Banker",
    "Barber",
    "Bartender",
    "Bookkeeper",
    "Builder",
    "Business Owner",
    "Butcher",
    "Carpenter",
    "Cashier",
    "Chef",
    "Civil Engineer",
    "Cleaner",
    "Clerk",
    "Coach",
    "Computer Programmer",
    "Construction Worker",
    "Cook",
    "Customer Service",
    "Dentist",
    "Designer",
    "Doctor",
    "Driver",
    "Electrician",
    "Engineer",
    "Farmer",
    "Firefighter",
    "Fisherman",
    "Florist",
    "Gardener",
    "Hairdresser",
    "Housekeeper",
    "IT Specialist",
    "Journalist",
    "Judge",
    "Lawyer",
    "Lecturer",
    "Librarian",
    "Manager",
    "Mechanic",
    "Musician",
    "Nurse",
    "Painter",
    "Pharmacist",
    "Photographer",
    "Pilot",
    "Plumber",
    "Police Officer",
    "Professor",
    "Receptionist",
    "Salesperson",
    "Secretary",
    "Security Guard",
    "Soldier",
    "Student",
    "Surgeon",
    "Teacher",
    "Technician",
    "Trader",
    "Translator",
    "Veterinarian",
    "Waiter",
    "Waitress",
    "Writer"
];

$query = isset($_GET['term']) ? strtolower(trim($_GET['term'])) : '';

$suggestions = [];

// Add matching occupations
foreach ($occupations as $occupation) {
    if (empty($query)) {  // Fixed syntax error - missing closing parenthesis
        $suggestions[] = $occupation;
    } elseif (strpos(strtolower($occupation), $query) !== false) {
        $suggestions[] = $occupation;
    }
}

// Add current input as an option if it doesn't match any occupations
if (!empty($query) && !in_array(ucwords($query), $occupations)) {
    $suggestions[] = ucwords($query);
}

// Format for jQuery UI Autocomplete
$response = array_map(function($item) {
    return ['label' => $item, 'value' => $item];
}, $suggestions);

echo json_encode($response);
?>