#!/usr/bin/env bash

#
# TYPO3 core test runner based on docker and docker-compose.
#

# Function to write a .env file in Build/testing-docker/local
# This is read by docker-compose and vars defined here are
# used in Build/testing-docker/local/docker-compose.yml
setUpDockerComposeDotEnv() {
    # Delete possibly existing local .env file if exists
    [ -e .env ] && rm .env
    # Set up a new .env file for docker-compose
    echo "COMPOSE_PROJECT_NAME=local" >>.env
    # Delete possibly existing local .env file if exists
    [ -e .env ] && rm .env
    # Set up a new .env file for docker-compose
    {
        echo "COMPOSE_PROJECT_NAME=local"
        # To prevent access rights of files created by the testing, the docker image later
        # runs with the same user that is currently executing the script. docker-compose can't
        # use $UID directly itself since it is a shell variable and not an env variable, so
        # we have to set it explicitly here.
        echo "HOST_UID=$(id -u)"
        # Your local user
        echo "ROOT_DIR=${ROOT_DIR}"
        echo "HOST_USER=${USER}"
        echo "TEST_FILE=${TEST_FILE}"
        echo "TYPO3_VERSION=${TYPO3_VERSION}"
        echo "PHP_XDEBUG_ON=${PHP_XDEBUG_ON}"
        echo "DOCKER_PHP_IMAGE=${DOCKER_PHP_IMAGE}"
        echo "EXTRA_TEST_OPTIONS=${EXTRA_TEST_OPTIONS}"
        echo "SCRIPT_VERBOSE=${SCRIPT_VERBOSE}"
        echo "CGLCHECK_DRY_RUN=${CGLCHECK_DRY_RUN}"
        echo "DATABASE_DRIVER=${DATABASE_DRIVER}"
        echo "PHP_XDEBUG_PORT=${PHP_XDEBUG_PORT}"
        echo "HOST_HOME=${HOME}"
        echo "PHP_VERSION=${PHP_VERSION}"
    } >.env
}

# Options -a and -d depend on each other. The function
# validates input combinations and sets defaults.
handleDbmsAndDriverOptions() {
    case ${DBMS} in
        mysql|mariadb)
            [ -z "${DATABASE_DRIVER}" ] && DATABASE_DRIVER="mysqli"
            if [ "${DATABASE_DRIVER}" != "mysqli" ] && [ "${DATABASE_DRIVER}" != "pdo_mysql" ]; then
                echo "Invalid option -a ${DATABASE_DRIVER} with -d ${DBMS}" >&2
                echo >&2
                echo "call \"./Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        postgres|sqlite)
            if [ -n "${DATABASE_DRIVER}" ]; then
                echo "Invalid option -a ${DATABASE_DRIVER} with -d ${DBMS}" >&2
                echo >&2
                echo "call \"./Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
    esac
}

# Load help text into $HELP
read -r -d '' HELP <<EOF
mime_converter test runner. Execute unit test suite and some other details.
Also used by github actions for test execution.

Usage: $0 [options] [file]

No arguments: Run all unit tests with PHP 7.4

Options:
    -s <...>
        Specifies which test suite to run
            - cgl: cgl test and fix all php files
            - checkBom: check UTF-8 files do not contain BOM
            - checkRst: test .rst files for integrity
            - checkTestMethodsPrefix: check tests methods do not start with "test"
            - clean: clean up build and testing related files
            - composerUpdate: "composer update", handy if host has no PHP
            - functional: functional tests
            - lint: PHP linting
            - phpstan: phpstan analyze
            - phpstanGenerateBaseline: regenerate phpstan baseline, handy after phpstan updates
            - unit: PHP unit tests

    -d <sqlite|mariadb|mysql|postgres>
        Only with -s acceptance,functional
        Specifies on which DBMS tests are performed
            - sqlite: (default) use sqlite
            - mariadb: use mariadb
            - mysql: use mysql
            - postgres: use postgres

    -a <mysqli|pdo_mysql>
        Only with -s acceptance,functional
        Specifies to use another driver, following combinations are available:
            - mysql
                - mysqli (default)
                - pdo_mysql
            - mariadb
                - mysqli (default)
                - pdo_mysql

    -p <7.4|8.0|8.1|8.2>
        Specifies the PHP minor version to be used
            - 7.4: (default): use PHP 7.4
            - 8.0: use PHP 8.0
            - 8.0: use PHP 8.1
            - 8.0: use PHP 8.2

    -t <11|12>
        Only with -s composerUpdate
        Specifies the TYPO3 core major version to be used
            - 11 (default): use TYPO3 core v11
            - 12: Use TYPO3 core v12

    -e "<phpunit options>"
        Only with -s functional|unit
        Additional options to send to phpunit tests.
        For phpunit, options starting with "--" must be added after options starting with "-".
        Example -e "-v --filter canRetrieveValueWithGP" to enable verbose output AND filter tests
        named "canRetrieveValueWithGP"

    -x
        Only with -s unit
        Send information to host instance for test or system under test break points. This is especially
        useful if a local PhpStorm instance is listening on default xdebug port 9003. A different port
        can be selected with -y

    -y <port>
        Send xdebug information to a different port than default 9003 if an IDE like PhpStorm
        is not listening on default port.

    -u
        Update existing typo3gmbh/phpXY:latest docker images. Maintenance call to docker pull latest
        versions of the main php images. The images are updated once in a while and only the youngest
        ones are supported by core testing. Use this if weird test errors occur. Also removes obsolete
        image versions of typo3gmbh/phpXY.

    -v
        Enable verbose script output. Shows variables and docker commands.

    -h
        Show this help.

