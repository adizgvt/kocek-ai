<?php
//WHEN UPLOAD IMAGE, DETECT, RECOGNIZE, REGISTER

// API URLs
define('BASE_URL', 'http://localhost:8000/api/v1');

$detectApiUrl       = BASE_URL . '/detection/detect';
$recognizeUrl       = BASE_URL . '/recognition/recognize?limit=1';
$registerApiUrl     = BASE_URL . '/recognition/subjects';
$uploadImageApiUrl  = BASE_URL . '/recognition/faces';

// API Keys
$detectApiKey       = '102f6afd-ab52-4970-ad28-36ee2d738078';
$recognizeApiKey    = '2edd8e78-be1e-44f3-bfcb-8778933ef5b4';

//THRESHOLD (TO DETERMINE IF A FACE IS NEW OR EXISTING)
$probabilityThreshold = 0.98; //HIGH PROBABILITY MEANS HIGH CONFIDENCE
$similarityThreshold  = 0.88; //HIGH SIMILARITY MEANS LOW DIFFERENCE
//SIMILARITY < 0.8 MEANS THE FACES ARE DIFFERENT

// Get all files from uploaded folder
$imagePath = 'api-client/images/uploaded/b.png';


//╔═══════════════════════════════════════════════════════════════════════════════════════╗
//║  ██╗  ██╗ ██████╗  ██████╗███████╗██╗  ██╗     █████╗ ██╗                             ║
//║  ██║ ██╔╝██╔═══██╗██╔════╝██╔════╝██║ ██╔╝    ██╔══██╗██╗                             ║
//║  █████╔╝ ██║   ██║██║     █████╗  █████╔╝     ███████║██╗                             ║
//║  ██╔═██╗ ██║   ██║██║     ██╔══╝  ██╔═██╗     ██╔══██║██║                             ║
//║  ██║  ██╗╚██████╔╝╚██████╗███████╗██║  ██╗    ██║  ██║██║                             ║
//║  ╚═╝  ╚═╝ ╚═════╝  ╚═════╝╚══════╝╚═╝  ╚═╝    ╚═╝  ╚═╝╚═╝                             ║
//╚═══════════════════════════════════════════════════════════════════════════════════════╝
//CLEAN UP

//Delete all files in the extracted directory
$extractedDir = 'api-client/images/extracted/';
$files = glob($extractedDir . '*');
foreach($files as $file) {
    if(is_file($file)) {
        unlink($file);
        echo "Deleted: " . basename($file) . "\n";
    }
}

//CALL API TO DETECT

echo "\n";
echo "╔══════════════════════════════════════════╗\n";
echo "║         FACE DETECTION                   ║\n";
echo "╚══════════════════════════════════════════╝\n\n";

// Initialize cURL session
$ch = curl_init();

// Set headers
$headers = [
    'x-api-key: ' . $detectApiKey
];

$postFields = [
    'file' => new CURLFile($imagePath)
];

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $detectApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute the cURL request
$response = curl_exec($ch);

// Get the HTTP status code
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP Status Code: " . $statusCode . "\n";

// Check for errors
if (curl_errno($ch)) {
    echo 'Error: ' . curl_error($ch);
    exit();
}

// Decode the JSON response
$data = json_decode($response, true);

if($statusCode != 200){
    echo "Error for {$imagePath}: " . $data['message'] . "\n";
    exit();
}

// Handle the response
if (!isset($data['result'])) {
    return;
}

print_r($data['result']);
// Print table header
echo "\n";
echo str_pad("| Face", 10) . str_pad("| X min", 10) . str_pad("| Y min", 10) . str_pad("| X max", 10) . str_pad("| Y max", 10) . str_pad("| Prob", 10) . "|\n";
echo str_repeat("-", 65) . "\n";
// Print table rows for each face
foreach ($data['result'] as $index => $face) {
    if (isset($face['box'])) {
        $box = $face['box'];
        echo str_pad("| " . ($index + 1), 10) .
             str_pad("| " . $box['x_min'], 10) .
             str_pad("| " . $box['y_min'], 10) .
             str_pad("| " . $box['x_max'], 10) .
             str_pad("| " . $box['y_max'], 10) .
             str_pad("| " . (isset($box['probability']) ? number_format($box['probability'], 2) : 'N/A'), 10) . "|\n";
    }
}
echo "\nDetected " . count($data['result']) . " faces in {$imagePath}\n\n";

