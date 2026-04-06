<?php
declare(strict_types=1);

function find_user_by_username(PDO $pdo, string $username): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function find_user_by_id(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function touch_last_login(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([$userId]);
}

function log_audit(
    PDO $pdo,
    ?int $actorUserId,
    string $action,
    string $tableName,
    ?int $recordId,
    string $description,
    ?int $schoolId = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (actor_user_id, school_id, action, table_name, record_id, description, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $actorUserId,
        $schoolId,
        $action,
        $tableName,
        $recordId,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    ]);
}

function get_all_schools(PDO $pdo, bool $includeDisabled = true): array
{
    $sql = 'SELECT * FROM schools';

    if (!$includeDisabled) {
        $sql .= ' WHERE is_active = 1';
    }

    $sql .= ' ORDER BY code';

    return $pdo->query($sql)->fetchAll();
}

function get_users(PDO $pdo, ?string $role = null): array
{
    $sql = '
        SELECT
            u.*,
            GROUP_CONCAT(DISTINCT hs.name ORDER BY hs.name SEPARATOR ", ") AS head_schools,
            GROUP_CONCAT(DISTINCT ts.name ORDER BY ts.name SEPARATOR ", ") AS teacher_schools
        FROM users u
        LEFT JOIN head_school_assignments hsa
            ON hsa.head_user_id = u.id AND hsa.is_active = 1
        LEFT JOIN schools hs
            ON hs.id = hsa.school_id
        LEFT JOIN teacher_school_assignments tsa
            ON tsa.teacher_user_id = u.id AND tsa.is_active = 1
        LEFT JOIN schools ts
            ON ts.id = tsa.school_id
    ';

    $params = [];

    if ($role !== null) {
        $sql .= ' WHERE u.role = ?';
        $params[] = $role;
    }

    $sql .= ' GROUP BY u.id ORDER BY FIELD(u.role, "superadmin", "head", "teacher"), u.full_name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function get_active_users_by_role(PDO $pdo, string $role): array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE role = ? AND is_enabled = 1 ORDER BY full_name');
    $stmt->execute([$role]);

    return $stmt->fetchAll();
}

function get_students(PDO $pdo, ?array $schoolIds = null): array
{
    if ($schoolIds !== null && $schoolIds === []) {
        return [];
    }

    $sql = '
        SELECT st.*, sc.name AS school_name, sc.code AS school_code
        FROM students st
        JOIN schools sc ON sc.id = st.school_id
    ';

    $params = [];

    if ($schoolIds !== null && $schoolIds !== []) {
        $sql .= ' WHERE st.school_id IN (' . sql_placeholders(count($schoolIds)) . ')';
        $params = $schoolIds;
    }

    $sql .= ' ORDER BY st.full_name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function get_courses(PDO $pdo, ?array $schoolIds = null, bool $onlyActive = false): array
{
    if ($schoolIds !== null && $schoolIds === []) {
        return [];
    }

    $sql = '
        SELECT
            c.*,
            sc.name AS school_name,
            sc.code AS school_code,
            (
                SELECT COUNT(*)
                FROM course_enrollments ce
                WHERE ce.course_id = c.id AND ce.is_active = 1
            ) AS total_students,
            (
                SELECT COUNT(*)
                FROM attendance_sessions ats
                WHERE ats.course_id = c.id
            ) AS total_sessions
        FROM courses c
        JOIN schools sc ON sc.id = c.school_id
        WHERE 1 = 1
    ';

    $params = [];

    if ($onlyActive) {
        $sql .= ' AND c.is_active = 1';
    }

    if ($schoolIds !== null && $schoolIds !== []) {
        $sql .= ' AND c.school_id IN (' . sql_placeholders(count($schoolIds)) . ')';
        $params = $schoolIds;
    }

    $sql .= ' ORDER BY sc.code, c.name, c.section';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function get_teacher_schools(PDO $pdo, int $teacherUserId): array
{
    $stmt = $pdo->prepare(
        'SELECT sc.*
         FROM teacher_school_assignments tsa
         JOIN schools sc ON sc.id = tsa.school_id
         WHERE tsa.teacher_user_id = ? AND tsa.is_active = 1
         ORDER BY sc.code'
    );
    $stmt->execute([$teacherUserId]);

    return $stmt->fetchAll();
}

function get_head_schools(PDO $pdo, int $headUserId): array
{
    $stmt = $pdo->prepare(
        'SELECT sc.*
         FROM head_school_assignments hsa
         JOIN schools sc ON sc.id = hsa.school_id
         WHERE hsa.head_user_id = ? AND hsa.is_active = 1
         ORDER BY sc.code'
    );
    $stmt->execute([$headUserId]);

    return $stmt->fetchAll();
}

function get_head_school_ids(PDO $pdo, int $headUserId): array
{
    return array_map(
        static fn (array $school): int => (int) $school['id'],
        get_head_schools($pdo, $headUserId)
    );
}

function get_teacher_courses(PDO $pdo, int $teacherUserId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            c.*,
            sc.name AS school_name,
            sc.code AS school_code,
            tca.period_label,
            (
                SELECT COUNT(*)
                FROM course_enrollments ce
                WHERE ce.course_id = c.id AND ce.is_active = 1
            ) AS total_students,
            (
                SELECT COUNT(*)
                FROM attendance_sessions ats
                WHERE ats.course_id = c.id
            ) AS total_sessions
         FROM teacher_course_assignments tca
         JOIN courses c ON c.id = tca.course_id AND c.is_active = 1
         JOIN schools sc ON sc.id = c.school_id
         WHERE tca.teacher_user_id = ? AND tca.is_active = 1
         ORDER BY sc.code, c.name, c.section'
    );
    $stmt->execute([$teacherUserId]);

    return $stmt->fetchAll();
}

function get_teacher_course(PDO $pdo, int $teacherUserId, int $courseId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            c.*,
            sc.name AS school_name,
            sc.code AS school_code,
            tca.period_label
         FROM teacher_course_assignments tca
         JOIN courses c ON c.id = tca.course_id AND c.is_active = 1
         JOIN schools sc ON sc.id = c.school_id
         WHERE tca.teacher_user_id = ? AND c.id = ? AND tca.is_active = 1
         LIMIT 1'
    );
    $stmt->execute([$teacherUserId, $courseId]);
    $course = $stmt->fetch();

    return $course ?: null;
}

