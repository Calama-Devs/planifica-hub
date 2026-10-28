<?php

/**
 * Helper de formulário do PlanIFica Hub.
 *
 * Fornece funções auxiliares para manipulação de campos e feedback visual de formulários.
 */

if (! function_exists('erro_campo')) {
    /**
     * Retorna a classe CSS 'is-invalid' caso o campo especificado possua erro de validação.
     *
     * Verifica nos erros nativos do CodeIgniter 4 (form_helper) e nas mensagens flash da sessão.
     *
     * @param string $campo Nome do campo a ser verificado.
     * @return string Retorna 'is-invalid' se houver erro, ou string vazia.
     */
    function erro_campo(string $campo): string
    {
        if (function_exists('validation_errors')) {
            $erros = validation_errors();
            if (is_array($erros) && isset($erros[$campo])) {
                return 'is-invalid';
            }
        }

        if (session()->has('errors')) {
            $errosSessao = session('errors');
            if (is_array($errosSessao) && isset($errosSessao[$campo])) {
                return 'is-invalid';
            }
        }

        return '';
    }
}

