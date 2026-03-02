<?php
require_once '../includes/session.php';
require_once '../includes/conn.php';

$id = intval($_GET['id'] ?? 0);
$log = null;
if ($id > 0) {
  $stmt = $conn->prepare("\n    SELECT 
      l.*, 
      MAX(COALESCE(l.session_type, s.session_type, s2.session_type, '')) AS session_type,
      GROUP_CONCAT(CONCAT(cp.first_name,' ',cp.last_name) ORDER BY cp.sex DESC SEPARATOR ' & ') AS couple_name,
      MAX(CASE WHEN cp.sex = 'Male' THEN TRIM(cp.contact_number) END) AS male_mobile,
      MAX(CASE WHEN cp.sex = 'Female' THEN TRIM(cp.contact_number) END) AS female_mobile
    FROM sms_logs l
    LEFT JOIN scheduling s ON s.schedule_id = l.schedule_id
    /* Fallback: derive by access_id and log date if schedule_id not linked */
    LEFT JOIN scheduling s2 ON s2.access_id = l.access_id AND DATE(s2.session_date) = DATE(l.created_at)
    LEFT JOIN couple_profile cp ON cp.access_id = l.access_id
    WHERE l.id = ?
    GROUP BY l.id
  ");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $log = $stmt->get_result()->fetch_assoc();
}
if (!$log) {
  echo '<div class="modal fade" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Not Found</h5><button class="close" data-dismiss="modal">&times;</button></div><div class="modal-body"><p>Log not found.</p></div></div></div></div>';
  exit;
}
?>
<div class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">SMS Log</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <style>
          .sms-detail dt{color:#6c757d;font-weight:600}
          .sms-detail dd{margin-bottom:10px}
          .sms-pre{background:#f8f9fa;border:1px solid #e9ecef;border-radius:4px;padding:12px}
        </style>
        <dl class="row sms-detail">
          <dt class="col-sm-3">Time</dt><dd class="col-sm-9"><?= htmlspecialchars($log['created_at']) ?></dd>
          <dt class="col-sm-3">Male Mobile</dt><dd class="col-sm-9"><span class="font-weight-bold"><?= htmlspecialchars($log['male_mobile'] ?: '-') ?></span></dd>
          <dt class="col-sm-3">Female Mobile</dt><dd class="col-sm-9"><span class="font-weight-bold"><?= htmlspecialchars($log['female_mobile'] ?: '-') ?></span></dd>
          <dt class="col-sm-3">Session Type</dt><dd class="col-sm-9"><span class="font-weight-bold"><?= htmlspecialchars($log['session_type'] ?: '-') ?></span></dd>
          <dt class="col-sm-3">Couple Name</dt><dd class="col-sm-9"><span class="font-weight-bold"><?= htmlspecialchars($log['couple_name'] ?: '-') ?></span></dd>
          <dt class="col-sm-3">Reminder</dt><dd class="col-sm-9"><span class="badge badge-info"><?= htmlspecialchars($log['run_label']) ?></span></dd>
          <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge badge-<?= $log['success']? 'success':'danger' ?>"><?= $log['success']? 'Sent':'Failed' ?></span></dd>
          <dt class="col-sm-3">Message</dt><dd class="col-sm-9"><pre class="sms-pre mb-0" style="white-space:pre-wrap;"><?= htmlspecialchars($log['message']) ?></pre></dd>
        </dl>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
