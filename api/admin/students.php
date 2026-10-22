<?php
session_start();
require_once '../includes/config.php';
require_once 'auth_guard.php';
require_once 'includes.php';
$db = getDB();
$error = '';

// Handle status updates (Approve/Reject)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $db->prepare("UPDATE students SET status = 'Active' WHERE id = ?")->execute([$id]);
        flash('success', 'Student account approved successfully.');
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE students SET status = 'Inactive' WHERE id = ?")->execute([$id]);
        flash('success', 'Student account rejected.');
    } elseif ($action === 'pending') {
        $db->prepare("UPDATE students SET status = 'Pending' WHERE id = ?")->execute([$id]);
        flash('success', 'Student account set to pending.');
    }
    header('Location: students.php');
    exit;
}

// Handle adding new student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $name = trim($_POST['name'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $mname = trim($_POST['mname'] ?? '');
    $sid = trim($_POST['student_id'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $sex = $_POST['sex'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    
    if (!$name || !$lname || !$sid || !$age || !$address || !$sex || !$email || !$pass) { 
        $error = 'All fields required.'; 
    } else {
        try {
            $sql = "INSERT INTO students (name, lname, mname, student_id, age, address, sex, email, password, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";
            $db->prepare($sql)->execute([
                $name, $lname, $mname ?: null, $sid, $age, $address, $sex, $email, 
                password_hash($pass, PASSWORD_DEFAULT)
            ]);
            flash('success', "Student $name added successfully!");
            header('Location: students.php');
            exit;
        } catch (PDOException $e) { 
            $error = 'Student ID or Email already exists.'; 
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) { 
    // First get the payment photo path to delete file
    $stmt = $db->prepare("SELECT payment_photo FROM students WHERE id = ?");
    $stmt->execute([intval($_GET['delete'])]);
    $student = $stmt->fetch();
    
    if ($student && $student['payment_photo'] && file_exists('../' . $student['payment_photo'])) {
        unlink('../' . $student['payment_photo']); // Delete the file
    }
    
    $db->prepare("DELETE FROM students WHERE id=?")->execute([intval($_GET['delete'])]); 
    flash('success', 'Student deleted.'); 
    header('Location: students.php'); 
    exit; 
}

// Get all students with additional info
$students = $db->query("
    SELECT s.*, COUNT(es.id) as exam_count 
    FROM students s 
    LEFT JOIN exam_sessions es ON es.student_id = s.id 
    GROUP BY s.id 
    ORDER BY 
        CASE s.status 
            WHEN 'Pending' THEN 1 
            WHEN 'Active' THEN 2 
            WHEN 'Inactive' THEN 3 
        END, 
        s.created_at DESC
")->fetchAll();

$flash = getFlash();

// Count statistics
$pending_count = $db->query("SELECT COUNT(*) FROM students WHERE status = 'Pending'")->fetchColumn();
$active_count = $db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
$inactive_count = $db->query("SELECT COUNT(*) FROM students WHERE status = 'Inactive'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Students — Southern Baptist College</title>
<?php adminCSS(); ?>
<style>
.layout{display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;}
.form-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:22px;box-shadow:var(--shadow);position:sticky;top:20px;max-height:90vh;overflow-y:auto;}
.form-card h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:0.9rem;font-weight:700;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border);}
.btn-submit{width:100%;padding:10px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:0.85rem;cursor:pointer;}
.btn-submit:hover{background:var(--primary-dark,#3730a3);}
.err{background:var(--danger-bg);border:1px solid #fecaca;color:var(--danger);padding:10px 12px;border-radius:8px;font-size:0.82rem;margin-bottom:12px;}

/* Status badges */
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.status-pending { background: #fef3c7; color: #d97706; }
.status-active { background: #dcfce7; color: #16a34a; }
.status-inactive { background: #fee2e2; color: #dc2626; }

/* Action buttons */
.btn-sm {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    margin: 0 2px;
}
.btn-approve { background: #dcfce7; color: #16a34a; }
.btn-reject { background: #fee2e2; color: #dc2626; }
.btn-pending { background: #fef3c7; color: #d97706; }
.btn-view { background: #e0f2fe; color: #0284c7; }
.btn-red { background: #fee2e2; color: #dc2626; }

/* Stats cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}
.stat-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 15px;
    text-align: center;
}
.stat-number {
    font-size: 1.8rem;
    font-weight: 800;
    font-family: 'Plus Jakarta Sans', sans-serif;
    line-height: 1;
}
.stat-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: var(--text-3);
    letter-spacing: 0.5px;
}

/* Payment info */
.payment-info {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.75rem;
    color: var(--text-2);
    margin-top: 4px;
}
.payment-info i {
    color: #00579f;
}

/* Table enhancements */
table td {
    vertical-align: middle;
}
.name-cell {
    font-weight: 600;
}
.student-id {
    font-size: 0.7rem;
    color: var(--text-3);
    /* display: block; */
}

/* PURE CSS MODAL */
.modal-toggle {
    display: none;
}
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
}
.modal-toggle:checked + .modal {
    opacity: 1;
    visibility: visible;
}
.modal-content {
    background: #fff;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    animation: modalSlideIn 0.3s;
}
@keyframes modalSlideIn {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.modal-header h3 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.1rem;
    font-weight: 700;
}
.modal-close {
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-3);
    text-decoration: none;
}
.modal-close:hover {
    color: var(--danger);
}
.modal-body {
    padding: 24px;
}
.modal-footer {
    padding: 20px 24px;
    border-top: 1px solid var(--border);
    text-align: right;
}
.payment-image {
    width: 100%;
    border-radius: 8px;
    border: 1px solid var(--border);
    margin-bottom: 15px;
}
.payment-details {
    background: var(--bg);
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}
.payment-details p {
    margin: 5px 0;
    font-size: 0.9rem;
}
.btn-close {
    padding: 8px 20px;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}
/* Name fields in form */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 8px;
    margin-bottom: 15px;
}
</style>
</head>
<body>
<?php adminSidebar('students'); ?>
<main class="main">
  <div class="page-header">
    <div>
        <div class="page-title">Students Management</div>
        <div class="page-sub">Manage student accounts and registrations</div>
    </div>
  </div>
  
  <!-- Stats Cards -->
  <div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $pending_count ?></div>
        <div class="stat-label">Pending Approval</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $active_count ?></div>
        <div class="stat-label">Active</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $inactive_count ?></div>
        <div class="stat-label">Inactive</div>
    </div>
  </div>
  
  <?php if ($flash): ?><div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  
  <div class="layout">
    <div class="card">
      <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Contact</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Exams</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
          <?php if (empty($students)): ?>
          <tr><td colspan="6"><div class="empty">No students yet.</div></td></tr>
          <?php else: ?>
          <?php foreach ($students as $s): ?>
          <tr>
            <td>
                <div class="name-cell"><?= htmlspecialchars($s['name'] . ' ' . $s['lname']) ?></div>
                <span class="student-id"><?= htmlspecialchars($s['student_id']) ?></span>
                <?php if ($s['mname']): ?>
                    <span class="student-id"><?= htmlspecialchars($s['mname']) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <div><?= htmlspecialchars($s['email']) ?></div>
                <div class="student-id">Age: <?= $s['age'] ?> | <?= htmlspecialchars($s['sex']) ?></div>
                <div class="student-id"><?= htmlspecialchars(substr($s['address'], 0, 30)) ?>...</div>
            </td>
            <td>
                <?php if ($s['payment_proof']): ?>
                <!-- <div class="payment-info">
                    <i class="fa-brands fa-gcash"></i> Ref: <?= htmlspecialchars($s['payment_proof']) ?>
                </div> -->
                <?php endif; ?>
                <?php if ($s['payment_photo']): ?>
                <!-- Modal Trigger -->
                <label for="modal-<?= $s['id'] ?>" class="btn-sm btn-view" style="cursor:pointer;">
                    <i class="fa-regular fa-image"></i> View
                </label>
                
                <!-- Pure CSS Modal -->
                <input type="checkbox" id="modal-<?= $s['id'] ?>" class="modal-toggle">
                <div class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Payment Proof - <?= htmlspecialchars($s['name'] . ' ' . $s['lname']) ?></h3>
                            <label for="modal-<?= $s['id'] ?>" class="modal-close">&times;</label>
                        </div>
                        <div class="modal-body">
                            <img src="../<?= htmlspecialchars($s['payment_photo']) ?>" alt="Payment Proof" class="payment-image" onerror="this.src='../assets/images/no-image.png';">
                            <div class="payment-details">
                                <p><strong>Student:</strong> <?= htmlspecialchars($s['name'] . ' ' . $s['mname'] . ' ' . $s['lname']) ?></p>
                                <p><strong>Student ID:</strong> <?= htmlspecialchars($s['student_id']) ?></p>
                                <!-- <p><strong>GCash Ref #:</strong> <?= htmlspecialchars($s['payment_proof']) ?></p> -->
                                <p><strong>Registered:</strong> <?= date('F d, Y h:i A', strtotime($s['created_at'])) ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="status-badge status-<?= strtolower($s['status']) ?>">
                                        <?= $s['status'] ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <label style="
    justify-content: center;
    display: flex;"for="modal-<?= $s['id'] ?>" class="btn-close">Close</label>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </td>
            <td>
                <span class="status-badge status-<?= strtolower($s['status']) ?>">
                    <?= $s['status'] ?>
                </span>
            </td>
            <td><?= $s['exam_count'] ?></td>
            <td style="white-space: nowrap;">
                <?php if ($s['status'] === 'Pending'): ?>
                    <a href="?action=approve&id=<?= $s['id'] ?>" class="btn-sm btn-approve" onclick="return confirm('Approve this student account?')">
                        <i class="fa-regular fa-circle-check"></i> Approve
                    </a>
                    <a href="?action=reject&id=<?= $s['id'] ?>" class="btn-sm btn-reject" onclick="return confirm('Reject this student account?')">
                        <i class="fa-regular fa-circle-xmark"></i> Reject
                    </a>
                <?php elseif ($s['status'] === 'Active'): ?>
                    <a href="?action=pending&id=<?= $s['id'] ?>" class="btn-sm btn-pending" onclick="return confirm('Set this account to pending?')">
                        <i class="fa-regular fa-clock"></i> Pending
                    </a>
                    <a href="?action=reject&id=<?= $s['id'] ?>" class="btn-sm btn-reject" onclick="return confirm('Deactivate this account?')">
                        <i class="fa-regular fa-circle-xmark"></i> Deactivate
                    </a>
                <?php else: ?>
                    <a href="?action=approve&id=<?= $s['id'] ?>" class="btn-sm btn-approve" onclick="return confirm('Activate this account?')">
                        <i class="fa-regular fa-circle-check"></i> Activate
                    </a>
                    <a href="?action=pending&id=<?= $s['id'] ?>" class="btn-sm btn-pending" onclick="return confirm('Set this account to pending?')">
                        <i class="fa-regular fa-clock"></i> Pending
                    </a>
                <?php endif; ?>
                <a href="?delete=<?= $s['id'] ?>" class="btn-sm btn-red" onclick="return confirm('Delete student? This will also remove their payment proof.')">
                    <i class="fa-regular fa-trash-can"></i> Delete
                </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <div class="form-card">
      <h3><i class="fa-regular fa-plus"></i> Add New Student</h3>
      <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      
      <form method="POST">
        <input type="hidden" name="add_student" value="1">
        
        <!-- Name Fields (3 columns) -->
        <div class="form-row">
            <div class="form-group" style="margin-bottom: 0;">
                <label>First Name *</label>
                <input type="text" name="name" placeholder="Juan" required>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Middle Name</label>
                <input type="text" name="mname" placeholder="Dela Cruz">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Last Name *</label>
                <input type="text" name="lname" placeholder="Dela Cruz" required>
            </div>
        </div>
        
        <!-- Student ID and Age -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-bottom: 15px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label>Student ID *</label>
                <input type="text" name="student_id" placeholder="STU001" required>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Age *</label>
                <input type="number" name="age" placeholder="18" min="1" max="100" required>
            </div>
        </div>
        
        <!-- Address -->
        <div class="form-group">
            <label>Address *</label>
            <textarea name="address" placeholder="Complete address" rows="2" required></textarea>
        </div>
        
        <!-- Sex and Email -->
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px; margin-bottom: 15px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label>Sex *</label>
                <select name="sex" required>
                    <option value="">Select</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Email *</label>
                <input type="email" name="email" placeholder="juan@example.com" required>
            </div>
        </div>
        
        <!-- Password -->
        <div class="form-group">
            <label>Password *</label>
            <input type="password" name="password" placeholder="Set password" required>
        </div>
        
        <button type="submit" class="btn-submit">
            <i class="fa-regular fa-pen-to-square"></i> Add Student
        </button>
      </form>
      
      <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border);">
          <p style="font-size: 0.8rem; color: var(--text-3);">
              <i class="fa-regular fa-circle-info"></i> Students added manually are automatically ACTIVE. Students who register through the registration form start as PENDING.
          </p>
      </div>
    </div>
  </div>
</main>

<script>
// Auto-hide flash message
setTimeout(function() {
    const flash = document.querySelector('.flash');
    if (flash) {
        flash.style.transition = 'opacity 0.5s';
        flash.style.opacity = '0';
        setTimeout(() => flash.remove(), 500);
    }
}, 3000);
</script>
</body>
</html>