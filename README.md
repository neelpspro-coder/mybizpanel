# MyBizPanel - Système de Gestion Collaborative

![MyBizPanel](https://img.shields.io/badge/Version-1.0.0-violet) ![PHP](https://img.shields.io/badge/PHP-8.0+-blue) ![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange)

**MyBizPanel** est un système intranet collaboratif complet conçu pour la gestion d'entreprise. Il centralise la gestion des projets, tâches, finances, clients et communications d'équipe dans une interface moderne et intuitive.

## 🌟 Fonctionnalités Principales

### 📊 **Dashboard Collaboratif**
- Vue d'ensemble des statistiques globales de l'équipe
- Projets actifs et revenus collectifs en temps réel
- Activité récente de toute l'organisation
- Actions rapides pour les tâches courantes

### 🏗️ **Gestion de Projets**
- **Projets partagés** visibles par toute l'équipe
- Association de clients aux projets
- Suivi des statuts (Planification, Actif, Terminé, En pause)
- Gestion des priorités et budgets
- Traçabilité des auteurs

### ✅ **Gestion des Tâches**
- Tâches collaboratives avec dates d'échéance
- Liaison aux projets existants
- Statuts : À faire, En cours, Terminé
- Vue d'ensemble avec indicateurs visuels

### 💰 **Finances Collaboratives**
- **Transactions partagées** pour suivi financier global
- Association des factures aux clients
- Catégorisation des revenus/dépenses
- Justificatifs via liens Discord/images
- Calculs automatiques des totaux et soldes

### 👥 **Gestion Clients**
- Base clients centralisée de l'équipe
- Fiches complètes avec contacts et notes
- Statistiques par client (CA, projets, transactions)
- Sélection dans projets et finances

### 📝 **Base de Connaissances**
- **Notes partagées** pour documentation collaborative
- Recherche intégrée dans le contenu
- Organisation par auteur et date
- Interface de type wiki d'équipe

### 💬 **Communication**
- **Chat d'équipe** en temps réel
- Messages avec format utilisateur:message
- Suppression de ses propres messages
- Historique des conversations

### 🔔 **Système de Notifications**
- **Pop-ups automatiques** lors d'ajouts (projets, tâches, etc.)
- Notifications qui disparaissent après 5 secondes
- Types : info, succès, avertissement, erreur
- Historique des notifications avec gestion

### 🛡️ **Administration & Sécurité**
- Gestion des utilisateurs et rôles (admin, support, employé)
- Logs système complets
- Statistiques globales de l'application
- Interface d'administration dédiée

## 🚀 Installation

### Prérequis
- **PHP 7.4+** avec extensions PDO, MySQL
- **MySQL 5.7+** ou MariaDB 10.3+
- **Serveur web** (Apache, Nginx) ou hébergement compatible

### Installation sur Hostinger

1. **Téléchargez** les fichiers du projet
2. **Extractez** le contenu dans votre dossier `public_html`
3. **Importez** le fichier `database.sql` dans phpMyAdmin
4. **Configurez** la base de données dans `config.php` :

```php
$host = 'localhost';        // Généralement localhost
$dbname = 'mybizpanel';     // Nom de votre base
$username = 'votre_user';   // Utilisateur MySQL
$password = 'votre_pass';   // Mot de passe MySQL
```

5. **Accédez** à votre site via votre domaine

### Compte Admin par Défaut
- **Email** : `admin@mybizpanel.com`
- **Mot de passe** : `password`

⚠️ **Important** : Changez ces identifiants après la première connexion !

## 📱 Fonctionnalités Avancées

### 🎯 **Système Collaboratif**
- **Données partagées** : Tous les utilisateurs voient les mêmes projets, tâches, clients
- **Traçabilité** : Chaque élément conserve l'information de son créateur
- **Permissions** : Modification possible par tous, suppression contrôlée
- **Notifications automatiques** : Alert en temps réel sur les nouvelles activités

### 🔄 **Notifications Automatiques**
Les notifications pop-up apparaissent automatiquement lors de :
- Création d'un nouveau projet
- Ajout d'une tâche
- Nouvelle transaction financière
- Envoi d'un message dans le chat
- Création d'un client

### 🎨 **Interface Moderne**
- **Design responsive** adapté mobile/tablette/desktop
- **Thème violet/pastel** professionnel et moderne
- **Animations CSS** fluides et élégantes
- **Icons FontAwesome** pour une UX claire

## 🗂️ Structure du Projet

```
mybizpanel-php-final/
├── config.php              # Configuration BDD et fonctions
├── index.php               # Point d'entrée principal
├── database.sql            # Structure et données initiales
├── pages/                  # Pages de l'application
│   ├── login.php           # Authentification
│   ├── dashboard.php       # Tableau de bord
│   ├── projects.php        # Gestion projets
│   ├── tasks.php           # Gestion tâches
│   ├── finances.php        # Gestion finances
│   ├── clients.php         # Gestion clients
│   ├── notes.php           # Base connaissances
│   ├── messages.php        # Chat équipe
│   ├── notifications.php   # Système notifications
│   ├── admin.php           # Panel admin
│   └── settings.php        # Paramètres utilisateur
└── README.md               # Documentation
```

## 👤 Gestion des Rôles

### 🔴 **Administrateur**
- Accès complet à toutes les fonctionnalités
- Gestion des utilisateurs (création, modification, désactivation)
- Accès aux logs système et statistiques
- Interface d'administration dédiée

### 🔵 **Support**
- Accès à toutes les données collaboratives
- Création et modification de tous les contenus
- Pas d'accès à la gestion des utilisateurs

### ⚪ **Employé**
- Accès aux données collaboratives
- Création et modification de contenus
- Interface standard sans administration

## 🎯 Cas d'Usage

### 🏢 **Agence Web/Digital**
- Suivi projets clients avec budgets
- Facturation et suivi CA par client
- Communication d'équipe centralisée
- Base de connaissances techniques

### 🛠️ **PME/Freelance Équipe**
- Gestion collaborative des clients
- Suivi financier transparent
- Organisation des tâches partagées
- Documentation interne

### 📊 **Consultant/Service**
- Projets multi-clients
- Suivi temps et facturation
- Communication client/équipe
- Reporting et statistiques

## 🔧 Personnalisation

### Thèmes et Couleurs
Le design utilise des variables CSS modifiables dans `index.php` :
```css
:root {
    --primary: #8b5cf6;      /* Violet principal */
    --primary-dark: #7c3aed;  /* Violet foncé */
    --secondary: #f3f4f6;     /* Gris clair */
    --accent: #fbbf24;        /* Accent jaune */
}
```

### Extensions Possibles
- API REST pour applications mobiles
- Intégration calendrier (Google Calendar, Outlook)
- Export PDF des rapports
- Notifications par email
- Module CRM avancé

## 🐛 Dépannage

### Problèmes Courants

**Erreur de connexion base de données**
- Vérifiez les paramètres dans `config.php`
- Assurez-vous que MySQL est démarré
- Vérifiez les permissions utilisateur

**Page blanche**
- Activez l'affichage des erreurs PHP
- Vérifiez les logs serveur
- Assurez-vous que toutes les extensions PHP sont installées

**Notifications non affichées**
- Vérifiez que JavaScript est activé
- Assurez-vous que les fonctions sont bien chargées

## 📄 Licence

Ce projet est développé par **Neelps** et distribué pour usage commercial et personnel.

## 🤝 Support

Pour toute question ou support :
- Documentation complète incluse
- Code commenté et structuré
- Architecture modulaire et extensible

---

**MyBizPanel v1.0.0** - *Système de gestion collaborative moderne*
*Développé avec ❤️ par Neelps*