EOF

# Test if docker-compose exists, else exit out with error
if ! type "docker-compose" >/dev/null; then
    echo "This script relies on docker and docker-compose. Please install" >&2
    exit 1
fi

# Go to the directory this script is located, so everything else is relative
# to this dir, no matter from where this script is called.
THIS_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null && pwd)"
cd "$THIS_SCRIPT_DIR" || exit 1

# Go to directory that contains the local docker-compose.yml file
cd ../testing-docker || exit 1

# Option defaults
ROOT_DIR=$(readlink -f ${PWD}/../../)
TEST_SUITE="unit"
DBMS="sqlite"
PHP_VERSION="7.4"
TYPO3_VERSION="11"
PHP_XDEBUG_ON=0
PHP_XDEBUG_PORT=9003
EXTRA_TEST_OPTIONS=""
SCRIPT_VERBOSE=0
CGLCHECK_DRY_RUN=""
DATABASE_DRIVER=""

# Option parsing
# Reset in case getopts has been used previously in the shell
OPTIND=1
# Array for invalid options
INVALID_OPTIONS=()
# Simple option parsing based on getopts (! not getopt)
while getopts ":s:d:a:p:n:t:e:xy:huv" OPT; do
    case ${OPT} in
    a)
        DATABASE_DRIVER=${OPTARG}
        ;;
    s)
        TEST_SUITE=${OPTARG}
        ;;
    d)
        DBMS=${OPTARG}
        ;;
    p)
        PHP_VERSION=${OPTARG}
        if ! [[ ${PHP_VERSION} =~ ^(7.2|7.3|7.4|8.0|8.1|8.2)$ ]]; then
            INVALID_OPTIONS+=("p ${OPTARG}")
        fi
        ;;
    t)
        TYPO3_VERSION=${OPTARG}
        if ! [[ ${TYPO3_VERSION} =~ ^(11|12)$ ]]; then
            INVALID_OPTIONS+=("p ${OPTARG}")
        fi
        ;;
    e)
        EXTRA_TEST_OPTIONS=${OPTARG}
        ;;
    n)
        CGLCHECK_DRY_RUN="-n"
        ;;
    x)
        PHP_XDEBUG_ON=1
        ;;
    y)
        PHP_XDEBUG_PORT=${OPTARG}
        ;;
    h)
        echo "${HELP}"
        exit 0
        ;;
    u)
        TEST_SUITE=update
        ;;
    v)
        SCRIPT_VERBOSE=1
        ;;
    \?)
        INVALID_OPTIONS+=(${OPTARG})
        ;;
    :)
        INVALID_OPTIONS+=(${OPTARG})
        ;;
    esac
done

