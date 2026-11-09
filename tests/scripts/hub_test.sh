#!/usr/bin/env bash
# Testa o wrapper com o parser real do Compose, sem construir ou iniciar serviços.
set -euo pipefail
umask 077

DIRETORIO_PROJETO="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
DOCKER_TESTE_REAL="$(command -v docker)" || { printf 'Instale Docker e Compose para executar os testes.\n' >&2; exit 1; }
export DOCKER_TESTE_REAL
"$DOCKER_TESTE_REAL" compose version >/dev/null

DIRETORIO_TESTE="$(mktemp -d "${TMPDIR:-/tmp}/hub-teste.XXXXXXXX")"
trap 'rm -rf -- "$DIRETORIO_TESTE"' EXIT
mkdir -p "$DIRETORIO_TESTE/projeto/scripts" "$DIRETORIO_TESTE/bin"
cp "$DIRETORIO_PROJETO/scripts/hub.sh" "$DIRETORIO_TESTE/projeto/scripts/hub.sh"
cp "$DIRETORIO_PROJETO/compose.yaml" "$DIRETORIO_PROJETO/Dockerfile" \
    "$DIRETORIO_PROJETO/.env.example" "$DIRETORIO_TESTE/projeto/"

# Apenas o acesso ao daemon é simulado; config usa o Docker Compose instalado.
cat > "$DIRETORIO_TESTE/bin/docker" <<'BASH'
#!/usr/bin/env bash
set -euo pipefail
case "$1" in
    context)
        case "$2" in
            show) printf 'default\n' ;;
            inspect) printf '%s\n' "${HUB_TESTE_DESTINO:-unix:///var/run/docker.sock}" ;;
            *) exit 1 ;;
        esac
        ;;
    info)
        [[ "${HUB_TESTE_INDISPONIVEL:-0}" == 0 ]] || exit 1
        printf '%s\n' "${HUB_TESTE_SISTEMA:-linux}"
        ;;
    compose)
        [[ "${HUB_TESTE_SEM_COMPOSE:-0}" == 0 ]] || exit 1
        argumentos=("$@")
        shift
        while [[ "${1:-}" == --* ]]; do
            case "$1" in
                --project-directory|--project-name|--env-file|--file) shift 2 ;;
                *) exit 1 ;;
            esac
        done
        case "${1:-}" in
            config|version) exec "$DOCKER_TESTE_REAL" "${argumentos[@]}" ;;
            *) exit 1 ;;
        esac
        ;;
    *) exit 1 ;;
esac
BASH
chmod +x "$DIRETORIO_TESTE/bin/docker"

export PATH="$DIRETORIO_TESTE/bin:$PATH"
unset DOCKER_HOST DOCKER_CONTEXT CI_ENVIRONMENT
unset DOCKER_BANCO_NOME DOCKER_BANCO_USUARIO DOCKER_BANCO_SENHA DOCKER_BANCO_SENHA_ROOT
unset COMPOSE_FILE COMPOSE_PROJECT_NAME COMPOSE_PROFILES COMPOSE_ENV_FILES
unset HUB_TESTE_DESTINO HUB_TESTE_INDISPONIVEL HUB_TESTE_SISTEMA HUB_TESTE_SEM_COMPOSE
senhaAplicacao="$(od -An -N16 -tx1 /dev/urandom | tr -d ' \n')"
senhaRoot="$(od -An -N16 -tx1 /dev/urandom | tr -d ' \n')"
total=0

configurar() {
    cp "$DIRETORIO_PROJETO/.env.example" "$DIRETORIO_TESTE/projeto/.env"
    definir DOCKER_BANCO_SENHA "'$senhaAplicacao'"
    definir DOCKER_BANCO_SENHA_ROOT "'$senhaRoot'"
}

definir() {
    sed -i "/^[[:space:]]*$1[[:space:]]*=/d" "$DIRETORIO_TESTE/projeto/.env"
    printf '%s = %s\n' "$1" "$2" >> "$DIRETORIO_TESTE/projeto/.env"
}

