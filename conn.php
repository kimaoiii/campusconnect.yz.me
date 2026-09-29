<?php
declare(strict_types=1);

session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = 'dsb';
const DB_USER = 'root';
const DB_PASSWORD = '';
const ADMIN_EMAIL = 'adminbcc@gmail.com';
const ADMIN_PASSWORD = 'adminbcc';
const OFFICER_EMAIL = 'access@gmail.com';
const OFFICER_PASSWORD = 'access123';

function redirectToLogin(string $message, bool $showLogin = false): never
{
    $hash = $showLogin ? '#login-form' : '';
    header('Location: student-login.html?message=' . rawurlencode($message) . $hash);
    exit;
}

function redirectToOfficerLogin(string $message): never
{
    header('Location: officer-login.html?message=' . rawurlencode($message));
    exit;
}

$connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($connection->connect_errno) {
    http_response_code(500);
    exit('Database connection failed. Check the MySQL settings in conn.php.');
}

$connection->set_charset('utf8mb4');

$sectionColumn = $connection->query("SHOW COLUMNS FROM student LIKE 'section'");
if ($sectionColumn && $sectionColumn->num_rows === 0) {
    $connection->query(
        "ALTER TABLE student ADD COLUMN section VARCHAR(60) NOT NULL DEFAULT '' AFTER year_section"
    );
}

$connection->query(
    'CREATE TABLE IF NOT EXISTS officer (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$connection->query(
    'CREATE TABLE IF NOT EXISTS officer_login_records (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        officer_id INT UNSIGNED NOT NULL,
        officer_name VARCHAR(120) NOT NULL,
        officer_email VARCHAR(190) NOT NULL,
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (officer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$connection->query(
    'CREATE TABLE IF NOT EXISTS direct_messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id INT UNSIGNED NOT NULL,
        subject VARCHAR(180) NOT NULL,
        message TEXT NOT NULL,
        sent_by VARCHAR(190) NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        INDEX (student_id),
        INDEX (sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$defaultOfficerEmail = OFFICER_EMAIL;
$defaultOfficerPassword = OFFICER_PASSWORD;
$defaultOfficerCheck = $connection->prepare('SELECT id FROM officer WHERE email = ? LIMIT 1');
$defaultOfficerCheck->bind_param('s', $defaultOfficerEmail);
$defaultOfficerCheck->execute();
$defaultOfficerExists = $defaultOfficerCheck->get_result()->num_rows > 0;
$defaultOfficerCheck->close();

if (!$defaultOfficerExists) {
    $defaultOfficerStatement = $connection->prepare(
        'INSERT INTO officer (full_name, email, password) VALUES (?, ?, ?)'
    );
    $defaultOfficerName = 'Admin Officer';
    $defaultOfficerHash = password_hash($defaultOfficerPassword, PASSWORD_DEFAULT);
    $defaultOfficerStatement->bind_param(
        'sss',
        $defaultOfficerName,
        $defaultOfficerEmail,
        $defaultOfficerHash
    );
    $defaultOfficerStatement->execute();
    $defaultOfficerStatement->close();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'student_session') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['student_id'])) {
        http_response_code(401);
        echo json_encode(['authenticated' => false]);
        exit;
    }

    $statement = $connection->prepare('SELECT full_name, email FROM student WHERE id = ? LIMIT 1');
    $statement->bind_param('i', $_SESSION['student_id']);
    $statement->execute();
    $student = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$student) {
        http_response_code(401);
        echo json_encode(['authenticated' => false]);
        exit;
    }

    echo json_encode([
        'authenticated' => true,
        'name' => $student['full_name'],
        'email' => $student['email']
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'officer_session') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['officer_id'])) {
        http_response_code(401);
        echo json_encode(['authenticated' => false]);
        exit;
    }

    $statement = $connection->prepare(
        'SELECT full_name, email FROM officer WHERE id = ? LIMIT 1'
    );
    $officerId = (int) $_SESSION['officer_id'];
    $statement->bind_param('i', $officerId);
    $statement->execute();
    $officer = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$officer) {
        http_response_code(401);
        echo json_encode(['authenticated' => false]);
        exit;
    }

    echo json_encode([
        'authenticated' => true,
        'name' => $officer['full_name'],
        'email' => $officer['email']
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'admin_students') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['admin_authenticated'])) {
        http_response_code(401);
        echo json_encode(['authenticated' => false]);
        exit;
    }

    $students = [];
    $result = $connection->query(
        'SELECT id, full_name, email, student_id, course, year_section, section
         FROM student ORDER BY full_name ASC'
    );

    while ($student = $result->fetch_assoc()) {
        $students[] = $student;
    }

    echo json_encode(['authenticated' => true, 'students' => $students]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'student_messages') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['student_id'])) {
        http_response_code(401);
        echo json_encode(['authenticated' => false]);
        exit;
    }

    $studentId = (int) $_SESSION['student_id'];
    $statement = $connection->prepare(
        'SELECT id, subject, message, sent_by, sent_at, is_read
         FROM direct_messages WHERE student_id = ? ORDER BY sent_at DESC'
    );
    $statement->bind_param('i', $studentId);
    $statement->execute();
    $result = $statement->get_result();
    $messages = [];

    while ($message = $result->fetch_assoc()) {
        $messages[] = $message;
    }

    $statement->close();
    echo json_encode(['authenticated' => true, 'messages' => $messages]);
    exit;
}

