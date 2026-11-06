<?php

(static function (): void {
    $chaves = [
        'app.baseURL',
        'database.default.hostname',
        'database.default.database',
        'database.default.username',
        'database.default.password',
        'database.default.DBDriver',
        'database.default.port',
    ];

    foreach ($chaves as $chaveComPontos) {
        $chaveComUnderscores = str_replace('.', '_', $chaveComPontos);
        $valor = getenv($chaveComUnderscores);

        if ($valor === false || $valor === '') {
            continue;
        }

        // O DotEnv pode preencher $_ENV e $_SERVER com valores locais divergentes.
        foreach ([$chaveComPontos, $chaveComUnderscores] as $chave) {
            $_ENV[$chave] = $valor;
            $_SERVER[$chave] = $valor;
            putenv($chave . '=' . $valor);
        }
    }
})();
