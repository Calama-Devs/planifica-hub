#!/usr/bin/env bash
# Wrapper opcional do ambiente local definido no compose.yaml.
# Para adicionar um comando, crie comando_<nome> e registre-o em COMANDOS.
set -euo pipefail

DIRETORIO_PROJETO="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
COMANDOS=(
    "verificar|Verifica Docker, Compose e configurações locais"
    "construir|Constrói a imagem da aplicação usando o Dockerfile existente"
    "iniciar|Constrói se necessário e inicia aplicação e banco em segundo plano"
    "phpmyadmin|Inicia o phpMyAdmin opcional e o banco local, se necessário"
    "status|Mostra o estado dos serviços locais"
    "logs|Acompanha os últimos 100 registros da aplicação"
    "parar|Para os serviços sem remover containers ou volumes"
    "cs-fixer|Corrige o estilo PHP com PHPCBF (alias; aceita argumentos do PHPCBF)"
    "stan|Executa PHPStan (aceita caminhos e opções de analyse)"
    "check|Corrige com PHPCBF, valida com PHPCS e analisa com PHPStan"
    "ajuda|Mostra esta ajuda"
)

informar() { printf '[hub] %s\n' "$*" >&2; }
falhar() { informar "$*"; exit 1; }

# Artes literais para preservar barras e caracteres especiais; uma é sorteada por execução.
ARTES_BANNER=()
adicionarArteBanner() {
    local arte
    IFS= read -r -d '' arte || true
    ARTES_BANNER+=("${arte%$'\n'}")
}

adicionarArteBanner <<'EOF'
  ██████╗ ██╗      █████╗ ███╗   ██╗██╗███████╗██╗ ██████╗ █████╗
  ██╔══██╗██║     ██╔══██╗████╗  ██║██║██╔════╝██║██╔════╝██╔══██╗
  ██████╔╝██║     ███████║██╔██╗ ██║██║█████╗  ██║██║     ███████║
  ██╔═══╝ ██║     ██╔══██║██║╚██╗██║██║██╔══╝  ██║██║     ██╔══██║
  ██║     ███████╗██║  ██║██║ ╚████║██║██║     ██║╚██████╗██║  ██║
  ╚═╝     ╚══════╝╚═╝  ╚═╝╚═╝  ╚═══╝╚═╝╚═╝     ╚═╝ ╚═════╝╚═╝  ╚═╝
EOF

adicionarArteBanner <<'EOF'
   ____  _        _    _   _ ___ _____ ___ ____    _
  |  _ \| |      / \  | \ | |_ _|  ___|_ _/ ___|  / \
  | |_) | |     / _ \ |  \| || || |_   | | |     / _ \
  |  __/| |___ / ___ \| |\  || ||  _|  | | |___ / ___ \
  |_|   |_____/_/   \_\_| \_|___|_|   |___\____/_/   \_\
EOF

# Estilo Isometric1, de Lennert Stock, convertido por Kent Nassen.
adicionarArteBanner <<'EOF'
        ___           ___       ___           ___                       ___                       ___           ___
       /\  \         /\__\     /\  \         /\__\          ___        /\  \          ___        /\  \         /\  \
      /::\  \       /:/  /    /::\  \       /::|  |        /\  \      /::\  \        /\  \      /::\  \       /::\  \
     /:/\:\  \     /:/  /    /:/\:\  \     /:|:|  |        \:\  \    /:/\:\  \       \:\  \    /:/\:\  \     /:/\:\  \
    /::\~\:\  \   /:/  /    /::\~\:\  \   /:/|:|  |__      /::\__\  /::\~\:\  \      /::\__\  /:/  \:\  \   /::\~\:\  \
   /:/\:\ \:\__\ /:/__/    /:/\:\ \:\__\ /:/ |:| /\__\  __/:/\/__/ /:/\:\ \:\__\  __/:/\/__/ /:/__/ \:\__\ /:/\:\ \:\__\
   \/__\:\/:/  / \:\  \    \/__\:\/:/  / \/__|:|/:/  / /\/:/  /    \/__\:\ \/__/ /\/:/  /    \:\  \  \/__/ \/__\:\/:/  /
        \::/  /   \:\  \        \::/  /      |:/:/  /  \::/__/          \:\__\   \::/__/      \:\  \            \::/  /
         \/__/     \:\  \       /:/  /       |::/  /    \:\__\           \/__/    \:\__\       \:\  \           /:/  /
                    \:\__\     /:/  /        /:/  /      \/__/                     \/__/        \:\__\         /:/  /
                     \/__/     \/__/         \/__/                                               \/__/         \/__/