// then crop each face
foreach ($data['result'] as $index => $face) {
    // Extract and crop each detected face
    $cropParams = [
        'left'      => $face['box']['x_min'],
        'top'       => $face['box']['y_min'], 
        'width'     => $face['box']['x_max'] - $face['box']['x_min'],
        'height'    => $face['box']['y_max'] - $face['box']['y_min']
    ];

    $outputImagePath = 'api-client/images/extracted/output_face_' . ($index + 1) . '.png';

    // Perform image cropping
    if (!extension_loaded('gd')) {
        echo "GD library not available. Please install it to crop images.\n";
        break;
    }

    // Handle different image extensions
    $imageInfo = pathinfo($imagePath);
    $extension = strtolower($imageInfo['extension']);
    
    switch($extension) {
        case 'png':
            $image = imagecreatefrompng($imagePath);
            break;
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($imagePath);
            break;
        default:
            echo "Unsupported image format: {$extension}\n";
            exit();
    }

    if (!$image) {
        echo 'Error: Could not load the image file.' . "\n";
        continue;
    }

    // Crop the image
    $croppedImage = imagecrop(
        $image,
        [
            'x'      => $cropParams['left'],
            'y'      => $cropParams['top'],
            'width'  => $cropParams['width'],
            'height' => $cropParams['height']
        ]
    );

    if ($croppedImage === FALSE) {
        echo "Failed to crop face " . ($index + 1) . "\n";
        imagedestroy($image);
        continue;
    }

    // Save the cropped image
    imagepng($croppedImage, $outputImagePath);
    imagedestroy($croppedImage);
    echo "\033[32mCropped face " . ($index + 1) . " saved to " . $outputImagePath . "\033[0m\n";
    imagedestroy($image);
}

// Close cURL session
curl_close($ch);


//CALL API TO RECOGNIZE

echo "\n";
echo "╔══════════════════════════════════════════╗\n";
echo "║         FACE RECOGNITION                 ║\n";
echo "╚══════════════════════════════════════════╝\n\n";

// Get all extracted face images
$extractedDir     = 'api-client/images/extracted/';
$extractedFiles   = glob($extractedDir . '*.png');

// Filter out files less than 10KB
$extractedFiles = array_filter($extractedFiles, function($file) {
    $fileSize = filesize($file);
    if ($fileSize < 10 * 1024) { // 10KB in bytes
        echo "\033[31mSkipping " . basename($file) . " - too small (" . round($fileSize/1024, 2) . "KB)\033[0m\n";
        return false;
    }
    return true;
});


$newFaces       = [];
$existingFaces  = [];

