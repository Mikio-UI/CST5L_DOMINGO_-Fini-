<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

require_once __DIR__ . '/../public/database.config.php';
$db = $conn;


// Auto-migrate: rename 'tag' to 'tag_class' if old schema exists
$cols = [];
$r = $db->query("SHOW COLUMNS FROM tasks");
while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
if (in_array('tag', $cols) && !in_array('tag_class', $cols)) {
    $db->query("ALTER TABLE tasks CHANGE COLUMN `tag` `tag_class` VARCHAR(30) NOT NULL DEFAULT 'tag-blue'");
}
// Add tag_class if missing entirely
if (!in_array('tag_class', $cols) && !in_array('tag', $cols)) {
    $db->query("ALTER TABLE tasks ADD COLUMN tag_class VARCHAR(30) NOT NULL DEFAULT 'tag-blue'");
}

$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

switch ($action) {

    case 'create':
        $title     = trim($body['title']     ?? '');
        $status    = trim($body['status']    ?? 'todo');
        $tag       = trim($body['tag_class'] ?? 'tag-blue');
        $tag_label = trim($body['tag_label'] ?? 'Task');
        $due_date  = trim($body['due_date']  ?? '') ?: null;

        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Title is required']);
            exit();
        }

        $stmt = $db->prepare(
            "INSERT INTO tasks (user_id, title, status, tag_class, tag_label, due_date)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('isssss', $user_id, $title, $status, $tag, $tag_label, $due_date);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $db->insert_id]);
        } else {
            echo json_encode(['success' => false, 'error' => $db->error]);
        }
        break;

    case 'update':
        $id        = (int)  ($body['id']        ?? 0);
        $title     = trim($body['title']        ?? '');
        $status    = trim($body['status']       ?? 'todo');
        $tag       = trim($body['tag_class']    ?? 'tag-blue');
        $tag_label = trim($body['tag_label']    ?? 'Task');
        $due_date  = trim($body['due_date']     ?? '') ?: null;

        if (!$id || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
            exit();
        }

        $stmt = $db->prepare(
            "UPDATE tasks
             SET title=?, status=?, tag_class=?, tag_label=?, due_date=?
             WHERE id=? AND user_id=?"
        );
        $stmt->bind_param('sssssii', $title, $status, $tag, $tag_label, $due_date, $id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $db->error]);
        }
        break;

    case 'delete':
        $id = (int) ($body['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Invalid id']);
            exit();
        }

        $stmt = $db->prepare("DELETE FROM tasks WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $db->error]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}

$db->close();