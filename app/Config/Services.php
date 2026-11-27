<?php

namespace Config;

use App\Services\MenuService;
use Config\Menu;
use App\Services\RedefinirSenhaService;
use App\Services\PerfilService;
use App\Services\AcessoService;
use App\Services\PermissaoService;
use App\Services\ConviteService;
use App\Services\AuditoriaService;
use App\Services\EstadoContaService;
use App\Services\EdicaoContaService;
use App\Services\ConfirmacaoAdministrativaService;
use App\Services\ConfirmacaoOperacaoService;
use App\Services\ConfirmacaoEmailContaService;
use CodeIgniter\Config\BaseService;
use App\Services\CabecalhoService;
use App\Services\FinalizarCadastroService;

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
    public static function auditoriaService(bool $getShared = true): AuditoriaService
    {
        if ($getShared) {
            return static::getSharedInstance('auditoriaService');
        }

        return new AuditoriaService();
    }

    public static function estadoContaService(bool $getShared = true): EstadoContaService
    {
        if ($getShared) {
            return static::getSharedInstance('estadoContaService');
        }

        return new EstadoContaService();
    }

    public static function confirmacaoEmailContaService(bool $getShared = true): ConfirmacaoEmailContaService
    {
        if ($getShared) {
            return static::getSharedInstance('confirmacaoEmailContaService');
        }

        return new ConfirmacaoEmailContaService();
    }

    public static function confirmacaoAdministrativaService(bool $getShared = true): ConfirmacaoAdministrativaService
    {
        if ($getShared) {
            return static::getSharedInstance('confirmacaoAdministrativaService');
        }

        return new ConfirmacaoAdministrativaService();
    }

    public static function confirmacaoOperacaoService(bool $getShared = true): ConfirmacaoOperacaoService
    {
        if ($getShared) {
            return static::getSharedInstance('confirmacaoOperacaoService');
        }

        return new ConfirmacaoOperacaoService();
    }

    public static function edicaoContaService(bool $getShared = true): EdicaoContaService
    {
        if ($getShared) {
            return static::getSharedInstance('edicaoContaService');
        }

        return new EdicaoContaService();
    }

    public static function redefinirSenhaService(bool $getShared = true): RedefinirSenhaService
    {
        if ($getShared) {
            return static::getSharedInstance('redefinirSenhaService');
        }

        return new RedefinirSenhaService();
    }

    public static function perfilService(bool $getShared = true): PerfilService
    {
        if ($getShared) {
            return static::getSharedInstance('perfilService');
        }

        return new PerfilService();
    }

    public static function conviteService(bool $getShared = true): ConviteService
    {
        if ($getShared) {
            return static::getSharedInstance('conviteService');
        }

        return new ConviteService();
    }

    public static function permissaoService(bool $getShared = true): PermissaoService
    {
        if ($getShared) {
            return static::getSharedInstance('permissaoService');
        }

        return new PermissaoService();
    }

    public static function acessoService(bool $getShared = true): AcessoService
    {
        if ($getShared) {
            return static::getSharedInstance('acessoService');
        }

        return new AcessoService();
    }

    public static function menu(?Menu $config = null, bool $getShared = true): MenuService
    {
        if ($getShared) {
            return static::getSharedInstance('menu', $config);
        }

        return new MenuService($config);
    }

    public static function contaCabecalho(bool $getShared = true): CabecalhoService
    {
        if ($getShared) {
            return static::getSharedInstance('contaCabecalho');
        }

        return new CabecalhoService();
    }

    public static function finalizarCadastroService(bool $getShared = true): FinalizarCadastroService
    {
        if ($getShared) {
            return static::getSharedInstance('finalizarCadastroService');
        }

        return new FinalizarCadastroService();
    }
}
