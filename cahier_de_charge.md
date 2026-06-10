# SPÉCIFICATION FONCTIONNELLE

# SecureVault – Gestionnaire de mots de passe sécurisé

---

# 1. Présentation du projet

## 1.1 Contexte

La multiplication des services numériques impose aux utilisateurs de gérer un nombre croissant d'identifiants et de mots de passe.

Cette situation conduit souvent à :

* l'utilisation de mots de passe faibles ;
* la réutilisation des mêmes mots de passe ;
* le stockage non sécurisé des informations sensibles.

**SecureVault** est une application web permettant aux utilisateurs de stocker, organiser, partager et sécuriser leurs mots de passe dans des coffres numériques.

L'application s'inspire de solutions telles que :

* Bitwarden
* LastPass
* 1Password

## 1.2 Objectifs

Le système doit permettre :

* la gestion sécurisée des mots de passe ;
* l'organisation par catégories et tags ;
* le partage sécurisé de coffres ;
* la détection des risques de sécurité ;
* l'administration complète de la plateforme ;
* l'exposition de fonctionnalités via API REST.

---

# 2. Acteurs

## Visiteur

Peut :

* consulter la page d'accueil ;
* créer un compte ;
* se connecter.

## Utilisateur

Peut :

* gérer son profil ;
* créer des coffres ;
* gérer des mots de passe ;
* partager des coffres ;
* consulter les alertes ;
* utiliser l'API.

## Gestionnaire

Peut :

* administrer les coffres partagés ;
* gérer les permissions de partage.

## Administrateur

Peut :

* gérer les utilisateurs ;
* gérer les rôles ;
* gérer les permissions ;
* consulter les journaux ;
* consulter les statistiques ;
* accéder au back-office.

---

# 3. Entités du système

Le système contient **14 entités métier**.

## 3.1 User

### Description

Représente un utilisateur enregistré.

### Champs

* id
* email
* password
* firstName
* lastName
* isActive
* emailVerified
* createdAt
* updatedAt

### Relations

* ManyToMany Role
* OneToMany Vault
* OneToMany Notification
* OneToMany ActivityLog
* OneToMany SecurityAlert
* OneToMany LoginAttempt

---

## 3.2 Role

### Description

Définit un rôle fonctionnel.

### Exemples

* ROLE_USER
* ROLE_MANAGER
* ROLE_ADMIN

### Champs

* id
* name
* description

### Relations

* ManyToMany User
* ManyToMany Permission

---

## 3.3 Permission

### Description

Décrit une permission précise.

### Exemples

* USER_MANAGE
* USER_DELETE
* VAULT_CREATE
* VAULT_DELETE
* ALERT_MANAGE

### Champs

* id
* code
* description

### Relations

* ManyToMany Role

---

## 3.4 Vault

### Description

Représente un coffre contenant des mots de passe.

### Champs

* id
* name
* description
* archived
* createdAt
* updatedAt

### Relations

* ManyToOne User (owner)
* OneToMany PasswordEntry
* OneToMany SharedVault
* ManyToMany Tag

---

## 3.5 VaultPermission

### Description

Décrit un niveau d'accès à un coffre partagé.

### Champs

* id
* code
* name
* description

### Données initiales

| Code  | Description    |
| ----- | -------------- |
| READ  | Consultation   |
| WRITE | Modification   |
| ADMIN | Administration |

### Relations

* OneToMany SharedVault

---

## 3.6 SharedVault

### Description

Association entre un utilisateur et un coffre partagé.

### Champs

* id
* accepted
* sharedAt

### Relations

* ManyToOne User
* ManyToOne Vault
* ManyToOne VaultPermission

---

## 3.7 PasswordEntry

### Description

Stocke les informations d'accès à un service.

### Champs

* id
* title
* username
* encryptedPassword
* url
* notes
* favorite
* createdAt
* updatedAt

### Relations

* ManyToOne Vault
* ManyToMany Category
* ManyToMany Tag
* OneToMany PasswordHistory

---

## 3.8 Category

### Description

Permet d'organiser les mots de passe.

### Exemples

* Travail
* Banque
* Personnel
* Réseaux sociaux

### Champs

* id
* name
* color

### Relations

* ManyToMany PasswordEntry

---

## 3.9 Tag

### Description

Étiquettes libres utilisées pour classer les données.

### Exemples

* Important
* Projet Symfony
* Urgent
* Personnel

### Champs

* id
* name
* color

### Relations

* ManyToMany PasswordEntry
* ManyToMany Vault

---

## 3.10 SecurityAlert

### Description

Alerte de sécurité générée automatiquement.

### Types

