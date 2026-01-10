<?php
header("Content-Type: application/json");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli("localhost", "root", "", "trading_db");
} catch (Exception $e) {
    die(json_encode(["error" => "Connection failed: " . $e->getMessage()]));
}

$action = $_GET['action'] ?? '';

// --- JOURNAL ENTRIES LOGIC ---

if ($action == 'read') {
    $result = $conn->query("SELECT * FROM journal_entries ORDER BY id DESC");
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
}

if ($action == 'save') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'];
        
        $check = $conn->prepare("SELECT id FROM journal_entries WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        
        $mistakes = json_encode($data['mistakes'] ?? []);
        $images = json_encode($data['images'] ?? []);
        $notes = $data['notes'] ?? ''; 
        
        // New Fields
        $entry_price = $data['entry_price'] ?? 0;
        $exit_price = $data['exit_price'] ?? 0;
        $lots = $data['lots'] ?? 0;
        $pnl = $data['pnl'] ?? 0;

        if ($exists) {
            $stmt = $conn->prepare("UPDATE journal_entries SET asset=?, focus_area=?, notes=?, mistakes=?, neg_notes=?, plan_bias=?, key_level=?, plan_notes=?, images=?, entry_price=?, exit_price=?, lots=?, pnl=? WHERE id=?");
            $stmt->bind_param("sssssssssddidi", $data['asset'], $data['focus_area'], $notes, $mistakes, $data['neg_notes'], $data['plan_bias'], $data['key_level'], $data['plan_notes'], $images, $entry_price, $exit_price, $lots, $pnl, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO journal_entries (id, entry_type, asset, timestamp_str, focus_area, notes, mistakes, neg_notes, plan_bias, key_level, plan_notes, images, entry_price, exit_price, lots, pnl) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssssssssddid", $id, $data['entry_type'], $data['asset'], $data['timestamp_str'], $data['focus_area'], $notes, $mistakes, $data['neg_notes'], $data['plan_bias'], $data['key_level'], $data['plan_notes'], $images, $entry_price, $exit_price, $lots, $pnl);
        }
        $stmt->execute();
        echo json_encode(["success" => true]);
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
}

if ($action == 'delete') {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM journal_entries WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(["success" => true]);
}

// --- CHECKLIST LOGIC ---

if ($action == 'checklist_read') {
    $result = $conn->query("SELECT id, rule_text as text, is_checked as checked FROM checklist_rules ORDER BY id ASC");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['checked'] = (bool)$row['checked'];
        $data[] = $row;
    }
    echo json_encode($data);
}

if ($action == 'checklist_add') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!empty($data['text'])) {
        $stmt = $conn->prepare("INSERT INTO checklist_rules (rule_text, is_checked) VALUES (?, 0)");
        $stmt->bind_param("s", $data['text']);
        $stmt->execute();
        echo json_encode(["success" => true]);
    }
}

if ($action == 'checklist_toggle') {
    $id = (int)$_GET['id'];
    $conn->query("UPDATE checklist_rules SET is_checked = NOT is_checked WHERE id = $id");
    echo json_encode(["success" => true]);
}

if ($action == 'checklist_delete') {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM checklist_rules WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(["success" => true]);
}

if ($action == 'checklist_reset') {
    $conn->query("UPDATE checklist_rules SET is_checked = 0");
    echo json_encode(["success" => true]);
}
// --- IMPORT PROXY LOGIC ---

if ($action == 'fetch_sheet') {
    $url = $_GET['url'] ?? '';
    
    if (empty($url)) {
        echo json_encode(["error" => "No URL provided"]);
        exit;
    }

    // 1. Extract Spreadsheet ID and GID from the standard link
    // Link format: https://docs.google.com/spreadsheets/d/LONG_ID_HERE/edit?gid=12345
    
    preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $idMatch);
    preg_match('/[?&]gid=([0-9]+)/', $url, $gidMatch);

    $spreadsheetId = $idMatch[1] ?? '';
    $gid = $gidMatch[1] ?? '0'; // Default to first sheet if GID missing

    if (!$spreadsheetId) {
        echo json_encode(["error" => "Invalid Google Sheet Link"]);
        exit;
    }

    // 2. Construct the CSV Export URL
    $csvUrl = "https://docs.google.com/spreadsheets/d/$spreadsheetId/export?format=csv&gid=$gid";

    // 3. Fetch the data
    $csvData = @file_get_contents($csvUrl);

    if ($csvData === FALSE) {
        echo json_encode(["error" => "Could not fetch sheet. Ensure it is set to 'Anyone with the link'."]);
    } else {
        // Return the raw CSV text safely
        echo json_encode(["success" => true, "csv" => $csvData]);
    }
}
?>