<?php

namespace Config;

use App\Services\PerfilService;
use App\Services\PermissaoDiretaService;
use App\Services\PermissaoService;
use App\Services\UsuarioService;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function perfilService(bool $getShared = true): PerfilService
    {
        if ($getShared) {
            return static::getSharedInstance('perfilService');
        }

        return new PerfilService();
    }

    public static function permissaoService(bool $getShared = true): PermissaoService
    {
        if ($getShared) {
            return static::getSharedInstance('permissaoService');
        }

        return new PermissaoService();
    }

    public static function permissaoDiretaService(bool $getShared = true): PermissaoDiretaService
    {
        if ($getShared) {
            return static::getSharedInstance('permissaoDiretaService');
        }

        return new PermissaoDiretaService();
    }

    public static function usuarioService(bool $getShared = true): UsuarioService
    {
        if ($getShared) {
            return static::getSharedInstance('usuarioService');
        }

        return new UsuarioService();
    }
}