EOF

exibirBanner() {
    local corAzul="" corNegrito="" corDiscreta="" corRestaurar=""
    if [[ -t 1 ]]; then
        corAzul=$'\e[34m'
        corNegrito=$'\e[1m'
        corDiscreta=$'\e[2m'
        corRestaurar=$'\e[0m'
    fi
    printf '\n%s%s%s%s\n' "$corAzul" "$corNegrito" \
        "${ARTES_BANNER[RANDOM % ${#ARTES_BANNER[@]}]}" "$corRestaurar"
    printf '  %sPlanIFica Hub — ambiente de desenvolvimento local%s\n\n' "$corDiscreta" "$corRestaurar"
}

# Não permite que opções externas selecionem outro arquivo, perfil ou projeto.
# O nome padrão mantém compatibilidade com docker compose na raiz do projeto.
compose() (
    unset COMPOSE_FILE COMPOSE_PROJECT_NAME COMPOSE_PROFILES COMPOSE_ENV_FILES
    unset COMPOSE_REMOVE_ORPHANS
    docker compose --project-directory "$DIRETORIO_PROJETO" \
        --project-name "$(basename -- "$DIRETORIO_PROJETO")" \
        --env-file "$DIRETORIO_PROJETO/.env" \
        --file "$DIRETORIO_PROJETO/compose.yaml" "$@"
)

