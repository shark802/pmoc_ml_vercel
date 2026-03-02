<?php
// Centralized scheduling configuration
// Adjust capacities as needed per day for each session type
return [
	'capacity' => [
		'Orientation' => 7,
		'Counseling' => 7,
	],
	// Statuses that should count toward capacity (exclude cancelled/no-show)
	'count_statuses' => ['pending', 'confirmed']
];
?>