* WeakPassword
* ReusedPassword
* BreachedPassword
* SuspiciousLogin

### Champs

* id
* type
* severity
* message
* resolved
* createdAt

### Relations

* ManyToOne User

---

## 3.11 Notification

### Description

Notification interne et historique d'envoi.

### Champs

* id
* title
* message
* type
* isRead
* isSent
* sentAt
* createdAt

### Relations

* ManyToOne User

---

## 3.12 ActivityLog

### Description

Historique des actions réalisées.

### Exemples

* Création d'un coffre
* Suppression d'un mot de passe
* Modification du profil

### Champs

* id
* action
* ipAddress
* userAgent
* createdAt

### Relations

* ManyToOne User

---

## 3.13 LoginAttempt

### Description

Historique des tentatives de connexion.

### Champs

* id
* ipAddress
* success
* createdAt

### Relations

* ManyToOne User

---

## 3.14 PasswordHistory

### Description

Historique des anciens mots de passe.

### Champs

* id
* previousPasswordHash
* changedAt

### Relations

* ManyToOne PasswordEntry

---

# 4. Relations principales

## ManyToMany

### User ↔ Role

Un utilisateur peut posséder plusieurs rôles.

### Role ↔ Permission

Un rôle peut contenir plusieurs permissions.

### PasswordEntry ↔ Category

Une entrée peut appartenir à plusieurs catégories.

### PasswordEntry ↔ Tag

Une entrée peut posséder plusieurs tags.

### Vault ↔ Tag

Un coffre peut posséder plusieurs tags.

## OneToMany / ManyToOne

* User → Vault
* User → Notification
* User → ActivityLog
* User → SecurityAlert
* User → LoginAttempt
* Vault → PasswordEntry
* Vault → SharedVault
* VaultPermission → SharedVault
* PasswordEntry → PasswordHistory

---

# 5. Fonctionnalités

## Gestion des comptes

* Inscription
* Validation email
* Connexion
* Déconnexion
* Réinitialisation du mot de passe
* Gestion du profil

## Gestion des coffres

* Création
* Modification
* Suppression
* Archivage
* Partage

## Gestion des mots de passe

* Création
* Consultation
* Modification
* Suppression

## Générateur de mots de passe

### Paramètres

* Longueur
* Majuscules
* Minuscules
* Chiffres
* Caractères spéciaux

## Recherche

Recherche par :

* Nom
* URL
* Catégorie
* Tag

## Tableau de bord

### Affichage

* Nombre de coffres
* Nombre de mots de passe
* Nombre d'alertes
* Dernières activités

---

# 6. Sécurité

## Authentification

Symfony Security

## Hashage

Argon2id

## Chiffrement

AES-256-GCM

## Contrôle d'accès

### Rôles

* ROLE_USER
* ROLE_MANAGER
* ROLE_ADMIN

### Voter

**VaultVoter**

Actions :

* VIEW
* EDIT
* DELETE
* SHARE

---

# 7. API REST

## Préfixe

```text
/api/v1
```

## Authentification

JWT

## Endpoints

* Authentification
* Utilisateurs
* Coffres
* Mots de passe
* Alertes
* Notifications

## Serializer Symfony

* Groups
* Normalization
* Denormalization

---

# 8. API Externe

## HaveIBeenPwned

### Objectif

Détecter les mots de passe compromis et générer des alertes.

---

# 9. Notifications Email

## Symfony Mailer

### Emails

* Confirmation d'inscription
* Réinitialisation de mot de passe
* Invitation à un coffre
* Alertes de sécurité

---

# 10. Administration

## EasyAdminBundle

### Gestion

* Utilisateurs
* Rôles
* Permissions
* Coffres
* Alertes
* Logs
* Statistiques

---

# 11. Services

* EncryptionService
* PasswordGeneratorService
* SecurityAlertService
* MailNotificationService
* ActivityLoggerService
* VaultSharingService

---

# 12. Event Subscribers

* LoginSubscriber
* ActivitySubscriber
* SecuritySubscriber

---

# 13. Tests

## Tests unitaires

* EncryptionServiceTest
* PasswordGeneratorServiceTest

## Tests fonctionnels

* LoginControllerTest
* VaultControllerTest

## Tests API

* Authentification JWT
* CRUD Coffres
* Gestion des permissions

---

# 14. CI/CD

## GitHub Actions

* Lint Symfony
* Lint Twig
* PHPStan
* PHPUnit

---

# 15. Déploiement

* VPS Linux

---

# 16. Livrables

* Cahier des charges
* UML
* MCD
* Code source
* Migrations Doctrine
* Fixtures
* Documentation API
* README
* Rapport de tests
* Application déployée

