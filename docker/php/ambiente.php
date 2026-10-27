<?php

(static function (): void {
    // O PHP não importa automaticamente as variáveis com pontos em $_ENV.
    $nomesConfiguracoes = [
        'app.baseURL',
        'database.default.hostname',
        'database.default.database',
        'database.default.username',
        'database.default.password',
        'database.default.DBDriver',
        'database.default.port',
    ];

    foreach ($nomesConfiguracoes as $nomeConfiguracao) {
        $valorConfiguracao = getenv($nomeConfiguracao);

        if ($valorConfiguracao === false) {
            continue;
        }

        $_ENV[$nomeConfiguracao] = $valorConfiguracao;
        $_SERVER[$nomeConfiguracao] = $valorConfiguracao;
    }
})();
