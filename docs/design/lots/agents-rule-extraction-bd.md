# Projet Extraction BD – règle permanente de l'espace de travail
> À copier dans `.agents/rules/extraction-bd.md`.

Avant toute tâche sur ce projet :
1. Lis `AGENTS.md` (à la racine) : ce sont les règles obligatoires (interdits, sécurité, Git, rapport de fin de lot).
2. Lis `docs/design/decisions.md` : les choix techniques validés ou encore « Proposée ».
3. Si Marc te confie un lot, lis le fichier `docs/design/lots/lot-NN-*.md` correspondant, puis les documents et captures qu'il cite (`docs/design/design-tokens.md`, `docs/design/ecrans-*.md`, `docs/design/catalogue/*`, `docs/design/admin/*.png`, `docs/design/metier/*.png`).
4. **Si tu ne peux pas ouvrir un fichier cité, dis-le explicitement et arrête-toi. Ne devine jamais son contenu.**
5. Travaille sur **un seul lot à la fois**, présente d'abord un plan de 10 lignes maximum et attends la validation de Marc avant d'écrire du code.
6. Termine chaque lot par le **rapport de fin de lot** (modèle dans `AGENTS.md`) et attends la validation avant le lot suivant.
7. Rappels critiques : Oracle en **lecture seule** avec le compte `APP_RO` ; aucune migration sur la connexion `oracle` ; ne jamais lire ni afficher `.env` ; jamais de `git push`, `git merge` ni `git reset --hard`.
