# Trame du diaporama et démonstration

Cette trame doit être complétée avec les noms du groupe et les captures réellement obtenues sur vos nuages et GitHub. Ne pas présenter les étapes restantes comme déjà effectuées.

## Diapositive 1 — Contexte et objectif

- Cyber 2 : comptes, connexion, mots de passe, habilitations et déploiement automatique.
- Cyber 3 : prouver les règles de sécurité automatiquement et détecter leur régression.
- Répartition des tâches entre membres du groupe.

À expliquer : un site qui fonctionne lors d'un essai manuel peut devenir vulnérable lors d'une modification ultérieure.

## Diapositive 2 — Architecture

Schéma conseillé : deux nuages de développement → branches et pull requests GitHub → tests dans une machine temporaire → approbation et fusion → déploiement sur le nuage de test commun.

Préciser que les bases de tests ne contiennent aucune donnée de l'application utilisée par les étudiants ou du serveur commun.

## Diapositive 3 — Outils et installation

Présenter PHP, Symfony, PHPUnit, BrowserKit, DomCrawler et MySQL.

Montrer `.env.test`, le suffixe `_test` et le compte SQL limité à cette base. Ne pas capturer `.env.test.local` s'il contient un mot de passe personnel.

## Diapositive 4 — Structure d'un test

Ouvrir `testUserCanModifyOwnProfile` : préparation des fixtures, connexion HTTP, soumission du formulaire, assertions sur la réponse puis relecture en base.

Expliquer les statuts 200, 302, 403 et 422. Distinguer l'assertion HTTP de l'assertion sur les données : un refus affiché ne suffit pas si des données ont quand même été modifiées.

## Diapositive 5 — Authentification et session

Présenter les tests 1 à 4 : pages protégées, connexion correcte, erreurs d'identifiants, déconnexion et rejeu de l'ancien cookie.

Démontrer pourquoi les tests passent par le véritable formulaire de connexion et ne simulent pas un utilisateur déjà connecté.

## Diapositive 6 — Autorisations et IDOR

Présenter les tests 5 à 8 : administrateur, utilisateur ordinaire, modification de son profil et accès au profil de Bob.

Montrer le contrôle de l'identité dans `ProfileController` et le champ `roles` ajouté uniquement par décision serveur pour un administrateur. Un paramètre ajouté à la main n'accorde pas un nouveau droit.

## Diapositive 7 — Validation et inscription

Présenter les tests 9 à 12 : champs vides, email, code postal, longueur du nom, mot de passe, création et absence de doublon.

Montrer un fournisseur de données (`DataProvider`) : chaque jeu est un cas indépendant. Souligner que les requêtes de tests peuvent ignorer les contraintes HTML, afin de contrôler le serveur.

## Diapositive 8 — Suspension et mots de passe

Présenter les tests 13 à 15 : suspension/réactivation, A → B → C et refus d'un ancien mot de passe, vérification du mot de passe actuel et nouvelle connexion.

Expliquer le hachage : la base ne conserve pas les mots de passe en clair ; la vérification utilise le hasher.

## Diapositive 9 — Dépendance externe simulée

Présenter `PwnedClientFactory` et `config/services_test.yaml` : le client HTTP est simulé mais le validateur de mot de passe compromis reste réel.

Expliquer le protocole par préfixe SHA-1 sans envoyer le mot de passe complet. Dire pourquoi dépendre d'Internet pendant les tests rendrait les résultats moins reproductibles.

## Diapositive 10 — Démonstration locale

Exécuter sur votre nuage de développement :

```bash
APP_ENV=test vendor/bin/phpunit
APP_ENV=test vendor/bin/phpunit --filter testUserCannotModifyOtherProfileEvenWithForgedRequest
```

Commenter le nombre de tests, d'assertions et le code de retour. Les résultats locaux Codex figurent dans `VALIDATION-CYBER3.md` ; faire vos propres captures sur le nuage pédagogique.

## Diapositive 11 — PR rouge puis verte et protection

Afficher le workflow de tests, sa base MySQL temporaire, puis les paramètres de protection de `main`.

Démontrer la PR avec une régression volontaire, l'échec du contrôle et l'impossibilité de fusionner. Corriger sur la même branche, montrer les contrôles verts et l'approbation du camarade. Ne jamais fusionner le code vulnérable.

Captures nécessaires : détail de l'assertion échouée, contrôles requis et blocage, correction, approbation réelle.

## Diapositive 12 — Livraison et bilan

Après fusion, montrer le workflow `Deploy to Server C` existant puis vérifier l'application sur le nuage de test commun.

Présenter les limites : les tests couvrent des scénarios définis ; ils ne prouvent pas l'absence de toute vulnérabilité. Mentionner les suites exécutées, les vérifications encore manuelles et les améliorations possibles.

Terminer avec le lexique de `CYBER3.md`. Chaque membre doit pouvoir expliquer une assertion et modifier un test simple devant le jury.