foreach($extractedFiles as $faceFile) {
    echo "\nAttempting to recognize face: " . basename($faceFile) . "\n";
    
    // Create new cURL request for each face
    $ch = curl_init();

    $headers = [
        'x-api-key: ' . $recognizeApiKey
    ];

    $postFields = [
        'file' => new CURLFile($faceFile)
    ];

    curl_setopt($ch, CURLOPT_URL, $recognizeUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "HTTP Status Code: " . $statusCode . "\n";

    if(curl_errno($ch)) {
        echo 'Error: ' . curl_error($ch) . "\n";
        continue;
    }

    $result = json_decode($response, true);

    print_r($result);

    if($statusCode != 200) {
        echo $result['message'] . "\n";
        continue;
    }

    // Check and store faces that are likely new (high detection probability, low similarity)

    
    $hasProbability = isset($result['result'][0]['box']['probability']);
    $probability = $hasProbability ? $result['result'][0]['box']['probability'] : 0;
    $hasHighProbability = $probability > $probabilityThreshold;
    
    $hasSubjects = isset($result['result'][0]['subjects']);
    $hasEmptySubjects = empty($result['result'][0]['subjects']);
    
    $hasSimilarity = isset($result['result'][0]['subjects'][0]['similarity']);
    $similarity = $hasSimilarity ? $result['result'][0]['subjects'][0]['similarity'] : 1;
    $hasLowSimilarity = $similarity < $similarityThreshold;
    
    if (
            $hasProbability
        &&
            $hasHighProbability
        && (
                !$hasSubjects
            ||
                $hasEmptySubjects
            ||
                ($hasSimilarity && $hasLowSimilarity)
        )
    ) {
        
        echo "High probability face detection with low similarity match - likely a new person\n";
        echo "+---------------------------+---------------+\n";
        echo "| Metric                   | Value         |\n";
        echo "+---------------------------+---------------+\n";
        echo "| Face detection prob      | " . 
             (is_null($result['result'][0]['box']['probability']) ? 'N/A' : number_format($result['result'][0]['box']['probability'], 4)) . "        |\n";
        echo "| Similarity score         | " . 
             (!isset($result['result'][0]['subjects']) || empty($result['result'][0]['subjects']) ? 'N/A' : number_format($result['result'][0]['subjects'][0]['similarity'], 4)) . "        |\n";
        echo "+---------------------------+---------------+\n";

        // Store face file path for registration
        $newFaces[] = [
            'file_path' => $faceFile,
            'detection_prob' => $result['result'][0]['box']['probability'],
            'similarity' => $result['result'][0]['subjects'][0]['similarity'] ?? '0'
        ];

        echo "Added face from " . basename($faceFile) . " to new faces list\n";
    } else if ($result['result'][0]['box']['probability'] < $probabilityThreshold) {
        echo "\n" . basename($faceFile) . " - Low probability face detection - ignoring\n";
        continue;
    } else {
        echo "Face from " . basename($faceFile) . " likely already exists in database\n";
        $existingFaces[] = [
            'file_path' => $faceFile,
            'detection_prob' => $result['result'][0]['box']['probability'],
            'similarity' => $result['result'][0]['subjects'][0]['similarity'],
            'subject' => $result['result'][0]['subjects'][0]['subject']
        ];
    }

    curl_close($ch);
}

echo "\n+----------------------+------------------+------------------+\n";

// Print out details of new faces found
echo "\nSummary of New Faces Found:\n";
echo "+----------------------+------------------+------------------+\n";
echo "| File                | Detection Prob   | Similarity Score |\n"; 
echo "+----------------------+------------------+------------------+\n";
foreach ($newFaces as $face) {
    echo "| " . str_pad(basename($face['file_path']), 20) . " | " .
         str_pad(is_null($face['detection_prob']) ? 'N/A' : number_format($face['detection_prob'], 4), 16) . " | " .
         str_pad(is_null($face['similarity']) ? 'N/A' : number_format($face['similarity'], 4), 16) . " |\n";
         
}
echo "+----------------------+------------------+------------------+\n";
echo "Total new faces found: " . count($newFaces) . "\n\n";

// Print out details of existing faces
echo "\nSummary of Existing Faces Found:\n";
echo "+----------------------+------------------+------------------+\n";
echo "| File                | Detection Prob   | Similarity Score |\n"; 
echo "+----------------------+------------------+------------------+\n";
foreach ($existingFaces as $face) {
    echo "| " . str_pad(basename($face['file_path']), 20) . " | " .
         str_pad(is_null($face['detection_prob']) ? 'N/A' : number_format($face['detection_prob'], 4), 16) . " | " .
         str_pad(is_null($face['similarity']) ? 'N/A' : number_format($face['similarity'], 4), 16) . " |\n";
}
echo "+----------------------+------------------+------------------+\n";
echo "Total existing faces found: " . count($existingFaces) . "\n\n";

//CALL API TO ADD SUBJECT
echo "\n";
echo "╔══════════════════════════════════════════╗\n";
echo "║   FACE REGISTRATION (FOR NEW FACES ONLY) ║\n";
echo "╚══════════════════════════════════════════╝\n\n";


// Initialize registration stats
$registeredCount = 0;
$failedCount = 0;

foreach ($newFaces as $index => $face) { // Use index instead of reference

    echo "\n";
    echo "      ╔════════════════════╗\n";
    echo "      ║   ADD SUBJECT      ║\n"; 
    echo "      ╚════════════════════╝\n\n";
    
    // Initialize cURL session
    $ch = curl_init();

    // Set headers
    $headers = [
        'content-type: application/json',
        'x-api-key: ' . $recognizeApiKey
    ];

    // Generate a subject name from uuid
    $subjectName = uniqid();
    
    // Prepare the files for upload
    $postFields = [
        //'file' => new CURLFile($face['file_path']),
        'subject' => $subjectName
    ];

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $registerApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Execute the cURL request
    $response = curl_exec($ch);
    
    // Get the HTTP status code
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Print the status code
    echo "HTTP Status Code: " . $statusCode . "\n";

    

    // Check for errors
    if (curl_errno($ch)) {
        echo 'Error registering ' . basename($face['file_path']) . ': ' . curl_error($ch) . "\n";
        $failedCount++;
        curl_close($ch);
        continue;
    }

    // Decode the JSON response
    $add_subject_data = json_decode($response, true);

    if ($statusCode == 201) {
        echo "Successfully registered " . basename($face['file_path']) . " as subject: " . $subjectName . "\n";
        $newFaces[$index]['subject'] = $subjectName; // Modify array using index
    } else {
        echo "Failed to register " . basename($face['file_path']) . ": " . ($add_subject_data['message'] ?? 'Unknown error') . "\n";
        $failedCount++;
        continue;
    }

    curl_close($ch);

    echo "\n";
    echo "      ╔════════════════════╗\n";
    echo "      ║   UPLOAD IMAGE     ║\n"; 
    echo "      ╚════════════════════╝\n\n";

    // Initialize a new cURL session for uploading the subject image
    $ch = curl_init();

    $headers = [
        'content-type: multipart/form-data',
        'x-api-key: ' . $recognizeApiKey
    ];

    // Set cURL options for uploading the image
    $postFields = [
        'file' => new CURLFile($face['file_path']),
        'subject' => $subjectName
    ];

    curl_setopt($ch, CURLOPT_URL, $uploadImageApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Execute the image upload request
    $uploadResponse = curl_exec($ch);
    
    // Get the HTTP status code for the upload
    $uploadStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "Image Upload Status Code: " . $uploadStatusCode . "\n";

    if ($uploadStatusCode != 201) {
        echo "Failed to upload image for subject " . $subjectName . "\n";
        $failedCount++;
        continue;
    }else{
        echo "Successfully uploaded image for subject " . $subjectName . "\n";
        $registeredCount++;
    }
}

// Print registration summary
echo "\nRegistration Summary:\n";
echo "+------------------+-------+\n";
echo "| Status          | Count |\n";
echo "+------------------+-------+\n";
echo "| Successful      | " . str_pad($registeredCount, 5) . " |\n";
echo "| Failed          | " . str_pad($failedCount, 5) . " |\n";
echo "+------------------+-------+\n";
echo "| Total Processed | " . str_pad($registeredCount + $failedCount, 5) . " |\n";
echo "+------------------+-------+\n\n";


//FACE TAGGING
echo "\n";
echo "╔══════════════════════════════════════════╗\n";
echo "║   FACE TAGGING (FOR EXISTING FACES ONLY) ║\n";
echo "╚══════════════════════════════════════════╝\n\n";

// Print out details of existing faces with their subject IDs
echo "\nExisting Faces and Their Subject IDs:\n";
echo "+----------------------+----------------------------------+\n";
echo "| File                | Subject ID                       |\n"; 
echo "+----------------------+----------------------------------+\n";
foreach ($existingFaces as $face) {

    echo "| " . str_pad(basename($face['file_path']), 20) . " | " .
         str_pad($face['subject'], 32) . " |\n";
}
echo "+----------------------+----------------------------------+\n";

//FACE TAGGING
echo "\n";
echo "╔══════════════════════════════════════════╗\n";
echo "║   SAVE TO DATABASE                       ║\n";
echo "╚══════════════════════════════════════════╝\n\n";

// Database connection parameters
$dbHost = 'localhost';
$dbName = 'kocek';
$dbUser = 'root';
$dbPass = '';

// Create database connection
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "\nDatabase connection failed: " . $e->getMessage() . "\n";
    return;
}

// Guard clause - check if we have any faces to process
if (empty($newFaces) && empty($existingFaces)) {
    echo "\nNo faces to insert into database\n";
    return;
}

// Insert new faces data
if (!empty($newFaces)) {

// Print results table
echo "\n";
echo "╔═════════════════════════════════════╗\n";
echo "║    NEW FACE RECORD                  ║\n";
echo "╚═════════════════════════════════════╝\n\n";
    $successCount = 0;
    $failCount = 0;

    echo "\nNew Faces Array Contents:\n";
    echo "+-----------------+------------------+\n";
    echo "| File Path       | Subject         |\n";
    echo "+-----------------+------------------+\n";
    
    foreach ($newFaces as $face) {
        echo "| " . str_pad(basename($face['file_path']), 15) . " | " .
             str_pad($face['subject'] ?? 'N/A', 16) . " |\n";
    }
    
    echo "+-----------------+------------------+\n";

    try {
        // Start transaction
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO faces (`subject`) VALUES (?)");

        if (!$stmt) {
            echo "\nFailed to prepare statement for new faces\n";
            return;
        }

        foreach ($newFaces as $face) {
            if (!isset($face['file_path']) || !isset($face['detection_prob'])) {
                echo "\nSkipping invalid face record - missing required data\n";
                $failCount++;
                continue;
            }

            try {
                $stmt->execute([$face['subject']]);
                $faceId = $pdo->lastInsertId();
                
                $imageStmt = $pdo->prepare(
                    "INSERT INTO face_image (
                        face_id,
                        file_path,
                        x_min, 
                        y_min,
                        x_max,
                        y_max
                    ) VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )"
                );

                if (!$imageStmt) {
                    throw new PDOException("Failed to prepare statement for face image");
                }

                // Extract face index from filename (e.g. output_face_1.png -> index 0)
                $faceIndex = intval(preg_replace('/[^0-9]/', '', basename($face['file_path']))) - 1;
                
                // Get coordinates from original detection result
                $face['x_min'] = $data['result'][$faceIndex]['box']['x_min'];
                $face['y_min'] = $data['result'][$faceIndex]['box']['y_min'];
                $face['x_max'] = $data['result'][$faceIndex]['box']['x_max']; 
                $face['y_max'] = $data['result'][$faceIndex]['box']['y_max'];

                $imageStmt->execute([
                    $faceId,
                    $imagePath,
                    $face['x_min'],
                    $face['y_min'],
                    $face['x_max'],
                    $face['y_max']
                ]);

                echo "\nSuccessfully inserted face image record for face ID: $faceId\n";
                $successCount++;

            } catch(PDOException $e) {
                echo "\nError inserting face record: " . $e->getMessage() . "\n";
                $failCount++;
            }
        }

        // Commit transaction
        $pdo->commit();

        echo "\nNew Faces Summary:\n";
        echo "Successful inserts: $successCount\n";
        echo "Failed inserts: $failCount\n";

    } catch(PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo "\nError occurred, rolling back changes: " . $e->getMessage() . "\n";
    }
}

