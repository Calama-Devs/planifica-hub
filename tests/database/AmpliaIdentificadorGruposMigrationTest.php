<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Database\Migrations\AmpliaIdentificadorGrupos;
use App\Models\GrupoModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use RuntimeException;

final class AmpliaIdentificadorGruposMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    protected function tearDown(): void
    {
        if ($this->db->tableExists('grupos')) {
            $this->db->table('grupos')->emptyTable();
        }

        parent::tearDown();
    }

    public function testRollbackBloqueiaPerdaDeMetadadosPersistidos(): void
    {
        model(GrupoModel::class)->insert([
            'identificador' => 'admin',
            'ativo'         => false,
        ]);

        $migration = new AmpliaIdentificadorGrupos(Database::forge($this->db));

        $this->expectException(RuntimeException::class);
        $migration->down();
    }

    public function testRollbackComTabelaVaziaMantemOsDadosSemMetadados(): void
    {
        $migration = new AmpliaIdentificadorGrupos(Database::forge($this->db));

        $migration->down();

        $this->assertTrue($this->db->tableExists('grupos'));
        $this->assertSame(0, $this->db->table('grupos')->countAllResults());
    }
}