if ($action === 'logout') {
    $logoutPage = isset($_SESSION['officer_id'])
        ? 'officer-login.html'
        : (isset($_SESSION['admin_authenticated']) ? 'admin-login.html' : 'student-login.html');
    session_unset();
    session_destroy();
    header('Location: ' . $logoutPage);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToLogin('Invalid request.');
}

if ($action === 'admin_delete_student') {
    if (!isset($_SESSION['admin_authenticated'])) {
        header('Location: admin-login.html?message=' . rawurlencode('Please sign in as administrator.'));
        exit;
    }

    $studentId = filter_var($_POST['student_id'] ?? '', FILTER_VALIDATE_INT);

    if (!$studentId) {
        header('Location: admin-dashboard.html?message=' . rawurlencode('Invalid student selected.'));
        exit;
    }

    $connection->begin_transaction();
    $messageStatement = $connection->prepare('DELETE FROM direct_messages WHERE student_id = ?');
    $messageStatement->bind_param('i', $studentId);
    $messagesDeleted = $messageStatement->execute();
    $messageStatement->close();

    $studentStatement = $connection->prepare('DELETE FROM student WHERE id = ?');
    $studentStatement->bind_param('i', $studentId);
    $studentDeleted = $studentStatement->execute() && $studentStatement->affected_rows === 1;
    $studentStatement->close();

    if ($messagesDeleted && $studentDeleted) {
        $connection->commit();
        header('Location: admin-dashboard.html?message=' . rawurlencode('Student removed successfully.'));
        exit;
    }

    $connection->rollback();
    header('Location: admin-dashboard.html?message=' . rawurlencode('The student could not be removed.'));
    exit;
}

if ($action === 'admin_send_message') {
    if (!isset($_SESSION['admin_authenticated'])) {
        header('Location: admin-login.html?message=' . rawurlencode('Please sign in as administrator.'));
        exit;
    }

    $studentId = filter_var($_POST['student_id'] ?? '', FILTER_VALIDATE_INT);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$studentId || $subject === '' || $message === '') {
        header('Location: admin-dashboard.html?message=' . rawurlencode('Choose a student and complete the message.'));
        exit;
    }

    $statement = $connection->prepare(
        'INSERT INTO direct_messages (student_id, subject, message, sent_by)
         SELECT id, ?, ?, ? FROM student WHERE id = ?'
    );
    $sentBy = $_SESSION['admin_email'] ?? ADMIN_EMAIL;
    $statement->bind_param('sssi', $subject, $message, $sentBy, $studentId);

    if (!$statement->execute() || $statement->affected_rows !== 1) {
        $statement->close();
        header('Location: admin-dashboard.html?message=' . rawurlencode('The message could not be sent.'));
        exit;
    }

    $statement->close();
    header('Location: admin-dashboard.html?message=' . rawurlencode('Message sent successfully.'));
    exit;
}