// Insert existing faces data 
if (!empty($existingFaces)) {

    // Print results table
    echo "\n";
    echo "╔═════════════════════════════════════╗\n";
    echo "║    EXISTING FACE RECORD             ║\n";
    echo "╚═════════════════════════════════════╝\n\n";

    $stmt = $pdo->prepare(
        "INSERT INTO face_image (
            face_id,
            file_path, 
            x_min,
            y_min,
            x_max,
            y_max
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )"
    );
    
    if (!$stmt) {
        echo "\nFailed to prepare statement for existing faces\n";
        return;
    }

    $successCount = 0;
    $failCount = 0;
    $results = [];

    foreach ($existingFaces as $face) {

        // Query face table to get face_id for this subject
        $getFaceId = $pdo->prepare("SELECT id FROM faces WHERE subject = ?");
        if (!$getFaceId) {
            echo "\nFailed to prepare statement for getting face ID\n";
            continue;
        }

        try {
            $getFaceId->execute([$face['subject']]);
            $result = $getFaceId->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $face['face_id'] = $result['id'];
            } else {
                echo "\nNo face ID found for subject: " . $face['subject'] . "\n";
                $failCount++;
                continue;
            }
        } catch(PDOException $e) {
            echo "\nError getting face ID: " . $e->getMessage() . "\n";
            $failCount++;
            continue;
        }

        if (!isset($face['file_path'])) {
            echo "\nSkipping invalid face record - missing required data\n";
            $failCount++;
            continue;
        }

        // Extract face index from filename (e.g. output_face_1.png -> index 0)
        $faceIndex = intval(preg_replace('/[^0-9]/', '', basename($face['file_path']))) - 1;
        
        // Get coordinates from original detection result
        $face['x_min'] = $data['result'][$faceIndex]['box']['x_min'];
        $face['y_min'] = $data['result'][$faceIndex]['box']['y_min'];
        $face['x_max'] = $data['result'][$faceIndex]['box']['x_max']; 
        $face['y_max'] = $data['result'][$faceIndex]['box']['y_max'];

        try {
            $stmt->execute([
                $face['face_id'],
                $imagePath,
                $face['x_min'],
                $face['y_min'], 
                $face['x_max'],
                $face['y_max']
            ]);
            $successCount++;
            $results[] = [
                'subject' => $face['subject'],
                'file' => basename($imagePath),
                'status' => 'Success'
            ];
        } catch(PDOException $e) {
            echo "\nError inserting face record: " . $imagePath . " - " . $e->getMessage() . "\n";
            $failCount++;
            $results[] = [
                'subject' => $face['subject'],
                'file' => basename($imagePath),
                'status' => 'Failed'
            ];
            continue;
        }
    }
    
    echo str_pad("| Subject", 20) . str_pad("| File", 30) . str_pad("| Status", 10) . "|\n";
    echo str_repeat("-", 63) . "\n";
    
    foreach ($results as $result) {
        echo str_pad("| " . $result['subject'], 20) .
             str_pad("| " . $result['file'], 30) .
             str_pad("| " . $result['status'], 10) . "|\n";
    }
    
    echo "\nSummary:\n";
    echo "Successfully inserted: $successCount records\n";
    echo "Failed to insert: $failCount records\n";
    echo "Total processed: " . ($successCount + $failCount) . " records\n\n";
}

// Close database connection
$pdo = null;