# Exit on invalid options
if [ ${#INVALID_OPTIONS[@]} -ne 0 ]; then
    echo "Invalid option(s):" >&2
    for I in "${INVALID_OPTIONS[@]}"; do
        echo "-"${I} >&2
    done
    echo >&2
    echo "${HELP}" >&2
    exit 1
fi

# Move "7.2" to "php72", the latter is the docker container name
DOCKER_PHP_IMAGE=$(echo "php${PHP_VERSION}" | sed -e 's/\.//')

# Set $1 to first mass argument, this is the optional test file or test directory to execute
shift $((OPTIND - 1))
if [ -n "${1}" ]; then
    TEST_FILE="Web/typo3conf/ext/mime_converter/${1}"
fi

if [ ${SCRIPT_VERBOSE} -eq 1 ]; then
    set -x
fi

# Suite execution
case ${TEST_SUITE} in
clean)
    rm -rf ../../composer.lock ../../.Build/ ../../composer.json.testing
    ;;
composerUpdate)
    setUpDockerComposeDotEnv
    cp ../../composer.json ../../composer.json.orig
    if [ -f "../../composer.json.testing" ]; then
        cp ../../composer.json ../../composer.json.orig
    fi
    docker-compose run composer_update
    cp ../../composer.json ../../composer.json.testing
    mv ../../composer.json.orig ../../composer.json
    SUITE_EXIT_CODE=$?
    docker-compose down
    ;;
functional)
    handleDbmsAndDriverOptions
    setUpDockerComposeDotEnv
    case ${DBMS} in
    mariadb)
        echo "Using driver: ${DATABASE_DRIVER}"
        docker-compose run functional_mariadb10
        SUITE_EXIT_CODE=$?
        ;;
    mysql)
        echo "Using driver: ${DATABASE_DRIVER}"
        docker-compose run functional_mysql80
        SUITE_EXIT_CODE=$?
        ;;
    postgres)
        docker-compose run functional_postgres10
        SUITE_EXIT_CODE=$?
        ;;
    sqlite)
        # sqlite has a tmpfs as .Build/Web/typo3temp/var/tests/functional-sqlite-dbs/
        # Since docker is executed as root (yay!), the path to this dir is owned by
        # root if docker creates it. Thank you, docker. We create the path beforehand
        # to avoid permission issues.
        mkdir -p ${ROOT_DIR}/.Build/Web/typo3temp/var/tests/functional-sqlite-dbs/
        docker-compose run functional_sqlite
        SUITE_EXIT_CODE=$?
        ;;
    *)
        echo "Invalid -d option argument ${DBMS}" >&2
        echo >&2
        echo "${HELP}" >&2
        exit 1
        ;;
    esac
    docker-compose down
    ;;
lint)
    setUpDockerComposeDotEnv
    docker-compose run lint
    SUITE_EXIT_CODE=$?
    docker-compose down
    ;;
unit)
    setUpDockerComposeDotEnv
    docker-compose run unit
    SUITE_EXIT_CODE=$?
    docker-compose down
    ;;
update)
    # pull typo3/core-testing-*:latest versions of those ones that exist locally
    docker images typo3/core-testing-*:latest --format "{{.Repository}}:latest" | xargs -I {} docker pull {}
    # remove "dangling" typo3/core-testing--* images (those tagged as <none>)
    docker images typo3/core-testing-* --filter "dangling=true" --format "{{.ID}}" | xargs -I {} docker rmi {}
    ;;
phpstan)
    setUpDockerComposeDotEnv
    docker-compose run phpstan
    SUITE_EXIT_CODE=$?
    docker-compose down
    ;;
phpstanGenerateBaseline)
    setUpDockerComposeDotEnv
    docker-compose run phpstan_generate_baseline
    SUITE_EXIT_CODE=$?
    docker-compose down
    ;;
cgl)
    # Active dry-run for cgl needs not "-n" but specific options
    if [[ ! -z ${CGLCHECK_DRY_RUN} ]]; then
        CGLCHECK_DRY_RUN="--dry-run --diff"
    fi
    setUpDockerComposeDotEnv
    docker-compose run cgl_all
    SUITE_EXIT_CODE=$?
    docker-compose down
    ;;
checkBom)
    setUpDockerComposeDotEnv
    docker-compose run check_bom
    SUITE_EXIT_CODE=$?
    docker-compose down
    ;;
checkExceptionCodes)
    setUpDockerComposeDotEnv
    docker-compose run check_exception_codes
    SUITE_EXIT_CODE=$?
    docker-compose down
    ;;
checkTestMethodsPrefix)
    setUpDockerComposeDotEnv
    docker-compose run check_test_methods_prefix
    SUITE_EXIT_CODE=$?
    docker-compose down
    ;;
checkRst)
    setUpDockerComposeDotEnv
    docker-compose run check_rst
    SUITE_EXIT_CODE=$?
    docker-compose down
    ;;
*)
    echo "Invalid -s option argument ${TEST_SUITE}" >&2
    echo >&2
    echo "${HELP}" >&2
    exit 1
    ;;
esac

exit $SUITE_EXIT_CODE
