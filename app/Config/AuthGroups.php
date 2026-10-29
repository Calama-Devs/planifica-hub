<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'user';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     * An associative array of the available groups in the system, where the keys
     * are the group names and the values are arrays of the group info.
     *
     * Whatever value you assign as the key will be used to refer to the group
     * when using functions such as:
     *      $user->addGroup('superadmin');
     *
     * @var array<string, array<string, string>>
     *
     * @see https://codeigniter4.github.io/shield/quick_start_guide/using_authorization/#change-available-groups for more info
     */
    public array $groups = [
        'superadmin' => [
            'title'       => 'Super Admin',
            'description' => 'Complete control of the site.',
        ],
        'admin' => [
            'title'       => 'Admin',
            'description' => 'Day to day administrators of the site.',
        ],
        'developer' => [
            'title'       => 'Developer',
            'description' => 'Site programmers.',
        ],
        'user' => [
            'title'       => 'User',
            'description' => 'General users of the site. Often customers.',
        ],
        'beta' => [
            'title'       => 'Beta User',
            'description' => 'Has access to beta-level features.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * Catálogo de permissões do sistema. O Shield recusa qualquer permissão
     * que não esteja listada aqui.
     *
     * Identificadores no formato `contexto.acao`, em minúsculas e sem acento.
     * Depois de integrados, não devem ser renomeados: o código e os vínculos
     * gravados no banco dependem deles. Convenção e passo a passo para
     * acrescentar uma permissão: docs/padroes/permissoes.md.
     */
    public array $permissions = [
        'contas.consultar'    => 'Consultar a lista e os dados das contas de usuário',
        'contas.criar'        => 'Criar novas contas de usuário',
        'acessos.administrar' => 'Conceder e remover grupos e permissões das contas de usuário',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Permissões concedidas a cada grupo.
     *
     * As concessões são explícitas, sem curinga (`*`), para que fique
     * verificável quem possui cada permissão administrativa.
     */
    public array $matrix = [
        'superadmin' => [
            'contas.consultar',
            'contas.criar',
            'acessos.administrar',
        ],
        'admin' => [
            'contas.consultar',
            'contas.criar',
        ],
        'developer' => [],
        'user'      => [],
        'beta'      => [],
    ];
}
