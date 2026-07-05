# Scénarios de tests manuels — SecureVault

Ce document liste les scénarios de tests manuels à exécuter pour valider chaque fonctionnalité.  
Chaque scénario précise les **préconditions**, les **étapes**, le **résultat attendu** et le **résultat obtenu** (à remplir lors du test).

> **Environnement :** http://localhost:8080 — `make up` + `make migrate` + `make fixtures`  
> **E-mails :** http://localhost:8025 (Mailpit)

---

## Légende

| Statut | Signification |
| :---: | :--- |
| ✅ | Passé |
| ❌ | Échoué |
| ⚠️ | Partiellement passé / à revoir |
| — | Non testé |

---

## Module 1 — Inscription

### TC-01 — Inscription valide

**Préconditions :** aucun compte avec cet e-mail  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Accéder à `/register` | Formulaire d'inscription affiché | — |
| 2 | Saisir prénom, nom, e-mail valide, mot de passe ≥ 8 caractères | Champs remplis sans erreur | — |
| 3 | Soumettre | Redirection vers la page "Vérifiez votre e-mail" | — |
| 4 | Ouvrir Mailpit (:8025) | Un e-mail de vérification est présent | — |
| 5 | Cliquer sur le lien de vérification | Redirection vers `/login` avec message de succès | — |
| 6 | Se connecter | Accès au dashboard accordé | — |

---

### TC-02 — Inscription avec e-mail déjà utilisé

**Préconditions :** un compte existe avec cet e-mail  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Accéder à `/register` | Formulaire affiché | — |
| 2 | Saisir un e-mail déjà enregistré | — | — |
| 3 | Soumettre | Message d'erreur "Cet e-mail est déjà utilisé" — pas de redirection | — |

---

### TC-03 — Accès à une page protégée avant vérification d'e-mail

**Préconditions :** compte créé, lien de vérification non cliqué  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Se connecter avec le nouveau compte | Connexion réussie | — |
| 2 | Accéder à `/dashboard` | Redirection vers `/verify/pending` | — |
| 3 | Cliquer "Renvoyer l'e-mail" | Nouvel e-mail dans Mailpit | — |
| 4 | Cliquer le lien dans le nouvel e-mail | Accès au dashboard accordé | — |

---

## Module 2 — Connexion / Déconnexion

### TC-04 — Connexion avec identifiants valides

**Préconditions :** compte vérifié existant  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Accéder à `/login` | Formulaire de connexion affiché | — |
| 2 | Saisir e-mail et mot de passe corrects | — | — |
| 3 | Soumettre | Redirection vers `/dashboard` | — |

---

### TC-05 — Connexion avec mauvais mot de passe

**Méthode :** Interface web et API

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | **Web :** saisir un mot de passe incorrect → soumettre | Message d'erreur sur la page login, pas de redirection | — |
| 2 | **API :** `POST /api/v1/auth/login` avec mauvais mot de passe | HTTP 401 — `{"code":401,"message":"Invalid credentials."}` | — |

---

### TC-06 — Déconnexion

**Préconditions :** connecté  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Cliquer "Se déconnecter" dans le menu | Redirection vers `/` | — |
| 2 | Accéder à `/dashboard` | Redirection vers `/login` | — |

---

### TC-07 — Accès protégé sans connexion

**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Accéder directement à `/dashboard` sans être connecté | Redirection vers `/login` | — |
| 2 | Accéder directement à `/vaults` sans être connecté | Redirection vers `/login` | — |

---

## Module 3 — Authentification à deux facteurs (2FA)

### TC-08 — Activation de la 2FA

**Préconditions :** connecté, 2FA désactivée  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Aller sur `/alerts` | Section "Conseil de sécurité" visible | — |
| 2 | Cliquer "Activer maintenant" | Message de confirmation flash | — |
| 3 | La section passe au vert "Protection Maximale Activée" | Bouton "Désactiver" visible | — |

---

### TC-09 — Flux de connexion avec 2FA activée

