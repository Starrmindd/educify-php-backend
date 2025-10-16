<?php
namespace Educify;
use Educify\DB;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class App {
    private $base;
    private $db;
    private $dotenv;
    public function __construct($basePath){
        $this->base = $basePath;
        if(file_exists($basePath.'/.env')){
            $this->dotenv = \Dotenv\Dotenv::createImmutable($basePath);
            $this->dotenv->load();
        }
        $this->db = new DB($basePath);
    }

    public function run(){
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        header('Content-Type: application/json');
        // Simple routing
        if($path === '/auth/register' && $method === 'POST') return $this->register();
        if($path === '/auth/login' && $method === 'POST') return $this->login();
        if(preg_match('#^/tutors/?$#', $path) && $method === 'GET') return $this->listTutors();
        if(preg_match('#^/tutors/(\d+)/availability$#', $path) && $method === 'GET') return $this->getAvailability((int)preg_replace('#^/tutors/(\d+)/availability$#','$1',$path));
        if(preg_match('#^/tutors/(\d+)$#', $path) && $method === 'GET') return $this->getTutor((int)preg_replace('#^/tutors/(\d+)$#','$1',$path));
        if($path === '/bookings' && $method === 'POST') return $this->bookLesson();
        if(preg_match('#^/promo/([A-Za-z0-9_-]+)$#', $path) && $method === 'GET') return $this->checkPromo(preg_replace('#^/promo/([A-Za-z0-9_-]+)$#','$1',$path));
        if($path === '/payments/create-intent' && $method === 'POST') return $this->createIntent();
        if($path === '/payments/webhook' && $method === 'POST') return $this->webhook();
        http_response_code(404); echo json_encode(['error'=>'Not found']); exit;
    }

    private function json_body(){
        $b = file_get_contents('php://input');
        return json_decode($b, true) ?? [];
    }

    private function register(){
        $data = $this->json_body();
        if(empty($data['email']) || empty($data['password'])){
            http_response_code(400); echo json_encode(['error'=>'email and password required']); exit;
        }
        $pdo = $this->db->getPDO();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        if($stmt->fetch()) { http_response_code(400); echo json_encode(['error'=>'Email already registered']); exit; }
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (email, hashed_password, full_name, is_tutor) VALUES (?, ?, ?, 0)');
        $stmt->execute([$data['email'], $hash, $data['full_name'] ?? null]);
        $token = $this->create_jwt(['sub'=>$data['email']]);
        echo json_encode(['access_token'=>$token,'token_type'=>'bearer']); exit;
    }

    private function login(){
        $data = $this->json_body();
        if(empty($data['email']) || empty($data['password'])){
            http_response_code(400); echo json_encode(['error'=>'email and password required']); exit;
        }
        $pdo = $this->db->getPDO();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        $u = $stmt->fetch(\PDO::FETCH_ASSOC);
        if(!$u || !password_verify($data['password'], $u['hashed_password'])){
            http_response_code(401); echo json_encode(['error'=>'Invalid credentials']); exit;
        }
        $token = $this->create_jwt(['sub'=>$u['email']]);
        echo json_encode(['access_token'=>$token,'token_type'=>'bearer']); exit;
    }

    private function listTutors(){
        $q = $_GET['q'] ?? null;
        $subject = $_GET['subject'] ?? null;
        $pdo = $this->db->getPDO();
        $sql = 'SELECT * FROM tutors WHERE 1=1';
        $params = [];
        if($q){ $sql .= ' AND display_name LIKE ?'; $params[] = '%'.$q.'%'; }
        if($subject){ $sql .= ' AND subjects LIKE ?'; $params[] = '%'.$subject.'%'; }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode($rows); exit;
    }

    private function getTutor($id){
        $pdo = $this->db->getPDO();
        $stmt = $pdo->prepare('SELECT * FROM tutors WHERE id = ?');
        $stmt->execute([$id]);
        $t = $stmt->fetch(\PDO::FETCH_ASSOC);
        if(!$t){ http_response_code(404); echo json_encode(['error'=>'Tutor not found']); exit; }
        echo json_encode($t); exit;
    }

    private function getAvailability($tutor_id){
        $pdo = $this->db->getPDO();
        $stmt = $pdo->prepare('SELECT weekday, start_time, end_time FROM availability WHERE tutor_id = ?');
        $stmt->execute([$tutor_id]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode($rows); exit;
    }

    private function bookLesson(){
        $data = $this->json_body();
        if(empty($data['tutor_id']) || empty($data['start_datetime'])){
            http_response_code(400); echo json_encode(['error'=>'tutor_id and start_datetime required']); exit;
        }
        $pdo = $this->db->getPDO();
        $stmt = $pdo->prepare('SELECT * FROM tutors WHERE id = ?');
        $stmt->execute([$data['tutor_id']]);
        $t = $stmt->fetch(\PDO::FETCH_ASSOC);
        if(!$t){ http_response_code(404); echo json_encode(['error'=>'Tutor not found']); exit; }
        // use student id 1 from seed
        $student_id = 1;
        $duration = isset($data['duration_minutes']) ? (int)$data['duration_minutes'] : 30;
        $amount = $t['rate_per_hour'] * ($duration/60.0);
        if(!empty($data['promo_code'])){
            $stmt = $pdo->prepare('SELECT * FROM promo_codes WHERE code = ? AND active = 1');
            $stmt->execute([$data['promo_code']]);
            $p = $stmt->fetch(\PDO::FETCH_ASSOC);
            if($p){
                $amount = $amount * (100 - (int)$p['discount_percent'])/100.0;
            }
        }
        $stmt = $pdo->prepare('INSERT INTO lessons (student_id, tutor_id, subject, start_datetime, duration_minutes, amount_paid, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$student_id, $t['id'], $data['subject'] ?? null, $data['start_datetime'], $duration, $amount, 'booked']);
        $lesson_id = $pdo->lastInsertId();
        echo json_encode(['lesson_id'=>$lesson_id,'amount'=>$amount]); exit;
    }

    private function checkPromo($code){
        $pdo = $this->db->getPDO();
        $stmt = $pdo->prepare('SELECT * FROM promo_codes WHERE code = ? AND active = 1');
        $stmt->execute([$code]);
        $p = $stmt->fetch(\PDO::FETCH_ASSOC);
        if(!$p){ http_response_code(404); echo json_encode(['error'=>'Promo not found or inactive']); exit; }
        echo json_encode(['code'=>$p['code'],'discount_percent'=>$p['discount_percent']]); exit;
    }

    private function createIntent(){
        $body = $this->json_body();
        // simulate client secret
        echo json_encode(['client_secret'=>'simulated_client_secret','status'=>'requires_payment_method']); exit;
    }

    private function webhook(){
        $payload = $this->json_body();
        // stub - in production verify signature
        echo json_encode(['received'=>true,'payload'=>$payload]); exit;
    }

    private function create_jwt($payload){
        $secret = $_ENV['JWT_SECRET'] ?? 'change_me';
        $now = time();
        $token = array_merge(['iat'=>$now,'exp'=>$now + 3600], $payload);
        return JWT::encode($token, $secret, 'HS256');
    }
}
