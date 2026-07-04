# Guide de test — SecureVault

Ce document décrit comment tester chaque fonctionnalité de SecureVault, soit via l'**interface web** (navigateur sur http://localhost:8080), soit via l'**API REST** (curl / Postman).

> **Prérequis :** l'application tourne (`make up`) et les migrations sont appliquées (`make migrate`).  
> **E-mails en dev :** tous les e-mails (vérification, 2FA) sont capturés par Mailpit — http://localhost:8025

---

## Table des matières

1. [Inscription et vérification d'e-mail](#1-inscription-et-vérification-de-mail)
2. [Connexion / déconnexion](#2-connexion--déconnexion)
3. [Authentification à deux facteurs (2FA)](#3-authentification-à-deux-facteurs-2fa)
4. [Profil utilisateur](#4-profil-utilisateur)
5. [Coffres-forts](#5-coffres-forts)
6. [Mots de passe](#6-mots-de-passe)
7. [Partage de coffres](#7-partage-de-coffres)
8. [Alertes de sécurité](#8-alertes-de-sécurité)
9. [Notifications](#9-notifications)
10. [Administration](#10-administration)
11. [API REST — authentification JWT](#11-api-rest--authentification-jwt)
12. [API REST — coffres](#12-api-rest--coffres)
13. [API REST — mots de passe](#13-api-rest--mots-de-passe)

---

## 1. Inscription et vérification d'e-mail

> Disponible uniquement via l'interface web (pas de route d'inscription API).

### Interface web

1. Aller sur http://localhost:8080/register
2. Remplir le formulaire (prénom, nom, e-mail, mot de passe)
3. Soumettre → page "Vérifiez votre e-mail"
4. Ouvrir **Mailpit** sur http://localhost:8025
5. Cliquer sur le lien de vérification dans l'e-mail reçu
6. Redirection vers `/login` → se connecter

**Résultat attendu :** connexion réussie, accès au dashboard.

**Cas d'erreur — accès sans vérification :**
1. S'inscrire sans cliquer sur le lien
2. Se connecter immédiatement
3. **Attendu :** redirection vers `/verify/pending` (page "En attente de vérification")
4. Cliquer "Renvoyer l'e-mail" → nouvel e-mail dans Mailpit

---

## 2. Connexion / déconnexion

### Interface web

1. Aller sur http://localhost:8080/login
2. Saisir e-mail et mot de passe
3. Soumettre → redirection vers `/dashboard`

**Déconnexion :** menu utilisateur → "Se déconnecter" → `/logout`

**Cas d'erreur — mauvais mot de passe :**
- **Attendu :** message d'erreur sur la page de login, pas de redirection

**Cas d'erreur — accès protégé sans connexion :**
1. Accéder directement à http://localhost:8080/dashboard sans être connecté
2. **Attendu :** redirection vers `/login`

### API

```bash
# Connexion (obtenir le token JWT)
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "MonMotDePasse1!"}'
```

**Réponse attendue (200) :**
```json
{ "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..." }
```

**Mauvais mot de passe (401) :**
```json
{ "code": 401, "message": "Invalid credentials." }
```

**Sans token sur une route protégée (401) :**
```bash
curl http://localhost:8080/api/v1/vaults
# → 401 Unauthorized
```

> Stocker le token pour les prochaines commandes :
> ```bash
> TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
>   -H "Content-Type: application/json" \
>   -d '{"email": "user@example.com", "password": "MonMotDePasse1!"}' \
>   | grep -o '"token":"[^"]*' | cut -d'"' -f4)
> ```

---

## 3. Authentification à deux facteurs (2FA)

> La 2FA s'active/désactive depuis l'interface web uniquement.

### Activer la 2FA

1. Se connecter sur http://localhost:8080
2. Aller sur `/alerts`
3. Section "Conseil de sécurité" → cliquer **"Activer maintenant"**
4. **Attendu :** message de confirmation, section passe au vert "Protection Maximale Activée"

### Tester le flux 2FA à la connexion

1. Se déconnecter puis se reconnecter
2. **Attendu :** redirection vers `/2fa/verify` (pas vers `/dashboard`)
3. Ouvrir Mailpit http://localhost:8025 → récupérer le code à 6 chiffres
4. Saisir le code sur `/2fa/verify` → soumettre
5. **Attendu :** redirection vers `/dashboard`

**Code incorrect :**
- Saisir `000000` → **Attendu :** message d'erreur "Code incorrect ou expiré"

**Code expiré (TTL 10 min) :**
- Attendre plus de 10 minutes puis soumettre le code
- **Attendu :** message d'erreur

**Renvoyer un code :**
- Sur `/2fa/verify` → cliquer "Renvoyer un nouveau code"
- **Attendu :** nouveau code dans Mailpit, ancien code invalidé

**Accès bloqué pendant la 2FA :**
- Après login avec 2FA activée, tenter d'accéder à `/vaults` directement
- **Attendu :** redirection vers `/2fa/verify`

### Désactiver la 2FA

1. Aller sur `/alerts`
2. Section "Protection Maximale Activée" → cliquer **"Désactiver"**
3. **Attendu :** message de confirmation, retour à la section indigo "Activer la 2FA"

---

## 4. Profil utilisateur

### Interface web

1. Aller sur http://localhost:8080/profile
2. **Modifier les informations personnelles :** changer prénom/nom → soumettre
3. **Changer le mot de passe :** remplir "Mot de passe actuel" + "Nouveau mot de passe" → soumettre
4. **Attendu :** message de succès, données mises à jour

**Cas d'erreur — mauvais mot de passe actuel :**
- Saisir un mot de passe actuel incorrect → **Attendu :** message d'erreur

---

## 5. Coffres-forts

### Interface web

**Créer un coffre :**
1. Aller sur http://localhost:8080/vaults
2. Remplir le formulaire "Nouveau coffre" (nom, description optionnelle) → soumettre
3. **Attendu :** le coffre apparaît dans la liste

**Voir le contenu d'un coffre :**
1. Cliquer sur un coffre dans la liste → `/vaults/{id}`
2. **Attendu :** page détail avec les entrées de mots de passe et les options de gestion

**Renommer un coffre :**
1. Sur la page du coffre → formulaire d'édition → modifier le nom → soumettre
2. **Attendu :** nom mis à jour

**Archiver un coffre :**
1. Sur la page du coffre → bouton "Archiver"
2. **Attendu :** coffre marqué comme archivé, badge visible

**Supprimer un coffre :**
1. Sur la page du coffre → bouton "Supprimer" → confirmer
2. **Attendu :** retour sur `/vaults`, coffre supprimé

### API

```bash
# Lister les coffres
curl http://localhost:8080/api/v1/vaults \
  -H "Authorization: Bearer $TOKEN"

# Créer un coffre
curl -X POST http://localhost:8080/api/v1/vaults \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Mon coffre perso", "description": "Identifiants personnels"}'
# → 201 Created : {"id": 1, "name": "Mon coffre perso", ...}

# Voir un coffre
curl http://localhost:8080/api/v1/vaults/1 \
  -H "Authorization: Bearer $TOKEN"

# Renommer / archiver
curl -X PATCH http://localhost:8080/api/v1/vaults/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Nouveau nom", "archived": true}'

# Supprimer
curl -X DELETE http://localhost:8080/api/v1/vaults/1 \
  -H "Authorization: Bearer $TOKEN"
# → 204 No Content

# Accès à un coffre d'un autre utilisateur → 403
curl http://localhost:8080/api/v1/vaults/99 \
  -H "Authorization: Bearer $TOKEN"
# → {"error": "Access denied."}
```

---

## 6. Mots de passe

### Interface web

**Ajouter une entrée :**
1. Aller sur `/vaults/{id}`
2. Formulaire "Ajouter un mot de passe" → remplir titre, identifiant, URL, mot de passe, notes → soumettre
3. **Attendu :** entrée ajoutée à la liste, mot de passe chiffré en base

**Révéler un mot de passe :**
1. Sur la page du coffre → bouton "Révéler" sur une entrée
2. **Attendu :** le mot de passe déchiffré s'affiche (requête vers `/passwords/{id}/decrypt`)

**Modifier une entrée :**
1. Bouton "Modifier" sur une entrée → formulaire → modifier les champs → soumettre
2. **Attendu :** données mises à jour

**Supprimer une entrée :**
1. Bouton "Supprimer" sur une entrée → confirmer
2. **Attendu :** entrée supprimée

**Vue globale :**
1. Aller sur http://localhost:8080/passwords
2. **Attendu :** liste de toutes les entrées de tous les coffres de l'utilisateur

### API

```bash
# Lister les entrées (sans mot de passe déchiffré)
curl http://localhost:8080/api/v1/vaults/1/passwords \
  -H "Authorization: Bearer $TOKEN"

# Créer une entrée
curl -X POST http://localhost:8080/api/v1/vaults/1/passwords \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "GitHub",
    "username": "johndoe",
    "password": "MonSuperSecret!",
    "url": "https://github.com",
    "notes": "Compte pro"
  }'
# → 201 : {"id": 5, "title": "GitHub", ...} (sans le mot de passe)

# Voir une entrée avec mot de passe déchiffré
curl http://localhost:8080/api/v1/vaults/1/passwords/5 \
  -H "Authorization: Bearer $TOKEN"
# → {"id": 5, "title": "GitHub", "password": "MonSuperSecret!", ...}

# Modifier (titre, username, url, notes, favorite, password)
curl -X PATCH http://localhost:8080/api/v1/vaults/1/passwords/5 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title": "GitHub Pro", "favorite": true}'

# Supprimer
curl -X DELETE http://localhost:8080/api/v1/vaults/1/passwords/5 \
  -H "Authorization: Bearer $TOKEN"
# → 204 No Content

# Validation — titre vide → 422
curl -X POST http://localhost:8080/api/v1/vaults/1/passwords \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"username": "foo", "password": "bar"}'
# → {"error": "title and password are required."}
```

---

## 7. Partage de coffres

> Disponible uniquement via l'interface web. L'utilisateur destinataire doit être inscrit.

**Partager un coffre :**
1. Aller sur `/vaults/{id}`
2. Onglet ou section "Partages" → `/vaults/{id}/shares`
3. Saisir l'e-mail du destinataire + choisir la permission (`VIEW`, `EDIT` ou `DELETE`)
4. Soumettre → invitation envoyée
5. **Attendu :** invitation apparaît dans la liste en attente

**Accepter une invitation (côté destinataire) :**
1. Se connecter avec le compte destinataire
2. Aller sur http://localhost:8080/shares
3. **Attendu :** invitation en attente visible
4. Cliquer "Accepter"
5. **Attendu :** le coffre partagé apparaît dans la liste des coffres

**Refuser une invitation :**
1. Sur `/shares` → cliquer "Refuser"
2. **Attendu :** invitation supprimée

**Révoquer un partage :**
1. Propriétaire → aller sur `/vaults/{id}/shares`
2. Cliquer "Révoquer" sur un partage actif
3. **Attendu :** partage supprimé, le destinataire n'a plus accès

**Tester les permissions :**
- Partager avec `VIEW` → le destinataire peut voir mais pas modifier ni supprimer
- Partager avec `EDIT` → peut modifier mais pas supprimer
- Tenter une action non autorisée → **Attendu :** message "Accès refusé" (flash d'erreur)

---

## 8. Alertes de sécurité

### Interface web

1. Aller sur http://localhost:8080/alerts
2. **Attendu :** compteurs par niveau (Critique, Avertissement, Information) + liste des alertes

**Marquer une alerte comme lue :**
1. Survol d'une alerte → lien "Marquer comme lu"
2. **Attendu :** alerte grisée, compteur mis à jour

**Marquer toutes les alertes comme lues :**
1. Bouton "Marquer tout comme lu" (en haut, visible si au moins une alerte non lue)
2. **Attendu :** toutes les alertes grisées

**Ignorer une alerte :**
1. Survol d'une alerte → lien "Ignorer"
2. **Attendu :** alerte supprimée de la liste

---

## 9. Notifications

### Interface web

1. Aller sur http://localhost:8080/notifications
2. **Attendu :** liste des notifications (connexion, partage reçu, activité)

**Marquer une notification comme lue :**
1. Cliquer le bouton de lecture sur une notification
2. **Attendu :** notification mise à jour

**Marquer toutes comme lues :**
1. Bouton "Tout marquer comme lu"

**Ignorer une notification :**
1. Bouton "Ignorer" → notification supprimée de la liste

---

## 10. Administration

> Requiert le rôle `ROLE_ADMIN`. À attribuer via `make db-setup` (fixtures) ou directement en BDD.

### Interface web

1. Aller sur http://localhost:8080/admin
2. **Attendu :** tableau de bord EasyAdmin avec les sections suivantes :

| Section | URL | Ce qu'on peut tester |
| :--- | :--- | :--- |
| Utilisateurs | `/admin/user` | Lister, créer, modifier (rôles, e-mail vérifié), supprimer |
| Coffres | `/admin/vault` | Lister tous les coffres de tous les utilisateurs |
| Alertes | `/admin/alert` | Lister, modifier, supprimer des alertes |
| Journal d'activité | `/admin/activity-log` | Consulter toutes les actions enregistrées |
| Tentatives de connexion | `/admin/login-attempt` | Consulter les tentatives (IP, date, succès/échec) |

**Accès admin sans le rôle :**
1. Se connecter avec un compte normal
2. Accéder à http://localhost:8080/admin
3. **Attendu :** erreur 403 Forbidden

---

## 11. API REST — authentification JWT

```bash
BASE=http://localhost:8080

# 1. Obtenir un token
curl -s -X POST $BASE/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "MonMotDePasse1!"}'
# → {"token": "eyJ..."}

# Stocker le token dans une variable
TOKEN=$(curl -s -X POST $BASE/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "MonMotDePasse1!"}' \
  | grep -o '"token":"[^"]*' | cut -d'"' -f4)

# 2. Vérifier que le token fonctionne
curl $BASE/api/v1/vaults -H "Authorization: Bearer $TOKEN"

# 3. Token expiré ou invalide → 401
curl $BASE/api/v1/vaults -H "Authorization: Bearer token_invalide"
# → {"code":401,"message":"Invalid JWT Token"}

# 4. Sans header → 401
curl $BASE/api/v1/vaults
# → {"code":401,"message":"JWT Token not found"}
```

---

## 12. API REST — coffres

```bash
BASE=http://localhost:8080
AUTH="-H \"Authorization: Bearer $TOKEN\""

# Lister
curl $BASE/api/v1/vaults -H "Authorization: Bearer $TOKEN"
# → [{"id":1,"name":"...","entries_count":0,...}]

# Créer
curl -X POST $BASE/api/v1/vaults \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test Vault"}'
# → 201 {"id": 2, "name": "Test Vault", "archived": false, ...}

# Créer sans nom → 422
curl -X POST $BASE/api/v1/vaults \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"description": "pas de nom"}'
# → 422 {"error": "name is required."}

# Voir
curl $BASE/api/v1/vaults/2 -H "Authorization: Bearer $TOKEN"

# Mettre à jour
curl -X PATCH $BASE/api/v1/vaults/2 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Vault renommé", "archived": false}'
# → 200

# Mettre à jour avec nom vide → 422
curl -X PATCH $BASE/api/v1/vaults/2 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": ""}'
# → 422

# Supprimer
curl -X DELETE $BASE/api/v1/vaults/2 -H "Authorization: Bearer $TOKEN"
# → 204

# Accéder au coffre supprimé → 404
curl $BASE/api/v1/vaults/2 -H "Authorization: Bearer $TOKEN"
# → 404

# Accéder au coffre d'un autre utilisateur → 403
curl $BASE/api/v1/vaults/99 -H "Authorization: Bearer $TOKEN"
# → 403 {"error": "Access denied."}
```

---

## 13. API REST — mots de passe

```bash
VAULT_ID=1   # Remplacer par un vrai ID de coffre

# Lister (vide au départ)
curl $BASE/api/v1/vaults/$VAULT_ID/passwords \
  -H "Authorization: Bearer $TOKEN"
# → []

# Créer
curl -X POST $BASE/api/v1/vaults/$VAULT_ID/passwords \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Gmail",
    "username": "john@gmail.com",
    "password": "Sup3rS3cr3t!",
    "url": "https://mail.google.com",
    "notes": "Compte perso",
    "favorite": false
  }'
# → 201 {"id":3,"title":"Gmail","username":"john@gmail.com",...} (sans le champ "password")

ENTRY_ID=3   # Remplacer par l'ID retourné

# Voir avec mot de passe déchiffré
curl $BASE/api/v1/vaults/$VAULT_ID/passwords/$ENTRY_ID \
  -H "Authorization: Bearer $TOKEN"
# → {"id":3,"title":"Gmail","password":"Sup3rS3cr3t!",...}

# Modifier le titre et marquer en favori
curl -X PATCH $BASE/api/v1/vaults/$VAULT_ID/passwords/$ENTRY_ID \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title": "Gmail Pro", "favorite": true}'
# → 200 {"id":3,"title":"Gmail Pro","favorite":true,...}

# Changer le mot de passe
curl -X PATCH $BASE/api/v1/vaults/$VAULT_ID/passwords/$ENTRY_ID \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"password": "N0uv3auMdp!"}'
# → 200 (le nouveau mot de passe est re-chiffré en base)

# Vérifier que le nouveau mot de passe est bien stocké
curl $BASE/api/v1/vaults/$VAULT_ID/passwords/$ENTRY_ID \
  -H "Authorization: Bearer $TOKEN"
# → {"password": "N0uv3auMdp!", ...}

# Créer sans titre → 422
curl -X POST $BASE/api/v1/vaults/$VAULT_ID/passwords \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"password": "abc"}'
# → 422 {"error": "title and password are required."}

# Modifier avec titre vide → 422
curl -X PATCH $BASE/api/v1/vaults/$VAULT_ID/passwords/$ENTRY_ID \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title": ""}'
# → 422

# Supprimer
curl -X DELETE $BASE/api/v1/vaults/$VAULT_ID/passwords/$ENTRY_ID \
  -H "Authorization: Bearer $TOKEN"
# → 204

# Vérifier suppression
curl $BASE/api/v1/vaults/$VAULT_ID/passwords/$ENTRY_ID \
  -H "Authorization: Bearer $TOKEN"
# → 404
```