function get_course_students(PDO $pdo, int $courseId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            st.*,
            ce.period_label
         FROM course_enrollments ce
         JOIN students st ON st.id = ce.student_id
         WHERE ce.course_id = ? AND ce.is_active = 1 AND st.is_active = 1
         ORDER BY st.full_name'
    );
    $stmt->execute([$courseId]);

    return $stmt->fetchAll();
}

function get_attendance_session(PDO $pdo, int $courseId, string $date): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            ats.*,
            u.full_name AS teacher_name
         FROM attendance_sessions ats
         JOIN users u ON u.id = ats.teacher_user_id
         WHERE ats.course_id = ? AND ats.attendance_date = ?
         LIMIT 1'
    );
    $stmt->execute([$courseId, $date]);
    $session = $stmt->fetch();

    return $session ?: null;
}

function get_attendance_records_by_session(PDO $pdo, int $sessionId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            ar.*,
            st.student_code,
            st.full_name
         FROM attendance_records ar
         JOIN students st ON st.id = ar.student_id
         WHERE ar.session_id = ?
         ORDER BY st.full_name'
    );
    $stmt->execute([$sessionId]);

    return $stmt->fetchAll();
}

function get_recent_attendance_sessions(PDO $pdo, array $filters = [], int $limit = 8): array
{
    if (array_key_exists('school_ids', $filters) && $filters['school_ids'] === []) {
        return [];
    }

    $sql = '
        SELECT
            ats.*,
            c.name AS course_name,
            c.code AS course_code,
            sc.name AS school_name,
            u.full_name AS teacher_name,
            (
                SELECT COUNT(*)
                FROM attendance_records ar
                WHERE ar.session_id = ats.id
            ) AS total_records,
            (
                SELECT COUNT(*)
                FROM attendance_records ar
                WHERE ar.session_id = ats.id AND ar.status = "absent"
            ) AS total_absences
        FROM attendance_sessions ats
        JOIN courses c ON c.id = ats.course_id
        JOIN schools sc ON sc.id = c.school_id
        JOIN users u ON u.id = ats.teacher_user_id
        WHERE 1 = 1
    ';

    $params = [];

    if (!empty($filters['teacher_user_id'])) {
        $sql .= ' AND ats.teacher_user_id = ?';
        $params[] = (int) $filters['teacher_user_id'];
    }

    if (!empty($filters['school_ids'])) {
        $schoolIds = array_map('intval', $filters['school_ids']);
        $sql .= ' AND c.school_id IN (' . sql_placeholders(count($schoolIds)) . ')';
        $params = array_merge($params, $schoolIds);
    }

    $sql .= ' ORDER BY ats.attendance_date DESC, ats.created_at DESC LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function get_risk_students_for_teacher(PDO $pdo, int $teacherUserId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            c.id AS course_id,
            c.code AS course_code,
            c.name AS course_name,
            st.id AS student_id,
            st.student_code,
            st.full_name AS student_name,
            COUNT(ar.id) AS total_classes,
            COALESCE(SUM(CASE WHEN ar.status = "absent" THEN 1 ELSE 0 END), 0) AS absences
         FROM teacher_course_assignments tca
         JOIN courses c ON c.id = tca.course_id
         JOIN course_enrollments ce ON ce.course_id = c.id AND ce.is_active = 1
         JOIN students st ON st.id = ce.student_id AND st.is_active = 1
         LEFT JOIN attendance_sessions ats ON ats.course_id = c.id
         LEFT JOIN attendance_records ar
            ON ar.session_id = ats.id AND ar.student_id = st.id
         WHERE tca.teacher_user_id = ? AND tca.is_active = 1
         GROUP BY c.id, st.id
         HAVING total_classes > 0 AND (absences * 100 / total_classes) > 30
         ORDER BY course_name, student_name'
    );
    $stmt->execute([$teacherUserId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['absence_rate'] = absence_rate($row);
    }

    return $rows;
}