**Préconditions :** 2FA activée sur le compte  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Se déconnecter puis se reconnecter | Redirection vers `/2fa/verify` (et non `/dashboard`) | — |
| 2 | Ouvrir Mailpit (:8025) | E-mail avec un code à 6 chiffres reçu | — |
| 3 | Saisir le code correct et soumettre | Redirection vers `/dashboard` | — |
| 4 | Accéder à `/vaults` | Accès accordé normalement | — |

---

### TC-10 — Code 2FA incorrect

**Préconditions :** en attente de vérification 2FA sur `/2fa/verify`  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Saisir `000000` comme code | Message d'erreur "Code incorrect ou expiré" | — |
| 2 | La page `/2fa/verify` reste affichée | Pas de redirection vers le dashboard | — |

---

### TC-11 — Renvoi du code 2FA

**Préconditions :** sur la page `/2fa/verify`  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Cliquer "Renvoyer un nouveau code" | Message de confirmation flash | — |
| 2 | Ouvrir Mailpit | Un nouveau code est reçu | — |
| 3 | Saisir l'ancien code | Message d'erreur (ancien code invalidé) | — |
| 4 | Saisir le nouveau code | Redirection vers `/dashboard` | — |

---

### TC-12 — Blocage des pages pendant la 2FA

**Préconditions :** connecté avec 2FA activée, code non encore saisi  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Tenter d'accéder à `/vaults` | Redirection vers `/2fa/verify` | — |
| 2 | Tenter d'accéder à `/dashboard` | Redirection vers `/2fa/verify` | — |
| 3 | Accéder à `/logout` | Déconnexion possible (non bloquée) | — |

---

### TC-13 — Désactivation de la 2FA

**Préconditions :** 2FA activée  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Aller sur `/alerts` | Bouton "Désactiver" visible | — |
| 2 | Cliquer "Désactiver" | Message flash de confirmation | — |
| 3 | Se déconnecter puis reconnecter | Redirection directe vers `/dashboard` (pas de code demandé) | — |

---

## Module 4 — Coffres-forts

### TC-14 — Création d'un coffre

**Préconditions :** connecté  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Aller sur `/vaults` | Liste des coffres affichée | — |
| 2 | Remplir le formulaire "Nouveau coffre" avec un nom | — | — |
| 3 | Soumettre | Coffre ajouté à la liste | — |

---

### TC-15 — Création d'un coffre via API

**Méthode :** API (curl / Postman)

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | `POST /api/v1/vaults` avec `{"name":"Vault API"}` | HTTP 201 — objet coffre retourné avec un `id` | — |
| 2 | `POST /api/v1/vaults` sans `name` | HTTP 422 — `{"error":"name is required."}` | — |
| 3 | `POST /api/v1/vaults` sans token | HTTP 401 | — |

---

### TC-16 — Modification et suppression d'un coffre

**Méthode :** Interface web + API

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | **Web :** renommer un coffre depuis sa page de détail | Nouveau nom affiché | — |
| 2 | **Web :** archiver un coffre | Badge "Archivé" visible | — |
| 3 | **API :** `PATCH /api/v1/vaults/{id}` avec `{"name":"Nouveau"}` | HTTP 200 — nom mis à jour | — |
| 4 | **API :** `PATCH /api/v1/vaults/{id}` avec `{"name":""}` | HTTP 422 | — |
| 5 | **Web :** supprimer un coffre | Coffre absent de la liste | — |
| 6 | **API :** `DELETE /api/v1/vaults/{id}` | HTTP 204 | — |
| 7 | **API :** `GET /api/v1/vaults/{id}` après suppression | HTTP 404 | — |

---

### TC-17 — Isolation entre utilisateurs (coffres)

**Préconditions :** deux comptes distincts, chacun avec un coffre  
**Méthode :** API

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Se connecter avec l'utilisateur A → obtenir token A | — | — |
| 2 | `GET /api/v1/vaults/{id_coffre_B}` avec token A | HTTP 403 — `{"error":"Access denied."}` | — |
| 3 | `DELETE /api/v1/vaults/{id_coffre_B}` avec token A | HTTP 403 | — |

---

## Module 5 — Mots de passe

### TC-18 — Ajout et affichage d'une entrée

