.PHONY: help docker-up docker-down docker-build \
        test test-mysql test-pgsql test-sqlsrv test-unit \
        test-docker test-docker-mysql test-docker-pgsql test-docker-sqlsrv test-docker-unit \
        sh coverage phpstan cs-fix cs-check ci db-init fight validate

# ----- Configuration FrankenPHP -----
DC                  = docker compose
DOCKER_APP          = $(DC) run --rm app
CONSOLE             = php bin/console

# DATABASE_URLs utilisées à l'intérieur du réseau Docker (hostnames du compose)
URL_MYSQL           = mysql://app_user:app_password@db_mysql:3306/gladiator_arena?serverVersion=8.0
URL_PGSQL           = postgresql://app_user:app_password@database:5432/gladiator_arena?serverVersion=16&charset=utf8
URL_SQLSRV          = pdo-sqlsrv://sa:StrongPassw0rd@db_mssql:1433/gladiator_arena?serverVersion=2022&charset=UTF-8

# Raccourcis injectant la bonne BDD à la volée dans le conteneur FrankenPHP
DOCKER_APP_MYSQL    = $(DC) run --rm -e DATABASE_URL='$(URL_MYSQL)' app
DOCKER_APP_PGSQL    = $(DC) run --rm -e DATABASE_URL='$(URL_PGSQL)' app
DOCKER_APP_SQLSRV   = $(DC) run --rm -e DATABASE_URL='$(URL_SQLSRV)' app

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}'

# ----- Cycle de vie des conteneurs -----
docker-up: ## Démarre tous les conteneurs (FrankenPHP, PostgreSQL, etc.)
	$(DC) up -d
	@echo "\033[32m🏛️  L'arène FrankenPHP tourne sur http://localhost:8080\033[0m"

docker-down: ## Arrête tous les conteneurs
	$(DC) down

docker-build: ## Reconstruit l'image FrankenPHP de développement
	$(DC) build app

sh: ## Ouvre un shell interactif dans le conteneur FrankenPHP
	$(DOCKER_APP) bash

# ----- Commandes spécifiques à l'Arène des Gladiateurs -----
db-init: ## Initialise le squelette Symfony via notre FrankenPHP local, la config et la BDD
	@if [ ! -f composer.json ]; then \
		echo "📦 Aucun projet trouvé. Génération du squelette Symfony via notre conteneur FrankenPHP..."; \
		$(DC) run --rm --entrypoint="" app sh -c " \
			composer create-project symfony/skeleton tmp_symfony --stability=stable --no-interaction && \
			cp -r tmp_symfony/. . && \
			rm -rf tmp_symfony \
		"; \
	fi
	@if [ ! -f config/packages/trigger_mapping.yaml ]; then \
		echo "⚙️  Création de la configuration trigger_mapping.yaml..."; \
		mkdir -p config/packages; \
		printf "trigger_mapping:\n  storage:\n    type: 'php'\n    directory: '%%kernel.project_dir%%/src/Triggers'\n    namespace: 'App\\\Triggers'\n  migrations: true\n" > config/packages/trigger_mapping.yaml; \
	fi
	@echo "🔄 Démarrage de l'infrastructure FrankenPHP..."
	$(DC) up -d --wait
	@echo "🔌 Installation du Trigger Mapping Bundle..."
	$(DC) exec app composer require talleu/trigger-mapping
	@echo "🗄️ Création de la base de données..."
	$(DC) exec app $(CONSOLE) doctrine:database:create --if-not-exists
	@echo "📜 Génération et application des migrations..."
	$(DC) exec app $(CONSOLE) doctrine:migrations:migrate --no-interaction
	@echo "🚀 Déploiement des triggers SQL..."
	$(DC) exec app $(CONSOLE) triggers:schema:update --force
	@echo "\033[32m🏛️ L'Arène FrankenPHP est prête ! http://localhost:8080 \033[0m"

fight: ## Lance une simulation de combat (déclenche le trigger SQL)
	$(DC) exec app $(CONSOLE) app:fight

validate: ## Détecte la dérive (drift) entre tes attributs PHP et la BDD (idéal pour la CI)
	$(DC) exec app $(CONSOLE) triggers:schema:validate

# ----- Tests avec le PHP LOCAL (Rapide, nécessite les extensions locales) -----
HAS_SQLSRV := $(shell php -r "echo extension_loaded('pdo_sqlsrv') ? 1 : 0;" 2>/dev/null)

ifeq ($(HAS_SQLSRV),1)
test: test-unit test-mysql test-pgsql test-sqlsrv ## Lance tous les tests (unit + SQL) avec le PHP local
else
test: test-unit test-mysql test-pgsql ## Lance les tests unitaires, MySQL et PGSQL locaux (SQL Server sauté)
	@echo ""
	@echo "ℹ️  Tests SQL Server sautés - pdo_sqlsrv n'est pas installé sur ton PHP local."
	@echo "    Utilise \`make test-docker\` pour exécuter la suite complète sans rien installer."
endif

test-mysql: ## Exécute les tests fonctionnels MySQL (PHP local)
	vendor/bin/phpunit --testsuite=mysql

test-pgsql: ## Exécute les tests fonctionnels PostgreSQL (PHP local)
	vendor/bin/phpunit -c phpunit-postgresql.xml.dist

test-sqlsrv: ## Exécute les tests fonctionnels SQL Server (PHP local)
	vendor/bin/phpunit -c phpunit-sqlserver.xml.dist

test-unit: ## Exécute les tests unitaires isolés (PHP local)
	vendor/bin/phpunit --testsuite=unit

# ----- Tests dans Docker (Zéro configuration locale requise) -----
test-docker: test-docker-unit test-docker-mysql test-docker-pgsql test-docker-sqlsrv ## Lance TOUS les tests dans le conteneur FrankenPHP

test-docker-mysql: ## Lance les tests MySQL dans le conteneur
	$(DOCKER_APP_MYSQL) vendor/bin/phpunit --testsuite=mysql

test-docker-pgsql: ## Lance les tests PostgreSQL dans le conteneur
	$(DOCKER_APP_PGSQL) vendor/bin/phpunit -c phpunit-postgresql.xml.dist

test-docker-sqlsrv: ## Lance les tests SQL Server dans le conteneur
	$(DOCKER_APP_SQLSRV) vendor/bin/phpunit -c phpunit-sqlserver.xml.dist

test-docker-unit: ## Lance les tests unitaires dans le conteneur
	$(DOCKER_APP) vendor/bin/phpunit --testsuite=unit

# ----- Qualité du Code -----
coverage: ## Génère le rapport de couverture Clover (Unit tests dans Docker)
	$(DC) run --rm -e XDEBUG_MODE=coverage app vendor/bin/phpunit --testsuite=unit --coverage-clover=coverage/clover.xml --coverage-text

phpstan: ## Lance l'analyse statique PHPStan
	vendor/bin/phpstan analyse src

cs-fix: ## Répare le style de code (PHP-CS-Fixer)
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix src

cs-check: ## Vérifie le style de code sans le modifier (mode dry-run)
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix src --dry-run --diff

ci: cs-check phpstan test ## Pipeline CI complète en local (Style + Analyse + Tests)

build: docker-build ## Alias pour reconstruire l'image FrankenPHP
install: db-init ## Alias pour initialiser l'environnement complet et la BDD
