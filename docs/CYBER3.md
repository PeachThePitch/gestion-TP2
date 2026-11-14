# Cyber 3 — tests de sécurité et intégration continue

## Les trois environnements

- **Nuages de développement (chacun des deux étudiants)** : développement, PHPUnit, base `cyber3_test` dédiée.
- **GitHub Actions** : machine et MySQL temporaires propres à chaque job, sans accès aux nuages.
- **Nuage de test commun** : application déployée après fusion sur `main`. Ne jamais y lancer la préparation de base ou PHPUnit.

Le workflow `deploy.yml` de Cyber 2 est conservé : son déclencheur reste le push sur `main`. Aucune intervention directe sur le nuage de test n'est nécessaire pour installer la suite. Les nouvelles fonctionnalités sont livrées par le déploiement habituel après fusion.

## Installation sur chaque nuage de développement

Prérequis : PHP 8.2 ou 8.4, Composer 2, MySQL, extensions `pdo_mysql`, `ctype`, `iconv`, `dom`, `xml`, `mbstring` (et `intl` recommandé).

Depuis la racine du dépôt :

```bash
composer install --no-interaction --prefer-dist --no-plugins --no-scripts
composer dump-autoload --no-interaction --no-scripts
```

Les recettes Composer ne sont pas réappliquées pour éviter des modifications automatiques de la configuration. Ne pas utiliser `composer update` pour installer les versions du groupe : elles sont enregistrées dans `composer.lock`.

Avec un compte MySQL administrateur **sur le nuage de développement uniquement**, créer une base et un utilisateur limité à cette base :

```sql
CREATE DATABASE cyber3_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cyber3_test'@'localhost' IDENTIFIED BY 'choisir-un-mot-de-passe-local';
GRANT ALL PRIVILEGES ON cyber3_test.* TO 'cyber3_test'@'localhost';
```

Créer `.env.test.local` (ignoré par Git), adapté à votre serveur, sans y recopier les accès de l'application :

```dotenv
DATABASE_URL="mysql://cyber3_test:MOT_DE_PASSE_ENCODE_URL@127.0.0.1:3306/cyber3?serverVersion=8.0&charset=utf8mb4"
```

Adapter `serverVersion` à votre serveur (par exemple `8.4` pour MySQL 8.4). Les caractères spéciaux du mot de passe doivent être encodés pour une URL. Doctrine ajoute automatiquement le suffixe `_test` : **la base réelle sera `cyber3_test`**, et non `cyber3`. Les valeurs de `.env.test` sont des valeurs locales d'exemple, pas des identifiants de production. Une variable `DATABASE_URL` déjà exportée dans le terminal est prioritaire sur ces fichiers ; vérifier qu'elle est celle des tests avant de continuer.

```bash
APP_ENV=test php bin/prepare-test-db.php
APP_ENV=test vendor/bin/phpunit
```

La première commande **recrée les tables de tests**. Elle refuse une base dont le nom ne se termine pas par `_test` (ou `_test` suivi de chiffres). Le compte SQL limité empêche en plus toute écriture sur les bases de l'application. Le dépôt n'a pas de migration initiale complète : le schéma de tests est créé depuis les entités Doctrine, sans changer les migrations de Cyber 2.

Les jeux de données Alice, Bob, administrateur et compte suspendu sont recréés avant chaque cas. Les mots de passe sont réellement hachés ; les connexions passent par le formulaire et le système de sécurité Symfony, sans `loginUser()` qui contournerait l'authentification.

```bash
# Un mécanisme précis
APP_ENV=test vendor/bin/phpunit tests/Functional/AuthenticationTest.php
# Vérifier l'indépendance de l'ordre d'exécution
APP_ENV=test vendor/bin/phpunit --order-by=random --random-order-seed=2026
# Rapport exploitable par la CI
APP_ENV=test vendor/bin/phpunit --log-junit var/test-results/junit.xml
```

