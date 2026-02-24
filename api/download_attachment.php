<?php
require_once '../config/database.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die("No attachment ID provided.");
}

try {
    // Fetch attachment info from DB
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $file = $stmt->fetch();

    if ($file) {
        // The path where the actual file is stored
        $filePath = '../uploads/' . $file['file_name'];

        if (file_exists($filePath)) {
            // Set headers to trigger download
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            
            readfile($filePath);
            exit;
        } else {
            die("File not found on server.");
        }
    } else {
        die("Record not found in database.");
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>