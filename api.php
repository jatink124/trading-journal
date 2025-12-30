<?php
header("Content-Type: application/json");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Database configuration
    $conn = new mysqli("localhost", "root", "", "trading_db");
} catch (Exception $e) {
    die(json_encode(["error" => "Connection failed: " . $e->getMessage()]));
}

$action = $_GET['action'] ?? '';

// FETCH ENTRIES
if ($action == 'read') {
    $result = $conn->query("SELECT * FROM journal_entries ORDER BY id DESC");
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
}

// SAVE OR UPDATE ENTRY
if ($action == 'save') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'];
        
        // Check if entry exists for Update
        $check = $conn->query("SELECT id FROM journal_entries WHERE id = $id");
        
        // Prepare data for SQL (converting arrays to JSON strings)
        $mistakes = json_encode($data['mistakes'] ?? []);
        $images = json_encode($data['images'] ?? []);

        if ($check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE journal_entries SET asset=?, focus_area=?, notes=?, mistakes=?, neg_notes=?, plan_bias=?, key_level=?, plan_notes=?, images=? WHERE id=?");
            $stmt->bind_param("sssssssssi", 
                $data['asset'], 
                $data['focus_area'], 
                $data['notes'], 
                $mistakes, 
                $data['neg_notes'], 
                $data['plan_bias'], 
                $data['key_level'], 
                $data['plan_notes'], 
                $images, 
                $id
            );
        } else {
            $stmt = $conn->prepare("INSERT INTO journal_entries (id, entry_type, asset, timestamp_str, focus_area, notes, mistakes, neg_notes, plan_bias, key_level, plan_notes, images) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssssssss", 
                $id, 
                $data['entry_type'], 
                $data['asset'], 
                $data['timestamp_str'], 
                $data['focus_area'], 
                $data['notes'], 
                $mistakes, 
                $data['neg_notes'], 
                $data['plan_bias'], 
                $data['key_level'], 
                $data['plan_notes'], 
                $images
            );
        }
        
        $stmt->execute();
        echo json_encode(["success" => true]);
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// DELETE ENTRY
if ($action == 'delete') {
    $id = $_GET['id'];
    $conn->query("DELETE FROM journal_entries WHERE id = $id");
    echo json_encode(["success" => true]);
}
?>