Dans l'environnement Codex configuré avec Docker, utiliser :

```bash
docker compose -f /workspace/cloud-setup/compose.yaml exec -T \
  -e APP_ENV=test \
  -e 'DATABASE_URL=mysql://cyber3_test:cyber3_test@database:3306/cyber3?serverVersion=8.4&charset=utf8mb4' \
  app php bin/prepare-test-db.php
# Remplacer la fin par « app vendor/bin/phpunit » pour exécuter les tests.
```

## Couverture des exemples du PDF

| Tests du PDF | Fichier / vérifications |
| --- | --- |
| 1 | `AuthenticationTest` : visiteur refusé, URL directe, utilisateur connecté autorisé |
| 2 | `AuthenticationTest` : connexion valide et session conservée entre profil et mot de passe |
| 3 | `AuthenticationTest` : mauvais mot de passe, celui de Bob, identifiant inexistant |
| 4 | `AuthenticationTest` : déconnexion, nouvelle requête et rejeu du cookie précédent refusés |
| 5 | `AuthorizationTest` + visiteur dans `AuthenticationTest` : `/admin`, administrateur, utilisateur 403 |
| 6 | `AuthorizationTest` : liste, modification, suspension, suppression et refus aux utilisateurs |
| 7 | `AuthorizationTest` : profil personnel, ID remplacé dans l'URL, POST direct sur Bob, données inchangées, administrateur autorisé |
| 8 | `AuthorizationTest` : prénom modifiable, injection `roles` refusée, rôles de Bob protégés, rôle autorisé par l'administrateur |
| 9 | `RegistrationTest` : nom, prénom, adresse, ville, code postal, email et mot de passe vides |
| 10 | `RegistrationTest` : code postal, email, longueur du nom, mot de passe court, faible et compromis |
| 11 | `RegistrationTest` : inscription valide, compte et historique créés, hachage et connexion |
| 12 | `RegistrationTest` : absence de compte pour chaque refus, correction et absence de doublon |
| 13 | `AccountSecurityTest` : compte actif, suspension réelle par l'administrateur, refus, réactivation et connexion |
| 14 | `AccountSecurityTest` : A → B → C, réutilisation de A et B refusée, hash et historique contrôlés |
| 15 | `AccountSecurityTest` : mot de passe actuel correct/incorrect/absent, nouveau mot de passe invalide, ancienne et nouvelle connexion |

Des tests supplémentaires couvrent la confirmation du mot de passe, les jetons CSRF des opérations sensibles, l'interdiction des modifications par GET, les rôles inconnus et le nettoyage des journaux lors d'une suppression.

### Simulation des mots de passe compromis

`config/services_test.yaml` injecte un `MockHttpClient` dans le **véritable validateur** `NotCompromisedPasswordValidator`. `PwnedClientFactory` reproduit la réponse de l'API par préfixe SHA-1 : un mot de passe de test est déclaré compromis, les autres non. La validation n'est pas désactivée. Aucun appel externe n'est nécessaire pour exécuter les tests et les résultats sont reproductibles. Cette simulation est chargée exclusivement en environnement `test` ; l'application conserve son contrôle réel.

### Exemple d'explication d'un test à l'oral

`testUserCannotModifyOtherProfileEvenWithForgedRequest` :

1. Préparer Alice et Bob dans la base de tests.
2. Connecter Alice par HTTP et obtenir son formulaire et son jeton CSRF.
3. Envoyer les données vers l'URL contenant l'identifiant de Bob.
4. Vérifier le statut **403** et relire le prénom de Bob en base pour prouver qu'il est inchangé.

Le contrôle porte sur l'autorisation côté serveur ; cacher un bouton ne suffirait pas.

## GitHub Actions et protection de `main`

