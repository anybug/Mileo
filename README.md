# Mileo

Mileo est une application de calculs d'indémnités kilométriques simple et rapide. Mileo est l'application qui vous permet de gérer vos frais kilométriques en quelques minutes.

## Sommaire
- [Pré-requis](#pré-requis)
- [Stack de développement](#stack-de-développement)
- [Installation](#installation)
  - [Modifier la directive `DocumentRoot` d'Apache si nécessaire](#directive-documentroot-dapache-si-nécessaire)
  - [Installation des dépendances](#installation-des-dépendances)
  - [Variables d'environnement](#variables-denvironnement)
  - [Migration de la base de données](#migration-de-la-base-de-données)


## Pré-requis

- Composer
- PHP 8.2+
- Apache
- MariaDB 10+

## Stack de développement

- Symfony 6
- PHP 8.2
- EasyAdminBundle 4
- JQuery (refonte Vanilla dans la roadmap)
- CSS natif + Bootstrap (intégré à Easyadmin)
- AssetMapper


## Installation

### Directive `DocumentRoot` d'Apache (si nécessaire)

La directive `DocumentRoot` d'Apache doit pointer sur le dossier `public` du projet afin qu'il puisse lire le fichier d'entrée `index.php`.


### Installation des dépendances

Ensuite, installer les dépendances à l'aide de Composer en lançant la commande suivante :

```bash
composer install
```
Attention: laisser les demandes de recipes par défaut

Commande pour déployer les assets (pas nécessaire à ce stade mais au cas où) :

```bash
php bin/console asset-map:compile
```

### Variables d'environnement

Décommenter les variables d’environnement pésentes dans le fichier `.env` ou copier le fichier `.env` vers `.env.local`, préférable pour essayer l’application en environnement Staging avec des API de test.

Exemple de fichier .env minimal : 
```
APP_ENV=prod
# Voir : https://symfony.com/doc/current/doctrine.html
DATABASE_URL="mysql://db_user:your_password@127.0.0.1:3306/db_name"
```
D'autres variables optionnelles sont présentes dans le fichier .env, elles correspondent en majorité à des clés API. Si elles ne sont pas définies ou si elles sont factices, les fonctionnalités de d’autocomplétion d’adresse, calcul de distance et paiement en ligne de l'application ne fonctionneront pas.

### Migration de la base de données

Enfin, créer la structure de la base de données. Utiliser la commande :

```bash
php bin/console doctrine:schema:update --force  

# ou

php bin/console d:s:u --force  
```
