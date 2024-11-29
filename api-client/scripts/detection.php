<?php

// Path to the JavaScript file in your images directory
$filePath = 'api-client/images/gambar_staff.jpg';

// API endpoint and API key
$apiUrl = 'http://localhost:8000/api/v1/detection/detect?face_plugins=landmarks,gender,age,pose';
$apiKey = '102f6afd-ab52-4970-ad28-36ee2d738078';

// Create the cURL request
$ch = curl_init();
$headers = [
    'x-api-key: ' . $apiKey
];

$file = new CURLFile($filePath);

// Prepare the form data
$postFields = [
    'file' => $file
];

// Set the cURL options
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute the request
$response = curl_exec($ch);

// Get the HTTP status code
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if(curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
    exit;
}

// Close the cURL session
curl_close($ch);

// Decode the JSON response
$data = json_decode($response, true);

if($statusCode == 400){
    echo $data['message'];
    exit();
}

if (isset($data['result']) && is_array($data['result'])) {
    foreach ($data['result'] as $index => $face) {
        echo 'Face ' . ($index + 1) . "\n";
        echo '- Box: x_min: ' . $face['box']['x_min'] . ', y_min: ' . $face['box']['y_min'] . ', x_max: ' . $face['box']['x_max'] . ', y_max: ' . $face['box']['y_max'] . "\n";

        // EXTRACT - Crop the image using the detected face box
        $cropParams = [
            'left'      => $face['box']['x_min'],
            'top'       => $face['box']['y_min'],
            'width'     => $face['box']['x_max'] - $face['box']['x_min'],
            'height'    => $face['box']['y_max'] - $face['box']['y_min']
        ];

        $outputImagePath = 'api-client/images/extracted/output_face_' . ($index + 1) . '.png';

        // Perform image cropping
        cropImage($filePath, $cropParams, $outputImagePath);
        
        echo "---\n";
    }
} else {
    echo "No faces detected or invalid response.\n";
}

function cropImage($imagePath, $params, $outputImagePath) {
    // Use GD library or Imagick for cropping
    if (extension_loaded('gd')) {
        $image = imagecreatefromjpeg($imagePath);
        if (!$image) {
            echo 'Error: Could not load the image file.' . "\n";
            return;
        }

        // Calculate the crop dimensions
        $width  = $params['width'];
        $height = $params['height'];
        $x      = $params['left'];
        $y      = $params['top'];

        // Crop the image
        $croppedImage = imagecrop(
            $image, 
            [
                'x'         => $x, 
                'y'         => $y, 
                'width'     => $width, 
                'height'    => $height
            ]
        );

        if ($croppedImage !== FALSE) {
            // Save the cropped image
            imagepng($croppedImage, $outputImagePath);
            imagedestroy($croppedImage);
            echo "Cropped image saved to " . $outputImagePath . "\n";
        } else {
            echo "Failed to crop the image.\n";
        }

        imagedestroy($image);
    } else {
        echo "GD library not available. Please install it to crop images.\n";
    }
}
?>
