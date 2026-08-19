# Traductions

Sodium prend en charge `fr`, `en`, `de`, `it`, `es` et `pt`. Le français est la langue de référence et de repli.

Les catalogues se trouvent dans `languages/<code>.php`. Chaque clé ajoutée à `languages/fr.php` doit être présente dans les cinq autres catalogues. Les variables utilisent la forme `:nom` et ne doivent pas être traduites.

Une traduction doit rester concise, professionnelle et adaptée au vocabulaire d’un webmail. Elle ne doit jamais modifier les contenus utilisateurs : corps de message, sujet, adresse, signature ou pièce jointe.

Avant une contribution, vérifiez la syntaxe de chaque catalogue avec `php -l` et comparez leurs clés.