if ($action === 'register') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $yearSection = trim($_POST['year_section'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $studentId === '' ||
        $course === '' || $yearSection === '' || $section === '' || strlen($password) < 6) {
        redirectToLogin('Please complete all fields and use a password with at least 6 characters.');
    }

    if ($password !== $confirmPassword) {
        redirectToLogin('Passwords do not match.');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $statement = $connection->prepare(
           'INSERT INTO student (full_name, email, student_id, course, year_section, section, password)
            VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
        $statement->bind_param('sssssss', $fullName, $email, $studentId, $course, $yearSection, $section, $hashedPassword);

    if (!$statement->execute()) {
        if ($connection->errno === 1062) {
            redirectToLogin('That email or student ID is already registered.');
        }

        http_response_code(500);
        exit('Registration failed.');
    }

    $statement->close();

    redirectToLogin('Account created successfully. Please sign in.', true);
}

if ($action === 'login') {
    $studentId = trim($_POST['student_id'] ?? '');
    $password = $_POST['password'] ?? '';

    $statement = $connection->prepare('SELECT id, full_name, password FROM student WHERE student_id = ? LIMIT 1');
    $statement->bind_param('s', $studentId);
    $statement->execute();
    $student = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$student || !password_verify($password, $student['password'])) {
        redirectToLogin('Invalid student ID or password.', true);
    }

    session_regenerate_id(true);
    $_SESSION['student_id'] = (int) $student['id'];
    $_SESSION['student_name'] = $student['full_name'];
    header('Location: student-dashboard.html');
    exit;
}

if ($action === 'officer_login') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        redirectToOfficerLogin('Please enter the officer name, email, and password.');
    }

    $statement = $connection->prepare(
        'SELECT id, full_name, email, password FROM officer WHERE email = ? LIMIT 1'
    );
    $statement->bind_param('s', $email);
    $statement->execute();
    $officer = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$officer || !password_verify($password, $officer['password'])) {
        redirectToOfficerLogin('Invalid officer email or password.');
    }

    $nameStatement = $connection->prepare(
        'UPDATE officer SET full_name = ? WHERE id = ?'
    );
    $officerId = (int) $officer['id'];
    $nameStatement->bind_param('si', $fullName, $officerId);

    if (!$nameStatement->execute()) {
        $nameStatement->close();
        redirectToOfficerLogin('The officer name could not be saved.');
    }

    $nameStatement->close();

    $recordStatement = $connection->prepare(
        'INSERT INTO officer_login_records (officer_id, officer_name, officer_email)
         VALUES (?, ?, ?)'
    );
    $recordStatement->bind_param('iss', $officerId, $fullName, $officer['email']);

    if (!$recordStatement->execute()) {
        $recordStatement->close();
        redirectToOfficerLogin('The officer login could not be recorded.');
    }

    $recordStatement->close();

    session_regenerate_id(true);
    $_SESSION['officer_id'] = $officerId;
    $_SESSION['officer_name'] = $fullName;
    $_SESSION['officer_email'] = $officer['email'];
    header('Location: officer-dashboard.html');
    exit;
}

if ($action === 'admin_login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email !== ADMIN_EMAIL || !hash_equals(ADMIN_PASSWORD, $password)) {
        header('Location: admin-login.html?message=' . rawurlencode('Invalid administrator credentials.'));
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_email'] = ADMIN_EMAIL;
    header('Location: admin-dashboard.html');
    exit;
}

redirectToLogin('Unknown request.');
?>