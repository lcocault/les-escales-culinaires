# Escales Culinaires

Site web pour **Les Escales Culinaires**, une activité à Toulouse qui propose à la fois des ateliers de cuisine pour les enfants et une boutique gourmande.

## Périmètre du projet

Le projet couvre deux espaces distincts accessibles depuis la page d'accueil :

- 🍳 **Ateliers de cuisine** – organisation et réservation des séances de cuisine pour les enfants (site historique).
- 🛍️ **Boutique** – vente de produits et équipements de cuisine (à venir).

## Fonctionnalités – Ateliers de cuisine

- 🍳 **Séances publiques** – liste des séances à venir avec résumé
- 🛒 **Réservation en ligne** – paiement via Stripe ou Square (configurable)
- 📚 **Contenu post-séance** – accessible uniquement aux participants confirmés
- 📸 **Photos privées** – soumises au consentement renseigné à l'inscription
- ⚙️ **Interface admin** – gestion des séances, participants, présences, crédits

## Pile technique

- **PHP 8.2+** · **PostgreSQL** · hébergement **AlwaysData**
- Thème graphique inspiré de *Ratatouille*

## Installation

```bash
# 1. Copier la configuration et renseigner vos valeurs
cp config/config.example.php config/config.php

# 2. Créer la base de données PostgreSQL et initialiser le schéma
psql -U <user> -d <dbname> -f database/schema.sql

# 3. Configurer le document root sur /public
#    (sur AlwaysData : Sites > votre site > Répertoire racine = /public)
```

## Envoi d'e-mails (PHPMailer + Gmail)

Le site utilise **PHPMailer** (SMTP) pour envoyer les e-mails transactionnels (confirmation de réservation, annulation, etc.).  
La configuration se fait via les constantes `SMTP_*` dans `config/config.php`.

### Utiliser Gmail comme serveur SMTP

1. Activez la **validation en deux étapes** sur le compte Google expéditeur.
2. Créez un **mot de passe d'application** :
   - Accédez à <https://myaccount.google.com/apppasswords>
   - Choisissez « Courrier » et votre appareil, puis copiez le mot de passe à 16 caractères généré.
3. Renseignez les constantes suivantes dans `config/config.php` :

```php
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'votre-adresse@gmail.com');
define('SMTP_PASS',      'xxxx xxxx xxxx xxxx'); // mot de passe d'application
define('SMTP_FROM',      'votre-adresse@gmail.com');
define('SMTP_FROM_NAME', 'Escales Culinaires');
```

> **Remarque** : N'utilisez pas votre mot de passe Gmail habituel ; Gmail exige un *App Password* dédié lorsque la validation en deux étapes est activée.

## Paiement

Le fournisseur de paiement est configurable via la constante `PAYMENT_PROVIDER` dans `config/config.php` :

- `'stripe'` – Stripe Checkout (par défaut)
- `'square'` – Square Payment Links

Voir `config/config.example.php` pour les clés requises selon le fournisseur choisi.

## CI / Déploiement automatique (GitHub Actions)

Le workflow `.github/workflows/deploy.yml` s'exécute automatiquement à chaque push :

1. **Tests** – installe les dépendances PHP et lance la suite PHPUnit.
2. **Déploiement** (sur push vers `main` uniquement, si les tests passent) – synchronise les fichiers vers AlwaysData via `rsync` over SSH.

Vous pouvez aussi déclencher le déploiement **manuellement** depuis l'interface GitHub :
> **Actions** › *CI / Deploy to AlwaysData* › **Run workflow** (bouton en haut à droite de la liste des exécutions).

### Secrets GitHub requis

Renseignez ces quatre secrets dans **Settings › Secrets and variables › Actions** de votre dépôt :

| Secret | Exemple | Description |
|---|---|---|
| `ALWAYSDATA_SSH_HOST` | `ssh-username.alwaysdata.net` | Hôte SSH AlwaysData |
| `ALWAYSDATA_SSH_USER` | `username` | Identifiant SSH AlwaysData |
| `ALWAYSDATA_SSH_KEY` | *(contenu du fichier `.pem`)* | Clé SSH privée (format PEM) |
| `ALWAYSDATA_REMOTE_PATH` | `/home/username/www` | Chemin absolu sur le serveur |
| `ALWAYSDATA_SSH_KNOWN_HOSTS` | *(sortie de `ssh-keyscan <host>`)* | Empreinte de l'hôte SSH vérifiée |
| `APP_BASE_URL` | `https://escales-cours.alwaysdata.net` | URL publique du site (sans slash final) |

Pour obtenir la valeur de `ALWAYSDATA_SSH_KNOWN_HOSTS`, exécutez **une seule fois** depuis votre poste :

```bash
ssh-keyscan ssh-username.alwaysdata.net
```

Copiez la ligne affichée et enregistrez-la comme secret.

> **Remarque** : `config/config.php` n'est jamais déployé par le workflow. Copiez-le manuellement sur le serveur lors de la première installation.

### Vérifier que le déploiement fonctionne

Après chaque push vers `main`, le workflow effectue automatiquement un **smoke test** : il interroge le point de contrôle `<APP_BASE_URL>/health.php` (jusqu'à 5 tentatives espacées de 5 s) et vérifie qu'il répond `{"status":"ok"}`.

Vous pouvez aussi le tester manuellement dans un navigateur ou avec curl :

```bash
curl https://votre-site.alwaysdata.net/health.php
# {"status":"ok"}
```

Si le smoke test échoue, le workflow se termine en erreur et vous en êtes notifié par GitHub.

## Développement

Voir [`.github/copilot-instructions.md`](.github/copilot-instructions.md) pour les guidelines complètes destinées aux assistants IA et aux contributeurs.