function get_risk_students_for_schools(PDO $pdo, array $schoolIds): array
{
    if ($schoolIds === []) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT
            sc.name AS school_name,
            c.id AS course_id,
            c.code AS course_code,
            c.name AS course_name,
            st.id AS student_id,
            st.student_code,
            st.full_name AS student_name,
            COUNT(ar.id) AS total_classes,
            COALESCE(SUM(CASE WHEN ar.status = "absent" THEN 1 ELSE 0 END), 0) AS absences
         FROM courses c
         JOIN schools sc ON sc.id = c.school_id
         JOIN course_enrollments ce ON ce.course_id = c.id AND ce.is_active = 1
         JOIN students st ON st.id = ce.student_id AND st.is_active = 1
         LEFT JOIN attendance_sessions ats ON ats.course_id = c.id
         LEFT JOIN attendance_records ar
            ON ar.session_id = ats.id AND ar.student_id = st.id
         WHERE c.school_id IN (' . sql_placeholders(count($schoolIds)) . ')
         GROUP BY c.id, st.id
         HAVING total_classes > 0 AND (absences * 100 / total_classes) > 30
         ORDER BY sc.name, c.name, st.full_name'
    );
    $stmt->execute($schoolIds);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['absence_rate'] = absence_rate($row);
    }

    return $rows;
}

function get_course_report(PDO $pdo, int $courseId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            st.id,
            st.student_code,
            st.full_name,
            st.email,
            st.semester,
            COUNT(ar.id) AS total_classes,
            COALESCE(SUM(CASE WHEN ar.status = "present" THEN 1 ELSE 0 END), 0) AS presents,
            COALESCE(SUM(CASE WHEN ar.status = "late" THEN 1 ELSE 0 END), 0) AS lates,
            COALESCE(SUM(CASE WHEN ar.status = "justified" THEN 1 ELSE 0 END), 0) AS justified,
            COALESCE(SUM(CASE WHEN ar.status = "absent" THEN 1 ELSE 0 END), 0) AS absences
         FROM course_enrollments ce
         JOIN students st ON st.id = ce.student_id AND st.is_active = 1
         LEFT JOIN attendance_sessions ats ON ats.course_id = ce.course_id
         LEFT JOIN attendance_records ar
            ON ar.session_id = ats.id AND ar.student_id = st.id
         WHERE ce.course_id = ? AND ce.is_active = 1
         GROUP BY st.id
         ORDER BY st.full_name'
    );
    $stmt->execute([$courseId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['absence_rate'] = absence_rate($row);
        $row['attendance_rate'] = $row['total_classes'] > 0
            ? round((((int) $row['total_classes'] - (int) $row['absences']) / (int) $row['total_classes']) * 100, 1)
            : 0.0;
        $row['attendance_state'] = absence_state((float) $row['absence_rate']);
    }

    return $rows;
}

