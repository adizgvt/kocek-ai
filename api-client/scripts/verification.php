<?php

// API URL and API key
$apiUrl = 'http://localhost:8000/api/v1/verification/verify?face_plugins=landmarks,gender,age,pose';
$apiKey = '5048fc02-0bde-4d27-b8b2-90e773355290';

// Paths to the source and target images
$sourceImagePath = 'api-client/images/farihah.jpeg';
$targetImagePath = 'api-client/images/farihah_maziah_aisyah.jpeg';

// Initialize cURL session
$ch = curl_init();


// Set headers
$headers = [
    'x-api-key: ' . $apiKey
];

// Prepare the files for upload
$postFields = [
    'source_image' => new CURLFile($sourceImagePath),
    'target_image' => new CURLFile($targetImagePath)
];

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute the cURL request
$response = curl_exec($ch);

// Get the HTTP status code
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if (curl_errno($ch)) {
    echo 'Error: ' . curl_error($ch);
    exit;
}

// Close cURL session
curl_close($ch);

// Print the status code
echo "HTTP Status Code: " . $statusCode . "\n";

// Decode the JSON response
$data = json_decode($response, true);

if($statusCode == 400){
    echo $data['message'];
    exit();
}

// Handle the response (print the data)
if (isset($data['result'][0]['face_matches']) && is_array($data['result'][0]['face_matches'])) {
    $similarities = [];
    
    foreach ($data['result'][0]['face_matches'] as $match) {
        // Store similarity values in the array
        $similarities[] = $match['similarity'];
    }

    // Print similarity values

    $hasMatch   = false;
    $matchCount = 0;

    echo "Similarity values:\n";
    foreach ($similarities as $similarity) {

        if($similarity > 0.95){
            $matchCount++;
        }

        echo $similarity . "\n";
    }

    if($matchCount == 1){
        $hasMatch = true;
    }

    if($hasMatch){
        echo "One similar matches detected";
    } else {
        echo "No or more than one matches detected";
    }
} else {
    echo "No face matches found or invalid response format.\n";
}

?>
