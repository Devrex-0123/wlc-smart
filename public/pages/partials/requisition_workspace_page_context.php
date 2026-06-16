<?php
require_once __DIR__ . '/session_access_guard.php';
require_once __DIR__ . '/../../../app/helpers/dean_office_context.php';

$db = Database::connect();
$deanCtx = cwirms_bootstrap_requisition_workspace_page_context($db);

$isDepartmentLogin = !empty($deanCtx['is_department_login']);
$user = $deanCtx['user'];
$currentUser = $deanCtx['currentUser'];
$deanOfficeId = (int) $deanCtx['office_id'];
$deptName = (string) $deanCtx['office_name'];
$deanActingUserId = isset($deanCtx['acting_user_id']) ? (int) $deanCtx['acting_user_id'] : 0;

$user['office_name'] = $deptName;
$user['office_id'] = $deanOfficeId;
$currentUser['office_name'] = $deptName;
$currentUser['office_id'] = $deanOfficeId;