verificarDocker() {
    command -v docker >/dev/null 2>&1 \
        || falhar "Docker não encontrado. Instale Docker Engine e o plugin Docker Compose para Linux."

    local destino contexto
    if [[ -n "${DOCKER_HOST:-}" && -z "${DOCKER_CONTEXT:-}" ]]; then
        destino="$DOCKER_HOST"
    else
        contexto="${DOCKER_CONTEXT:-$(docker context show 2>/dev/null)}" \
            || falhar "Não foi possível identificar o contexto Docker. Confira 'docker context ls'."
        destino="$(docker context inspect "$contexto" --format '{{.Endpoints.docker.Host}}' 2>/dev/null)" \
            || falhar "Contexto Docker indisponível. Confira 'docker context ls'."
    fi
    [[ "$destino" == unix://* ]] \
        || falhar "Use um Docker local com socket Unix. O wrapper não opera em Docker remoto."

    local sistema
    sistema="$(docker info --format '{{.OSType}}' 2>/dev/null)" \
        || falhar "Docker indisponível. Inicie o serviço Docker e confira a permissão do seu usuário no socket."
    [[ "$sistema" == linux ]] || falhar "Este ambiente requer containers Linux."
    docker compose version >/dev/null 2>&1 \
        || falhar "Plugin Docker Compose não encontrado. Instale o plugin 'docker compose'."
}

verificarConfiguracao() {
    [[ -f "$DIRETORIO_PROJETO/Dockerfile" && -r "$DIRETORIO_PROJETO/compose.yaml" ]] \
        || falhar "Dockerfile ou compose.yaml ausente. Confira os arquivos do projeto."
    [[ -f "$DIRETORIO_PROJETO/.env.example" ]] \
        || falhar "Arquivo .env.example ausente. Restaure o exemplo versionado do projeto."
    if [[ ! -e "$DIRETORIO_PROJETO/.env" ]]; then
        informar "Arquivo .env ausente. Na raiz do projeto, copie sem sobrescrever: cp -n .env.example .env"
        falhar "Preencha as configurações locais conforme o README e execute novamente."
    fi
    [[ -f "$DIRETORIO_PROJETO/.env" && -r "$DIRETORIO_PROJETO/.env" ]] \
        || falhar "O .env precisa ser um arquivo legível pelo seu usuário."

    # O .env não é um script Bash: nunca usar source/eval nem imprimir seu conteúdo.
    if ! grep -Eq "^[[:space:]]*CI_ENVIRONMENT[[:space:]]*=[[:space:]]*(['\"]development['\"]|development)[[:space:]]*(#.*)?$" "$DIRETORIO_PROJETO/.env"; then
        falhar "Configure CI_ENVIRONMENT = development no .env. Uso restrito ao desenvolvimento local."
    fi
    if grep -Eq "^[[:space:]]*DOCKER_BANCO_(SENHA|SENHA_ROOT)[[:space:]]*=[[:space:]]*['\"]?Preencha com a senha local no \\.env" "$DIRETORIO_PROJETO/.env"; then
        falhar "Substitua os textos de exemplo de DOCKER_BANCO_SENHA e DOCKER_BANCO_SENHA_ROOT por senhas locais distintas."
    fi

    # A validação nativa respeita a sintaxe do Compose. Sua saída pode conter segredos.
    compose config --quiet >/dev/null 2>&1 \
        || falhar "Configuração inválida. Confira a sintaxe do .env e preencha DOCKER_BANCO_NOME, DOCKER_BANCO_USUARIO, DOCKER_BANCO_SENHA e DOCKER_BANCO_SENHA_ROOT. Consulte o README."
}

comando_ajuda() {
    printf 'Uso: ./scripts/hub.sh <comando> [argumentos]\n\nComandos disponíveis:\n'
    local entrada
    for entrada in "${COMANDOS[@]}"; do
        printf '  %-10s %s\n' "${entrada%%|*}" "${entrada#*|}"
    done
    printf '\nPrimeiro acesso: configure o .env conforme o README. Depois execute:\n'
    printf '  ./scripts/hub.sh verificar\n  ./scripts/hub.sh construir\n  ./scripts/hub.sh iniciar\n'
    printf '\nLogs sem acompanhamento: ./scripts/hub.sh logs --sem-acompanhar\n'
    printf '\nQualidade:\n  ./scripts/hub.sh check\n'
    printf '  ./scripts/hub.sh cs-fixer [fix] [caminhos/opções]\n'
    printf '  ./scripts/hub.sh stan [caminhos/opções]\n'
    printf 'Nenhum comando executa migrations, seeds ou remove dados persistentes.\n\n'
}

comando_verificar() {
    informar "Docker local, Compose e configurações disponíveis. Nenhuma alteração realizada."
}

comando_construir() {
    informar "Construindo a imagem da aplicação..."
    compose build app || falhar "Falha na construção. Confira a conexão com a internet e as etapas do Dockerfile acima."
}

comando_iniciar() {
    local resultado
    informar "Iniciando aplicação e banco; a primeira execução pode demorar..."
    if ! resultado="$(compose up --detach --wait --wait-timeout 150 app 2>&1)"; then
        # Não reproduz a saída bruta: erros de interpolação podem incluir credenciais.
        if [[ "$resultado" == *"port is already allocated"* || "$resultado" == *"address already in use"* || "$resultado" == *"failed to bind host port"* ]]; then
            falhar "Porta local indisponível. Confira as portas em compose.yaml (padrão: 127.0.0.1:8080) e encerre o serviço conflitante; o wrapper não encerra outros processos."
        fi
        falhar "Não foi possível iniciar os serviços. Confira './scripts/hub.sh status' e './scripts/hub.sh logs --sem-acompanhar'. Verifique também o Docker e a saúde do banco local."
    fi
    informar "Serviços em execução. Endereço padrão: http://localhost:8080/ (confira ports no compose.yaml)."
    informar "No primeiro acesso, as migrations locais são uma etapa manual descrita no README."
}

comando_status() {
    local identificadores
    identificadores="$(compose ps --all --quiet 2>/dev/null)" \
        || falhar "Não foi possível consultar os serviços. Confira a disponibilidade do Docker."
    if [[ -z "$identificadores" ]]; then
        informar "Nenhum container deste projeto foi criado. Use './scripts/hub.sh iniciar'."
        return
    fi
    compose ps --all || falhar "Não foi possível consultar o estado dos serviços."
}

comando_phpmyadmin() {
    local resultado
    informar "Iniciando o phpMyAdmin e aguardando o banco local..."
    if ! resultado="$(compose --profile ferramentas up --detach --wait --wait-timeout 150 phpmyadmin 2>&1)"; then
        if [[ "$resultado" == *"port is already allocated"* || "$resultado" == *"address already in use"* || "$resultado" == *"failed to bind host port"* ]]; then
            falhar "Porta do phpMyAdmin indisponível. Confira ports no compose.yaml (padrão: 127.0.0.1:8082) e encerre manualmente o serviço conflitante."
        fi
        falhar "Não foi possível iniciar o phpMyAdmin. Confira './scripts/hub.sh status', a saúde do banco e a conexão com a internet. Para os logs, use 'docker compose --profile ferramentas logs --tail=100 phpmyadmin banco'."
    fi
    informar "phpMyAdmin em execução. Endereço padrão: http://localhost:8082/ (confira ports no compose.yaml)."
    informar "Entre com o usuário e a senha locais configurados no .env. Nenhuma credencial foi exibida."
}

comando_logs() {
    local identificador
    identificador="$(compose ps --all --quiet app 2>/dev/null)" \
        || falhar "Não foi possível consultar o container da aplicação."
    [[ -n "$identificador" ]] || falhar "Container da aplicação ainda não criado. Use './scripts/hub.sh iniciar'."
    local opcoes=(--follow)
    if [[ "${1:-}" == --sem-acompanhar ]]; then
        opcoes=()
    fi
    compose logs --tail 100 "${opcoes[@]}" app || falhar "Não foi possível consultar os logs."
}

comando_parar() {
    # Inclui a ferramenta opcional, iniciada pelo wrapper ou diretamente pelo Compose.
    compose --profile ferramentas stop >/dev/null 2>&1 \
        || falhar "Não foi possível parar os serviços. Confira a disponibilidade do Docker."
    informar "Serviços parados. Containers e volumes preservados."
}

# Reutiliza imagem e volumes do Compose, sem publicar portas nem iniciar dependências.
executarFerramenta() {
    local usuario="$1"
    shift
    local opcoesTerminal=() codigo=0
    if [[ ! -t 0 || ! -t 1 ]]; then
        opcoesTerminal=(-T)
    fi
    compose run --rm --no-deps "${opcoesTerminal[@]}" --user "$usuario" \
        --workdir /var/www/html app "$@" || codigo=$?
    if [[ "$codigo" -ne 0 ]]; then
        informar "A ferramenta $1 terminou com código $codigo. Confira a saída acima. Se faltar o executável, construa a imagem e confira a sincronização do volume de dependências no README."
    fi
    return "$codigo"
}

comando_cs_fixer() {
    # Compatibilidade com a chamada do modelo; as demais opções são do PHPCBF.
    if [[ "${1:-}" == fix ]]; then shift; fi
    informar "Corrigindo o estilo com PHPCBF. Os arquivos PHP locais podem ser alterados."
    executarFerramenta "$(id -u):$(id -g)" vendor/bin/phpcbf "$@"
}

comando_stan() {
    informar "Executando análise estática com PHPStan..."
    # O cache em writable pertence ao usuário do Apache na imagem existente.
    executarFerramenta www-data vendor/bin/phpstan analyse --no-progress --memory-limit=1G "$@"
}

comando_check() {
    comando_cs_fixer || return $?
    informar "Validando o estilo com PHPCS..."
    executarFerramenta "$(id -u):$(id -g)" vendor/bin/phpcs || return $?
    comando_stan || return $?
    informar "Verificações de estilo e análise estática concluídas."
}

principal() {
    local comando="${1:-ajuda}"
    if [[ $# -gt 0 ]]; then shift; fi
    case "$comando" in
        -h|--help) comando=ajuda ;;
    esac

    exibirBanner

    # Lista explícita: funções internas nunca ficam acessíveis como comandos.
    case "$comando" in
        verificar|construir|iniciar|phpmyadmin|status|parar|check|ajuda)
            [[ $# -eq 0 ]] || falhar "Este comando não aceita argumentos extras. Consulte './scripts/hub.sh ajuda'."
            ;;
        logs)
            [[ $# -eq 0 || ( $# -eq 1 && "$1" == --sem-acompanhar ) ]] \
                || falhar "Uso: ./scripts/hub.sh logs [--sem-acompanhar]"
            ;;
        cs-fixer|stan) ;;
        *) falhar "Comando desconhecido. Consulte './scripts/hub.sh ajuda'." ;;
    esac

    if [[ "$comando" != ajuda ]]; then
        verificarDocker
        verificarConfiguracao
    fi
    "comando_${comando//-/_}" "$@"
}

principal "$@"
