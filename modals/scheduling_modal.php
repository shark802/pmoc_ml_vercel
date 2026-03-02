<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" role="dialog" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="couple_scheduling_add.php" id="scheduleForm">
                <?php require_once __DIR__ . '/../includes/csrf_helper.php'; ?>
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="addScheduleModalLabel">Add New Session</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="couple_code" class="required-field">Couple</label>
                        <select class="form-control" id="couple_code" name="couple_code" required>
                            <option value="">Select Couple</option>
                            <?php
                            $stmt = $conn->prepare("
                                SELECT 
                                    ca.access_code, 
                                    GROUP_CONCAT(
                                        CONCAT(cp.first_name, ' ', cp.last_name, ' (', TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE()), ')') 
                                        ORDER BY cp.sex DESC 
                                        SEPARATOR ' & '
                                    ) as couple_names,
                                    IFNULL(MIN(TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE())), 100) as min_age
                                FROM couple_access ca
                                JOIN couple_profile cp ON ca.access_id = cp.access_id
                                WHERE ca.code_status IN ('used', 'active')
                                AND ca.access_id NOT IN (
                                    SELECT DISTINCT s.access_id 
                                    FROM scheduling s 
                                    WHERE (s.session_date >= CURDATE() 
                                           OR s.status IN ('pending', 'confirmed', 'reschedule_requested'))
                                )
                                GROUP BY ca.access_id
                                HAVING COUNT(cp.couple_profile_id) = 2
                                ORDER BY couple_names
                            ");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($row['access_code']) . '" 
                                        data-min-age="' . $row['min_age'] . '">'
                                    . htmlspecialchars($row['couple_names']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="session_date" class="required-field">Session Date</label>
                        <div class="row">
                            <div class="col-md-4">
                                <select class="form-control" id="session_month" name="session_month" required>
                                    <option value="">Month</option>
                                    <option value="1">January</option>
                                    <option value="2">February</option>
                                    <option value="3">March</option>
                                    <option value="4">April</option>
                                    <option value="5">May</option>
                                    <option value="6">June</option>
                                    <option value="7">July</option>
                                    <option value="8">August</option>
                                    <option value="9">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="number" class="form-control" id="session_day" name="session_day"
                                    min="1" max="31" placeholder="Day" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="session_year" name="session_year"
                                    value="<?= date('Y') ?>" readonly>
                            </div>
                        </div>
                        <small class="form-text text-muted">Only Tuesdays and Fridays are schedule for orientation and counseling</small>
                    </div>

                    <div class="form-group">
                        <label class="required-field d-block">Session Type</label>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="type_orientation" name="session_type" value="Orientation" class="custom-control-input" required>
                            <label class="custom-control-label" for="type_orientation">Orientation (8AM-12PM)</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="type_both" name="session_type" value="Orientation + Counseling" class="custom-control-input" required>
                            <label class="custom-control-label" for="type_both">Orientation + Counseling (Full Day)</label>
                        </div>
                        <small id="sessionTypeHelp" class="form-text text-muted"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div class="modal fade" id="editScheduleModal" tabindex="-1" role="dialog" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="couple_scheduling_edit.php" id="editScheduleForm">
                <?php require_once __DIR__ . '/../includes/csrf_helper.php'; ?>
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="editScheduleModalLabel">Edit Schedule</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="edit_form_error" class="alert alert-danger" style="display:none;"></div>
                    <input type="hidden" name="schedule_id" id="edit_schedule_id">
                    
                    <div class="form-group">
                        <label>Couple</label>
                        <input type="text" class="form-control" id="edit_couple_names" readonly>
                        <small id="edit_age_warning" class="form-text text-danger" style="display: none;">Includes partner(s) age 25 or below - Counseling required</small>
                    </div>

                    <div class="form-group">
                        <label class="required-field">Session Date</label>
                        <div class="row">
                            <div class="col-md-4">
                                <select class="form-control" id="edit_session_month" name="session_month" required>
                                    <option value="">Month</option>
                                    <option value="1">January</option>
                                    <option value="2">February</option>
                                    <option value="3">March</option>
                                    <option value="4">April</option>
                                    <option value="5">May</option>
                                    <option value="6">June</option>
                                    <option value="7">July</option>
                                    <option value="8">August</option>
                                    <option value="9">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="number" class="form-control" id="edit_session_day" name="session_day"
                                    min="1" max="31" placeholder="Day" required>
                            </div>
                            <div class="col-md-4">
                                <input type="number" class="form-control" id="edit_session_year" name="session_year" 
                                       min="<?= date('Y') ?>" max="<?= date('Y') + 5 ?>" placeholder="Year" required>
                            </div>
                        </div>
                        <small class="form-text text-muted">Only Tuesdays and Fridays are schedule for orientation and counseling</small>
                        <div id="edit_date_error" class="invalid-feedback d-block" style="display:none;"></div>
                    </div>

                    <div class="form-group">
                        <label class="required-field d-block">Session Type</label>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="edit_type_orientation" name="session_type" value="Orientation" class="custom-control-input" required>
                            <label class="custom-control-label" for="edit_type_orientation">Orientation (8AM-12PM)</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="edit_type_both" name="session_type" value="Orientation + Counseling" class="custom-control-input" required>
                            <label class="custom-control-label" for="edit_type_both">Orientation + Counseling (Full Day)</label>
                        </div>
                        <small id="edit_sessionTypeHelp" class="form-text text-muted"></small>
                    </div>

                    <div class="form-group">
                        <label class="required-field d-block">Status</label>
                        <span id="edit_status_badge" class="badge badge-secondary">Pending</span>
                        <input type="hidden" name="status" id="edit_status" value="pending">
                        <input type="hidden" name="reschedule" id="edit_reschedule_flag" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

