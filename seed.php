<?php
require_once __DIR__.'/vendor/autoload.php';
use Educify\DB;

if(file_exists(__DIR__.'/educify.db')){
    echo "educify.db already exists, remove it to reseed\n";
    exit;
}
$dotenv = null;
if(file_exists(__DIR__.'/.env')){
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}
$db = new DB(__DIR__);
$pdo = $db->getPDO();

$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, hashed_password TEXT, full_name TEXT, is_tutor INTEGER DEFAULT 0)');
$pdo->exec('CREATE TABLE tutors (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, display_name TEXT, bio TEXT, rate_per_hour REAL, subjects TEXT, location_lat REAL, location_lng REAL, verified INTEGER DEFAULT 0)');
$pdo->exec('CREATE TABLE availability (id INTEGER PRIMARY KEY AUTOINCREMENT, tutor_id INTEGER, weekday INTEGER, start_time TEXT, end_time TEXT)');
$pdo->exec('CREATE TABLE lessons (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INTEGER, tutor_id INTEGER, subject TEXT, start_datetime TEXT, duration_minutes INTEGER, amount_paid REAL, status TEXT)');
$pdo->exec('CREATE TABLE promo_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, discount_percent INTEGER, active INTEGER DEFAULT 1, expires_at TEXT)');

$pwd = password_hash('password', PASSWORD_DEFAULT);
$pdo->prepare('INSERT INTO users (email, hashed_password, full_name, is_tutor) VALUES (?, ?, ?, ?)')->execute(['student@example.com', $pwd, 'Sample Student', 0]);
$pdo->prepare('INSERT INTO users (email, hashed_password, full_name, is_tutor) VALUES (?, ?, ?, ?)')->execute(['guy@example.com', $pwd, 'Guy Hawkins', 1]);
$pdo->prepare('INSERT INTO users (email, hashed_password, full_name, is_tutor) VALUES (?, ?, ?, ?)')->execute(['anna@example.com', $pwd, 'Anna Smith', 1]);

$pdo->prepare('INSERT INTO tutors (user_id, display_name, bio, rate_per_hour, subjects, location_lat, location_lng, verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([2, 'Guy Hawkins', 'Math tutor with 5 years experience', 14.0, 'Algebra,Math', 37.77, -122.41, 1]);
$pdo->prepare('INSERT INTO tutors (user_id, display_name, bio, rate_per_hour, subjects, location_lat, location_lng, verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([3, 'Anna Smith', 'Physics and Math', 20.0, 'Physics,Algebra', 37.78, -122.40, 1]);

$pdo->prepare('INSERT INTO availability (tutor_id, weekday, start_time, end_time) VALUES (?, ?, ?, ?)')->execute([1, 0, '12:00', '14:00']);
$pdo->prepare('INSERT INTO availability (tutor_id, weekday, start_time, end_time) VALUES (?, ?, ?, ?)')->execute([2, 2, '09:00', '11:30']);

$pdo->prepare('INSERT INTO promo_codes (code, discount_percent, active, expires_at) VALUES (?, ?, ?, ?)')->execute(['TRIAL50', 50, 1, date('c', strtotime('+30 days'))]);

echo "Seeded educify.db with sample data\n";
