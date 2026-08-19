# Contribuer à Sodium Webmail

Merci de contribuer à l’amélioration de Sodium.

## Avant de commencer

1. Recherchez une issue existante avant d’en ouvrir une nouvelle.
2. Pour une évolution importante, décrivez d’abord le besoin et le comportement attendu.
3. Ne publiez jamais de clé de licence, mot de passe, configuration serveur, donnée personnelle ou contenu de messagerie.
4. Les vulnérabilités doivent suivre exclusivement la procédure de [SECURITY.md](SECURITY.md).

## Développement

- utilisez une branche dédiée créée depuis `main` ;
- ciblez PHP 8.2 ou supérieur ;
- conservez Bootstrap et Bootstrap Icons dans leurs versions locales ;
- ajoutez les nouveaux textes d’interface dans les six catalogues de `languages/` ;
- exécutez `find . -type f -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l` ;
- documentez les changements visibles dans `CHANGELOG.md`.

## Pull request

La pull request doit expliquer le problème, la solution, les tests réalisés et les éventuelles migrations. Une contribution peut être adaptée ou refusée pour des raisons de sécurité, de cohérence produit, de licence ou de maintenance.

Sodium demeure un logiciel propriétaire. Une contribution acceptée n’accorde aucun droit d’exploitation du logiciel en dehors d’une licence valide et des accords conclus avec Jessy System.