Le workflow `PHP CI` s'exécute à l'ouverture ou à la mise à jour d'une PR vers `main`. Il installe PHP et les dépendances verrouillées, prépare un MySQL temporaire isolé, vérifie la configuration et lance PHPUnit. Deux jobs vérifient PHP 8.2 et 8.4. Le rapport JUnit est disponible dans les artefacts même en cas d'échec de tests.

Après une première exécution du workflow, un administrateur du dépôt doit vérifier/configurer dans **Settings → Rules → Rulesets** (ou **Branches → Branch protection rules**) une règle pour `main` :

- Exiger une pull request et au moins **une approbation d'un autre membre**.
- Exiger les contrôles `Tests de sécurité (PHP 8.2)` et `Tests de sécurité (PHP 8.4)`.
- Exiger une branche à jour avant fusion.
- Appliquer la règle aux administrateurs, sans contournement permettant de pousser directement sur `main`.
- Interdire les force-push et la suppression de `main`.

Attention : une règle de pull request seule ne rend pas les tests obligatoires. Le push direct effectué lors de Cyber 2 avait signalé un contournement de règle ; vérifier les droits de bypass dans les paramètres. Les droits de l'intégration de ce chat ne permettent pas de lire ou modifier la protection de branche (réponse GitHub 403) : cette étape doit être faite sur GitHub par un administrateur. Un workflow vert ne prouve pas à lui seul que la fusion serait bloquée s'il était rouge.

## Démonstration demandée par le TP

À réaliser après activation de la protection, sur une branche et une PR, jamais directement sur `main` :

```bash
git switch main
git pull --ff-only origin main
git switch -c demo/cyber3-regression
```

Dans `ProfileController`, remplacer temporairement la condition de refus par `if (false)` pour simuler une faille IDOR. Le test ciblé doit échouer :

```bash
APP_ENV=test vendor/bin/phpunit --filter testUserCannotModifyOtherProfileEvenWithForgedRequest
```

Pousser cette régression uniquement sur `demo/cyber3-regression`, créer une PR et conserver les preuves : test rouge dans Actions, message indiquant que la fusion est bloquée. **Ne jamais fusionner la version vulnérable.** Restaurer immédiatement la condition correcte et pousser la correction sur la même branche. Conserver ensuite : tests verts, approbation réelle du camarade, fusion et workflow de déploiement réussi. Vérifier sur le nuage de test : inscription, connexion, profil et accès administrateur. Aucun faux compte rendu d'approbation ou de déploiement ne remplace ces étapes.

La base de tests reste sur les nuages de développement et dans Actions ; aucune commande de préparation de tests ne doit être exécutée sur le nuage de test commun.

## Support pour l'oral

Préparer un diaporama comprenant : contexte Cyber 2 → Cyber 3, architecture des trois environnements, tests et assertions commentés, simulation de l'API, résultats locaux, PR rouge puis verte, protection de `main`, approbation et déploiement. Capturer les résultats réellement obtenus et les légender avec leur contexte. Ne pas montrer de secrets dans les captures.

| Terme | Définition |
| --- | --- |
| Authentification | Vérification de l'identité, ici par email et mot de passe |
| Autorisation | Décision permettant ou refusant une action à un utilisateur identifié |
| Moindre privilège | Accorder seulement les permissions nécessaires |
| IDOR / BOLA | Accès à un objet d'un autre utilisateur par manque de contrôle d'autorisation |
| Validation serveur | Contrôle des données indépendamment du navigateur |
| Régression / non-régression | Défaut réintroduit / test destiné à détecter son retour |
| Fixture | Jeu de données reproductible préparé avant un test |
| Mock | Simulation contrôlée d'une dépendance externe |
| Assertion | Vérification automatique d'un résultat attendu |
| Isolation | Séparation des données et de l'état entre tests et environnements |
| CSRF | Requête malveillante exploitant la session d'une victime ; jeton et contrôle d'origine protègent les actions |
| CI/CD | Vérifications automatiques et livraison après validation |
| Protection de branche | Règles conditionnant la fusion, par exemple tests verts et approbation |