**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Aller sur la page d'un coffre | Formulaire d'ajout visible | — |
| 2 | Remplir titre, identifiant, URL, mot de passe | — | — |
| 3 | Soumettre | Entrée visible dans la liste du coffre | — |
| 4 | Cliquer "Révéler" sur l'entrée | Mot de passe déchiffré affiché | — |

---

### TC-19 — CRUD mots de passe via API

**Méthode :** API

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | `POST /api/v1/vaults/{id}/passwords` avec title + password | HTTP 201 — réponse sans le champ `password` | — |
| 2 | `POST` sans title | HTTP 422 | — |
| 3 | `GET /api/v1/vaults/{id}/passwords/{entryId}` | HTTP 200 — champ `password` déchiffré présent | — |
| 4 | `PATCH` avec `{"title":"Nouveau titre"}` | HTTP 200 — titre mis à jour | — |
| 5 | `PATCH` avec `{"password":"NouveauMdp!"}` puis `GET` | Nouveau mot de passe retourné déchiffré | — |
| 6 | `PATCH` avec `{"title":""}` | HTTP 422 | — |
| 7 | `DELETE /api/v1/vaults/{id}/passwords/{entryId}` | HTTP 204 | — |
| 8 | `GET` après suppression | HTTP 404 | — |

---

### TC-20 — Isolation entre utilisateurs (mots de passe)

**Méthode :** API

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | `GET /api/v1/vaults/{id_coffre_B}/passwords` avec token A | HTTP 403 | — |
| 2 | `PATCH /api/v1/vaults/{id_coffre_B}/passwords/1` avec token A | HTTP 403 | — |
| 3 | `DELETE /api/v1/vaults/{id_coffre_B}/passwords/1` avec token A | HTTP 403 | — |

---

## Module 6 — Partage de coffres

### TC-21 — Envoi et acceptation d'une invitation

**Préconditions :** deux comptes vérifiés (Alice = propriétaire, Bob = destinataire)  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Connecté en tant qu'Alice → aller sur `/vaults/{id}/shares` | Page de partage affichée | — |
| 2 | Saisir l'e-mail de Bob + permission `VIEW` → soumettre | Invitation en attente visible | — |
| 3 | Se connecter en tant que Bob → aller sur `/shares` | Invitation en attente visible | — |
| 4 | Cliquer "Accepter" | Le coffre d'Alice apparaît dans les coffres de Bob | — |

---

### TC-22 — Refus d'une invitation

**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Bob reçoit une invitation → cliquer "Refuser" | Invitation supprimée, coffre absent des coffres de Bob | — |

---

### TC-23 — Révocation d'un partage

**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Alice → `/vaults/{id}/shares` → cliquer "Révoquer" sur le partage avec Bob | Partage supprimé | — |
| 2 | Bob accède à `/vaults` | Le coffre d'Alice n'apparaît plus | — |

---

### TC-24 — Respect des permissions de partage

**Préconditions :** Bob a accès au coffre d'Alice avec permission `VIEW`  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Bob tente de modifier une entrée du coffre d'Alice | Message d'erreur "Accès refusé" | — |
| 2 | Bob tente de supprimer une entrée | Message d'erreur "Accès refusé" | — |

---

## Module 7 — Profil

### TC-25 — Modification du profil

**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Aller sur `/profile` | Formulaire avec les informations actuelles | — |
| 2 | Modifier le prénom → soumettre | Message de succès, nouveau prénom affiché | — |

---

### TC-26 — Changement de mot de passe

**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Sur `/profile` → section mot de passe | Formulaire avec "Mot de passe actuel" et "Nouveau mot de passe" | — |
| 2 | Saisir le mot de passe actuel correct + nouveau mot de passe | — | — |
| 3 | Soumettre | Message de succès | — |
| 4 | Se déconnecter puis reconnecter avec le nouveau mot de passe | Connexion réussie | — |
| 5 | Tenter de se reconnecter avec l'ancien mot de passe | HTTP 401 / message d'erreur | — |

---

### TC-27 — Changement de mot de passe avec mauvais mot de passe actuel

**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Sur `/profile` → saisir un mot de passe actuel incorrect | — | — |
| 2 | Soumettre | Message d'erreur, mot de passe inchangé | — |

---

## Module 8 — Alertes et Notifications

### TC-28 — Consultation et gestion des alertes

