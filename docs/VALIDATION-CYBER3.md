# Résultats effectivement vérifiés

Vérifications locales dans l'environnement de développement Codex (MySQL 8.4, base dédiée `cyber3_test`).

| Vérification | Résultat |
| --- | --- |
| PHPUnit sur PHP 8.4.27 | 53 tests, 234 assertions, tous réussis |
| Ordre aléatoire, graine 2026, PHP 8.4 | 53 tests, 234 assertions, tous réussis |
| Préparation de la base puis PHPUnit sur PHP 8.2.35 | 53 tests, 234 assertions, tous réussis |
| Contrôle des dépendances sur PHP 8.2 | Réussi |
| Configuration du conteneur Symfony, Twig et YAML | Réussis |
| Régression IDOR introduite temporairement en local | Le test ciblé échoue : HTTP 302 au lieu de 403 |
| Régression retirée et suite relancée | Tous les tests réussissent |

La régression locale consistait à remplacer temporairement le contrôle d'identité dans `ProfileController` par `if (false)`. Ce changement a été annulé immédiatement après le test et ne fait pas partie du code livré.

Ces résultats ne constituent pas une preuve d'exécution sur les deux nuages pédagogiques ou dans GitHub Actions. Les étudiants doivent encore conserver les preuves de leurs propres exécutions, des paramètres de protection, de la PR bloquée puis corrigée, de l'approbation du camarade et du déploiement après fusion. Les droits GitHub disponibles dans ce chat renvoient 403 pour la protection de branche.

Le déploiement Cyber 2 n'a pas été modifié et aucune intervention directe n'a été effectuée sur le nuage de test commun.
