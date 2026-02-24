<?php
// api/attachments.php
require_once '../config/database.php';

$issueId = $_GET['issueId'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET' && $issueId) {
        $stmt = $pdo->prepare("SELECT * FROM attachments WHERE issue_id = :id ORDER BY uploaded_at DESC");
        $stmt->execute([':id' => $issueId]);
        echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);

    } elseif ($method === 'POST' && $issueId) {
        // 1. Check 5-file limit (Conversion of AttachmentService logic)
        $count = $pdo->prepare("SELECT COUNT(*) FROM attachments WHERE issue_id = :id");
        $count->execute([':id' => $issueId]);
        if ($count->fetchColumn() >= 5) {
            echo json_encode(["success" => false, "message" => "Maximum of 5 attachments reached."]);
            exit;
        }

        // 2. Handle File Upload
        $file = $_FILES['file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uniqueName = uniqid() . '.' . $ext; // PHP version of GUID
        $targetPath = "../uploads/" . $uniqueName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $sql = "INSERT INTO attachments (issue_id, filename, original_name, file_path, file_size, file_type) 
                    VALUES (:issueId, :fname, :oname, :path, :size, :type)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':issueId' => $issueId,
                ':fname'   => $uniqueName,
                ':oname'   => $file['name'],
                ':path'    => $targetPath,
                ':size'    => $file['size'],
                ':type'    => $file['type']
            ]);
            echo json_encode(["success" => true, "message" => "File uploaded successfully."]);
        }
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>