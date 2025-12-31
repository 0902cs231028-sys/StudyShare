<?php
session_start();
require_once '../includes/db_connect.php';

// 1. STRICT SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 2. MODERATION ACTIONS
$msg = "";
if (isset($_GET['action']) && isset($_GET['id'])) {
    $target_id = $_GET['id'];
    
    if ($_GET['action'] == 'ban') {
        $conn->prepare("UPDATE users SET is_blocked = 1 WHERE id = :id AND role != 'admin'")->execute(['id' => $target_id]);
        $msg = "User Hard-Banned.";
    }
    if ($_GET['action'] == 'shadow_ban') {
        $conn->prepare("UPDATE users SET is_shadow_banned = 1 WHERE id = :id")->execute(['id' => $target_id]);
        $msg = "User Shadow-Banned.";
    }
    if ($_GET['action'] == 'restore') {
        $conn->prepare("UPDATE users SET is_blocked = 0, is_shadow_banned = 0 WHERE id = :id")->execute(['id' => $target_id]);
        $msg = "User Access Restored.";
    }
    if ($_GET['action'] == 'delete_file') {
        $stmt = $conn->prepare("SELECT stored_name FROM files WHERE id = :id");
        $stmt->execute(['id' => $target_id]);
        $file = $stmt->fetch();
        if ($file) {
            $path = "../uploads/" . $file['stored_name'];
            if (file_exists($path)) unlink($path);
            $conn->prepare("DELETE FROM files WHERE id = :id")->execute(['id' => $target_id]);
            $msg = "File Permanently Deleted.";
        }
    }
}

// 3. WARNING THREAT HANDLER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_warning'])) {
    $stmt = $conn->prepare("UPDATE users SET pending_warning = :warn WHERE id = :id");
    $stmt->execute(['warn' => $_POST['warning_text'], 'id' => $_POST['target_user']]);
    $msg = "Warning sent to user's dashboard.";
}

