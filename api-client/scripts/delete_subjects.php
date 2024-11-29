<?php


// API endpoint and API key for deleting subjects
$apiUrl = 'http://localhost:8000/api/v1/recognition/subjects';
$apiKey = '2edd8e78-be1e-44f3-bfcb-8778933ef5b4';

echo "╔══════════════════════════════════════════╗\n";
echo "║         DELETE ALL SUBJECTS              ║\n"; 
echo "╚══════════════════════════════════════════╝\n\n";

// Initialize cURL session
$ch = curl_init();

// Set headers
$headers = [
    'x-api-key: ' . $apiKey
];

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute the request
$response = curl_exec($ch);

// Get HTTP status code
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if(curl_errno($ch)) {
    echo 'Error: ' . curl_error($ch) . "\n";
    exit;
}

// Close cURL session
curl_close($ch);

// Process response
$result = json_decode($response, true);

if($statusCode == 200) {
    echo "Successfully deleted all subjects\n";
} else {
    echo "Failed to delete subjects. Status code: " . $statusCode . "\n";
    if(isset($result['message'])) {
        echo "Error message: " . $result['message'] . "\n";
    }
}