function get_head_dashboard_stats(PDO $pdo, array $schoolIds): array
{
    if ($schoolIds === []) {
        return [
            'schools' => 0,
            'courses' => 0,
            'students' => 0,
            'sessions' => 0,
        ];
    }

    $placeholders = sql_placeholders(count($schoolIds));

    $queries = [
        'courses' => 'SELECT COUNT(*) FROM courses WHERE school_id IN (' . $placeholders . ') AND is_active = 1',
        'students' => 'SELECT COUNT(*) FROM students WHERE school_id IN (' . $placeholders . ') AND is_active = 1',
        'sessions' => '
            SELECT COUNT(*)
            FROM attendance_sessions ats
            JOIN courses c ON c.id = ats.course_id
            WHERE c.school_id IN (' . $placeholders . ')
        ',
    ];

    $stats = ['schools' => count($schoolIds)];

    foreach ($queries as $key => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($schoolIds);
        $stats[$key] = (int) $stmt->fetchColumn();
    }

    return $stats;
}

function get_superadmin_stats(PDO $pdo): array
{
    return [
        'schools' => (int) $pdo->query('SELECT COUNT(*) FROM schools WHERE is_active = 1')->fetchColumn(),
        'users' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_enabled = 1')->fetchColumn(),
        'students' => (int) $pdo->query('SELECT COUNT(*) FROM students WHERE is_active = 1')->fetchColumn(),
        'courses' => (int) $pdo->query('SELECT COUNT(*) FROM courses WHERE is_active = 1')->fetchColumn(),
        'sessions' => (int) $pdo->query('SELECT COUNT(*) FROM attendance_sessions')->fetchColumn(),
    ];
}

function get_teacher_course_assignments(PDO $pdo): array
{
    return $pdo->query(
        'SELECT
            tca.*,
            u.full_name AS teacher_name,
            c.name AS course_name,
            c.code AS course_code,
            c.school_id AS school_id,
            sc.name AS school_name
         FROM teacher_course_assignments tca
         JOIN users u ON u.id = tca.teacher_user_id
         JOIN courses c ON c.id = tca.course_id
         JOIN schools sc ON sc.id = c.school_id
         ORDER BY teacher_name, school_name, course_name'
    )->fetchAll();
}

function get_teacher_school_assignments(PDO $pdo): array
{
    return $pdo->query(
        'SELECT
            tsa.*,
            u.full_name AS teacher_name,
            sc.name AS school_name,
            sc.code AS school_code
         FROM teacher_school_assignments tsa
         JOIN users u ON u.id = tsa.teacher_user_id
         JOIN schools sc ON sc.id = tsa.school_id
         ORDER BY teacher_name, sc.code'
    )->fetchAll();
}

function get_head_school_assignments(PDO $pdo): array
{
    return $pdo->query(
        'SELECT
            hsa.*,
            u.full_name AS head_name,
            sc.name AS school_name,
            sc.code AS school_code
         FROM head_school_assignments hsa
         JOIN users u ON u.id = hsa.head_user_id
         JOIN schools sc ON sc.id = hsa.school_id
         ORDER BY sc.code, head_name'
    )->fetchAll();
}

function get_course_enrollments(PDO $pdo): array
{
    return $pdo->query(
        'SELECT
            ce.*,
            st.full_name AS student_name,
            st.student_code,
            c.name AS course_name,
            c.code AS course_code,
            c.school_id AS school_id,
            sc.name AS school_name
         FROM course_enrollments ce
         JOIN students st ON st.id = ce.student_id
         JOIN courses c ON c.id = ce.course_id
         JOIN schools sc ON sc.id = c.school_id
         ORDER BY c.name, student_name'
    )->fetchAll();
}

function get_audit_logs(PDO $pdo, ?array $schoolIds = null, int $limit = 120): array
{
    if ($schoolIds !== null && $schoolIds === []) {
        return [];
    }

    $sql = '
        SELECT
            al.*,
            u.full_name AS actor_name,
            sc.name AS school_name
        FROM audit_logs al
        LEFT JOIN users u ON u.id = al.actor_user_id
        LEFT JOIN schools sc ON sc.id = al.school_id
        WHERE 1 = 1
    ';

    $params = [];

    if ($schoolIds !== null && $schoolIds !== []) {
        $sql .= ' AND (al.school_id IN (' . sql_placeholders(count($schoolIds)) . ') OR al.school_id IS NULL)';
        $params = $schoolIds;
    }

    $sql .= ' ORDER BY al.created_at DESC LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}