**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Aller sur `/alerts` | Compteurs par niveau + liste des alertes | — |
| 2 | Survoler une alerte non lue → cliquer "Marquer comme lu" | Alerte grisée, compteur décrémenté | — |
| 3 | Cliquer "Marquer tout comme lu" | Toutes les alertes grisées | — |
| 4 | Survoler une alerte → cliquer "Ignorer" | Alerte disparaît de la liste | — |

---

### TC-29 — Consultation et gestion des notifications

**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Aller sur `/notifications` | Liste des notifications affichée | — |
| 2 | Cliquer sur "Marquer comme lu" sur une notification | Notification mise à jour | — |
| 3 | Cliquer "Tout marquer comme lu" | Toutes les notifications lues | — |
| 4 | Cliquer "Ignorer" sur une notification | Notification supprimée | — |

---

## Module 9 — Administration

### TC-30 — Accès admin avec le bon rôle

**Préconditions :** utilisateur avec `ROLE_ADMIN`  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Accéder à `/admin` | Tableau de bord EasyAdmin affiché | — |
| 2 | Aller sur `/admin/user` | Liste de tous les utilisateurs | — |
| 3 | Modifier le rôle d'un utilisateur | Modification enregistrée | — |
| 4 | Aller sur `/admin/vault` | Liste de tous les coffres | — |
| 5 | Aller sur `/admin/activity-log` | Journal d'activité affiché | — |
| 6 | Aller sur `/admin/login-attempt` | Tentatives de connexion affichées | — |

---

### TC-31 — Accès admin refusé sans le rôle

**Préconditions :** utilisateur sans `ROLE_ADMIN`  
**Méthode :** Interface web

| # | Étape | Résultat attendu | Statut |
| - | :--- | :--- | :---: |
| 1 | Accéder à `/admin` | HTTP 403 Forbidden | — |

---

## Récapitulatif

| ID | Scénario | Module | Méthode | Statut |
| :- | :--- | :--- | :--- | :---: |
| TC-01 | Inscription valide | Inscription | Web | — |
| TC-02 | Inscription e-mail existant | Inscription | Web | — |
| TC-03 | Accès sans vérification e-mail | Inscription | Web | — |
| TC-04 | Connexion valide | Connexion | Web | — |
| TC-05 | Mauvais mot de passe | Connexion | Web + API | — |
| TC-06 | Déconnexion | Connexion | Web | — |
| TC-07 | Accès protégé sans session | Connexion | Web | — |
| TC-08 | Activation 2FA | 2FA | Web | — |
| TC-09 | Flux connexion avec 2FA | 2FA | Web | — |
| TC-10 | Code 2FA incorrect | 2FA | Web | — |
| TC-11 | Renvoi code 2FA | 2FA | Web | — |
| TC-12 | Blocage pages pendant 2FA | 2FA | Web | — |
| TC-13 | Désactivation 2FA | 2FA | Web | — |
| TC-14 | Création coffre (web) | Coffres | Web | — |
| TC-15 | Création coffre (API) | Coffres | API | — |
| TC-16 | Modification + suppression coffre | Coffres | Web + API | — |
| TC-17 | Isolation utilisateurs (coffres) | Coffres | API | — |
| TC-18 | Ajout + affichage mot de passe | Mots de passe | Web | — |
| TC-19 | CRUD mots de passe (API) | Mots de passe | API | — |
| TC-20 | Isolation utilisateurs (mots de passe) | Mots de passe | API | — |
| TC-21 | Envoi + acceptation invitation | Partage | Web | — |
| TC-22 | Refus invitation | Partage | Web | — |
| TC-23 | Révocation partage | Partage | Web | — |
| TC-24 | Respect permissions partage | Partage | Web | — |
| TC-25 | Modification profil | Profil | Web | — |
| TC-26 | Changement mot de passe | Profil | Web | — |
| TC-27 | Mauvais mot de passe actuel | Profil | Web | — |
| TC-28 | Alertes | Alertes | Web | — |
| TC-29 | Notifications | Notifications | Web | — |
| TC-30 | Accès admin (rôle valide) | Admin | Web | — |
| TC-31 | Accès admin refusé | Admin | Web | — |
