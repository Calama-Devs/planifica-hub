<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UsersModel extends Model
{
  protected $table            ='users';
  protected $primaryKey       ='id';
  protected $useAutoIncrement = true;
  protected $returnType       = 'array';
  protected $useSoftDeletes   = true;
  protected $protectFields    = true;
  protected $allowedFields    = [
      'username',
      'status',
      'status_message',
      'active',
  ];
  
  protected $useTimestamps = true;
  protected $dateFormat    = 'datetime';
  protected $createdField  = 'created_at';
  protected $updatedField  = 'updated_at';
  protected $deletedField  = 'deleted_at';
  
  public function listarUsers()
  {
    $usuarios = $this->select(
      'id,
      username,
      active'
    )
    ->withIdentities()
    ->withGroups()
    ->orderBy('username', 'ASC')
    ->findAll();
    
    return $usuarios;
  }
}
