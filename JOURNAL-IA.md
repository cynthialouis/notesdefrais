# Journal IA

Une ligne par intervention : proposition refusée, API inventée, version obsolète, spec mal interprétée, test du harness. Entrées réelles uniquement.

| Date | Étape | Ce que l'agent a proposé | Ce que j'ai fait | Pourquoi |
|---|---|---|---|---|
| 11/10 | Test du subagent `verificateur-docs` (questions volontairement datées : `ReactDOM.render` de React 18, `@SWG\Response` de Nelmio 3) | A signalé d'abord l'écart de version (React 19.3.0 et Nelmio 5.13.1 installés), puis répondu pour la bonne version (`createRoot`, `#[OA\Response]`), avec la doc officielle et le code installé comme sources. Réponses un peu au-delà de 15 lignes, niveau de preuve pas étiqueté explicitement | Test validé, aucune correction du subagent | Comportement attendu sur le point critique (écart de version). Les deux écarts de format sont mineurs. Affirmations sur Nelmio vérifiées dans `vendor/` |