conferir() {
    local descricao="$1" esperado="$2" mensagem="$3" comando="${4:-verificar}"
    local saida codigo=0 antes depois
    antes="$(sha256sum "$DIRETORIO_TESTE/projeto/.env" 2>/dev/null || true)"
    saida="$(bash "$DIRETORIO_TESTE/projeto/scripts/hub.sh" "$comando" 2>&1)" || codigo=$?
    depois="$(sha256sum "$DIRETORIO_TESTE/projeto/.env" 2>/dev/null || true)"
    if [[ "$codigo" -ne "$esperado" || "$saida" != *"$mensagem"* || "$antes" != "$depois" \
        || "$saida" == *"$senhaAplicacao"* || "$saida" == *"$senhaRoot"* ]]; then
        printf 'FALHOU: %s (saída omitida para proteger configurações)\n' "$descricao" >&2
        exit 1
    fi
    total=$((total + 1))
    printf 'OK: %s\n' "$descricao"
}

configurar
conferir 'configuração válida; .env preservado e senhas não exibidas' 0 'Nenhuma alteração realizada'

rm "$DIRETORIO_TESTE/projeto/.env"
conferir 'ajuda funciona sem .env' 0 'Comandos disponíveis' ajuda
conferir '.env ausente não é criado' 1 'cp -n .env.example .env'
[[ ! -e "$DIRETORIO_TESTE/projeto/.env" ]]
mkdir "$DIRETORIO_TESTE/projeto/.env"
conferir '.env precisa ser um arquivo' 1 'arquivo legível'
rmdir "$DIRETORIO_TESTE/projeto/.env"

cp "$DIRETORIO_PROJETO/.env.example" "$DIRETORIO_TESTE/projeto/.env"
conferir 'senhas de exemplo são recusadas' 1 'textos de exemplo'

for nome in DOCKER_BANCO_NOME DOCKER_BANCO_USUARIO DOCKER_BANCO_SENHA DOCKER_BANCO_SENHA_ROOT; do
    configurar
    sed -i "/^$nome[[:space:]]*=/d" "$DIRETORIO_TESTE/projeto/.env"
    conferir "$nome ausente" 1 'Configuração inválida'
    definir "$nome" "''"
    conferir "$nome vazio" 1 'Configuração inválida'
    definir "$nome" "'   '"
    conferir "$nome somente com espaços" 1 "Preencha $nome"
done

configurar
definir CI_ENVIRONMENT production
conferir 'ambiente de produção recusado' 1 'Uso restrito ao desenvolvimento local'
configurar
sed -i '/^CI_ENVIRONMENT[[:space:]]*=/d' "$DIRETORIO_TESTE/projeto/.env"
conferir 'ambiente ausente recusado' 1 'CI_ENVIRONMENT = development'
configurar
printf 'CI_ENVIRONMENT = production\n' >> "$DIRETORIO_TESTE/projeto/.env"
conferir 'última definição do ambiente é respeitada' 1 'Uso restrito ao desenvolvimento local'
configurar
definir CI_ENVIRONMENT "'development' # ambiente local"
conferir 'aspas e comentário no ambiente' 0 'Nenhuma alteração realizada'
sed -i 's/$/\r/' "$DIRETORIO_TESTE/projeto/.env"
conferir 'arquivo com finais de linha CRLF' 0 'Nenhuma alteração realizada'

configurar
definir DOCKER_BANCO_USUARIO root
conferir 'usuário root recusado para a aplicação' 1 'diferente de root'
configurar
definir DOCKER_BANCO_SENHA_ROOT "'$senhaAplicacao'"
conferir 'senhas iguais recusadas' 1 'senhas locais distintas'
configurar
definir DOCKER_BANCO_SENHA "'$senhaAplicacao\$# = sufixo'"
conferir 'senha com dólar, comentário, espaços e igual preservada' 0 'Nenhuma alteração realizada'
definir DOCKER_BANCO_SENHA_ROOT "'$senhaAplicacao\$# = sufixo'"
conferir 'senhas iguais com caracteres especiais recusadas' 1 'senhas locais distintas'
configurar
definir DOCKER_BANCO_SENHA "\"sem fechamento"
conferir 'sintaxe inválida recusada sem imprimir valores' 1 'Configuração inválida'
configurar
definir DOCKER_BANCO_SENHA "'primeira linha
segunda linha'"
conferir 'senha em múltiplas linhas recusada com orientação' 1 'uma única linha'

