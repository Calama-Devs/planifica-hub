<div class="modal fade" id="modalAtivacao" tabindex="-1"
     aria-labelledby="modalAtivacaoTitulo" aria-describedby="modalAtivacaoDescricao" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="modalAtivacaoTitulo">
                    <?= $conta['active'] ? 'Desativar conta' : 'Ativar conta' ?>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Conta: <strong class="text-break"><?= esc($conta['rotulo']) ?></strong></p>
                <p id="modalAtivacaoDescricao">
                    Informa senha para <?= $conta['active'] ? 'desativar' : 'ativar' ?> o usuario
                </p>

                <input type="password_hash" 
                    id="campo-senha" 
                    name="nome"
                    class="form-control"
                    placeholder="Senha"
                >

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Entendi</button>
            </div>
        </div>
    </div>
</div>
