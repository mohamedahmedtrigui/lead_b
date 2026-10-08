# Déploiement — Render (API + front) et Neon (base de données)

```
Navigateur ──► miraldrive-lead-app.onrender.com   (site statique React)
                   │  /api/*  et  /sanctum/*  (réécriture Render, même origine)
                   ▼
               miraldrive-lead-api.onrender.com   (Laravel, Docker)
                   │  SSL
                   ▼
               Neon PostgreSQL 16 (Francfort)  ──► PITR Neon + sauvegarde locale quotidienne
```

Le front appelle l’API via sa **propre adresse** : les cookies de session Sanctum
fonctionnent sans domaine personnalisé (`onrender.com` est un suffixe public : deux
sous-domaines y sont considérés comme deux sites différents).

---

## 1. Neon (base de données)

1. Créer un compte sur <https://neon.tech> → **New project**
   - Nom : `miraldrive-lead`
   - **Postgres version : 16** (identique aux outils `pg_dump` installés sur le PC de sauvegarde)
   - **Region : AWS Europe Central 1 (Frankfurt)** (même région que Render)
2. **Connect** → décocher **Connection pooling** → copier l’URL *directe* :
   `postgresql://USER:MOT_DE_PASSE@ep-xxxx.eu-central-1.aws.neon.tech/neondb?sslmode=require`
3. Sur le PC, dans `lead_b/.env` : `NEON_DB_URL="<l’URL copiée>"`
4. Créer le schéma puis copier toutes les données actuelles (admin, script, leads, historique) :

   ```powershell
   cd lead_b
   php artisan migrate --database=neon --force
   php artisan db:copy mysql neon
   ```

## 2. Render (API + front)

1. Créer un compte sur <https://render.com> et connecter GitHub (accès aux dépôts `lead_b` et `lead_f`).
2. **New + → Blueprint** → dépôt **`lead_b`** → Render lit `render.yaml` et propose 2 services.
3. Renseigner les 2 secrets demandés :
   - `APP_KEY` → sortie de `php artisan key:generate --show` (à exécuter sur le PC)
   - `DB_URL` → la même URL Neon directe qu’à l’étape 1.2
4. **Apply**. Premier déploiement : ~5–8 min pour l’API (image Docker), ~1 min pour le front.
   Les migrations s’exécutent automatiquement au démarrage de l’API.
5. Vérifier les adresses attribuées. Si Render a ajouté un suffixe (nom déjà pris),
   remplacer les URLs marquées `SERVICE URL` dans `render.yaml`, puis `git push`.
6. Ouvrir `https://miraldrive-lead-app.onrender.com` et se connecter avec le compte admin.
7. Changer immédiatement le mot de passe admin (saisi masqué) :

   ```powershell
   php artisan user:password admin@miraldrive.com --connection=neon
   ```

> Plan **Free** : l’API s’endort après 15 min d’inactivité (≈ 1 min de réveil) et Neon
> après 5 min. Pour la production : Render **Starter** (API toujours active) et Neon
> **Launch** (PITR 7 jours, mise en veille désactivable).

## 3. Sauvegarde locale quotidienne

1. Dans phpMyAdmin, créer la base **`lead_backup`** (utf8mb4_unicode_ci) : copie MySQL
   locale utilisable hors ligne.
2. Premier essai (PowerShell, dans `lead_b`) :

   ```powershell
   .\scripts\backup-neon.ps1 -Mirror
   ```

   → dump dans `%USERPROFILE%\MiralDrive-Backups\neon\` + base `lead_backup` remplie.
3. Planifier tous les jours à 02:00 (rattrapé au démarrage si le PC était éteint) :

   ```powershell
   .\scripts\register-backup-task.ps1
   ```

Rétention : **24 h** (le dernier dump n’est jamais supprimé). Pour garder plus longtemps :
`-KeepHours 72` dans la tâche planifiée. Journal : `backup.log` dans le dossier de sauvegarde.

## 4. Restaurer

| Situation | Action |
|---|---|
| Erreur de manipulation récente | Neon → **Restore** (point-in-time, fenêtre 6 h Free / 7 j Launch) ou nouvelle branche à l’instant voulu |
| Base Neon perdue / autre compte | `pg_restore --clean --if-exists --no-owner --dbname="<URL Neon directe>" lead-AAAAMMJJ-HHMMSS.dump` |
| Neon indisponible | Lancer l’application en local sur la copie : `DB_CONNECTION=mysql`, `DB_DATABASE=lead_backup` dans `lead_b/.env` |
| Revenir sur Neon après un travail hors ligne | `php artisan db:copy local_backup neon` |

## 5. Tolérance aux pannes et montée en charge

- **Données** : stockage Neon répliqué sur plusieurs zones (AZ), journal WAL durable ; la
  compute est sans état et redémarre automatiquement.
- **3 copies, 2 supports, 1 hors site** : Neon (PITR) + dump local quotidien + copie MySQL
  locale. Option recommandée : copier aussi `MiralDrive-Backups` vers OneDrive / Google Drive.
- **Montée en charge** : autoscaling Neon (jusqu’à 2 CU en Free, 16 CU en Launch) ; API
  sans état (sessions en base) → plusieurs instances Render possibles. Au-delà d’une
  instance, utiliser l’URL **pooled** (`-pooler`) de Neon pour `DB_URL`.
- **Lectures lourdes** (tableaux de bord) : une *read replica* Neon si nécessaire.
- **Tests** : la CI exécute toute la suite sur SQLite **et PostgreSQL 16** à chaque push.
