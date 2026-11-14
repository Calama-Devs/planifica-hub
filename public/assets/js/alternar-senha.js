document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-alternar-senha]').forEach(function (botao) {
        var campo = document.getElementById(botao.getAttribute('data-alternar-senha'));
        var icone = botao.querySelector('i');

        if (!campo || !icone) {
            return;
        }

        botao.addEventListener('click', function () {
            var mostrando = campo.type === 'text';

            campo.type = mostrando ? 'password' : 'text';
            icone.classList.toggle('mdi-eye', mostrando);
            icone.classList.toggle('mdi-eye-off', !mostrando);
            botao.setAttribute('aria-pressed', String(!mostrando));
            botao.setAttribute('aria-label', mostrando ? 'Mostrar senha' : 'Ocultar senha');
            botao.setAttribute('title', mostrando ? 'Mostrar senha' : 'Ocultar senha');
        });
    });

    var paineis = [];

    document.querySelectorAll('[data-dica-alvo]').forEach(function (botao) {
        var painel = document.getElementById(botao.getAttribute('data-dica-alvo'));

        if (!painel) {
            return;
        }

        paineis.push({ botao: botao, painel: painel });

        botao.addEventListener('click', function () {
            var abrir = painel.hidden;

            fecharTodos();
            painel.hidden = !abrir;
            botao.setAttribute('aria-expanded', String(abrir));
        });
    });

    function fecharTodos() {
        paineis.forEach(function (item) {
            item.painel.hidden = true;
            item.botao.setAttribute('aria-expanded', 'false');
        });
    }

    document.addEventListener('click', function (evento) {
        paineis.forEach(function (item) {
            if (item.painel.hidden) {
                return;
            }

            if (!item.painel.contains(evento.target) && !item.botao.contains(evento.target)) {
                item.painel.hidden = true;
                item.botao.setAttribute('aria-expanded', 'false');
            }
        });
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            fecharTodos();
        }
    });
});