configurar
export DOCKER_BANCO_SENHA_ROOT="$senhaAplicacao"
conferir 'prioridade de senha exportada no terminal' 1 'senhas locais distintas'
unset DOCKER_BANCO_SENHA_ROOT
export DOCKER_BANCO_SENHA='Preencha com a senha local no .env'
conferir 'senha de exemplo exportada recusada' 1 'textos de exemplo'
unset DOCKER_BANCO_SENHA
export CI_ENVIRONMENT=production
conferir 'ambiente exportado de produção recusado' 1 'Uso restrito ao desenvolvimento local'
unset CI_ENVIRONMENT

configurar
definir DOCKER_BANCO_SENHA "'\$(touch $DIRETORIO_TESTE/nao_executar)'"
conferir '.env nunca é executado como Bash' 0 'Nenhuma alteração realizada'
[[ ! -e "$DIRETORIO_TESTE/nao_executar" ]]

configurar
export HUB_TESTE_DESTINO=ssh://servidor-invalido
conferir 'Docker remoto recusado' 1 'não opera em Docker remoto'
unset HUB_TESTE_DESTINO
export HUB_TESTE_INDISPONIVEL=1
conferir 'Docker indisponível orienta o usuário' 1 'Docker indisponível'
unset HUB_TESTE_INDISPONIVEL
export HUB_TESTE_SISTEMA=windows
conferir 'containers não Linux recusados' 1 'containers Linux'
unset HUB_TESTE_SISTEMA
export HUB_TESTE_SEM_COMPOSE=1
conferir 'Compose ausente orienta o usuário' 1 'Plugin Docker Compose não encontrado'
unset HUB_TESTE_SEM_COMPOSE

mv "$DIRETORIO_TESTE/projeto/Dockerfile" "$DIRETORIO_TESTE/projeto/Dockerfile.reserva"
conferir 'Dockerfile ausente orienta o usuário' 1 'Dockerfile ou compose.yaml ausente'
mv "$DIRETORIO_TESTE/projeto/Dockerfile.reserva" "$DIRETORIO_TESTE/projeto/Dockerfile"
mv "$DIRETORIO_TESTE/projeto/compose.yaml" "$DIRETORIO_TESTE/projeto/compose.reserva"
conferir 'Compose ausente orienta a restauração dos arquivos' 1 'Dockerfile ou compose.yaml ausente'
mv "$DIRETORIO_TESTE/projeto/compose.reserva" "$DIRETORIO_TESTE/projeto/compose.yaml"
mv "$DIRETORIO_TESTE/projeto/.env.example" "$DIRETORIO_TESTE/projeto/exemplo.reserva"
conferir 'exemplo ausente orienta o usuário' 1 'Arquivo .env.example ausente'
mv "$DIRETORIO_TESTE/projeto/exemplo.reserva" "$DIRETORIO_TESTE/projeto/.env.example"

# Um PATH mínimo permite simular ausência do Docker sem modificar o computador.
mkdir "$DIRETORIO_TESTE/sem_docker"
ln -s "$(command -v dirname)" "$DIRETORIO_TESTE/sem_docker/dirname"
caminhoOriginal="$PATH"
export PATH="$DIRETORIO_TESTE/sem_docker"
saida="$(/bin/bash "$DIRETORIO_TESTE/projeto/scripts/hub.sh" verificar 2>&1)" && exit 1
export PATH="$caminhoOriginal"
[[ "$saida" == *'Docker não encontrado'* ]]
total=$((total + 1))
printf 'OK: Docker ausente orienta o usuário\n'
printf '\n%s cenários passaram. Nenhum serviço ou volume foi criado.\n' "$total"
