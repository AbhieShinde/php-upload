<?php
// Configure the base directory for uploads
$uploadDir = __DIR__ . '/stores';

// Ensure the uploads directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Check if store_id and files are provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['store_id']) && isset($_FILES['files'])) {
    $storeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['store_id']); // Sanitize store_id
    $storeDir = $uploadDir . '/' . $storeId;

    // Ensure the store-specific directory exists
    if (!is_dir($storeDir)) {
        mkdir($storeDir, 0755, true);
    }

    $files = $_FILES['files'];
    $uploadSuccess = [];

    foreach ($files['name'] as $key => $name) {
        $tmpName = $files['tmp_name'][$key];
        $error = $files['error'][$key];
        $size = $files['size'][$key];
        $maxFileSize = 20 * 1024 * 1024; // 20 MB

        // Basic file validation
        if ($error === UPLOAD_ERR_OK && $size <= $maxFileSize) {
            $fileName = basename($name);
            $targetFile = $storeDir . '/' . $fileName;

            if (move_uploaded_file($tmpName, $targetFile)) {
                $uploadSuccess[] = $fileName;
            }
        }
    }

    // Response
    if (!empty($uploadSuccess)) {
        echo json_encode(['status' => 'success', 'uploaded' => $uploadSuccess]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No files were uploaded or all failed.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}
?>
