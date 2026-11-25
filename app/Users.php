<?php
declare(strict_types=1);
namespace Moba;
use PDO;
use InvalidArgumentException;
use RuntimeException;

final class Users
{
    public function __construct(private PDO $db, private array $config) {}
    public function root(): array { return ['id'=>0,'username'=>$this->config['admin_username'],'name'=>'Administrador principal','role'=>'admin','active'=>true,'session_version'=>1]; }
    public function all(): array {
        return $this->db->query('SELECT id,username,name,role,active,version FROM erp_users ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function get(int $id): array {
        $q=$this->db->prepare('SELECT * FROM erp_users WHERE id=?');$q->execute([$id]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new InvalidArgumentException('El usuario no existe.'); }
        return $row;
    }
    public function login(string $username,string $password): ?array {
        if (hash_equals($this->config['admin_username'],$username)) {
            return Auth::verify($username,$password,$this->config) ? $this->root() : null;
        }
        $q=$this->db->prepare('SELECT * FROM erp_users WHERE username=?');$q->execute([$username]);$row=$q->fetch(PDO::FETCH_ASSOC);
        $valid=password_verify($password,$row['password_hash'] ?? $this->config['admin_password_hash']);
        if (!$row || !$valid || !$row['active']) { return null; }
        unset($row['password_hash']);return $row;
    }
    public function current(array $session): ?array {
        if (empty($session['authenticated'])) { return null; }
        // Existing sessions belong to the original configuration administrator.
        if (!isset($session['user_id']) || (int)$session['user_id']===0) { return $this->root(); }
        try { $row=$this->get((int)$session['user_id']); } catch (InvalidArgumentException) { return null; }
        if (!$row['active'] || (int)$row['session_version'] !== (int)($session['user_version'] ?? 0)) { return null; }
        unset($row['password_hash']);return $row;
    }
    public static function allow(array $actor,array $roles): void {
        if (!in_array($actor['role'] ?? '',$roles,true)) { http_response_code(403);throw new InvalidArgumentException('No tienes permiso para esta acción.'); }
    }
    public function save(array $input,array $actor): array {
        self::allow($actor,['admin']);
        $id=filter_var($input['id'] ?? 0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        if ($id===false) { throw new InvalidArgumentException('Usuario no válido.'); }
        foreach (['username','name','role','password'] as $key) { if (!is_string($input[$key] ?? '')) { throw new InvalidArgumentException('Datos de usuario no válidos.'); } }
        $username=trim($input['username'] ?? '');$name=trim($input['name'] ?? '');$role=$input['role'] ?? '';$password=$input['password'] ?? '';
        if (!preg_match('/^[a-zA-Z0-9_.@-]{3,80}$/D',$username) || strcasecmp($username,$this->config['admin_username'])===0) { throw new InvalidArgumentException('Elige otro usuario (3–80 letras, números, punto, guion, @ o _).'); }
        if ($name==='' || mb_strlen($name)>190 || !in_array($role,['admin','sales','production'],true)) { throw new InvalidArgumentException('Nombre o rol no válido.'); }
        $active=!empty($input['active']);
        if ($id>0 && $id===(int)$actor['id'] && (!$active || $role!=='admin')) { throw new InvalidArgumentException('No puedes quitarte tu acceso de administrador.'); }
        if (($id===0 || $password!=='') && (strlen($password)<12 || strlen($password)>72)) { throw new InvalidArgumentException('La contraseña debe tener entre 12 y 72 bytes.'); }
        $hash=$password!=='' ? password_hash($password,PASSWORD_DEFAULT) : null;
        try {
            if ($id===0) {
                $q=$this->db->prepare('INSERT INTO erp_users(username,name,password_hash,role,active) VALUES (?,?,?,?,?)');$q->execute([$username,$name,$hash,$role,(int)$active]);$id=(int)$this->db->lastInsertId();
            } else {
                $q=$this->db->prepare('UPDATE erp_users SET username=?,name=?,password_hash=COALESCE(?,password_hash),role=?,active=?,version=version+1,session_version=session_version+1 WHERE id=? AND version=?');
                $q->execute([$username,$name,$hash,$role,(int)$active,$id,(int)($input['version'] ?? 0)]);
                if ($q->rowCount()!==1) { throw new RuntimeException('Usuario modificado en otra pestaña. Recarga.',409); }
            }
        } catch (\PDOException $e) { if ($e->getCode()==='23000') { throw new InvalidArgumentException('Ese nombre de usuario ya existe.'); } throw $e; }
        $row=$this->get($id);unset($row['password_hash'],$row['session_version']);return $row;
    }
}
