<?php
// Configure the base directory for uploads
$uploadDir = __DIR__ . '/stores';

$maxFileSize = 20 * 1024 * 1024; // 20 MB

$allowedMIME = [
	"image/apng", // Animated PNG images .apng
	"image/avif", // AV1 Image File .avif
	"image/bmp", // Bitmap Image .bmp
	"image/gif", // Graphics Interchange Format .gif
	"image/jpeg", // .jpg, .jpeg, .jfif
	"image/png", // Portable Network Graphics .png
	"image/svg+xml", // Scalable Vector Graphics .svg
	"image/webp", // Web Picture .webp
];

$apiUrl = 'https://vikreta.vercel.app/api/v1/stores/';

// Ensure the uploads directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    // Create .htaccess file to restrict access
    $htaccessContent = '<FilesMatch "\.php$">Deny from all</FilesMatch> <FilesMatch ".*">Allow from all</FilesMatch> Options -Indexes';
    file_put_contents($uploadDir . '/.htaccess', $htaccessContent);
}

// Check if store_id and files are provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['store_id']) && isset($_FILES['files'])) {
    $storeId = filter_var($_POST['store_id'], FILTER_SANITIZE_NUMBER_INT); // Sanitize store_id
    $createdBy = filter_var($_POST['created_by'], FILTER_SANITIZE_NUMBER_INT); // Sanitize created_by

    // Ensure the store_id is a number
    if (!is_numeric($storeId)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid store_id.']);
        exit;
    }

    // Ensure the created_by is a number
    if (!is_numeric($createdBy)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid created_by.']);
        exit;
    }

    // Ensure only allowed MIME types are uploaded
    foreach ($_FILES['files']['type'] as $type) {
        if (!in_array($type, $allowedMIME)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid file type.']);
            exit;
        }
    }

    // Ensure uploaded file sizes are within the limit
    foreach ($_FILES['files']['size'] as $size) {
        if ($size > $maxFileSize) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'File size exceeds the limit.']);
            exit;
        }
    }

    // Validate uploaded files
    foreach ($_FILES['files']['error'] as $error) {
        if ($error !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Failed to upload file.']);
            exit;
        }
    }

    $storeDir = $uploadDir . '/' . $storeId;

    // Ensure the store-specific directory exists
    if (!is_dir($storeDir)) {
        mkdir($storeDir, 0755, true);
        // Create .htaccess file to restrict access
        $htaccessContent = '<FilesMatch "\.php$">Deny from all</FilesMatch> <FilesMatch ".*">Allow from all</FilesMatch> Options -Indexes';
        file_put_contents($uploadDir . '/.htaccess', $htaccessContent);
    }

    $files = $_FILES['files'];
    $uploadedDocs = [];

    foreach ($files['name'] as $key => $name) {
        $fileName = basename($name);
        $targetFile = $storeDir . '/' . $fileName;

        if (move_uploaded_file($files['tmp_name'][$key], $targetFile)) {
            $uploadedDocs[] = [
                'url' => 'https://' . $_SERVER['HTTP_HOST'] . str_replace($_SERVER['DOCUMENT_ROOT'], '', $targetFile),
                'size' => $files['size'][$key],
                'name' => $fileName,
                'uploader' => "php_script",
                'mime_type' => $files['type'][$key],
			    'created_by' => $createdBy,
				'created_at' => date('Y-m-d H:i:s'),
				'cdn_provider' => "hostinger_web_server",
            ];
        }
    }

    // Response
    if (!empty($uploadedDocs)) {
        // Create documents in the database
        $ch = curl_init($apiUrl . $storeId . '/documents');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($uploadedDocs));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer YOUR_API_TOKEN' // Replace with your actual API token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);

        if ($httpCode !== 200) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Failed to send data to external API.']);
            exit;
        }

        echo json_encode(['ok' => true, 'documents' => $uploadedDocs]);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'No files were uploaded or all failed.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
}
?>