// 4. DATA FETCHING
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$reported_count = $conn->query("SELECT COUNT(*) FROM files WHERE report_count > 0")->fetchColumn();
$users_list = $conn->query("SELECT * FROM users WHERE role != 'admin' ORDER BY id DESC")->fetchAll();
$reported_files = $conn->query("SELECT f.*, u.username as uploader FROM files f JOIN users u ON f.user_id = u.id WHERE f.report_count > 0 ORDER BY f.report_count DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Command | ShareStudy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0b0e14; color: #e0e6ed; font-family: 'Inter', sans-serif; }
        .glass-panel { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; }
        .stat-card { border-left: 4px solid #ffc107; padding: 1.5rem; }
        .upload-area { border: 2px dashed #ffc107; background: rgba(255,193,7,0.05); cursor: pointer; }
        .chat-monitor { height: 450px; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 8px; padding: 10px; }
        .date-divider { text-align: center; margin: 15px 0; }
        .date-divider span { background: #1a1a1a; padding: 2px 12px; border-radius: 10px; font-size: 0.6rem; color: #888; border: 1px solid #333; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark px-4 border-bottom border-secondary">
    <a class="navbar-brand fw-bold text-warning" href="#"><i class="fas fa-shield-alt"></i> COMMAND CENTER</a>
    <div class="d-flex align-items-center">
        <span class="me-3 text-warning fw-bold small">ADMIN: <?php echo $username; ?></span>
        <a href="../dashboard.php" class="btn btn-sm btn-outline-light me-2">Exit to Site</a>
        <a href="../logout.php" class="btn btn-sm btn-danger">Logout</a>
    </div>
</nav>

<div class="container-fluid py-4">
    <?php if($msg): ?><div class="alert alert-warning text-center small"><?php echo $msg; ?></div><?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="glass-panel stat-card"><h6>Total Students</h6><h3><?php echo $total_users; ?></h3></div></div>
        <div class="col-md-3"><div class="glass-panel stat-card" style="border-color:#f44336"><h6>Reported Files</h6><h3><?php echo $reported_count; ?></h3></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="glass-panel p-4 mb-4">
                <h6 class="text-warning mb-3"><i class="fas fa-upload"></i> Global Resource Upload</h6>
                <div class="upload-area p-3 text-center rounded" id="dropZone">
                    <p class="mb-0" id="fileLabel">Drag & Drop file to publish</p>
                    <input type="file" id="fileInput" hidden>
                </div>
                <div id="uploadDetails" class="mt-3 d-none">
                    <div class="row g-2">
                        <div class="col-md-8"><input type="text" id="fileDesc" class="form-control bg-dark text-white border-secondary" placeholder="Description"></div>
                        <div class="col-md-4">
                            <select id="fileSubject" class="form-select bg-dark text-white border-secondary">
                                <option value="DSA">DSA</option><option value="DBMS">DBMS</option><option value="TOC">TOC</option><option value="OS">OS</option><option value="Cyber Security">Cyber Security</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-warning w-100 mt-2 fw-bold" id="confirmUploadBtn">UPLOAD AS ADMIN</button>
                </div>
            </div>

            <div class="glass-panel p-4 mb-4">
                <h6>Student Management</h6>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>User</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach($users_list as $u): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                                <td><?php echo $u['is_blocked'] ? '<span class="badge bg-danger">Banned</span>' : ($u['is_shadow_banned'] ? '<span class="badge bg-warning text-dark">Shadowed</span>' : '<span class="badge bg-success">Active</span>'); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-info" onclick="adminResetPass(<?php echo $u['id']; ?>, '<?php echo $u['username']; ?>')" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        
                                        <button class="btn btn-sm btn-outline-warning" onclick="openWarn(<?php echo $u['id']; ?>, '<?php echo $u['username']; ?>')" title="Send Warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </button>

                                        <?php if(!$u['is_shadow_banned']): ?>
                                            <a href="dashboard.php?action=shadow_ban&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-dark" title="Shadow Ban">
                                                <i class="fas fa-user-secret"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if(!$u['is_blocked']): ?>
                                            <a href="dashboard.php?action=ban&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hard ban user?')" title="Hard Ban">
                                                <i class="fas fa-user-times"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if($u['is_blocked'] || $u['is_shadow_banned']): ?>
                                            <a href="dashboard.php?action=restore&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-success" title="Lift Restrictions">
                                                <i class="fas fa-user-check"></i> Restore
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-panel p-4 d-flex flex-column" style="height: 100%;">
                <h6 class="text-warning"><i class="fas fa-satellite"></i> Message Monitor</h6>
                <hr class="border-secondary">
                <div class="chat-monitor flex-grow-1" id="chatMonitor"></div>
                <div class="mt-3">
                    <input type="text" id="adminMsg" class="form-control bg-dark text-white border-secondary mb-2" placeholder="Admin Broadcast...">
                    <button class="btn btn-warning w-100 fw-bold" id="sendBtn">SEND BROADCAST</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="warnModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content bg-dark border-secondary"><form method="POST">
        <div class="modal-header"><h5>Warning for <span id="warnUser"></span></h5></div>
        <div class="modal-body">
            <input type="hidden" name="target_user" id="warnId">
            <textarea name="warning_text" class="form-control bg-dark text-white border-secondary" rows="3" placeholder="Enter threat message..." required></textarea>
        </div>
        <div class="modal-footer"><button type="submit" name="send_warning" class="btn btn-danger w-100">SEND THREAT</button></div>
    </form></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const chatMonitor = document.getElementById('chatMonitor');

    function fetchMonitor() {
        fetch('../message.php?action=fetch').then(res => res.json()).then(res => {
            let html = '';
            let currentLoopDate = null;
            res.data.forEach(m => {
                if (m.date_raw !== currentLoopDate) {
                    currentLoopDate = m.date_raw;
                    html += `<div class="date-divider"><span>${m.date_raw}</span></div>`;
                }
                html += `
                <div class="small mb-2 pb-1 border-bottom border-secondary">
                    <b class="${m.is_admin ? 'text-warning' : 'text-info'}">${m.username}:</b> 
                    <span class="text-light">${m.message}</span>
                    <small class="text-muted" style="font-size:0.5rem">${m.time}</small>
                    <i class="fas fa-trash-alt text-danger float-end" style="cursor:pointer" onclick="delMsg(${m.id})"></i>
                </div>`;
            });
            const isAtBottom = chatMonitor.scrollHeight - chatMonitor.scrollTop <= chatMonitor.clientHeight + 100;
            chatMonitor.innerHTML = html;
            if (isAtBottom) chatMonitor.scrollTop = chatMonitor.scrollHeight;
        });
    }

    const dropZone = document.getElementById('dropZone');
    const fInput = document.getElementById('fileInput');
    dropZone.onclick = () => fInput.click();
    fInput.onchange = (e) => {
        if(e.target.files.length) {
            document.getElementById('fileLabel').innerText = e.target.files[0].name;
            document.getElementById('uploadDetails').classList.remove('d-none');
        }
    };
    document.getElementById('confirmUploadBtn').onclick = function() {
        const fd = new FormData();
        fd.append('file', fInput.files[0]);
        fd.append('description', document.getElementById('fileDesc').value);
        fd.append('subject', document.getElementById('fileSubject').value);
        fetch('../upload_file.php', { method: 'POST', body: fd }).then(() => location.reload());
    };

    function adminResetPass(id, name) {
        const newPass = prompt("Enter new password for " + name + ":");
        if(!newPass) return;
        const fd = new FormData();
        fd.append('target_user_id', id);
        fd.append('new_password', newPass);
        fetch('../change_password.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => alert(data.message));
    }
    
    function delMsg(id) {
        if(!confirm('Delete?')) return;
        const fd = new FormData(); fd.append('action', 'delete'); fd.append('msg_id', id);
        fetch('../message.php', { method: 'POST', body: fd }).then(() => fetchMonitor());
    }

    document.getElementById('sendBtn').onclick = () => {
        const input = document.getElementById('adminMsg');
        if(!input.value.trim()) return;
        const fd = new FormData(); fd.append('action', 'send'); fd.append('message', '📢 ADMIN: ' + input.value);
        fetch('../message.php', { method: 'POST', body: fd }).then(() => { input.value = ''; fetchMonitor(); });
    };

    function openWarn(id, name) {
        document.getElementById('warnId').value = id;
        document.getElementById('warnUser').innerText = name;
        new bootstrap.Modal('#warnModal').show();
    }

    setInterval(fetchMonitor, 10000); 
    fetchMonitor();
</script>
</body>
</html>
